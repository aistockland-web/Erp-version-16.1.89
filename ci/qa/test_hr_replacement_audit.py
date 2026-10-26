#!/usr/bin/env python3
"""Regression tests for the read-only HR replacement baseline audit."""
from __future__ import annotations
import json, subprocess, sys, tempfile, zipfile
from pathlib import Path

SCRIPT=Path(__file__).resolve().parents[1]/"hr_replacement_audit.py"
CANONICAL_SHA="494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"

def run(installer: Path, out: Path, allow: bool=False, expected_sha: str=CANONICAL_SHA):
    cmd=[sys.executable,str(SCRIPT),"--installer",str(installer),"--output-dir",str(out),"--expected-sha256",expected_sha]
    if allow: cmd.append("--allow-noncanonical")
    return subprocess.run(cmd,text=True,capture_output=True)

def main():
    if len(sys.argv)!=2:
        raise SystemExit("Usage: test_hr_replacement_audit.py <canonical-installer.zip>")
    installer=Path(sys.argv[1]).resolve()
    with tempfile.TemporaryDirectory(prefix="stockland-hr-audit-test-") as td:
        root=Path(td)
        p=run(installer,root/"canonical")
        assert p.returncode==0,(p.stdout,p.stderr)
        report=json.loads((root/"canonical"/"hr_replacement_baseline_audit.json").read_text())
        assert report["installer"]["sha256"]==CANONICAL_SHA
        assert report["installer"]["member_count"]==75
        assert report["installer"]["php_member_count"]==48
        assert report["baseline_schema"]["manifest_total"]==49
        assert report["baseline_schema"]["registered_key_count"]==49
        assert report["baseline_schema"]["static_ddl_count"]==49
        assert len(report["baseline_schema"]["core_table_keys"])==29
        assert len(report["baseline_schema"]["finance_procurement_table_keys"])==16
        assert set(report["baseline_schema"]["legacy_hr_table_keys"])=={"employees","employee_compensation","payroll_periods","payroll_lines"}
        assert report["legacy_hr_routes"]["count"]==13
        assert not report["legacy_hr_routes"]["missing_permission_callback_lines"]
        assert (root/"canonical"/"hr_table_dependency_index.csv").stat().st_size>1000
        wrong=run(installer,root/"wrong-hash",allow=True,expected_sha="0"*64)
        assert wrong.returncode==0
        wr=json.loads((root/"wrong-hash"/"hr_replacement_baseline_audit.json").read_text())
        assert not wr["assertions"]["pass"]
        assert any("SHA mismatch" in f for f in wr["assertions"]["failures"])
        unsafe=root/"unsafe.zip"
        with zipfile.ZipFile(installer) as zin, zipfile.ZipFile(unsafe,"w",zipfile.ZIP_DEFLATED) as zout:
            for item in zin.infolist():
                zout.writestr(item.filename,zin.read(item.filename))
            zout.writestr("../unsafe.txt",b"not a runtime file")
        bad=run(unsafe,root/"unsafe",allow=True)
        assert bad.returncode==0
        br=json.loads((root/"unsafe"/"hr_replacement_baseline_audit.json").read_text())
        assert not br["assertions"]["pass"]
        assert any("unsafe archive path" in f for f in br["assertions"]["failures"])
    print("PASS HR replacement audit: canonical identity, 49/49 DDL parity, 13 legacy routes, wrong-hash and unsafe-path detection")

if __name__=="__main__": main()
