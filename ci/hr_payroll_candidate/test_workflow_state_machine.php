<?php
require_once __DIR__ . '/Stockland_HR_Workflow_State_Machine.php';

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
};
$throws = static function ( callable $fn, $message ) use ( $assert ) {
	try {
		$fn();
	} catch ( DomainException $e ) {
		$assert( '' !== $e->getMessage(), $message . ' should explain the blocking reason' );
		return;
	}
	throw new RuntimeException( 'FAIL: expected workflow guard for ' . $message );
};
$base = array(
	'actor_id' => 20,
	'created_by' => 10,
	'approver_id' => 20,
	'current_version' => 4,
	'expected_version' => 4,
	'can_manage' => true,
	'can_approve' => true,
);
$allowed = Stockland_HR_Workflow_State_Machine::allowed_next( 'payroll_run', 'SUBMITTED' );
$assert( in_array( 'APPROVED', $allowed, true ) && in_array( 'REJECTED', $allowed, true ), 'payroll submitted states are enumerated' );
$throws( static function () { Stockland_HR_Workflow_State_Machine::allowed_next( 'payroll_run', 'UNKNOWN' ); }, 'unknown workflow state' );
$throws( static function () use ( $base ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'payroll_run', 'DRAFT', 'APPROVED', $base ); }, 'illegal direct payroll approval' );

$bad_version = $base;
$bad_version['expected_version'] = 3;
$throws( static function () use ( $bad_version ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'vacancy', 'SUBMITTED', 'APPROVED', $bad_version ); }, 'stale version' );

$no_approver = $base;
$no_approver['can_approve'] = false;
$throws( static function () use ( $no_approver ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'vacancy', 'SUBMITTED', 'APPROVED', $no_approver ); }, 'missing approval capability' );

$self = $base;
$self['created_by'] = $self['actor_id'];
$throws( static function () use ( $self ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'compensation', 'SUBMITTED', 'APPROVED', $self ); }, 'self-approval' );

$app = $base;
$app['human_decision'] = true;
$app['selection_rationale'] = 'Best role fit based on structured interview evidence.';
$decision = Stockland_HR_Workflow_State_Machine::assert_transition( 'application', 'RECOMMENDED', 'SELECTED', $app );
$assert( 'SELECTED' === $decision['to_status'] && 5 === $decision['next_version'], 'documented human candidate decision allowed' );
$app['selection_rationale'] = 'yes';
$throws( static function () use ( $app ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'application', 'RECOMMENDED', 'SELECTED', $app ); }, 'selection without rationale' );

$activation = $base + array(
	'contract_signed' => true,
	'private_documents_verified' => true,
	'onboarding_complete' => true,
	'compensation_approved' => true,
	'payroll_setup_complete' => true,
);
$activation['current_version'] = 2;
$activation['expected_version'] = 2;
$active = Stockland_HR_Workflow_State_Machine::assert_transition( 'employment', 'PENDING_ACTIVATION', 'ACTIVE', $activation );
$assert( 3 === $active['next_version'], 'employee activation increments version only after required evidence' );
$missing = $activation;
$missing['contract_signed'] = false;
$throws( static function () use ( $missing ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'employment', 'PENDING_ACTIVATION', 'ACTIVE', $missing ); }, 'activation without signed contract' );

$legal = $base + array(
	'qualified_legal_reviewer' => true,
	'test_vectors_passed' => true,
	'source_hash_valid' => true,
	'effective_dates_verified' => true,
);
$legal['current_version'] = 1;
$legal['expected_version'] = 1;
$rule = Stockland_HR_Workflow_State_Machine::assert_transition( 'legal_rule', 'LEGAL_REVIEW', 'APPROVED', $legal );
$assert( 'LEGAL_RULE_LEGAL_REVIEW_TO_APPROVED' === $rule['event_key'], 'legal approval emits a versioned audit event key' );
$legal['qualified_legal_reviewer'] = false;
$throws( static function () use ( $legal ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'legal_rule', 'LEGAL_REVIEW', 'APPROVED', $legal ); }, 'legal rule without qualified review' );

$ready = $base + array( 'preflight_complete' => true, 'period_valid' => true, 'inputs_frozen' => true, 'rule_versions_pinned' => true );
$ready['current_version'] = 1; $ready['expected_version'] = 1;
$throws( static function () use ( $base ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'payroll_run', 'DRAFT', 'READY', $base ); }, 'payroll ready without preflight' );
$ready_result = Stockland_HR_Workflow_State_Machine::assert_transition( 'payroll_run', 'DRAFT', 'READY', $ready );
$assert( 'READY' === $ready_result['to_status'], 'payroll can become ready only with frozen inputs and pinned rules' );

$payroll_approval = $base + array( 'all_lines_calculated' => true, 'rules_approved' => true, 'exceptions_clear' => true, 'totals_reconcile' => true, 'audit_ready' => true );
$throws( static function () use ( $base ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'payroll_run', 'SUBMITTED', 'APPROVED', $base ); }, 'payroll approval without reconciliation' );
$approved = Stockland_HR_Workflow_State_Machine::assert_transition( 'payroll_run', 'SUBMITTED', 'APPROVED', $payroll_approval );
$assert( 'APPROVED' === $approved['to_status'], 'reconciled payroll can proceed to independent approval' );

$payout = $base + array( 'external_confirmation_reference' => 'BANK-REF-SYNTHETIC-001', 'confirmed_amount' => '2290.00', 'source_payment_unique' => true );
$throws( static function () use ( $base ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'payout_instruction', 'SENT', 'SETTLED', $base ); }, 'settlement without external proof' );
$settled = Stockland_HR_Workflow_State_Machine::assert_transition( 'payout_instruction', 'SENT', 'SETTLED', $payout );
$assert( 'SETTLED' === $settled['to_status'], 'settlement requires external proof and unique payment' );

$exit = $base + array( 'legal_exit_basis_verified' => true, 'leave_balance_reconciled' => true, 'permitted_deductions_verified' => true, 'final_calculation_reconciled' => true, 'clearance_complete' => true );
$exit_ok = Stockland_HR_Workflow_State_Machine::assert_transition( 'exit_settlement', 'SUBMITTED', 'APPROVED', $exit );
$assert( 'APPROVED' === $exit_ok['to_status'], 'final settlement requires legal, leave, deductions, calculation and clearance evidence' );
$exit['clearance_complete'] = false;
$throws( static function () use ( $exit ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'exit_settlement', 'SUBMITTED', 'APPROVED', $exit ); }, 'exit approval without clearance' );

$payee = $base + array( 'classification_decision_approved' => true );
$payee_ok = Stockland_HR_Workflow_State_Machine::assert_transition( 'payee_request', 'AUTO_CHECK', 'APPROVED', $payee );
$assert( 'APPROVED' === $payee_ok['to_status'], 'payee request approval requires classification decision' );
$payee['classification_decision_approved'] = false;
$throws( static function () use ( $payee ) { Stockland_HR_Workflow_State_Machine::assert_transition( 'payee_request', 'AUTO_CHECK', 'APPROVED', $payee ); }, 'payee approval without classification' );

echo "PASS HR workflow: legal transitions, maker-checker, optimistic version, evidence gates, settlement proof and exit reconciliation\n";
