#!/usr/bin/env python3
"""Build a clean-slate Stockland HRX candidate from a locked 16.1.95 installer.

Deterministic package builder only: it never contacts WordPress or mutates business data.
"""
from __future__ import annotations
import argparse, hashlib, json, re, zipfile
from pathlib import Path, PurePosixPath

BASELINE_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
BASELINE_SIZE = 1_102_405
PLUGIN_ROOT = "stockland-erp-app/"
RELEASE = "16.3.0"
LEGACY_TABLES = {"employees", "employee_compensation", "payroll_periods", "payroll_lines"}
DROP_PATHS = {PLUGIN_ROOT + "includes/class-stockland-payroll-statutory.php", PLUGIN_ROOT + "includes/rest/class-rest-hr.php"}

def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()

def once(text: str, old: str, new: str, label: str) -> str:
    n = text.count(old)
    if n != 1:
        raise SystemExit(f"{label}: expected one exact anchor, found {n}")
    return text.replace(old, new, 1)

def between(text: str, start: str, end: str, replacement: str, label: str) -> str:
    a = text.find(start)
    if a < 0:
        raise SystemExit(f"{label}: opening anchor missing")
    b = text.find(end, a + len(start))
    if b < 0:
        raise SystemExit(f"{label}: closing anchor missing")
    return text[:a] + replacement + text[b:]

def text_file(files: dict[str, bytes], path: str) -> str:
    key = PLUGIN_ROOT + path
    if key not in files:
        raise SystemExit("Required source member missing: " + key)
    return files[key].decode("utf-8")

def put(files: dict[str, bytes], path: str, value: str) -> None:
    files[PLUGIN_ROOT + path] = value.encode("utf-8")

def target_hrx_count(source: str) -> int:
    m = re.search(r"public\s+static\s+function\s+table_keys\s*\(\s*\)\s*\{.*?return\s+array\s*\((.*?)\);", source, re.S)
    if not m:
        raise SystemExit("Could not parse HRX table_keys() contract")
    keys = re.findall(r"'([a-z][a-z0-9_]*)'", m.group(1))
    if not keys or len(keys) != len(set(keys)) or any(not k.startswith("hrx_") for k in keys):
        raise SystemExit("HRX table_keys() must be a non-empty unique list of hrx_* names")
    return len(keys)

def hr_compatibility_shim() -> str:
    return """<?php
/**
 * Compatibility facade for the retired four-table HR subsystem.
 * The clean-slate HRX runtime owns HR/payroll records. This shim intentionally exposes
 * no legacy table keys, DDL, payroll formulas, migrations, reads or writes.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Stockland_HR {
    const DB_VERSION = STOCKLAND_HR_SCHEMA_VERSION;
    public static function table_keys() { return array(); }
    public static function required_columns() { return array(); }
    public static function upgrade_gaps() { return array(); }
    public static function missing_tables() { return array(); }
    public static function install( $limit = 1 ) {
        return array('installed'=>array(),'missing'=>array(),'complete'=>true,'legacy_hr_payroll_retired'=>true,'note'=>'Use the separate HRX schema setup.');
    }
}
"""

def patch_entrypoint(files: dict[str, bytes]) -> None:
    p = text_file(files, "stockland-inventory.php")
    p = once(p, "* Version:           16.1.95", "* Version:           16.3.0", "plugin header")
    p = once(p, "define( 'STOCKLAND_VERSION', '16.1.95' );", "define( 'STOCKLAND_VERSION', '16.3.0' );", "runtime release constant")
    p = once(p, "define( 'STOCKLAND_HR_SCHEMA_VERSION', '16.1.3' );", "define( 'STOCKLAND_HR_SCHEMA_VERSION', '16.3.0' );", "retired HR schema marker")
    p = p.replace("require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-payroll-statutory.php';\n", "")
    p = p.replace("require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr.php';\n", "")
    p = once(
        p,
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-authority.php';",
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-authority.php';\n"
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-replacement.php';\n"
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-replacement-calculator.php';\n"
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr-replacement.php';",
        "HRX runtime load order",
    )
    p = once(p, "\t\tnew Stockland_REST_Accounting();", "\t\tnew Stockland_REST_Accounting();\n\t\tnew Stockland_REST_HR_Replacement();", "HRX controller registration")
    if "new Stockland_REST_HR();" in p:
        raise SystemExit("Legacy HR REST controller is still instantiated")
    put(files, "stockland-inventory.php", p)

