#!/usr/bin/env python3
"""Consolidated WordPress compatibility, report integrity, and management UI patch."""
from __future__ import annotations

import hashlib
import json
import os
import tempfile
import zipfile
from pathlib import Path

ROOT = "stockland-erp-app/"
PHP = ROOT + "includes/rest/class-rest-app.php"
JS = ROOT + "assets/app.js"
ENTRY = ROOT + "stockland-inventory.php"
ADMIN = ROOT + "includes/class-stockland-admin.php"
XLSX = ROOT + "includes/class-stockland-xlsx.php"
MANIFEST = ROOT + "RELEASE_MANIFEST.json"
README = ROOT + "README.md"


def once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise ValueError(f"{label}: expected one source anchor, found {count}")
    return source.replace(old, new, 1)


def patch_report_errors(source: str) -> str:
    queries = [
        ("\t\t$rt = Stockland_DB::read_row( $wpdb->prepare( $rt_sql, $rt_args ), ARRAY_A );", "retail sales totals"),
        ("\t\t$branch = Stockland_DB::read_results( $wpdb->prepare( $branch_sql, $branch_args ), ARRAY_A );", "branch sales breakdown"),
        ("\t\t$top = Stockland_DB::read_results( $wpdb->prepare( $top_sql, $top_args ), ARRAY_A );", "product sales breakdown"),
        ("\t\t$returns = Stockland_DB::read_row( $wpdb->prepare( $returns_sql, $returns_args ), ARRAY_A );", "returns totals"),
        ("\t\t$stock = Stockland_DB::read_results( $wpdb->prepare( $stock_sql, $stock_args ), ARRAY_A );", "inventory availability"),
        ("\t\t$transfer_rows = Stockland_DB::read_results( $wpdb->prepare( $transfers_sql, $targs ), ARRAY_A );", "transfer report"),
        ("\t\t$people = Stockland_DB::read_results( $wpdb->prepare( $people_sql, $people_args ), ARRAY_A );", "sales staff report"),
        ("\t\t$variances = Stockland_DB::read_results( $wpdb->prepare( $variance_sql, $variance_args ), ARRAY_A );", "transfer variance report"),
        ("\t\t$count_batches = Stockland_DB::read_results( $wpdb->prepare( $count_sql, $cargs ), ARRAY_A );", "physical count report"),
    ]
    for statement, label in queries:
        text = label[0].upper() + label[1:]
        replacement = (
            "\t\t$wpdb->last_error = '';\n"
            + statement
            + "\n\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
            + "\t\t\treturn new WP_Error( 'report_source_query_failed', 'The "
            + text
            + " source could not be read. No totals were returned; inspect ERP System Health and database integrity.', array( 'status' => 500 ) );\n"
            + "\t\t}"
        )
        source = once(source, statement, replacement, "fail-closed report query: " + label)

    wholesale_start = "\t\t$wt = null !== $scope ? array("
    source = once(source, wholesale_start, "\t\t$wpdb->last_error = '';\n" + wholesale_start, "reset wholesale read error")
    wholesale_end = "\t\t) : Stockland_DB::read_row( $wpdb->prepare( $wh_sql, $wh_args ), ARRAY_A );"
    wholesale_guard = (
        wholesale_end
        + "\n\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
        + "\t\t\treturn new WP_Error( 'report_source_query_failed', 'The wholesale sales source could not be read. No totals were returned; inspect ERP System Health and database integrity.', array( 'status' => 500 ) );\n"
        + "\t\t}"
    )
    source = once(source, wholesale_end, wholesale_guard, "fail-closed wholesale query")

    location_query = "\t\t\t$location_label = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT location_name FROM %i WHERE id=%d AND is_active=1', Stockland_DB::table( 'locations' ), (int) $location_request ) );"
    location_guard = (
        "\t\t\t$wpdb->last_error = '';\n"
        + location_query
        + "\n\t\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
        + "\t\t\t\treturn new WP_Error( 'report_location_label_failed', 'The selected location label could not be resolved. No report was returned.', array( 'status' => 500 ) );\n"
        + "\t\t\t}"
    )
    source = once(source, location_query, location_guard, "fail-closed location lookup")
    if source.count("report_source_query_failed") < 10:
        raise ValueError("Required report-query failure guards are incomplete.")
    return source


