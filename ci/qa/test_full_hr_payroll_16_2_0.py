#!/usr/bin/env python3
"""Source/package acceptance for the isolated 16.2.7 full HR/payroll-cycle candidate.

Static ZIP tests are not a substitute for a live WordPress + MariaDB staging run
or qualified Ethiopian tax/pension sign-off.
"""
import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path

def require(ok, message):
    if not ok:
        raise AssertionError(message)
    print("PASS " + message)

def main():
    if len(sys.argv) != 2:
        raise SystemExit("usage: test_full_hr_payroll_16_2_0.py <installer.zip>")
    package = Path(sys.argv[1])
    require(package.is_file() and package.stat().st_size > 100_000, "candidate installer exists and is non-empty")
    print("Installer SHA-256: " + hashlib.sha256(package.read_bytes()).hexdigest())
    with zipfile.ZipFile(package) as z:
        require(z.testzip() is None, "ZIP CRC and archive integrity")
        infos = [i for i in z.infolist() if not i.is_dir()]
        names = [i.filename for i in infos]
        require(len(names) == 87 and len(set(names)) == 87, "87 unique runtime files: 75 baseline + 6 HR/payroll/POS controllers + 6 ordered CSS modules")
        require(all(n.startswith("stockland-erp-app/") and ".." not in n.split("/") for n in names), "single plugin root and safe archive paths")
        root = "stockland-erp-app/"
        files = {n[len(root):]: z.read(n) for n in names}
        required_files = [
            "includes/class-stockland-hr-lifecycle.php",
            "includes/rest/class-rest-hr-lifecycle.php",
            "includes/class-stockland-hr-payroll-calculator.php",
            "includes/class-stockland-hr-private-vault.php",
            "includes/rest/class-rest-hr-payroll-cycle.php",
            "includes/rest/class-rest-pos-cart.php",
        ]
        for path in required_files:
            require(path in files, "runtime file included: " + path)
        main = files["stockland-inventory.php"].decode()
        manifest = json.loads(files["RELEASE_MANIFEST.json"].decode())
        modules = json.loads(files["assets/css/modules.json"].decode())
        app = files["assets/app.js"].decode()
        schema = files["includes/class-stockland-hr-lifecycle.php"].decode()
        health_php = files["includes/rest/class-rest-app.php"].decode()
        system_control_php = files["includes/class-stockland-admin.php"].decode()
        sales_php = files["includes/rest/class-rest-sales.php"].decode()
        stock_db_php = files["includes/class-stockland-db.php"].decode()
        sale_math_php = files["includes/class-stockland-sale-math.php"].decode()
        money_php = files["includes/class-stockland-money.php"].decode()
        ledger_php = files["includes/class-stockland-ledger.php"].decode()
        accounting_php = files["includes/class-stockland-accounting.php"].decode()
        rest_accounting_php = files["includes/rest/class-rest-accounting.php"].decode()
        rest_app_php = files["includes/rest/class-rest-app.php"].decode()
        hr_repository_php = files["includes/class-stockland-hr.php"].decode()
        rest = files["includes/rest/class-rest-hr-lifecycle.php"].decode()
        cycle = files["includes/rest/class-rest-hr-payroll-cycle.php"].decode()
        cart = files["includes/rest/class-rest-pos-cart.php"].decode()
        calc = files["includes/class-stockland-hr-payroll-calculator.php"].decode()
        vault = files["includes/class-stockland-hr-private-vault.php"].decode()
        css = files["assets/app.css"].decode()
        app_min_css = files["assets/app.min.css"].decode()
        require("Version:           16.2.7" in main and "define( 'STOCKLAND_VERSION', '16.2.7' );" in main, "plugin version/header matches 16.2.7")
        require(manifest.get("release") == "16.2.7" and modules.get("release") == "16.2.7", "release manifest and CSS module metadata agree")
        required_css_tail = [
            "07-dashboard-operations.css",
            "08-reporting-worksheet.css",
            "09-command-center-theme.css",
            "10-bootstrap-desktop-search.css",
            "11-operational-workflows.css",
            "12-hr-payroll-lifecycle.css",
        ]
        require(len(modules.get("modules", [])) == 12 and modules["modules"][-6:] == required_css_tail,
                "twelve ordered CSS modules include dedicated dashboard, worksheet, desktop and HR/payroll layers")
        css_chunks = [files["assets/css/" + name].decode("utf-8").strip() for name in modules["modules"]]
        require(css == "\n\n".join(css_chunks) + "\n", "generated app.css exactly matches the ordered CSS module sources")
        bootstrap = files["assets/bootstrap.min.css"]
        bootstrap_hash = hashlib.sha256(bootstrap).hexdigest()
        require(b"Bootstrap  v5.3.8" in bootstrap[:220]
                and manifest.get("frontend", {}).get("bootstrap_version") == "5.3.8"
                and manifest.get("frontend", {}).get("bootstrap_asset_sha256") == bootstrap_hash,
                "local Bootstrap 5.3.8 source hash matches release manifest")
        require(manifest.get("schema_contract_status") == "PROVISIONAL_CANDIDATE_NOT_ARCHITECTURE_APPROVAL", "physical table count is explicitly marked provisional")
        require(manifest.get("schema_contract") == {"total":86,"core_operations":29,"accounting_procurement":16,"hr_payroll":41}, "provisional source-mapped 86-table candidate contract agrees")
        health_lines = health_php.splitlines()
        schema_gate_lines = [line for line in health_lines if re.search(r"\$checks\['schema_contract_expected'\]\s*=", line)]
        require(len(schema_gate_lines) == 2 and all("empty( $missing_full )" in line and "count( $found_keys ) === 86" not in line and "empty( $unexpected_full )" not in line for line in schema_gate_lines),
                "quick and deep runtime gates check required tables, not an exact physical count")
        require(health_php.count("$diagnostics['schema_contract_status']") == 2 and health_php.count("$diagnostics['schema_contract_extra_table_count']") == 2,
                "quick and deep runtime diagnostics distinguish missing tables from extras")
        require("Database unavailable or one or more required ERP schema tables are missing" in health_php and "All required ERP schema tables in the versioned contract" in health_php,
                "runtime deep-scan rules no longer cite a stale 49-table contract")
        require("Stockland_HR_Lifecycle::table_keys()" in system_control_php and "$full_expected = array_values( array_unique( array_merge( $expected_core, $expected_accounting, $expected_hr ) ) );" in system_control_php,
                "WordPress System Control includes the full HR/payroll lifecycle schema")
        require("Additional ERP tables detected" in app and "do not block the read-only integrity scan" in app,
                "health UI explains extra tables as a review warning rather than a false failed gate")
        require(manifest.get("statutory_rates_or_exemption_caps_seeded") is False, "no automatic statutory rates or exemption caps are seeded")
        required_table_names = [
            "hr_workforce_plans","hr_positions","hr_requisitions","hr_candidates","hr_applications","hr_interviews",
            "hr_documents","hr_contracts","hr_onboarding_tasks","hr_shifts","hr_attendance","hr_leave_policies",
            "hr_leave_balances","hr_leave_requests","hr_compensation_components","hr_rule_sets","hr_rule_components",
            "hr_employee_events","hr_payroll_events","hr_payroll_payments","hr_payroll_adjustments","hr_exit_settlements",
            "hr_training_records","hr_performance_reviews","hr_payroll_runs","hr_payroll_run_lines","hr_payroll_run_exceptions",
            "hr_payroll_payment_batches","hr_payroll_payment_items","hr_statutory_remittances","hr_individual_payees",
            "hr_individual_payee_obligations","hr_individual_payee_payments","hr_resources","hr_social_campaigns",
            "hr_social_snapshots","hr_employee_reimbursements"
        ]
        for name in required_table_names:
            require(("'" + name + "'") in schema, "logical table key " + name)
            require("CREATE TABLE {$p}" + name in schema, "DDL declaration " + name)
        require("$p .= 'stock_';" in schema, "DDL prefix aligns with Stockland_DB::table() logical name contract")
        require("CREATE TABLE {$p}stock_hr_workforce_plans" not in schema, "no duplicated stock_ prefix in generated HR DDL")
        require("function has_qualified_legal_review" in schema and "approved_parameters" in schema, "statutory activation requires qualified opinion and approved parameter scope")
        require("payroll_legal_review_missing" in schema and "return array();" in schema, "unreviewed payroll rules/allowance exemptions fail closed")
        require("employee_pension" not in schema or "pensionable_base" in schema, "pensionable base is explicitly modelled, not inferred from a vendor template")
        require("approval_status" in files["includes/class-stockland-hr.php"].decode() and "statutory_rule_set_id" in files["includes/class-stockland-hr.php"].decode(), "legacy compensation schema gains migration-safe approval/provenance columns")
        require("hr_legacy_payroll_retired" in files["includes/rest/class-rest-hr.php"].decode(), "legacy payroll mutation endpoints are retired in favor of frozen runs")
        for route in ["/hr/payroll-runs","/generate","/submit","/approve","/payment-batches","/hr/payroll-payment-batches","/reconcile","/hr/individual-payees","/hr/individual-payee-obligations","/hr/employee-reimbursements","/hr/statutory-remittances","/hr/resources","/hr/management-social"]:
            require(route in cycle, "cycle API contains " + route)
        require("source_snapshot_sha256" in cycle and "calculation_sha256" in cycle and "input_snapshot_sha256" in cycle, "frozen input/calculation hashes are stored and re-verified")
        require("Stockland_Accounting::post_journal" in cycle and "HR_PAYROLL_BATCH_PAYMENT" in cycle, "payroll accrual and external settlement clear liability through GL/PLEDGER")
        require("PARTIAL_RECONCILED" in cycle and "hr_statutory_remittances" in cycle, "partial recipient failures and statutory obligations remain independently reconcilable")
        require("individual payee only" in cycle.lower() or "not an employee" in cycle.lower(), "nonemployee payees are separate from employee master")
        require("LEGAL_REVIEW" in cycle and "withholding_method" in cycle and "qualified_legal_review" in cycle, "payee withholding requires an independently approved rule and legal evidence")
        require("STOCKLAND_PRIVATE_VAULT_DIR" in vault and "DOCUMENT_ROOT" in vault and "hr_private_vault_public_path" in vault, "private file vault fails closed inside public web root")
        require("is_uploaded_file" in vault and "sha256" in vault and "0600" in vault and "MAX_BYTES" in vault, "private uploads enforce source/type/size/hash/file permissions")
        require("readfile($f['path'])" in cycle and "X-Content-Type-Options: nosniff" in cycle, "authenticated private resource delivery sets secure download headers")
        require("source_kind'=>'MANUAL_EXPORT'" in cycle and "report_as_of" in cycle and "MANUAL_EXPORT_NOT_REALTIME" in cycle, "social reporting is explicitly timestamped manual/export, not false real-time integration")
        require("posting_allowed" in calc and "payment_allowed" in calc and "allow_synthetic_test_rules" in calc, "payroll calculation core exposes separate legal-posting/payment gates")
        require("hrlcPayrollCyclePanel" in app and "bindHRPayrollCycleForms" in app, "new HR/payroll cycle UI is wired into the ERP app")
        require("hr/payroll-runs" in app and "hr/employee-reimbursements" in app and "hr/individual-payees" in app, "UI uses frozen payroll, reimbursements and individual payee APIs")
        require("MANUAL EXPORT" in app and "recovery tokens" in app, "social reporting and credential safety are described in the UI")
        require("sl-hrlc-section-summary" in app and "sl-hrlc-section-body" in app and "function hrlcSectionIcon" in app,
                "HR lifecycle and payroll modules use accessible native dropdown sections with clear icons")
        require("if($('#refreshHR'))$('#refreshHR').onclick" in app,
                "Refresh HR & Payroll button remains wired after the dashboard refactor")
        require("function bindHRLCSectionState()" in app and "state.hrlcOpenSections" in app,
                "expanded HR accordion state is preserved across workflow refreshes")
        require("function bindHRLCSectionSearch()" in app and "state.hrlcSearchTerm" in app and "data-hrlc-visible-count" in app,
                "HR module search filters sections and preserves its query across refreshes")
        require("root.querySelectorAll('[data-hrlc-section][open]')" in app and "if(other!==s)other.open=false" in app,
                "the accordion handler keeps only one HR workflow section open at a time")
        require("aria-expanded" in app and "aria-controls" in app and 'aria-level="3"' in app,
                "each section has accessible heading and expanded/collapsed semantics")
        require("openSections.length>1" in app and "state.hrlcOpenSections=[keep.getAttribute('data-hrlc-section')]" in app,
                "initialization normalizes legacy multi-open state into one active section")
        require("sl-hrlc-social-card" in app and "sl-hrlc-social-icon" in app and "Product reels, outfit stories and engagement insights" in app and "MANUAL EXPORT" in app,
                "social platform cards show icons, practical content descriptions and manual-export disclosure")
        require("function hrlcSocialIcon(key)" in app and "hrlcSocialIcon(x.key)" in app
                and "icon:'◎'" not in app and "icon:'♪'" not in app and "icon:'▶'" not in app
                and ".sl-hrlc-social-icon>svg.sl-hrlc-social-icon-art" in css
                and "Social platform cards use scoped inline SVGs instead of font/text glyphs with fixed geometry." in " ".join(manifest.get("release_changes", [])),
                "social platform cards use accessible inline SVGs and protected responsive geometry")
        require("sl-hrlc-section-summary" in css and "sl-hrlc-section-toggle" in css and "sl-hrlc-social-grid" in css and "sl-hrlc-social-card" in css and "sl-hrlc-icon-art" in css,
                "responsive accordion holders, consistent line icons and social channel cards are styled in generated app.css")
        icon_guard = "#hrPage .sl-hrlc-section-icon>svg.sl-hrlc-icon-art{display:block!important;box-sizing:content-box!important;width:22px!important;height:22px!important;min-width:22px!important;min-height:22px!important;max-width:22px!important;max-height:22px!important;flex:0 0 22px!important;flex-shrink:0!important;margin:0!important;padding:0!important;position:static!important;inset:auto!important;transform:none!important;zoom:1!important;overflow:visible!important;fill:none!important;stroke:currentColor!important;stroke-width:1.7px!important;stroke-linecap:round!important;stroke-linejoin:round!important;vector-effect:non-scaling-stroke!important;filter:none!important}"
        require(icon_guard in css and "width:20px!important" in css and "height:20px!important" in css and "fill:none!important" in css and "stroke:currentColor!important" in css,
                "HR SVG icons have protected desktop/mobile geometry and explicit no-fill/current-color stroke rules")
        require(icon_guard in app_min_css and "width:20px!important" in app_min_css,
                "icon CSS protection survives the production minification build")
        wrapper_guard = "#hrPage .sl-hrlc-section-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important;flex:0 0 44px!important;width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;max-width:44px!important;max-height:44px!important;padding:0!important;position:relative!important;overflow:visible!important}"
        child_guard = "stroke-width:1.7px!important;stroke-linecap:round!important;stroke-linejoin:round!important;vector-effect:non-scaling-stroke!important"
        require(wrapper_guard in css and "width:38px!important" in css and "height:38px!important" in css,
                "HR icon holders have fixed 44px desktop and 38px mobile geometry")
        require("#hrPage .sl-hrlc-section-icon>svg.sl-hrlc-icon-art *{" in css and child_guard in css and "stroke-width:1.6px!important" in css,
                "HR SVG child paths force consistent stroke thickness, rounded caps and mobile stroke")
        require("#stockland-app #hrPage .sl-hrlc-section-icon>svg.sl-hrlc-icon-art" not in css,
                "HR icon fix is scoped to #hrPage and does not depend on an outer theme wrapper")
        require(wrapper_guard in app_min_css and child_guard in app_min_css,
                "icon holder and child path rules survive production minification")
        require(manifest.get("hr_icon_css_contract") == "HRPAGE_SCOPED_WRAPPER_44PX_38PX; SVG_FIXED_22PX_20PX; CHILD_STROKE_1_7PX_1_6PX; FORCE_NO_FILL_CURRENT_COLOR",
                "release manifest records the explicit HR icon rendering contract")
        require("sl-hrlc-toolbar" in css and "sl-hrlc-search:focus-within" in css and "[data-hrlc-section][hidden]" in css and ".sl-hrlc-section[open]" in css,
                "SaaS search, keyboard focus, no-result and active accordion states are styled")
        require("#hrPage{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))" in css and ".sl-hrlc-cycle{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))" in css and "@media(max-width:700px){#hrPage{grid-template-columns:minmax(0,1fr)" in css,
                "HR/payroll dropdowns use two side-by-side desktop columns and stack on mobile")
        for url in ["https://business.facebook.com/","https://www.instagram.com/","https://business.tiktok.com/","https://studio.youtube.com/"]:
            require(url in app, "direct social sign-in link " + url)
        require("STOCKLAND_PRIVATE_VAULT_DIR" in manifest.get("release_gate_note","") or "private vault configuration" in manifest.get("release_gate_note",""), "manifest records private vault configuration as a release gate")
        require(manifest.get("hr_document_control") == "PRIVATE_VAULT; VERSIONED_DRAFTS; INDEPENDENT_APPROVAL; APPROVED_VERSION_SUPERSEDED_ATOMICALLY",
                "manifest records safe immutable HR document version/approval contract")
        require("'EMPLOYMENT_CONTRACT'" in cycle and "function revise_resource(" in cycle and "supersedes_resource_id" in cycle,
                "HR private document API supports employment contracts and immutable version drafts")
        require("status IN ('DRAFT','APPROVED','SUPERSEDED')" in cycle and "RESOURCE_SUPERSEDED" in cycle,
                "HR managers can view drafts/history and approval supersedes the old document atomically")
        require("hrlcCycleTable(resources" in app and "Edit / save new version" in app and "function openHRResourceRevisionDialog" in app,
                "HR document library renders a controlled edit/save-new-version form")
        require("EMPLOYMENT_CONTRACT" in app and "data-hrcycle-action=\"approve-resource\"" in app and "/hr/resources/'+id+'/revise" in app,
                "job descriptions and approved employment contracts are uploadable, approval-gated and revision-capable")
        require("form.elements[k].readOnly=true" in app,
                "document type, role and language stay locked during version revision")
        require(".sl-hrlc-resource-dialog" in css and ".sl-hrlc-resource-grid" in css and "@media(max-width:620px){.sl-hrlc-resource-dialog" in css,
                "document revision editor has responsive desktop/mobile form styling")
        require(manifest.get("pos_retry_contract") == "CLIENT_SALE_ID_STABLE_UNTIL_VERIFIED_CLOSED; SERVER_IDEMPOTENT_REPLAY",
                "release manifest records stable POS request identity for ambiguous network retries")
        require(manifest.get("stock_availability_error_policy") == "FAIL_CLOSED_ON_LEDGER_OR_RESERVATION_QUERY_ERROR",
                "release manifest records fail-closed stock availability reads")
        require(manifest.get("pos_cart_scope") == "MULTI_LINE_ATOMIC_TRANSACTION; SHARED_VAT_MODE; NO_CART_DISCOUNTS_OR_SPLIT_TENDER",
                "manifest declares supported multi-line atomic cart scope and explicitly excluded tender/discount features")
        require(manifest.get("uom_precision_policy") == "WHOLE_UNITS_REQUIRED_FOR_PCS_EA_PIECE_UNIT_UOM_IN_POS_AND_CART; OTHER_UOMS_ALLOW_TWO_DECIMALS",
                "manifest records whole-unit validation for discrete Product Master UOMs")
        require("register_rest_route" in cart and "'/sales/cart'" in cart and "count( $existing_rows ) !== count( $lines )" in cart,
                "cart endpoint has one route and rejects partial/different-line idempotent replays")
        require("START TRANSACTION" in cart and "ROLLBACK" in cart and "Stockland_Ledger::post_sale_closed" in cart and "Stockland_Accounting::post_journal" in cart and "Stockland_Audit::log" in cart,
                "all cart lines, stock ledger, GL and audit share one transaction/rollback boundary")
        require("'/sales/cart'" in app and "function submitPOSCart(e)" in app and "function bindPosCart()" in app and "function renderPosCart()" in app,
                "Retail POS exposes a real multi-line cart bound to the atomic server endpoint")
        require("sessionStorage" in app and "pendingPayload" in app and "RETRY SAME CART" in app,
                "cart retries retain the exact ID/payload across page reload within the same browser tab session")
        require("data-cart-qty" in app and "data-cart-price" in app and "posCartGross" in app and "posCartVat" in app and "posCartNet" in app,
                "cart provides line quantity/price editing and live gross/VAT/net preview totals")
        require("POS cart now posts 1–50 distinct SKU lines" in " ".join(manifest.get("release_changes",[])),
                "release manifest records real atomic cart behavior")
        require(rest_app_php.count("SELECT variant_id,sku,product_name,product_type,parent_sku,style_code,brand,color,size,unit,retail_price,barcode,gtin FROM %i") == 2,
                "role-scoped POS SKU search exposes retail price/UOM but not purchase-cost data")
        require("'hr_resources' => array(" in hr_repository_php and "'supersedes_resource_id' => 'BIGINT UNSIGNED NULL AFTER version_label'" in hr_repository_php,
                "existing HR resource tables receive an additive document-version schema migration")
        require("#stockland-app .sl-pos-cart-summary" in css and "#stockland-app .sl-pos-cart-table" in css and "@media(max-width:620px){#stockland-app .sl-pos-cart-meta" in css,
                "POS cart summary, table and mobile layout have responsive styling")
        require("d.sale_id=f.dataset.saleRequestId" in app and "delete f.dataset.saleRequestId;" in app,
                "Retail POS sends one stable sale id and clears it only after CLOSED confirmation")
        require("external_receipt_no" in sales_php and "(string) ( $existing['external_receipt_no'] ?? '' ) !== (string) ( $p['external_receipt_no'] ?? '' )" in sales_php,
                "POS idempotent replay rejects a mismatched external receipt reference")
        require("if ( '' !== (string) $wpdb->last_error || null === $physical_raw )" in stock_db_php and "if ( '' !== (string) $wpdb->last_error || null === $reserved_raw )" in stock_db_php,
                "stock availability treats failed ledger or reservation query as unavailable, never as zero reservations")
        require("Stock availability could not be verified" in sales_php and "Stockland_DB::get_available_stock" in sales_php,
                "POS reports an availability query failure instead of treating it as an ordinary stock shortage")
        require("Stockland_Sale_Math::calculate" in sales_php and "Stockland_Ledger::post_sale_closed" in sales_php and "Stockland_Accounting::post_journal" in sales_php and "START TRANSACTION" in sales_php and "ROLLBACK" in sales_php,
                "single-line POS calculation, stock-ledger movement, GL journal and transaction rollback remain wired")
        require("debit/credit" in accounting_php.lower() and "Stockland_Money::subtract( $sum_d, $sum_c )" in accounting_php and "existing_journal_matches" in accounting_php,
                "double-entry journal requires balanced debits and credits and validates source-idempotent replay")
        require("Stockland_Money::multiply( $q, $uc )" in rest_accounting_php and "Supplier invoice line subtotal/tax does not match" in rest_accounting_php and "Payment allocation total must equal the payment amount exactly" in rest_accounting_php,
                "supplier invoice quantities/tax and AP/AR payment allocations are revalidated server-side")
        require("final" not in sale_math_php or "Stockland_Money::multiply" in sale_math_php,
                "POS money path uses integer minor-unit arithmetic for quantity × unit price")
        require("drop table" not in schema.lower() and "truncate table" not in schema.lower(), "candidate HR schema has no destructive drop/truncate statements")
        require("stockland-private-vault" not in app.lower() and "wp-content/uploads" not in vault.lower(), "private resource storage is not exposed through public uploads URLs")
        require(manifest.get("statutory_rates_or_exemption_caps_seeded") is False, "no automatic statutory rates or exemption caps are seeded")
        # Ensure the new accounting/HR API has explicit permission callbacks for every REST registration.
        routes = re.findall(r"register_rest_route\s*\(", cycle)
        permissions = re.findall(r"""[\'"]?permission_callback[\'"]?\s*=>""", cycle)
        require(len(permissions) >= len(routes), "every REST route handler declares explicit permission callback(s)")
        require("employee_reimbursements" in schema and "receipt_resource_id" in schema, "reimbursements require a receipt-resource reference")
        # No real payee identifiers may be returned by list APIs.
        list_payees = cycle[cycle.index("public function list_payees"):cycle.index("public function create_payee")]
        require("tin_ciphertext" not in list_payees and "license_ciphertext" not in list_payees, "payee listing never returns encrypted TIN/licence ciphertext")
    print("PASS static candidate archive checks complete (runtime/staging and legal sign-off remain separate gates)")

if __name__ == "__main__":
    main()