def patch_admin(files: dict[str, bytes]) -> None:
    p = text_file(files, "includes/class-stockland-admin.php")
    p = once(
        p,
        "$full_expected = array_values( array_unique( array_merge( $expected_core, $expected_accounting, array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' ) ) ) );",
        "$full_expected = array_values( array_unique( array_merge( $expected_core, $expected_accounting ) ) );",
        "base ERP schema inventory",
    )
    old = """\t\t$expected_hr = array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' );
\t\t$hr_missing  = array_values( array_diff( $expected_hr, $found_short ) );
\t\techo '<tr><td><strong>HR / Payroll schema</strong></td><td>' . ( empty( $hr_missing ) ? '<span style="color:#00a32a">&#10003; all ' . count( $expected_hr ) . ' present</span>' : '<span style="color:#d63638">&#9888; ' . ( count( $expected_hr ) - count( $hr_missing ) ) . ' of ' . count( $expected_hr ) . ' present — missing: ' . esc_html( implode( ', ', $hr_missing ) ) . '</span>' ) . '</td></tr>';"""
    new = """\t\t$hrx_status = class_exists( 'Stockland_HR_Replacement' ) ? Stockland_HR_Replacement::schema_status() : array( 'ready' => false, 'present' => 0, 'total' => 0, 'missing_tables' => array( 'hrx_runtime_missing' ), 'schema_drift' => array() );
\t\techo '<tr><td><strong>HR &amp; Payroll replacement (HRX)</strong></td><td>' . ( ! empty( $hrx_status['ready'] ) ? '<span style="color:#00a32a">&#10003; normalized HRX schema verified: ' . (int) $hrx_status['present'] . ' / ' . (int) $hrx_status['total'] . '</span>' : '<span style="color:#dba617">Setup required: ' . (int) ( $hrx_status['present'] ?? 0 ) . ' / ' . (int) ( $hrx_status['total'] ?? 0 ) . '; use the HRX schema gate in the ERP workspace. Legacy HR tables are not the active system of record.</span>' ) . '</td></tr>';"""
    p = once(p, old, new, "admin HRX status row")
    put(files, "includes/class-stockland-admin.php", p)

def patch_setup(files: dict[str, bytes]) -> None:
    p = text_file(files, "includes/rest/class-rest-setup.php")
    old = """\t\t\t'hr'                 => array(
\t\t\t\t'present'      => $hr_total - count( $hr ),
\t\t\t\t'total'        => $hr_total,
\t\t\t\t'missing'      => $hr,
\t\t\t\t'upgrade_gaps' => $hr_gaps,
\t\t\t\t'ready'        => empty( $hr ) && empty( $hr_gaps ),
\t\t\t),"""
    new = """\t\t\t'hr'                 => array(
\t\t\t\t'present'      => 0,
\t\t\t\t'total'        => 0,
\t\t\t\t'missing'      => array(),
\t\t\t\t'upgrade_gaps' => array(),
\t\t\t\t'ready'        => true,
\t\t\t\t'legacy_runtime' => false,
\t\t\t),
\t\t\t'hrx_replacement' => class_exists( 'Stockland_HR_Replacement' ) ? Stockland_HR_Replacement::schema_status() : array( 'ready' => false, 'status' => 'RUNTIME_MISSING' ),"""
    p = once(p, old, new, "setup separates HRX from legacy setup")
    put(files, "includes/rest/class-rest-setup.php", p)