def patch_dashboard_error_guards(source: str) -> str:
    """Protect Command Center KPI values from being fabricated as zero on SQL failures."""
    mappings = [
        (
            "\t\t$rt = Stockland_DB::read_row( $wpdb->prepare( $rt_sql, array_merge( array( $sales ), $sales_args ) ), ARRAY_A );",
            "\t\t$rt",
            "dashboard_sales_source_failed",
            "Command Center retail-sales totals could not be read. No zero sales value was assumed.",
        ),
        (
            "\t\t$inv = (float) Stockland_DB::read_var( $wpdb->prepare( $inv_sql, $inv_args ) );",
            "\t\t$inv",
            "dashboard_inventory_source_failed",
            "Command Center inventory valuation could not be read. No zero inventory value was assumed.",
        ),
        (
            "\t\t\t$low = (int) Stockland_DB::read_var( $wpdb->prepare( $low_sql, $low_args ) );",
            "\t\t\t$low",
            "dashboard_low_stock_source_failed",
            "Command Center low-stock count could not be read. No zero low-stock value was assumed.",
        ),
        (
            "\t\t\t$today_sales = (float) Stockland_DB::read_var( $wpdb->prepare( $today_sql, array_merge( array( $sales ), $today_args ) ) );",
            "\t\t\t$today_sales",
            "dashboard_today_sales_source_failed",
            "Command Center today-sales total could not be read. No zero sales value was assumed.",
        ),
    ]
    for statement, var, code, message in mappings:
        if var == "\t\t$inv":
            replacement = (
                "\t\t$wpdb->last_error = '';\n"
                "\t\t$inv_value = Stockland_DB::read_var( $wpdb->prepare( $inv_sql, $inv_args ) );\n"
                "\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
                "\t\t\treturn new WP_Error( '" + code + "', '" + message + "', array( 'status' => 500 ) );\n"
                "\t\t}\n"
                "\t\t$inv = (float) $inv_value;"
            )
        elif var == "\t\t\t$low":
            replacement = (
                "\t\t\t$wpdb->last_error = '';\n"
                + statement
                + "\n\t\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
                + "\t\t\t\treturn new WP_Error( '" + code + "', '" + message + "', array( 'status' => 500 ) );\n"
                + "\t\t\t}"
            )
        elif var == "\t\t\t$today_sales":
            replacement = (
                "\t\t\t$wpdb->last_error = '';\n"
                "\t\t\t$today_sales_value = Stockland_DB::read_var( $wpdb->prepare( $today_sql, array_merge( array( $sales ), $today_args ) ) );\n"
                "\t\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
                "\t\t\t\treturn new WP_Error( '" + code + "', '" + message + "', array( 'status' => 500 ) );\n"
                "\t\t\t}\n"
                "\t\t\t$today_sales = (float) $today_sales_value;"
            )
        else:
            replacement = (
                "\t\t$wpdb->last_error = '';\n"
                + statement
                + "\n\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
                + "\t\t\treturn new WP_Error( '" + code + "', '" + message + "', array( 'status' => 500 ) );\n"
                + "\t\t}"
            )
        source = once(source, statement, replacement, code)

    # Wholesale sales are included only when scope permits them; still never substitute a zero on read failure.
    wt_start = "\t\t\t$wt = null !== $scope_ids ? array("
    if source.count(wt_start) != 1:
        raise ValueError("Could not locate the Command Center wholesale totals block.")
    source = source.replace(wt_start, "\t\t\t$wpdb->last_error = '';\n" + wt_start, 1)
    wt_end = "\t\t\t) : Stockland_DB::read_row( $wpdb->prepare( 'SELECT COALESCE(SUM(sales_value),0) revenue,COALESCE(SUM(net_sales_excl_vat),0) net,COALESCE(SUM(gross_profit),0) profit,COALESCE(SUM(vat_amount),0) vat,COALESCE(SUM(quantity),0) units FROM %i', $wh ), ARRAY_A );"
    source = once(
        source, wt_end,
        wt_end + "\n\t\t\tif ( '' !== (string) $wpdb->last_error ) {\n"
        + "\t\t\t\treturn new WP_Error( 'dashboard_wholesale_source_failed', 'Command Center wholesale-sales totals could not be read. No zero sales value was assumed.', array( 'status' => 500 ) );\n"
        + "\t\t\t}",
        "dashboard wholesale error guard",
    )
    return source


