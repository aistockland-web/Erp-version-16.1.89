#!/usr/bin/env python3
"""Static dependency trace for the exact locked Stockland 16.1.95 installer.
Source-navigation aid only; not AST/data-flow proof, live DB audit, or release certification.
"""
import argparse,csv,hashlib,json,re,zipfile
from collections import Counter,defaultdict
from pathlib import Path,PurePosixPath
SHA="494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
SIZE=1102405
ROOT="stockland-erp-app/"
LEGACY=("employees","employee_compensation","payroll_periods","payroll_lines")
EXPECTED={"employees":14,"employee_compensation":5,"payroll_periods":17,"payroll_lines":13}
METHOD=re.compile(r"(?m)^\s*(?:public|private|protected)\s+(?:static\s+)?function\s+([A-Za-z_]\w*)\s*\(")
CALL=re.compile(r"Stockland_DB::table\s*\(\s*(['\"])(?:stock_)?(employees|employee_compensation|payroll_periods|payroll_lines)\1\s*\)",re.I)
def sha(b): return hashlib.sha256(b).hexdigest()
def method_at(s,p):
 m=list(METHOD.finditer(s[:p])); return m[-1].group(1) if m else "(unresolved class scope)"
def hint(s,a,b):
 q=s[max(0,a-360):min(len(s),b+520)]; found=[]
 for name,pat in [("INSERT",r"\bINSERT\b|insert_record\s*\("),("UPDATE",r"\bUPDATE\b|update_record\s*\("),("DELETE",r"\bDELETE\b|delete_record\s*\("),("SELECT",r"\bSELECT\b|read_(?:row|results|var|col)\s*\("),("LOCK",r"\bFOR\s+UPDATE\b|START\s+TRANSACTION|acquire_named_lock\s*\(")]:
  if re.search(pat,q,re.I): found.append(name)
 return "+".join(found) if found else "TABLE_HANDLE_OR_UNCLASSIFIED"
