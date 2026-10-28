#!/usr/bin/env python3
"""Read-only audit of the checksum-locked Stockland ERP 16.1.95 package."""
from __future__ import annotations
import argparse, hashlib, json, re, sys, zipfile
from collections import Counter
from pathlib import Path, PurePosixPath

EXPECTED_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
EXPECTED_SIZE = 1102405
ROOT = "stockland-erp-app/"
CONTRACT = {"total": 49, "core_operations": 29, "accounting_procurement": 16, "hr_payroll": 4}
SOURCES = {ROOT+"includes/class-stockland-activator.php":29,ROOT+"includes/class-stockland-accounting.php":16,ROOT+"includes/class-stockland-hr.php":4}
LEGACY = {"employees","employee_compensation","payroll_periods","payroll_lines"}

def require(ok, msg):
    if not ok: raise AssertionError(msg)

def run(path):
    raw=path.read_bytes()
    require(len(raw)==EXPECTED_SIZE,f"size mismatch: {len(raw)}")
    digest=hashlib.sha256(raw).hexdigest()
    require(digest==EXPECTED_SHA256,f"SHA-256 mismatch: {digest}")
    with zipfile.ZipFile(path) as z:
        require(z.testzip() is None,"ZIP CRC failure")
        items=[i.filename for i in z.infolist() if not i.is_dir()]
        require(len(items)==75 and len(items)==len(set(items)),"archive member count/uniqueness failed")
        require(all(PurePosixPath(n).parts[0]=="stockland-erp-app" and ".." not in PurePosixPath(n).parts for n in items),"unsafe/unexpected root")
        require(sum(n.endswith(".php") for n in items)==48,"expected 48 PHP files")
        required=set(SOURCES)|{ROOT+"RELEASE_MANIFEST.json",ROOT+"stockland-inventory.php",ROOT+"includes/class-stockland-authority.php",ROOT+"includes/class-stockland-audit.php",ROOT+"includes/class-stockland-workflow.php",ROOT+"includes/rest/class-rest-hr.php"}
        require(required.issubset(items),"required source member missing")
        manifest=json.loads(z.read(ROOT+"RELEASE_MANIFEST.json").decode())
        require(manifest.get("release")=="16.1.95","release identity mismatch")
        require(manifest.get("schema_contract")==CONTRACT,"schema contract mismatch")
        rows=[]
        for src in SOURCES:
            content=z.read(src).decode()
            rows.extend((m.group(1),src) for m in re.finditer(r"CREATE TABLE\s+\{\$p\}stock_([A-Za-z0-9_]+)\s*\(",content))
        require(len(rows)==49 and len({name for name,_ in rows})==49,"expected 49 unique static DDL declarations")
        by_src=Counter(src for _,src in rows)
        require(dict(by_src)==SOURCES,f"unexpected DDL split {dict(by_src)}")
        hr={name for name,src in rows if src==ROOT+"includes/class-stockland-hr.php"}
        require(hr==LEGACY,f"legacy HR table mismatch: {sorted(hr)}")
        fks=sum(len(re.findall(r"\bFOREIGN\s+KEY\s*\(",z.read(src).decode(),re.I)) for src in SOURCES)
    return {"audit_type":"read_only_archive_source_audit","live_data_verified":False,"installer":path.name,"size_bytes":len(raw),"sha256":digest,"package_files":75,"php_files":48,"release":"16.1.95","schema_contract":CONTRACT,"ddl_declarations":49,"ddl_source_counts":{Path(s).name:by_src[s] for s in SOURCES},"legacy_hr_transition_sources":sorted(hr),"explicit_fk_clauses_in_static_ddl":fks,"target_hr_table_count_approved":False,"notes":["Static archive only; not proof of installed live bytes.","Old HR tables are transition sources; no data or schema was changed.","Current Notion v0.5 supersedes historical fixed table counts."]}

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument("--package",required=True,type=Path)
    ap.add_argument("--json-out",type=Path)
    args=ap.parse_args()
    try: result=run(args.package)
    except Exception as e:
        print("FAIL: "+str(e),file=sys.stderr); return 1
    rendered=json.dumps(result,indent=2,sort_keys=True); print(rendered)
    if args.json_out:
        args.json_out.parent.mkdir(parents=True,exist_ok=True); args.json_out.write_text(rendered+"\n",encoding="utf-8")
    return 0
if __name__=="__main__": raise SystemExit(main())
