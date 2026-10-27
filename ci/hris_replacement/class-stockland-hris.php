<?php
/**
 * Stockland HRIS replacement domain model.
 *
 * Staging-only successor authority. Legacy employees/compensation/payroll tables
 * are read-only migration sources; this class never writes to them.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_HRIS {
	const SCHEMA_VERSION = '0.2.0';
	const OPTION_SCHEMA_VERSION = 'stockland_hris_schema_version';
	const OPTION_CUTOVER = 'stockland_hris_authority_active';

	/**
	 * Provisional physical model. The final production table count remains unapproved
	 * until ERD/data-health/reuse review; this list is a candidate implementation schema.
	 *
	 * @return array<string>
	 */
	public static function table_keys() {
		return array(
			'hris_people','hris_employments','hris_positions','hris_workforce_plans',
			'hris_requisitions','hris_applications','hris_assessments','hris_contracts',
			'hris_documents','hris_onboarding_tasks','hris_employment_events',
			'hris_work_schedules','hris_time_entries','hris_leave_transactions',
			'hris_compensation_assignments','hris_compensation_items',
			'hris_legal_rules','hris_legal_rule_components','hris_payroll_runs',
			'hris_payroll_lines','hris_payroll_line_items','hris_events',
			'hris_payment_batches','hris_payment_instructions','hris_payment_attempts',
			'hris_statutory_obligations','hris_remittances','hris_exit_settlements',
			'hris_payee_engagements','hris_payee_requests','hris_payee_decisions',
			'hris_payee_payments','hris_training_records','hris_performance_reviews',
			'hris_social_snapshots'
		);
	}

	/** Expected columns used to detect partial/incompatible tables without altering them. */
	public static function required_columns() {
		return array(
			'hris_people' => array('id','person_code','legal_name','status','created_by','created_at'),
			'hris_employments' => array('id','person_id','employee_code','status','start_date','location_id'),
			'hris_positions' => array('id','position_code','title','department','status'),
			'hris_workforce_plans' => array('id','plan_code','fiscal_year','headcount_budget','status'),
			'hris_requisitions' => array('id','requisition_code','position_id','openings','status'),
			'hris_applications' => array('id','application_code','requisition_id','person_id','stage','decision','selected_employment_id'),
			'hris_assessments' => array('id','application_id','assessment_type','recommendation','created_by'),
			'hris_contracts' => array('id','contract_code','record_type','status','version_no'),
			'hris_documents' => array('id','subject_type','subject_id','document_type','vault_reference','status'),
			'hris_onboarding_tasks' => array('id','employment_id','task_code','status','created_by'),
			'hris_employment_events' => array('id','employment_id','event_type','effective_date','created_by'),
			'hris_work_schedules' => array('id','schedule_code','effective_from','start_time','end_time'),
			'hris_time_entries' => array('id','employment_id','work_date','ordinary_minutes','overtime_minutes','status'),
			'hris_leave_transactions' => array('id','employment_id','leave_type','transaction_type','days','status'),
			'hris_compensation_assignments' => array('id','employment_id','effective_from','base_salary','approval_status'),
			'hris_compensation_items' => array('id','assignment_id','category','component_type','amount','status'),
			'hris_legal_rules' => array('id','rule_code','rule_type','version_label','effective_from','source_sha256','review_status'),
			'hris_legal_rule_components' => array('id','rule_id','component_code','category','source_clause'),
			'hris_payroll_runs' => array('id','period_key','status','version_no','input_sha256'),
			'hris_payroll_lines' => array('id','run_id','employment_id','gross_pay','net_pay','status'),
			'hris_payroll_line_items' => array('id','run_id','line_id','component_code','amount','rule_version'),
			'hris_events' => array('id','entity_type','entity_id','event_code','payload_sha256','actor_id'),
			'hris_payment_batches' => array('id','batch_code','run_id','status','created_by'),
			'hris_payment_instructions' => array('id','batch_id','line_id','amount','status','idempotency_key'),
			'hris_payment_attempts' => array('id','instruction_id','attempt_no','result','recorded_by'),
			'hris_statutory_obligations' => array('id','obligation_code','obligation_type','period_key','amount','status'),
			'hris_remittances' => array('id','obligation_id','amount','status','evidence_reference'),
			'hris_exit_settlements' => array('id','settlement_code','employment_id','exit_date','status','net_total'),
			'hris_payee_engagements' => array('id','engagement_code','person_id','engagement_type','status'),
			'hris_payee_requests' => array('id','request_code','engagement_id','cash_amount','in_kind_value','status'),
			'hris_payee_decisions' => array('id','request_id','decision','basis_note','reviewed_by'),
			'hris_payee_payments' => array('id','request_id','amount','status','payment_reference'),
			'hris_training_records' => array('id','employment_id','training_code','status','created_by'),
			'hris_performance_reviews' => array('id','employment_id','review_period','status','created_by'),
			'hris_social_snapshots' => array('id','platform','account_label','period_from','period_to','captured_at')
		);
	}

	/** Candidate DDL only. All target tables are new hris_* tables; legacy tables are untouched. */
	public static function ddl( $p, $cc ) {
		$d = array();
		$d[] = "CREATE TABLE {$p}stock_hris_people (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			person_code VARCHAR(48) NOT NULL,
			legal_name VARCHAR(180) NOT NULL,
			preferred_name VARCHAR(180) NULL,
			email VARCHAR(190) NULL,
			phone VARCHAR(60) NULL,
			privacy_notice_version VARCHAR(80) NULL,
			privacy_basis VARCHAR(120) NULL,
			retention_until DATE NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY person_code (person_code), KEY legal_name (legal_name), KEY email (email), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_employments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			person_id BIGINT UNSIGNED NOT NULL,
			employee_code VARCHAR(48) NOT NULL,
			legal_employer VARCHAR(180) NOT NULL,
			employment_type VARCHAR(32) NOT NULL,
			position_id BIGINT UNSIGNED NULL,
			department VARCHAR(120) NOT NULL,
			location_id BIGINT UNSIGNED NULL,
			manager_employment_id BIGINT UNSIGNED NULL,
			wp_user_id BIGINT UNSIGNED NULL,
			start_date DATE NOT NULL,
			end_date DATE NULL,
			probation_end DATE NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'PENDING_ACTIVATION',
			legacy_employee_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY employee_code (employee_code), KEY person_id (person_id), KEY location_status (location_id,status), KEY active_dates (status,start_date,end_date), KEY legacy_crosswalk (legacy_employee_id)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_positions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			position_code VARCHAR(48) NOT NULL,
			title VARCHAR(160) NOT NULL,
			department VARCHAR(120) NOT NULL,
			grade_code VARCHAR(40) NULL,
			location_id BIGINT UNSIGNED NULL,
			headcount_slots INT UNSIGNED NOT NULL DEFAULT 1,
			annual_salary_min DECIMAL(14,2) NULL,
			annual_salary_max DECIMAL(14,2) NULL,
			job_description TEXT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY position_code (position_code), KEY department_grade (department,grade_code), KEY location_status (location_id,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_workforce_plans (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plan_code VARCHAR(48) NOT NULL,
			fiscal_year VARCHAR(20) NOT NULL,
			department VARCHAR(120) NOT NULL,
			location_id BIGINT UNSIGNED NULL,
			headcount_budget INT UNSIGNED NOT NULL DEFAULT 0,
			annual_salary_budget DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			other_cost_budget DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			justification TEXT NOT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			submitted_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY plan_code (plan_code), KEY year_status (fiscal_year,status), KEY location_department (location_id,department)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_requisitions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			requisition_code VARCHAR(48) NOT NULL,
			position_id BIGINT UNSIGNED NOT NULL,
			plan_id BIGINT UNSIGNED NULL,
			location_id BIGINT UNSIGNED NULL,
			openings INT UNSIGNED NOT NULL DEFAULT 1,
			employment_type VARCHAR(32) NOT NULL,
			target_start_date DATE NULL,
			justification TEXT NOT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			decision_note VARCHAR(500) NULL,
			submitted_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY requisition_code (requisition_code), KEY position_status (position_id,status), KEY location_status (location_id,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_applications (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			application_code VARCHAR(48) NOT NULL,
			requisition_id BIGINT UNSIGNED NOT NULL,
			person_id BIGINT UNSIGNED NOT NULL,
			stage VARCHAR(32) NOT NULL DEFAULT 'APPLIED',
			decision VARCHAR(24) NOT NULL DEFAULT 'PENDING',
			screening_score DECIMAL(7,2) NULL,
			selection_rationale VARCHAR(1200) NULL,
			consent_or_privacy_reference VARCHAR(190) NULL,
			selected_employment_id BIGINT UNSIGNED NULL,
			decided_by BIGINT UNSIGNED NULL,
			decided_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY application_code (application_code), UNIQUE KEY req_person (requisition_id,person_id), KEY req_stage (requisition_id,stage), KEY person (person_id)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_assessments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			application_id BIGINT UNSIGNED NOT NULL,
			assessment_type VARCHAR(32) NOT NULL,
			scheduled_at DATETIME NULL,
			scorecard_version VARCHAR(80) NULL,
			scores_json LONGTEXT NULL,
			total_score DECIMAL(7,2) NULL,
			recommendation VARCHAR(24) NOT NULL DEFAULT 'PENDING',
			reviewer_note VARCHAR(1200) NULL,
			reviewer_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY application_type (application_id,assessment_type), KEY reviewer (reviewer_id)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_contracts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contract_code VARCHAR(60) NOT NULL,
			record_type VARCHAR(20) NOT NULL DEFAULT 'CONTRACT',
			version_no INT UNSIGNED NOT NULL DEFAULT 1,
			application_id BIGINT UNSIGNED NULL,
			employment_id BIGINT UNSIGNED NULL,
			contract_type VARCHAR(32) NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NULL,
			probation_end DATE NULL,
			terms_hash CHAR(64) NULL,
			document_id BIGINT UNSIGNED NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			issued_at DATETIME NULL,
			signed_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY code_version (contract_code,version_no), KEY employment_dates (employment_id,start_date,end_date), KEY application_status (application_id,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subject_type VARCHAR(24) NOT NULL,
			subject_id BIGINT UNSIGNED NOT NULL,
			document_type VARCHAR(60) NOT NULL,
			vault_reference VARCHAR(190) NOT NULL,
			content_sha256 CHAR(64) NULL,
			version_label VARCHAR(80) NULL,
			issued_on DATE NULL,
			expires_on DATE NULL,
			access_class VARCHAR(24) NOT NULL DEFAULT 'HR_RESTRICTED',
			status VARCHAR(24) NOT NULL DEFAULT 'PENDING_REVIEW',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY subject (subject_type,subject_id), KEY expiry_status (expires_on,status), KEY hash (content_sha256)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_onboarding_tasks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employment_id BIGINT UNSIGNED NOT NULL,
			task_code VARCHAR(60) NOT NULL,
			task_title VARCHAR(180) NOT NULL,
			responsible_role VARCHAR(40) NOT NULL,
			due_date DATE NULL,
			evidence_reference VARCHAR(190) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
			completed_by BIGINT UNSIGNED NULL,
			completed_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employment_status (employment_id,status), KEY due_status (due_date,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_employment_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employment_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(40) NOT NULL,
			effective_date DATE NOT NULL,
			reason VARCHAR(1000) NOT NULL,
			before_hash CHAR(64) NULL,
			after_hash CHAR(64) NOT NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY employment_effective (employment_id,effective_date), KEY event_type_date (event_type,created_at)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_work_schedules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			schedule_code VARCHAR(48) NOT NULL,
			location_id BIGINT UNSIGNED NULL,
			shift_name VARCHAR(100) NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			rule_id BIGINT UNSIGNED NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY schedule_code (schedule_code), KEY location_effective (location_id,effective_from,effective_to)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_time_entries (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employment_id BIGINT UNSIGNED NOT NULL,
			location_id BIGINT UNSIGNED NULL,
			schedule_id BIGINT UNSIGNED NULL,
			work_date DATE NOT NULL,
			check_in DATETIME NULL,
			check_out DATETIME NULL,
			ordinary_minutes INT UNSIGNED NOT NULL DEFAULT 0,
			overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
			time_type VARCHAR(24) NOT NULL DEFAULT 'ORDINARY',
			source_type VARCHAR(24) NOT NULL DEFAULT 'MANUAL',
			correction_reason VARCHAR(1000) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			submitted_by BIGINT UNSIGNED NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY employment_work_date (employment_id,work_date), KEY location_date (location_id,work_date), KEY status_date (status,work_date)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_leave_transactions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			transaction_code VARCHAR(60) NOT NULL,
			employment_id BIGINT UNSIGNED NOT NULL,
			leave_type VARCHAR(40) NOT NULL,
			transaction_type VARCHAR(24) NOT NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			days DECIMAL(8,2) NOT NULL,
			reason VARCHAR(1000) NULL,
			evidence_reference VARCHAR(190) NULL,
			rule_id BIGINT UNSIGNED NULL,
			rule_version VARCHAR(80) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			submitted_by BIGINT UNSIGNED NULL,
			decided_by BIGINT UNSIGNED NULL,
			decided_at DATETIME NULL,
			decision_note VARCHAR(500) NULL,
			reverses_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY transaction_code (transaction_code), KEY employment_leave (employment_id,leave_type), KEY leave_dates (start_date,end_date), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_compensation_assignments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			assignment_code VARCHAR(60) NOT NULL,
			employment_id BIGINT UNSIGNED NOT NULL,
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			base_salary DECIMAL(14,2) NOT NULL,
			currency VARCHAR(8) NOT NULL DEFAULT 'ETB',
			change_reason VARCHAR(1000) NOT NULL,
			budget_reference VARCHAR(120) NULL,
			approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY assignment_code (assignment_code), KEY employment_effective (employment_id,effective_from,effective_to), KEY approval_status (approval_status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_compensation_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			assignment_id BIGINT UNSIGNED NOT NULL,
			component_code VARCHAR(48) NOT NULL,
			category VARCHAR(40) NOT NULL,
			component_type VARCHAR(24) NOT NULL,
			label VARCHAR(160) NOT NULL,
			amount DECIMAL(14,2) NOT NULL,
			frequency VARCHAR(24) NOT NULL DEFAULT 'MONTHLY',
			currency VARCHAR(8) NOT NULL DEFAULT 'ETB',
			source_reference VARCHAR(190) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY assignment (assignment_id), KEY category_status (category,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_legal_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_code VARCHAR(80) NOT NULL,
			rule_type VARCHAR(32) NOT NULL,
			version_label VARCHAR(80) NOT NULL,
			jurisdiction VARCHAR(80) NOT NULL DEFAULT 'ETHIOPIA-FEDERAL',
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			source_title VARCHAR(240) NOT NULL,
			source_url VARCHAR(500) NOT NULL,
			source_clause VARCHAR(240) NOT NULL,
			source_sha256 CHAR(64) NOT NULL,
			publication_date DATE NULL,
			rules_json LONGTEXT NOT NULL,
			review_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			reviewer_note VARCHAR(1200) NULL,
			submitted_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY rule_version (rule_code,version_label), KEY type_effective (rule_type,review_status,effective_from,effective_to)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_legal_rule_components (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_id BIGINT UNSIGNED NOT NULL,
			component_code VARCHAR(60) NOT NULL,
			category VARCHAR(40) NOT NULL,
			lower_bound DECIMAL(14,2) NULL,
			upper_bound DECIMAL(14,2) NULL,
			rate DECIMAL(9,6) NULL,
			fixed_credit DECIMAL(14,2) NULL,
			tax_treatment VARCHAR(24) NULL,
			exemption_cap DECIMAL(14,2) NULL,
			pensionable_treatment VARCHAR(24) NULL,
			source_clause VARCHAR(240) NOT NULL,
			parameters_json LONGTEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY rule_component (rule_id,component_code), KEY category_rule (category,rule_id)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payroll_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			period_key VARCHAR(7) NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			version_no INT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			input_sha256 CHAR(64) NULL,
			paye_rule_id BIGINT UNSIGNED NULL,
			paye_rule_version VARCHAR(80) NULL,
			pension_rule_id BIGINT UNSIGNED NULL,
			pension_rule_version VARCHAR(80) NULL,
			employee_count INT UNSIGNED NOT NULL DEFAULT 0,
			gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			paye_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employee_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employer_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			other_deduction_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employer_cost_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			journal_id BIGINT UNSIGNED NULL,
			submitted_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			closed_by BIGINT UNSIGNED NULL,
			closed_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY period_version (period_key,version_no), KEY status_period (status,period_key)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payroll_lines (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT UNSIGNED NOT NULL,
			employment_id BIGINT UNSIGNED NOT NULL,
			person_id BIGINT UNSIGNED NOT NULL,
			employee_code VARCHAR(48) NOT NULL,
			employee_name_snapshot VARCHAR(180) NOT NULL,
			location_id BIGINT UNSIGNED NULL,
			base_pay DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			earnings_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			gross_pay DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			taxable_base DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			paye DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			pensionable_base DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employee_pension DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employer_pension DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			other_deductions DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			net_pay DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employer_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			calculation_sha256 CHAR(64) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY run_employment (run_id,employment_id), KEY person (person_id), KEY status (status), KEY location (location_id)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payroll_line_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT UNSIGNED NOT NULL,
			line_id BIGINT UNSIGNED NOT NULL,
			component_code VARCHAR(60) NOT NULL,
			component_label VARCHAR(180) NOT NULL,
			component_type VARCHAR(24) NOT NULL,
			amount DECIMAL(14,2) NOT NULL,
			taxable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			exempt_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			pensionable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			rule_id BIGINT UNSIGNED NULL,
			rule_version VARCHAR(80) NULL,
			source_reference VARCHAR(190) NULL,
			calculation_json LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY run_line (run_id,line_id), KEY rule_ref (rule_id,rule_version)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type VARCHAR(40) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			event_code VARCHAR(60) NOT NULL,
			from_state VARCHAR(24) NULL,
			to_state VARCHAR(24) NULL,
			actor_id BIGINT UNSIGNED NOT NULL,
			approver_id BIGINT UNSIGNED NULL,
			reference_hash CHAR(64) NOT NULL,
			details_json LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY entity_history (entity_type,entity_id,id), KEY event_time (event_code,created_at), KEY actor_time (actor_id,created_at)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payment_batches (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_code VARCHAR(60) NOT NULL,
			run_id BIGINT UNSIGNED NOT NULL,
			payment_method VARCHAR(24) NOT NULL,
			batch_reference VARCHAR(120) NULL,
			instruction_count INT UNSIGNED NOT NULL DEFAULT 0,
			amount_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(24) NOT NULL DEFAULT 'PREPARED',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			submitted_at DATETIME NULL,
			confirmed_by BIGINT UNSIGNED NULL,
			confirmed_at DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY batch_code (batch_code), KEY run_status (run_id,status), KEY external_ref (batch_reference)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payment_instructions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id BIGINT UNSIGNED NOT NULL,
			run_id BIGINT UNSIGNED NOT NULL,
			line_id BIGINT UNSIGNED NOT NULL,
			employment_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(14,2) NOT NULL,
			currency VARCHAR(8) NOT NULL DEFAULT 'ETB',
			idempotency_key VARCHAR(100) NOT NULL,
			external_reference VARCHAR(120) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'PREPARED',
			last_result_at DATETIME NULL,
			last_result_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY run_line (run_id,line_id), UNIQUE KEY idempotency_key (idempotency_key), KEY batch_status (batch_id,status), KEY external_ref (external_reference)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payment_attempts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			instruction_id BIGINT UNSIGNED NOT NULL,
			attempt_no INT UNSIGNED NOT NULL,
			result VARCHAR(24) NOT NULL,
			external_reference VARCHAR(120) NULL,
			evidence_reference VARCHAR(190) NULL,
			failure_reason VARCHAR(500) NULL,
			recorded_by BIGINT UNSIGNED NOT NULL,
			recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY instruction_attempt (instruction_id,attempt_no), KEY reference_status (external_reference,result)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_statutory_obligations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			obligation_code VARCHAR(60) NOT NULL,
			obligation_type VARCHAR(32) NOT NULL,
			period_key VARCHAR(7) NOT NULL,
			run_id BIGINT UNSIGNED NULL,
			rule_id BIGINT UNSIGNED NULL,
			rule_version VARCHAR(80) NULL,
			amount DECIMAL(14,2) NOT NULL,
			due_date DATE NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY obligation_code (obligation_code), KEY type_period (obligation_type,period_key), KEY due_status (due_date,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_remittances (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			obligation_id BIGINT UNSIGNED NOT NULL,
			remittance_code VARCHAR(60) NOT NULL,
			amount DECIMAL(14,2) NOT NULL,
			submission_reference VARCHAR(120) NULL,
			receipt_reference VARCHAR(190) NULL,
			payment_reference VARCHAR(120) NULL,
			paid_on DATE NULL,
			evidence_reference VARCHAR(190) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			confirmed_by BIGINT UNSIGNED NULL,
			confirmed_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY remittance_code (remittance_code), KEY obligation (obligation_id), KEY status_date (status,paid_on)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_exit_settlements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			settlement_code VARCHAR(60) NOT NULL,
			employment_id BIGINT UNSIGNED NOT NULL,
			exit_date DATE NOT NULL,
			exit_reason VARCHAR(48) NOT NULL,
			last_work_date DATE NULL,
			notice_reference VARCHAR(190) NULL,
			leave_balance_days DECIMAL(8,2) NULL,
			components_json LONGTEXT NOT NULL,
			rule_id BIGINT UNSIGNED NULL,
			rule_version VARCHAR(80) NULL,
			gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			taxable_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			tax_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			deductions_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			payment_reference VARCHAR(120) NULL,
			paid_by BIGINT UNSIGNED NULL,
			paid_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY settlement_code (settlement_code), KEY employment_exit (employment_id,exit_date), KEY status_date (status,exit_date)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payee_engagements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			engagement_code VARCHAR(60) NOT NULL,
			person_id BIGINT UNSIGNED NOT NULL,
			engagement_type VARCHAR(32) NOT NULL,
			campaign_or_contract VARCHAR(190) NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NULL,
			description VARCHAR(1000) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY engagement_code (engagement_code), KEY person_status (person_id,status), KEY engagement_dates (start_date,end_date)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payee_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_code VARCHAR(60) NOT NULL,
			engagement_id BIGINT UNSIGNED NOT NULL,
			deliverable_reference VARCHAR(500) NOT NULL,
			delivered_on DATE NULL,
			accepted_by BIGINT UNSIGNED NULL,
			accepted_at DATETIME NULL,
			cash_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			in_kind_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(8) NOT NULL DEFAULT 'ETB',
			tin_status VARCHAR(40) NULL,
			business_licence_status VARCHAR(40) NULL,
			residency_status VARCHAR(40) NULL,
			classification_status VARCHAR(24) NOT NULL DEFAULT 'NEEDS_REVIEW',
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			submitted_by BIGINT UNSIGNED NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY request_code (request_code), KEY engagement_status (engagement_id,status), KEY classification_status (classification_status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payee_decisions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT UNSIGNED NOT NULL,
			decision VARCHAR(32) NOT NULL,
			rule_id BIGINT UNSIGNED NULL,
			rule_version VARCHAR(80) NULL,
			source_clause VARCHAR(240) NULL,
			basis_note VARCHAR(1200) NOT NULL,
			withholding_rate DECIMAL(9,6) NULL,
			withholding_amount DECIMAL(14,2) NULL,
			reviewer_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY request_history (request_id,id), KEY rule_version (rule_id,rule_version)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_payee_payments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			payment_code VARCHAR(60) NOT NULL,
			request_id BIGINT UNSIGNED NOT NULL,
			gross_amount DECIMAL(14,2) NOT NULL,
			withholding_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			net_amount DECIMAL(14,2) NOT NULL,
			payment_method VARCHAR(24) NOT NULL,
			payment_reference VARCHAR(120) NOT NULL,
			journal_id BIGINT UNSIGNED NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'PREPARED',
			recorded_by BIGINT UNSIGNED NOT NULL,
			confirmed_by BIGINT UNSIGNED NULL,
			confirmed_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY payment_code (payment_code), UNIQUE KEY payment_reference (payment_reference), KEY request_status (request_id,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_training_records (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employment_id BIGINT UNSIGNED NOT NULL,
			training_code VARCHAR(60) NOT NULL,
			title VARCHAR(180) NOT NULL,
			training_type VARCHAR(48) NOT NULL,
			started_on DATE NULL,
			completed_on DATE NULL,
			evidence_reference VARCHAR(190) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'PLANNED',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY employment_training (employment_id,training_code), KEY status_date (status,completed_on)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_performance_reviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employment_id BIGINT UNSIGNED NOT NULL,
			review_period VARCHAR(32) NOT NULL,
			reviewer_id BIGINT UNSIGNED NOT NULL,
			competency_framework_version VARCHAR(80) NULL,
			scores_json LONGTEXT NULL,
			narrative VARCHAR(2000) NULL,
			employee_comments VARCHAR(1200) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), UNIQUE KEY employment_period (employment_id,review_period), KEY reviewer_status (reviewer_id,status)
		) ENGINE=InnoDB {$cc};";
		$d[] = "CREATE TABLE {$p}stock_hris_social_snapshots (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			platform VARCHAR(48) NOT NULL,
			account_label VARCHAR(160) NOT NULL,
			account_owner VARCHAR(160) NULL,
			campaign_code VARCHAR(80) NULL,
			period_from DATE NOT NULL,
			period_to DATE NOT NULL,
			captured_at DATETIME NOT NULL,
			source_reference VARCHAR(500) NOT NULL,
			metrics_json LONGTEXT NOT NULL,
			fees DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			gifted_stock_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			attributed_sales DECIMAL(14,2) NULL,
			staleness_status VARCHAR(24) NOT NULL DEFAULT 'CURRENT',
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id), KEY platform_period (platform,period_from,period_to), KEY campaign (campaign_code), KEY captured_at (captured_at)
		) ENGINE=InnoDB {$cc};";
		return $d;
	}

	public static function is_authority_active() {
		return '1' === (string) get_option( self::OPTION_CUTOVER, '0' );
	}

	public static function table_exists( $key ) {
		global $wpdb;
		if ( ! in_array( $key, self::table_keys(), true ) ) { return false; }
		return (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s', DB_NAME, Stockland_DB::table( $key ) ) ) === 1;
	}

	public static function schema_status() {
		global $wpdb;
		$missing = array();
		$drift = array();
		foreach ( self::table_keys() as $key ) {
			$table = Stockland_DB::table( $key );
			if ( ! self::table_exists( $key ) ) { $missing[] = $key; continue; }
			$known = Stockland_DB::read_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
			foreach ( self::required_columns()[ $key ] as $col ) {
				if ( ! in_array( $col, (array) $known, true ) ) { $drift[] = $key . '.' . $col; }
			}
		}
		return array('version'=>self::SCHEMA_VERSION,'required'=>count(self::table_keys()),'present'=>count(self::table_keys())-count($missing),'missing'=>$missing,'drift'=>$drift,'ready'=>empty($missing)&&empty($drift));
	}

	/** Creates only missing namespaced target tables in small explicit batches. Existing tables are never altered here. */
	public static function install_batch( $offset, $limit = 4 ) {
		global $wpdb;
		$all=self::table_keys(); $offset=max(0,(int)$offset); $limit=max(1,min(4,(int)$limit));
		if($offset>=count($all))return array('processed'=>0,'next_offset'=>$offset,'done'=>true,'schema'=>self::schema_status());
		$ddl=self::ddl($wpdb->prefix,$wpdb->get_charset_collate());
		$processed=0;
		for($i=$offset;$i<min(count($all),$offset+$limit);$i++){
			$key=$all[$i];
			if(self::table_exists($key)){
				$known=Stockland_DB::read_col($wpdb->prepare('SHOW COLUMNS FROM %i',Stockland_DB::table($key)),0);
				foreach(self::required_columns()[$key] as $col)if(!in_array($col,(array)$known,true))throw new RuntimeException('Existing HRIS target table has schema drift: '.$key.'.'.$col.'. Refuse to alter or guess a migration.');
				$processed++;continue;
			}
			$sql=$ddl[$i]??'';
			if(!$sql||false===Stockland_DB::execute_query($sql)||!self::table_exists($key))throw new RuntimeException('Could not create required candidate table '.$key.'.');
			$processed++;
		}
		$next=$offset+$processed;$schema=self::schema_status();
		return array('processed'=>$processed,'next_offset'=>$next,'done'=>$next>=count($all),'schema'=>$schema);
	}

	public static function log_event($entity_type,$entity_id,$event_code,$from_state,$to_state,$details=array(),$approver_id=null) {
		global $wpdb;
		$json=wp_json_encode($details);
		if(!is_string($json))return false;
		$hash=hash('sha256',$entity_type.'|'.$entity_id.'|'.$event_code.'|'.$from_state.'|'.$to_state.'|'.$json);
		$ok=Stockland_DB::insert_record(Stockland_DB::table('hris_events'),array(
			'entity_type'=>sanitize_key($entity_type),'entity_id'=>(int)$entity_id,'event_code'=>sanitize_key($event_code),
			'from_state'=>$from_state!==null?sanitize_key($from_state):null,'to_state'=>$to_state!==null?sanitize_key($to_state):null,
			'actor_id'=>get_current_user_id(),'approver_id'=>$approver_id,'reference_hash'=>$hash,'details_json'=>$json
		));
		if(false===$ok)return false;
		return Stockland_Audit::log(get_current_user_id(),null,'HRIS_'.strtoupper(sanitize_key($event_code)),null,null,$from_state,$to_state,
			'HRIS '.$entity_type.' '.$entity_id.' state event; details SHA-256 '.$hash,
			sanitize_key($entity_type).':'.(int)$entity_id,$approver_id);
	}

	public static function approved_rule($type,$date) {
		global $wpdb;
		$r=Stockland_DB::read_row($wpdb->prepare(
			"SELECT * FROM %i WHERE rule_type=%s AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) ORDER BY effective_from DESC LIMIT 2",
			Stockland_DB::table('hris_legal_rules'),strtoupper(sanitize_key($type)),$date,$date),ARRAY_A);
		if(!$r)return new WP_Error('hris_rule_missing','No approved effective-dated '.$type.' rule exists for '.$date.'. Statutory finalization is blocked.',array('status'=>409));
		$other=Stockland_DB::read_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE rule_type=%s AND review_status='APPROVED' AND effective_from<=%s AND (effective_to IS NULL OR effective_to>=%s) AND id<>%d",Stockland_DB::table('hris_legal_rules'),strtoupper(sanitize_key($type)),$date,$date,(int)$r['id']));
		if((int)$other>0)return new WP_Error('hris_rule_overlap','Overlapping approved effective-dated '.$type.' rules exist. Finalization is blocked.',array('status'=>409));
		return $r;
	}

	public static function rule_components($rule_id) {
		global $wpdb;
		$r=Stockland_DB::read_results($wpdb->prepare('SELECT * FROM %i WHERE rule_id=%d ORDER BY lower_bound,id',Stockland_DB::table('hris_legal_rule_components'),(int)$rule_id),ARRAY_A);
		return is_array($r)?$r:array();
	}

	/** Progressive marginal band calculation; rounding/edge behavior is covered by test vectors. */
	public static function progressive_tax($taxable,$components) {
		$taxable=Stockland_Money::decimal(Stockland_Money::minor($taxable));$sum='0.00';$seen=false;$credit='0.00';
		$base=Stockland_Money::minor($taxable);
		foreach((array)$components as $b){
			if(!is_numeric($b['rate']??null))continue;
			$lower=isset($b['lower_bound'])?Stockland_Money::minor($b['lower_bound']):0;
			$upper=isset($b['upper_bound'])&&$b['upper_bound']!==null?Stockland_Money::minor($b['upper_bound']):null;
			$portionMinor=max(0,$base-$lower);
			if($upper!==null)$portionMinor=min($portionMinor,max(0,$upper-$lower));
			if($portionMinor<=0)continue;
			$portion=Stockland_Money::decimal($portionMinor);
			$sum=Stockland_Money::add($sum,Stockland_Money::percentage($portion,(string)$b['rate']));
			$seen=true;
			// A fixed credit belongs to the selected marginal schedule; never subtract it once per band.
			if($base>=$lower && ($upper===null || $base<=$upper) && isset($b['fixed_credit'])&&$b['fixed_credit']!==null) {
				$credit=Stockland_Money::decimal(Stockland_Money::minor($b['fixed_credit']));
			}
		}
		if(!$seen&&$base>0)return new WP_Error('hris_tax_band_gap','Approved PAYE rules contain no applicable band for taxable income.',array('status'=>409));
		$sum=Stockland_Money::subtract($sum,$credit);
		return Stockland_Money::minor($sum)<0?'0.00':$sum;
	}
}