def run(package,out):
 if not package.is_file(): raise SystemExit("FAIL: baseline ZIP missing")
 raw=package.read_bytes()
 if len(raw)!=SIZE or sha(raw)!=SHA: raise SystemExit("FAIL: package size or SHA-256 differs from locked baseline")
 with zipfile.ZipFile(package) as z:
  bad=z.testzip()
  if bad: raise SystemExit("FAIL: ZIP CRC issue: "+bad)
  names=[i.filename for i in z.infolist() if not i.is_dir()]
  if len(names)!=75 or len(set(names))!=75: raise SystemExit("FAIL: expected 75 unique package members")
  if any(not PurePosixPath(n).parts or PurePosixPath(n).parts[0]!="stockland-erp-app" or ".." in PurePosixPath(n).parts for n in names): raise SystemExit("FAIL: unexpected ZIP root/path")
  files={n:z.read(n) for n in names}
 php={n:b.decode("utf-8","replace") for n,b in files.items() if n.endswith(".php")}
 if len(php)!=48: raise SystemExit("FAIL: expected 48 PHP files")
 manifest=json.loads(files[ROOT+"RELEASE_MANIFEST.json"].decode())
 if manifest.get("release")!="16.1.95" or manifest.get("schema_contract",{}).get("total")!=49: raise SystemExit("FAIL: release/schema manifest mismatch")
 schema=php[ROOT+"includes/class-stockland-hr.php"]
 m=re.search(r"public\s+static\s+function\s+table_keys\s*\(\s*\)\s*\{(.*?)\n\s*\}",schema,re.S)
 if not m: raise SystemExit("FAIL: cannot read legacy table_keys")
 keys=tuple(re.findall(r"['\"]([a-z_]+)['\"]",m.group(1)))
 if keys!=LEGACY: raise SystemExit("FAIL: legacy table key mismatch: "+repr(keys))
 ddl={k:len(re.findall(r"CREATE\s+TABLE\s+\{\$p\}stock_"+re.escape(k)+r"\s*\(",schema,re.I)) for k in LEGACY}
 if any(v!=1 for v in ddl.values()): raise SystemExit("FAIL: expected exactly one DDL for each legacy HR key: "+repr(ddl))
 fk=len(re.findall(r"\bFOREIGN\s+KEY\b",schema,re.I))
 if fk: raise SystemExit("FAIL: legacy HR DDL FK inventory changed")
 rows=[]; counts=Counter(); hints=defaultdict(Counter)
 for path,src in sorted(php.items()):
  lines=src.splitlines()
  for m in CALL.finditer(src):
   key=m.group(2).lower(); ln=src.count("\n",0,m.start())+1; op=hint(src,m.start(),m.end()); counts[key]+=1; hints[key][op]+=1
   rows.append({"legacy_table":key,"file":path.removeprefix(ROOT),"line":ln,"method_guess":method_at(src,m.start()),"lexical_operation_hint":op,"source_excerpt":re.sub(r"\s+"," ",lines[ln-1]).strip()[:260],"manual_review_required":"YES — inspect full method, SQL, permission, location scope, transaction and side effects"})
 if dict(counts)!=EXPECTED or len(rows)!=49: raise SystemExit("FAIL: legacy call count changed: "+repr(dict(counts)))
 hr=php[ROOT+"includes/rest/class-rest-hr.php"]; routes=len(re.findall(r"\bregister_rest_route\s*\(",hr)); perms=len(re.findall(r"'permission_callback'\s*=>",hr))
 if routes!=13 or perms!=15: raise SystemExit(f"FAIL: route/callback inventory changed: {routes}/{perms}")
 cross=[]
 for path in [ROOT+"includes/rest/class-rest-app.php",ROOT+"includes/class-stockland-admin.php",ROOT+"assets/app.js",ROOT+"assets/pwa.js",ROOT+"includes/rest/class-rest-accounting.php",ROOT+"includes/rest/class-rest-requisition-v16.php",ROOT+"includes/class-stockland-payroll-statutory.php"]:
  if path not in files: continue
  for ln,line in enumerate(files[path].decode("utf-8","replace").splitlines(),1):
   if re.search(r"schema_contract_exact_49|49\s*/\s*49|['\"]employees['\"]|employee_compensation|payroll_periods|payroll_lines|Stockland_Payroll_Statutory::calculate|post_journal|payroll_pay",line,re.I):
    cross.append({"file":path.removeprefix(ROOT),"line":ln,"signal":"schema/legacy/payroll/accounting reference","excerpt":re.sub(r"\s+"," ",line).strip()[:300],"manual_review_required":"YES"})
 out.mkdir(parents=True,exist_ok=True)
 with (out/"hr_legacy_semantic_call_sites_v0.1.csv").open("w",encoding="utf-8-sig",newline="") as f:
  w=csv.DictWriter(f,fieldnames=list(rows[0]));w.writeheader();w.writerows(sorted(rows,key=lambda r:(r["legacy_table"],r["file"],r["line"])))
 with (out/"hr_cross_module_dependency_signals_v0.1.csv").open("w",encoding="utf-8-sig",newline="") as f:
  w=csv.DictWriter(f,fieldnames=list(cross[0]));w.writeheader();w.writerows(cross)
 result={"release":"16.1.95","baseline_sha256":sha(raw),"baseline_bytes":len(raw),"zip_entries":len(names),"php_files":len(php),"schema_contract":manifest["schema_contract"],"hr_table_keys":list(keys),"legacy_static_ddl_per_key":ddl,"explicit_foreign_key_constraints_in_legacy_hr_ddl":fk,"direct_table_helper_call_counts":dict(counts),"direct_call_site_count":len(rows),"direct_call_site_source_files_by_table":{k:sorted({r["file"] for r in rows if r["legacy_table"]==k}) for k in LEGACY},"hr_rest_register_route_call_sites":routes,"permission_callback_entries":perms,"cross_module_dependency_signal_rows":len(cross),"lexical_operation_hints_by_table":{k:dict(hints[k]) for k in LEGACY},"interpretation":"Static source-navigation evidence, not AST/data-flow or runtime authorization proof. Manually trace full method, SQL parameters, permission, row/location scope, transaction, audit/workflow, reports/Health and GL/PLEDGER effects.","not_run_here":["live WordPress source parity","live DB/schema/data-health","role/location penetration tests","WordPress/MariaDB E2E","migration/restore/rollback","qualified Ethiopian legal/payroll review"]}
 (out/"hr_semantic_dependency_receipt_v0.1.json").write_text(json.dumps(result,indent=2,sort_keys=True)+"\n",encoding="utf-8")
 print(json.dumps(result,indent=2,sort_keys=True))
def main():
 p=argparse.ArgumentParser();p.add_argument("--package",required=True);p.add_argument("--out-dir",required=True);a=p.parse_args();run(Path(a.package),Path(a.out_dir))
if __name__=="__main__": main()
