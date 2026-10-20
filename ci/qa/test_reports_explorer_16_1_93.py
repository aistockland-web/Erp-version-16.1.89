#!/usr/bin/env python3
"""Static release, permissions, and behavior-contract tests for 16.1.93."""
from __future__ import annotations
import hashlib, json, re, subprocess, zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip"
CANDIDATE = ROOT / "dist/STOCKLAND_ERP_16.1.93_REPORTS_EXPLORER_CANDIDATE.zip"
BASE_SHA = "adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd"

def need(ok: bool, msg: str) -> None:
    if not ok:
        raise AssertionError(msg)

def read_package(path: Path) -> tuple[list[str], dict[str, bytes]]:
    with zipfile.ZipFile(path) as z:
        need(z.testzip() is None, f"ZIP CRC failed: {path.name}")
        names = [i.filename for i in z.infolist() if not i.is_dir()]
        need(len(names) == 75 and len(set(names)) == 75, f"Unexpected inventory: {path.name}")
        need(all(not PurePosixPath(n).is_absolute() and ".." not in PurePosixPath(n).parts and PurePosixPath(n).parts[0] == "stockland-erp-app" for n in names), "Unexpected archive root/path")
        return names, {n:z.read(n) for n in names}

def run() -> None:
    baseline_bytes = BASE.read_bytes()
    need(hashlib.sha256(baseline_bytes).hexdigest() == BASE_SHA and len(baseline_bytes) == 1_113_446, "Audited baseline hash/size mismatch")
    old_names, old = read_package(BASE)
    new_names, new = read_package(CANDIDATE)
    need(old_names == new_names, "Candidate file names/order changed")
    app = new["stockland-erp-app/assets/app.js"].decode()
    rest = new["stockland-erp-app/includes/rest/class-rest-app.php"].decode()
    entry = new["stockland-erp-app/stockland-inventory.php"].decode()
    frontend = new["stockland-erp-app/includes/class-stockland-frontend.php"].decode()
    manifest = json.loads(new["stockland-erp-app/RELEASE_MANIFEST.json"])
    cssmeta = json.loads(new["stockland-erp-app/assets/css/modules.json"])

    for text, label in [
        ("['branch_performance','Branch performance']", "branch performance type/default"),
        ('<option value="today" selected>Today</option>', "Today default"),
        ('<option value="all" selected>All authorized locations</option>', "All authorized locations default"),
        ('id="reportSku" name="sku" type="search" value=""', "empty SKU search"),
        ("api('/app/reports/explorer?'", "explorer endpoint"),
        ("api16('/sku-search?q='", "SKU/product/barcode autocomplete"),
        ("window.print()", "print/PDF action"),
        ("buildSelectedReportCsv(report)", "selected-report CSV export"),
        ('id="selectedOpsReport"', "selected report container"),
        ("data-report-details", "Owner reorder row detail"),
        ("window.removeEventListener('afterprint',restore)", "after-print cleanup"),
    ]:
        need(text in app, "Missing UI contract: " + label)
    for text, label in [
        ("'/app/reports/explorer'", "REST explorer route"),
        ("array( $this, 'reports_explorer' )", "REST handler"),
        ("current_user_can( 'stockland_view_branch_reports' )", "report capability"),
        ("! Stockland_Authority::is_owner()", "Owner-only permission callback"),
        ("! current_user_can( 'stockland_view_all_branches' ) && ! Stockland_Authority::can_access_location( $selected_location )", "authorized location enforcement"),
        ("AND is_active=1 AND location_type IN (%s,%s)", "active branch/warehouse validation"),
        ("$this->scope_locations()", "multi-location server scope"),
        ("append_scope_filter( 'branch_location_id', $rt_args, $scope )", "sales authorized scope"),
        ("append_scope_filter( 'l.id', $stock_args, $scope )", "stock authorized scope"),
        ("strlen( $sku ) > 128", "bounded SKU parameter"),
        ("current_user_can( 'stockland_view_cost_and_profit' )", "profit permission"),
        ("'critical_reorder' === $type && ! Stockland_Authority::is_owner()", "Owner-only handler recheck"),
    ]:
        need(text in rest, "Missing server contract: " + label)

    changed = {n for n in old_names if old[n] != new[n]}
    allowed = {
        "stockland-erp-app/README.md",
        "stockland-erp-app/RELEASE_MANIFEST.json",
        "stockland-erp-app/assets/app.js",
        "stockland-erp-app/assets/css/modules.json",
        "stockland-erp-app/includes/class-stockland-frontend.php",
        "stockland-erp-app/includes/rest/class-rest-app.php",
        "stockland-erp-app/stockland-inventory.php",
    }
    need(changed == allowed, "Unexpected changed files: " + repr(sorted(changed)))
    need(manifest.get("release") == "16.1.93" and cssmeta.get("release") == "16.1.93", "Release metadata mismatch")
    need("Version:           16.1.93" in entry and "define( 'STOCKLAND_VERSION', '16.1.93' );" in entry, "Plugin identity mismatch")
    need("const ROUTE_VERSION      = '16.1.93';" in frontend, "Route cache identity mismatch")
    need("16.1.1" in entry, "Database version changed or marker absent")
    need(old["stockland-erp-app/includes/class-stockland-activator.php"] == new["stockland-erp-app/includes/class-stockland-activator.php"], "Schema activator changed")
    css_files = [n for n in old_names if n.endswith(".css")]
    need(all(old[n] == new[n] for n in css_files), "CSS source or compiled bytes changed")

    match = re.search(r"function reportDateRange\(period\)\{.*?\n\}", app, re.S)
    need(match is not None, "Date-range helper absent")
    js = "function localDate(){return '2026-10-20';}\n" + match.group(0) + """
const r={today:reportDateRange('today'),yesterday:reportDateRange('yesterday'),last7:reportDateRange('last7'),month:reportDateRange('this_month')};
process.stdout.write(JSON.stringify(r));
"""
    ranges = json.loads(subprocess.run(["node", "-e", js], check=True, capture_output=True, text=True).stdout)
    need(ranges["today"] == {"from":"2026-10-20","to":"2026-10-20"}, "Today date regression")
    need(ranges["yesterday"] == {"from":"2026-10-19","to":"2026-10-19"}, "Yesterday date regression")
    need(ranges["last7"] == {"from":"2026-10-14","to":"2026-10-20"}, "Last-7-days date regression")
    need(ranges["month"] == {"from":"2026-10-01","to":"2026-10-20"}, "Month-to-date regression")

    print("PASS: locked baseline SHA/size, ZIP CRC, 75-file inventory and one-root paths")
    print("PASS: selected report selector defaults, SKU autocomplete, selected-only print/CSV, afterprint cleanup")
    print("PASS: server capability, authorized location, Owner-only reorder and profit permission contracts")
    print("PASS: report source filters; existing schema activator unchanged; all CSS source/compiled files byte-identical")
    print("PASS: plugin/cache/release identity and site-local period date regressions")
    print("CHANGED: " + ", ".join(sorted(changed)))
    print("CANDIDATE_SHA256: " + hashlib.sha256(CANDIDATE.read_bytes()).hexdigest())

if __name__ == "__main__":
    run()
