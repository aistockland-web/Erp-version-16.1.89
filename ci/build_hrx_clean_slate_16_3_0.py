#!/usr/bin/env python3
"""Build the integrated Stockland HRX release package from the locked 16.1.95 installer.

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
    p = once(p, "\t\tnew Stockland_REST_HR();", "", "retire legacy HR REST controller instance")
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
    p = once(p, "'schema_contract_exact_49'        => (int) ( $snapshot['total']['present'] ?? 0 ) === (int) ( $snapshot['total']['total'] ?? 49 ),", "'schema_contract_core_finance_exact' => (int) ( $snapshot['total']['present'] ?? 0 ) === (int) ( $snapshot['total']['total'] ?? 45 ),", "setup core/accounting schema label")
    p = once(p, "'hr_schema_upgrade_ready'         => empty( $snapshot['hr']['upgrade_gaps'] ),", "'hr_schema_upgrade_ready'         => ! empty( $snapshot['hrx_replacement']['ready'] ) && ! empty( $snapshot['hrx_replacement']['design_approved'] ),", "setup HRX readiness source")
    p = once(p, "'operational_routes_available'    => false,", "'operational_routes_available'    => false,\n\t\t\t'hrx_schema_design_approved'     => ! empty( $snapshot['hrx_replacement']['design_approved'] ),", "setup HRX design approval status")
    p = once(p, "'full_schema_total'     => $snapshot['total']['total'] ?? 49,", "'full_schema_total'     => $snapshot['total']['total'] ?? 45,\n\t\t\t'base_schema_scope'    => 'Core and accounting/procurement tables only; HRX is reported separately.',\n\t\t\t'hrx_schema_present'   => $snapshot['hrx_replacement']['present'] ?? 0,\n\t\t\t'hrx_schema_total'     => $snapshot['hrx_replacement']['total'] ?? 0,", "setup schema scope diagnostics")
    p = once(p, "'hr'         => $snapshot['hr']['upgrade_gaps'] ?? array(),", "'hr'         => array_merge( (array) ( $snapshot['hrx_replacement']['missing_tables'] ?? array() ), (array) ( $snapshot['hrx_replacement']['schema_drift'] ?? array() ) ),\n\t\t\t\t'hrx_design_status' => $snapshot['hrx_replacement']['design_status'] ?? 'RUNTIME_MISSING',", "setup HRX gap diagnostics")
    p = once(p, "'deep_integrity_status' => 'DEFERRED_NOT_RUN',", "'hrx_replacement'      => $snapshot['hrx_replacement'] ?? array( 'ready' => false, 'design_status' => 'RUNTIME_MISSING' ),\n\t\t\t'deep_integrity_status' => 'DEFERRED_NOT_RUN',", "setup HRX diagnostic details")
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
    if p.count("schema_contract_hrx_exact") != 2:
        raise SystemExit("both primary schema check expressions must be rewritten")

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
\t\t\t'status' => 'NOT_RUN',
\t\t\t'evaluated' => false,
\t\t\t'unresolved_active_employees' => null,
\t\t\t'excluded_retainer_service_contracts' => null,
\t\t\t'scan_query_failed' => false,
\t\t\t'rule' => 'Unknown employee-level status is reported as unknown here; use HRX payroll preflight for the selected pay period. This gate never reads, alters or auto-fills legacy employee compensation.',
\t\t\t'current_period_preflight' => 'NOT_RUN_BY_STATIC_SYSTEM_HEALTH',
\t\t);
\t\t$checks['payroll_compensation_classification'] = false;
\t\t$diagnostics['check_statuses']['payroll_compensation_classification'] = 'NOT_RUN';
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
    manifest["baseline"] = "16.1.95"
    manifest["baseline_sha256"] = BASELINE_SHA256
    manifest["artifact_class"] = "INTEGRATION_CANDIDATE"
    manifest["cumulative_core_update"] = False
    manifest["release_label"] = "Stockland Ethiopia ERP — HRX integration candidate 16.3.0 (derived from 16.1.95; not a cumulative 16.2.x update)"
    manifest["schema_contract"] = {
        "total": 45 + hrx_count,
        "core_operations": 29,
        "accounting_procurement": 16,
        "legacy_hr_payroll_active": 0,
        "hr_payroll_replacement": hrx_count,
        "schema_inventory_is_not_approved_design": True,
    }
    manifest["schema_changed"] = True
    manifest["live_data_changed"] = False
    manifest["live_business_data_changed"] = False
    manifest.setdefault("release_changes", []).append(
        "16.3.0 replaces the old four-table HR runtime with normalized HRX workforce, recruitment, contract, time/leave, compensation, legal-rule, payroll, payout, remittance, private-document, payee and exit/settlement domains."
    )
    manifest["hrx_clean_slate"] = {
        "schema_version": "1.0.0",
        "hrx_table_count_in_current_ddl_proposal": hrx_count,
        "schema_design_approved": False,
        "schema_design_gate": {
            "approved": False,
            "owner_written_approval_required": True,
            "initial_planning_range": "approximately 14-16 HR-owned tables, subject to dependency audit",
            "current_ddl_table_count": hrx_count,
            "installation_enabled": False,
            "required_evidence": ["49-table dependency map", "shared-service reuse matrix", "SOP-to-entity traceability", "approved logical and physical ERD", "migration and rollback plan"],
        },
        "legacy_tables": sorted(LEGACY_TABLES),
        "legacy_tables_active_as_system_of_record": False,
        "legacy_reset": "not included in the plugin; legacy HR/payroll tables are preserved as read-only historical sources; any future retirement requires a separately reviewed migration outside the plugin",
        "schema_setup": "blocked until written Owner approval of the final logical/physical model, ERD and dependency map; after approval, bounded CREATE-only batches; plugin activation performs no HRX DDL",
        "statutory_rates": "none hard-coded by the HRX calculator",
        "source_authority": "Notion HR Design Reset v0.5 and HR/payroll SOP/resource pack; current legal rules require qualified Ethiopian reviewer approval",
        "status": "PRODUCTION_ACCEPTANCE_PENDING — final HRX data-model approval, live E2E, legal review and Owner/HR/Finance acceptance are still required",
    }
    manifest["private_document_security"] = {
        "algorithm": "AES-256-GCM",
        "storage": "authenticated ciphertext in private HRX database blob table",
        "key_source": "site-specific STOCKLAND_HRX_DOCUMENT_KEYRING in wp-config.php or a secret manager loaded by wp-config.php",
        "current_key_version_constant": "STOCKLAND_HRX_DOCUMENT_CURRENT_KEY_VERSION",
        "key_material_in_plugin_or_database": False,
        "rotation": "bounded, audited re-encryption endpoint; prior key versions must be retained until every blob is verified under the current key",
        "missing_or_invalid_key_behavior": "document uploads fail closed with HTTP 503; review/download fail closed",
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
    manifest["private_document_security"] = {
        "algorithm": "AES-256-GCM",
        "storage": "authenticated ciphertext in private HRX database blob table",
        "key_source": "site-specific STOCKLAND_HRX_DOCUMENT_KEYRING in wp-config.php or a secret manager loaded by wp-config.php",
        "current_key_version_constant": "STOCKLAND_HRX_DOCUMENT_CURRENT_KEY_VERSION",
        "key_material_in_plugin_or_database": False,
        "key_rotation": "keyring retains old versions; existing blobs must be re-encrypted in a controlled, backed-up rotation before an old key is removed",
        "missing_or_invalid_key_behavior": "document uploads fail closed with HTTP 503; downloads/review cannot return plaintext",
        "health": "HR bootstrap reports crypto readiness and available key version IDs only; never returns key material",
    }

    files[path] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    readme_path = PLUGIN_ROOT + "README.md"
    readme = """# Stockland Ethiopia ERP — HRX integration candidate 16.3.0

