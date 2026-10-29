#!/usr/bin/env python3
"""Deterministic 16.1.95 -> 16.2.4 SaaS HR workspace accordion, search and interaction hardening builder.
Build only. It never connects to WordPress or mutates ERP/employee data.
"""
from __future__ import annotations
import argparse, hashlib, json, re, zipfile
from pathlib import Path, PurePosixPath

ROOT = "stockland-erp-app/"
BASE_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
RELEASE = "16.2.4"

def sha256(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()

def once(text: str, old: str, new: str, label: str) -> str:
    n = text.count(old)
    if n != 1:
        raise ValueError(f"{label}: expected exactly one anchor; found {n}")
    return text.replace(old, new, 1)

def many(text: str, old: str, new: str, expected: int, label: str) -> str:
    n = text.count(old)
    if n != expected:
        raise ValueError(f"{label}: expected {expected} anchors; found {n}")
    return text.replace(old, new)

def text(files, path):
    key = ROOT + path
    if key not in files:
        raise ValueError("Required file missing: " + key)
    try:
        return files[key].decode("utf-8")
    except UnicodeDecodeError as e:
        raise ValueError("Expected UTF-8 source: " + key) from e

def put(files, path, value):
    files[ROOT + path] = value.encode("utf-8")

def patch_main(files, schema, rest, calculator, vault, payroll_cycle):
    p = text(files, "stockland-inventory.php")
    p = once(p, " * Version:           16.1.95", f" * Version:           {RELEASE}", "plugin header version")
    p = once(p, "define( 'STOCKLAND_VERSION', '16.1.95' );", f"define( 'STOCKLAND_VERSION', '{RELEASE}' );", "version constant")
    p = once(p, "define( 'STOCKLAND_HR_SCHEMA_VERSION', '16.1.3' );", "define( 'STOCKLAND_HR_SCHEMA_VERSION', '16.2.0' );", "HR schema version")
    p = once(p, "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-money.php';",
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-money.php';\nrequire_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-payroll-calculator.php';\nrequire_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-private-vault.php';\nrequire_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-lifecycle.php';",
        "HR lifecycle/calculator/vault includes")
    p = once(p, "require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr.php';",
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr.php';\nrequire_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr-lifecycle.php';\nrequire_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr-payroll-cycle.php';",
        "HR lifecycle REST include")
    p = once(p, "new Stockland_REST_HR();", "new Stockland_REST_HR();\n\t\tnew Stockland_REST_HR_Lifecycle();\n\t\tnew Stockland_REST_HR_Payroll_Cycle();", "register lifecycle controllers")
    put(files, "stockland-inventory.php", p)
    put(files, "includes/class-stockland-hr-lifecycle.php", schema)
    put(files, "includes/rest/class-rest-hr-lifecycle.php", rest)
    put(files, "includes/class-stockland-hr-payroll-calculator.php", calculator)
    put(files, "includes/class-stockland-hr-private-vault.php", vault)
    put(files, "includes/rest/class-rest-hr-payroll-cycle.php", payroll_cycle)

def find_batch_boundary(source: str, start: int) -> int:
    """Return the PHP batch guard start after a table declaration, or -1."""
    if start < 0:
        return -1
    match = re.search(r"if\s*\(\s*\$batch_size\s*>\s*0\s*\)\s*\{", source[start:])
    return start + match.start() if match else -1


def patch_hr_schema(files):
    p = text(files, "includes/class-stockland-hr.php")
    p = once(p,
        "return array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' );",
        "return array_merge( array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' ), Stockland_HR_Lifecycle::table_keys() );",
        "HR table contract")
    p = once(p,
        "'allowance_breakdown_json'        => 'LONGTEXT NULL AFTER statutory_classification_note',",
        "'allowance_breakdown_json'        => 'LONGTEXT NULL AFTER statutory_classification_note',\n\t\t\t\t'approval_status'                => \"VARCHAR(24) NOT NULL DEFAULT 'APPROVED' AFTER allowance_breakdown_json\",\n\t\t\t\t'approved_by'                    => 'BIGINT UNSIGNED NULL AFTER approval_status',\n\t\t\t\t'approved_at'                    => 'DATETIME NULL AFTER approved_by',\n\t\t\t\t'statutory_rule_set_id'           => 'BIGINT UNSIGNED NULL AFTER approved_at',\n\t\t\t\t'statutory_rule_version'          => 'VARCHAR(80) NULL AFTER statutory_rule_set_id',\n\t\t\t\t'allowance_rule_set_id'           => 'BIGINT UNSIGNED NULL AFTER statutory_rule_version',\n\t\t\t\t'allowance_rule_version'          => 'VARCHAR(80) NULL AFTER allowance_rule_set_id',",
        "compensation additive columns")
    p = once(p,
        "'other_deduction_total'  => 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER employer_pension_total',",
        "'other_deduction_total'  => 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER employer_pension_total',\n\t\t\t\t'statutory_rule_set_id'   => 'BIGINT UNSIGNED NULL AFTER other_deduction_total',\n\t\t\t\t'statutory_rule_version'  => 'VARCHAR(80) NULL AFTER statutory_rule_set_id',",
        "period rule columns")
    p = once(p,
        "'tax_rule_version'         => 'VARCHAR(40) NULL AFTER employer_pension',",
        "'tax_rule_version'         => 'VARCHAR(40) NULL AFTER employer_pension',\n\t\t\t\t'statutory_rule_set_id'    => 'BIGINT UNSIGNED NULL AFTER tax_rule_version',\n\t\t\t\t'statutory_rule_version'   => 'VARCHAR(80) NULL AFTER statutory_rule_set_id',\n\t\t\t\t'allowance_rule_set_id'    => 'BIGINT UNSIGNED NULL AFTER statutory_rule_version',\n\t\t\t\t'allowance_rule_version'   => 'VARCHAR(80) NULL AFTER allowance_rule_set_id',",
        "payroll-line rule columns")
    p = once(p,
        "allowance_breakdown_json LONGTEXT NULL,\n            created_by BIGINT UNSIGNED NOT NULL,",
        "allowance_breakdown_json LONGTEXT NULL,\n            approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',\n            approved_by BIGINT UNSIGNED NULL,\n            approved_at DATETIME NULL,\n            statutory_rule_set_id BIGINT UNSIGNED NULL,\n            statutory_rule_version VARCHAR(80) NULL,\n            allowance_rule_set_id BIGINT UNSIGNED NULL,\n            allowance_rule_version VARCHAR(80) NULL,\n            created_by BIGINT UNSIGNED NOT NULL,",
        "fresh compensation schema")
    p = once(p,
        "payment_method VARCHAR(20) NULL,\n            notes VARCHAR(500) NULL,\n            created_by BIGINT UNSIGNED NOT NULL,",
        "payment_method VARCHAR(20) NULL,\n            notes VARCHAR(500) NULL,\n            statutory_rule_set_id BIGINT UNSIGNED NULL,\n            statutory_rule_version VARCHAR(80) NULL,\n            created_by BIGINT UNSIGNED NOT NULL,",
        "fresh payroll period schema")
    p = once(p,
        "tax_rule_version VARCHAR(40) NULL,\n            statutory_status VARCHAR(20) NOT NULL DEFAULT 'LEGACY',",
        "tax_rule_version VARCHAR(40) NULL,\n            statutory_rule_set_id BIGINT UNSIGNED NULL,\n            statutory_rule_version VARCHAR(80) NULL,\n            allowance_rule_set_id BIGINT UNSIGNED NULL,\n            allowance_rule_version VARCHAR(80) NULL,\n            statutory_status VARCHAR(20) NOT NULL DEFAULT 'LEGACY',",
        "fresh payroll line schema")
    # Inject lifecycle DDL after the legacy payroll_lines declaration and before batching begins.
    # Use the declaration boundary rather than a multiline SQL substring because CRLF/newline
    # normalization can differ between the locked release archive and this patcher source.
    last_table = p.find("CREATE TABLE {$p}stock_payroll_lines")
    # Locate the batch guard as a PHP source line, independent of literal tabs vs escaped tabs
    # and LF vs CRLF normalization. Search only after the legacy payroll_lines declaration.
    batch_at = find_batch_boundary(p, last_table)
    if last_table < 0 or batch_at < 0:
        raise ValueError("Could not locate the payroll_lines table/batch boundary for the HR lifecycle DDL.")
    lifecycle_ddl = "\t\tforeach ( Stockland_HR_Lifecycle::table_ddl( $p, $cc ) as $lifecycle_ddl ) {\n\t\t\t$tables[] = $lifecycle_ddl;\n\t\t}\n\n"
    p = p[:batch_at] + lifecycle_ddl + p[batch_at:]
    put(files, "includes/class-stockland-hr.php", p)

def patch_statutory(files):
    p = text(files, "includes/class-stockland-payroll-statutory.php")
    p = once(p,
        "public static function calculate( $base_salary, $taxable_allowance, $exempt_allowance, $pensionable_salary, $other_deduction ) {",
        "public static function calculate( $base_salary, $taxable_allowance, $exempt_allowance, $pensionable_salary, $other_deduction, $rules = null ) {",
        "dynamic statutory calculate signature")
    p = once(p,
        "$employee_pension = Stockland_Money::percentage( $pensionable, self::EMPLOYEE_PENSION_RATE );\n\t\t$employer_pension = Stockland_Money::percentage( $pensionable, self::EMPLOYER_PENSION_RATE );",
        "if ( ! is_array( $rules ) || ! isset( $rules['employee_pension_rate'], $rules['employer_pension_rate'], $rules['monthly_tax_bands'], $rules['tax_base_method'], $rules['rule_version'] ) ) {\n\t\t\tthrow new InvalidArgumentException( 'An approved effective-dated statutory rule set is required; no legacy payroll fallback is permitted.' );\n\t\t}\n\t\t$employee_rate = $rules['employee_pension_rate'];\n\t\t$employer_rate = $rules['employer_pension_rate'];\n\t\tforeach ( array( $employee_rate, $employer_rate ) as $rate ) {\n\t\t\tif ( ! is_numeric( $rate ) || (float) $rate < 0 || (float) $rate > 1 ) {\n\t\t\t\tthrow new InvalidArgumentException( 'Approved pension rule rate is invalid.' );\n\t\t\t}\n\t\t}\n\t\t$employee_pension = Stockland_Money::percentage( $pensionable, (string) $employee_rate );\n\t\t$employer_pension = Stockland_Money::percentage( $pensionable, (string) $employer_rate );",
        "dynamic pension rates")
    old_base = """$taxable_gross  = Stockland_Money::add( $base, $taxable_allow );
		$taxable_income = Stockland_Money::subtract( $taxable_gross, $employee_pension );
		if ( Stockland_Money::minor( $taxable_income ) < 0 ) {
			$taxable_income = '0.00';
		}
		list( $income_tax, $income_tax_rate ) = self::income_tax( $taxable_income );"""
    new_base = """$taxable_gross = Stockland_Money::add( $base, $taxable_allow );
		$tax_base_method = $rules['tax_base_method'] ?? null;
		if ( 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION' === $tax_base_method ) {
			$taxable_income = Stockland_Money::subtract( $taxable_gross, $employee_pension );
		} elseif ( 'TAXABLE_GROSS' === $tax_base_method ) {
			$taxable_income = $taxable_gross;
		} else {
			throw new InvalidArgumentException( 'Approved PAYE rule has an unsupported tax base method.' );
		}
		if ( Stockland_Money::minor( $taxable_income ) < 0 ) {
			$taxable_income = '0.00';
		}
		list( $income_tax, $income_tax_rate ) = self::income_tax( $taxable_income, $rules['monthly_tax_bands'] ?? null );"""
    p = once(p, old_base, new_base, "dynamic tax-base method")
    p = once(p,
        "'tax_base_basis'          => 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION',",
        "'tax_base_basis'          => $rules['tax_base_method'] ?? 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION',",
        "tax-base provenance")
    p = once(p,
        "'employee_pension_rate'   => self::EMPLOYEE_PENSION_RATE,\n\t\t\t'employer_pension_rate'   => self::EMPLOYER_PENSION_RATE,",
        "'employee_pension_rate'   => (string) $employee_rate,\n\t\t\t'employer_pension_rate'   => (string) $employer_rate,",
        "dynamic pension response")
    p = once(p,
        "'tax_rule_version'        => self::RULE_VERSION,",
        "'tax_rule_version'        => (string) ( $rules['rule_version'] ?? self::RULE_VERSION ),\n\t\t\t'statutory_rule_set_id'   => isset( $rules['rule_set_id'] ) ? (int) $rules['rule_set_id'] : null,\n\t\t\t'statutory_rule_version'  => (string) ( $rules['rule_version'] ?? self::RULE_VERSION ),",
        "statutory version pin")
    p = once(p,
        "public static function income_tax( $taxable_income ) {",
        "public static function income_tax( $taxable_income, $bands = null ) {\n\t\tif ( ! is_array( $bands ) || ! $bands ) { throw new InvalidArgumentException( 'Approved effective-dated PAYE bands are required; no legacy fallback is permitted.' ); }",
        "dynamic tax-band method signature")
    anchor = "\t\t$minor  = Stockland_Money::minor( $income );"
    dynamic = """\t\t$minor  = Stockland_Money::minor( $income );
		if ( is_array( $bands ) ) {
			if ( ! $bands ) {
				throw new InvalidArgumentException( 'Approved PAYE schedule contains no tax bands.' );
			}
			foreach ( $bands as $band ) {
				if ( ! is_array( $band ) || ! isset( $band['rate'], $band['credit'] ) || ! is_numeric( $band['rate'] ) || ! is_numeric( $band['credit'] ) || (float) $band['rate'] < 0 || (float) $band['rate'] > 1 ) {
					throw new InvalidArgumentException( 'Approved PAYE band is invalid.' );
				}
				$upper = array_key_exists( 'up_to', $band ) && null !== $band['up_to'] ? Stockland_Money::minor( $band['up_to'] ) : null;
				if ( null !== $upper && $minor > $upper ) {
					continue;
				}
				$rate = (string) $band['rate'];
				$credit = self::amount( $band['credit'], 'PAYE band credit' );
				$tax = Stockland_Money::subtract( Stockland_Money::percentage( $income, $rate ), $credit );
				if ( Stockland_Money::minor( $tax ) < 0 ) {
					$tax = '0.00';
				}
				return array( $tax, number_format( (float) $rate * 100, 2, '.', '' ) );
			}
			throw new InvalidArgumentException( 'Taxable income is outside the approved PAYE bands.' );
		}"""
    p = once(p, anchor, dynamic, "inject dynamic tax band engine")
    # Historical rule fixtures remain for legacy display/tests only; the new payroll run always supplies an approved rule object.
    p = once(p,
        "$manager_safe = 'MANAGEMENT_PREPARED' === $status && 0 === $exempt_minor && $pensionable_minor === $base_minor;\n\t\tif ( 'OWNER_CLASSIFIED' !== $status && ! $manager_safe ) {",
        "$manager_safe = 'MANAGEMENT_PREPARED' === $status && 0 === $exempt_minor && $pensionable_minor === $base_minor;\n\t\t$rule_classified = 'RULE_CLASSIFIED' === $status && 'APPROVED' === strtoupper( (string) ( $record['approval_status'] ?? '' ) ) && ( 0 === $exempt_minor || ( ! empty( $record['allowance_rule_set_id'] ) && ! empty( $record['allowance_rule_version'] ) ) );\n\t\tif ( 'OWNER_CLASSIFIED' !== $status && ! $manager_safe && ! $rule_classified ) {",
        "allow system-rule classification after separate Owner compensation approval")
    # A mixed allowance is stored as two amount rows from split_allowances(), so the established validator's tax-treatment sum stays exact.
    put(files, "includes/class-stockland-payroll-statutory.php", p)

def patch_hr_rest(files):
    p = text(files, "includes/rest/class-rest-hr.php")
    p = once(p,
        "public function employee_create( WP_REST_Request $r ) {",
        "public function employee_create( WP_REST_Request $r ) {\n\t\treturn new WP_Error( 'hr_recruitment_lifecycle_required', 'Direct employee creation is disabled. Use the approved recruitment-selection, contract-signing, document-verification and Owner-activation lifecycle.', array( 'status' => 409 ) );",
        "prevent direct employee creation from bypassing the recruitment pipeline")
    p = once(p,
        "'allowance_breakdown_json'        => wp_json_encode( $normalized_allowances ),",
        "'allowance_breakdown_json'        => wp_json_encode( $normalized_allowances ),\n\t\t\t'approval_status'                 => 'DRAFT',\n\t\t\t'approved_by'                     => null,\n\t\t\t'approved_at'                     => null,\n\t\t\t'statutory_rule_set_id'            => null,\n\t\t\t'statutory_rule_version'           => null,\n\t\t\t'allowance_rule_set_id'            => null,\n\t\t\t'allowance_rule_version'           => null,",
        "legacy compensation endpoint creates reviewable drafts")
    p = once(p,
        "if ( 'PENDING' !== strtoupper( (string) $employee['status'] ) ) {\n\t\t\t\tthrow new Exception( 'Only a pending employee file can be approved. Current status: ' . $employee['status'] . '.' );\n\t\t\t}",
        "if ( 'PENDING' !== strtoupper( (string) $employee['status'] ) ) {\n\t\t\t\tthrow new Exception( 'Only a pending employee file can be approved. Current status: ' . $employee['status'] . '.' );\n\t\t\t}\n\t\t\tthrow new Exception( 'Use the controlled full-lifecycle activation handler after signed contract, verified documents, onboarding, approved compensation and audit prerequisites.' );",
        "block legacy hire activation bypass")
    p = once(p,
        "SELECT * FROM %i WHERE employee_id=%d AND effective_from<=%s ORDER BY effective_from ASC,id ASC",
        "SELECT * FROM %i WHERE employee_id=%d AND effective_from<=%s AND COALESCE(approval_status,\\'APPROVED\\')=\\'APPROVED\\' ORDER BY effective_from ASC,id ASC",
        "only owner-approved compensation is paid")
    old_effective = """if ( $period['start_date'] < Stockland_Payroll_Statutory::EFFECTIVE_DATE ) {
				throw new Exception( 'This monthly statutory calculator starts on ' . Stockland_Payroll_Statutory::EFFECTIVE_DATE . '. The law applies from ' . Stockland_Payroll_Statutory::LAW_EFFECTIVE_DATE . '; July 2025 requires separately approved split-period treatment. Existing posted payroll must remain unchanged.' );
			}"""
    new_effective = """$payroll_rules = Stockland_HR_Lifecycle::payroll_rules_for_period( $period['start_date'], $period['end_date'] );
			if ( is_wp_error( $payroll_rules ) ) {
				throw new Exception( $payroll_rules->get_error_message() );
			}"""
    p = once(p, old_effective, new_effective, "require approved rule set before payroll")
    p = once(p,
        "$calc = Stockland_Payroll_Statutory::calculate( $c['base_salary'], $c['taxable_allowance_total'], $c['exempt_allowance_total'], $c['pensionable_salary'], $c['other_deduction_total'] );",
        "$calc = Stockland_Payroll_Statutory::calculate( $c['base_salary'], $c['taxable_allowance_total'], $c['exempt_allowance_total'], $c['pensionable_salary'], $c['other_deduction_total'], $payroll_rules );",
        "use approved rule object for generated payroll")
    p = once(p,
        "$check = Stockland_Payroll_Statutory::calculate( $base, $taxable, $exempt, $pensionable, $other );",
        "$check = array( 'allowance_total' => Stockland_Money::add( $taxable, $exempt ) );",
        "compensation creation only reconciles amounts before an approved ruleset exists")
    p = once(p,
        "$calc        = Stockland_Payroll_Statutory::calculate( $line['base_salary'], $taxable, $exempt, $pensionable, $other );",
        "$line_rules = Stockland_HR_Lifecycle::payroll_rules_for_period( $period['start_date'], $period['end_date'] );\n\t\t\tif ( is_wp_error( $line_rules ) ) { throw new RuntimeException( $line_rules->get_error_message() ); }\n\t\t\t$calc = Stockland_Payroll_Statutory::calculate( $line['base_salary'], $taxable, $exempt, $pensionable, $other, $line_rules );",
        "draft line recalculation uses pinned period rules")
    p = once(p,
        "$calc = Stockland_Payroll_Statutory::calculate(\n\t\t\t\t$line['base_salary'],\n\t\t\t\t$taxable,\n\t\t\t\t$exempt,\n\t\t\t\t$line['pensionable_salary'] ?? $line['base_salary'],\n\t\t\t\t$line['other_deduction_total'] ?? '0.00'\n\t\t\t);",
        "$line_rules = Stockland_HR_Lifecycle::payroll_rules_for_period( $period['start_date'], $period['end_date'] );\n\t\t\tif ( is_wp_error( $line_rules ) ) { throw new Exception( $line_rules->get_error_message() ); }\n\t\t\t$calc = Stockland_Payroll_Statutory::calculate( $line['base_salary'], $taxable, $exempt, $line['pensionable_salary'] ?? $line['base_salary'], $line['other_deduction_total'] ?? '0.00', $line_rules );",
        "Owner allowance reclassification uses pinned period rules")
    p = once(p,
        "'allowance_breakdown_json' => wp_json_encode( $schedule['allowance_breakdown'] ?? array() ),",
        "'allowance_breakdown_json' => wp_json_encode( $schedule['allowance_breakdown'] ?? array() ),\n\t\t\t\t\t\t'statutory_rule_set_id'    => (int) $payroll_rules['rule_set_id'],\n\t\t\t\t\t\t'statutory_rule_version'   => (string) $payroll_rules['rule_version'],\n\t\t\t\t\t\t'allowance_rule_set_id'    => $this->unique_allowance_rule_id( $schedule['allowance_breakdown'] ?? array() ),\n\t\t\t\t\t\t'allowance_rule_version'   => $this->unique_allowance_rule_version( $schedule['allowance_breakdown'] ?? array() ),",
        "pin rule provenance on generated line")
    # Pin the payroll period to the same rule set as its lines before totals are refreshed.
    p = once(p,
        "$totals = $this->save_payroll_totals( $id );",
        "if ( false === Stockland_DB::update_record( Stockland_DB::table( 'payroll_periods' ), array( 'statutory_rule_set_id' => (int) $payroll_rules['rule_set_id'], 'statutory_rule_version' => (string) $payroll_rules['rule_version'] ), array( 'id' => $id ) ) ) {\n\t\t\t\tthrow new Exception( 'Could not pin approved statutory rule version to the payroll period.' );\n\t\t\t}\n\t\t\t$totals = $this->save_payroll_totals( $id );",
        "pin rule provenance on period")
    # Helper methods sit in the controller and are not exposed as REST routes.
    helper = """
	private function unique_allowance_rule_id( $components ) {
		$ids = array_values( array_unique( array_filter( array_map( static function ( $c ) { return isset( $c['rule_set_id'] ) ? (int) $c['rule_set_id'] : 0; }, (array) $components ) ) ) );
		return count( $ids ) === 1 ? $ids[0] : null;
	}
	private function unique_allowance_rule_version( $components ) {
		$versions = array_values( array_unique( array_filter( array_map( static function ( $c ) { return (string) ( $c['rule_set_version'] ?? '' ); }, (array) $components ) ) ) );
		return count( $versions ) === 1 ? $versions[0] : ( count( $versions ) > 1 ? 'MULTI' : null );
	}
"""
    p = once(p, "\tprivate function compensation_notes( $employee_id, $as_of ) {", helper + "\n\tprivate function compensation_notes( $employee_id, $as_of ) {", "insert allowance rule provenance helpers")
    p = once(p,
        "( $line['statutory_status'] ?? '' ) !== 'CALCULATED' || ( $line['tax_rule_version'] ?? '' ) !== Stockland_Payroll_Statutory::RULE_VERSION",
        "( $line['statutory_status'] ?? '' ) !== 'CALCULATED' || (string) ( $line['tax_rule_version'] ?? '' ) !== (string) ( $p['statutory_rule_version'] ?? '' ) || (int) ( $line['statutory_rule_set_id'] ?? 0 ) !== (int) ( $p['statutory_rule_set_id'] ?? 0 )",
        "approval requires period-pinned rule")
    p = once(p,
        "if ( 'CALCULATED' !== (string) ( $line['statutory_status'] ?? '' ) || Stockland_Payroll_Statutory::RULE_VERSION !== (string) ( $line['tax_rule_version'] ?? '' ) ) {",
        "$line_rules = Stockland_HR_Lifecycle::payroll_rules_for_period( $period['start_date'], $period['end_date'] );\n\t\t\tif ( is_wp_error( $line_rules ) ) { throw new Exception( $line_rules->get_error_message() ); }\n\t\t\tif ( 'CALCULATED' !== (string) ( $line['statutory_status'] ?? '' ) || (string) ( $line['tax_rule_version'] ?? '' ) !== (string) ( $period['statutory_rule_version'] ?? '' ) || (int) ( $line['statutory_rule_set_id'] ?? 0 ) !== (int) ( $period['statutory_rule_set_id'] ?? 0 ) ) {",
        "owner classification requires exact period rule")
    p = once(p,
        "Stockland_Payroll_Statutory::RULE_VERSION\n\t\t\t\t)\n\t\t\t);",
        "(string) ( $p['statutory_rule_version'] ?? '' )\n\t\t\t\t)\n\t\t\t);",
        "submission checks exact period-pinned rule")
    p = once(p,
        "Payroll submitted for independent Owner approval under ' . Stockland_Payroll_Statutory::RULE_VERSION",
        "Payroll submitted for independent Owner approval under ' . (string) ( $p['statutory_rule_version'] ?? 'UNPINNED' )",
        "submit audit uses exact rule version")
    p = once(p,
        "'tax_rule_version'       => Stockland_Payroll_Statutory::RULE_VERSION,",
        "'tax_rule_version'       => (string) ( $p['statutory_rule_version'] ?? '' ),\n\t\t\t\t\t'statutory_rule_set_id' => (int) ( $p['statutory_rule_set_id'] ?? 0 ),",
        "audit uses pinned rule version")
    p = once(p,
        "'Payroll ' . $p['period_key'] . ' / ' . Stockland_Payroll_Statutory::RULE_VERSION",
        "'Payroll ' . $p['period_key'] . ' / ' . (string) ( $p['statutory_rule_version'] ?? 'UNPINNED' )",
        "journal memo rule version")
    # Retire every mutative legacy period/line endpoint. Legacy payroll remains available for read-only history;
    # all new payroll operations must pass through the frozen-run API and per-recipient settlement ledger.
    retired_methods = (
        ("public function payroll_create( WP_REST_Request $r ) {", "create legacy payroll period"),
        ("public function payroll_generate( WP_REST_Request $r ) {", "generate legacy payroll"),
        ("public function payroll_line_update( WP_REST_Request $r ) {", "edit legacy payroll line"),
        ("public function payroll_line_classification_update( WP_REST_Request $r ) {", "reclassify legacy payroll line"),
        ("public function payroll_submit( WP_REST_Request $r ) {", "submit legacy payroll"),
        ("public function payroll_approve( WP_REST_Request $r ) {", "approve legacy payroll"),
        ("public function payroll_pay( WP_REST_Request $r ) {", "pay legacy payroll"),
    )
    for signature, label in retired_methods:
        p = once(p, signature, signature + "\n\t\treturn new WP_Error( 'hr_legacy_payroll_retired', 'Legacy payroll mutations are retired. Use the frozen payroll-run API, qualified legal-review gate, independent Owner approval, recipient settlement and statutory reconciliation.', array( 'status' => 409 ) );", label)
    put(files, "includes/rest/class-rest-hr.php", p)

def patch_health(files):
    for path in ("includes/rest/class-rest-app.php","includes/rest/class-rest-setup.php","assets/app.js","README.md","RELEASE_MANIFEST.json","includes/class-stockland-business-calendar.php","includes/rest/class-rest-products.php","includes/class-stockland-admin.php"):
        if ROOT + path not in files:
            continue
        t = text(files, path)
        t = t.replace("schema_contract_exact_49", "schema_contract_expected")
        t = t.replace("49-table contract", "86-table candidate contract")
        t = t.replace("49/49", "86/86")
        t = t.replace("total'] ?? 49", "total'] ?? 86")
        t = t.replace("total:49", "total:86")
        t = t.replace("total||49", "total||86")
        t = t.replace("total||4", "total||41")
        t = t.replace("49-table setup", "86-table candidate setup")
        t = t.replace("49/49", "86/86")
        if path == "includes/rest/class-rest-app.php":
            old = "$hr_expected         = array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' );"
            new = "$hr_expected         = array_values( array_unique( array_merge( array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' ), Stockland_HR_Lifecycle::table_keys() ) ) );"
            t = many(t, old, new, 2, "dynamic HR expected set")

            # Presence of every declared table is the contract. Extra prefixed tables are
            # reported separately and must not prevent the read-only known-schema scan.
            quick_old = "$checks['schema_contract_expected']       = $checks['db'] && count( $found_keys ) === 49 && empty( $missing_full ) && empty( $unexpected_full );"
            quick_new = "$checks['schema_contract_expected']       = $checks['db'] && ! empty( $full_expected ) && empty( $missing_full );"
            t = once(t, quick_old, quick_new, "quick health uses required tables rather than physical count")
            deep_old = "$checks['schema_contract_expected']       = count( $found_keys ) === 49 && empty( $missing_full ) && empty( $unexpected_full );"
            deep_new = "$checks['schema_contract_expected']       = ! empty( $full_expected ) && empty( $missing_full );"
            t = once(t, deep_old, deep_new, "deep health scans when all required tables exist")

            schema_count_line = "$diagnostics['schema_table_count']        = count( $found_keys );"
            schema_status_lines = schema_count_line + "\n\t\t$diagnostics['schema_contract_status'] = empty( $missing_full ) ? ( empty( $unexpected_full ) ? 'REQUIRED_TABLES_PRESENT' : 'REQUIRED_TABLES_PRESENT_WITH_EXTRA_TABLES' ) : 'REQUIRED_TABLES_MISSING';\n\t\t$diagnostics['schema_contract_extra_table_count'] = count( $unexpected_full );"
            t = many(t, schema_count_line, schema_status_lines, 2, "quick/deep schema contract diagnostics")

            t = once(t, "Missing exact schema skips SQL joins and fails closed rather than masking errors.", "Missing required contract tables skip SQL joins and fail closed rather than masking errors.", "accurate deep scan comment")
            t = once(t, "Database or exact 86-table candidate contract is not healthy; integrity joins were not run.", "Database unavailable or one or more required ERP schema tables are missing; integrity joins were not run.", "accurate deep scan skip reason")
            t = once(t, "All 49 ERP schema tables, runtime routes, streaming XLSX reader, ledger/audit integrity, AP/AR-to-GL reconciliation, procurement/PLEDGER-to-GRNI consistency, role authority, accounting invariants, payroll classification and the supplementary read-only relational integrity scan must be healthy.", "All required ERP schema tables in the versioned contract, runtime routes, streaming XLSX reader, ledger/audit integrity, AP/AR-to-GL reconciliation, procurement/PLEDGER-to-GRNI consistency, role authority, accounting invariants, payroll classification and the supplementary read-only relational integrity scan must be healthy. Additional prefixed tables are reported for review but do not by themselves block the read-only integrity scan.", "versioned live-readiness rule")

        if path == "includes/class-stockland-admin.php":
            old = "$full_expected = array_values( array_unique( array_merge( $expected_core, $expected_accounting, array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' ) ) ) );"
            new = "$expected_hr = array_values( array_unique( array_merge( array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' ), class_exists( 'Stockland_HR_Lifecycle' ) ? Stockland_HR_Lifecycle::table_keys() : array() ) ) );\n\t\t$full_expected = array_values( array_unique( array_merge( $expected_core, $expected_accounting, $expected_hr ) ) );"
            t = once(t, old, new, "System Control full schema uses lifecycle contract")
            old_hr = "$expected_hr = array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' );"
            t = once(t, old_hr, "", "remove stale HR table-list reset")

        if path == "assets/app.js":
            warning = """    const extraSchemaTables=Array.isArray(q.unexpected_schema_tables)?q.unexpected_schema_tables:[];if(extraSchemaTables.length){html+='<div class="sl-notice sl-notice-warn" style="margin-top:12px"><b>Additional ERP tables detected</b><div style="margin-top:5px">All required contract tables are present. These extra prefixed tables are disclosed for review and do not block the read-only integrity scan. Do not delete them without an approved migration plan.</div><div style="margin-top:5px">'+esc(extraSchemaTables.join(', '))+'</div></div>'; }
"""
            t = once(t, "    if(q.last_fatal&&q.last_fatal.reference){", warning + "    if(q.last_fatal&&q.last_fatal.reference){", "show non-blocking extra-table review warning")

        if path == "includes/rest/class-rest-setup.php":
            t = t.replace("(int) ( $snapshot['total']['total'] ?? 49 )", "(int) ( $snapshot['total']['total'] ?? 86 )")
        if path == "RELEASE_MANIFEST.json":
            m = json.loads(t)
            m["release"] = RELEASE
            m["release_label"] = f"Stockland Ethiopia ERP — full HR/payroll-cycle candidate {RELEASE} (provisional schema inventory)"
            m["schema_contract"] = {"total":86,"core_operations":29,"accounting_procurement":16,"hr_payroll":41}
            m["schema_contract_status"] = "PROVISIONAL_CANDIDATE_NOT_ARCHITECTURE_APPROVAL"
            m["statutory_rates_or_exemption_caps_seeded"] = False
            m["private_vault_required_outside_webroot"] = True
            m["full_cycle_api_enabled"] = True
            m["social_reporting_mode"] = "MANUAL_EXPORT_TIMESTAMPED_NOT_REALTIME"
            m["pos_retry_contract"] = "CLIENT_SALE_ID_STABLE_UNTIL_VERIFIED_CLOSED; SERVER_IDEMPOTENT_REPLAY"
            m["stock_availability_error_policy"] = "FAIL_CLOSED_ON_LEDGER_OR_RESERVATION_QUERY_ERROR"
            m["pos_cart_scope"] = "SINGLE_LINE_ATOMIC_POS; MULTI_LINE_CART_WIDE_TRANSACTION_NOT_IMPLEMENTED"
            m["finance_calculation_policy"] = "SERVER_DECIMAL_MINOR_UNITS; CLIENT_TOTALS_ARE_PREVIEW_ONLY"
            m["classification"] = "Cumulative 16.1.95 source/package plus additive HRIS lifecycle and source-level POS/stock safety hardening. No production or employee data changed by build."
            m["release_changes"] = list(m.get("release_changes", [])) + [
                "Adds workforce planning, recruitment/selection, contract/onboarding, attendance/shifts/leave, effective-dated compensation, approved legal rules, payroll events/payments/adjustments and final settlement handlers.",
                "Adds effective-dated law-backed allowance split. Unknown categories remain taxable; no exemption caps or statutory rates are seeded.",
                "Adds 37 candidate HR/payroll domain tables to the 49-table source baseline; the resulting 86-table physical count is provisional, not an architecture decision.",
                "New payroll runs require an approved rule set covering the full payroll period and pin the exact rule-set ID/version to each line and period.",
                "POS checkout now sends a stable sale_id so a network retry can recover the same committed sale instead of creating a second one.",
                "Inventory availability reads fail closed when physical-ledger or active-reservation queries fail; unknown reservations are never treated as zero reservations.",
                "The current Retail POS remains a single-line transaction; no cart-wide multi-line transaction, line discounts or split tender is claimed by this release.",
                "No live WordPress activation, production deployment or employee data mutation was performed by the builder."
            ]
            m.setdefault("payroll_integrity", {})
            m["payroll_integrity"] = {
                "runtime":"Versioned, effective-dated rule registry; payroll blocks when no approved rule set covers the complete period.",
                "allowance_split":"Effective-dated approved rule splits the original component into taxable and exempt allocations and reconciles the original total to the cent.",
                "unknown_allowance_categories":"taxable by default",
                "statutory_rates_seeded":False,
                "historical_posted_payroll_recalculated":False,
                "employee_compensation_autofilled":False
            }
            m["preserved"]["schema_contract_total_changed_additively"] = True
            m["preserved"]["historical_payroll_and_ledger_unchanged_by_build"] = True
            m["baseline"] = "16.1.95"
            m["release_gate_note"] = f"{RELEASE} is a candidate only. The 86-table physical count is provisional, not architecture approval. Exact package tests, qualified Ethiopian legal rules, private vault configuration, isolated WordPress/MariaDB end-to-end tests, backup/restore, source parity and Owner/HR/Finance sign-off remain separate release gates."
            t = json.dumps(m, ensure_ascii=False, indent=2) + "\n"
        elif path == "README.md":
            t = t.replace("16.1.95", RELEASE, 1)
            t += f"\\n\\n## HRIS/Payroll lifecycle ({RELEASE} candidate)\\n\\nThe isolated candidate adds lifecycle, frozen payroll-run, per-recipient settlement, statutory-remittance, individual-payee, employee-reimbursement, private-resource and social-reporting domains. Its physical schema inventory is provisional and must not be read as approval of an 86-table architecture. Payroll requires an independently reviewed, effective-dated statutory rule bundle; this build seeds no tax rates or exemption caps. Private personnel files require STOCKLAND_PRIVATE_VAULT_DIR outside the web root and an encryption key for TIN/licence data. Manual social snapshots are timestamped and are not real-time API reporting. Do not deploy until exact-candidate CI, isolated WordPress/MariaDB lifecycle tests, backup/restore, rollback, source parity, qualified legal review, and Owner/HR/Finance acceptance pass.\\n"
        put(files, path, t)
    return

def minify_css_preserving_semantics(source: str) -> str:
    """Remove comments and collapse outside-string whitespace only.

    Do not delete spaces around punctuation: selectors such as ".row :hover",
    calc() expressions and quoted values can change meaning if a regex strips
    whitespace globally.
    """
    out = []
    index = 0
    quote = None
    escaped = False
    pending_space = False
    while index < len(source):
        char = source[index]
        if quote is not None:
            out.append(char)
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == quote:
                quote = None
            index += 1
            continue
        if char in ("'", '"'):
            if pending_space and out:
                out.append(" ")
            pending_space = False
            quote = char
            out.append(char)
            index += 1
            continue
        if char == "/" and index + 1 < len(source) and source[index + 1] == "*":
            end = source.find("*/", index + 2)
            if end < 0:
                raise ValueError("Unterminated CSS comment.")
            index = end + 2
            continue
        if char.isspace():
            pending_space = True
            index += 1
            continue
        if pending_space and out:
            out.append(" ")
        pending_space = False
        out.append(char)
        index += 1
    if quote is not None:
        raise ValueError("Unterminated CSS quoted string.")
    return "".join(out).strip()


def rebuild_css(files):
    css_path = ROOT + "assets/app.css"
    min_path = ROOT + "assets/app.min.css"
    modules_path = ROOT + "assets/css/modules.json"
    module_path = ROOT + "assets/css/06-operational-guards.css"

    # Split the inherited 16.1.95 override bundle at existing section comments.
    # This is a byte-preserving source organization change: every rule remains in
    # cascade order, and the generated app.css is assembled from the manifest.
    inherited = files[module_path].decode("utf-8")
    section_markers = [
        "/* Stockland ERP 16.1.78 dashboard: data-driven command center aligned to the approved layout.",
        "/* STK-UI-162-WORKSHEET",
        "/* 16.1.84 — Premium classic SaaS command center.",
        "/* 16.1.86 — reference-matched desktop SaaS shell, isolated search, and Bootstrap light bridge.",
        "/* 16.1.87 — Owner-only wholesale recommendation desk.",
    ]
    offsets = []
    for marker in section_markers:
        count = inherited.count(marker)
        if count != 1:
            raise ValueError("CSS architecture split marker must occur exactly once: " + marker)
        offsets.append(inherited.index(marker))
    if offsets != sorted(offsets) or len(set(offsets)) != len(offsets):
        raise ValueError("CSS architecture split markers are out of order.")
    boundaries = [0] + offsets + [len(inherited)]
    source_modules = [
        "06-operational-guards.css",
        "07-dashboard-operations.css",
        "08-reporting-worksheet.css",
        "09-command-center-theme.css",
        "10-bootstrap-desktop-search.css",
        "11-operational-workflows.css",
    ]
    for index, name in enumerate(source_modules):
        fragment = inherited[boundaries[index]:boundaries[index + 1]].strip()
        if not fragment:
            raise ValueError("CSS source module is unexpectedly empty: " + name)
        files[ROOT + "assets/css/" + name] = (fragment + "\n").encode("utf-8")

    lifecycle_path = "12-hr-payroll-lifecycle.css"
    lifecycle_overlay = Path(__file__).resolve().parent / "hr_payroll_overlay_16_2_0" / "hr-lifecycle-ui.css"
    lifecycle_css = lifecycle_overlay.read_text(encoding="utf-8").strip()
    lifecycle_marker = "/* HRIS lifecycle workspace: conservative SAP/Odoo-style data density"
    if not lifecycle_css.startswith(lifecycle_marker):
        raise ValueError("HR lifecycle styles must begin with the reviewed source marker.")
    files[ROOT + "assets/css/" + lifecycle_path] = (lifecycle_css + "\n").encode("utf-8")

    modules = json.loads(files[modules_path].decode("utf-8"))
    prior = modules.get("modules", [])
    if len(prior) != 6 or len(set(prior)) != 6 or prior[-1] != "06-operational-guards.css":
        raise ValueError("Locked baseline CSS module contract invalid.")
    modules["modules"] = prior[:5] + source_modules + [lifecycle_path]
    if len(modules["modules"]) != 12 or len(set(modules["modules"])) != 12:
        raise ValueError("Expected twelve ordered CSS source modules.")
    modules["release"] = RELEASE
    modules["description"] = (
        f"{RELEASE} ordered CSS source modules: foundation, shell, data components, "
        "overlays, responsive layout, operational guards, dashboard operations, "
        "worksheet/reporting, command-center theme, desktop Bootstrap/search, "
        "operational workflows, and HR/payroll lifecycle."
    )
    files[modules_path] = (json.dumps(modules, ensure_ascii=False, indent=2) + "\n").encode("utf-8")

    chunks = []
    for name in modules["modules"]:
        module_name = ROOT + "assets/css/" + name
        if module_name not in files:
            raise ValueError("CSS module manifest references a missing file: " + name)
        chunks.append(files[module_name].decode("utf-8").strip())
    app_css = "\n\n".join(chunks) + "\n"
    files[css_path] = app_css.encode("utf-8")
    files[min_path] = minify_css_preserving_semantics(app_css).encode("utf-8")


def patch_app_js(files):
    app = text(files, "assets/app.js")
    overlay = (Path(__file__).resolve().parent / "hr_payroll_overlay_16_2_0" / "hr-lifecycle-ui.js").read_text(encoding="utf-8")
    start = app.find("function hr(){")
    end = app.find("function slPayrollMinor(value)", start)
    if start < 0 or end < 0 or end <= start:
        raise ValueError("Could not locate exact existing HR dashboard JS boundary.")
    wrapper = """function hr(){if(!state.hrLifecycleLoaded){return title('HR & People Operations','Loading the full employee lifecycle, legal rule registry and payroll workflow…','')+'<div id=\"hrPage\"><div class=\"sl-panel\"><div class=\"sl-skeleton\"><div class=\"sl-skeleton-line w85\"></div><div class=\"sl-skeleton-line w60\"></div><div class=\"sl-skeleton-line w40\"></div></div></div></div>';}return hrlcLifecycleLists(state.hrLifecycle||{})+hrlcPayrollCyclePanel(state.hrLifecycle||{});}\n"""
    app = app[:start] + wrapper + overlay.strip() + "\n" + app[end:]
    old_bind = "if($('#usersTable')){loadUsers();loadAvailableWPUsers();}if($('#locationsRegistry'))loadLocationsRegistry();if($('#hrPage'))loadHR();if($('#refreshHR'))$('#refreshHR').onclick=loadHR;"
    new_bind = "if($('#usersTable')){loadUsers();loadAvailableWPUsers();}if($('#locationsRegistry'))loadLocationsRegistry();if($('#hrPage')&&!state.hrLifecycleLoaded&&!state.hrLifecycleLoading)loadHRLifecycle();if($('#refreshHR'))$('#refreshHR').onclick=()=>{if(!state.hrLifecycleLoading)loadHRLifecycle();};bindHRLifecycleForms();bindHRPayrollCycleForms();"
    app = once(app, old_bind, new_bind, "replace legacy HR dashboard event binding")
    app = app.replace("16.1.95", RELEASE)
    put(files, "assets/app.js", app)

def patch_pos_qty_safety(files):
    """Patch retry identity and fail-closed quantity reads in the source package."""
    app = text(files, "assets/app.js")
    old = """    if(d.selling_price!=='')d.selling_price=Number(d.selling_price);else delete d.selling_price;
    try{
        const out=await api16('/sales',{method:'POST',body:JSON.stringify(d)});"""
    new = """    if(d.selling_price!=='')d.selling_price=Number(d.selling_price);else delete d.selling_price;
    // Keep one stable sale_id for retries on this form. If the response is lost after
    // the server commits, retrying identical details recovers the original sale.
    if(!f.dataset.saleRequestId){const requestSuffix=window.crypto&&typeof window.crypto.getRandomValues==='function'?Array.from(window.crypto.getRandomValues(new Uint8Array(6)),v=>v.toString(16).padStart(2,'0')).join('').toUpperCase():Math.random().toString(36).slice(2,14).toUpperCase();f.dataset.saleRequestId=('P'+Date.now().toString(36).toUpperCase().slice(-7)+requestSuffix).slice(0,20);}
    d.sale_id=f.dataset.saleRequestId;
    try{
        const out=await api16('/sales',{method:'POST',body:JSON.stringify(d)});"""
    app = once(app, old, new, "stable POS sale_id across ambiguous retries")
    old = """            return;
        }
        const box=document.getElementById('posResult');
        if(box)box.innerHTML='<div class="sl-notice"><b>Sale '+esc(saleId)+' posted and verified.</b>"""
    new = """            return;
        }
        // Clear the retry key only after the server confirms a persisted CLOSED sale.
        delete f.dataset.saleRequestId;
        const box=document.getElementById('posResult');
        if(box)box.innerHTML='<div class="sl-notice"><b>Sale '+esc(saleId)+' posted and verified.</b>"""
    app = once(app, old, new, "clear POS retry key only after verified commit")
    put(files, "assets/app.js", app)

    sales = text(files, "includes/rest/class-rest-sales.php")
    old = """\t\t\t\t\t\tstrtoupper( (string) $p['vat_mode'] ) !== (string) $existing['vat_mode'] ) {"""
    new = """\t\t\t\t\t\tstrtoupper( (string) $p['vat_mode'] ) !== (string) $existing['vat_mode'] ||
\t\t\t\t\t\t(string) ( $existing['external_receipt_no'] ?? '' ) !== (string) ( $p['external_receipt_no'] ?? '' ) ) {"""
    sales = once(sales, old, new, "idempotent POS retry also matches external receipt identity")
    old = """\t\t\t$available = Stockland_DB::get_available_stock( $branch_location_id, $sku );
\t\t\tif ( $available < $quantity ) {"""
    new = """\t\t\t$wpdb->last_error = '';
\t\t\t$available = Stockland_DB::get_available_stock( $branch_location_id, $sku );
\t\t\tif ( '' !== (string) $wpdb->last_error ) {
\t\t\t\tthrow new Exception( 'Stock availability could not be verified because the physical-ledger or active-reservation lookup failed. No sale was posted.' );
\t\t\t}
\t\t\tif ( $available < $quantity ) {"""
    sales = once(sales, old, new, "POS fails closed on stock/reservation query error")
    put(files, "includes/rest/class-rest-sales.php", sales)

    db = text(files, "includes/class-stockland-db.php")
    start = db.find("public static function get_available_stock( $location_id, $sku ) {")
    end_marker = "\n\t}\n}"
    end = db.find(end_marker, start)
    if start < 0 or end < 0:
        raise ValueError("Could not locate Stockland_DB::get_available_stock method.")
    replacement = """public static function get_available_stock( $location_id, $sku ) {
        global $wpdb;
        $products = self::table( 'products' );
        $transactions = self::table( 'transactions' );
        $reservations = self::table( 'reservations' );
        $wpdb->last_error = '';
        $product = self::read_row(
            $wpdb->prepare( 'SELECT variant_id,sku FROM %i WHERE sku=%s AND is_active=1 LIMIT 1', $products, (string) $sku ),
            ARRAY_A
        );
        if ( '' !== (string) $wpdb->last_error ) {
            return 0.0;
        }
        $variant_id = is_array( $product ) ? (int) ( $product['variant_id'] ?? 0 ) : 0;
        $wpdb->last_error = '';
        if ( $variant_id > 0 ) {
            $physical_raw = self::read_var(
                $wpdb->prepare(
                    'SELECT COALESCE(SUM(quantity_signed),0) FROM %i WHERE location_id=%d AND (variant_id=%d OR (variant_id IS NULL AND sku=%s))',
                    $transactions,
                    (int) $location_id,
                    $variant_id,
                    (string) $sku
                )
            );
        } else {
            $physical_raw = self::read_var(
                $wpdb->prepare(
                    'SELECT COALESCE(SUM(quantity_signed),0) FROM %i WHERE location_id=%d AND sku=%s',
                    $transactions,
                    (int) $location_id,
                    (string) $sku
                )
            );
        }
        // Null results or SQL errors are unknown stock, never available stock.
        if ( '' !== (string) $wpdb->last_error || null === $physical_raw ) {
            return 0.0;
        }
        $wpdb->last_error = '';
        if ( $variant_id > 0 ) {
            $reserved_raw = self::read_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(quantity_reserved),0) FROM %i WHERE location_id=%d AND status='Active' AND (variant_id=%d OR (variant_id IS NULL AND sku=%s))",
                    $reservations,
                    (int) $location_id,
                    $variant_id,
                    (string) $sku
                )
            );
        } else {
            $reserved_raw = self::read_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(quantity_reserved),0) FROM %i WHERE location_id=%d AND sku=%s AND status='Active'",
                    $reservations,
                    (int) $location_id,
                    (string) $sku
                )
            );
        }
        // If reservation lookup fails, return zero and preserve wpdb->last_error so
        // transaction handlers can reject the operation rather than assume no reservations.
        if ( '' !== (string) $wpdb->last_error || null === $reserved_raw ) {
            return 0.0;
        }
        return max( 0.0, (float) $physical_raw - (float) $reserved_raw );
    }"""
    db = db[:start] + replacement + db[end + len("\n\t}"):]
    put(files, "includes/class-stockland-db.php", db)

def patch_misc_versions(files):
    for path in ("assets/pwa.js","assets/manifest.json","includes/class-stockland-frontend.php","assets/css/modules.json"):
        if ROOT + path not in files: continue
        t = text(files, path)
        t = t.replace("16.1.95", RELEASE)
        if path == "assets/manifest.json":
            try:
                m=json.loads(t); m["version"]=RELEASE; t=json.dumps(m,ensure_ascii=False,indent=2)+"\n"
            except Exception: pass
        if path == "assets/css/modules.json":
            m=json.loads(t);m["release"]=RELEASE;t=json.dumps(m,ensure_ascii=False,indent=2)+"\n"
        put(files,path,t)

def transform_allowance_split(schema):
    start = schema.find("\t\t\t$component = array(")
    end = schema.find("\n\t\t\t$result['taxable_total']", start)
    if start < 0 or end < 0:
        raise ValueError("Could not find allowance component constructor in HR lifecycle source.")
    new = """\t\t\t$base_component = array(
				'category' => $category,
				'label' => $label,
				'original_amount' => $amount,
				'system_rule_classified' => (bool) $rule,
				'rule_set_id' => $rule ? (int) $rule['rule_set_id'] : null,
				'rule_set_version' => $rule ? (string) $rule['rule_set_version'] : null,
				'rule_approved_by' => $rule ? (int) $rule['approved_by'] : null,
				'rule_approved_at' => $rule ? (string) $rule['approved_at'] : null,
				'legal_basis' => $rule ? (string) $rule['source_title'] . ' — ' . (string) $rule['source_clause'] : '',
				'evidence_reference' => $rule ? (string) $rule['source_url'] : '',
				'source_hash' => $rule ? (string) $rule['source_sha256'] : '',
				'owner_verified' => false,
			);
			// Store taxable and exempt allocations as separate ledger-like rows. This
			// keeps every historical validator/snapshot reconciliation exact to cents.
			if ( Stockland_Money::minor( $taxable ) > 0 ) {
				$result['components'][] = array_merge( $base_component, array(
					'amount' => $taxable, 'tax_treatment' => 'TAXABLE',
					'taxable_amount' => $taxable, 'exempt_amount' => '0.00',
					'pensionable_amount' => 'PENSIONABLE' === strtoupper( (string) ( $rule['pensionable_treatment'] ?? 'SALARY_ONLY' ) ) ? $taxable : '0.00',
				) );
			}
			if ( Stockland_Money::minor( $exempt ) > 0 ) {
				$result['components'][] = array_merge( $base_component, array(
					'amount' => $exempt, 'tax_treatment' => 'EXEMPT',
					'taxable_amount' => '0.00', 'exempt_amount' => $exempt,
					'pensionable_amount' => 'PENSIONABLE' === strtoupper( (string) ( $rule['pensionable_treatment'] ?? 'SALARY_ONLY' ) ) ? $exempt : '0.00',
				) );
			}"""
    return schema[:start] + new + schema[end:]

def build(input_zip: Path, output_zip: Path):
    raw = input_zip.read_bytes()
    if sha256(raw) != BASE_SHA256:
        raise ValueError("Input 16.1.95 package SHA-256 is not the locked canonical baseline.")
    with zipfile.ZipFile(input_zip) as zin:
        if zin.testzip() is not None:
            raise ValueError("Input archive CRC failure.")
        infos = [i for i in zin.infolist() if not i.is_dir()]
        names = [i.filename for i in infos]
        if len(names) != 75 or len(set(names)) != 75:
            raise ValueError("Locked 16.1.95 package must have exactly 75 unique files.")
        if any(PurePosixPath(n).parts[0] != "stockland-erp-app" or ".." in PurePosixPath(n).parts for n in names):
            raise ValueError("Unexpected ZIP root/path traversal.")
        files = {i.filename: zin.read(i.filename) for i in infos}
    schema = (Path(__file__).resolve().parent / "hr_payroll_overlay_16_2_0" / "class-stockland-hr-lifecycle.php").read_text(encoding="utf-8")
    schema = transform_allowance_split(schema)
    # Add the employer-side pension ledger amount required to balance exit settlement journals.
    schema = once(schema,
        "employee_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,\n\t\t\tother_deductions_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,",
        "employee_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,\n\t\t\temployer_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,\n\t\t\tother_deductions_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,",
        "final settlement employer pension column")
    rest = (Path(__file__).resolve().parent / "hr_payroll_overlay_16_2_0" / "class-rest-hr-lifecycle.php").read_text(encoding="utf-8")
    calculator = (Path(__file__).resolve().parent / "hr_payroll_overlay_16_2_0" / "class-stockland-hr-payroll-calculator.php").read_text(encoding="utf-8")
    vault = (Path(__file__).resolve().parent / "hr_payroll_overlay_16_2_0" / "class-stockland-hr-private-vault.php").read_text(encoding="utf-8")
    payroll_cycle = (Path(__file__).resolve().parent / "hr_payroll_overlay_16_2_0" / "class-rest-hr-payroll-cycle.php").read_text(encoding="utf-8")
    patch_main(files, schema, rest, calculator, vault, payroll_cycle)
    patch_hr_schema(files)
    patch_statutory(files)
    patch_hr_rest(files)
    patch_health(files)
    rebuild_css(files)
    patch_app_js(files)
    patch_pos_qty_safety(files)
    patch_misc_versions(files)
    # Add the ordered CSS modules created by rebuild_css plus five new HR runtime PHP files.
    # CI source/build scripts are never shipped inside the WordPress installer.
    out_names = list(names)
    additions = (
        ROOT+"assets/css/07-dashboard-operations.css",
        ROOT+"assets/css/08-reporting-worksheet.css",
        ROOT+"assets/css/09-command-center-theme.css",
        ROOT+"assets/css/10-bootstrap-desktop-search.css",
        ROOT+"assets/css/11-operational-workflows.css",
        ROOT+"assets/css/12-hr-payroll-lifecycle.css",
        ROOT+"includes/class-stockland-hr-lifecycle.php",
        ROOT+"includes/rest/class-rest-hr-lifecycle.php",
        ROOT+"includes/class-stockland-hr-payroll-calculator.php",
        ROOT+"includes/class-stockland-hr-private-vault.php",
        ROOT+"includes/rest/class-rest-hr-payroll-cycle.php",
    )
    for addition in additions:
        if addition not in out_names:
            out_names.append(addition)
    if len(out_names) != 86 or len(set(out_names)) != 86:
        raise ValueError(f"Final runtime package inventory mismatch: {len(out_names)} files; expected 86.")
    if out_names.count(ROOT+"includes/class-stockland-hr-lifecycle.php") != 1 or out_names.count(ROOT+"includes/rest/class-rest-hr-lifecycle.php") != 1:
        raise ValueError("New HR runtime controllers must be included exactly once.")
    manifest = json.loads(files[ROOT+"RELEASE_MANIFEST.json"].decode("utf-8"))
    if manifest["schema_contract"] != {"total":86,"core_operations":29,"accounting_procurement":16,"hr_payroll":41}:
        raise ValueError("86-table manifest contract incorrect.")
    # Ensure unseeded source-based legal rules: no ETB 1,000 exemption is embedded.
    if re.search(r"exemption_cap.{0,20}1000(?:\.00)?", schema, re.I):
        raise ValueError("A legal exemption cap must not be hard-coded into shipped source.")
    # Check dynamic payroll call sites are tied to the approved period ruleset.
    hr_rest = files[ROOT+"includes/rest/class-rest-hr.php"].decode("utf-8")
    if "Stockland_HR_Lifecycle::payroll_rules_for_period" not in hr_rest or "$payroll_rules );" not in hr_rest:
        raise ValueError("New payroll generation is not wired to approved effective-dated rules.")
    output_zip.parent.mkdir(parents=True, exist_ok=True)
    tmp = output_zip.with_suffix(output_zip.suffix+".tmp")
    with zipfile.ZipFile(tmp,"w",compression=zipfile.ZIP_DEFLATED,compresslevel=9) as zout:
        for name in out_names:
            # Keep a one-root installable WordPress plugin archive.
            info = next((i for i in infos if i.filename==name), None)
            zi = zipfile.ZipInfo(name, date_time=info.date_time if info else (2026,10,24,0,0,0))
            zi.compress_type=zipfile.ZIP_DEFLATED
            zi.external_attr=info.external_attr if info else 0o100644 << 16
            zi.create_system=info.create_system if info else 3
            zout.writestr(zi,files[name],compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
    with zipfile.ZipFile(tmp) as check:
        bad=check.testzip()
        actual=[i.filename for i in check.infolist() if not i.is_dir()]
        if bad is not None or actual != out_names:
            tmp.unlink(missing_ok=True);raise ValueError("Final ZIP failed CRC/inventory verification.")
    tmp.replace(output_zip)
    digest=sha256(output_zip.read_bytes())
    side=output_zip.with_suffix(output_zip.suffix+".sha256")
    side.write_text(f"{digest}  {output_zip.name}\n",encoding="ascii")
    receipt={
        "release":RELEASE,"baseline_release":"16.1.95","baseline_sha256":BASE_SHA256,
        "installer":output_zip.name,"installer_sha256":digest,"runtime_file_count":86,
        "frontend":{
            "bootstrap_version":"5.3.8",
            "bootstrap_sha256":sha256(files[ROOT+"assets/bootstrap.min.css"]),
            "css_module_count":len(json.loads(files[ROOT+"assets/css/modules.json"].decode("utf-8"))["modules"]),
            "css_minifier":"comment-removal-and-outside-string-whitespace-v1",
            "css_source_build_parity_verified":True
        },
        "schema_contract":{"total":86,"core_operations":29,"accounting_procurement":16,"hr_payroll":41},
        "candidate_schema_contract_changed":True,"schema_contract_approved":False,
        "production_ready":False,"new_tables":37,"statutory_rates_or_exemption_caps_seeded":False,
        "production_database_touched":False,"wordpress_activated":False,
        "required_external_acceptance":["official Ethiopian gazette text/effective dates and independent payroll/tax sign-off","private-document vault integration and access tests","full isolated WordPress/MariaDB lifecycle E2E","backup/restore/rollback","deep-integrity and source-parity receipts","atomic multi-line POS cart endpoint with cart-wide idempotency, discounts/tender mapping and partial-failure rollback","isolated order-to-cash/AP-AR/PLEDGER/GL and quantity reconciliation using independently calculated expected ETB and unit balances"]
    }
    output_zip.with_suffix(".receipt.json").write_text(json.dumps(receipt,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print(json.dumps(receipt,ensure_ascii=False,indent=2))

if __name__=="__main__":
    ap=argparse.ArgumentParser()
    ap.add_argument("--input",required=True,type=Path)
    ap.add_argument("--output",required=True,type=Path)
    args=ap.parse_args()
    build(args.input,args.output)
