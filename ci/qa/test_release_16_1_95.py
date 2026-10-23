#!/usr/bin/env python3
"""Independent 16.1.95 exact-package contracts against the locked 16.1.94 archive."""
import argparse, hashlib, json, re, sys, zipfile
from pathlib import Path

ROOT = "stockland-erp-app/"
EXPECTED_CHANGED = {
    ROOT + "README.md", ROOT + "RELEASE_MANIFEST.json", ROOT + "assets/app.js",
    ROOT + "assets/css/modules.json", ROOT + "includes/class-stockland-activator.php",
    ROOT + "includes/class-stockland-frontend.php", ROOT + "includes/class-stockland-roles.php",
    ROOT + "includes/rest/class-rest-accounting.php", ROOT + "includes/rest/class-rest-app.php",
    ROOT + "includes/rest/class-rest-import-v16.php", ROOT + "includes/rest/class-rest-sales.php",
    ROOT + "includes/rest/class-rest-setup.php", ROOT + "includes/rest/class-rest-stock.php",
    ROOT + "stockland-inventory.php",
}
def need(condition, label):
    print(("PASS " if condition else "FAIL ") + label)
    if not condition: raise SystemExit(1)
def read(files, path): return files[ROOT + path].decode("utf-8")

def main():
    p=argparse.ArgumentParser()
    p.add_argument("--baseline", type=Path, required=True)
    p.add_argument("--package", type=Path, required=True)
    p.add_argument("--receipt", type=Path, required=True)
    a=p.parse_args()
    side=Path(str(a.package)+".sha256")
    need(a.baseline.is_file() and a.package.is_file() and side.is_file(), "baseline, final ZIP and SHA-256 sidecar exist")
    baseline_hash=hashlib.sha256(a.baseline.read_bytes()).hexdigest()
    candidate_hash=hashlib.sha256(a.package.read_bytes()).hexdigest()
    need(baseline_hash=="10f5499291e633e66774d63157317b287e4ff418d95eb5fd97fd5c9e03dbfa23", "exact CI-built 16.1.94 baseline checksum")
    need(side.read_text().split()[0]==candidate_hash, "sidecar matches final ZIP bytes")
    with zipfile.ZipFile(a.baseline) as oldz, zipfile.ZipFile(a.package) as newz:
        need(oldz.testzip() is None and newz.testzip() is None, "baseline and candidate ZIP CRC")
        oldnames=[i.filename for i in oldz.infolist() if not i.is_dir()]
        newnames=[i.filename for i in newz.infolist() if not i.is_dir()]
        need(oldnames==newnames and len(newnames)==75 and len(set(newnames))==75, "75 unique plugin files in same order")
        need(all(n.startswith(ROOT) and ".." not in n.split("/") for n in newnames), "single safe plugin root")
        old={n:oldz.read(n) for n in oldnames}; new={n:newz.read(n) for n in newnames}
    changed={n for n in old if old[n]!=new[n]}
    need(changed==EXPECTED_CHANGED, "only 14 audited files changed")
    entry=read(new,"stockland-inventory.php")
    front=read(new,"includes/class-stockland-frontend.php")
    manifest=json.loads(read(new,"RELEASE_MANIFEST.json"))
    cssmanifest=json.loads(read(new,"assets/css/modules.json"))
    need(manifest.get("release")=="16.1.95" and cssmanifest.get("release")=="16.1.95", "release and CSS cache markers align at 16.1.95")
    need("Version:           16.1.95" in entry and "define( 'STOCKLAND_VERSION', '16.1.95' );" in entry, "plugin header and runtime release constant agree")
    need("const ROUTE_VERSION      = '16.1.95';" in front, "frontend route/cache marker agrees")
    schema=manifest.get("schema_contract",{})
    need(schema=={"total":49,"core_operations":29,"accounting_procurement":16,"hr_payroll":4}, "49-table contract remains 29 + 16 + 4")
    need("define( 'STOCKLAND_DB_VERSION', '16.1.1' );" in entry, "database version marker is preserved")
    app=read(new,"includes/rest/class-rest-app.php")
    importer=read(new,"includes/rest/class-rest-import-v16.php")
    stock=read(new,"includes/rest/class-rest-stock.php")
    activator=read(new,"includes/class-stockland-activator.php")
    roles=read(new,"includes/class-stockland-roles.php")
    accounting=read(new,"includes/rest/class-rest-accounting.php")
    sales=read(new,"includes/rest/class-rest-sales.php")
    setup=read(new,"includes/rest/class-rest-setup.php")
    need("COUNT(*) AS " + chr(96) + "rows" + chr(96) in app and "COUNT(cl.id) AS " + chr(96) + "lines" + chr(96) in app, "reserved report aliases are quoted")
    need(re.search(r"\bAS\s+(?:rows|lines|row_number)\b", app+importer, re.I) is None, "no unquoted reserved aliases remain in Reports/Imports")
    need(importer.count("AS " + chr(96) + "row_number" + chr(96))==5, "all five import row_number aliases are quoted")
    need("$res       = Stockland_DB::table( 'reservations' );" in stock, "reorder query has explicit reservations table binding")
    need("if ( self::table_exists_static( $expenses_table ) )" in activator and "$has_col( $expenses_table, 'expense_date' )" in activator, "first activation guards table and column existence")
    need("if ( ! Stockland_DB::table_exists( $users_table ) )" in roles and "ERP role synchronization deferred" in roles, "role sync defers safely until profiles exist")
    need(app.count("return $this->can_access_erp_profile();")==4 and "return true === Stockland_Authority::assert_profile();" in app, "bootstrap, search and dashboard insights require active ERP profile/admin")
    need("current_user_can( 'stockland_install_schema' )" in setup and "return true === Stockland_Authority::assert_profile();" in setup, "setup status is restricted to authorized setup/admin/ERP profiles")
    need("$p['business_date'] ?? current_time( 'Y-m-d' )" not in sales and "business_date_required" in sales and "invalid_date" in sales, "day close requires an explicit valid business_date")
    need("$over_receipt =" in accounting and "'over_receipt_quantity'" in accounting and "( $closed || $over_receipt ) ? 409 : 500" in accounting, "PO over-receipt uses 409; unexpected errors remain 500")
    js=read(new,"assets/app.js")
    need("function resolveTrustedApiUrl(path,baseOverride)" in js and "return api(pth,cfg,base);" in js, "REST resolver preserves base/query separately")
    need("new URLSearchParams(raw.slice(q+1)).forEach" in js and "Blocked ERP request outside the configured WordPress REST API" in js, "query preservation and trusted-origin enforcement are present")
    need("Management Intelligence is deterministic, source-backed ERP analytics" in manifest["release_changes"][8], "release scope documents Cloud AI accurately")
    ai=manifest["user_interface"]["management_intelligence"]["cloud_ai_boundary"]
    need(ai.get("external_provider_configured") is False and ai.get("external_ai_requests_from_plugin") is False, "no unapproved external AI data flow is claimed")
    readme=read(new,"README.md")
    need("WordPress plugin update 16.1.95" in readme and "WordPress plugin update 16.1.94" not in readme, "README release text matches 16.1.95")
    need("Nine active employee compensation records remain unresolved" in readme, "payroll data blocker retained in release notes")
    receipt=json.loads(a.receipt.read_text())
    need(receipt.get("result")=="PASS" and receipt.get("output_sha256")==candidate_hash and receipt.get("input_sha256")==baseline_hash, "release receipt hashes match exact artifacts")
    need(receipt.get("schema_changed") is False and receipt.get("live_business_data_changed") is False, "release boundary reports no schema/data mutation")
    print("PASS 16.1.95 exact-package verification complete")
    print("SHA256=" + candidate_hash)
    print("BYTES=" + str(a.package.stat().st_size))
if __name__=="__main__": main()
