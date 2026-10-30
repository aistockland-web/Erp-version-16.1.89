<?php
/**
 * New HR/payroll replacement domain model. Legacy HR tables are read-only migration sources.
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** HRX owns new normalized people/workforce/time/leave/compensation/payroll/settlement records. */
final class Stockland_HR_Replacement {
	const SCHEMA_VERSION = '1.0.0';
	const OPTION_SCHEMA_VERSION = 'stockland_hrx_schema_version';

	public static function table_keys() {
		return array(
			'hrx_people','hrx_org_units','hrx_positions','hrx_workforce_plans','hrx_requisitions',
			'hrx_applications','hrx_interviews','hrx_offers','hrx_employments','hrx_contracts',
			'hrx_onboarding_tasks','hrx_assignments','hrx_schedules','hrx_time_events',
			'hrx_leave_policies','hrx_leave_transactions','hrx_compensation_assignments',
			'hrx_compensation_items','hrx_legal_rules','hrx_legal_rule_bands','hrx_payroll_runs',
			'hrx_payroll_inputs','hrx_payroll_lines','hrx_payroll_line_items','hrx_payout_batches',
			'hrx_payout_items','hrx_payout_attempts','hrx_statutory_obligations','hrx_remittances',
			'hrx_accounting_links','hrx_payee_engagements','hrx_payee_requests','hrx_payee_deliverables',
			'hrx_payee_classifications','hrx_document_refs','hrx_document_blobs','hrx_approvals','hrx_social_snapshots',
			'hrx_accounting_config','hrx_exit_settlements','hrx_exit_items','hrx_exit_clearance_tasks','hrx_exit_payouts','hrx_exit_payout_attempts','hrx_payee_payments','hrx_payee_payment_attempts','hrx_legacy_mappings'
		);
	}

