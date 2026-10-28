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
	const VERSION = '0.1.0';

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
			'requisitions'     => self::table( 'vacancy_requisitions' ),
			'workflow_events'  => self::table( 'workflow_events' ),
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
grade_code varchar(80) NULL,
reporting_position_id bigint(20) unsigned NULL,
planned_headcount smallint(5) unsigned NOT NULL DEFAULT 1,
filled_headcount smallint(5) unsigned NOT NULL DEFAULT 0,
status varchar(24) NOT NULL DEFAULT 'DRAFT',
version_no bigint(20) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
updated_by bigint(20) unsigned NULL,
updated_at datetime NULL,
approval_reference varchar(190) NULL,
approved_by bigint(20) unsigned NULL,
approved_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY position_code (position_code),
KEY org_unit_id (org_unit_id),
KEY location_id (location_id),
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
correlation_id varchar(80) NOT NULL,
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
