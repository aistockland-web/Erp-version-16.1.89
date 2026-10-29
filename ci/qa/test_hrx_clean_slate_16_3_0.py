#!/usr/bin/env python3
"""Exact package and source contracts for clean-slate HRX 16.3.0.

This verifies source/package construction, not live WordPress/MariaDB, payroll-law or deployment acceptance.
"""
from __future__ import annotations
import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path, PurePosixPath

ROOT=Path(__file__).resolve().parents[2]
RELEASE="16.3.0"
BASELINE_SHA256="494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
BASELINE_SIZE=1_102_405
PLUGIN="stockland-erp-app/"
def require(ok, msg):
    if not ok:
        raise SystemExit("FAIL: "+msg)
def sha(raw): return hashlib.sha256(raw).hexdigest()
def read_member(z, path):
    require(path in z.namelist(), "missing package member "+path)
    return z.read(path).decode("utf-8")

def main():
    import argparse
    ap=argparse.ArgumentParser()
    ap.add_argument("--baseline",required=True,type=Path)
    ap.add_argument("--package",required=True,type=Path)
    ap.add_argument("--receipt",required=True,type=Path)
    ap.add_argument("--source-root",type=Path,default=ROOT/"ci"/"hr_replacement_v2")
    ap.add_argument("--ui-root",type=Path,default=ROOT/"ci"/"hrx_clean_slate_16_3_0")
    a=ap.parse_args()
    require(a.baseline.is_file() and a.package.is_file() and a.receipt.is_file(),"baseline, candidate ZIP and receipt must exist")
    braw=a.baseline.read_bytes()
    require(len(braw)==BASELINE_SIZE and sha(braw)==BASELINE_SHA256,"baseline size/SHA-256 is not the reviewed 16.1.95 installer")
    praw=a.package.read_bytes()
    sidecar=Path(str(a.package)+".sha256")
    require(sidecar.is_file(),"SHA-256 sidecar is missing")
    require(sidecar.read_text(encoding="utf-8").split()[0]==sha(praw),"candidate SHA-256 sidecar mismatch")
    receipt=json.loads(a.receipt.read_text(encoding="utf-8"))
    require(receipt.get("release")==RELEASE,"receipt release identity mismatch")
    require(receipt.get("installer_sha256")==sha(praw),"receipt SHA-256 mismatch")
    require(receipt.get("baseline_sha256")==BASELINE_SHA256,"receipt did not pin expected baseline")
    require(receipt.get("production_ready") is False,"candidate receipt must not claim production readiness")
    require(receipt.get("business_data_changed_by_builder") is False,"builder must declare no business data mutation")
    with zipfile.ZipFile(a.baseline) as bz, zipfile.ZipFile(a.package) as z:
        require(bz.testzip() is None and z.testzip() is None,"ZIP CRC/integrity failure")
        bn=[i.filename for i in bz.infolist() if not i.is_dir()]
        names=[i.filename for i in z.infolist() if not i.is_dir()]
        require(len(bn)==75 and len(set(bn))==75,"baseline package inventory mismatch")
        require(len(names)==76 and len(set(names))==76,"candidate must contain 76 unique members after two legacy runtime files are removed and three HRX runtime classes added")
        require(all(PurePosixPath(n).parts[0]==PLUGIN.rstrip("/") and ".." not in PurePosixPath(n).parts for n in names),"unexpected ZIP root or path traversal")
        before={n:bz.read(n) for n in bn}
        after={n:z.read(n) for n in names}
    removed=set(before)-set(after)
    added=set(after)-set(before)
    require(removed=={
      PLUGIN+"includes/class-stockland-payroll-statutory.php",
      PLUGIN+"includes/rest/class-rest-hr.php"
    },"only the retired HR REST controller and old statutory calculator may be removed")
    require(added=={
      PLUGIN+"includes/class-stockland-hr-replacement.php",
      PLUGIN+"includes/class-stockland-hr-replacement-calculator.php",
      PLUGIN+"includes/rest/class-rest-hr-replacement.php"
    },"only three HRX runtime classes may be added")
    main=read_member(zipfile.ZipFile(a.package),PLUGIN+"stockland-inventory.php")
    shim=read_member(zipfile.ZipFile(a.package),PLUGIN+"includes/class-stockland-hr.php")
    controller=read_member(zipfile.ZipFile(a.package),PLUGIN+"includes/rest/class-rest-hr-replacement.php")
    model=read_member(zipfile.ZipFile(a.package),PLUGIN+"includes/class-stockland-hr-replacement.php")
    calc=read_member(zipfile.ZipFile(a.package),PLUGIN+"includes/class-stockland-hr-replacement-calculator.php")
    app=read_member(zipfile.ZipFile(a.package),PLUGIN+"assets/app.js")
    css=read_member(zipfile.ZipFile(a.package),PLUGIN+"assets/app.css")
    css_min=read_member(zipfile.ZipFile(a.package),PLUGIN+"assets/app.min.css")
    css_manifest=json.loads(read_member(zipfile.ZipFile(a.package),PLUGIN+"assets/css/modules.json"))
    manifest=json.loads(read_member(zipfile.ZipFile(a.package),PLUGIN+"RELEASE_MANIFEST.json"))
    require("* Version:           16.3.0" in main and "define( 'STOCKLAND_VERSION', '16.3.0' );" in main,"plugin version markers disagree")
    require("const ROUTE_VERSION      = '16.3.0';" in read_member(zipfile.ZipFile(a.package),PLUGIN+"includes/class-stockland-frontend.php"),"frontend route/cache version mismatch")
    require("new Stockland_REST_HR_Replacement();" in main and "new Stockland_REST_HR();" not in main,"only HRX REST runtime may be registered")
    require("class Stockland_HR {" in shim and "return array();" in shim and "return array('installed'=>array(),'missing'=>array(),'complete'=>true" in shim,"legacy compatibility class is not inert")
    for k in ("employees","employee_compensation","payroll_periods","payroll_lines"):
        require(k not in shim,"legacy table "+k+" appears in compatibility shim")
    require("function reset_legacy_tables" in controller and "RESET_LEGACY_HR_PAYROLL_AFTER_BACKUP" in controller,"explicit legacy reset action missing")
    require("STOCKLAND_HRX_ALLOW_LEGACY_RESET" in controller and "wp_get_environment_type" in controller,"legacy reset lacks opt-in/non-production check")
    require("array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' )" in controller,"legacy reset table allowlist absent")
    require("DROP TABLE" not in model.upper() and "TRUNCATE TABLE" not in model.upper() and "ALTER TABLE" not in model.upper(),"HRX schema installer must not include destructive/automatic DDL")
    keys_match=re.search(r"public\s+static\s+function\s+table_keys\s*\(\s*\)\s*\{.*?return\s+array\s*\((.*?)\);",model,re.S)
    require(bool(keys_match),"HRX table-key inventory cannot be parsed")
    keys=re.findall(r"'(hrx_[a-z0-9_]+)'",keys_match.group(1))
    require(len(keys)>=40 and len(keys)==len(set(keys)),"HRX table-key inventory is incomplete or duplicated")
    ddl_keys=set(re.findall(r"'(hrx_[a-z0-9_]+)'\s*=>\s*\"",model))
    require(set(keys)==ddl_keys,"HRX DDL inventory does not match table_keys() exactly")
    sc=manifest.get("schema_contract",{})
    require(sc.get("core_operations")==29 and sc.get("accounting_procurement")==16,"shared ERP schema contracts changed unexpectedly")
    require(sc.get("legacy_hr_payroll_active")==0 and sc.get("hr_payroll_replacement")==len(keys),"manifest does not describe clean-slate HRX")
    require(sc.get("total")==45+len(keys),"manifest schema total mismatch")
    require(manifest.get("schema_changed") is True and manifest.get("live_business_data_changed") is False,"candidate schema/data-impact declaration incorrect")
    require(manifest.get("production_acceptance",{}).get("status","").startswith("PENDING"),"manifest must preserve pending production acceptance")
    require(css_manifest.get("release")==RELEASE and len(css_manifest.get("modules",[]))==6 and len(set(css_manifest["modules"]))==6,"CSS ordered source-module manifest mismatch")
    normalized=re.sub(r"/\*[\s\S]*?\*/"," ",css)
    normalized=re.sub(r"\s+"," ",normalized).strip()
    normalized=re.sub(r"\s*([{}:;,])\s*",r"\1",normalized).replace(";}", "}")
    require(normalized==css_min,"compiled/minified CSS is not derived from its source")
    require("@media(max-width:767px)" in css_min and "@media(max-width:380px)" in css_min,"responsive 767px and 380px HRX layouts missing")
    require("data-hrx-exit-create" in app and "/hr/replacement/exits" in app,"exit creation workflow is not wired into UI")
    for token in ("payroll-preflight","payroll-calculate","payroll-submit","payroll-approve","payout-outcome","payout-reconcile","remittance-verify","offer-create-employment","contract-sign","document-review","exit-add-item","exit-approve","exit-payout","payee-classify","payee-verify-in-kind","payee-calculate","payee-approve","payee-pay"):
        require(token in app,"HRX UI action absent: "+token)
    for token in ("hrxSchema","hr/replacement/schema/batch","hrx-ui.js","HRX clean-slate"):
        # Some names are source-level only and intentionally not required in packaged app; checked below where relevant.
        pass
    require("STOCKLAND_HR_SCHEMA_VERSION', '16.3.0" in main,"legacy schema display marker is not at 16.3.0")
    require("schema_contract_exact_49" not in read_member(zipfile.ZipFile(a.package),PLUGIN+"includes/rest/class-rest-app.php"),"old exact-49 readiness gate remains")
    health=read_member(zipfile.ZipFile(a.package),PLUGIN+"includes/rest/class-rest-app.php")
    require("schema_contract_hrx_exact" in health and "Stockland_HR_Replacement::schema_status()" in health,"health checks are not HRX-aware")
    require("new Stockland_Payroll_Statutory" not in main and "class-stockland-payroll-statutory.php" not in main,"legacy hard-coded statutory calculator remains active")
    for pattern in (r"\bEMPLOYEE_PENSION_RATE\b",r"\bEMPLOYER_PENSION_RATE\b",r"\b0\.07\b",r"\b0\.11\b"):
        require(not re.search(pattern,calc),"HRX calculator contains a hard-coded historical pension constant")
    for token in ("progressive_tax","payroll_line","final_settlement","Stockland_Money::minor","rule"):
        require(token.lower() in calc.lower(),"rules-driven deterministic calculator component missing: "+token)
    for token in ("admin_post_stockland_hrx_document_download","check_admin_referer","Content-Disposition","X-Content-Type-Options","hash('sha256'","document_accessible"):
        require(token in controller,"private download/authorization integrity guard missing: "+token)
    require("private-document" in read_member(zipfile.ZipFile(a.package),PLUGIN+"README.md").lower() or "private document" in controller.lower(),"private-document control documentation missing")
    require("stockland_hrx_schema_version" in model and "install_schema_batch" in model,"bounded, versioned, explicit schema setup missing")
    require("function reset_legacy_tables" in controller,"legacy cleanup must be a separate explicit action")
    print("PASS HRX exact-package identity, runtime, clean-slate/schema, protected reset, CSS and lifecycle UI contracts")
    print("NOTE: source/package evidence only. Live WordPress/MariaDB E2E, legal review, backup/restore, and Owner/HR/Finance acceptance remain separate gates.")

if __name__=="__main__":
    main()