def patch_management_ui(source: str, fragment: str) -> str:
    source = once(source, "const iconFor={dashboard:'home',pos:'cash'", "const iconFor={dashboard:'home',intelligence:'chart',pos:'cash'", "navigation icon")
    source = once(
        source,
        "button('reports','Reports & Analytics',canReports)+",
        "button('intelligence','Management Intelligence',!!(c.finance_view&&['owner','management'].includes(u.role)))+\n button('reports','Reports & Analytics',canReports)+",
        "management navigation",
    )
    source = once(
        source,
        "const icons={dashboard:'home',dailyreport:'report'",
        "const icons={dashboard:'home',intelligence:'chart',dailyreport:'report'",
        "command palette icon",
    )
    source = once(
        source,
        "['reports','Reports & Analytics',canReports],",
        "['reports','Reports & Analytics',canReports],['intelligence','Management Intelligence',!!(c.finance_view&&['owner','management'].includes(state.boot?.user?.role))],",
        "command palette entry",
    )
    source = once(source, "case'reports':b=reports();break;", "case'reports':b=reports();break;case'intelligence':b=managementIntelligence();break;", "view render route")
    source = once(source, "async function loadFinance(force){", fragment + "\nasync function loadFinance(force){", "management intelligence implementation")
    source = once(
        source,
        "const host=document.getElementById('slDashboardSalesTrend');if(!host||state.view!=='dashboard'||!state.boot)return;",
        "const host=document.getElementById('slDashboardSalesTrend');if(!host||!['dashboard','intelligence'].includes(state.view)||!state.boot)return;",
        "insights load view guard",
    )
    source = once(
        source,
        "if(requestId!==state.dashboardInsightsRequestId||state.view!=='dashboard')return;",
        "if(requestId!==state.dashboardInsightsRequestId||!['dashboard','intelligence'].includes(state.view))return;",
        "insights response view guard",
    )
    source = once(
        source,
        "const st=document.getElementById('slDashboardInsightsStatus');if(st)st.textContent=payload?.range?'Live • '+String(payload.range.from||'')+' to '+String(payload.range.to||''):'Live insights';",
        "const st=document.getElementById('slDashboardInsightsStatus');if(st)st.textContent=payload?.range?'Live • '+String(payload.range.from||'')+' to '+String(payload.range.to||''):'Live insights';updateManagementIntelligenceKpis(state.dashboardInsights);",
        "management insight KPI refresh",
    )
    source = once(
        source,
        "if(document.getElementById('slDashboardSalesTrend'))loadDashboardInsights();if(document.getElementById('v16ReqForm')){}",
        "if(document.getElementById('slDashboardSalesTrend'))loadDashboardInsights();const miRefresh=document.getElementById('refreshManagementIntelligence');if(miRefresh)miRefresh.onclick=()=>loadDashboardInsights(true);if(document.getElementById('v16ReqForm')){}",
        "management refresh button",
    )
    return source