def patch_health(files: dict[str, bytes]) -> None:
    p = text_file(files, "includes/rest/class-rest-app.php")
    old = "$hr_expected         = array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' );"
    new = "$hr_expected         = class_exists( 'Stockland_HR_Replacement' ) ? Stockland_HR_Replacement::table_keys() : array();"
    if p.count(old) != 2:
        raise SystemExit(f"health HR schema inventory anchor count is {p.count(old)}, expected 2")
    p = p.replace(old, new)
    p = p.replace(
        "$checks['schema_contract_exact_49']       = $checks['db'] && count( $found_keys ) === 49 && empty( $missing_full ) && empty( $unexpected_full );",
        "$checks['schema_contract_hrx_exact']      = $checks['db'] && ! empty( $hr_expected ) && empty( $missing_full ) && empty( $unexpected_full );",
    )
    p = p.replace(
        "$checks['schema_contract_exact_49']       = count( $found_keys ) === 49 && empty( $missing_full ) && empty( $unexpected_full );",
        "$checks['schema_contract_hrx_exact']      = ! empty( $hr_expected ) && empty( $missing_full ) && empty( $unexpected_full );",
    )
    if "schema_contract_exact_49" in p or p.count("schema_contract_hrx_exact") != 2:
        raise SystemExit("legacy exact-49 check was not fully replaced")

    start = p.find("\t\t$accounting_schema_gaps = array( 'accounting_upgrade_check_failed' );")
    end = p.find("\n\t\t$diagnostics['accounting_schema_upgrade_gaps'] = $accounting_schema_gaps;", start)
    if start < 0 or end < 0:
        raise SystemExit("health HR schema upgrade-gate block not found")
    new_block = """\t\t$accounting_schema_gaps = array( 'accounting_upgrade_check_failed' );
\t\t$hr_schema_gaps         = array( 'hrx_runtime_missing' );
\t\t$hrx_schema_status      = array( 'ready' => false, 'missing_tables' => array(), 'schema_drift' => array() );
\t\ttry {
\t\t\tif ( class_exists( 'Stockland_Accounting' ) ) {
\t\t\t\t$accounting_schema_gaps = Stockland_Accounting::upgrade_gaps();
\t\t\t}
\t\t} catch ( Throwable $e ) {
\t\t\t$diagnostics['accounting_schema_upgrade_error'] = 'Accounting schema-upgrade inspection failed; inspect authorized server diagnostics.';
\t\t}
\t\ttry {
\t\t\tif ( class_exists( 'Stockland_HR_Replacement' ) ) {
\t\t\t\t$hrx_schema_status = Stockland_HR_Replacement::schema_status();
\t\t\t\t$hr_schema_gaps = array_merge( (array) ( $hrx_schema_status['missing_tables'] ?? array() ), (array) ( $hrx_schema_status['schema_drift'] ?? array() ) );
\t\t\t\tif ( empty( $hrx_schema_status['ready'] ) ) { $hr_schema_gaps[] = 'hrx_schema_not_ready_or_version_unverified'; }
\t\t\t}
\t\t} catch ( Throwable $e ) {
\t\t\t$diagnostics['hrx_schema_error'] = 'HRX schema inspection failed; inspect authorized server diagnostics.';
\t\t\t$hr_schema_gaps[] = 'hrx_schema_inspection_failed';
\t\t}
\t\t$checks['accounting_schema_upgrade_ready'] = empty( $accounting_schema_gaps );
\t\t$checks['hr_schema_upgrade_ready'] = empty( $hr_schema_gaps );
"""
    p = p[:start] + new_block + p[end:]

    a = p.find("\t\t// The statutory calculation gate now proves both the versioned calculation service")
    b = p.find("\t\t// Source/runtime boundary checks are deliberately narrow:", a)
    if a < 0 or b < 0:
        raise SystemExit("legacy payroll data-scan block not found")
    hrx_diag = """\t\t// Static health verifies the HRX runtime/model, not each employee's payroll eligibility.
\t\t// The period-specific preflight is authoritative for compensation, attendance, leave and legal rules.
\t\t$hrx_health = class_exists( 'Stockland_HR_Replacement' ) ? Stockland_HR_Replacement::schema_status() : array( 'ready' => false, 'present' => 0, 'total' => 0, 'missing_tables' => array( 'hrx_runtime_missing' ) );
\t\t$checks['payroll_statutory_model'] = class_exists( 'Stockland_HR_Replacement_Calculator' )
\t\t\t&& method_exists( 'Stockland_HR_Replacement_Calculator', 'payroll_line' )
\t\t\t&& method_exists( 'Stockland_HR_Replacement_Calculator', 'progressive_tax' )
\t\t\t&& ! empty( $hrx_health['ready'] );
\t\t$diagnostics['payroll_statutory_model'] = array(
\t\t\t'implemented' => class_exists( 'Stockland_HR_Replacement_Calculator' ),
\t\t\t'hrx_schema_ready' => ! empty( $hrx_health['ready'] ),
\t\t\t'hrx_tables_present' => (int) ( $hrx_health['present'] ?? 0 ),
\t\t\t'hrx_tables_required' => (int) ( $hrx_health['total'] ?? 0 ),
\t\t\t'statutory_rule_activation' => 'QUALIFIED_ETHIOPIAN_LEGAL_REVIEW_REQUIRED',
\t\t\t'note' => 'No Ethiopian rates are embedded by the HRX calculator. A selected payroll run must pass its own approved-rule and employee-input preflight.',
\t\t);
\t\t$diagnostics['payroll_compensation_classification'] = array(
\t\t\t'unresolved_active_employees' => null,
\t\t\t'excluded_retainer_service_contracts' => null,
\t\t\t'scan_query_failed' => false,
\t\t\t'rule' => 'Unknown employee-level status is reported as unknown here; use HRX payroll preflight for the selected pay period. This gate never reads, alters or auto-fills legacy employee compensation.',
\t\t\t'current_period_preflight' => 'NOT_RUN_BY_STATIC_SYSTEM_HEALTH',
\t\t);
\t\t$checks['payroll_compensation_classification'] = $checks['payroll_statutory_model'];
\t\t$diagnostics['hrx_replacement'] = $hrx_health;

"""
    p = p[:a] + hrx_diag + p[b:]
    p = p.replace(
        "$checks['live_ready'] = ( $checks['db'] && $checks['php_runtime'] && $checks['xlsx_upload_capacity'] && $checks['balance_model_contract'] && $checks['core_schema'] && $checks['full_schema'] && $checks['schema_contract_exact_49'] &&",
        "$checks['live_ready'] = ( $checks['db'] && $checks['php_runtime'] && $checks['xlsx_upload_capacity'] && $checks['balance_model_contract'] && $checks['core_schema'] && $checks['full_schema'] && $checks['schema_contract_hrx_exact'] &&",
    )
    # Rename every dependent read-only health gate; no legacy exact-49 contract may remain.
    p = p.replace("schema_contract_exact_49", "schema_contract_hrx_exact")
    p = p.replace("exact 49-table contract is not healthy", "exact HRX schema contract is not healthy")
    p = p.replace("All 49 ERP schema tables", "All core/accounting ERP tables and the complete HRX schema")
    p = p.replace("        $diagnostics['hrx_replacement'] = $hrx_health;", "        $diagnostics['hrx_replacement'] = $hrx_health;\n        $diagnostics['hr_schema_upgrade_gaps'] = $hr_schema_gaps;")
    if "schema_contract_exact_49" in p:
        raise SystemExit("old 49-table health check is still referenced")
    put(files, "includes/rest/class-rest-app.php", p)

