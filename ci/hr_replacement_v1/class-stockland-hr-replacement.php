<?php
/**
 * New HR/payroll system-of-record schema, isolated from the legacy four-table module.
 *
 * The legacy employees/employee_compensation/payroll_periods/payroll_lines tables
 * are never altered by this class. Installation is explicit and capability-gated.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_HR_Replacement {
	const SCHEMA_VERSION = 'hr-replacement-v1.0.0';
	const OPTION_VERSION = 'stockland_hr_replacement_schema_version';

	public static function table( $suffix ) {
		return Stockland_DB::table( 'hr_' . sanitize_key( $suffix ) );
	}

	/**
	 * Logical domain tables. Relationships are checked by services because the
	 * existing hosting/schema contract does not rely on SQL FOREIGN KEY support.
	 * Do not add a target table without a documented requirement and test.
	 *
	 * @return array<string,array{fields:string,keys:string}>
	 */
	public static function definitions() {
		return array(
			'people' => array(
				'fields' => "person_code varchar(40) NOT NULL, person_kind varchar(24) NOT NULL DEFAULT 'CANDIDATE', display_name varchar(190) NOT NULL, legal_name varchar(190) NULL, email varchar(190) NULL, phone varchar(50) NULL, employee_number varchar(40) NULL, privacy_notice_version varchar(60) NULL, lawful_basis varchar(80) NULL, retention_review_on date NULL, status varchar(24) NOT NULL DEFAULT 'ACTIVE'",
				'keys' => 'UNIQUE KEY person_code (person_code), UNIQUE KEY employee_number (employee_number), KEY person_kind_status (person_kind,status), KEY email (email)',
			),
			'workforce_plans' => array(
				'fields' => "plan_code varchar(50) NOT NULL, fiscal_year smallint unsigned NOT NULL, department varchar(100) NOT NULL, location_id bigint unsigned NULL, position_id bigint unsigned NULL, requested_headcount int unsigned NOT NULL DEFAULT 0, approved_headcount int unsigned NOT NULL DEFAULT 0, budget_amount decimal(14,2) NULL, currency char(3) NOT NULL DEFAULT 'ETB', justification text NOT NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, effective_from date NULL, effective_to date NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY plan_code (plan_code), KEY fiscal_department (fiscal_year,department), KEY location_status (location_id,status)',
			),
			'positions' => array(
				'fields' => "position_code varchar(50) NOT NULL, title varchar(150) NOT NULL, department varchar(100) NOT NULL, grade_code varchar(50) NULL, location_id bigint unsigned NULL, manager_person_id bigint unsigned NULL, reporting_position_id bigint unsigned NULL, budget_code varchar(80) NULL, effective_from date NULL, effective_to date NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY position_code (position_code), KEY location_status (location_id,status), KEY manager_person (manager_person_id)',
			),
			'requisitions' => array(
				'fields' => "requisition_code varchar(50) NOT NULL, workforce_plan_id bigint unsigned NULL, position_id bigint unsigned NOT NULL, requester_user_id bigint unsigned NOT NULL, location_id bigint unsigned NULL, openings int unsigned NOT NULL DEFAULT 1, priority varchar(20) NOT NULL DEFAULT 'NORMAL', justification text NOT NULL, open_date date NULL, close_date date NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY requisition_code (requisition_code), KEY position_status (position_id,status), KEY location_status (location_id,status)',
			),
			'applications' => array(
				'fields' => "application_code varchar(50) NOT NULL, requisition_id bigint unsigned NOT NULL, person_id bigint unsigned NOT NULL, applied_at datetime NOT NULL, source varchar(80) NULL, stage varchar(32) NOT NULL DEFAULT 'APPLIED', human_decision varchar(24) NULL, decision_reason text NULL, retention_review_on date NULL, privacy_notice_version varchar(60) NULL, offered_at datetime NULL, status varchar(24) NOT NULL DEFAULT 'ACTIVE'",
				'keys' => 'UNIQUE KEY application_code (application_code), UNIQUE KEY requisition_person (requisition_id,person_id), KEY person_stage (person_id,stage), KEY requisition_stage (requisition_id,stage)',
			),
			'interview_events' => array(
				'fields' => "application_id bigint unsigned NOT NULL, scheduled_at datetime NULL, interviewer_user_id bigint unsigned NOT NULL, rubric_version varchar(60) NOT NULL, score_decimal decimal(8,3) NULL, evidence_ref varchar(255) NULL, decision varchar(24) NULL, rationale text NULL, event_hash char(64) NULL",
				'keys' => 'KEY application_time (application_id,scheduled_at), KEY interviewer_time (interviewer_user_id,scheduled_at)',
			),
			'offers' => array(
				'fields' => "offer_code varchar(50) NOT NULL, application_id bigint unsigned NOT NULL, contract_type varchar(30) NOT NULL, proposed_start date NOT NULL, proposed_end date NULL, probation_end date NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, issued_at datetime NULL, accepted_at datetime NULL, terms_ref varchar(255) NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY offer_code (offer_code), KEY application_status (application_id,status)',
			),
			'employment_contracts' => array(
				'fields' => "contract_code varchar(50) NOT NULL, person_id bigint unsigned NOT NULL, offer_id bigint unsigned NULL, contract_type varchar(30) NOT NULL, legal_employer varchar(190) NOT NULL, department varchar(100) NOT NULL, job_title varchar(150) NOT NULL, location_id bigint unsigned NULL, manager_person_id bigint unsigned NULL, start_date date NOT NULL, end_date date NULL, probation_end date NULL, weekly_hours decimal(6,2) NULL, schedule_json longtext NULL, notice_terms_ref varchar(255) NULL, signed_document_ref varchar(255) NULL, signed_document_sha256 char(64) NULL, signed_at datetime NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, supersedes_contract_id bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY contract_code (contract_code), KEY person_dates (person_id,start_date,end_date), KEY location_status (location_id,status)',
			),
			'onboarding_tasks' => array(
				'fields' => "case_code varchar(50) NOT NULL, person_id bigint unsigned NOT NULL, contract_id bigint unsigned NULL, task_key varchar(60) NOT NULL, task_title varchar(190) NOT NULL, mandatory tinyint(1) NOT NULL DEFAULT 1, owner_user_id bigint unsigned NULL, due_at date NULL, completed_at datetime NULL, evidence_ref varchar(255) NULL, blocker_note varchar(500) NULL, status varchar(24) NOT NULL DEFAULT 'OPEN'",
				'keys' => 'KEY case_task (case_code,task_key), KEY person_status (person_id,status), KEY owner_due (owner_user_id,due_at)',
			),
			'employee_documents' => array(
				'fields' => "subject_type varchar(24) NOT NULL, subject_id bigint unsigned NOT NULL, document_type varchar(60) NOT NULL, private_file_ref varchar(255) NOT NULL, file_sha256 char(64) NULL, issued_on date NULL, expires_on date NULL, verified_by bigint unsigned NULL, verified_at datetime NULL, access_class varchar(24) NOT NULL DEFAULT 'RESTRICTED', retention_review_on date NULL, status varchar(24) NOT NULL DEFAULT 'PENDING_REVIEW'",
				'keys' => 'KEY subject_document (subject_type,subject_id,document_type), KEY expiry (expires_on,status), KEY file_hash (file_sha256)',
			),
			'lifecycle_events' => array(
				'fields' => "person_id bigint unsigned NOT NULL, event_type varchar(40) NOT NULL, effective_on date NOT NULL, previous_ref varchar(120) NULL, new_ref varchar(120) NULL, reason_code varchar(60) NOT NULL, evidence_ref varchar(255) NULL, actor_user_id bigint unsigned NOT NULL, approver_user_id bigint unsigned NULL, event_uuid char(36) NOT NULL, prior_event_hash char(64) NULL, event_hash char(64) NULL",
				'keys' => 'UNIQUE KEY event_uuid (event_uuid), KEY person_effective (person_id,effective_on), KEY event_type_date (event_type,effective_on)',
			),
			'shift_patterns' => array(
				'fields' => "pattern_code varchar(50) NOT NULL, title varchar(150) NOT NULL, timezone varchar(80) NOT NULL DEFAULT 'Africa/Addis_Ababa', schedule_json longtext NOT NULL, weekly_rest_json longtext NULL, effective_from date NOT NULL, effective_to date NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY pattern_code (pattern_code), KEY effective_status (effective_from,effective_to,status)',
			),
			'shift_assignments' => array(
				'fields' => "person_id bigint unsigned NOT NULL, pattern_id bigint unsigned NOT NULL, location_id bigint unsigned NULL, effective_from date NOT NULL, effective_to date NULL, assigned_by bigint unsigned NOT NULL, approved_by bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'KEY person_dates (person_id,effective_from,effective_to), KEY pattern_dates (pattern_id,effective_from,effective_to), KEY location_dates (location_id,effective_from)',
			),
			'attendance_records' => array(
				'fields' => "attendance_code varchar(50) NOT NULL, person_id bigint unsigned NOT NULL, shift_assignment_id bigint unsigned NULL, location_id bigint unsigned NULL, work_date date NOT NULL, clock_in datetime NULL, clock_out datetime NULL, break_minutes int unsigned NOT NULL DEFAULT 0, ordinary_minutes int unsigned NOT NULL DEFAULT 0, overtime_minutes int unsigned NOT NULL DEFAULT 0, time_source varchar(24) NOT NULL DEFAULT 'MANUAL', evidence_ref varchar(255) NULL, correction_of_id bigint unsigned NULL, correction_reason text NULL, submitted_by bigint unsigned NOT NULL, reviewed_by bigint unsigned NULL, reviewed_at datetime NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY attendance_code (attendance_code), KEY person_date (person_id,work_date), KEY location_date (location_id,work_date), KEY correction_of (correction_of_id)',
			),
			'leave_policies' => array(
				'fields' => "policy_code varchar(50) NOT NULL, title varchar(150) NOT NULL, leave_kind varchar(30) NOT NULL, paid_flag tinyint(1) NOT NULL DEFAULT 1, eligibility_json longtext NOT NULL, accrual_method varchar(30) NOT NULL, accrual_amount decimal(8,3) NOT NULL DEFAULT 0, carry_cap decimal(8,3) NULL, effective_from date NOT NULL, effective_to date NULL, rule_set_id bigint unsigned NULL, approved_by bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY policy_code (policy_code), KEY leave_kind_dates (leave_kind,effective_from,effective_to,status)',
			),
			'leave_requests' => array(
				'fields' => "request_code varchar(50) NOT NULL, person_id bigint unsigned NOT NULL, policy_id bigint unsigned NOT NULL, date_from date NOT NULL, date_to date NOT NULL, units decimal(8,3) NOT NULL, reason text NULL, evidence_ref varchar(255) NULL, submitted_by bigint unsigned NOT NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, decision_reason text NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY request_code (request_code), KEY person_dates (person_id,date_from,date_to), KEY policy_dates (policy_id,date_from,date_to)',
			),
			'leave_ledger' => array(
				'fields' => "entry_uuid char(36) NOT NULL, person_id bigint unsigned NOT NULL, policy_id bigint unsigned NOT NULL, entry_type varchar(24) NOT NULL, units decimal(8,3) NOT NULL, effective_on date NOT NULL, source_type varchar(32) NOT NULL, source_id bigint unsigned NULL, correction_of_id bigint unsigned NULL, idempotency_key varchar(100) NOT NULL, approved_by bigint unsigned NULL, note_code varchar(60) NULL, entry_hash char(64) NULL",
				'keys' => 'UNIQUE KEY entry_uuid (entry_uuid), UNIQUE KEY idempotency_key (idempotency_key), KEY person_policy_date (person_id,policy_id,effective_on), KEY source_ref (source_type,source_id)',
			),
			'compensation_components' => array(
				'fields' => "component_code varchar(50) NOT NULL, label varchar(150) NOT NULL, component_kind varchar(30) NOT NULL, unit_kind varchar(24) NOT NULL DEFAULT 'MONEY', frequency varchar(24) NOT NULL DEFAULT 'MONTHLY', description varchar(255) NULL, default_rule_tag varchar(80) NULL, requires_evidence tinyint(1) NOT NULL DEFAULT 0, effective_from date NOT NULL, effective_to date NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY component_code (component_code), KEY kind_dates (component_kind,effective_from,effective_to,status)',
			),
			'compensation_assignments' => array(
				'assignment_code varchar(50) NOT NULL, person_id bigint unsigned NOT NULL, component_id bigint unsigned NOT NULL, amount decimal(14,2) NOT NULL, currency char(3) NOT NULL DEFAULT 'ETB', frequency varchar(24) NOT NULL DEFAULT 'MONTHLY', quantity decimal(10,3) NOT NULL DEFAULT 1, effective_from date NOT NULL, effective_to date NULL, reason_code varchar(60) NOT NULL, evidence_ref varchar(255) NULL, proposed_by bigint unsigned NOT NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, supersedes_id bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY assignment_code (assignment_code), KEY person_effective (person_id,effective_from,effective_to,status), KEY component_effective (component_id,effective_from,effective_to)',
			),
			'benefit_enrollments' => array(
				'enrollment_code varchar(50) NOT NULL, person_id bigint unsigned NOT NULL, benefit_code varchar(60) NOT NULL, coverage_from date NOT NULL, coverage_to date NULL, employee_share decimal(14,2) NOT NULL DEFAULT 0, employer_share decimal(14,2) NOT NULL DEFAULT 0, currency char(3) NOT NULL DEFAULT 'ETB', evidence_ref varchar(255) NULL, approved_by bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY enrollment_code (enrollment_code), KEY person_coverage (person_id,coverage_from,coverage_to)',
			),
			'rule_sets' => array(
				'fields' => "rule_code varchar(60) NOT NULL, jurisdiction varchar(80) NOT NULL DEFAULT 'ET-FEDERAL', rule_kind varchar(40) NOT NULL, title varchar(190) NOT NULL, version_label varchar(80) NOT NULL, source_title varchar(255) NOT NULL, source_clause varchar(120) NOT NULL, official_url varchar(500) NOT NULL, source_sha256 char(64) NULL, publication_date date NULL, effective_from date NOT NULL, effective_to date NULL, tax_base_basis varchar(60) NULL, rounding_method varchar(30) NOT NULL DEFAULT 'HALF_UP', reviewer_user_id bigint unsigned NULL, professional_signoff_ref varchar(255) NULL, test_vector_sha256 char(64) NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, approved_hash char(64) NULL, status varchar(24) NOT NULL DEFAULT 'LEGAL_REVIEW'",
				'keys' => 'UNIQUE KEY rule_version (rule_code,version_label), KEY kind_dates (rule_kind,effective_from,effective_to,status)',
			),
			'rule_components' => array(
				'fields' => "rule_set_id bigint unsigned NOT NULL, component_code varchar(80) NOT NULL, component_kind varchar(40) NOT NULL, parameters_json longtext NOT NULL, sort_order smallint NOT NULL DEFAULT 0, test_case_ref varchar(255) NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY rule_component (rule_set_id,component_code), KEY component_order (rule_set_id,sort_order)',
			),
			'payroll_periods' => array(
				'fields' => "period_code varchar(50) NOT NULL, period_kind varchar(24) NOT NULL DEFAULT 'REGULAR', period_start date NOT NULL, period_end date NOT NULL, input_cutoff datetime NOT NULL, payment_date date NULL, locked_at datetime NULL, locked_by bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'OPEN'",
				'keys' => 'UNIQUE KEY period_code (period_code), KEY period_dates (period_start,period_end,status)',
			),
			'payroll_runs' => array(
				'fields' => "run_code varchar(70) NOT NULL, period_id bigint unsigned NOT NULL, separation_case_id bigint unsigned NULL, run_version int unsigned NOT NULL DEFAULT 1, run_kind varchar(24) NOT NULL DEFAULT 'REGULAR', source_snapshot_json longtext NULL, source_snapshot_sha256 char(64) NULL, calculator_version varchar(60) NOT NULL, rule_set_id bigint unsigned NOT NULL, gross_total decimal(14,2) NOT NULL DEFAULT 0, taxable_total decimal(14,2) NOT NULL DEFAULT 0, tax_total decimal(14,2) NOT NULL DEFAULT 0, employee_pension_total decimal(14,2) NOT NULL DEFAULT 0, employer_pension_total decimal(14,2) NOT NULL DEFAULT 0, deductions_total decimal(14,2) NOT NULL DEFAULT 0, net_total decimal(14,2) NOT NULL DEFAULT 0, employer_cost_total decimal(14,2) NOT NULL DEFAULT 0, exception_count int unsigned NOT NULL DEFAULT 0, submitted_by bigint unsigned NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, posted_journal_id bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY run_code (run_code), UNIQUE KEY period_version (period_id,run_version), KEY period_status (period_id,status), KEY rule_set (rule_set_id), KEY separation_case (separation_case_id)',
			),
			'payroll_line_details' => array(
				'fields' => "run_id bigint unsigned NOT NULL, person_id bigint unsigned NOT NULL, component_id bigint unsigned NULL, line_kind varchar(40) NOT NULL, label varchar(190) NOT NULL, quantity decimal(12,4) NOT NULL DEFAULT 1, unit_rate decimal(14,4) NULL, base_amount decimal(14,2) NOT NULL DEFAULT 0, amount decimal(14,2) NOT NULL DEFAULT 0, taxable_base decimal(14,2) NOT NULL DEFAULT 0, pensionable_base decimal(14,2) NOT NULL DEFAULT 0, rule_set_id bigint unsigned NULL, rule_version varchar(80) NULL, calculation_trace_json longtext NULL, source_type varchar(40) NULL, source_id bigint unsigned NULL, idempotency_key varchar(120) NOT NULL, correction_of_id bigint unsigned NULL",
				'keys' => 'UNIQUE KEY idempotency_key (idempotency_key), KEY run_person (run_id,person_id), KEY person_run (person_id,run_id), KEY source_ref (source_type,source_id)',
			),
			'payroll_approvals' => array(
				'fields' => "approval_uuid char(36) NOT NULL, run_id bigint unsigned NOT NULL, stage varchar(32) NOT NULL, decision varchar(24) NOT NULL, actor_user_id bigint unsigned NOT NULL, reason text NULL, run_hash char(64) NOT NULL, request_key varchar(100) NOT NULL",
				'keys' => 'UNIQUE KEY approval_uuid (approval_uuid), UNIQUE KEY request_key (request_key), KEY run_stage (run_id,stage), KEY actor_time (actor_user_id,created_at)',
			),
			'payroll_disbursements' => array(
				'fields' => "disbursement_uuid char(36) NOT NULL, run_id bigint unsigned NOT NULL, person_id bigint unsigned NOT NULL, amount decimal(14,2) NOT NULL, currency char(3) NOT NULL DEFAULT 'ETB', payment_method varchar(20) NOT NULL, external_batch_ref varchar(120) NULL, external_payment_ref varchar(120) NULL, masked_destination varchar(100) NULL, idempotency_key varchar(120) NOT NULL, submitted_at datetime NULL, settled_at datetime NULL, failure_code varchar(80) NULL, reconciliation_ref varchar(120) NULL, journal_id bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'PENDING'",
				'keys' => 'UNIQUE KEY disbursement_uuid (disbursement_uuid), UNIQUE KEY idempotency_key (idempotency_key), UNIQUE KEY external_payment_ref (external_payment_ref), KEY run_status (run_id,status), KEY person_run (person_id,run_id)',
			),
			'disbursement_attempts' => array(
				'fields' => "disbursement_id bigint unsigned NOT NULL, attempt_no int unsigned NOT NULL, action varchar(24) NOT NULL, external_payment_ref varchar(120) NULL, response_code varchar(80) NULL, evidence_ref varchar(255) NULL, actor_user_id bigint unsigned NOT NULL, attempted_at datetime NOT NULL, request_uuid char(36) NOT NULL, idempotency_key varchar(120) NOT NULL",
				'keys' => 'UNIQUE KEY request_uuid (request_uuid), UNIQUE KEY idempotency_key (idempotency_key), UNIQUE KEY attempt_no (disbursement_id,attempt_no), KEY external_payment_ref (external_payment_ref)',
			),
			'remittances' => array(
				'fields' => "remittance_uuid char(36) NOT NULL, run_id bigint unsigned NULL, remittance_kind varchar(30) NOT NULL, liability_period varchar(30) NOT NULL, amount decimal(14,2) NOT NULL, currency char(3) NOT NULL DEFAULT 'ETB', due_date date NULL, external_return_ref varchar(120) NULL, payment_ref varchar(120) NULL, evidence_ref varchar(255) NULL, reconciled_by bigint unsigned NULL, reconciled_at datetime NULL, status varchar(24) NOT NULL DEFAULT 'PENDING'",
				'keys' => 'UNIQUE KEY remittance_uuid (remittance_uuid), KEY kind_period (remittance_kind,liability_period), KEY due_status (due_date,status)',
			),
			'separation_cases' => array(
				'fields' => "case_code varchar(60) NOT NULL, person_id bigint unsigned NOT NULL, exit_type varchar(32) NOT NULL, reason_code varchar(60) NOT NULL, notice_date date NULL, proposed_exit_date date NOT NULL, approved_exit_date date NULL, legal_review_ref varchar(255) NULL, clearance_json longtext NULL, leave_reconciled tinyint(1) NOT NULL DEFAULT 0, final_run_id bigint unsigned NULL, final_statement_ref varchar(255) NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, settled_at datetime NULL, access_revoked_at datetime NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY case_code (case_code), KEY person_status (person_id,status), KEY exit_date (proposed_exit_date,status)',
			),
			'learning_records' => array(
				'fields' => "person_id bigint unsigned NOT NULL, record_code varchar(60) NOT NULL, learning_kind varchar(40) NOT NULL, title varchar(190) NOT NULL, provider varchar(150) NULL, started_on date NULL, completed_on date NULL, expires_on date NULL, evidence_ref varchar(255) NULL, status varchar(24) NOT NULL DEFAULT 'PLANNED'",
				'keys' => 'UNIQUE KEY record_code (record_code), KEY person_expiry (person_id,expires_on)',
			),
			'performance_reviews' => array(
				'fields' => "review_code varchar(60) NOT NULL, person_id bigint unsigned NOT NULL, period_start date NOT NULL, period_end date NOT NULL, reviewer_user_id bigint unsigned NOT NULL, rubric_version varchar(60) NOT NULL, ratings_json longtext NOT NULL, evidence_ref varchar(255) NULL, outcome_code varchar(40) NULL, status varchar(24) NOT NULL DEFAULT 'DRAFT'",
				'keys' => 'UNIQUE KEY review_code (review_code), KEY person_period (person_id,period_start,period_end)',
			),
			'individual_payees' => array(
				'fields' => "payee_code varchar(60) NOT NULL, payee_kind varchar(30) NOT NULL, display_name varchar(190) NOT NULL, contact_email varchar(190) NULL, contact_phone varchar(50) NULL, taxpayer_identity_ref varchar(255) NULL, tin_status varchar(24) NOT NULL DEFAULT 'UNKNOWN', business_license_status varchar(24) NOT NULL DEFAULT 'UNKNOWN', residency_code varchar(40) NULL, privacy_notice_version varchar(60) NULL, retention_review_on date NULL, status varchar(24) NOT NULL DEFAULT 'ACTIVE'",
				'keys' => 'UNIQUE KEY payee_code (payee_code), KEY payee_kind_status (payee_kind,status), KEY tin_status (tin_status,business_license_status)',
			),
			'payee_engagements' => array(
				'fields' => "engagement_code varchar(60) NOT NULL, payee_id bigint unsigned NOT NULL, engagement_kind varchar(40) NOT NULL, campaign_code varchar(60) NULL, contract_ref varchar(255) NULL, location_id bigint unsigned NULL, requested_by bigint unsigned NOT NULL, agreed_on date NULL, service_from date NOT NULL, service_to date NULL, cash_fee decimal(14,2) NOT NULL DEFAULT 0, noncash_value decimal(14,2) NOT NULL DEFAULT 0, currency char(3) NOT NULL DEFAULT 'ETB', acceptance_required tinyint(1) NOT NULL DEFAULT 1, budget_ref varchar(100) NULL, status varchar(24) NOT NULL DEFAULT 'REQUESTED'",
				'keys' => 'UNIQUE KEY engagement_code (engagement_code), KEY payee_status (payee_id,status), KEY service_dates (service_from,service_to), KEY location_status (location_id,status)',
			),
			'payee_deliverables' => array(
				'fields' => "deliverable_code varchar(60) NOT NULL, engagement_id bigint unsigned NOT NULL, title varchar(190) NOT NULL, due_on date NULL, submitted_at datetime NULL, evidence_ref varchar(255) NULL, accepted_by bigint unsigned NULL, accepted_at datetime NULL, acceptance_note text NULL, cash_value decimal(14,2) NOT NULL DEFAULT 0, noncash_value decimal(14,2) NOT NULL DEFAULT 0, status varchar(24) NOT NULL DEFAULT 'PENDING'",
				'keys' => 'UNIQUE KEY deliverable_code (deliverable_code), KEY engagement_status (engagement_id,status)',
			),
			'payee_tax_decisions' => array(
				'fields' => "decision_code varchar(60) NOT NULL, engagement_id bigint unsigned NOT NULL, classification varchar(32) NOT NULL, rule_set_id bigint unsigned NULL, source_clause varchar(120) NULL, decision_reason text NOT NULL, withholding_rate decimal(8,6) NULL, withholding_amount decimal(14,2) NULL, base_amount decimal(14,2) NULL, reviewer_user_id bigint unsigned NOT NULL, professional_signoff_ref varchar(255) NULL, evidence_ref varchar(255) NULL, decided_at datetime NOT NULL, status varchar(24) NOT NULL DEFAULT 'LEGAL_REVIEW'",
				'keys' => 'UNIQUE KEY decision_code (decision_code), KEY engagement_status (engagement_id,status), KEY rule_set (rule_set_id)',
			),
			'payee_payments' => array(
				'fields' => "payment_code varchar(60) NOT NULL, engagement_id bigint unsigned NOT NULL, payee_id bigint unsigned NOT NULL, deliverable_id bigint unsigned NULL, tax_decision_id bigint unsigned NULL, gross_amount decimal(14,2) NOT NULL, withholding_amount decimal(14,2) NOT NULL DEFAULT 0, net_amount decimal(14,2) NOT NULL, currency char(3) NOT NULL DEFAULT 'ETB', payment_method varchar(20) NOT NULL, payment_ref varchar(120) NULL, idempotency_key varchar(120) NOT NULL, journal_id bigint unsigned NULL, paid_at datetime NULL, remittance_id bigint unsigned NULL, status varchar(24) NOT NULL DEFAULT 'REQUESTED'",
				'keys' => 'UNIQUE KEY payment_code (payment_code), UNIQUE KEY idempotency_key (idempotency_key), UNIQUE KEY payment_ref (payment_ref), KEY engagement_status (engagement_id,status), KEY payee_status (payee_id,status)',
			),
			'social_accounts' => array(
				'fields' => "account_code varchar(60) NOT NULL, platform varchar(40) NOT NULL, display_label varchar(190) NOT NULL, official_url varchar(500) NOT NULL, owner_user_id bigint unsigned NULL, manager_user_id bigint unsigned NULL, permitted_scopes_json longtext NULL, last_snapshot_at datetime NULL, status varchar(24) NOT NULL DEFAULT 'ACTIVE'",
				'keys' => 'UNIQUE KEY account_code (account_code), KEY platform_status (platform,status), KEY manager_user (manager_user_id)',
			),
			'campaigns' => array(
				'fields' => "campaign_code varchar(60) NOT NULL, title varchar(190) NOT NULL, account_id bigint unsigned NULL, payee_id bigint unsigned NULL, engagement_id bigint unsigned NULL, start_date date NOT NULL, end_date date NULL, cash_budget decimal(14,2) NOT NULL DEFAULT 0, gifted_stock_cost decimal(14,2) NOT NULL DEFAULT 0, currency char(3) NOT NULL DEFAULT 'ETB', owner_user_id bigint unsigned NOT NULL, status varchar(24) NOT NULL DEFAULT 'PLANNED'",
				'keys' => 'UNIQUE KEY campaign_code (campaign_code), KEY dates_status (start_date,end_date,status), KEY account_status (account_id,status)',
			),
			'campaign_snapshots' => array(
				'fields' => "campaign_id bigint unsigned NOT NULL, period_start date NOT NULL, period_end date NOT NULL, captured_at datetime NOT NULL, source_platform varchar(40) NOT NULL, source_ref varchar(255) NULL, metrics_json longtext NOT NULL, freshness_status varchar(24) NOT NULL DEFAULT 'CURRENT', captured_by bigint unsigned NOT NULL",
				'keys' => 'KEY campaign_period (campaign_id,period_start,period_end), KEY captured_at (captured_at,freshness_status)',
			),
			'resource_documents' => array(
				'fields' => "document_code varchar(80) NOT NULL, function_name varchar(100) NOT NULL, role_code varchar(80) NULL, language_code varchar(12) NOT NULL DEFAULT 'en', document_type varchar(40) NOT NULL, version_label varchar(40) NOT NULL, title varchar(190) NOT NULL, state varchar(24) NOT NULL DEFAULT 'DRAFT', private_file_ref varchar(255) NOT NULL, file_sha256 char(64) NOT NULL, mime_type varchar(120) NOT NULL, size_bytes bigint unsigned NOT NULL, owner_user_id bigint unsigned NOT NULL, effective_on date NULL, supersedes_id bigint unsigned NULL, legal_source_ref varchar(255) NULL, access_class varchar(24) NOT NULL DEFAULT 'HR'",
				'keys' => 'UNIQUE KEY document_version (document_code,version_label,language_code), KEY state_function (state,function_name), KEY role_language (role_code,language_code)',
			),
			'migration_map' => array(
				'fields' => "source_release varchar(40) NOT NULL, source_table varchar(120) NOT NULL, source_id bigint unsigned NOT NULL, target_table varchar(120) NOT NULL, target_id bigint unsigned NULL, source_row_sha256 char(64) NULL, migration_batch varchar(80) NOT NULL, disposition varchar(24) NOT NULL DEFAULT 'PENDING', review_note_code varchar(80) NULL",
				'keys' => 'UNIQUE KEY source_identity (source_release,source_table,source_id), KEY target_ref (target_table,target_id), KEY batch_disposition (migration_batch,disposition)',
			),
		);
	}

	public static function all_table_keys() {
		return array_keys( self::definitions() );
	}

	public static function install() {
		global $wpdb;
		if ( ! current_user_can( 'stockland_install_schema' ) ) {
			return new WP_Error( 'hrx_schema_permission', 'Only an authorized schema installer may initialize the new HR schema.', array( 'status' => 403 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$errors = array();
		foreach ( self::definitions() as $suffix => $definition ) {
			$table = self::table( $suffix );
			$sql = self::ddl( $table, $definition['fields'], $definition['keys'], $charset );
			$wpdb->last_error = '';
			dbDelta( $sql );
			if ( ! empty( $wpdb->last_error ) || ! Stockland_DB::table_exists( $table ) ) {
				$errors[] = array( 'table' => $table, 'error' => (string) $wpdb->last_error );
			}
		}
		$missing = self::missing_tables();
		if ( $missing || $errors ) {
			return new WP_Error( 'hrx_schema_incomplete', 'HR replacement schema installation did not verify cleanly; no HR cutover is enabled.', array( 'status' => 500, 'missing_tables' => $missing, 'errors' => $errors ) );
		}
		update_option( self::OPTION_VERSION, self::SCHEMA_VERSION, false );
		update_option( 'stockland_hr_replacement_active', 0, false );
		return array( 'schema_version' => self::SCHEMA_VERSION, 'table_count' => count( self::all_table_keys() ), 'missing_tables' => array(), 'cutover_enabled' => false );
	}

	private static function ddl( $table, $fields, $keys, $charset ) {
		return "CREATE TABLE {$table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
{$fields},
row_version int(10) unsigned NOT NULL DEFAULT 1,
created_by bigint(20) unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
{$keys}
) {$charset};";
	}

	public static function missing_tables() {
		$missing = array();
		foreach ( self::all_table_keys() as $suffix ) {
			$table = self::table( $suffix );
			if ( ! Stockland_DB::table_exists( $table ) ) { $missing[] = $table; }
		}
		return $missing;
	}

	public static function schema_status() {
		$missing = self::missing_tables();
		return array(
			'expected_tables' => count( self::all_table_keys() ),
			'present_tables' => count( self::all_table_keys() ) - count( $missing ),
			'missing_tables' => $missing,
			'schema_version' => (string) get_option( self::OPTION_VERSION, '' ),
			'schema_ready' => empty( $missing ) && self::SCHEMA_VERSION === get_option( self::OPTION_VERSION, '' ),
			'cutover_active' => (bool) get_option( 'stockland_hr_replacement_active', 0 ),
			'legacy_tables_changed' => false,
		);
	}

	/**
	 * Activate only after caller has separately verified staging acceptance.
	 * No data migration, legacy writes, or cleanup happens in this method.
	 */
	public static function set_cutover_active( $active ) {
		if ( ! Stockland_Authority::is_owner() || ! current_user_can( 'stockland_install_schema' ) ) {
			return new WP_Error( 'hrx_cutover_permission', 'Owner-level cutover permission is required.', array( 'status' => 403 ) );
		}
		if ( $active && ! self::schema_status()['schema_ready'] ) {
			return new WP_Error( 'hrx_schema_not_ready', 'The target HR schema is not completely installed and verified.', array( 'status' => 409 ) );
		}
		update_option( 'stockland_hr_replacement_active', $active ? 1 : 0, false );
		return array( 'cutover_active' => (bool) get_option( 'stockland_hr_replacement_active', 0 ) );
	}

	public static function integrity_summary() {
		global $wpdb;
		$missing = self::missing_tables();
		if ( $missing ) { return array( 'ready' => false, 'missing_tables' => $missing, 'orphan_counts' => array(), 'query_errors' => array() ); }
		$checks = array(
			'contracts_person' => array( 'child' => 'employment_contracts', 'column' => 'person_id', 'parent' => 'people' ),
			'applications_person' => array( 'child' => 'applications', 'column' => 'person_id', 'parent' => 'people' ),
			'leave_request_person' => array( 'child' => 'leave_requests', 'column' => 'person_id', 'parent' => 'people' ),
			'comp_assignment_person' => array( 'child' => 'compensation_assignments', 'column' => 'person_id', 'parent' => 'people' ),
			'payroll_run_period' => array( 'child' => 'payroll_runs', 'column' => 'period_id', 'parent' => 'payroll_periods' ),
			'payroll_line_run' => array( 'child' => 'payroll_line_details', 'column' => 'run_id', 'parent' => 'payroll_runs' ),
			'payroll_line_person' => array( 'child' => 'payroll_line_details', 'column' => 'person_id', 'parent' => 'people' ),
			'separation_person' => array( 'child' => 'separation_cases', 'column' => 'person_id', 'parent' => 'people' ),
			'payee_engagement' => array( 'child' => 'payee_engagements', 'column' => 'payee_id', 'parent' => 'individual_payees' ),
			'payee_payment_engagement' => array( 'child' => 'payee_payments', 'column' => 'engagement_id', 'parent' => 'payee_engagements' ),
		);
		$counts = array(); $errors = array();
		foreach ( $checks as $key => $check ) {
			$child = self::table( $check['child'] ); $parent = self::table( $check['parent'] ); $column = $check['column'];
			$sql = $wpdb->prepare( "SELECT COUNT(*) FROM %i c LEFT JOIN %i p ON p.id=c.%i WHERE c.%i IS NOT NULL AND p.id IS NULL", $child, $parent, $column, $column );
			$value = Stockland_DB::read_var( $sql );
			if ( null === $value || '' !== (string) $wpdb->last_error ) { $errors[] = $key; }
			else { $counts[$key] = (int) $value; }
		}
		return array( 'ready' => empty( $errors ) && empty( array_filter( $counts ) ), 'missing_tables' => array(), 'orphan_counts' => $counts, 'query_errors' => $errors );
	}
}