def patch_runtime(files: dict[str, bytes]) -> None:
    entry = files[ENTRY].decode("utf-8")
    if "Requires PHP:      8.3" not in entry:
        entry = once(entry, "Requires PHP:      8.4", "Requires PHP:      8.3", "plugin PHP requirement")
    files[ENTRY] = entry.encode("utf-8")

    php = files[PHP].decode("utf-8")
    if php.count("version_compare( PHP_VERSION, '8.4', '>=' )") != 2:
        raise ValueError("Expected both System Health PHP-version checks.")
    php = php.replace("version_compare( PHP_VERSION, '8.4', '>=' )", "version_compare( PHP_VERSION, '8.3', '>=' )")
    files[PHP] = php.encode("utf-8")

    admin = files[ADMIN].decode("utf-8")
    admin = admin.replace("plugin requires 8.4+", "plugin requires 8.3+")
    admin = admin.replace(">Server environment</h2>", ">WordPress hosting compatibility</h2>")
    if "plugin requires 8.4+" in admin or "plugin requires 8.3+" not in admin:
        raise ValueError("WordPress admin PHP requirement text is inconsistent.")
    if "Server environment</h2>" in admin or "WordPress hosting compatibility</h2>" not in admin:
        raise ValueError("WordPress hosting compatibility diagnostics heading was not normalized.")
    # Keep the live PHP, database, memory and execution-limit values visible; this is
    # diagnostic data from the existing WordPress host, not a separate server dependency.
    for diagnostic in ("PHP version", "MySQL/MariaDB version", "memory_limit", "max_execution_time"):
        if diagnostic not in admin:
            raise ValueError("Hosting diagnostic was removed: " + diagnostic)
    files[ADMIN] = admin.encode("utf-8")

    xlsx = files[XLSX].decode("utf-8")
    old = "\t\t\t// XMLReader::fromStream() is PHP 8.4+. InfinityFree currently provides\n\t\t\t// PHP 8.4, but keep a safe disk-backed fallback for hosts with an older\n"
    new = "\t\t\t// XMLReader::fromStream() is not available on every supported runtime;\n\t\t\t// retain the disk-backed fallback for shared hosts with an older PHP build.\n"
    xlsx = xlsx.replace(old, new)
    if "InfinityFree currently provides" in xlsx:
        raise ValueError("Removed unsupported hard-coded host-runtime claim was not found.")
    files[XLSX] = xlsx.encode("utf-8")


def patch_metadata(files: dict[str, bytes]) -> None:
    source_manifest = json.loads(files[MANIFEST].decode("utf-8"))

    # The embedded JSON is production metadata, not a historical CI notebook. Keep
    # documented product/operational contracts and the current release only; keep
    # exact-head CI receipts, workflow URLs, candidate narratives and old toolchain
    # labels in the repository audit/Actions artifacts, outside the installable ZIP.
    production_keys = (
        "release", "release_label", "classification", "schema_contract",
        "runtime_rules", "frontend", "frontend_css_architecture", "import_gateway",
        "security", "runtime_diagnostics", "inventory_report_controls",
        "procurement_controls", "expense_authority", "payroll", "stock_reconciliation",
    )
    manifest = {key: source_manifest[key] for key in production_keys if key in source_manifest}
    manifest["classification"] = (
        "Cumulative Stockland WordPress plugin update. Includes selected Reports & Analytics, "
        "report-source failure handling, hosting compatibility corrections and stock/reorder "
        "consistency controls. The live site and business records are not modified by package build."
    )
    manifest["release_changes"] = [
        "Reports & Analytics: selected-report filters, print/PDF and CSV use authorized locations and role checks.",
        "Report integrity: database read failures produce explicit errors, not fabricated zero totals.",
        "Inventory reporting: physical stock, active reservations, sellable availability and eligible inbound transfers remain distinct.",
        "Hosting compatibility: WordPress/PHP runtime checks align to PHP 8.3+ without a separate application service.",
        "Payroll data integrity: effective-dated compensation and current statutory posting workflows are retained; missing values are not invented.",
    ]
    manifest["release_label"] = "Stockland Ethiopia ERP — WordPress update"
    manifest["minimum_php"] = "8.3"
    manifest.setdefault("hosting_profile", {})["minimum_php"] = "8.3+"
    manifest.setdefault("hosting_profile", {})["separate_application_runtime_required"] = False
    manifest.setdefault("user_interface", {})["management_intelligence"] = {
        "enabled_for": ["owner", "management"],
        "data_source": "Existing server-authorized ERP insights",
        "automatic_transaction_posting": False,
    }
    manifest.setdefault("report_integrity", {}).update({
        "required_query_errors_fail_closed": True,
        "query_failure_is_not_reported_as_zero": True,
    })
    manifest.setdefault("payroll_integrity", {}).update({
        "runtime": "Existing WordPress/PHP/MySQL service",
        "tax_rule": "ET-PAYE-1395-2025-v3-PENSION-BASE",
        "employee_pension_rate": "7%",
        "employer_pension_rate": "11%",
        "effective_dated_compensation": True,
        "missing_compensation_autofilled": False,
        "sample_personal_records_imported": False,
        "allowance_exemption_requires_owner_evidence": True,
    })
    # Remove intermediate builder/provider declarations from the installed-plugin metadata.
    # The actual plugin retains native Stockland identity, permissions, notifications and work areas.
    manifest.pop("external_connections", None)
    manifest.pop("reports_explorer_16_1_93", None)
    quality = manifest.get("quality")
    if isinstance(quality, dict):
        for transient_key in ("local_validation_status", "target_host_acceptance", "production_certification"):
            quality.pop(transient_key, None)
    files[MANIFEST] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")