def compile_css(files: dict[str, bytes]) -> None:
    css_path = PLUGIN_ROOT + "assets/css/06-operational-guards.css"
    overlay = (Path(__file__).resolve().parent / "hrx_clean_slate_16_3_0" / "hrx-ui.css").read_text(encoding="utf-8")
    files[css_path] = (files[css_path].decode("utf-8").rstrip() + "\n\n" + overlay.strip() + "\n").encode("utf-8")
    manifest_path = PLUGIN_ROOT + "assets/css/modules.json"
    css_manifest = json.loads(files[manifest_path].decode("utf-8"))
    css_manifest["release"] = RELEASE
    css_manifest["description"] = "16.3.0 HRX workspace; six ordered CSS source modules are the single source of truth."
    files[manifest_path] = (json.dumps(css_manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    modules = css_manifest.get("modules", [])
    if len(modules) != 6 or len(set(modules)) != 6:
        raise SystemExit("CSS manifest must retain six unique ordered modules")
    chunks = [files[PLUGIN_ROOT + "assets/css/" + name].decode("utf-8").strip() for name in modules]
    built = "\n\n".join(chunks) + "\n"
    files[PLUGIN_ROOT + "assets/app.css"] = built.encode("utf-8")
    normalized = re.sub(r"/\*[\s\S]*?\*/", " ", built)
    normalized = re.sub(r"\s+", " ", normalized).strip()
    normalized = re.sub(r"\s*([{}:;,])\s*", r"\1", normalized)
    normalized = normalized.replace(";}", "}")
    files[PLUGIN_ROOT + "assets/app.min.css"] = normalized.encode("utf-8")

def integrate_ui(files: dict[str, bytes]) -> None:
    js_path = PLUGIN_ROOT + "assets/app.js"
    js = files[js_path].decode("utf-8")
    overlay = (Path(__file__).resolve().parent / "hrx_clean_slate_16_3_0" / "hrx-ui.js").read_text(encoding="utf-8")
    load_anchor = "async function loadHR(){"
    split = overlay.find(load_anchor)
    if split < 0:
        raise SystemExit("HRX UI must include loadHR()")
    js = between(js, "function hr(){", "async function loadHR(){", overlay[:split], "replace legacy HR renderer")
    js = between(js, "async function loadHR(){", "async function loadBranchOperations(", overlay[split:] + "\n", "replace legacy HR loader")
    files[js_path] = js.encode("utf-8")

def patch_frontend_manifest(files: dict[str, bytes], hrx_count: int) -> None:
    front = text_file(files, "includes/class-stockland-frontend.php")
    front = once(front, "const ROUTE_VERSION      = '16.1.95';", "const ROUTE_VERSION      = '16.3.0';", "frontend route/cache marker")
    put(files, "includes/class-stockland-frontend.php", front)
    path = PLUGIN_ROOT + "RELEASE_MANIFEST.json"
    manifest = json.loads(files[path].decode("utf-8"))
    manifest["release"] = RELEASE
    manifest["release_label"] = "Stockland Ethiopia ERP — clean-slate HRX lifecycle candidate 16.3.0"
    manifest["schema_contract"] = {
        "total": 45 + hrx_count,
        "core_operations": 29,
        "accounting_procurement": 16,
        "legacy_hr_payroll_active": 0,
        "hr_payroll_replacement": hrx_count,
        "candidate_schema_inventory_not_final_design_count": True,
    }
    manifest["schema_changed"] = True
    manifest["live_data_changed"] = False
    manifest["live_business_data_changed"] = False
    manifest.setdefault("release_changes", []).append(
        "16.3.0 replaces the old four-table HR runtime with normalized HRX workforce, recruitment, contract, time/leave, compensation, legal-rule, payroll, payout, remittance, private-document, payee and exit/settlement domains."
    )
    manifest["hrx_clean_slate"] = {
        "schema_version": "1.0.0",
        "hrx_table_count_in_this_candidate": hrx_count,
        "legacy_tables": sorted(LEGACY_TABLES),
        "legacy_tables_active_as_system_of_record": False,
        "legacy_reset": "explicit allow-listed action; disabled by default; only local/development/staging with STOCKLAND_HRX_ALLOW_LEGACY_RESET=true, verified backup SHA-256, dependency-review SHA-256 and operator confirmation",
        "schema_setup": "authorized user triggers bounded CREATE-only batches; plugin activation performs no HRX DDL",
        "statutory_rates": "none hard-coded by the HRX calculator",
        "source_authority": "Notion HR Design Reset v0.5 and HR/payroll SOP/resource pack; current legal rules require qualified Ethiopian reviewer approval",
        "status": "CANDIDATE — live E2E, legal review and Owner/HR/Finance acceptance pending",
    }
    manifest["production_acceptance"] = {
        "status": "PENDING — not production certified",
        "pending": [
            "exact ZIP/source identity review",
            "isolated WordPress/MariaDB lifecycle E2E",
            "role/location/REST/private-document authorization tests",
            "official Ethiopian labour/tax/pension legal review",
            "payroll journal, payout, remittance and final-settlement reconciliation",
            "backup/restore/rollback evidence",
            "Owner/HR/Finance acceptance and live-source parity",
        ],
    }
    files[path] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    readme_path = PLUGIN_ROOT + "README.md"
    readme = files[readme_path].decode("utf-8")
    section = """

## HR & Payroll clean-slate candidate 16.3.0

This candidate replaces the legacy HR runtime with an independent normalized HRX domain. It does not migrate or write data to the retired employees, employee_compensation, payroll_periods, or payroll_lines tables. HRX tables are created only after an authorized user explicitly starts bounded schema batches from the HR workspace; plugin activation performs no HRX DDL. The optional legacy reset is disabled by default and blocked for production environment types.

Payroll calculations use deterministic decimal arithmetic and require approved effective-dated rules with official-source/test-vector evidence. This package contains no hard-coded Ethiopian PAYE or pension rates. Static health is not a substitute for selected-run preflight and independent Owner approval.

**Release status: CANDIDATE / NO-GO for production until package, live-host, legal, backup/restore, and Owner/HR/Finance acceptance gates pass.** The build never mutates ERP or employee data.
"""
    if "HR & Payroll clean-slate candidate 16.3.0" not in readme:
        files[readme_path] = (readme.rstrip() + section).encode("utf-8")

def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", type=Path, required=True)
    ap.add_argument("--output", type=Path, required=True)
    args = ap.parse_args()
    root = Path(__file__).resolve().parents[1]
    if not args.input.is_file():
        raise SystemExit("Baseline installer not found: " + str(args.input))
    raw = args.input.read_bytes()
    if len(raw) != BASELINE_SIZE or sha(raw) != BASELINE_SHA256:
        raise SystemExit("STOP: baseline installer size/SHA-256 mismatch")
    source_root = root / "ci" / "hr_replacement_v2"
    schema_source = (source_root / "class-stockland-hr-replacement.php").read_text(encoding="utf-8")
    calculator_source = (source_root / "class-stockland-hr-replacement-calculator.php").read_text(encoding="utf-8")
    rest_source = (source_root / "class-rest-hr-replacement.php").read_text(encoding="utf-8")
    if re.search(r"\b(?:ALTER|DROP|TRUNCATE)\s+TABLE\b", schema_source, re.I):
        raise SystemExit("HRX schema installer must be CREATE-only; destructive DDL is prohibited in schema source")
    hrx_count = target_hrx_count(schema_source)
    with zipfile.ZipFile(args.input, "r") as zin:
        if zin.testzip() is not None:
            raise SystemExit("Baseline ZIP CRC failed")
        infos = [i for i in zin.infolist() if not i.is_dir()]
        names = [i.filename for i in infos]
        if len(names) != 75 or len(set(names)) != 75:
            raise SystemExit("Expected 75 unique members in the locked 16.1.95 installer")
        if any(PurePosixPath(n).parts[0] != "stockland-erp-app" or ".." in PurePosixPath(n).parts for n in names):
            raise SystemExit("Unsafe or unexpected baseline ZIP path")
        files = {name: zin.read(name) for name in names}
    for key in DROP_PATHS:
        files.pop(key, None)
    patch_entrypoint(files)
    patch_admin(files)
    patch_setup(files)
    patch_health(files)
    files[PLUGIN_ROOT + "includes/class-stockland-hr.php"] = hr_compatibility_shim().encode()
    files[PLUGIN_ROOT + "includes/class-stockland-hr-replacement.php"] = schema_source.encode()
    files[PLUGIN_ROOT + "includes/class-stockland-hr-replacement-calculator.php"] = calculator_source.encode()
    files[PLUGIN_ROOT + "includes/rest/class-rest-hr-replacement.php"] = rest_source.encode()
    integrate_ui(files)
    compile_css(files)
    patch_frontend_manifest(files, hrx_count)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(args.output, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
        for name, data in files.items():
            zout.writestr(name, data)
    final_raw = args.output.read_bytes()
    digest = sha(final_raw)
    Path(str(args.output) + ".sha256").write_text(digest + "  " + args.output.name + "\n", encoding="utf-8")
    receipt = {
        "release": RELEASE,
        "status": "CANDIDATE_NOT_PRODUCTION_CERTIFIED",
        "baseline_sha256": BASELINE_SHA256,
        "installer_sha256": digest,
        "installer_size_bytes": len(final_raw),
        "package_file_count": len(files),
        "hrx_table_count": hrx_count,
        "legacy_hr_payroll_runtime_disabled": True,
        "legacy_hr_tables_active_as_system_of_record": False,
        "schema_changes_in_package": True,
        "business_data_changed_by_builder": False,
        "production_ready": False,
        "pending": [
            "isolated WordPress/MariaDB full lifecycle E2E",
            "current Ethiopian legal/tax/pension review",
            "role/location authorization and private-document download acceptance",
            "backup/restore/rollback evidence",
            "Owner/HR/Finance sign-off",
            "live source parity and cutover plan",
        ],
    }
    Path(str(args.output) + ".receipt.json").write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps(receipt, sort_keys=True))

if __name__ == "__main__":
    main()
