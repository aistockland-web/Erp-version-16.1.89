#!/usr/bin/env python3
"""Read-only package/source inventory for Stockland's HR/payroll replacement.

This tool verifies a checksum-locked plugin ZIP and produces table/runtime dependency
navigation. Lexical references are investigation leads, not proof of runtime execution.
"""
from __future__ import annotations
import argparse, csv, hashlib, json, re, zipfile
from pathlib import Path, PurePosixPath

EXPECTED_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
EXPECTED_RELEASE = "16.1.95"
EXPECTED_FILES = 75
EXPECTED_PHP = 48
ROOT = "stockland-erp-app/"
LEGACY_HR_KEYS = {"employees", "employee_compensation", "payroll_periods", "payroll_lines"}
SHARED_SERVICE_CANDIDATES = {
    "db": "includes/class-stockland-db.php",
    "authority": "includes/class-stockland-authority.php",
    "roles": "includes/class-stockland-roles.php",
    "workflow": "includes/class-stockland-workflow.php",
    "audit": "includes/class-stockland-audit.php",
    "activator": "includes/class-stockland-activator.php",
    "accounting": "includes/class-stockland-accounting.php",
    "reports": "includes/rest/class-rest-reports.php",
    "rest_base": "includes/rest/class-rest-base.php",
    "legacy_hr_schema": "includes/class-stockland-hr.php",
    "legacy_hr_routes": "includes/rest/class-rest-hr.php",
    "statutory_calculator": "includes/class-stockland-payroll-statutory.php",
}