def patch_readme(files: dict[str, bytes]) -> None:
    readme = """# Stockland Ethiopia ERP — WordPress plugin update 16.1.93

## Update the installed WordPress plugin

1. Back up WordPress files and the MySQL/MariaDB database from the hosting control panel.
2. In WordPress, open Plugins → Add New Plugin → Upload Plugin and select this ZIP. Use the update/overwrite action for the existing Stockland ERP plugin when offered.
3. Keep the plugin directory name stockland-erp-app. Do not uninstall the plugin and do not delete its business tables.
4. Clear page/browser/PWA caches and sign in to the native Stockland ERP front end.
5. Review System Health, authorized locations and HR/payroll readiness before posting transactions.

## ERP work areas

Command Center; Product Master; Inventory and Daily Inventory; Retail POS and Wholesale; Central Warehouse and Receiving; Purchase Orders; Requisitions and Reservations; Transfers; Returns, Damage and Adjustments; Finance and Procurement; AP/AR; PLEDGER and General Ledger; HR/Payroll; Reports & Analytics; Management Intelligence; Users/Roles; Audit Trail; Notifications and System Health.

## Stock, reorder and finance rules

Posted stock-ledger movements are the source of truth for physical movement history. Physical on-hand, active reservations, sellable availability and dispatched-but-unreceived transfers remain separate values. Legacy SKU-only movements/reservations are mapped to a canonical Product Master variant only when that mapping exists. Over-reservation remains visible as an integrity exception. Location-specific reorder thresholds must be configured; an unset threshold does not create a false reorder alert. Suggested replenishment is advisory and does not create a purchase order or change stock. In-transit quantities inform the reorder position but are not physical stock until destination receipt is posted.

The ERP uses its shared decimal-safe money service and server-side posting workflows. Historical sale prices, VAT values, staged COGS, posted payroll, journal entries and audit records must not be silently rewritten by later master-data edits. Required report queries fail closed when the database reports an error; an unavailable source must not be shown as a genuine zero.

## HR and statutory payroll

The existing WordPress/PHP/MySQL-or-MariaDB payroll service remains authoritative. It uses decimal-safe ETB calculations, effective-dated compensation, the versioned PAYE schedule, employee pension at 7%, employer pension at 11%, allowance-classification evidence, period locking, Owner approval and accounting posting. Missing compensation remains a visible readiness issue until authorized staff enter and approve the correct value; the system does not invent salaries or overwrite employee records.

The currently implemented employee/compensation/monthly-payroll flow is not proof of a complete HR lifecycle. Workforce planning, recruitment/onboarding, attendance/time, leave, employee development and termination/final-settlement workflows must not be represented as available until implemented and accepted against the approved Stockland SOP. Overtime and separation calculations must follow the applicable effective-date-specific legal requirements and an approved rule matrix; unverified examples must not be imported as employee data or used to override the statutory service.

## Runtime

WordPress 6.8+; PHP 8.3+; MySQL 8.0+ or MariaDB 10.11+; HTTPS enabled. The plugin executes inside the existing WordPress/PHP environment on the configured host; it does not require a separate application service or worker for normal ERP operation. Use Stockland ERP's native sign-in, role-based access, profile preferences, internal notifications and existing work areas.
"""
    files[README] = readme.encode("utf-8")


