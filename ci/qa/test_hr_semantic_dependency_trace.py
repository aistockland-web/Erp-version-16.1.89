#!/usr/bin/env python3
"""Regression checks for the locked 16.1.95 semantic trace."""
import argparse,csv,hashlib,json,subprocess,sys,tempfile
from pathlib import Path
SHA="494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
def must(ok,msg):
 if not ok: raise SystemExit("FAIL: "+msg)
def main():
 p=argparse.ArgumentParser();p.add_argument("--package",required=True);a=p.parse_args()
 base=Path(a.package);script=Path(__file__).with_name("trace_hr_replacement_semantic_dependencies.py")
 must(base.is_file(),"baseline package missing")
 must(hashlib.sha256(base.read_bytes()).hexdigest()==SHA,"baseline checksum mismatch")
 with tempfile.TemporaryDirectory(prefix="stockland-hr-semantic-") as td:
  out=Path(td)/"out"
  r=subprocess.run([sys.executable,str(script),"--package",str(base),"--out-dir",str(out)],capture_output=True,text=True)
  must(r.returncode==0,"trace run failed: "+r.stdout+" "+r.stderr)
  d=json.loads((out/"hr_semantic_dependency_receipt_v0.1.json").read_text())
  must(d["direct_call_site_count"]==49,"need 49 call sites")
  must(d["direct_table_helper_call_counts"]=={"employees":14,"employee_compensation":5,"payroll_periods":17,"payroll_lines":13},"legacy counts changed")
  must(d["legacy_static_ddl_per_key"]=={"employees":1,"employee_compensation":1,"payroll_lines":1,"payroll_periods":1},"one DDL per key required")
  must(d["explicit_foreign_key_constraints_in_legacy_hr_ddl"]==0,"legacy FK contract changed")
  must(d["hr_rest_register_route_call_sites"]==13 and d["permission_callback_entries"]==15,"REST inventory changed")
  with (out/"hr_legacy_semantic_call_sites_v0.1.csv").open(encoding="utf-8-sig",newline="") as f: rows=list(csv.DictReader(f))
  must(len(rows)==49,"CSV row count")
  must(all(x["manual_review_required"].startswith("YES") for x in rows),"static hints must require manual review")
  must(len({(x["legacy_table"],x["file"],x["line"]) for x in rows})==49,"duplicate source call-site key")
  must(len(d["direct_call_site_source_files_by_table"]["employees"])==2,"employees crosses HR and System Health")
  must(len(d["direct_call_site_source_files_by_table"]["payroll_lines"])==1,"payroll line direct refs expected in legacy HR controller")
  must((out/"hr_cross_module_dependency_signals_v0.1.csv").is_file(),"cross-module output missing")
  altered=Path(td)/"changed.zip";altered.write_bytes(base.read_bytes()+b"not-baseline")
  failed=subprocess.run([sys.executable,str(script),"--package",str(altered),"--out-dir",str(Path(td)/"bad")],capture_output=True,text=True)
  must(failed.returncode!=0 and "SHA-256" in (failed.stdout+failed.stderr),"modified ZIP must be rejected")
 print("HR semantic trace tests PASS: exact baseline, 49 calls, four DDLs, REST counts, output checks and fail-closed checksum")
if __name__=="__main__":main()