	/** Each table has typed, queryable business columns; no legacy table DDL is included. */
	private static function ddl() {
		global $wpdb;
		$p = $wpdb->prefix . 'stock_';
		$c = $wpdb->get_charset_collate();
		$defs = array(
			'hrx_people' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, person_code VARCHAR(40) NOT NULL, first_name VARCHAR(100) NOT NULL, middle_name VARCHAR(100) NULL, last_name VARCHAR(100) NOT NULL, preferred_name VARCHAR(120) NULL, phone VARCHAR(60) NULL, email VARCHAR(190) NULL, privacy_state VARCHAR(24) NOT NULL DEFAULT 'ACTIVE', source_record VARCHAR(80) NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, PRIMARY KEY (id), UNIQUE KEY person_code (person_code), KEY person_name (last_name,first_name), KEY person_email (email)",
			'hrx_org_units' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, unit_code VARCHAR(40) NOT NULL, unit_name VARCHAR(150) NOT NULL, unit_type VARCHAR(30) NOT NULL, parent_id BIGINT UNSIGNED NULL, location_id BIGINT UNSIGNED NULL, manager_employment_id BIGINT UNSIGNED NULL, active TINYINT(1) NOT NULL DEFAULT 1, effective_from DATE NOT NULL, effective_to DATE NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY unit_code (unit_code), KEY unit_parent (parent_id), KEY unit_location (location_id), KEY unit_effective (effective_from,effective_to)",
			'hrx_positions' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, position_code VARCHAR(40) NOT NULL, title VARCHAR(160) NOT NULL, org_unit_id BIGINT UNSIGNED NOT NULL, grade_code VARCHAR(50) NULL, band_min DECIMAL(14,2) NULL, band_max DECIMAL(14,2) NULL, openings_planned INT UNSIGNED NOT NULL DEFAULT 0, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', effective_from DATE NOT NULL, effective_to DATE NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, PRIMARY KEY (id), UNIQUE KEY position_code (position_code), KEY position_unit_status (org_unit_id,status)",
			'hrx_workforce_plans' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, plan_code VARCHAR(40) NOT NULL, fiscal_year VARCHAR(16) NOT NULL, org_unit_id BIGINT UNSIGNED NOT NULL, position_id BIGINT UNSIGNED NULL, headcount_requested INT UNSIGNED NOT NULL DEFAULT 0, budget_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, justification TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY plan_code (plan_code), KEY plan_year_unit (fiscal_year,org_unit_id,status)",
			'hrx_requisitions' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, requisition_code VARCHAR(40) NOT NULL, workforce_plan_id BIGINT UNSIGNED NULL, position_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NULL, openings INT UNSIGNED NOT NULL DEFAULT 1, requested_start DATE NULL, budget_amount DECIMAL(14,2) NULL, business_reason TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY requisition_code (requisition_code), KEY requisition_status (status,requested_start), KEY requisition_position (position_id)",
			'hrx_applications' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, application_code VARCHAR(40) NOT NULL, requisition_id BIGINT UNSIGNED NOT NULL, person_id BIGINT UNSIGNED NOT NULL, privacy_notice_version VARCHAR(60) NOT NULL, consent_or_lawful_basis VARCHAR(80) NOT NULL, applied_at DATETIME NOT NULL, stage VARCHAR(30) NOT NULL DEFAULT 'RECEIVED', screening_score DECIMAL(7,2) NULL, recommendation TEXT NULL, selection_rationale TEXT NULL, retention_until DATE NULL, status VARCHAR(24) NOT NULL DEFAULT 'OPEN', created_by BIGINT UNSIGNED NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY application_code (application_code), UNIQUE KEY requisition_person (requisition_id,person_id), KEY application_stage (requisition_id,stage,status)",
			'hrx_interviews' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, application_id BIGINT UNSIGNED NOT NULL, interview_round VARCHAR(40) NOT NULL, interview_at DATETIME NULL, interviewer_user_id BIGINT UNSIGNED NOT NULL, rubric_version VARCHAR(60) NOT NULL, score DECIMAL(7,2) NULL, recommendation VARCHAR(24) NOT NULL DEFAULT 'PENDING', evidence_ref VARCHAR(190) NULL, notes_redacted TEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY interview_application (application_id,interview_round), KEY interview_actor (interviewer_user_id,interview_at)",
			'hrx_offers' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, offer_code VARCHAR(40) NOT NULL, application_id BIGINT UNSIGNED NOT NULL, employment_type VARCHAR(30) NOT NULL, proposed_start DATE NOT NULL, proposed_end DATE NULL, position_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NULL, compensation_assignment_id BIGINT UNSIGNED NULL, terms_version VARCHAR(60) NOT NULL, contract_ref_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, accepted_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY offer_code (offer_code), KEY offer_application (application_id,status)",
			'hrx_employments' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_code VARCHAR(40) NOT NULL, person_id BIGINT UNSIGNED NOT NULL, offer_id BIGINT UNSIGNED NULL, legal_employer_code VARCHAR(80) NOT NULL, employment_type VARCHAR(30) NOT NULL, start_date DATE NOT NULL, end_date DATE NULL, probation_end DATE NULL, status VARCHAR(24) NOT NULL DEFAULT 'PENDING_ACTIVATION', current_assignment_id BIGINT UNSIGNED NULL, wp_user_id BIGINT UNSIGNED NULL, access_revoked_at DATETIME NULL, access_revoked_by BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, activated_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY employment_code (employment_code), KEY employment_person (person_id,status), KEY employment_dates (start_date,end_date)",
			'hrx_contracts' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, contract_code VARCHAR(40) NOT NULL, contract_version INT UNSIGNED NOT NULL, contract_type VARCHAR(30) NOT NULL, start_date DATE NOT NULL, end_date DATE NULL, probation_end DATE NULL, terms_hash CHAR(64) NOT NULL, private_document_ref_id BIGINT UNSIGNED NULL, signed_by_person_at DATETIME NULL, owner_approved_by BIGINT UNSIGNED NULL, owner_approved_at DATETIME NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY contract_version (employment_id,contract_version), UNIQUE KEY contract_code (contract_code), KEY contract_dates (start_date,end_date,status)",
			'hrx_onboarding_tasks' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, task_code VARCHAR(40) NOT NULL, task_title VARCHAR(180) NOT NULL, owner_role VARCHAR(40) NOT NULL, due_date DATE NULL, evidence_ref_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'OPEN', completed_by BIGINT UNSIGNED NULL, completed_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY onboarding_task (employment_id,task_code), KEY onboarding_status (status,due_date)",
			'hrx_assignments' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employment_id BIGINT UNSIGNED NOT NULL, position_id BIGINT UNSIGNED NOT NULL, org_unit_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NULL, manager_employment_id BIGINT UNSIGNED NULL, effective_from DATE NOT NULL, effective_to DATE NULL, reason_code VARCHAR(40) NOT NULL, approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY assignment_employment_dates (employment_id,effective_from,effective_to), KEY assignment_location (location_id,effective_from,effective_to)",
			'hrx_schedules' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, schedule_code VARCHAR(40) NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, effective_from DATE NOT NULL, effective_to DATE NULL, timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Addis_Ababa', monday_json TEXT NULL, tuesday_json TEXT NULL, wednesday_json TEXT NULL, thursday_json TEXT NULL, friday_json TEXT NULL, saturday_json TEXT NULL, sunday_json TEXT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY schedule_code (schedule_code), KEY schedule_employee_dates (employment_id,effective_from,effective_to)",
			'hrx_time_events' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_code VARCHAR(40) NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NULL, event_type VARCHAR(30) NOT NULL, event_date DATE NOT NULL, time_in DATETIME NULL, time_out DATETIME NULL, ordinary_minutes INT UNSIGNED NOT NULL DEFAULT 0, overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0, night_minutes INT UNSIGNED NOT NULL DEFAULT 0, rest_day_minutes INT UNSIGNED NOT NULL DEFAULT 0, holiday_minutes INT UNSIGNED NOT NULL DEFAULT 0, source_type VARCHAR(30) NOT NULL DEFAULT 'MANUAL', source_reference VARCHAR(190) NULL, correction_of_id BIGINT UNSIGNED NULL, correction_reason VARCHAR(255) NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', submitted_by BIGINT UNSIGNED NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY event_code (event_code), KEY time_employment_date (employment_id,event_date,status), KEY time_location_date (location_id,event_date)",
			'hrx_leave_policies' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, policy_code VARCHAR(40) NOT NULL, leave_type VARCHAR(40) NOT NULL, policy_version INT UNSIGNED NOT NULL DEFAULT 1, effective_from DATE NOT NULL, effective_to DATE NULL, accrual_method VARCHAR(30) NOT NULL, accrual_amount DECIMAL(8,3) NOT NULL DEFAULT 0.000, annual_limit DECIMAL(8,3) NULL, carry_forward_limit DECIMAL(8,3) NULL, paid_status VARCHAR(20) NOT NULL, legal_rule_id BIGINT UNSIGNED NULL, approval_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY policy_version (policy_code,policy_version), KEY leave_policy_dates (leave_type,effective_from,effective_to)",
			'hrx_leave_transactions' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, transaction_code VARCHAR(40) NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, leave_policy_id BIGINT UNSIGNED NULL, transaction_type VARCHAR(24) NOT NULL, start_date DATE NULL, end_date DATE NULL, quantity_days DECIMAL(8,3) NOT NULL DEFAULT 0.000, source_transaction_id BIGINT UNSIGNED NULL, reason VARCHAR(255) NULL, evidence_ref_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, decided_by BIGINT UNSIGNED NULL, decided_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY transaction_code (transaction_code), KEY leave_employee_dates (employment_id,start_date,end_date,status), KEY leave_policy (leave_policy_id,transaction_type)",
			'hrx_compensation_assignments' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, assignment_code VARCHAR(40) NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'ETB', base_salary DECIMAL(14,2) NOT NULL DEFAULT 0.00, effective_from DATE NOT NULL, effective_to DATE NULL, reason_code VARCHAR(40) NOT NULL, reason_text VARCHAR(500) NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', source_type VARCHAR(30) NOT NULL DEFAULT 'APPROVED_INSTRUCTION', source_reference VARCHAR(190) NULL, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY compensation_code (assignment_code), KEY compensation_employee_dates (employment_id,effective_from,effective_to,status)",
			'hrx_compensation_items' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, compensation_assignment_id BIGINT UNSIGNED NOT NULL, component_code VARCHAR(40) NOT NULL, component_name VARCHAR(120) NOT NULL, component_type VARCHAR(24) NOT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, frequency VARCHAR(24) NOT NULL DEFAULT 'MONTHLY', cash_payable TINYINT(1) NOT NULL DEFAULT 1, taxable_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', pensionable_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', legal_rule_id BIGINT UNSIGNED NULL, evidence_ref_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY compensation_component (compensation_assignment_id,component_code), KEY compensation_item_rule (legal_rule_id,taxable_status,pensionable_status)",
			'hrx_legal_rules' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, rule_code VARCHAR(60) NOT NULL, rule_family VARCHAR(30) NOT NULL, jurisdiction VARCHAR(80) NOT NULL DEFAULT 'ET-FEDERAL', source_title VARCHAR(255) NOT NULL, source_article VARCHAR(120) NOT NULL, source_url TEXT NOT NULL, source_sha256 CHAR(64) NULL, publication_date DATE NULL, effective_from DATE NOT NULL, effective_to DATE NULL, formula_key VARCHAR(60) NOT NULL, formula_parameters LONGTEXT NOT NULL, applicability TEXT NOT NULL, exceptions TEXT NULL, review_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', reviewer_name VARCHAR(120) NULL, reviewer_qualification VARCHAR(160) NULL, review_evidence_ref_id BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, test_vectors_sha256 CHAR(64) NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY legal_rule_version (rule_code,version_no), KEY legal_rule_effective (rule_family,review_status,effective_from,effective_to)",
			'hrx_legal_rule_bands' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, legal_rule_id BIGINT UNSIGNED NOT NULL, band_order INT UNSIGNED NOT NULL, lower_bound DECIMAL(14,2) NOT NULL DEFAULT 0.00, upper_bound DECIMAL(14,2) NULL, rate DECIMAL(8,6) NOT NULL DEFAULT 0.000000, constant_credit DECIMAL(14,2) NOT NULL DEFAULT 0.00, calculation_method VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY rule_band_order (legal_rule_id,band_order), KEY rule_band_bounds (legal_rule_id,lower_bound,upper_bound)",
			'hrx_payroll_runs' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, run_code VARCHAR(40) NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL, business_cutoff DATETIME NOT NULL, run_version INT UNSIGNED NOT NULL DEFAULT 1, status VARCHAR(30) NOT NULL DEFAULT 'DRAFT', currency CHAR(3) NOT NULL DEFAULT 'ETB', input_snapshot_sha256 CHAR(64) NULL, rule_bundle_sha256 CHAR(64) NULL, employee_count INT UNSIGNED NOT NULL DEFAULT 0, gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, taxable_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, paye_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, employee_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, employer_pension_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, deduction_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, employer_cost_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, submitted_by BIGINT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, journal_id BIGINT UNSIGNED NULL, payout_state VARCHAR(24) NOT NULL DEFAULT 'NOT_STARTED', closed_by BIGINT UNSIGNED NULL, closed_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY payroll_period_version (period_start,period_end,run_version), UNIQUE KEY run_code (run_code), KEY payroll_status_period (status,period_start,period_end)",
			'hrx_payroll_inputs' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payroll_run_id BIGINT UNSIGNED NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, source_kind VARCHAR(30) NOT NULL, source_id BIGINT UNSIGNED NULL, source_version INT UNSIGNED NULL, snapshot_json LONGTEXT NOT NULL, source_hash CHAR(64) NOT NULL, eligibility_status VARCHAR(24) NOT NULL DEFAULT 'READY', exception_code VARCHAR(60) NULL, exception_detail_redacted VARCHAR(500) NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY payroll_input_identity (payroll_run_id,employment_id,source_kind), KEY payroll_input_exception (payroll_run_id,eligibility_status)",
			'hrx_payroll_lines' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payroll_run_id BIGINT UNSIGNED NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, person_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NULL, legacy_source_id BIGINT UNSIGNED NULL, line_status VARCHAR(24) NOT NULL DEFAULT 'CALCULATED', base_salary DECIMAL(14,2) NOT NULL DEFAULT 0.00, taxable_earnings DECIMAL(14,2) NOT NULL DEFAULT 0.00, exempt_earnings DECIMAL(14,2) NOT NULL DEFAULT 0.00, gross_pay DECIMAL(14,2) NOT NULL DEFAULT 0.00, taxable_base DECIMAL(14,2) NOT NULL DEFAULT 0.00, paye DECIMAL(14,2) NOT NULL DEFAULT 0.00, employee_pension DECIMAL(14,2) NOT NULL DEFAULT 0.00, employer_pension DECIMAL(14,2) NOT NULL DEFAULT 0.00, other_deductions DECIMAL(14,2) NOT NULL DEFAULT 0.00, net_pay DECIMAL(14,2) NOT NULL DEFAULT 0.00, employer_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00, rule_bundle_sha256 CHAR(64) NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY payroll_employee_line (payroll_run_id,employment_id), KEY payroll_person (person_id,created_at), KEY payroll_location (location_id,created_at)",
			'hrx_payroll_line_items' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payroll_line_id BIGINT UNSIGNED NOT NULL, item_code VARCHAR(40) NOT NULL, item_label VARCHAR(160) NOT NULL, item_type VARCHAR(24) NOT NULL, quantity DECIMAL(12,3) NULL, rate DECIMAL(10,6) NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, cash_payable TINYINT(1) NOT NULL DEFAULT 1, taxable_status VARCHAR(24) NOT NULL, pensionable_status VARCHAR(24) NOT NULL, legal_rule_id BIGINT UNSIGNED NULL, source_type VARCHAR(30) NOT NULL, source_id BIGINT UNSIGNED NULL, calculation_trace TEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY payroll_item_line (payroll_line_id,item_type), KEY payroll_item_rule (legal_rule_id)",
			'hrx_payout_batches' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, batch_code VARCHAR(40) NOT NULL, payroll_run_id BIGINT UNSIGNED NOT NULL, method VARCHAR(24) NOT NULL, batch_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', external_batch_reference VARCHAR(190) NULL, exported_at DATETIME NULL, submitted_at DATETIME NULL, reconciled_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY payout_batch_code (batch_code), KEY payout_run_status (payroll_run_id,status)",
			'hrx_payout_items' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payout_batch_id BIGINT UNSIGNED NOT NULL, payroll_line_id BIGINT UNSIGNED NOT NULL, payment_reference VARCHAR(120) NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, status VARCHAR(24) NOT NULL DEFAULT 'PENDING', external_receiver_ref_hash CHAR(64) NULL, settled_at DATETIME NULL, confirmed_by BIGINT UNSIGNED NULL, confirmation_evidence_ref_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, PRIMARY KEY (id), UNIQUE KEY payout_line (payout_batch_id,payroll_line_id), UNIQUE KEY payout_reference (payment_reference), KEY payout_item_status (status,settled_at)",
			'hrx_payout_attempts' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payout_item_id BIGINT UNSIGNED NOT NULL, attempt_no INT UNSIGNED NOT NULL, attempt_status VARCHAR(24) NOT NULL, external_reference VARCHAR(190) NULL, attempted_at DATETIME NOT NULL, outcome_at DATETIME NULL, failure_code VARCHAR(60) NULL, failure_note_redacted VARCHAR(500) NULL, created_by BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id), UNIQUE KEY payout_attempt (payout_item_id,attempt_no), KEY payout_external_ref (external_reference)",
			'hrx_statutory_obligations' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, obligation_code VARCHAR(40) NOT NULL, payroll_run_id BIGINT UNSIGNED NOT NULL, obligation_type VARCHAR(30) NOT NULL, rule_id BIGINT UNSIGNED NULL, amount_due DECIMAL(14,2) NOT NULL DEFAULT 0.00, due_date DATE NULL, status VARCHAR(24) NOT NULL DEFAULT 'OPEN', created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY obligation_code (obligation_code), UNIQUE KEY obligation_run_type (payroll_run_id,obligation_type), KEY obligation_due (status,due_date)",
			'hrx_remittances' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, remittance_code VARCHAR(40) NOT NULL, obligation_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, payment_date DATE NULL, government_reference VARCHAR(190) NOT NULL, payment_reference VARCHAR(120) NULL, receipt_document_ref_id BIGINT UNSIGNED NULL, journal_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', prepared_by BIGINT UNSIGNED NOT NULL, verified_by BIGINT UNSIGNED NULL, verified_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY remittance_code (remittance_code), UNIQUE KEY remittance_payment_reference (payment_reference), KEY remittance_obligation (obligation_id,status,payment_date)",
			'hrx_accounting_links' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, source_type VARCHAR(40) NOT NULL, source_id BIGINT UNSIGNED NOT NULL, journal_id BIGINT UNSIGNED NOT NULL, journal_hash CHAR(64) NULL, posted_at DATETIME NOT NULL, posted_by BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id), UNIQUE KEY accounting_source (source_type,source_id), UNIQUE KEY accounting_journal (journal_id)",
			'hrx_payee_engagements' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, engagement_code VARCHAR(40) NOT NULL, payee_person_id BIGINT UNSIGNED NOT NULL, engagement_type VARCHAR(40) NOT NULL, campaign_or_service VARCHAR(160) NOT NULL, start_date DATE NOT NULL, end_date DATE NULL, contract_ref_id BIGINT UNSIGNED NULL, deliverable_terms TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY engagement_code (engagement_code), KEY engagement_person_dates (payee_person_id,start_date,end_date,status)",
			'hrx_payee_requests' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, request_code VARCHAR(40) NOT NULL, engagement_id BIGINT UNSIGNED NOT NULL, request_type VARCHAR(30) NOT NULL, service_period_start DATE NULL, service_period_end DATE NULL, cash_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, in_kind_value DECIMAL(14,2) NOT NULL DEFAULT 0.00, currency CHAR(3) NOT NULL DEFAULT 'ETB', invoice_or_receipt_ref VARCHAR(190) NULL, withholding_rule_id BIGINT UNSIGNED NULL, gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, withholding_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, net_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, in_kind_stock_reference VARCHAR(190) NULL, in_kind_posting_verified TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'DRAFT', classification_status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', ap_payment_id BIGINT UNSIGNED NULL, journal_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY payee_request_code (request_code), KEY payee_request_status (status,service_period_start)",
			'hrx_payee_deliverables' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payee_request_id BIGINT UNSIGNED NOT NULL, deliverable_code VARCHAR(40) NOT NULL, deliverable_type VARCHAR(40) NOT NULL, evidence_ref_id BIGINT UNSIGNED NULL, completed_at DATETIME NULL, accepted_by BIGINT UNSIGNED NULL, accepted_at DATETIME NULL, status VARCHAR(24) NOT NULL DEFAULT 'PENDING', notes_redacted VARCHAR(500) NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY payee_deliverable_code (deliverable_code), KEY payee_deliverable_request (payee_request_id,status)",
			'hrx_payee_classifications' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payee_request_id BIGINT UNSIGNED NOT NULL, rule_id BIGINT UNSIGNED NULL, classification VARCHAR(40) NOT NULL, withholding_required VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', withholding_rate DECIMAL(8,6) NULL, reason TEXT NOT NULL, evidence_ref_id BIGINT UNSIGNED NULL, reviewer_user_id BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL, status VARCHAR(24) NOT NULL DEFAULT 'LEGAL_REVIEW', created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY payee_classification (payee_request_id,status)",
			'hrx_document_refs' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, document_code VARCHAR(40) NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL, document_type VARCHAR(50) NOT NULL, private_storage_key VARCHAR(255) NOT NULL, content_sha256 CHAR(64) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, approval_status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', effective_date DATE NULL, uploaded_by BIGINT UNSIGNED NOT NULL, verified_by BIGINT UNSIGNED NULL, verified_at DATETIME NULL, retention_until DATE NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY document_code (document_code), KEY document_owner (entity_type,entity_id,document_type), KEY document_hash (content_sha256)",
			'hrx_document_blobs' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, document_ref_id BIGINT UNSIGNED NOT NULL, payload MEDIUMBLOB NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY document_ref (document_ref_id)",
			'hrx_approvals' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, entity_type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL, action_code VARCHAR(40) NOT NULL, from_status VARCHAR(30) NULL, to_status VARCHAR(30) NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL, approver_user_id BIGINT UNSIGNED NULL, reason_redacted VARCHAR(500) NULL, evidence_ref_id BIGINT UNSIGNED NULL, idempotency_key VARCHAR(100) NOT NULL, payload_sha256 CHAR(64) NOT NULL, occurred_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY approval_idempotency (idempotency_key), KEY approval_entity (entity_type,entity_id,occurred_at), KEY approval_actor (actor_user_id,occurred_at)",
			'hrx_social_snapshots' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, snapshot_code VARCHAR(40) NOT NULL, platform VARCHAR(40) NOT NULL, account_label VARCHAR(120) NOT NULL, campaign_code VARCHAR(80) NULL, reporting_period_start DATE NOT NULL, reporting_period_end DATE NOT NULL, as_of_at DATETIME NOT NULL, views BIGINT UNSIGNED NULL, reach BIGINT UNSIGNED NULL, engagements BIGINT UNSIGNED NULL, link_clicks BIGINT UNSIGNED NULL, attributable_sales DECIMAL(14,2) NULL, campaign_fee DECIMAL(14,2) NULL, gifted_stock_value DECIMAL(14,2) NULL, source_reference VARCHAR(190) NOT NULL, freshness_status VARCHAR(24) NOT NULL DEFAULT 'CURRENT', entered_by BIGINT UNSIGNED NOT NULL, reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY social_snapshot_code (snapshot_code), KEY social_account_period (platform,account_label,reporting_period_start,reporting_period_end)",
			'hrx_accounting_config' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, config_code VARCHAR(40) NOT NULL, wage_expense_account VARCHAR(40) NOT NULL, employer_pension_expense_account VARCHAR(40) NOT NULL, net_payable_account VARCHAR(40) NOT NULL, paye_payable_account VARCHAR(40) NOT NULL, employee_pension_payable_account VARCHAR(40) NOT NULL, employer_pension_payable_account VARCHAR(40) NOT NULL, other_deductions_payable_account VARCHAR(40) NOT NULL, cash_account_code VARCHAR(40) NOT NULL, payee_expense_account VARCHAR(40) NOT NULL, payee_payable_account VARCHAR(40) NOT NULL, payee_withholding_account VARCHAR(40) NOT NULL, effective_from DATE NOT NULL, effective_to DATE NULL, status VARCHAR(24) NOT NULL DEFAULT 'DRAFT', prepared_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY accounting_config_code (config_code), KEY accounting_config_effective (status,effective_from,effective_to)",
			'hrx_exit_settlements' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, settlement_code VARCHAR(40) NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, exit_type VARCHAR(40) NOT NULL, exit_reason VARCHAR(500) NOT NULL, notice_date DATE NULL, final_working_date DATE NOT NULL, effective_end_date DATE NOT NULL, last_payroll_run_id BIGINT UNSIGNED NULL, leave_balance_days DECIMAL(8,3) NULL, gross_final_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, deduction_total DECIMAL(14,2) NOT NULL DEFAULT 0.00, net_final_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, rule_bundle_sha256 CHAR(64) NULL, journal_id BIGINT UNSIGNED NULL, payout_journal_id BIGINT UNSIGNED NULL, settled_by BIGINT UNSIGNED NULL, settled_at DATETIME NULL, status VARCHAR(30) NOT NULL DEFAULT 'DRAFT', created_by BIGINT UNSIGNED NOT NULL, submitted_by BIGINT UNSIGNED NULL, approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, payout_item_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY settlement_code (settlement_code), KEY settlement_employment (employment_id,status,effective_end_date)",
			'hrx_exit_items' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, settlement_id BIGINT UNSIGNED NOT NULL, item_code VARCHAR(40) NOT NULL, item_label VARCHAR(160) NOT NULL, item_type VARCHAR(24) NOT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, rule_id BIGINT UNSIGNED NULL, payable_account_code VARCHAR(40) NULL, source_reference VARCHAR(190) NULL, calculation_trace TEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY exit_item_settlement (settlement_id,item_type)" ,
			'hrx_exit_clearance_tasks' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, settlement_id BIGINT UNSIGNED NOT NULL, employment_id BIGINT UNSIGNED NOT NULL, task_code VARCHAR(40) NOT NULL, task_title VARCHAR(180) NOT NULL, responsible_role VARCHAR(40) NOT NULL, evidence_ref_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'OPEN', completed_by BIGINT UNSIGNED NULL, completed_at DATETIME NULL, resolution_note VARCHAR(500) NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY exit_clearance_task (settlement_id,task_code), KEY exit_clearance_status (employment_id,status)",
			'hrx_exit_payouts' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payout_code VARCHAR(40) NOT NULL, settlement_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, method VARCHAR(24) NOT NULL, external_reference VARCHAR(120) NULL, evidence_ref_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'PENDING', journal_id BIGINT UNSIGNED NULL, idempotency_key VARCHAR(100) NOT NULL, created_by BIGINT UNSIGNED NOT NULL, paid_by BIGINT UNSIGNED NULL, paid_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY exit_payout_code (payout_code), UNIQUE KEY exit_payout_reference (external_reference), UNIQUE KEY exit_payout_idempotency (idempotency_key), KEY exit_payout_settlement (settlement_id,status)",
			'hrx_exit_payout_attempts' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payout_id BIGINT UNSIGNED NOT NULL, attempt_no INT UNSIGNED NOT NULL, attempt_status VARCHAR(24) NOT NULL, external_reference VARCHAR(190) NULL, evidence_ref_id BIGINT UNSIGNED NULL, attempted_at DATETIME NOT NULL, outcome_at DATETIME NULL, failure_note VARCHAR(500) NULL, created_by BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id), UNIQUE KEY exit_payout_attempt (payout_id,attempt_no)",
			'hrx_payee_payments' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payment_code VARCHAR(40) NOT NULL, payee_request_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, method VARCHAR(24) NOT NULL, external_reference VARCHAR(120) NULL, evidence_ref_id BIGINT UNSIGNED NULL, status VARCHAR(24) NOT NULL DEFAULT 'PENDING', journal_id BIGINT UNSIGNED NULL, idempotency_key VARCHAR(100) NOT NULL, created_by BIGINT UNSIGNED NOT NULL, paid_by BIGINT UNSIGNED NULL, paid_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY payee_payment_code (payment_code), UNIQUE KEY payee_payment_reference (external_reference), UNIQUE KEY payee_payment_request (payee_request_id), UNIQUE KEY payee_payment_idempotency (idempotency_key), KEY payee_payment_status (status)",
			'hrx_payee_payment_attempts' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payment_id BIGINT UNSIGNED NOT NULL, attempt_no INT UNSIGNED NOT NULL, attempt_status VARCHAR(24) NOT NULL, external_reference VARCHAR(190) NULL, evidence_ref_id BIGINT UNSIGNED NULL, attempted_at DATETIME NOT NULL, outcome_at DATETIME NULL, failure_note VARCHAR(500) NULL, created_by BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id), UNIQUE KEY payee_payment_attempt (payment_id,attempt_no)",
			'hrx_legacy_mappings' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, source_table VARCHAR(80) NOT NULL, source_id BIGINT UNSIGNED NOT NULL, source_sha256 CHAR(64) NOT NULL, target_table VARCHAR(80) NOT NULL, target_id BIGINT UNSIGNED NOT NULL, migration_version VARCHAR(40) NOT NULL, migrated_at DATETIME NOT NULL, migrated_by BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id), UNIQUE KEY legacy_source (source_table,source_id), KEY legacy_target (target_table,target_id)"
		);
		$out = array();
		foreach ( $defs as $key => $columns ) { $out[] = 'CREATE TABLE IF NOT EXISTS ' . $p . $key . ' (' . $columns . ') ENGINE=InnoDB ' . $c; }
		return $out;
	}

	/** Normalize integer display widths while preserving signedness and precision-bearing types. */
	private static function normalize_sql_type( $type ) {
		$type = strtolower( trim( preg_replace( '/\\s+/', ' ', (string) $type ) ) );
		return preg_replace( '/\\b(bigint|int|tinyint|smallint|mediumint)\\(\\d+\\)/i', '$1', $type );
	}

	/** Read-only physical schema state: names, SQL types and required indexes; never repairs the database. */
	public static function schema_status() {
		global $wpdb;
		$present = array(); $missing = array(); $drift = array();
		$expected = array();
		foreach ( self::ddl() as $sql ) {
			if ( ! preg_match( '/CREATE TABLE IF NOT EXISTS ([A-Za-z0-9_]+)\\s*\\((.*)\\) ENGINE=InnoDB/s', $sql, $match ) ) { continue; }
			$name = $match[1];
			$key = substr( $name, strlen( $wpdb->prefix . 'stock_' ) );
			$cols = array(); $types = array(); $indexes = array();
			if ( preg_match_all( '/(?:^|,)\\s*([a-z][a-z0-9_]*)\\s+(BIGINT|VARCHAR|TEXT|LONGTEXT|MEDIUMBLOB|CHAR|DATE|DATETIME|DECIMAL|INT|TINYINT)(?:\\(([^)]*)\\))?(\\s+UNSIGNED)?(?=\\s|,|$)/i', $match[2], $column_matches, PREG_SET_ORDER ) ) {
				foreach ( $column_matches as $cm ) {
					$column = strtolower( $cm[1] );
					$size = isset( $cm[3] ) && '' !== $cm[3] ? '(' . preg_replace( '/\\s+/', '', $cm[3] ) . ')' : '';
					$unsigned = ! empty( $cm[4] ) ? ' UNSIGNED' : '';
					$cols[] = $column;
					$types[$column] = self::normalize_sql_type( strtoupper( $cm[2] ) . $size . $unsigned );
				}
			}
			if ( preg_match( '/PRIMARY KEY\\s*\\(([^)]+)\\)/i', $match[2], $primary ) ) {
				$indexes['primary'] = array( 'unique'=>true, 'columns'=>array_map( static function( $v ) { return strtolower( trim( str_replace( '`', '', $v ) ) ); }, explode( ',', $primary[1] ) ) );
			}
			if ( preg_match_all( '/(UNIQUE KEY|KEY)\\s+([A-Za-z0-9_]+)\\s*\\(([^)]+)\\)/i', $match[2], $index_matches, PREG_SET_ORDER ) ) {
				foreach ( $index_matches as $im ) {
					$index_name = strtolower( $im[2] );
					$indexes[$index_name] = array(
						'unique' => 0 === stripos( $im[1], 'UNIQUE' ),
						'columns' => array_map( static function( $v ) { return strtolower( trim( str_replace( '`', '', $v ) ) ); }, explode( ',', $im[3] ) ),
					);
				}
			}
			$expected[$key] = array( 'columns'=>$cols, 'types'=>$types, 'indexes'=>$indexes );
		}
		foreach ( self::table_keys() as $key ) {
			$name = Stockland_DB::table( $key );
			$exists = Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s', DB_NAME, $name ) );
			if ( ! empty( $wpdb->last_error ) || (int) $exists !== 1 ) { $missing[] = $key; continue; }
			$present[] = $key;
			$actual_rows = Stockland_DB::read_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $name ), ARRAY_A );
			if ( ! empty( $wpdb->last_error ) || ! is_array( $actual_rows ) ) {
				$drift[$key] = array( 'missing_columns'=>$expected[$key]['columns'] ?? array(), 'unexpected_columns'=>array(), 'column_type_mismatch'=>array(), 'missing_indexes'=>array(), 'index_mismatch'=>array(), 'reason'=>'Could not inspect column metadata.' );
				continue;
			}
			$actual = array_map( 'strtolower', array_column( $actual_rows, 'Field' ) );
			$wanted = $expected[$key]['columns'] ?? array();
			$miss = array_values( array_diff( $wanted, $actual ) );
			$extra = array_values( array_diff( $actual, $wanted ) );
			$actual_types = array();
			foreach ( $actual_rows as $column_row ) { $actual_types[strtolower( $column_row['Field'] )] = self::normalize_sql_type( $column_row['Type'] ); }
			$type_mismatch = array();
			foreach ( ( $expected[$key]['types'] ?? array() ) as $column => $type ) {
				if ( isset( $actual_types[$column] ) && $type !== $actual_types[$column] ) { $type_mismatch[$column] = array( 'expected'=>$type, 'actual'=>$actual_types[$column] ); }
			}
			$index_rows = Stockland_DB::read_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $name ), ARRAY_A );
			$index_missing = array(); $index_mismatch = array(); $actual_indexes = array();
			if ( ! empty( $wpdb->last_error ) || ! is_array( $index_rows ) ) {
				$index_missing = array_keys( $expected[$key]['indexes'] ?? array() );
				$index_mismatch['metadata'] = 'Could not inspect index metadata.';
			} else {
				foreach ( $index_rows as $index_row ) {
					$index_name = strtolower( (string) $index_row['Key_name'] );
					if ( ! isset( $actual_indexes[$index_name] ) ) { $actual_indexes[$index_name] = array( 'unique'=>0 === (int) $index_row['Non_unique'], 'columns'=>array() ); }
					$actual_indexes[$index_name]['columns'][(int) $index_row['Seq_in_index']] = strtolower( (string) $index_row['Column_name'] );
				}
				foreach ( $actual_indexes as &$actual_index ) { ksort( $actual_index['columns'] ); $actual_index['columns'] = array_values( $actual_index['columns'] ); } unset( $actual_index );
				foreach ( ( $expected[$key]['indexes'] ?? array() ) as $index_name => $index ) {
					if ( ! isset( $actual_indexes[$index_name] ) ) { $index_missing[] = $index_name; continue; }
					if ( $actual_indexes[$index_name]['unique'] !== $index['unique'] || $actual_indexes[$index_name]['columns'] !== $index['columns'] ) {
						$index_mismatch[$index_name] = array( 'expected'=>$index, 'actual'=>$actual_indexes[$index_name] );
					}
				}
			}
			if ( $miss || $extra || $type_mismatch || $index_missing || $index_mismatch ) {
				$drift[$key] = array(
					'missing_columns'=>$miss, 'unexpected_columns'=>$extra,
					'column_type_mismatch'=>$type_mismatch, 'missing_indexes'=>$index_missing,
					'index_mismatch'=>$index_mismatch,
					'reason'=>'Physical schema does not match the full column-type and required-index contract. No automatic ALTER is attempted.',
				);
			}
		}
		$recorded = (string) get_option( self::OPTION_SCHEMA_VERSION, '' );
		return array(
			'version' => self::SCHEMA_VERSION, 'recorded_version' => $recorded,
			'total' => count( self::table_keys() ), 'present' => count( $present ),
			'present_tables' => $present, 'missing_tables' => $missing, 'schema_drift' => $drift,
			'ready' => empty( $missing ) && empty( $drift ) && self::SCHEMA_VERSION === $recorded,
			'legacy_tables_read_only_sources' => array( 'employees','employee_compensation','payroll_periods','payroll_lines' ),
			'schema_changes_to_legacy_tables' => false,
		);
	}

	/** Explicit CREATE only; no ALTER/DROP. Existing schema mismatches are reported, never auto-repaired. */
	public static function install_schema_batch( $limit = 5 ) {
		global $wpdb;
		if ( ! is_user_logged_in() || ! ( current_user_can( 'manage_options' ) || ( class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_approve_payroll() && current_user_can( 'stockland_manage_hr' ) ) || current_user_can( 'stockland_install_schema' ) ) ) {
			return new WP_Error( 'hrx_schema_forbidden', 'Only an authorized schema administrator may create the new HR replacement tables.', array( 'status' => 403 ) );
		}
		$limit = max( 1, min( 8, (int) $limit ) );
		$before = self::schema_status(); $todo = array_flip( $before['missing_tables'] ); $created = array(); $checked = 0;
		foreach ( self::ddl() as $sql ) {
			if ( $checked >= $limit ) { break; }
			if ( ! preg_match( '/CREATE TABLE IF NOT EXISTS ([A-Za-z0-9_]+)/', $sql, $m ) ) { return new WP_Error( 'hrx_ddl_contract', 'A schema declaration failed validation.', array( 'status' => 500 ) ); }
			$table = $m[1]; $key = substr( $table, strlen( $wpdb->prefix . 'stock_' ) );
			if ( ! isset( $todo[ $key ] ) ) { continue; }
			$wpdb->last_error = '';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL is a static internal allow-list; request data cannot alter SQL identifiers or definitions.
			$ok = Stockland_DB::execute_query( $sql );
			if ( false === $ok || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'hrx_schema_create_failed', 'Could not create an HR replacement table. Legacy tables were not changed.', array( 'status' => 500, 'table' => $key ) ); }
			$created[] = $key; ++$checked;
		}
		$after = self::schema_status();
		if ( empty( $after['missing_tables'] ) && empty( $after['schema_drift'] ) ) { update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false ); }
		return array( 'created_or_verified' => $created, 'status' => $after, 'message' => empty( $after['missing_tables'] ) ? 'New HR replacement schema is present. Legacy HR data remains unchanged.' : 'Schema batch completed; continue with the next bounded batch.' );
	}

	public static function table( $key ) {
		if ( ! in_array( $key, self::table_keys(), true ) ) { throw new InvalidArgumentException( 'Unknown HR replacement table key.' ); }
		return Stockland_DB::table( $key );
	}
	public static function now() { return current_time( 'mysql' ); }
	public static function valid_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) { return false; }
		return $value;
	}
	/** Only redacted references and state changes are sent through the established append-only audit chain. */
	public static function audit( $action, $entity_type, $entity_id, $reason = '', $location_id = null, $approver_id = null ) {
		return Stockland_Audit::log( get_current_user_id(), null !== $location_id ? (int) $location_id : null, 'HRX_' . sanitize_key( $action ), null, null, null, null, sanitize_text_field( substr( (string) $reason, 0, 240 ) ), sanitize_text_field( $entity_type . ':' . (int) $entity_id ), null !== $approver_id ? (int) $approver_id : null );
	}
	/** Idempotent, redacted transition record. */
	public static function approval_event( $entity_type, $entity_id, $action, $from, $to, $reason, $idempotency_key, $evidence_ref_id = null, $approver_id = null ) {
		global $wpdb;
		$idempotency_key = sanitize_text_field( (string) $idempotency_key );
		if ( '' === $idempotency_key ) { return new WP_Error( 'hrx_idempotency_required', 'A unique idempotency key is required.', array( 'status' => 400 ) ); }
		$payload = array( 'entity_type' => sanitize_key( $entity_type ), 'entity_id' => (int) $entity_id, 'action' => sanitize_key( $action ), 'from' => $from, 'to' => $to, 'actor' => get_current_user_id(), 'approver' => null !== $approver_id ? (int) $approver_id : null, 'reason_sha256' => hash( 'sha256', (string) $reason ), 'evidence_ref_id' => null !== $evidence_ref_id ? (int) $evidence_ref_id : null );
		$hash = hash( 'sha256', wp_json_encode( $payload ) );
		$existing = Stockland_DB::read_row( $wpdb->prepare( 'SELECT id,payload_sha256 FROM %i WHERE idempotency_key=%s', self::table( 'hrx_approvals' ), $idempotency_key ), ARRAY_A );
		if ( $existing ) {
			if ( hash_equals( (string) $existing['payload_sha256'], $hash ) ) { return (int) $existing['id']; }
			return new WP_Error( 'hrx_idempotency_conflict', 'This idempotency key was already used with a different action.', array( 'status' => 409 ) );
		}
		$ok = Stockland_DB::insert_record( self::table( 'hrx_approvals' ), array(
			'entity_type' => sanitize_key( $entity_type ), 'entity_id' => (int) $entity_id, 'action_code' => sanitize_key( $action),
			'from_status' => null === $from ? null : sanitize_key( $from), 'to_status' => sanitize_key( $to),
			'actor_user_id' => get_current_user_id(), 'approver_user_id' => null !== $approver_id ? (int) $approver_id : null,
			'reason_redacted' => sanitize_text_field( substr( (string) $reason, 0, 500 ) ),
			'evidence_ref_id' => null !== $evidence_ref_id ? (int) $evidence_ref_id : null,
			'idempotency_key' => $idempotency_key, 'payload_sha256' => $hash, 'occurred_at' => self::now()
		) );
		return false === $ok ? new WP_Error( 'hrx_approval_write_failed', 'The approval event was not saved.', array( 'status' => 500 ) ) : (int) $wpdb->insert_id;
	}
}
