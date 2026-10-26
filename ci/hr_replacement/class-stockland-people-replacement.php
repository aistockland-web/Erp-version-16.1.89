<?php
/**
 * Stockland People & Payroll replacement schema.
 *
 * This is an explicitly installed, additive target model. It never edits or drops
 * the four legacy HR tables. Run installation only through the protected
 * schema-install REST operation on an isolated, backed-up environment.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Stockland_People_Replacement {
	const SCHEMA_VERSION = '0.1.0';
	const OPTION_VERSION = 'stockland_people_schema_version';

	/**
	 * Target tables. The physical model remains versioned and reviewable; these
	 * new targets are not aliases for legacy employees/compensation/payroll rows.
	 *
	 * @return array<string>
	 */
	public static function table_keys() {
		return array(
			'people_people',
			'people_employments',
			'people_workforce_plans',
			'people_positions',
			'people_requisitions',
			'people_applications',
			'people_contracts',
			'people_documents',
			'people_tasks',
			'people_shifts',
			'people_time_entries',
			'people_leave_events',
			'people_compensation_assignments',
			'people_compensation_items',
			'people_rule_sets',
			'people_rule_items',
			'people_payroll_runs',
			'people_payroll_lines',
			'people_payment_batches',
			'people_payment_items',
			'people_remittances',
			'people_exit_cases',
			'people_exit_items',
			'people_individual_payables',
			'people_social_snapshots',
			'people_events',
			'people_resources',
			'people_download_audit',
		);
	}

	/**
	 * Fixed DDL definitions for the new, namespaced People & Payroll domain.
	 *
	 * @return array<string,array{columns:string,indexes:string}>
	 */
	private static function schema_spec() {
		$common = "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\nrecord_uuid CHAR(36) NOT NULL,\nstatus VARCHAR(24) NOT NULL DEFAULT 'DRAFT',\nversion_no BIGINT UNSIGNED NOT NULL DEFAULT 1,\ncreated_by BIGINT UNSIGNED NOT NULL,\ncreated_at DATETIME NOT NULL,\nupdated_by BIGINT UNSIGNED NULL,\nupdated_at DATETIME NULL";
		$base_indexes = "PRIMARY KEY  (id),\nUNIQUE KEY record_uuid (record_uuid)";
		return array(
			'people_people' => array(
				'columns' => $common . ",
person_code VARCHAR(48) NULL,
person_kind VARCHAR(24) NOT NULL DEFAULT 'UNCLASSIFIED',
full_name VARCHAR(190) NOT NULL,
display_name VARCHAR(190) NULL,
contact_email VARCHAR(190) NULL,
contact_phone VARCHAR(60) NULL,
identity_reference_key VARCHAR(190) NULL,
residency_code VARCHAR(32) NULL,
tin_reference_key VARCHAR(190) NULL,
license_reference_key VARCHAR(190) NULL,
privacy_notice_version VARCHAR(80) NULL,
privacy_notice_at DATETIME NULL,
retention_until DATE NULL,
notes_redacted VARCHAR(500) NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY person_code (person_code),
KEY person_kind (person_kind),
KEY full_name (full_name),
KEY contact_email (contact_email)"
			),
			'people_employments' => array(
				'columns' => $common . ",
person_id BIGINT UNSIGNED NOT NULL,
employee_code VARCHAR(48) NOT NULL,
legal_employer VARCHAR(190) NOT NULL,
department VARCHAR(120) NOT NULL,
position_id BIGINT UNSIGNED NULL,
manager_person_id BIGINT UNSIGNED NULL,
location_id BIGINT UNSIGNED NULL,
cost_center VARCHAR(80) NULL,
contract_basis VARCHAR(32) NOT NULL,
work_schedule VARCHAR(32) NOT NULL,
start_date DATE NOT NULL,
end_date DATE NULL,
probation_end DATE NULL,
last_worked_date DATE NULL,
lifecycle_status VARCHAR(24) NOT NULL DEFAULT 'PRE_HIRE',
source_legacy_employee_id BIGINT UNSIGNED NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY employee_code (employee_code),
KEY person_status (person_id,lifecycle_status),
KEY location_status (location_id,lifecycle_status),
KEY employment_dates (start_date,end_date),
KEY legacy_employee (source_legacy_employee_id)"
			),
			'people_workforce_plans' => array(
				'columns' => $common . ",
plan_code VARCHAR(60) NOT NULL,
fiscal_period VARCHAR(24) NOT NULL,
department VARCHAR(120) NOT NULL,
location_id BIGINT UNSIGNED NULL,
planned_headcount INT UNSIGNED NOT NULL DEFAULT 0,
budget_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
budget_reference VARCHAR(190) NULL,
justification TEXT NOT NULL,
submitted_by BIGINT UNSIGNED NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY plan_code (plan_code),
KEY fiscal_status (fiscal_period,status),
KEY location_status (location_id,status)"
			),
			'people_positions' => array(
				'columns' => $common . ",
position_code VARCHAR(60) NOT NULL,
title VARCHAR(160) NOT NULL,
department VARCHAR(120) NOT NULL,
grade_code VARCHAR(60) NULL,
pay_band_code VARCHAR(60) NULL,
manager_position_id BIGINT UNSIGNED NULL,
location_id BIGINT UNSIGNED NULL,
cost_center VARCHAR(80) NULL,
headcount_limit INT UNSIGNED NOT NULL DEFAULT 1,
effective_from DATE NOT NULL,
effective_to DATE NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY position_code (position_code),
KEY location_status (location_id,status),
KEY effective_dates (effective_from,effective_to)"
			),
			'people_requisitions' => array(
				'columns' => $common . ",
requisition_code VARCHAR(60) NOT NULL,
workforce_plan_id BIGINT UNSIGNED NULL,
position_id BIGINT UNSIGNED NOT NULL,
location_id BIGINT UNSIGNED NULL,
openings INT UNSIGNED NOT NULL DEFAULT 1,
reason TEXT NOT NULL,
required_from DATE NULL,
close_by DATE NULL,
recruiter_user_id BIGINT UNSIGNED NULL,
budget_amount DECIMAL(14,2) NULL,
approval_user_id BIGINT UNSIGNED NULL,
approval_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY requisition_code (requisition_code),
KEY position_status (position_id,status),
KEY location_status (location_id,status)"
			),
			'people_applications' => array(
				'columns' => $common . ",
application_code VARCHAR(60) NOT NULL,
requisition_id BIGINT UNSIGNED NOT NULL,
person_id BIGINT UNSIGNED NOT NULL,
source_code VARCHAR(40) NULL,
notice_version VARCHAR(80) NULL,
notice_acknowledged_at DATETIME NULL,
screening_summary TEXT NULL,
interview_score_json LONGTEXT NULL,
selection_rationale TEXT NULL,
decision_user_id BIGINT UNSIGNED NULL,
decision_at DATETIME NULL,
retention_until DATE NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY application_code (application_code),
UNIQUE KEY vacancy_person (requisition_id,person_id),
KEY requisition_stage (requisition_id,status),
KEY person_stage (person_id,status)"
			),
			'people_contracts' => array(
				'columns' => $common . ",
contract_code VARCHAR(60) NOT NULL,
employment_id BIGINT UNSIGNED NOT NULL,
contract_version INT UNSIGNED NOT NULL DEFAULT 1,
contract_basis VARCHAR(32) NOT NULL,
start_date DATE NOT NULL,
end_date DATE NULL,
probation_end DATE NULL,
terms_reference VARCHAR(190) NULL,
document_id BIGINT UNSIGNED NULL,
signed_at DATETIME NULL,
signed_by_person_id BIGINT UNSIGNED NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
supersedes_contract_id BIGINT UNSIGNED NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY contract_version (contract_code,contract_version),
KEY employment_status (employment_id,status),
KEY effective_dates (start_date,end_date)"
			),
			'people_documents' => array(
				'columns' => $common . ",
document_code VARCHAR(60) NOT NULL,
subject_type VARCHAR(32) NOT NULL,
subject_id BIGINT UNSIGNED NOT NULL,
document_type VARCHAR(60) NOT NULL,
vault_reference VARCHAR(190) NOT NULL,
document_sha256 CHAR(64) NULL,
issued_on DATE NULL,
expires_on DATE NULL,
sensitivity VARCHAR(24) NOT NULL DEFAULT 'RESTRICTED',
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
retention_until DATE NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY document_code (document_code),
KEY subject_type (subject_type,subject_id),
KEY document_expiry (expires_on),
KEY document_hash (document_sha256)"
			),
			'people_tasks' => array(
				'columns' => $common . ",
task_code VARCHAR(60) NOT NULL,
task_type VARCHAR(40) NOT NULL,
subject_type VARCHAR(32) NOT NULL,
subject_id BIGINT UNSIGNED NOT NULL,
assigned_user_id BIGINT UNSIGNED NULL,
due_date DATE NULL,
completed_at DATETIME NULL,
evidence_document_id BIGINT UNSIGNED NULL,
reason TEXT NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY task_code (task_code),
KEY subject_type (subject_type,subject_id),
KEY assignment_status (assigned_user_id,status),
KEY due_date (due_date)"
			),
			'people_shifts' => array(
				'columns' => $common . ",
shift_code VARCHAR(60) NOT NULL,
employment_id BIGINT UNSIGNED NULL,
location_id BIGINT UNSIGNED NULL,
shift_start DATETIME NOT NULL,
shift_end DATETIME NOT NULL,
timezone VARCHAR(80) NOT NULL,
break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
calendar_rule_set_id BIGINT UNSIGNED NULL,
published_by BIGINT UNSIGNED NULL,
published_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY shift_code (shift_code),
KEY employment_window (employment_id,shift_start,shift_end),
KEY location_start (location_id,shift_start)"
			),
			'people_time_entries' => array(
				'columns' => $common . ",
entry_code VARCHAR(60) NOT NULL,
employment_id BIGINT UNSIGNED NOT NULL,
shift_id BIGINT UNSIGNED NULL,
work_date DATE NOT NULL,
clock_in DATETIME NULL,
clock_out DATETIME NULL,
timezone VARCHAR(80) NOT NULL,
ordinary_minutes INT UNSIGNED NOT NULL DEFAULT 0,
overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
night_minutes INT UNSIGNED NOT NULL DEFAULT 0,
rest_day_minutes INT UNSIGNED NOT NULL DEFAULT 0,
holiday_minutes INT UNSIGNED NOT NULL DEFAULT 0,
source_event_key VARCHAR(190) NULL,
correction_of_id BIGINT UNSIGNED NULL,
correction_reason TEXT NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
locked_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY entry_code (entry_code),
UNIQUE KEY source_event_key (source_event_key),
KEY employment_date (employment_id,work_date),
KEY shift_id (shift_id),
KEY status_date (status,work_date)"
			),
			'people_leave_events' => array(
				'columns' => $common . ",
leave_code VARCHAR(60) NOT NULL,
employment_id BIGINT UNSIGNED NOT NULL,
leave_type VARCHAR(40) NOT NULL,
event_kind VARCHAR(24) NOT NULL,
start_date DATE NULL,
end_date DATE NULL,
units DECIMAL(8,2) NOT NULL DEFAULT 0.00,
policy_version VARCHAR(80) NULL,
reason TEXT NULL,
evidence_document_id BIGINT UNSIGNED NULL,
requested_by BIGINT UNSIGNED NULL,
decided_by BIGINT UNSIGNED NULL,
decided_at DATETIME NULL,
reversal_of_id BIGINT UNSIGNED NULL,
payroll_run_id BIGINT UNSIGNED NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY leave_code (leave_code),
KEY employment_type (employment_id,leave_type),
KEY date_window (start_date,end_date),
KEY reversal_of_id (reversal_of_id)"
			),
			'people_compensation_assignments' => array(
				'columns' => $common . ",
assignment_code VARCHAR(60) NOT NULL,
employment_id BIGINT UNSIGNED NOT NULL,
effective_from DATE NOT NULL,
effective_to DATE NULL,
base_salary DECIMAL(14,2) NOT NULL,
currency CHAR(3) NOT NULL DEFAULT 'ETB',
grade_code VARCHAR(60) NULL,
pay_band_code VARCHAR(60) NULL,
contract_reference VARCHAR(190) NULL,
reason TEXT NOT NULL,
proposed_by BIGINT UNSIGNED NOT NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
supersedes_assignment_id BIGINT UNSIGNED NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY assignment_code (assignment_code),
KEY employment_effective (employment_id,effective_from,effective_to),
KEY approval_status (status,approved_by)"
			),
			'people_compensation_items' => array(
				'columns' => $common . ",
item_code VARCHAR(60) NOT NULL,
assignment_id BIGINT UNSIGNED NOT NULL,
component_code VARCHAR(60) NOT NULL,
amount DECIMAL(14,2) NOT NULL,
cadence VARCHAR(24) NOT NULL DEFAULT 'MONTHLY',
recurring TINYINT(1) NOT NULL DEFAULT 1,
rule_item_id BIGINT UNSIGNED NULL,
evidence_document_id BIGINT UNSIGNED NULL,
contract_basis_reference VARCHAR(190) NULL,
notes_redacted VARCHAR(500) NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY item_code (item_code),
KEY assignment_component (assignment_id,component_code),
KEY rule_item_id (rule_item_id)"
			),
			'people_rule_sets' => array(
				'columns' => $common . ",
rule_set_code VARCHAR(60) NOT NULL,
rule_type VARCHAR(40) NOT NULL,
jurisdiction VARCHAR(80) NOT NULL DEFAULT 'ET-FEDERAL',
version_label VARCHAR(80) NOT NULL,
effective_from DATE NOT NULL,
effective_to DATE NULL,
source_title VARCHAR(190) NOT NULL,
source_url TEXT NOT NULL,
source_sha256 CHAR(64) NOT NULL,
source_clause VARCHAR(190) NOT NULL,
method_code VARCHAR(60) NOT NULL,
parameters_json LONGTEXT NOT NULL,
review_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW',
reviewer_user_id BIGINT UNSIGNED NULL,
reviewer_name VARCHAR(190) NULL,
reviewer_qualification VARCHAR(190) NULL,
review_reference VARCHAR(190) NULL,
reviewed_at DATETIME NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
supersedes_rule_set_id BIGINT UNSIGNED NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY rule_set_code (rule_set_code),
KEY type_status (rule_type,review_status),
KEY active_dates (effective_from,effective_to)"
			),
			'people_rule_items' => array(
				'columns' => $common . ",
rule_item_code VARCHAR(60) NOT NULL,
rule_set_id BIGINT UNSIGNED NOT NULL,
component_code VARCHAR(60) NOT NULL,
classification_code VARCHAR(40) NOT NULL,
parameters_json LONGTEXT NOT NULL,
evidence_required TINYINT(1) NOT NULL DEFAULT 1,
source_clause VARCHAR(190) NOT NULL,
effective_from DATE NOT NULL,
effective_to DATE NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY rule_item_code (rule_item_code),
KEY rule_set_component (rule_set_id,component_code),
KEY effective_dates (effective_from,effective_to)"
			),
			'people_payroll_runs' => array(
				'columns' => $common . ",
run_code VARCHAR(60) NOT NULL,
period_start DATE NOT NULL,
period_end DATE NOT NULL,
input_cutoff DATETIME NULL,
input_snapshot_hash CHAR(64) NULL,
rule_set_ids_json LONGTEXT NULL,
generated_at DATETIME NULL,
submitted_by BIGINT UNSIGNED NULL,
submitted_at DATETIME NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
posted_journal_id BIGINT UNSIGNED NULL,
posted_at DATETIME NULL,
closed_at DATETIME NULL,
exception_count INT UNSIGNED NOT NULL DEFAULT 0,
gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
paye_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
employee_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
employer_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
deduction_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
employer_cost_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
legacy_period_id BIGINT UNSIGNED NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY run_code (run_code),
KEY period_window (period_start,period_end),
KEY run_status (status),
KEY legacy_period (legacy_period_id)"
			),
			'people_payroll_lines' => array(
				'columns' => $common . ",
run_id BIGINT UNSIGNED NOT NULL,
person_id BIGINT UNSIGNED NOT NULL,
employment_id BIGINT UNSIGNED NOT NULL,
legacy_payroll_line_id BIGINT UNSIGNED NULL,
source_key VARCHAR(120) NOT NULL,
currency CHAR(3) NOT NULL DEFAULT 'ETB',
base_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
taxable_earnings DECIMAL(14,2) NOT NULL DEFAULT 0.00,
approved_exempt_earnings DECIMAL(14,2) NOT NULL DEFAULT 0.00,
gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
taxable_base DECIMAL(14,2) NULL,
paye_amount DECIMAL(14,2) NULL,
employee_pension_base DECIMAL(14,2) NULL,
employee_pension_amount DECIMAL(14,2) NULL,
employer_pension_amount DECIMAL(14,2) NULL,
other_deduction_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
net_amount DECIMAL(14,2) NULL,
employer_cost_amount DECIMAL(14,2) NULL,
calculation_trace_json LONGTEXT NULL,
input_snapshot_json LONGTEXT NOT NULL,
hold_reason VARCHAR(500) NULL,
calculated_at DATETIME NULL,
locked_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY run_source (run_id,source_key),
UNIQUE KEY legacy_line (legacy_payroll_line_id),
KEY run_status (run_id,status),
KEY person_run (person_id,run_id)"
			),
			'people_payment_batches' => array(
				'columns' => $common . ",
batch_code VARCHAR(60) NOT NULL,
payment_purpose VARCHAR(32) NOT NULL,
source_type VARCHAR(32) NOT NULL,
source_id BIGINT UNSIGNED NOT NULL,
total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
currency CHAR(3) NOT NULL DEFAULT 'ETB',
payment_method VARCHAR(24) NULL,
external_batch_reference VARCHAR(190) NULL,
submitted_at DATETIME NULL,
confirmed_at DATETIME NULL,
reconciled_at DATETIME NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
idempotency_key VARCHAR(120) NOT NULL,
failure_summary VARCHAR(500) NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY batch_code (batch_code),
UNIQUE KEY idempotency_key (idempotency_key),
KEY source_ref (source_type,source_id),
KEY payment_status (status)"
			),
			'people_payment_items' => array(
				'columns' => $common . ",
batch_id BIGINT UNSIGNED NOT NULL,
payment_item_code VARCHAR(60) NOT NULL,
payee_type VARCHAR(32) NOT NULL,
payee_id BIGINT UNSIGNED NOT NULL,
amount DECIMAL(14,2) NOT NULL,
external_payment_reference VARCHAR(190) NULL,
paid_at DATETIME NULL,
failure_code VARCHAR(60) NULL,
failure_reason VARCHAR(500) NULL,
retry_of_id BIGINT UNSIGNED NULL,
reconciled_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY payment_item_code (payment_item_code),
UNIQUE KEY external_payment_reference (external_payment_reference),
KEY batch_status (batch_id,status),
KEY payee_date (payee_type,payee_id,created_at)"
			),
			'people_remittances' => array(
				'columns' => $common . ",
remittance_code VARCHAR(60) NOT NULL,
remittance_type VARCHAR(32) NOT NULL,
jurisdiction VARCHAR(80) NOT NULL DEFAULT 'ET-FEDERAL',
period_start DATE NOT NULL,
period_end DATE NOT NULL,
rule_set_id BIGINT UNSIGNED NULL,
liability_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
due_date DATE NULL,
return_reference VARCHAR(190) NULL,
submission_reference VARCHAR(190) NULL,
receipt_reference VARCHAR(190) NULL,
submitted_at DATETIME NULL,
accepted_at DATETIME NULL,
paid_at DATETIME NULL,
payment_reference VARCHAR(190) NULL,
reconciled_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY remittance_code (remittance_code),
KEY type_period (remittance_type,period_start,period_end),
KEY due_status (due_date,status)"
			),
			'people_exit_cases' => array(
				'columns' => $common . ",
exit_code VARCHAR(60) NOT NULL,
employment_id BIGINT UNSIGNED NOT NULL,
exit_type VARCHAR(40) NOT NULL,
reason_code VARCHAR(60) NOT NULL,
notice_date DATE NULL,
last_worked_date DATE NOT NULL,
separation_date DATE NOT NULL,
clearance_json LONGTEXT NULL,
requested_by BIGINT UNSIGNED NOT NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
settlement_run_id BIGINT UNSIGNED NULL,
payment_item_id BIGINT UNSIGNED NULL,
closed_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY exit_code (exit_code),
KEY employment_status (employment_id,status),
KEY separation_date (separation_date)"
			),
			'people_exit_items' => array(
				'columns' => $common . ",
exit_case_id BIGINT UNSIGNED NOT NULL,
item_code VARCHAR(60) NOT NULL,
item_type VARCHAR(40) NOT NULL,
amount DECIMAL(14,2) NOT NULL,
rule_set_id BIGINT UNSIGNED NULL,
source_reference VARCHAR(190) NULL,
calculation_json LONGTEXT NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY exit_item_code (item_code),
KEY exit_case (exit_case_id,status)"
			),
			'people_individual_payables' => array(
				'columns' => $common . ",
payable_code VARCHAR(60) NOT NULL,
person_id BIGINT UNSIGNED NOT NULL,
relationship_type VARCHAR(32) NOT NULL,
payment_category VARCHAR(40) NOT NULL,
contract_reference VARCHAR(190) NULL,
deliverable_reference VARCHAR(500) NULL,
service_period_start DATE NULL,
service_period_end DATE NULL,
cash_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
in_kind_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
currency CHAR(3) NOT NULL DEFAULT 'ETB',
tin_status VARCHAR(24) NULL,
license_status VARCHAR(24) NULL,
tax_classification_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW',
rule_set_id BIGINT UNSIGNED NULL,
withholding_amount DECIMAL(14,2) NULL,
supporting_document_id BIGINT UNSIGNED NULL,
reviewer_user_id BIGINT UNSIGNED NULL,
reviewed_at DATETIME NULL,
payment_item_id BIGINT UNSIGNED NULL,
legacy_supplier_id BIGINT UNSIGNED NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY payable_code (payable_code),
KEY person_status (person_id,status),
KEY category_tax (payment_category,tax_classification_status),
KEY legacy_supplier (legacy_supplier_id)"
			),
			'people_social_snapshots' => array(
				'columns' => $common . ",
snapshot_code VARCHAR(60) NOT NULL,
platform_code VARCHAR(40) NOT NULL,
account_label VARCHAR(160) NOT NULL,
campaign_code VARCHAR(80) NULL,
owner_user_id BIGINT UNSIGNED NULL,
reporting_period_start DATE NOT NULL,
reporting_period_end DATE NOT NULL,
captured_at DATETIME NOT NULL,
source_reference VARCHAR(500) NULL,
views_count BIGINT UNSIGNED NULL,
reach_count BIGINT UNSIGNED NULL,
engagement_count BIGINT UNSIGNED NULL,
attributed_revenue DECIMAL(14,2) NULL,
creator_fees DECIMAL(14,2) NULL,
gifted_stock_value DECIMAL(14,2) NULL,
notes_redacted VARCHAR(500) NULL,
is_manual_capture TINYINT(1) NOT NULL DEFAULT 1",
				'indexes' => $base_indexes . ",
UNIQUE KEY snapshot_code (snapshot_code),
KEY platform_period (platform_code,reporting_period_start,reporting_period_end),
KEY captured_at (captured_at)"
			),
			'people_events' => array(
				'columns' => $common . ",
aggregate_type VARCHAR(40) NOT NULL,
aggregate_id BIGINT UNSIGNED NOT NULL,
event_code VARCHAR(60) NOT NULL,
from_status VARCHAR(24) NULL,
to_status VARCHAR(24) NULL,
actor_user_id BIGINT UNSIGNED NOT NULL,
approver_user_id BIGINT UNSIGNED NULL,
location_id BIGINT UNSIGNED NULL,
reason_redacted VARCHAR(500) NULL,
metadata_json LONGTEXT NULL,
request_id VARCHAR(100) NULL",
				'indexes' => $base_indexes . ",
KEY aggregate_event (aggregate_type,aggregate_id,created_at),
KEY actor_date (actor_user_id,created_at),
KEY request_id (request_id)"
			),
			'people_resources' => array(
				'columns' => $common . ",
resource_code VARCHAR(80) NOT NULL,
title VARCHAR(190) NOT NULL,
function_code VARCHAR(60) NOT NULL,
role_code VARCHAR(60) NOT NULL,
language_code VARCHAR(16) NOT NULL DEFAULT 'en',
resource_version VARCHAR(40) NOT NULL,
effective_from DATE NULL,
effective_to DATE NULL,
approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
vault_reference VARCHAR(190) NOT NULL,
resource_sha256 CHAR(64) NOT NULL,
owner_user_id BIGINT UNSIGNED NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL",
				'indexes' => $base_indexes . ",
UNIQUE KEY resource_version (resource_code,resource_version),
KEY role_status (role_code,approval_status),
KEY effective_dates (effective_from,effective_to)"
			),
			'people_download_audit' => array(
				'columns' => $common . ",
resource_id BIGINT UNSIGNED NOT NULL,
actor_user_id BIGINT UNSIGNED NOT NULL,
downloaded_at DATETIME NOT NULL,
result_code VARCHAR(24) NOT NULL,
request_id VARCHAR(100) NULL",
				'indexes' => $base_indexes . ",
KEY resource_actor (resource_id,actor_user_id),
KEY actor_time (actor_user_id,downloaded_at)"
			),
		);
	}

	/**
	 * Builds fully namespaced CREATE TABLE statements; identifiers come only
	 * from this class's source-controlled allow-list.
	 *
	 * @return array<string,string>
	 */
	public static function schema_sql() {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'stock_';
		$sql     = array();
		foreach ( self::schema_spec() as $key => $spec ) {
			$table = $prefix . $key;
			$sql[ $key ] = "CREATE TABLE {$table} (\n" . $spec['columns'] . ",\n" . $spec['indexes'] . "\n) {$collate};";
		}
		return $sql;
	}

	/**
	 * Report required new tables missing without changing anything.
	 *
	 * @return array<string,mixed>
	 */
	public static function schema_status() {
		global $wpdb;
		$missing = array();
		$present = array();
		foreach ( self::table_keys() as $key ) {
			$table = $wpdb->prefix . 'stock_' . $key;
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $found === $table ) {
				$present[] = $key;
			} else {
				$missing[] = $key;
			}
		}
		return array(
			'schema_version' => get_option( self::OPTION_VERSION, '' ),
			'target_version' => self::SCHEMA_VERSION,
			'expected_tables' => count( self::table_keys() ),
			'present_tables'  => count( $present ),
			'missing_tables'  => $missing,
			'ready'           => empty( $missing ) && get_option( self::OPTION_VERSION, '' ) === self::SCHEMA_VERSION,
		);
	}

	/**
	 * Install a bounded batch of new target tables; never alter legacy HR tables.
	 *
	 * @param int $offset Table offset.
	 * @param int $limit Maximum tables to create during this request.
	 * @return array<string,mixed>
	 */
	public static function install_batch( $offset = 0, $limit = 4 ) {
		global $wpdb;
		if ( ! current_user_can( 'stockland_install_schema' ) || ! Stockland_Authority::can_manage_hr() ) {
			throw new RuntimeException( 'Schema installation requires ERP Owner/admin schema authority and HR management authority.' );
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$spec = self::schema_sql();
		$keys = array_keys( $spec );
		$offset = max( 0, (int) $offset );
		$limit = min( 6, max( 1, (int) $limit ) );
		$batch = array_slice( $keys, $offset, $limit, true );
		$created_or_checked = array();
		foreach ( $batch as $key => $sql ) {
			// dbDelta is restricted to the new stock_people_* namespace. No legacy
			// employee/compensation/payroll DDL is passed to it.
			dbDelta( $sql );
			$created_or_checked[] = $key;
		}
		$status = self::schema_status();
		if ( empty( $status['missing_tables'] ) ) {
			update_option( self::OPTION_VERSION, self::SCHEMA_VERSION, false );
			$status = self::schema_status();
		}
		$status['processed_keys'] = $created_or_checked;
		$status['next_offset'] = min( count( $keys ), $offset + count( $batch ) );
		$status['complete'] = $status['next_offset'] >= count( $keys ) && $status['ready'];
		return $status;
	}

	/**
	 * Resolve a table from the source-controlled namespace allow-list.
	 *
	 * @param string $key Logical table key.
	 * @return string Database table name.
	 */
	public static function table( $key ) {
		global $wpdb;
		if ( ! in_array( $key, self::table_keys(), true ) ) {
			throw new InvalidArgumentException( 'Unknown People & Payroll table.' );
		}
		return $wpdb->prefix . 'stock_' . $key;
	}

	/**
	 * Persists a low-PII domain event separately from the shared audit chain.
	 * Callers must never include salary, identity, bank details or document bytes.
	 *
	 * @param string      $type Aggregate type.
	 * @param int         $id Aggregate ID.
	 * @param string      $event Event code.
	 * @param string|null $from Previous state.
	 * @param string|null $to New state.
	 * @param string      $reason Redacted reason.
	 * @param array       $meta Safe metadata only.
	 * @param int|null    $location_id Location, if applicable.
	 * @return bool
	 */
	public static function event( $type, $id, $event, $from = null, $to = null, $reason = '', $meta = array(), $location_id = null ) {
		$actor = get_current_user_id();
		$now = current_time( 'mysql' );
		$inserted = Stockland_DB::insert_record(
			self::table( 'people_events' ),
			array(
				'record_uuid' => wp_generate_uuid4(),
				'status' => 'RECORDED',
				'version_no' => 1,
				'created_by' => $actor,
				'created_at' => $now,
				'aggregate_type' => sanitize_key( $type ),
				'aggregate_id' => (int) $id,
				'event_code' => sanitize_key( $event ),
				'from_status' => $from ? sanitize_key( $from ) : null,
				'to_status' => $to ? sanitize_key( $to ) : null,
				'actor_user_id' => $actor,
				'approver_user_id' => null,
				'location_id' => $location_id ? (int) $location_id : null,
				'reason_redacted' => sanitize_text_field( $reason ),
				'metadata_json' => wp_json_encode( $meta ),
				'request_id' => isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUEST_ID'] ) ) : null,
			)
		);
		return false !== $inserted;
	}
}