> **Artifact classification:** HRX integration candidate built from the 16.1.95 baseline (SHA-256 recorded in the release manifest and receipt). It is not a cumulative core update for the reported 16.2.x installation. It replaces the legacy HR/Payroll runtime while the proposed HRX schema remains unapproved and unavailable for installation. Do not install this archive as a replacement for the running ERP.

## Product and installation

Stockland Ethiopia ERP is a WordPress plugin that provides the native Stockland operational application for retail, wholesale, inventory, warehouse, finance, procurement, reporting and people operations. Operational users work through the ERP front end; WordPress administrators retain platform administration.

For an existing installation, back up the WordPress files and MySQL/MariaDB database before replacing plugin files. Preserve the `stockland-erp-app` directory identity and existing business tables. Do not uninstall the plugin as an update method. After file replacement, clear application/PWA caches, sign in through the ERP front end, verify System Health and compare the installed files with the release checksum.

## Runtime requirements

- WordPress 6.8 or later
- PHP 8.3 or later with OpenSSL support
- MySQL 8.0 or MariaDB 10.11 or later
- HTTPS
- Writable WordPress uploads/cache paths only where the relevant WordPress feature requires them

The plugin runs inside the existing WordPress/PHP service. It does not require a separate Node.js production runtime.

## Operational areas