def patch_archive(archive: Path) -> dict:
    original = archive.read_bytes()
    original_sha = hashlib.sha256(original).hexdigest()
    with zipfile.ZipFile(archive, "r") as zin:
        if zin.testzip() is not None:
            raise ValueError("Input archive CRC validation failed.")
        infos = [info for info in zin.infolist() if not info.is_dir()]
        names = [info.filename for info in infos]
        if len(names) != 75 or len(set(names)) != 75 or not all(name.startswith(ROOT) and ".." not in Path(name).parts for name in names):
            raise ValueError("Unexpected plugin archive inventory.")
        files = {name: zin.read(name) for name in names}
        css_hashes = {name: hashlib.sha256(data).hexdigest() for name, data in files.items() if name.endswith(".css")}
        activator = files[ROOT + "includes/class-stockland-activator.php"]

    fragment_path = Path(__file__).with_name("management_intelligence_fragment.js")
    fragment = fragment_path.read_text(encoding="utf-8")
    php = patch_report_errors(files[PHP].decode("utf-8"))
    php = patch_dashboard_error_guards(php)
    files[PHP] = php.encode("utf-8")
    files[JS] = patch_management_ui(files[JS].decode("utf-8"), fragment).encode("utf-8")
    patch_runtime(files)
    patch_metadata(files)
    patch_readme(files)

    fd, temp_name = tempfile.mkstemp(prefix="stockland-update-", suffix=".zip", dir=str(archive.parent))
    os.close(fd)
    temp = Path(temp_name)
    try:
        with zipfile.ZipFile(temp, "w") as zout:
            for info in infos:
                zi = zipfile.ZipInfo(info.filename, date_time=info.date_time)
                zi.compress_type = zipfile.ZIP_DEFLATED
                zi.external_attr = info.external_attr
                zi.create_system = info.create_system
                zi.flag_bits = info.flag_bits
                zout.writestr(zi, files[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        with zipfile.ZipFile(temp, "r") as final:
            if final.testzip() is not None or [i.filename for i in final.infolist() if not i.is_dir()] != names:
                raise ValueError("Updated archive integrity or file order changed.")
            for name, digest in css_hashes.items():
                if hashlib.sha256(final.read(name)).hexdigest() != digest:
                    raise ValueError("CSS file changed unexpectedly: " + name)
            if final.read(ROOT + "includes/class-stockland-activator.php") != activator:
                raise ValueError("Database activator changed unexpectedly.")
            php = final.read(PHP).decode("utf-8")
            js = final.read(JS).decode("utf-8")
            entry = final.read(ENTRY).decode("utf-8")
            admin = final.read(ADMIN).decode("utf-8")
            manifest = json.loads(final.read(MANIFEST).decode("utf-8"))
            if php.count("report_source_query_failed") < 10 or "report_location_label_failed" not in php:
                raise ValueError("Required report query safeguards are missing.")
            if "function managementIntelligence()" not in js or "case'intelligence':b=managementIntelligence();break;" not in js:
                raise ValueError("Management Intelligence view is missing.")
            if "version_compare( PHP_VERSION, '8.4', '>=' )" in php or "plugin requires 8.4+" in admin:
                raise ValueError("PHP 8.3 runtime checks are not aligned.")
            if "release_candidate_changes" in manifest or manifest.get("minimum_php") != "8.3":
                raise ValueError("WordPress update metadata was not normalized.")
            if manifest.get("payroll_integrity", {}).get("sample_personal_records_imported") is not False:
                raise ValueError("Payroll demo-record safety metadata is missing.")
        os.replace(temp, archive)
        return {
            "previous_archive_sha256": original_sha,
            "archive_sha256": hashlib.sha256(archive.read_bytes()).hexdigest(),
            "archive_bytes": archive.stat().st_size,
            "plugin_files": len(names),
            "report_query_guards": 10,
            "management_intelligence": "Owner/Management, server-authorized insights",
            "php_minimum": "8.3+",
            "css_files_unchanged": len(css_hashes),
            "schema_activator_unchanged": True,
            "schema_version_preserved": "16.1.1",
            "payroll_demo_records_imported": False,
        }
    finally:
        if temp.exists():
            temp.unlink()


def main() -> None:
    import argparse
    parser = argparse.ArgumentParser(description="Apply consolidated WordPress runtime, report and management protections.")
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    print(json.dumps(patch_archive(args.archive), indent=2))


if __name__ == "__main__":
    main()
