<?php
/**
 * Candidate HR/payroll replacement schema. It never modifies the legacy HR tables.
 * Physical table boundaries remain subject to the Notion v0.5 dependency/data-health gate.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_HR_V2_Schema {
	const VERSION = '0.1.0-candidate';
	const OPTION = 'stockland_hr_v2_schema_version';
	const BATCH_LIMIT = 5;

	public static function table_keys() {
		return array(
			'hrv2_people','hrv2_positions','hrv2_workforce_plans','hrv2_vacancies',
			'hrv2_applications','hrv2_interviews','hrv2_employments','hrv2_contracts',
			'hrv2_documents','hrv2_onboarding_tasks','hrv2_schedules','hrv2_time_records',
			'hrv2_leave_policies','hrv2_leave_requests','hrv2_leave_ledger',
			'hrv2_compensation_assignments','hrv2_compensation_items',
			'hrv2_rule_sets','hrv2_rule_components','hrv2_rule_bands',
			'hrv2_payroll_runs','hrv2_payroll_employee_runs','hrv2_payroll_line_items',
			'hrv2_payout_batches','hrv2_payout_items','hrv2_remittances','hrv2_exit_settlements',
			'hrv2_payee_profiles','hrv2_payee_engagements','hrv2_payee_requests',
			'hrv2_social_accounts','hrv2_social_snapshots','hrv2_training_records',
			'hrv2_performance_reviews','hrv2_employee_events',
			'hrv2_migration_map','hrv2_migration_runs','hrv2_data_access_events','hrv2_audit_events',
		);
	}

	private static function ddl( $p, $cc ) {
		$tables = array();
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_people (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, person_code VARCHAR(48) NOT NULL,
			person_type VARCHAR(30) NOT NULL DEFAULT 'CANDIDATE', first_name VARCHAR(100) NOT NULL,
			middle_name VARCHAR(100) NULL, last_name VARCHAR(100) NOT NULL, preferred_name VARCHAR(100) NULL,
			email VARCHAR(190) NULL, phone VARCHAR(50) NULL, privacy_status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
			retention_until DATE NULL, legacy_employee_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY person_code (person_code), KEY person_type (person_type), KEY email (email),
			KEY retention_until (retention_until), KEY legacy_employee_id (legacy_employee_id), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_positions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, position_code VARCHAR(48) NOT NULL, title VARCHAR(190) NOT NULL,
			department VARCHAR(120) NOT NULL, grade_code VARCHAR(48) NULL, location_id BIGINT UNSIGNED NULL,
			reports_to_position_id BIGINT UNSIGNED NULL, budgeted_headcount INT UNSIGNED NOT NULL DEFAULT 1,
			budget_min DECIMAL(14,2) NULL, budget_max DECIMAL(14,2) NULL, employment_type VARCHAR(30) NOT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY position_code (position_code), KEY location_id (location_id), KEY department (department),
			KEY status (status), KEY reports_to_position_id (reports_to_position_id)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_workforce_plans (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, plan_code VARCHAR(48) NOT NULL, fiscal_year VARCHAR(16) NOT NULL,
			department VARCHAR(120) NOT NULL, location_id BIGINT UNSIGNED NULL, planned_headcount INT UNSIGNED NOT NULL,
			approved_budget DECIMAL(14,2) NULL, justification TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY plan_code (plan_code), KEY fiscal_year (fiscal_year), KEY location_id (location_id), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_vacancies (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, vacancy_code VARCHAR(48) NOT NULL, plan_id BIGINT UNSIGNED NULL,
			position_id BIGINT UNSIGNED NOT NULL, openings INT UNSIGNED NOT NULL DEFAULT 1, budget_amount DECIMAL(14,2) NULL,
			open_date DATE NULL, close_date DATE NULL, location_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL, closed_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY vacancy_code (vacancy_code), KEY plan_id (plan_id), KEY position_id (position_id),
			KEY location_id (location_id), KEY status (status), KEY close_date (close_date)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_applications (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, application_code VARCHAR(48) NOT NULL,
			vacancy_id BIGINT UNSIGNED NOT NULL, person_id BIGINT UNSIGNED NOT NULL, source VARCHAR(60) NULL,
			stage VARCHAR(32) NOT NULL DEFAULT 'DRAFT', privacy_notice_version VARCHAR(80) NOT NULL,
			retention_until DATE NULL, human_decision VARCHAR(24) NULL, selection_rationale TEXT NULL,
			location_id BIGINT UNSIGNED NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
			decision_by BIGINT UNSIGNED NULL, decision_at DATETIME NULL, created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY application_code (application_code),
			UNIQUE KEY vacancy_person (vacancy_id,person_id), KEY person_id (person_id), KEY stage (stage),
			KEY location_id (location_id), KEY retention_until (retention_until)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_interviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, application_id BIGINT UNSIGNED NOT NULL,
			panel_user_id BIGINT UNSIGNED NOT NULL, interview_stage VARCHAR(40) NOT NULL, interview_at DATETIME NULL,
			rubric_version VARCHAR(80) NOT NULL, score_total DECIMAL(8,2) NULL, recommendation VARCHAR(24) NOT NULL DEFAULT 'PENDING',
			evidence_reference VARCHAR(190) NULL, notes TEXT NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY application_id (application_id), KEY panel_user_id (panel_user_id), KEY recommendation (recommendation)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_employments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_code VARCHAR(48) NOT NULL, person_id BIGINT UNSIGNED NOT NULL,
			legacy_employee_id BIGINT UNSIGNED NULL, position_id BIGINT UNSIGNED NOT NULL, manager_person_id BIGINT UNSIGNED NULL,
			legal_employer VARCHAR(190) NOT NULL, location_id BIGINT UNSIGNED NOT NULL, employment_type VARCHAR(30) NOT NULL,
			start_date DATE NOT NULL, end_date DATE NULL, probation_end_date DATE NULL, status VARCHAR(32) NOT NULL DEFAULT 'PENDING_ACTIVATION',
			payroll_setup_status VARCHAR(24) NOT NULL DEFAULT 'NOT_READY', access_provision_status VARCHAR(24) NOT NULL DEFAULT 'NOT_REQUESTED',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY employment_code (employment_code), KEY person_id (person_id), KEY legacy_employee_id (legacy_employee_id),
			KEY position_id (position_id), KEY location_id (location_id), KEY status (status), KEY date_range (start_date,end_date)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_contracts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, contract_code VARCHAR(48) NOT NULL,
			contract_type VARCHAR(32) NOT NULL, start_date DATE NOT NULL, end_date DATE NULL, probation_end_date DATE NULL,
			terms_json LONGTEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', signed_at DATETIME NULL,
			document_id BIGINT UNSIGNED NULL, supersedes_contract_id BIGINT UNSIGNED NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY contract_code (contract_code), KEY employment_id (employment_id),
			KEY status (status), KEY start_date (start_date), KEY end_date (end_date)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, subject_type VARCHAR(40) NOT NULL, subject_id BIGINT UNSIGNED NOT NULL,
			document_type VARCHAR(60) NOT NULL, private_vault_ref VARCHAR(190) NOT NULL, document_sha256 CHAR(64) NOT NULL,
			access_class VARCHAR(24) NOT NULL DEFAULT 'HR_RESTRICTED', issued_on DATE NULL, expires_on DATE NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'PENDING_REVIEW', verified_by BIGINT UNSIGNED NULL, verified_at DATETIME NULL,
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY subject (subject_type,subject_id), KEY document_type (document_type),
			KEY document_sha256 (document_sha256), KEY status (status), KEY expires_on (expires_on)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_onboarding_tasks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, task_code VARCHAR(48) NOT NULL,
			title VARCHAR(190) NOT NULL, owner_role VARCHAR(48) NOT NULL, due_date DATE NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'OPEN', evidence_reference VARCHAR(190) NULL, completed_by BIGINT UNSIGNED NULL,
			completed_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY emp_task (employment_id,task_code),
			KEY status (status), KEY due_date (due_date)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_schedules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, schedule_code VARCHAR(48) NOT NULL, location_id BIGINT UNSIGNED NOT NULL,
			start_date DATE NOT NULL, end_date DATE NULL, work_pattern VARCHAR(80) NOT NULL, time_zone VARCHAR(80) NOT NULL DEFAULT 'Africa/Addis_Ababa',
			break_minutes INT UNSIGNED NOT NULL DEFAULT 0, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			rule_set_id BIGINT UNSIGNED NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
			approved_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY schedule_code (schedule_code), KEY location_id (location_id), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_time_records (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, schedule_id BIGINT UNSIGNED NULL,
			work_date DATE NOT NULL, record_type VARCHAR(32) NOT NULL, minutes INT UNSIGNED NOT NULL DEFAULT 0,
			start_at DATETIME NULL, end_at DATETIME NULL, source_reference VARCHAR(190) NULL, exception_code VARCHAR(48) NULL,
			reason TEXT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', correction_of_id BIGINT UNSIGNED NULL,
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), KEY employment_date (employment_id,work_date),
			KEY schedule_id (schedule_id), KEY status (status), KEY correction_of_id (correction_of_id)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_leave_policies (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, policy_code VARCHAR(48) NOT NULL, leave_type VARCHAR(48) NOT NULL,
			title VARCHAR(190) NOT NULL, paid_status VARCHAR(24) NOT NULL, eligibility_rule_reference VARCHAR(190) NOT NULL,
			accrual_units_per_period DECIMAL(10,4) NULL, carry_forward_limit DECIMAL(10,4) NULL,
			effective_from DATE NOT NULL, effective_to DATE NULL, legal_source_reference VARCHAR(190) NULL,
			legal_source_sha256 CHAR(64) NULL, approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY policy_code (policy_code),
			KEY type_effective (leave_type,effective_from,effective_to), KEY approval_status (approval_status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_leave_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, policy_id BIGINT UNSIGNED NOT NULL,
			start_date DATE NOT NULL, end_date DATE NOT NULL, units DECIMAL(10,4) NOT NULL, reason TEXT NULL,
			evidence_document_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, decision_by BIGINT UNSIGNED NULL, decision_at DATETIME NULL, decision_note TEXT NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), KEY employment_id (employment_id),
			KEY policy_id (policy_id), KEY date_range (start_date,end_date), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_leave_ledger (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, policy_id BIGINT UNSIGNED NOT NULL,
			transaction_code VARCHAR(48) NOT NULL, transaction_type VARCHAR(24) NOT NULL, units_delta DECIMAL(10,4) NOT NULL,
			effective_on DATE NOT NULL, source_type VARCHAR(40) NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
			balance_after DECIMAL(10,4) NOT NULL, reason VARCHAR(500) NULL, idempotency_key VARCHAR(96) NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY transaction_code (transaction_code), UNIQUE KEY idempotency_key (idempotency_key),
			KEY employment_policy_date (employment_id,policy_id,effective_on), KEY source (source_type,source_id)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_compensation_assignments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, assignment_code VARCHAR(48) NOT NULL, employment_id BIGINT UNSIGNED NOT NULL,
			grade_code VARCHAR(48) NULL, currency CHAR(3) NOT NULL DEFAULT 'ETB', base_salary DECIMAL(14,2) NOT NULL,
			effective_from DATE NOT NULL, effective_to DATE NULL, approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			statutory_rule_set_id BIGINT UNSIGNED NULL, justification TEXT NOT NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY assignment_code (assignment_code),
			KEY employment_effective (employment_id,effective_from,effective_to), KEY approval_status (approval_status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_compensation_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, assignment_id BIGINT UNSIGNED NOT NULL, item_code VARCHAR(64) NOT NULL,
			amount DECIMAL(14,2) NOT NULL, source_reference VARCHAR(190) NOT NULL, evidence_reference VARCHAR(190) NULL,
			basis_reference VARCHAR(190) NULL, effective_from DATE NOT NULL, effective_to DATE NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY assignment_item (assignment_id,item_code,effective_from), KEY item_code (item_code), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_rule_sets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, rule_code VARCHAR(64) NOT NULL, version VARCHAR(48) NOT NULL,
			jurisdiction VARCHAR(80) NOT NULL DEFAULT 'ET-FEDERAL', instrument_title VARCHAR(190) NOT NULL,
			article_reference VARCHAR(190) NOT NULL, source_reference VARCHAR(500) NOT NULL, source_sha256 CHAR(64) NOT NULL,
			publication_date DATE NULL, effective_from DATE NOT NULL, effective_to DATE NULL,
			taxable_base_mode VARCHAR(48) NOT NULL, tax_rounding_mode VARCHAR(32) NOT NULL,
			employee_pension_rate_bps INT UNSIGNED NOT NULL, employer_pension_rate_bps INT UNSIGNED NOT NULL,
			reviewer_qualification VARCHAR(80) NOT NULL DEFAULT 'UNVERIFIED', reviewer_id BIGINT UNSIGNED NULL,
			reviewed_at DATETIME NULL, approval_reference VARCHAR(190) NULL, test_vectors_passed TINYINT(1) NOT NULL DEFAULT 0,
			effective_dates_verified TINYINT(1) NOT NULL DEFAULT 0, approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY rule_version (rule_code,version), KEY effective_scope (jurisdiction,effective_from,effective_to),
			KEY approval_status (approval_status), KEY source_sha256 (source_sha256)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_rule_components (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, rule_set_id BIGINT UNSIGNED NOT NULL, item_code VARCHAR(64) NOT NULL,
			kind VARCHAR(24) NOT NULL, tax_treatment VARCHAR(32) NOT NULL, pension_treatment VARCHAR(32) NOT NULL,
			classification_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', evidence_required TINYINT(1) NOT NULL DEFAULT 1,
			exemption_cap DECIMAL(14,2) NULL, source_reference VARCHAR(190) NOT NULL, source_sha256 CHAR(64) NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY rule_item (rule_set_id,item_code), KEY classification_status (classification_status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_rule_bands (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, rule_set_id BIGINT UNSIGNED NOT NULL, band_order INT UNSIGNED NOT NULL,
			lower_minor BIGINT UNSIGNED NOT NULL, upper_minor BIGINT UNSIGNED NULL, rate_bps INT UNSIGNED NOT NULL,
			source_reference VARCHAR(190) NOT NULL, source_sha256 CHAR(64) NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY rule_band_order (rule_set_id,band_order),
			KEY rule_set_lower (rule_set_id,lower_minor)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payroll_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, run_code VARCHAR(48) NOT NULL, period_start DATE NOT NULL,
			period_end DATE NOT NULL, run_kind VARCHAR(24) NOT NULL DEFAULT 'REGULAR', rule_set_id BIGINT UNSIGNED NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'DRAFT', cutoff_at DATETIME NULL, input_snapshot_sha256 CHAR(64) NULL,
			calculation_sha256 CHAR(64) NULL, employee_count INT UNSIGNED NOT NULL DEFAULT 0,
			gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, tax_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employee_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, employer_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			other_deductions_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employer_cost_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, journal_id BIGINT UNSIGNED NULL,
			idempotency_key VARCHAR(96) NOT NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, submitted_by BIGINT UNSIGNED NULL, approved_by BIGINT UNSIGNED NULL,
			posted_at DATETIME NULL, closed_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY run_code (run_code), UNIQUE KEY idempotency_key (idempotency_key),
			UNIQUE KEY period_kind_version (period_start,period_end,run_kind,version_no), KEY status (status), KEY rule_set_id (rule_set_id)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payroll_employee_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, run_id BIGINT UNSIGNED NOT NULL, employment_id BIGINT UNSIGNED NOT NULL,
			input_snapshot LONGTEXT NOT NULL, calculation_snapshot LONGTEXT NULL, calculation_sha256 CHAR(64) NULL,
			gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, taxable_income DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			payroll_tax DECIMAL(14,2) NOT NULL DEFAULT 0.00, employee_pension DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			employer_pension DECIMAL(14,2) NOT NULL DEFAULT 0.00, other_deductions DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			net_payable DECIMAL(14,2) NOT NULL DEFAULT 0.00, employer_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY run_employment (run_id,employment_id),
			KEY employment_id (employment_id), KEY status (status), KEY calculation_sha256 (calculation_sha256)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payroll_line_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_run_id BIGINT UNSIGNED NOT NULL, line_no INT UNSIGNED NOT NULL,
			item_code VARCHAR(64) NOT NULL, description VARCHAR(190) NOT NULL, kind VARCHAR(24) NOT NULL,
			amount DECIMAL(14,2) NOT NULL, tax_treatment VARCHAR(32) NOT NULL, pension_treatment VARCHAR(32) NOT NULL,
			rule_version VARCHAR(48) NOT NULL, source_reference VARCHAR(190) NOT NULL, evidence_reference VARCHAR(190) NULL,
			calculation_detail LONGTEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY employee_line (employee_run_id,line_no), KEY item_code (item_code), KEY rule_version (rule_version)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payout_batches (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, batch_code VARCHAR(48) NOT NULL, payroll_run_id BIGINT UNSIGNED NOT NULL,
			payment_method VARCHAR(24) NOT NULL, external_batch_reference VARCHAR(190) NULL, status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
			total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, item_count INT UNSIGNED NOT NULL DEFAULT 0,
			idempotency_key VARCHAR(96) NOT NULL, submitted_by BIGINT UNSIGNED NULL, approved_by BIGINT UNSIGNED NULL,
			sent_at DATETIME NULL, reconciled_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY batch_code (batch_code), UNIQUE KEY idempotency_key (idempotency_key), KEY payroll_run_id (payroll_run_id), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payout_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, batch_id BIGINT UNSIGNED NOT NULL, employee_run_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(14,2) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'READY_FOR_PAYMENT',
			external_payment_reference VARCHAR(190) NULL, confirmed_amount DECIMAL(14,2) NULL,
			failure_reason VARCHAR(500) NULL, attempt_count INT UNSIGNED NOT NULL DEFAULT 0, last_attempt_at DATETIME NULL,
			confirmed_by BIGINT UNSIGNED NULL, confirmed_at DATETIME NULL, idempotency_key VARCHAR(96) NOT NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id),
			UNIQUE KEY batch_employee (batch_id,employee_run_id), UNIQUE KEY idempotency_key (idempotency_key),
			UNIQUE KEY external_payment_reference (external_payment_reference), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_remittances (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, obligation_code VARCHAR(48) NOT NULL, payroll_run_id BIGINT UNSIGNED NOT NULL,
			obligation_type VARCHAR(32) NOT NULL, amount DECIMAL(14,2) NOT NULL, due_date DATE NULL,
			rule_set_id BIGINT UNSIGNED NOT NULL, filing_reference VARCHAR(190) NULL, payment_reference VARCHAR(190) NULL,
			receipt_document_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'OUTSTANDING',
			paid_at DATETIME NULL, reconciled_by BIGINT UNSIGNED NULL, reconciled_at DATETIME NULL,
			created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY obligation_code (obligation_code), KEY payroll_run_id (payroll_run_id),
			KEY type_due (obligation_type,due_date), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_exit_settlements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, settlement_code VARCHAR(48) NOT NULL, employment_id BIGINT UNSIGNED NOT NULL,
			exit_type VARCHAR(40) NOT NULL, exit_reason TEXT NOT NULL, notice_date DATE NULL, last_work_date DATE NOT NULL,
			legal_basis_reference VARCHAR(190) NOT NULL, rule_set_id BIGINT UNSIGNED NULL, leave_balance_snapshot DECIMAL(12,4) NULL,
			components_snapshot LONGTEXT NULL, gross_amount DECIMAL(14,2) NULL, deductions_amount DECIMAL(14,2) NULL,
			net_amount DECIMAL(14,2) NULL, status VARCHAR(32) NOT NULL DEFAULT 'DRAFT', clearance_complete TINYINT(1) NOT NULL DEFAULT 0,
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, submitted_by BIGINT UNSIGNED NULL,
			approved_by BIGINT UNSIGNED NULL, payout_item_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY settlement_code (settlement_code), KEY employment_id (employment_id), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payee_profiles (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, person_id BIGINT UNSIGNED NOT NULL, payee_type VARCHAR(32) NOT NULL,
			tax_identifier_ref VARCHAR(190) NULL, license_reference VARCHAR(190) NULL, residency_status VARCHAR(48) NULL,
			registration_status VARCHAR(48) NULL, classification_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_TAX_REVIEW',
			classification_rule_set_id BIGINT UNSIGNED NULL, classification_reason TEXT NULL, status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, reviewed_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY person_type (person_id,payee_type),
			KEY classification_status (classification_status), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payee_engagements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, engagement_code VARCHAR(48) NOT NULL, payee_profile_id BIGINT UNSIGNED NOT NULL,
			engagement_type VARCHAR(32) NOT NULL, counterparty VARCHAR(190) NULL, campaign_code VARCHAR(64) NULL,
			contract_reference VARCHAR(190) NULL, description TEXT NOT NULL, deliverable_due DATE NULL,
			cash_value DECIMAL(14,2) NOT NULL DEFAULT 0.00, in_kind_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			currency CHAR(3) NOT NULL DEFAULT 'ETB', status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', accepted_by BIGINT UNSIGNED NULL,
			accepted_at DATETIME NULL, evidence_reference VARCHAR(190) NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY engagement_code (engagement_code), KEY payee_profile_id (payee_profile_id), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_payee_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, request_code VARCHAR(48) NOT NULL, engagement_id BIGINT UNSIGNED NOT NULL,
			invoice_reference VARCHAR(190) NULL, requested_amount DECIMAL(14,2) NOT NULL, withholding_amount DECIMAL(14,2) NULL,
			withholding_rule_reference VARCHAR(190) NULL, classification_decision_reference VARCHAR(190) NULL,
			evidence_reference VARCHAR(190) NULL, status VARCHAR(32) NOT NULL DEFAULT 'DRAFT', finance_note TEXT NULL,
			shared_ap_reference VARCHAR(190) NULL, payment_reference VARCHAR(190) NULL, paid_amount DECIMAL(14,2) NULL,
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY request_code (request_code),
			KEY engagement_id (engagement_id), KEY status (status), KEY shared_ap_reference (shared_ap_reference)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_social_accounts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, account_code VARCHAR(48) NOT NULL, platform VARCHAR(32) NOT NULL,
			account_name VARCHAR(190) NOT NULL, profile_url VARCHAR(500) NULL, owner_user_id BIGINT UNSIGNED NULL,
			managed_by_user_id BIGINT UNSIGNED NULL, permission_basis VARCHAR(190) NULL, status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
			created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY account_code (account_code), KEY platform (platform), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_social_snapshots (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, account_id BIGINT UNSIGNED NOT NULL, campaign_code VARCHAR(64) NULL,
			period_start DATE NOT NULL, period_end DATE NOT NULL, captured_at DATETIME NOT NULL, source_reference VARCHAR(500) NOT NULL,
			views BIGINT UNSIGNED NULL, reach BIGINT UNSIGNED NULL, engagements BIGINT UNSIGNED NULL, link_clicks BIGINT UNSIGNED NULL,
			attributed_sales DECIMAL(14,2) NULL, campaign_fee DECIMAL(14,2) NULL, gifted_stock_value DECIMAL(14,2) NULL,
			conversions INT UNSIGNED NULL, returns_value DECIMAL(14,2) NULL, stale_status VARCHAR(24) NOT NULL DEFAULT 'CURRENT',
			notes VARCHAR(1000) NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY account_id (account_id), KEY reporting_period (period_start,period_end), KEY captured_at (captured_at)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_training_records (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, course_code VARCHAR(48) NOT NULL,
			title VARCHAR(190) NOT NULL, required_flag TINYINT(1) NOT NULL DEFAULT 0, assigned_on DATE NULL,
			due_date DATE NULL, completed_on DATE NULL, evidence_reference VARCHAR(190) NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'ASSIGNED', created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL, PRIMARY KEY (id), KEY employment_id (employment_id), KEY due_date (due_date), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_performance_reviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, review_period VARCHAR(24) NOT NULL,
			reviewer_user_id BIGINT UNSIGNED NOT NULL, rubric_version VARCHAR(80) NOT NULL, overall_rating DECIMAL(5,2) NULL,
			summary TEXT NULL, development_plan TEXT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY employment_period (employment_id,review_period), KEY reviewer_user_id (reviewer_user_id), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_employee_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(48) NOT NULL,
			event_type VARCHAR(32) NOT NULL, effective_on DATE NOT NULL, old_snapshot LONGTEXT NULL, new_snapshot LONGTEXT NULL,
			reason TEXT NOT NULL, source_document_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
			version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY event_code (event_code),
			KEY employment_effective (employment_id,effective_on), KEY event_type (event_type), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_migration_map (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, run_id BIGINT UNSIGNED NOT NULL, source_table VARCHAR(64) NOT NULL,
			source_id BIGINT UNSIGNED NOT NULL, target_entity VARCHAR(64) NOT NULL, target_id BIGINT UNSIGNED NULL,
			source_fingerprint CHAR(64) NOT NULL, disposition VARCHAR(24) NOT NULL DEFAULT 'PENDING_REVIEW',
			review_note VARCHAR(500) NULL, reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY source_identity (source_table,source_id), KEY run_id (run_id),
			KEY target_identity (target_entity,target_id), KEY disposition (disposition)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_migration_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, run_code VARCHAR(48) NOT NULL, source_release VARCHAR(24) NOT NULL,
			source_manifest_sha256 CHAR(64) NOT NULL, mode VARCHAR(24) NOT NULL DEFAULT 'DRY_RUN', status VARCHAR(24) NOT NULL DEFAULT 'PLANNED',
			source_counts_json LONGTEXT NULL, reconciliation_json LONGTEXT NULL, started_by BIGINT UNSIGNED NOT NULL,
			approved_by BIGINT UNSIGNED NULL, started_at DATETIME NOT NULL, finished_at DATETIME NULL, rollback_reference VARCHAR(190) NULL,
			PRIMARY KEY (id), UNIQUE KEY run_code (run_code), KEY status (status)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_data_access_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, actor_user_id BIGINT UNSIGNED NOT NULL, action_code VARCHAR(48) NOT NULL,
			resource_type VARCHAR(48) NOT NULL, resource_id BIGINT UNSIGNED NULL, field_group VARCHAR(48) NULL,
			location_id BIGINT UNSIGNED NULL, request_id VARCHAR(96) NULL, occurred_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY actor_time (actor_user_id,occurred_at), KEY resource (resource_type,resource_id),
			KEY action_code (action_code), KEY location_id (location_id)
		) ENGINE=InnoDB {$cc};";
		$tables[] = "CREATE TABLE IF NOT EXISTS {$p}stock_hrv2_audit_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, aggregate_type VARCHAR(48) NOT NULL, aggregate_id BIGINT UNSIGNED NOT NULL,
			event_key VARCHAR(80) NOT NULL, from_status VARCHAR(32) NULL, to_status VARCHAR(32) NULL,
			actor_user_id BIGINT UNSIGNED NOT NULL, approver_user_id BIGINT UNSIGNED NULL, aggregate_version INT UNSIGNED NOT NULL,
			reference_code VARCHAR(190) NULL, payload_sha256 CHAR(64) NOT NULL, created_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY aggregate_history (aggregate_type,aggregate_id,id), KEY event_key (event_key),
			KEY actor_time (actor_user_id,created_at), KEY reference_code (reference_code)
		) ENGINE=InnoDB {$cc};";
		return $tables;
	}

	public static function status() {
		global $wpdb;
		$missing = array();
		$present = array();
		foreach ( self::table_keys() as $key ) {
			$table = $wpdb->prefix . 'stock_' . $key;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s', DB_NAME, $table ) );
			if ( (int) $exists > 0 ) { $present[] = $key; } else { $missing[] = $key; }
		}
		return array(
			'version' => get_option( self::OPTION, '' ),
			'candidate_version' => self::VERSION,
			'expected_tables' => count( self::table_keys() ),
			'present_tables' => count( $present ),
			'missing_tables' => $missing,
			'ready' => empty( $missing ) && get_option( self::OPTION, '' ) === self::VERSION,
			'legacy_tables_untouched_by_design' => true,
			'cutover_approved' => false,
		);
	}

	/** Create at most five new tables. No legacy table is altered or dropped. */
	public static function install_batch( $cursor = 0, $limit = self::BATCH_LIMIT ) {
		global $wpdb;
		$limit = max( 1, min( self::BATCH_LIMIT, (int) $limit ) );
		$all = self::ddl( $wpdb->prefix, $wpdb->get_charset_collate() );
		$keys = self::table_keys();
		if ( count( $all ) !== count( $keys ) ) {
			throw new RuntimeException( 'HR replacement DDL/table registry mismatch.' );
		}
		$cursor = max( 0, (int) $cursor );
		if ( $cursor >= count( $all ) ) {
			update_option( self::OPTION, self::VERSION, false );
			return array( 'created_or_verified' => 0, 'next_cursor' => count( $all ), 'complete' => true, 'schema' => self::status() );
		}
		$slice = array_slice( $all, $cursor, $limit );
		$created = 0;
		foreach ( $slice as $sql ) {
			if ( false === $wpdb->query( $sql ) || ! empty( $wpdb->last_error ) ) {
				throw new RuntimeException( 'HR replacement schema creation failed; no legacy table was modified.' );
			}
			++$created;
		}
		$next = $cursor + count( $slice );
		$complete = $next >= count( $all );
		if ( $complete ) {
			$verified = self::status();
			if ( ! empty( $verified['missing_tables'] ) ) {
				throw new RuntimeException( 'HR replacement schema verification failed: ' . implode( ', ', $verified['missing_tables'] ) );
			}
			update_option( self::OPTION, self::VERSION, false );
			$verified = self::status();
		}
		return array( 'created_or_verified' => $created, 'next_cursor' => $next, 'complete' => $complete, 'schema' => self::status() );
	}
}