Command Center; Product Master; Inventory; Retail POS; Wholesale; Central Warehouse and Receiving; Purchase Orders; Requisitions and Reservations; Transfers; Returns, Damage and Adjustments; Finance and Procurement; AP/AR; PLEDGER and General Ledger; People and Payroll; Reports and Analytics; Management Intelligence; Users and Roles; Audit Trail; Notifications and System Health.

## Inventory and finance integrity

Posted stock-ledger movements remain the source of truth for physical movement history. Physical on-hand, active reservations, sellable availability and dispatched-but-unreceived transfers remain separate values. Legacy SKU-only movements and reservations are resolved to a canonical Product Master variant only when a valid mapping exists. Over-reservation remains visible as an integrity exception. An unset reorder threshold does not create a false reorder alert. Suggested replenishment is advisory and does not create a purchase order or change stock.

The ERP uses decimal-safe money and server-side posting workflows. Historical sale prices, VAT, COGS, payroll, journal entries and audit records must not be silently rewritten by later master-data edits. Required report queries fail closed when the database reports an error; unavailable data must not be displayed as zero.

## HR and Payroll

The HRX domain provides workforce planning, recruitment, applications, selection, offers, employment and contracts, onboarding, attendance, shifts, leave, compensation, legal-rule registration, payroll runs, approvals, recipient-level payout outcomes, remittance tracking, private HR resources, payees and exit/settlement workflows.

The four historical tables `employees`, `employee_compensation`, `payroll_periods` and `payroll_lines` are preserved as historical migration sources. The HRX workflow does not write to them. No legacy reset, drop or truncate endpoint is included. Historical reconciliation and an approved reversible migration are separate requirements.

HRX schema creation is an explicit, bounded, authorized operation; plugin activation does not create HRX tables. The current physical HRX DDL inventory is 47 tables and is not an approved final logical data-model count. The final model must be reviewed against the controlling Notion design, shared ERP services, data dependencies, process requirements, security and actual operational necessity before schema installation is authorized.

Payroll uses deterministic decimal arithmetic and fails closed unless effective-dated legal rules, compensation evidence, run preflight and independent approval are present. No PAYE or pension rates are represented as legally approved by this package. Do not infer tax rules from old README examples or historical calculations. Written qualified Ethiopian legal/tax/pension review is required before statutory rules are activated or payroll is certified.

## Private HR document encryption

Private HR document bytes are encrypted using AES-256-GCM before they are written to the HRX database blob table. File SHA-256 is retained for integrity verification and is not used as an encryption key. The key is never bundled in the plugin. A site-specific keyring must be supplied through protected `wp-config.php` configuration or a secret manager loaded by it.

Generate a 32-byte random key on the server:

`php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`

Configure a site-specific keyring before enabling private document uploads:

```php
define('STOCKLAND_HRX_DOCUMENT_KEYRING', [
    'v1' => 'REPLACE_WITH_BASE64_OF_32_RANDOM_BYTES'
]);
define('STOCKLAND_HRX_DOCUMENT_CURRENT_KEY_VERSION', 'v1');
```

Never use the placeholder as a key. Restrict key access, maintain protected disaster-recovery escrow, and never commit key material to GitHub or expose it in logs or support exports. Retain old key versions until every affected document has been re-encrypted and verified. Key rotation is a controlled, backed-up operation; ordinary plugin activation does not rotate keys.

## Release acceptance

The exact package still requires live WordPress/MySQL-or-MariaDB activation and end-to-end role testing, historical data reconciliation, two complete parallel payroll periods, backup/restore and rollback evidence, installed-file parity, and Owner, HR, Finance and qualified Ethiopian legal/tax/pension acceptance. A successful static or CI run is not a substitute for those production acceptance gates.
"""
    files[readme_path] = readme.encode("utf-8")

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
        "artifact_class": "INTEGRATION_CANDIDATE",
        "baseline_version": "16.1.95",
        "cumulative_core_update": False,
        "status": "PRODUCTION_ACCEPTANCE_PENDING",
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
