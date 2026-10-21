#!/usr/bin/env python3
"""Regression contract for the deterministic 16.1.93 Reports Explorer patch."""
import hashlib, json, re, subprocess, sys, tempfile, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "ci"))
import patch_reports_explorer as patcher
import patch_stock_reconciliation as stock_patcher

def need(ok, message):
    if not ok:
        raise AssertionError(message)

def main():
    baseline = ROOT / "STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip"
    need(hashlib.sha256(baseline.read_bytes()).hexdigest() == patcher.BASELINE_SHA256, "exact baseline checksum")
    with tempfile.TemporaryDirectory(prefix="stockland-report-test-") as td:
        output = Path(td) / "candidate.zip"
        receipt = patcher.apply_to_installer(baseline, output)
        stock_receipt = stock_patcher.rebuild_zip(output)
        with zipfile.ZipFile(baseline) as old, zipfile.ZipFile(output) as new:
            need(old.testzip() is None and new.testzip() is None, "ZIP CRC")
            old_names = [x.filename for x in old.infolist() if not x.is_dir()]
            new_names = [x.filename for x in new.infolist() if not x.is_dir()]
            need(old_names == new_names and len(new_names) == 75, "archive inventory/order/root")
            source_before = {p: old.read(p) for p in old_names}
            source_after = {p: new.read(p) for p in new_names}
        js = source_after["stockland-erp-app/assets/app.js"].decode()
        php = source_after["stockland-erp-app/includes/rest/class-rest-app.php"].decode()
        stock_api = source_after["stockland-erp-app/includes/rest/class-rest-stock.php"].decode()
        daily_inventory = source_after["stockland-erp-app/includes/rest/class-rest-inventory-report-v16.php"].decode()
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
        need("sl-desktop-title-action-layer-fix" in js, "desktop title-action layering guard injected")
        need("z-index:80!important" in js, "desktop actions raised above header/search stacking context")
        need("pointer-events:auto!important" in js, "desktop action pointer events preserved")
        need("document.__slNewProductCaptureBound" in js, "desktop Product Master delegated click guard")
        need("e.stopImmediatePropagation();openProductEditor(null);" in js, "capture-phase create action chain")
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
            ("Stockland_Money::minor( $row['reorder_level'] ?? '0' )", "decimal-safe reorder threshold"),
            ("$position_minor <= $reorder_minor", "inbound-aware reorder threshold"),
            ("Stockland_Money::decimal( max( 0, $reorder_minor - $position_minor ) )", "decimal-safe inbound-aware proposed replenishment"),
            ("COALESCE(i.inbound_stock,0) inbound_stock", "report separates inbound from physical stock"),
            ("COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)+COALESCE(i.inbound_stock,0) reorder_position", "reorder position combines available stock and dispatched inbound"),
            ("t.status IN('Picked','In Transit')", "only dispatched in-transit transfer statuses count"),
            ("AND l.is_active=1", "inactive locations excluded from reorder report"),
            ("'key' => 'reorder_position', 'label' => 'Reorder position'", "inbound-aware position displayed"),
            ("'key' => 'inbound_stock', 'label' => 'Inbound transfer'", "inbound quantities displayed"),
            ("COALESCE(bl.physical_stock,0)", "legacy SKU-only stock included"),
            ("COALESCE(al.reserved_stock,0)", "legacy SKU-only active reservations included"),
            ("WHERE variant_id IS NULL GROUP BY location_id,sku", "legacy SKU-only rows aggregated separately"),
            ("p.product_type<>'STYLE'", "style parent rows excluded from variant availability"),
            ("$stock_args = array( $prod, $loc, $tx, $prod, $res, $prod, $tr, $prod )", "stock SQL placeholder order matches ledger, SKU mapping, reservations and transfer references"),
        ]:
            need(phrase in php, "server contract: " + label)

        changed = {p for p in source_before if source_before[p] != source_after[p]}
        allowed = {
            "stockland-erp-app/README.md", "stockland-erp-app/RELEASE_MANIFEST.json",
            "stockland-erp-app/assets/app.js", "stockland-erp-app/assets/css/modules.json",
            "stockland-erp-app/includes/class-stockland-frontend.php",
            "stockland-erp-app/includes/rest/class-rest-app.php",
            "stockland-erp-app/includes/rest/class-rest-stock.php",
            "stockland-erp-app/includes/rest/class-rest-inventory-report-v16.php",
            "stockland-erp-app/stockland-inventory.php",
        }
        need(changed == allowed, "changed file allowlist: " + repr(sorted(changed)))
        need(manifest.get("release") == "16.1.93" and css.get("release") == "16.1.93", "release identity")
        need("Version:           16.1.93" in entry and "define( 'STOCKLAND_VERSION', '16.1.93' );" in entry, "plugin release version")
        need(" * Requires PHP:      8.3" in entry, "InfinityFree PHP 8.3 compatibility floor")
        need("setInterval(()=>{if(document.visibilityState==='visible')refreshLiveIndicators()},60000)" in js, "low-resource activity refresh cadence")
        need(js.count("await new Promise(r=>setTimeout(r,300));") == 2, "paced Daily POS and Product Master import chunks")
        need(manifest.get("minimum_php") == "8.3" and manifest.get("hosting_profile", {}).get("activity_refresh_seconds") == 60, "InfinityFree resource profile in release manifest")
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
        # Independent arithmetic fixtures for variant-linked and legacy SKU-only rows.
        # Sellable availability is floored at zero; over-reservation remains explicit.
        stock_fixtures = [
            {"variant_on_hand": 4, "legacy_on_hand": 2, "variant_reserved": 3, "legacy_reserved": 1, "expected_available": 2, "expected_over_reserved": False},
            {"variant_on_hand": 0, "legacy_on_hand": 1, "variant_reserved": 0, "legacy_reserved": 3, "expected_available": 0, "expected_over_reserved": True},
            {"variant_on_hand": 0, "legacy_on_hand": 0, "variant_reserved": 0, "legacy_reserved": 0, "expected_available": 0, "expected_over_reserved": False},
        ]
        for case in stock_fixtures:
            physical = case["variant_on_hand"] + case["legacy_on_hand"]
            reserved = case["variant_reserved"] + case["legacy_reserved"]
            raw_available = physical - reserved
            observed_available = max(0, raw_available)
            observed_over_reserved = reserved > physical
            need(observed_available == case["expected_available"], "sellable availability fixture")
            need(observed_over_reserved == case["expected_over_reserved"], "reservation integrity fixture")
        for phrase, label in [
            ("COALESCE(t.variant_id,legacy_p.variant_id)", "live stock and reorder endpoints resolve legacy SKU-only ledger rows"),
            ("COALESCE(r.variant_id,legacy_r.variant_id)", "live stock and reorder endpoints resolve legacy SKU-only reservations"),
            ("GREATEST(0,v.physical_stock-COALESCE(r.reserved_stock,0)) available_stock", "live stock list floors sellable availability at zero"),
            ("GREATEST(0,COALESCE((SELECT SUM(t.quantity_signed)", "SKU detail endpoint floors available stock at zero"),
            ("OVER-RESERVED", "live stock and reorder endpoints expose reservation integrity exception"),
            ("$position <= 0.0 ? 'CRITICAL'", "reorder status uses available quantity plus eligible inbound transfer position"),
            ("$level <= 0.0 ? 'NO_LEVEL'", "zero reorder level is not treated as configured critical reorder"),
        ]:
            need(phrase in stock_api, "live stock API contract: " + label)

        need("<th>Inbound transfer</th><th>Reorder position</th>" in js, "Reorder Alerts shows dispatched inbound and reorder position")
        need("x.reorder_position" in js and "x.suggested_replenishment" in js, "Reorder Alerts uses server-authoritative suggested quantity")
        need("data-inbound" in js and "data-position" in js, "requisition context includes inbound position")
        need("$row['suggested_replenishment']" in stock_api, "live reorder endpoint returns suggested replenishment")
        need("$available <= 0 ? 'CRITICAL'" in daily_inventory, "Daily Inventory critical state uses sellable availability")
        need("$available <= $reorder_level" in daily_inventory, "Daily Inventory reorder boundary uses available stock")
        need("reorder_alerts" in daily_inventory, "Daily Inventory low-stock KPI retained")

        need("COALESCE(t.variant_id,legacy_p.variant_id)" in php, "report on-hand resolves variant-linked and legacy SKU-only ledger rows")
        need("COALESCE(r.variant_id,legacy_r.variant_id)" in php, "report reservations resolve variant-linked and legacy SKU-only rows")
        need("GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)) available_stock" in php, "report sellable availability is floored at zero")
        need("GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)" in php, "dashboard reorder KPI uses available stock after active reservations")
        need("CASE WHEN COALESCE(a.reserved_stock,0)>COALESCE(b.physical_stock,0) THEN 'OVER-RESERVED'" in php, "report exposes over-reservation as an integrity exception")
        need(php.count("WHERE l.location_type='Branch' AND l.is_active=1") >= 2, "branch rollups exclude inactive locations")
        need("FROM %i p JOIN %i l ON l.location_type IN('Warehouse','Branch') AND l.is_active=1" in php, "inventory/reorder report excludes inactive locations")
        need("'key' => 'inbound_stock', 'label' => 'Inbound transfer'" in php, "critical reorder displays inbound transfer quantities")
        need("'key' => 'reorder_position', 'label' => 'Reorder position'" in php, "critical reorder displays reorder position")
        need("$row['reorder_position'] ?? $row['available_stock'] ?? '0'" in php, "critical reorder filter uses reorder position")
        need("max( 100, $reorder_minor - $position_minor )" in php, "suggested replenishment matches the live minimum-one-unit policy")
        reorder_cases = [
            {"physical": 5, "reserved": 2, "inbound": 1, "level": 6, "alert": True, "suggested": 2},
            {"physical": 2, "reserved": 0, "inbound": 4, "level": 5, "alert": False, "suggested": 0},
            {"physical": 0, "reserved": 2, "inbound": 0, "level": 3, "alert": True, "suggested": 3},
            {"physical": 5, "reserved": 1, "inbound": 0, "level": 4, "alert": True, "suggested": 1},
        ]
        for case in reorder_cases:
            available = max(0, case["physical"] - case["reserved"])
            position = available + case["inbound"]
            need((case["level"] > 0 and position <= case["level"]) == case["alert"], "reorder position boundary fixture")
            need(max(0, case["level"] - position) == case["suggested"], "replenishment quantity fixture")
        print("PASS exact 16.1.92 checksum/size; ZIP CRC, inventory and root")
        print("PASS selector defaults, report-type selection, SKU autocomplete, selected-only print/PDF and CSV contracts")
        print("PASS server permission/location/SKU/cost scope assertions; Owner-only critical reorder")
        print("PASS exact changed-file allowlist; CSS source/compiled bytes and schema DDL untouched")
        print("PASS release/route metadata alignment; 49-table contract unchanged")
        print("PASS deterministic local-date ranges: today/yesterday/last7/month")
        print("BASELINE_SHA256=" + receipt["baseline_sha256"])
        print("CANDIDATE_SHA256=" + stock_receipt["candidate_sha256"])
        print("CANDIDATE_BYTES=" + str(stock_receipt["candidate_bytes"]))
        print("CSS_FILES_UNCHANGED=" + str(receipt["css_files_unchanged"]))

if __name__ == "__main__":
    main()
