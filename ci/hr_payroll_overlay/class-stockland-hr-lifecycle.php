<?php
/**
 * Expanded HRIS schema and effective-dated rule services.
 * All DDL is additive and runs through the protected, bounded setup controller.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_HR_Lifecycle {
	const SCHEMA_VERSION = '16.2.0';

	public static function table_keys() {
		return array(
			'hr_workforce_plans','hr_positions','hr_requisitions','hr_candidates',
			'hr_applications','hr_interviews','hr_documents','hr_contracts',
			'hr_onboarding_tasks','hr_shifts','hr_attendance','hr_leave_policies',
			'hr_leave_balances','hr_leave_requests','hr_compensation_components',
			'hr_rule_sets','hr_rule_components','hr_employee_events','hr_payroll_events',
			'hr_payroll_payments','hr_payroll_adjustments','hr_exit_settlements',
			'hr_training_records','hr_performance_reviews'
		);
	}

	/** DDL source for fresh installs. Existing installs create one missing table per protected setup batch. */
	public static function table_ddl( $p, $cc ) {
		$t = array();
		$t[] = "CREATE TABLE {$p}stock_hr_workforce_plans (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plan_code VARCHAR(40) NOT NULL,
			fiscal_year VARCHAR(20) NOT NULL,
			version_no INT UNSIGNED NOT NULL DEFAULT 1,
			location_id BIGINT UNSIGNED NULL,
			department VARCHAR(120) NOT NULL,
			planned_headcount INT UNSIGNED NOT NULL DEFAULT 0,
			annual_salary_budget DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			other_cost_budget DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			justification TEXT NOT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			submitted_at DATETIME NULL,
			submitted_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			decision_note VARCHAR(500) NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY plan_version (plan_code,version_no), KEY status_year (status,fiscal_year), KEY location_department (location_id,department)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_positions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			position_code VARCHAR(40) NOT NULL,
			title VARCHAR(160) NOT NULL,
			department VARCHAR(120) NOT NULL,
			grade_code VARCHAR(40) NULL,
			location_id BIGINT UNSIGNED NULL,
			headcount_slots INT UNSIGNED NOT NULL DEFAULT 1,
			annual_salary_min DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			annual_salary_max DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			workforce_plan_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY position_code (position_code), KEY location_status (location_id,status), KEY department_grade (department,grade_code)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_requisitions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			requisition_code VARCHAR(40) NOT NULL,
			position_id BIGINT UNSIGNED NOT NULL,
			workforce_plan_id BIGINT UNSIGNED NULL,
			location_id BIGINT UNSIGNED NULL,
			openings INT UNSIGNED NOT NULL DEFAULT 1,
			employment_type VARCHAR(40) NOT NULL DEFAULT 'FULL_TIME',
			target_start_date DATE NULL,
			justification TEXT NOT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			opened_at DATETIME NULL,
			approved_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			decision_note VARCHAR(500) NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY requisition_code (requisition_code), KEY position_status (position_id,status), KEY location_status (location_id,status)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_candidates (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			candidate_code VARCHAR(40) NOT NULL,
			full_name VARCHAR(180) NOT NULL,
			email VARCHAR(190) NULL,
			phone VARCHAR(60) NULL,
			source VARCHAR(80) NULL,
			consent_at DATETIME NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'NEW',
			notes VARCHAR(500) NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY candidate_code (candidate_code), KEY status_created (status,created_at), KEY email (email)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_applications (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			requisition_id BIGINT UNSIGNED NOT NULL,
			candidate_id BIGINT UNSIGNED NOT NULL,
			selected_employee_id BIGINT UNSIGNED NULL,
			applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			stage VARCHAR(24) NOT NULL DEFAULT 'APPLIED',
			screening_score DECIMAL(7,2) NULL,
			decision VARCHAR(24) NOT NULL DEFAULT 'PENDING',
			decision_reason VARCHAR(500) NULL,
			decided_by BIGINT UNSIGNED NULL,
			decided_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY requisition_candidate (requisition_id,candidate_id), KEY requisition_stage (requisition_id,stage), KEY decision (decision)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_interviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			application_id BIGINT UNSIGNED NOT NULL,
			interview_round VARCHAR(40) NOT NULL DEFAULT 'INITIAL',
			scheduled_at DATETIME NULL,
			panel_json LONGTEXT NULL,
			scorecard_json LONGTEXT NULL,
			total_score DECIMAL(7,2) NULL,
			recommendation VARCHAR(24) NOT NULL DEFAULT 'PENDING',
			notes VARCHAR(1000) NULL,
			completed_by BIGINT UNSIGNED NULL,
			completed_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY application_round (application_id,interview_round), KEY scheduled_at (scheduled_at)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subject_type VARCHAR(20) NOT NULL,
			subject_id BIGINT UNSIGNED NOT NULL,
			document_type VARCHAR(60) NOT NULL,
			document_reference VARCHAR(190) NOT NULL,
			document_sha256 CHAR(64) NULL,
			issued_on DATE NULL,
			expires_on DATE NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'PENDING_REVIEW',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			notes VARCHAR(500) NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY subject_type_id (subject_type,subject_id), KEY expiry (expires_on,status)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_contracts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			contract_code VARCHAR(60) NOT NULL,
			contract_type VARCHAR(40) NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NULL,
			probation_end DATE NULL,
			position_id BIGINT UNSIGNED NULL,
			location_id BIGINT UNSIGNED NULL,
			terms_json LONGTEXT NULL,
			document_id BIGINT UNSIGNED NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			signed_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY contract_code (contract_code), KEY employee_dates (employee_id,start_date,end_date), KEY status_end (status,end_date)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_onboarding_tasks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			task_code VARCHAR(60) NOT NULL,
			task_title VARCHAR(180) NOT NULL,
			responsible_role VARCHAR(40) NOT NULL DEFAULT 'MANAGEMENT',
			due_date DATE NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
			evidence_reference VARCHAR(190) NULL,
			completed_by BIGINT UNSIGNED NULL,
			completed_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employee_status (employee_id,status), KEY due_status (due_date,status)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_shifts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			shift_code VARCHAR(40) NOT NULL,
			shift_name VARCHAR(100) NOT NULL,
			location_id BIGINT UNSIGNED NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			rule_set_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY shift_code (shift_code), KEY location_effective (location_id,effective_from,effective_to)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_attendance (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			location_id BIGINT UNSIGNED NULL,
			shift_id BIGINT UNSIGNED NULL,
			work_date DATE NOT NULL,
			check_in DATETIME NULL,
			check_out DATETIME NULL,
			ordinary_minutes INT UNSIGNED NOT NULL DEFAULT 0,
			overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
			absence_type VARCHAR(30) NULL,
			source VARCHAR(24) NOT NULL DEFAULT 'MANUAL',
			status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
			correction_reason VARCHAR(500) NULL,
			submitted_by BIGINT UNSIGNED NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY employee_work_date (employee_id,work_date), KEY location_date (location_id,work_date), KEY status_date (status,work_date)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_leave_policies (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			policy_code VARCHAR(50) NOT NULL,
			leave_type VARCHAR(40) NOT NULL,
			annual_entitlement_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			max_carry_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			min_notice_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			paid_status VARCHAR(16) NOT NULL DEFAULT 'PAID',
			rule_set_id BIGINT UNSIGNED NULL,
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY policy_effective (policy_code,effective_from), KEY leave_type_dates (leave_type,effective_from,effective_to)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_leave_balances (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			leave_type VARCHAR(40) NOT NULL,
			leave_year SMALLINT UNSIGNED NOT NULL,
			opening_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			accrued_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			used_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			adjusted_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			closing_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			updated_by BIGINT UNSIGNED NOT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY employee_leave_year (employee_id,leave_type,leave_year), KEY leave_year (leave_year,leave_type)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_leave_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			leave_type VARCHAR(40) NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			requested_days DECIMAL(8,2) NOT NULL,
			reason VARCHAR(1000) NULL,
			evidence_reference VARCHAR(190) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'SUBMITTED',
			submitted_by BIGINT UNSIGNED NOT NULL,
			submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			decided_by BIGINT UNSIGNED NULL,
			decided_at DATETIME NULL,
			decision_note VARCHAR(500) NULL,
			payroll_period_key VARCHAR(40) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employee_dates (employee_id,start_date,end_date), KEY status_start (status,start_date)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_compensation_components (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			compensation_id BIGINT UNSIGNED NOT NULL,
			component_type VARCHAR(24) NOT NULL DEFAULT 'ALLOWANCE',
			category VARCHAR(40) NOT NULL,
			label VARCHAR(160) NOT NULL,
			amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(10) NOT NULL DEFAULT 'ETB',
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			tax_treatment VARCHAR(20) NOT NULL DEFAULT 'TAXABLE',
			exempt_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			taxable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			pensionable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			rule_set_id BIGINT UNSIGNED NULL,
			rule_set_version VARCHAR(80) NULL,
			source_hash CHAR(64) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employee_effective (employee_id,effective_from,effective_to), KEY compensation_id (compensation_id), KEY rule_ref (rule_set_id,rule_set_version)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_rule_sets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_code VARCHAR(80) NOT NULL,
			rule_type VARCHAR(24) NOT NULL,
			version_label VARCHAR(80) NOT NULL,
			jurisdiction VARCHAR(80) NOT NULL DEFAULT 'ETHIOPIA-FEDERAL',
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			source_title VARCHAR(240) NOT NULL,
			source_url VARCHAR(500) NOT NULL,
			source_clause VARCHAR(240) NOT NULL,
			source_sha256 CHAR(64) NULL,
			source_publication_date DATE NULL,
			review_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			rules_json LONGTEXT NOT NULL,
			reviewer_note VARCHAR(1000) NULL,
			submitted_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY rule_version (rule_code,version_label), KEY type_effective (rule_type,review_status,effective_from,effective_to)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_rule_components (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_set_id BIGINT UNSIGNED NOT NULL,
			category VARCHAR(40) NOT NULL,
			tax_treatment VARCHAR(20) NOT NULL DEFAULT 'TAXABLE',
			exemption_cap DECIMAL(14,2) NULL DEFAULT NULL,
			pensionable_treatment VARCHAR(24) NOT NULL DEFAULT 'SALARY_ONLY',
			pensionable_cap DECIMAL(14,2) NULL DEFAULT NULL,
			source_clause VARCHAR(240) NOT NULL,
			source_note VARCHAR(1000) NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY ruleset_category (rule_set_id,category), KEY category (category)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_employee_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(40) NOT NULL,
			effective_date DATE NOT NULL,
			old_snapshot_json LONGTEXT NULL,
			new_snapshot_json LONGTEXT NOT NULL,
			reason VARCHAR(1000) NOT NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employee_effective (employee_id,effective_date), KEY event_type (event_type,created_at)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_payroll_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type VARCHAR(30) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(40) NOT NULL,
			from_status VARCHAR(24) NULL,
			to_status VARCHAR(24) NULL,
			rule_set_id BIGINT UNSIGNED NULL,
			rule_version VARCHAR(80) NULL,
			payload_sha256 CHAR(64) NOT NULL,
			payload_json LONGTEXT NULL,
			actor_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY entity_history (entity_type,entity_id,id), KEY event_type (event_type,created_at), KEY rule_version (rule_set_id,rule_version)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_payroll_payments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			payment_code VARCHAR(60) NOT NULL,
			payroll_period_id BIGINT UNSIGNED NOT NULL,
			payment_type VARCHAR(24) NOT NULL DEFAULT 'EMPLOYEE_NET',
			amount DECIMAL(14,2) NOT NULL,
			currency VARCHAR(10) NOT NULL DEFAULT 'ETB',
			payment_method VARCHAR(30) NOT NULL,
			payment_reference VARCHAR(120) NOT NULL,
			payment_date DATE NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'RECORDED',
			recorded_by BIGINT UNSIGNED NOT NULL,
			recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY payment_code (payment_code), UNIQUE KEY period_type_reference (payroll_period_id,payment_type,payment_reference), KEY payment_date (payment_date,status)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_payroll_adjustments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			source_period_id BIGINT UNSIGNED NULL,
			target_period_key VARCHAR(40) NOT NULL,
			adjustment_type VARCHAR(24) NOT NULL,
			amount DECIMAL(14,2) NOT NULL,
			taxable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			pensionable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			reason VARCHAR(1000) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			posted_journal_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employee_period (employee_id,target_period_key), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_exit_settlements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			settlement_code VARCHAR(60) NOT NULL,
			employee_id BIGINT UNSIGNED NOT NULL,
			exit_type VARCHAR(30) NOT NULL,
			exit_date DATE NOT NULL,
			last_work_date DATE NOT NULL,
			reason VARCHAR(1000) NOT NULL,
			components_json LONGTEXT NOT NULL,
			gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			taxable_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			exempt_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			tax_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employee_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			other_deductions_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			rule_set_id BIGINT UNSIGNED NULL,
			rule_version VARCHAR(80) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			submitted_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			paid_by BIGINT UNSIGNED NULL,
			paid_at DATETIME NULL,
			payment_reference VARCHAR(120) NULL,
			journal_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY settlement_code (settlement_code), KEY employee_exit (employee_id,exit_date), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_training_records (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			training_code VARCHAR(60) NOT NULL,
			title VARCHAR(180) NOT NULL,
			provider VARCHAR(180) NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
			evidence_reference VARCHAR(190) NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employee_dates (employee_id,start_date,end_date), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$t[] = "CREATE TABLE {$p}stock_hr_performance_reviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			review_period VARCHAR(40) NOT NULL,
			reviewer_id BIGINT UNSIGNED NOT NULL,
			goals_json LONGTEXT NULL,
			rating DECIMAL(5,2) NULL,
			outcome VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
			notes VARCHAR(1000) NULL,
			reviewed_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employee_period (employee_id,review_period), KEY reviewer (reviewer_id)
		) ENGINE=InnoDB {$cc};";
		return $t;
	}

	public static function write_event( $entity_type, $entity_id, $event_type, $from_status, $to_status, $payload, $rule_set_id = null, $rule_version = null ) {
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) ) { return false; }
		$inserted = Stockland_DB::insert_record(
			Stockland_DB::table( 'hr_payroll_events' ),
			array(
				'entity_type' => sanitize_key( $entity_type ),
				'entity_id' => (int) $entity_id,
				'event_type' => sanitize_key( $event_type ),
				'from_status' => $from_status ? sanitize_key( $from_status ) : null,
				'to_status' => $to_status ? sanitize_key( $to_status ) : null,
				'rule_set_id' => $rule_set_id ? (int) $rule_set_id : null,
				'rule_version' => $rule_version ? sanitize_text_field( $rule_version ) : null,
				'payload_sha256' => hash( 'sha256', $json ),
				'payload_json' => $json,
				'actor_id' => (int) get_current_user_id(),
			)
		);
		if ( ! $inserted ) { return false; }
		return Stockland_Audit::log(
			get_current_user_id(), null, 'HR_' . strtoupper( sanitize_key( $event_type ) ),
			null, null, $from_status, $to_status, substr( $json, 0, 500 ), strtoupper( $entity_type ) . (int) $entity_id
		);
	}

	/** The PAYE rules are not guessed: payroll is blocked unless one approved rule version covers the entire month. */
	public static function payroll_rules_for_period( $start_date, $end_date ) {
		global $wpdb;
		$table = Stockland_DB::table( 'hr_rule_sets' );
		$row = Stockland_DB::read_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE rule_type='PAYROLL' AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC LIMIT 1",
			$table, $start_date, $end_date
		), ARRAY_A );
		if ( ! $row || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'payroll_rules_unapproved', 'No Owner-approved Ethiopian payroll rule version covers the entire payroll period. Add the official rule source, effective dates and test vectors, then obtain independent payroll/tax review and Owner approval.', array( 'status' => 409 ) );
		}
		$payload = json_decode( (string) $row['rules_json'], true );
		if ( ! is_array( $payload ) || empty( $payload['monthly_tax_bands'] ) || empty( $payload['employee_pension_rate'] ) || empty( $payload['employer_pension_rate'] ) || empty( $payload['tax_base_method'] ) ) {
			return new WP_Error( 'payroll_rules_invalid', 'The approved payroll rule version is incomplete; payroll was not generated.', array( 'status' => 409 ) );
		}
		$payload['rule_version'] = (string) $row['version_label'];
		$payload['rule_set_id'] = (int) $row['id'];
		$payload['effective_from'] = (string) $row['effective_from'];
		$payload['effective_to'] = $row['effective_to'] ? (string) $row['effective_to'] : null;
		$payload['source_title'] = (string) $row['source_title'];
		$payload['source_clause'] = (string) $row['source_clause'];
		$payload['source_url'] = (string) $row['source_url'];
		$payload['source_sha256'] = (string) $row['source_sha256'];
		$payload['approved_by'] = (int) $row['approved_by'];
		$payload['approved_at'] = (string) $row['approved_at'];
		return $payload;
	}

	public static function allowance_rules_for_date( $date ) {
		global $wpdb;
		$sets = Stockland_DB::read_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE rule_type='ALLOWANCE' AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC,id DESC",
			Stockland_DB::table( 'hr_rule_sets' ), $date, $date
		), ARRAY_A );
		if ( ! is_array( $sets ) || ! $sets ) { return array(); }
		$active = $sets[0];
		$rows = Stockland_DB::read_results( $wpdb->prepare( 'SELECT * FROM %i WHERE rule_set_id=%d ORDER BY category', Stockland_DB::table( 'hr_rule_components' ), (int) $active['id'] ), ARRAY_A );
		$rules = array();
		foreach ( (array) $rows as $row ) {
			$row['rule_set_id'] = (int) $active['id'];
			$row['rule_set_version'] = (string) $active['version_label'];
			$row['source_title'] = (string) $active['source_title'];
			$row['source_url'] = (string) $active['source_url'];
			$row['source_sha256'] = (string) $active['source_sha256'];
			$row['source_clause'] = (string) $row['source_clause'];
			$row['approved_by'] = (int) $active['approved_by'];
			$row['approved_at'] = (string) $active['approved_at'];
			$rules[(string) $row['category']] = $row;
		}
		return $rules;
	}

	/** Applies only approved, effective-dated rule components. Unknown categories default to taxable and salary-only pension treatment. */
	public static function split_allowances( $allowances, $date ) {
		$rules = self::allowance_rules_for_date( $date );
		$result = array( 'taxable_total' => '0.00', 'exempt_total' => '0.00', 'pensionable_allowance_total' => '0.00', 'components' => array(), 'rule_set_id' => null, 'rule_version' => null );
		foreach ( (array) $allowances as $item ) {
			if ( ! is_array( $item ) ) { throw new InvalidArgumentException( 'Allowance components must be a list of structured rows.' ); }
			$category = strtoupper( sanitize_key( (string) ( $item['category'] ?? 'OTHER' ) ) );
			$label = sanitize_text_field( (string) ( $item['label'] ?? $category ) );
			$amount = Stockland_Money::decimal( Stockland_Money::minor( $item['amount'] ?? '0.00' ) );
			if ( Stockland_Money::minor( $amount ) < 0 ) { throw new InvalidArgumentException( 'Allowance amounts cannot be negative.' ); }
			$rule = $rules[$category] ?? null;
			$treatment = $rule ? strtoupper( (string) $rule['tax_treatment'] ) : 'TAXABLE';
			$exempt = '0.00';
			if ( $rule && 'EXEMPT_FULL' === $treatment ) {
				$exempt = $amount;
			} elseif ( $rule && 'EXEMPT_CAP' === $treatment ) {
				if ( null === $rule['exemption_cap'] || '' === $rule['exemption_cap'] ) {
					throw new RuntimeException( 'Approved allowance rule ' . $category . ' is missing its exemption cap; payroll blocked.' );
				}
				$exempt = Stockland_Money::decimal( min( Stockland_Money::minor( $amount ), Stockland_Money::minor( $rule['exemption_cap'] ) ) );
			} elseif ( 'TAXABLE' !== $treatment ) {
				throw new RuntimeException( 'Allowance rule for ' . $category . ' is unsupported; payroll blocked.' );
			}
			$taxable = Stockland_Money::subtract( $amount, $exempt );
			$pensionable = 'PENSIONABLE' === strtoupper( (string) ( $rule['pensionable_treatment'] ?? 'SALARY_ONLY' ) ) ? $amount : '0.00';
			$component = array(
				'category' => $category, 'label' => $label, 'amount' => $amount,
				'tax_treatment' => Stockland_Money::minor( $exempt ) > 0 ? 'EXEMPT' : 'TAXABLE',
				'taxable_amount' => $taxable, 'exempt_amount' => $exempt,
				'pensionable_amount' => $pensionable,
				'system_rule_classified' => true,
				'rule_set_id' => $rule ? (int) $rule['rule_set_id'] : null,
				'rule_set_version' => $rule ? (string) $rule['rule_set_version'] : null,
				'rule_approved_by' => $rule ? (int) $rule['approved_by'] : null,
				'rule_approved_at' => $rule ? (string) $rule['approved_at'] : null,
				'legal_basis' => $rule ? (string) $rule['source_title'] . ' — ' . (string) $rule['source_clause'] : '',
				'evidence_reference' => $rule ? (string) $rule['source_url'] : '',
				'source_hash' => $rule ? (string) $rule['source_sha256'] : '',
				'owner_verified' => false,
			);
			$result['components'][] = $component;
			$result['taxable_total'] = Stockland_Money::add( $result['taxable_total'], $taxable );
			$result['exempt_total'] = Stockland_Money::add( $result['exempt_total'], $exempt );
			$result['pensionable_allowance_total'] = Stockland_Money::add( $result['pensionable_allowance_total'], $pensionable );
			if ( $rule ) {
				$result['rule_set_id'] = (int) $rule['rule_set_id'];
				$result['rule_version'] = (string) $rule['rule_set_version'];
			}
		}
		return $result;
	}
}
