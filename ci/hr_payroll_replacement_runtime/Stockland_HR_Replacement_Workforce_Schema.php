<?php
/**
 * Isolated additive schema for the new HR replacement workforce foundation.
 *
 * This schema is deliberately NOT called by plugin activation or normal setup.
 * It must only be installed on disposable staging after design approval.
 * It never alters or drops the four legacy HR/payroll tables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

final class Stockland_HR_Replacement_Workforce_Schema {
	const VERSION = '0.2.0';

	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'stock_hrx_' . $suffix;
	}

	public static function table_names() {
		return array(
			'org_units'        => self::table( 'org_units' ),
			'workforce_plans'  => self::table( 'workforce_plans' ),
			'plan_lines'       => self::table( 'workforce_plan_lines' ),
			'positions'        => self::table( 'positions' ),
			'requisitions'        => self::table( 'vacancy_requisitions' ),
			'people'              => self::table( 'people' ),
			'candidates'          => self::table( 'candidates' ),
			'applications'        => self::table( 'applications' ),
			'interviews'          => self::table( 'interviews' ),
			'selection_decisions' => self::table( 'selection_decisions' ),
			'employments'         => self::table( 'employments' ),
			'contracts'           => self::table( 'contracts' ),
			'documents'           => self::table( 'documents' ),
			'onboarding_tasks'    => self::table( 'onboarding_tasks' ),
			'workflow_events'     => self::table( 'workflow_events' ),
			'command_receipts' => self::table( 'command_receipts' ),
		);
	}

	/**
	 * Explicit, opt-in staging installer. DDL runs before application transactions.
	 *
	 * @param bool $explicit_stage_confirmation Caller must explicitly pass true.
	 * @return array|WP_Error Receipt or stable failure.
	 */
	public static function install( $explicit_stage_confirmation = false ) {
		global $wpdb;
		if ( true !== $explicit_stage_confirmation ) {
			return new WP_Error( 'hrx_install_confirmation_required', 'Replacement schema installation requires explicit staging confirmation.', array( 'status' => 403 ) );
		}
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'stockland_install_schema' ) ) {
			return new WP_Error( 'hrx_install_forbidden', 'Only the separately authorized staging schema operator can install this candidate schema.', array( 'status' => 403 ) );
		}
		if ( ! $wpdb->has_cap( 'collation' ) ) {
			return new WP_Error( 'hrx_database_capability_missing', 'The database does not report the required collation support.', array( 'status' => 503 ) );
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$names = self::table_names();
		$charset = $wpdb->get_charset_collate();
		$sql = array();
		$sql[] = 'CREATE TABLE ' . $names['org_units'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
unit_code varchar(64) NOT NULL,
unit_name varchar(190) NOT NULL,
unit_type varchar(40) NOT NULL DEFAULT 'DEPARTMENT',
parent_unit_id bigint(20) unsigned NULL,
status varchar(20) NOT NULL DEFAULT 'ACTIVE',
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY unit_code (unit_code),
KEY parent_unit_id (parent_unit_id),
KEY status (status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['workforce_plans'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
plan_code varchar(80) NOT NULL,
fiscal_year varchar(20) NOT NULL,
department varchar(190) NOT NULL,
location_id bigint(20) unsigned NOT NULL,
justification text NOT NULL,
budget_reference varchar(190) NULL,
approved_budget_etb decimal(14,2) NULL,
status varchar(24) NOT NULL DEFAULT 'DRAFT',
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
submitted_by bigint(20) unsigned NULL,
submitted_at datetime NULL,
approved_by bigint(20) unsigned NULL,
approved_at datetime NULL,
decision_note text NULL,
PRIMARY KEY  (id),
UNIQUE KEY plan_code (plan_code),
KEY fiscal_year (fiscal_year),
KEY location_id (location_id),
KEY status (status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['plan_lines'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
plan_id bigint(20) unsigned NOT NULL,
line_code varchar(80) NOT NULL,
position_title varchar(190) NOT NULL,
grade_code varchar(80) NULL,
openings smallint(5) unsigned NOT NULL DEFAULT 1,
estimated_monthly_cost_etb decimal(14,2) NOT NULL DEFAULT 0.00,
location_id bigint(20) unsigned NOT NULL,
business_case text NOT NULL,
status varchar(24) NOT NULL DEFAULT 'DRAFT',
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY plan_line_code (plan_id,line_code),
KEY plan_status (plan_id,status),
KEY location_id (location_id)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['positions'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
position_code varchar(80) NOT NULL,
title varchar(190) NOT NULL,
department varchar(190) NOT NULL,
org_unit_id bigint(20) unsigned NULL,
location_id bigint(20) unsigned NOT NULL,
workforce_plan_id bigint(20) unsigned NOT NULL,
plan_line_id bigint(20) unsigned NOT NULL,
grade_code varchar(80) NULL,
reporting_position_id bigint(20) unsigned NULL,
planned_headcount smallint(5) unsigned NOT NULL DEFAULT 1,
filled_headcount smallint(5) unsigned NOT NULL DEFAULT 0,
reserved_headcount smallint(5) unsigned NOT NULL DEFAULT 0,
status varchar(24) NOT NULL DEFAULT 'DRAFT',
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
approval_reference varchar(190) NULL,
approved_by bigint(20) unsigned NULL,
approved_at datetime NULL,
decision_note text NULL,
PRIMARY KEY  (id),
UNIQUE KEY position_code (position_code),
KEY org_unit_id (org_unit_id),
KEY location_id (location_id),
KEY workforce_plan_id (workforce_plan_id),
KEY plan_line_id (plan_line_id),
KEY reporting_position_id (reporting_position_id),
KEY status (status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['requisitions'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
requisition_code varchar(80) NOT NULL,
plan_id bigint(20) unsigned NOT NULL,
plan_line_id bigint(20) unsigned NOT NULL,
position_id bigint(20) unsigned NOT NULL,
location_id bigint(20) unsigned NOT NULL,
openings smallint(5) unsigned NOT NULL DEFAULT 1,
justification text NOT NULL,
budget_reference varchar(190) NULL,
status varchar(24) NOT NULL DEFAULT 'DRAFT',
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
submitted_by bigint(20) unsigned NULL,
submitted_at datetime NULL,
approved_by bigint(20) unsigned NULL,
approved_at datetime NULL,
opened_by bigint(20) unsigned NULL,
opened_at datetime NULL,
decision_note text NULL,
PRIMARY KEY  (id),
UNIQUE KEY requisition_code (requisition_code),
KEY plan_id (plan_id),
KEY plan_line_id (plan_line_id),
KEY position_id (position_id),
KEY location_id (location_id),
KEY status (status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['people'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
person_code varchar(80) NOT NULL,
full_name varchar(190) NOT NULL,
preferred_name varchar(190) NULL,
primary_email varchar(190) NULL,
primary_phone varchar(40) NULL,
privacy_notice_version varchar(80) NOT NULL,
processing_basis varchar(40) NOT NULL,
processing_basis_reference varchar(190) NOT NULL,
consent_status varchar(40) NOT NULL DEFAULT 'PENDING',
consent_recorded_at datetime NULL,
consent_withdrawn_at datetime NULL,
retention_until date NULL,
person_status varchar(24) NOT NULL DEFAULT 'ACTIVE',
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY person_code (person_code),
KEY primary_email (primary_email),
KEY primary_phone (primary_phone),
KEY consent_status (consent_status),
KEY person_status (person_status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['candidates'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
candidate_code varchar(80) NOT NULL,
person_id bigint(20) unsigned NOT NULL,
source_code varchar(80) NOT NULL,
status varchar(24) NOT NULL DEFAULT 'ACTIVE',
privacy_notice_version varchar(80) NOT NULL,
retention_until date NULL,
withdrawn_at datetime NULL,
withdrawal_reason varchar(190) NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY candidate_code (candidate_code),
KEY person_id (person_id),
KEY status (status),
KEY retention_until (retention_until)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['applications'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
application_code varchar(80) NOT NULL,
candidate_id bigint(20) unsigned NOT NULL,
person_id bigint(20) unsigned NOT NULL,
requisition_id bigint(20) unsigned NOT NULL,
location_id bigint(20) unsigned NOT NULL,
stage varchar(24) NOT NULL DEFAULT 'APPLIED',
status varchar(24) NOT NULL DEFAULT 'OPEN',
applied_at datetime NOT NULL,
withdrawn_at datetime NULL,
decision_note text NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY application_code (application_code),
UNIQUE KEY candidate_requisition (candidate_id,requisition_id),
KEY candidate_id (candidate_id),
KEY person_id (person_id),
KEY requisition_id (requisition_id),
KEY location_id (location_id),
KEY stage_status (stage,status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['interviews'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
interview_code varchar(80) NOT NULL,
application_id bigint(20) unsigned NOT NULL,
stage_code varchar(40) NOT NULL,
scheduled_start datetime NULL,
scheduled_end datetime NULL,
timezone_code varchar(64) NOT NULL DEFAULT 'Africa/Addis_Ababa',
panel_user_ids_json longtext NULL,
status varchar(24) NOT NULL DEFAULT 'PLANNED',
evaluation_json longtext NULL,
reviewer_summary text NULL,
evidence_reference varchar(190) NULL,
completed_by bigint(20) unsigned NULL,
completed_at datetime NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY interview_code (interview_code),
KEY application_id (application_id),
KEY scheduled_start (scheduled_start),
KEY status (status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['selection_decisions'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
decision_code varchar(80) NOT NULL,
application_id bigint(20) unsigned NOT NULL,
candidate_id bigint(20) unsigned NOT NULL,
recommendation varchar(24) NOT NULL,
rationale text NOT NULL,
evidence_reference varchar(190) NULL,
recommended_by bigint(20) unsigned NOT NULL,
recommended_at datetime NOT NULL,
status varchar(24) NOT NULL DEFAULT 'PENDING_OWNER',
owner_decision varchar(24) NULL,
owner_decision_note text NULL,
owner_decided_by bigint(20) unsigned NULL,
owner_decided_at datetime NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY decision_code (decision_code),
KEY application_id (application_id),
KEY candidate_id (candidate_id),
KEY status (status),
KEY recommendation (recommendation)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['employments'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
employment_code varchar(80) NOT NULL,
person_id bigint(20) unsigned NOT NULL,
candidate_id bigint(20) unsigned NOT NULL,
application_id bigint(20) unsigned NOT NULL,
selection_decision_id bigint(20) unsigned NOT NULL,
position_id bigint(20) unsigned NOT NULL,
requisition_id bigint(20) unsigned NOT NULL,
location_id bigint(20) unsigned NOT NULL,
job_title_snapshot varchar(190) NOT NULL,
department_snapshot varchar(190) NOT NULL,
employment_type varchar(32) NOT NULL,
legal_employer_code varchar(80) NULL,
start_date date NULL,
end_date date NULL,
status varchar(24) NOT NULL DEFAULT 'PENDING_OFFER',
activated_by bigint(20) unsigned NULL,
activated_at datetime NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY employment_code (employment_code),
UNIQUE KEY application_id (application_id),
UNIQUE KEY selection_decision_id (selection_decision_id),
KEY person_id (person_id),
KEY candidate_id (candidate_id),
KEY position_id (position_id),
KEY requisition_id (requisition_id),
KEY location_id (location_id),
KEY status (status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['contracts'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
contract_code varchar(80) NOT NULL,
employment_id bigint(20) unsigned NOT NULL,
contract_type varchar(32) NOT NULL,
contract_version smallint(5) unsigned NOT NULL DEFAULT 1,
start_date date NOT NULL,
end_date date NULL,
probation_end date NULL,
document_id bigint(20) unsigned NULL,
status varchar(24) NOT NULL DEFAULT 'DRAFT',
employee_signed_at datetime NULL,
employer_signed_at datetime NULL,
approved_by bigint(20) unsigned NULL,
approved_at datetime NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY contract_code (contract_code),
UNIQUE KEY employment_version (employment_id,contract_version),
KEY employment_id (employment_id),
KEY document_id (document_id),
KEY status (status)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['documents'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
document_code varchar(80) NOT NULL,
subject_type varchar(40) NOT NULL,
subject_id bigint(20) unsigned NOT NULL,
document_type varchar(64) NOT NULL,
vault_reference varchar(190) NOT NULL,
document_sha256 char(64) NOT NULL,
content_type varchar(120) NOT NULL,
file_size_bytes bigint(20) unsigned NOT NULL,
status varchar(24) NOT NULL DEFAULT 'PENDING_VERIFICATION',
verified_by bigint(20) unsigned NULL,
verified_at datetime NULL,
approved_by bigint(20) unsigned NULL,
approved_at datetime NULL,
issued_on date NULL,
expires_on date NULL,
retention_until date NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY document_code (document_code),
UNIQUE KEY vault_reference (vault_reference),
KEY subject (subject_type,subject_id),
KEY document_type (document_type),
KEY status (status),
KEY retention_until (retention_until)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['onboarding_tasks'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
task_code varchar(80) NOT NULL,
employment_id bigint(20) unsigned NOT NULL,
task_type varchar(48) NOT NULL,
title varchar(190) NOT NULL,
mandatory tinyint(1) NOT NULL DEFAULT 1,
assigned_to_role varchar(64) NULL,
assigned_to_user bigint(20) unsigned NULL,
due_on date NULL,
status varchar(24) NOT NULL DEFAULT 'OPEN',
evidence_reference varchar(190) NULL,
completed_by bigint(20) unsigned NULL,
completed_at datetime NULL,
completion_note varchar(500) NULL,
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY employment_task (employment_id,task_code),
KEY status (status),
KEY assigned_to_user (assigned_to_user),
KEY due_on (due_on)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['workflow_events'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
entity_type varchar(60) NOT NULL,
entity_id bigint(20) unsigned NOT NULL,
event_type varchar(80) NOT NULL,
from_state varchar(24) NULL,
to_state varchar(24) NOT NULL,
actor_id bigint(20) unsigned NOT NULL,
approver_id bigint(20) unsigned NULL,
reason_code varchar(80) NULL,
reason_text text NULL,
evidence_reference varchar(190) NULL,
correlation_id varchar(128) NOT NULL,
event_payload_sha256 char(64) NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY correlation_id (correlation_id),
KEY entity_timeline (entity_type,entity_id,id),
KEY actor_id (actor_id)
) $charset;";
		$sql[] = 'CREATE TABLE ' . $names['command_receipts'] . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
idempotency_key varchar(128) NOT NULL,
actor_id bigint(20) unsigned NOT NULL,
operation_code varchar(80) NOT NULL,
request_sha256 char(64) NOT NULL,
result_json longtext NULL,
result_status varchar(24) NOT NULL DEFAULT 'PROCESSING',
entity_type varchar(60) NULL,
entity_id bigint(20) unsigned NULL,
created_at datetime NOT NULL,
completed_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY idempotency_key (idempotency_key),
KEY actor_operation (actor_id,operation_code),
KEY result_status (result_status)
) $charset;";
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
			if ( '' !== (string) $wpdb->last_error ) {
				return new WP_Error( 'hrx_schema_install_failed', 'The replacement schema could not be installed. See restricted server logs.', array( 'status' => 500 ) );
			}
		}
		$counts = array();
		foreach ( $names as $key => $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			$counts[ $key ] = $exists === $table;
		}
		if ( in_array( false, $counts, true ) ) {
			return new WP_Error( 'hrx_schema_verification_failed', 'Not all replacement tables were found after installation.', array( 'status' => 500, 'tables' => $counts ) );
		}
		update_option( 'stockland_hrx_workforce_schema_version', self::VERSION, false );
		return array( 'version' => self::VERSION, 'tables_verified' => $counts, 'legacy_tables_changed' => false );
	}
}
