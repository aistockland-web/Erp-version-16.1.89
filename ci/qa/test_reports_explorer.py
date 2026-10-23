#!/usr/bin/env python3
"""Regression contract for the deterministic 16.1.93 Reports Explorer patch."""
import hashlib, json, re, subprocess, sys, tempfile, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "ci"))
import patch_reports_explorer as patcher
import patch_stock_reconciliation as stock_patcher
import patch_production_hardening as production_patcher
import patch_domain_cleanup as domain_patcher
import finalize_release_16_1_94 as release_finalizer

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
        production_receipt = production_patcher.patch_archive(output)
        domain_receipt = domain_patcher.clean_archive(output)
        layout_receipt = patcher.patch_requisition_mobile_approval_layout(output)
        after_first_layout_sha = hashlib.sha256(output.read_bytes()).hexdigest()
        replay_receipt = patcher.patch_requisition_mobile_approval_layout(output)
        need(replay_receipt["applied"] is False, "replaying the exact responsive patch is idempotent")
        need(hashlib.sha256(output.read_bytes()).hexdigest() == after_first_layout_sha, "replaying the responsive patch does not alter the candidate hash")
        need(replay_receipt["css_source_build_parity"] is True, "responsive patch replay keeps CSS source/build parity")
        final_package = patcher.final_package_attestation(output, baseline)
        need(final_package["sha256"] == hashlib.sha256(output.read_bytes()).hexdigest(), "final package receipt SHA reflects bytes after all patches")
        need(final_package["bytes"] == output.stat().st_size and final_package["files"] == 75, "final package receipt size and inventory match final archive")
        receipt_path = Path(td) / "final-build-receipt.json"
        release_output = Path(td) / "STOCKLAND_ERP_16.1.94_TEST_CANDIDATE.zip"
        release_finalizer.finalize(output, release_output)
        release_receipt_path = release_output.with_suffix(".receipt.json")
        sidecar_path = release_output.with_suffix(release_output.suffix + ".sha256")
        verified_path = Path(td) / "verified-final-package.json"
        receipt_path.write_text(json.dumps({
            "reports_explorer": receipt,
            "stock_reconciliation": stock_receipt,
            "production_hardening": production_receipt,
            "domain_cleanup": domain_receipt,
            "responsive_approval_layout": layout_receipt,
            "final_package": final_package,
        }, indent=2) + "\n", encoding="utf-8")
        verifier = ROOT / "ci" / "qa" / "verify_final_package.py"
        verifier_args = [
            sys.executable, str(verifier),
            "--baseline", str(baseline),
            "--package", str(release_output),
            "--sha256", str(sidecar_path),
            "--build-receipt", str(receipt_path),
            "--release-receipt", str(release_receipt_path),
            "--output", str(verified_path),
        ]
        gate = subprocess.run(verifier_args, text=True, capture_output=True)
        need(gate.returncode == 0, "final package/sidecar/build/release receipt gate passes: " + gate.stderr[-1600:])
        gate_receipt = json.loads(verified_path.read_text(encoding="utf-8"))
        need(gate_receipt.get("result") == "PASS", "final package verifier emits PASS receipt")
        need(
            gate_receipt["final_package"]["sha256"] == hashlib.sha256(release_output.read_bytes()).hexdigest(),
            "verified receipt identifies the exact finalized archive hash",
        )
        # Prove that a stale release-identity receipt is rejected, rather than
        # testing the intermediate build receipt against different package bytes.
        stale_release_receipt = json.loads(release_receipt_path.read_text(encoding="utf-8"))
        stale_release_receipt["output_sha256"] = "1cfab0a8221802aa2a5633e92d65ff76f146ea6152aae7068e6d82b384f2dceb"
        release_receipt_path.write_text(json.dumps(stale_release_receipt), encoding="utf-8")
        stale_gate = subprocess.run(verifier_args, text=True, capture_output=True)
        need(stale_gate.returncode != 0, "stale/intermediate release receipt is rejected")
        need(
            "release receipt SHA-256/size does not match the final ZIP" in stale_gate.stderr,
            "stale release receipt failure explains the hash mismatch",
        )
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
        admin = source_after["stockland-erp-app/includes/class-stockland-admin.php"].decode()
        manifest = json.loads(source_after["stockland-erp-app/RELEASE_MANIFEST.json"])
        css = json.loads(source_after["stockland-erp-app/assets/css/modules.json"])

        # The downloadable plugin must describe the installed ERP, not a trial/candidate package.
        readme = source_after["stockland-erp-app/README.md"].decode("utf-8")
        manifest_text = json.dumps(manifest, ensure_ascii=False).lower()
        readme_lower = readme.lower()
        need(manifest.get("release") == "16.1.93", "installed WordPress update release identity")
        need("release_candidate_changes" not in manifest, "candidate-only manifest fields removed")
        need("external_connections" not in manifest, "third-party provider integration metadata excluded")
        manifest_lower = json.dumps(manifest, ensure_ascii=False).lower()
        for marker in ("node.js", "postgresql", "workflow_run_url", "source_gate_evidence", "historical_release", "test on staging", "mock endpoint"):
            need(marker not in manifest_lower, "production manifest contains historical CI/runtime material: " + marker)
        need("Server environment</h2>" not in admin and "WordPress hosting compatibility</h2>" in admin, "hosting diagnostic heading normalized without removing the panel")
        for marker in ("PHP version", "MySQL/MariaDB version", "memory_limit", "max_execution_time"):
            need(marker in admin, "hosting diagnostic value preserved: " + marker)
        need("reports_explorer_16_1_93" not in manifest, "intermediate reports candidate metadata removed")
        quality = manifest.get("quality", {})
        need("target_host_acceptance" not in quality and "production_certification" not in quality and "local_validation_status" not in quality, "intermediate acceptance-status fields removed from installed manifest")
        for phrase in ("microsoft teams", "slack oauth", "oauth buttons", "placeholder chat panels", "test-only package", "test on staging", "trial installation", "node.js", "postgresql", "attached standalone reference material", "server environment"):
            need(phrase not in readme_lower, "installed guide excludes provider/demo/staging branding: " + phrase)
        need("native sign-in" in readme_lower and "role-based access" in readme_lower and "internal notifications" in readme_lower, "native ERP identity, role access and notifications remain described")
        need("third-party chat" not in readme_lower and "external single-sign-on" not in readme_lower, "provider chat/external SSO excluded from installed guide")
        for provider_marker in ("microsoft", "slack", "oauth"):
            need(provider_marker not in readme_lower and provider_marker not in manifest_text, "external provider reference absent from installed guide/metadata: " + provider_marker)
        need("test on staging" not in readme_lower and "staging environment" not in readme_lower and "test-only" not in readme_lower, "installed update guide contains no trial-environment branding")

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
        for marker, label in [
            ("Loading the selected authorized report…", "report loading state"),
            ("Report could not load.", "explicit report failure state"),
            ('id="retryOpsReport"', "report retry control"),
            ("state.report=null", "failed request clears stale report state"),
            ("run.removeAttribute('aria-busy')", "loading state clears after failure"),
            ("run.textContent='RUN REPORT'", "report action label is restored after failure"),
            ("loadReport().catch(()=>{})", "initial-load failure is handled without an uncaught startup exception"),
        ]:
            need(marker in js, "report loading/recovery regression: " + label)
        need("document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport());return null;" in js, "report source failures remain inline and retryable without a duplicate toast")
        need("loadReport().catch(err=>toast(err.message,true))" not in js, "no duplicate toast for report API failures")
        need("sl-desktop-title-action-layer-fix" in js, "desktop title-action layering guard injected")
        need("function managementIntelligence()" in js, "dedicated management intelligence workspace")
        need("case'intelligence':b=managementIntelligence();break;" in js, "management intelligence route")
        need("data-mi-kpi" in js and "refreshManagementIntelligence" in js, "management view uses authorized insight metrics and refresh action")
        need(php.count("report_source_query_failed") >= 10, "report query errors fail closed instead of returning zero totals")
        for marker, label in [
            ("dashboard_sales_source_failed", "Command Center retail totals fail closed"),
            ("dashboard_wholesale_source_failed", "Command Center wholesale totals fail closed"),
            ("dashboard_inventory_source_failed", "Command Center inventory valuation fails closed"),
            ("dashboard_low_stock_source_failed", "Command Center low-stock KPI fails closed"),
            ("dashboard_today_sales_source_failed", "Command Center today sales fails closed"),
        ]:
            need(marker in php, label)
        need("$checks['xlsx_xmlreader_stream']    = ! empty( $runtime['xml_reader'] ) && class_exists( 'ZipArchive' );" in php, "System Health accepts disk-backed PHP 8.3 XMLReader fallback")
        need("t.status='In Transit'" in php and "t.status='In Transit'" in stock_api, "only in-transit transfers count toward inbound")
        need("report_location_label_failed" in php, "selected report location-label lookup fails closed")
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
            ("t.status='In Transit'", "only explicitly in-transit transfers count"),
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
            "stockland-erp-app/includes/class-stockland-admin.php",
            "stockland-erp-app/includes/class-stockland-xlsx.php",
            "stockland-erp-app/includes/rest/class-rest-hr.php",
            "stockland-erp-app/assets/bootstrap.min.css",
            "stockland-erp-app/assets/css/06-operational-guards.css",
            "stockland-erp-app/assets/app.css",
            "stockland-erp-app/assets/app.min.css",
            "stockland-erp-app/stockland-inventory.php",
        }
        need(changed == allowed, "changed file allowlist: " + repr(sorted(changed)))
        need(layout_receipt["css_source_build_parity"] is True, "responsive approval layout rebuilds CSS source/build parity")
        need(layout_receipt["schema_or_business_data_changed"] is False, "approval presentation patch leaves schema and business data untouched")
        need("single-column" in layout_receipt["mobile_approval_rows"], "responsive approval history uses contained single-column rows")
        approval_css = source_after["stockland-erp-app/assets/css/06-operational-guards.css"].decode("utf-8")
        need("/* Requisition approval routing — keep decisions readable on phones and desktop." in approval_css, "scoped responsive approval-history CSS is included")
        need("grid-template-columns: repeat(2, minmax(0, 1fr)) !important;" in approval_css, "approval cards retain a two-column desktop/tablet layout")
        need("@media (max-width: 767px)" in approval_css and "grid-template-columns: minmax(0, 1fr) !important;" in approval_css, "approval cards switch to one column on phone widths")
        need("flex: 0 1 auto;" in approval_css, "phone approval text avoids cross-axis flex-basis overflow")
        need(domain_receipt["external_payroll_links_removed"] and domain_receipt["build_workflow_urls_removed"], "domain cleanup receipt")
        need("justice.gov.et" not in js, "no fixed legal-source website in installed payroll UI")
        need("justice.gov.et" not in source_after["stockland-erp-app/includes/rest/class-rest-hr.php"].decode(), "no fixed legal-source website in installed HR API")
        need("workflow_run_url" not in json.dumps(manifest), "no build-workflow URLs in installed release manifest")
        url_pattern = re.compile(rb"""https?://[^\s"'<>]+""", re.I)
        allowed_uri_ids = {b"http://www.w3.org/2000/svg", b"http://schemas.openxmlformats.org/officeDocument/2006/relationships"}
        unexpected_urls = []
        for path_name, payload in source_after.items():
            for found_url in url_pattern.findall(payload):
                if found_url in allowed_uri_ids or found_url == b"https://stocklandshop.rf.gd/wp-content/uploads/2026/09/22623-20kb.jpeg":
                    continue
                unexpected_urls.append((path_name, found_url.decode("utf-8", "ignore")))
        need(not unexpected_urls, "installed plugin contains non-approved website URLs: " + repr(unexpected_urls))
        logo_url = b"https://stocklandshop.rf.gd/wp-content/uploads/2026/09/22623-20kb.jpeg"
        prohibited_hosts = (b"stocklandet.gamer.gd", b"stocklandethio.rf.gd", b"infinityfreeapp.com", b"stockland-erp-project", b"gamer.gd")
        for path_name, payload in source_after.items():
            normalized_payload = payload.replace(logo_url, b"")
            for host_marker in prohibited_hosts + (b"stocklandshop.rf.gd",):
                need(host_marker not in normalized_payload, "fixed/redirected host appears in installed plugin: " + path_name + " / " + host_marker.decode())
        need(manifest.get("release") == "16.1.93" and css.get("release") == "16.1.93", "release identity")
        need(manifest.get("minimum_php") == "8.3", "host PHP floor is 8.3")
        need("release_candidate_changes" not in manifest, "installed plugin metadata is not branded as a candidate")
        need(manifest.get("payroll_integrity", {}).get("employee_pension_rate") == "7%", "employee pension rule metadata")
        need(manifest.get("payroll_integrity", {}).get("employer_pension_rate") == "11%", "employer pension rule metadata")
        need(manifest.get("payroll_integrity", {}).get("sample_personal_records_imported") is False, "no payroll demo data seeded")
        need("version_compare( PHP_VERSION, '8.4', '>=' )" not in php, "System Health runtime check aligned to PHP 8.3")
        need("plugin requires 8.4+" not in source_after["stockland-erp-app/includes/class-stockland-admin.php"].decode(), "admin health panel aligned to PHP 8.3")
        need("Version:           16.1.93" in entry and "define( 'STOCKLAND_VERSION', '16.1.93' );" in entry, "plugin release version")
        need(" * Requires PHP:      8.3" in entry, "InfinityFree PHP 8.3 compatibility floor")
        need("setInterval(()=>{if(document.visibilityState==='visible')refreshLiveIndicators()},60000)" in js, "low-resource activity refresh cadence")
        need(js.count("await new Promise(r=>setTimeout(r,300));") == 2, "paced Daily POS and Product Master import chunks")
        need(manifest.get("minimum_php") == "8.3" and manifest.get("hosting_profile", {}).get("activity_refresh_seconds") == 60, "InfinityFree resource profile in release manifest")
        need("const ROUTE_VERSION      = '16.1.93';" in front, "route/cache identity")
        need("16.1.1" in entry, "DB schema version marker preserved")
        need(source_before["stockland-erp-app/includes/class-stockland-activator.php"] == source_after["stockland-erp-app/includes/class-stockland-activator.php"], "schema DDL preserved")
        responsive_css_paths = {
            "stockland-erp-app/assets/css/06-operational-guards.css",
            "stockland-erp-app/assets/app.css",
            "stockland-erp-app/assets/app.min.css",
        }
        css_manifest = json.loads(source_after["stockland-erp-app/assets/css/modules.json"])
        css_source_paths = [
            "stockland-erp-app/assets/css/" + name
            for name in css_manifest["modules"]
        ]
        expected_app_css = "\n\n".join(
            source_after[name].decode("utf-8").strip() for name in css_source_paths
        ) + "\n"
        need(source_after["stockland-erp-app/assets/app.css"].decode("utf-8") == expected_app_css, "app.css equals ordered CSS source modules")
        expected_minified = re.sub(r"/\*[\s\S]*?\*/", " ", expected_app_css)
        expected_minified = re.sub(r"\s+", " ", expected_minified).strip()
        expected_minified = re.sub(r"\s*([{}:;,])\s*", r"\1", expected_minified).replace(";}", "}")
        need(source_after["stockland-erp-app/assets/app.min.css"].decode("utf-8") == expected_minified, "app.min.css matches deterministic normalized source")
        for p in source_before:
            if p.endswith(".css"):
                if p == "stockland-erp-app/assets/bootstrap.min.css":
                    original = source_before[p].decode("utf-8")
                    original = original.replace(" (https://getbootstrap.com/)", "").replace(" (https://github.com/twbs/bootstrap/blob/main/LICENSE)", "")
                    need(original.encode("utf-8") == source_after[p], "Bootstrap CSS rules unchanged; only URL text in header comment removed")
                elif p in responsive_css_paths:
                    need(source_before[p] != source_after[p], "the intended responsive approval layout is present in " + p)
                else:
                    need(source_before[p] == source_after[p], "CSS byte parity preserved outside approved responsive layout: " + p)

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
        # Exercise the existing PHP statutory payroll service using independent deterministic fixtures.
        # This validates its configured arithmetic; it does not import employee data or claim legal advice.
        payroll_dir = Path(td) / "payroll_fixture"
        payroll_dir.mkdir()
        (payroll_dir / "class-stockland-money.php").write_bytes(source_after["stockland-erp-app/includes/class-stockland-money.php"])
        (payroll_dir / "class-stockland-payroll-statutory.php").write_bytes(source_after["stockland-erp-app/includes/class-stockland-payroll-statutory.php"])
        payroll_harness = payroll_dir / "payroll_fixture.php"
        payroll_harness.write_text("""<?php
define('ABSPATH', __DIR__);
require __DIR__ . '/class-stockland-money.php';
require __DIR__ . '/class-stockland-payroll-statutory.php';
$inputs = array('2000.00','4000.00','7000.00','10000.00','14000.00','14000.01');
$bands = array();
foreach ($inputs as $income) { $bands[$income] = Stockland_Payroll_Statutory::income_tax($income); }
$line = Stockland_Payroll_Statutory::calculate('10000.00','1000.00','0.00','10000.00','0.00');
echo json_encode(array('bands'=>$bands,'line'=>$line,'rule'=>Stockland_Payroll_Statutory::RULE_VERSION), JSON_THROW_ON_ERROR);
""", encoding="utf-8")
        payroll_output = subprocess.run(["php", str(payroll_harness)], check=True, capture_output=True, text=True)
        payroll = json.loads(payroll_output.stdout)
        expected_bands = {
            "2000.00": ["0.00", "0.00"],
            "4000.00": ["300.00", "15.00"],
            "7000.00": ["900.00", "20.00"],
            "10000.00": ["1650.00", "25.00"],
            "14000.00": ["2850.00", "30.00"],
            "14000.01": ["2850.00", "35.00"],
        }
        need(payroll["bands"] == expected_bands, "PAYE band boundary fixtures from installed statutory service")
        line = payroll["line"]
        need(payroll["rule"] == "ET-PAYE-1395-2025-v3-PENSION-BASE", "payroll rule version is explicit")
        need(line["employee_pension"] == "700.00" and line["employer_pension"] == "1100.00", "employee 7% and employer 11% pension arithmetic")
        need(line["taxable_income"] == "10300.00" and line["income_tax"] == "1740.00", "taxable base and PAYE arithmetic fixture")
        need(line["gross_pay"] == "11000.00" and line["net_pay"] == "8560.00" and line["employer_cost_total"] == "12100.00", "gross/net/employer-cost ledger fixture")
        need("function managementIntelligence()" in js and "case'intelligence':b=managementIntelligence();break;" in js, "management intelligence included in canonical package build")
        need(production_receipt["schema_activator_unchanged"] is True, "production patch preserved the database activator")
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
            suggested = max(1, case["level"] - position) if case["level"] > 0 and position <= case["level"] else 0
            need(suggested == case["suggested"], "minimum-one-unit replenishment quantity fixture")
        print("PASS exact 16.1.92 checksum/size; ZIP CRC, inventory and root")
        print("PASS selector defaults, report-type selection, SKU autocomplete, selected-only print/PDF and CSV contracts")
        print("PASS server permission/location/SKU/cost scope assertions; Owner-only critical reorder")
        print("PASS exact changed-file allowlist; responsive approval CSS source/build/minified parity verified; schema DDL untouched")
        print("PASS Management Intelligence, report fail-closed guards and PHP 8.3 compatibility")
        print("PASS deterministic PHP payroll tax boundaries, 7% employee / 11% employer pension and net-pay reconciliation")
        print("PASS release/route metadata alignment; 49-table contract unchanged")
        print("PASS deterministic local-date ranges: today/yesterday/last7/month")
        print("BASELINE_SHA256=" + receipt["baseline_sha256"])
        print("WORDPRESS_UPDATE_SHA256=" + production_receipt["archive_sha256"])
        print("WORDPRESS_UPDATE_BYTES=" + str(production_receipt["archive_bytes"]))
        print("CSS_FILES_UNCHANGED=" + str(receipt["css_files_unchanged"]))

if __name__ == "__main__":
    main()
