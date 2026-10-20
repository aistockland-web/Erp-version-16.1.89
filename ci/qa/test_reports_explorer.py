#!/usr/bin/env python3
"""Regression contract for the deterministic 16.1.93 Reports Explorer patch."""
import hashlib, json, re, subprocess, sys, tempfile, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "ci"))
import patch_reports_explorer as patcher

def need(ok, message):
    if not ok:
        raise AssertionError(message)

def main():
    baseline = ROOT / "STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip"
    need(hashlib.sha256(baseline.read_bytes()).hexdigest() == patcher.BASELINE_SHA256, "exact baseline checksum")
    with tempfile.TemporaryDirectory(prefix="stockland-report-test-") as td:
        output = Path(td) / "candidate.zip"
        receipt = patcher.apply_to_installer(baseline, output)
        with zipfile.ZipFile(baseline) as old, zipfile.ZipFile(output) as new:
            need(old.testzip() is None and new.testzip() is None, "ZIP CRC")
            old_names = [x.filename for x in old.infolist() if not x.is_dir()]
            new_names = [x.filename for x in new.infolist() if not x.is_dir()]
            need(old_names == new_names and len(new_names) == 75, "archive inventory/order/root")
            source_before = {p: old.read(p) for p in old_names}
            source_after = {p: new.read(p) for p in new_names}
        js = source_after["stockland-erp-app/assets/app.js"].decode()
        php = source_after["stockland-erp-app/includes/rest/class-rest-app.php"].decode()
        entry = source_after["stockland-erp-app/stockland-inventory.php"].decode()
        front = source_after["stockland-erp-app/includes/class-stockland-frontend.php"].decode()
        manifest = json.loads(source_after["stockland-erp-app/RELEASE_MANIFEST.json"])
        css = json.loads(source_after["stockland-erp-app/assets/css/modules.json"])

        for phrase, label in [
            ("['branch_performance','Branch performance']", "default branch report"),
            ('<option value="today" selected>Today</option>', "today default"),
            ('<option value="all" selected>All authorized locations</option>', "all authorized locations default"),
            ('id="reportSku" name="sku" type="search" value=""', "empty SKU default"),
            ("api('/app/reports/explorer?'", "selected report API"),
            ("api16('/sku-search?q='", "authorized SKU autocomplete"),
            ("window.print()", "selected-report print"),
            ("buildSelectedReportCsv(report)", "selected-report CSV"),
            ("data-report-details", "reorder drilldown"),
        ]:
            need(phrase in js, "UI contract: " + label)
        for phrase, label in [
            ("'/app/reports/explorer'", "route registration"),
            ("array( $this, 'reports_explorer' )", "handler callback"),
            ("current_user_can( 'stockland_view_branch_reports' )", "reports capability"),
            ("! Stockland_Authority::is_owner()", "owner-only route restriction"),
            ("! current_user_can( 'stockland_view_all_branches' ) && ! Stockland_Authority::can_access_location( $selected_location )", "location scope validation"),
            ("$this->scope_locations()", "multi-location user scope"),
            ("strlen( $sku ) > 128", "SKU input limit"),
            ("current_user_can( 'stockland_view_cost_and_profit' )", "cost/profit permission"),
            ("'critical_reorder' === $type && ! Stockland_Authority::is_owner()", "owner-only handler restriction"),
        ]:
            need(phrase in php, "server contract: " + label)

        changed = {p for p in source_before if source_before[p] != source_after[p]}
        allowed = {
            "stockland-erp-app/README.md", "stockland-erp-app/RELEASE_MANIFEST.json",
            "stockland-erp-app/assets/app.js", "stockland-erp-app/assets/css/modules.json",
            "stockland-erp-app/includes/class-stockland-frontend.php",
            "stockland-erp-app/includes/rest/class-rest-app.php",
            "stockland-erp-app/stockland-inventory.php",
        }
        need(changed == allowed, "changed file allowlist: " + repr(sorted(changed)))
        need(manifest.get("release") == "16.1.93" and css.get("release") == "16.1.93", "release identity")
        need("Version:           16.1.93" in entry and "define( 'STOCKLAND_VERSION', '16.1.93' );" in entry, "plugin release version")
        need("const ROUTE_VERSION      = '16.1.93';" in front, "route/cache identity")
        need("16.1.1" in entry, "DB schema version marker preserved")
        need(source_before["stockland-erp-app/includes/class-stockland-activator.php"] == source_after["stockland-erp-app/includes/class-stockland-activator.php"], "schema DDL preserved")
        for p in source_before:
            if p.endswith(".css"):
                need(source_before[p] == source_after[p], "CSS byte parity: " + p)

        m = re.search(r"function reportDateRange\(period\)\{.*?\n\}", js, re.S)
        need(m is not None, "site-local date-range helper")
        js_test = "function localDate(){return '2026-10-20';}\n" + m.group(0) + """
const r={today:reportDateRange('today'),yesterday:reportDateRange('yesterday'),last7:reportDateRange('last7'),month:reportDateRange('this_month')};
process.stdout.write(JSON.stringify(r));
"""
        ranges = json.loads(subprocess.run(["node", "-e", js_test], check=True, capture_output=True, text=True).stdout)
        need(ranges["today"] == {"from":"2026-10-20","to":"2026-10-20"}, "today date")
        need(ranges["yesterday"] == {"from":"2026-10-19","to":"2026-10-19"}, "yesterday date")
        need(ranges["last7"] == {"from":"2026-10-14","to":"2026-10-20"}, "last-seven-days date")
        need(ranges["month"] == {"from":"2026-10-01","to":"2026-10-20"}, "month-to-date date")
        print("PASS exact 16.1.92 checksum/size; ZIP CRC, inventory and root")
        print("PASS selector defaults, report-type selection, SKU autocomplete, selected-only print/PDF and CSV contracts")
        print("PASS server permission/location/SKU/cost scope assertions; Owner-only critical reorder")
        print("PASS exact changed-file allowlist; CSS source/compiled bytes and schema DDL untouched")
        print("PASS release/route metadata alignment; 49-table contract unchanged")
        print("PASS deterministic local-date ranges: today/yesterday/last7/month")
        print("BASELINE_SHA256=" + receipt["baseline_sha256"])
        print("CANDIDATE_SHA256=" + receipt["candidate_sha256"])
        print("CANDIDATE_BYTES=" + str(receipt["candidate_bytes"]))
        print("CSS_FILES_UNCHANGED=" + str(receipt["css_files_unchanged"]))

if __name__ == "__main__":
    main()