def digest(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()

def method_array(src: str, method_name: str) -> list[str]:
    m = re.search(r"function\s+" + re.escape(method_name) + r"\s*\([^)]*\)\s*\{", src)
    if not m:
        return []
    start = src.find("return array(", m.end())
    if start < 0:
        return []
    open_paren = src.find("(", start + len("return array"))
    depth, quote, escaped = 0, None, False
    for pos in range(open_paren, len(src)):
        ch = src[pos]
        if quote:
            if escaped: escaped = False
            elif ch == "\\": escaped = True
            elif ch == quote: quote = None
            continue
        if ch in "'\"": quote = ch
        elif ch == "(": depth += 1
        elif ch == ")":
            depth -= 1
            if depth == 0:
                return re.findall(r"['\"]([a-z][a-z0-9_]*)['\"]", src[open_paren + 1:pos])
    return []

def extract_route_blocks(src: str) -> list[dict]:
    """Lexically isolate register_rest_route calls using balanced parentheses."""
    out=[]
    for m in re.finditer(r"register_rest_route\s*\(", src):
        opening=m.end()-1
        depth, quote, escaped = 0, None, False
        end=None
        for pos in range(opening, len(src)):
            ch=src[pos]
            if quote:
                if escaped: escaped=False
                elif ch=="\\": escaped=True
                elif ch==quote: quote=None
                continue
            if ch in "'\"": quote=ch
            elif ch=="(": depth+=1
            elif ch==")":
                depth-=1
                if depth==0:
                    end=pos+1; break
        if end is None: continue
        block=src[m.start():end]
        pm=re.search(r"self::NAMESPACE_V1\s*,\s*['\"]([^'\"]+)['\"]", block, re.S) or re.search(r"\$ns\s*,\s*['\"]([^'\"]+)['\"]", block, re.S)
        methods=re.findall(r"['\"]methods['\"]\s*=>\s*['\"]([^'\"]+)['\"]", block)
        callbacks=re.findall(r"['\"]callback['\"]\s*=>\s*array\(\s*\$this\s*,\s*['\"]([^'\"]+)['\"]", block)
        perms=len(re.findall(r"['\"]permission_callback['\"]\s*=>", block))
        out.append({"line":src[:m.start()].count("\n")+1,"path":pm.group(1) if pm else None,"methods":methods,"callbacks":callbacks,"permission_callback_count":perms,"has_permission_callback":perms>0})
    return out

def main() -> None:
    ap=argparse.ArgumentParser()
    ap.add_argument("--installer",required=True,type=Path)
    ap.add_argument("--output-dir",required=True,type=Path)
    ap.add_argument("--expected-sha256",default=EXPECTED_SHA256)
    ap.add_argument("--allow-noncanonical",action="store_true",help="write a report even if baseline identity assertions fail")
    args=ap.parse_args()
    raw=args.installer.read_bytes(); actual_sha=digest(raw); failures=[]
    if actual_sha!=args.expected_sha256: failures.append(f"installer SHA mismatch: expected {args.expected_sha256}, got {actual_sha}")
    args.output_dir.mkdir(parents=True,exist_ok=True)
    with zipfile.ZipFile(args.installer) as z:
        crc=z.testzip(); infos=[x for x in z.infolist() if not x.is_dir()]; names=[x.filename for x in infos]
        if crc: failures.append(f"ZIP CRC failed at {crc}")
        if len(names)!=len(set(names)): failures.append("ZIP contains duplicate member paths")
        if any(not PurePosixPath(n).parts or PurePosixPath(n).parts[0]!="stockland-erp-app" or ".." in PurePosixPath(n).parts for n in names): failures.append("unexpected or unsafe archive path")
        if len(names)!=EXPECTED_FILES: failures.append(f"expected {EXPECTED_FILES} files, got {len(names)}")
        files={n:z.read(n) for n in names}
    def get(path: str) -> str:
        key=ROOT+path
        if key not in files:
            failures.append("missing package file: "+key); return ""
        return files[key].decode("utf-8",errors="replace")
    manifest=json.loads(get("RELEASE_MANIFEST.json") or "{}")
    activator=get("includes/class-stockland-activator.php")
    accounting=get("includes/class-stockland-accounting.php")
    hr=get("includes/class-stockland-hr.php")
    hr_routes=get("includes/rest/class-rest-hr.php")
    core_keys=method_array(activator,"core_table_keys")
    finance_keys=method_array(accounting,"table_keys")
    hr_keys=method_array(hr,"table_keys")
    declared={}; source_hashes={}
    for path,data in files.items():
        if path.endswith(".php"):
            rel=path[len(ROOT):]; source=data.decode("utf-8",errors="replace"); source_hashes[rel]=digest(data)
            for m in re.finditer(r"CREATE TABLE\s+\{\$p\}stock_([A-Za-z0-9_]+)",source,re.I):
                declared.setdefault(m.group(1),[]).append({"path":rel,"line":source[:m.start()].count("\n")+1})
    declared_keys=set(declared); expected_keys=set(core_keys)|set(finance_keys)|set(hr_keys)
    if len(core_keys)!=29: failures.append(f"core registry expected 29 keys, got {len(core_keys)}")
    if len(finance_keys)!=16: failures.append(f"finance/procurement registry expected 16 keys, got {len(finance_keys)}")
    if set(hr_keys)!=LEGACY_HR_KEYS: failures.append(f"legacy HR keys differ from expected bridge set: {sorted(hr_keys)}")
    if len(declared_keys)!=49: failures.append(f"expected 49 static DDL table declarations, got {len(declared_keys)}")
    if expected_keys!=declared_keys: failures.append("registered table-key union differs from static DDL: registered_only="+str(sorted(expected_keys-declared_keys))+" DDL_only="+str(sorted(declared_keys-expected_keys)))
    if manifest.get("release")!=EXPECTED_RELEASE: failures.append(f"manifest release expected {EXPECTED_RELEASE}, got {manifest.get('release')}")
    if manifest.get("schema_contract",{}).get("total")!=49: failures.append("manifest does not assert the 49-table transition baseline")
    php_count=sum(n.endswith('.php') for n in names)
    if php_count!=EXPECTED_PHP: failures.append(f"expected {EXPECTED_PHP} PHP files, got {php_count}")
    routes=extract_route_blocks(hr_routes)
    if len(routes)!=13: failures.append(f"expected 13 legacy HR route registrations, got {len(routes)}")
    routes_without_perm=[x for x in routes if not x["has_permission_callback"]]
    if routes_without_perm: failures.append("legacy HR registration missing literal permission callback at lines "+str([x['line'] for x in routes_without_perm]))
    consumers={k:[] for k in sorted(declared_keys)}
    for path,data in files.items():
        if not path.endswith('.php'): continue
        rel=path[len(ROOT):]; source=data.decode("utf-8",errors="replace")
        for kind,pattern in [("DB::table",r"Stockland_DB::table\(\s*['\"]([a-z][a-z0-9_]*)['\"]"),("physical_stock_table",r"\bstock_([a-z][a-z0-9_]*)\b")]:
            for m in re.finditer(pattern,source,re.I):
                key=m.group(1).lower()
                if key in consumers: consumers[key].append({"path":rel,"line":source[:m.start()].count("\n")+1,"kind":kind,"excerpt":source[m.start():m.start()+100].splitlines()[0][:100]})
    service_status={}
    for service,rel in SHARED_SERVICE_CANDIDATES.items():
        key=ROOT+rel; service_status[service]={"path":rel,"exists":key in files,"sha256":digest(files[key]) if key in files else None}
    statutory=get("includes/class-stockland-payroll-statutory.php")
    legal_flags={
        "legacy_calculator_file_present":bool(statutory),
        "legacy_employee_pension_tax_base_reference":("TAXABLE_GROSS_LESS_EMPLOYEE_PENSION" in statutory or "subtract( $taxable_gross, $employee_pension )" in statutory),
        "legacy_fixed_pension_rates_reference":("0.0700" in hr or "EMPLOYEE_PENSION_RATE" in statutory),
        "classification_requires_review_reference":("REVIEW_REQUIRED" in hr or "OWNER_CLASSIFIED" in statutory),
        "interpretation":"Source-review flags only. This tool does not decide which tax/pension rule is lawful; do not change historical postings based on these flags."
    }
    report={
        "report_kind":"READ_ONLY_PACKAGE_AND_SOURCE_INVENTORY",
        "installer":{"path":str(args.installer),"release":manifest.get("release"),"sha256":actual_sha,"expected_sha256":args.expected_sha256,"sha_matches":actual_sha==args.expected_sha256,"zip_crc_error":crc,"member_count":len(names),"php_member_count":php_count,"member_path_unique":len(names)==len(set(names))},
        "baseline_schema":{"manifest_total":manifest.get("schema_contract",{}).get("total"),"core_table_keys":core_keys,"finance_procurement_table_keys":finance_keys,"legacy_hr_table_keys":hr_keys,"registered_key_count":len(expected_keys),"static_ddl_count":len(declared_keys),"ddl_declarations":{k:v for k,v in sorted(declared.items())}},
        "legacy_hr_routes":{"count":len(routes),"routes":routes,"missing_permission_callback_lines":[x["line"] for x in routes_without_perm]},
        "legacy_hr_consumers_lexical_map":{k:consumers[k] for k in sorted(LEGACY_HR_KEYS)},
        "all_table_consumers_lexical_counts":{k:len(v) for k,v in sorted(consumers.items())},
        "shared_service_candidates":service_status,
        "statutory_review_flags":legal_flags,
        "source_file_hashes":source_hashes,
        "assertions":{"failures":failures,"pass":not failures,"limitations":["Lexical matches are navigation aids, not proof of runtime execution.","No live database connection, employee data read, migration, or write was performed.","Static source DDL does not prove live schema equality; a host-side schema fingerprint remains required.","Legal flags are not a legal opinion or approval of any tax/pension formula."]}
    }
    out=args.output_dir/"hr_replacement_baseline_audit.json"
    out.write_text(json.dumps(report,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    with (args.output_dir/"hr_table_dependency_index.csv").open("w",newline="",encoding="utf-8-sig") as f:
        w=csv.writer(f); w.writerow(["table_key","declared_ddl_locations","lexical_reference_count","reference_locations","interpretation"])
        for key in sorted(declared_keys):
            ddl=";".join(f"{x['path']}:{x['line']}" for x in declared[key]); refs=consumers.get(key,[])
            locs=";".join(f"{x['path']}:{x['line']}[{x['kind']}]" for x in refs)
            w.writerow([key,ddl,len(refs),locs,"Navigation only; verify execution and runtime paths before migration."])
    (args.output_dir/"hr_baseline_source_fingerprints.csv").write_text("path,sha256\n"+"".join(f'"{p}",{h}\n' for p,h in sorted(source_hashes.items())),encoding="utf-8")
    print(json.dumps({"pass":not failures,"release":manifest.get("release"),"sha256":actual_sha,"members":len(names),"php_files":php_count,"core_tables":len(core_keys),"finance_procurement_tables":len(finance_keys),"legacy_hr_tables":len(hr_keys),"static_ddl_tables":len(declared_keys),"legacy_hr_routes":len(routes),"failure_count":len(failures),"failures":failures,"report":str(out)},indent=2))
    if failures and not args.allow_noncanonical: raise SystemExit(2)

if __name__=="__main__": main()
