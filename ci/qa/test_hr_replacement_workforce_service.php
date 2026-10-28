<?php
/**
 * Synthetic unit contract for the HR replacement workforce-planning vertical slice.
 * Runs without WordPress or a database; it is not a substitute for staging E2E.
 */
define( 'ABSPATH', __DIR__ );

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code, $message, $data = array() ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function rest_ensure_response( $value ) { return $value; }
function current_time( $format, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function get_current_user_id() { return (int) $GLOBALS['hrx_test_user_id']; }
function sanitize_email( $value ) { return strtolower( trim( filter_var( (string) $value, FILTER_SANITIZE_EMAIL ) ) ); }
function is_email( $value ) { return false !== filter_var( (string) $value, FILTER_VALIDATE_EMAIL ); }
function get_userdata( $id ) { return in_array( (int) $id, array( 7, 8, 22, 1 ), true ) ? (object) array( 'ID' => (int) $id ) : false; }
function get_option( $key, $default = false ) { return isset( $GLOBALS['hrx_test_options'][ $key ] ) ? $GLOBALS['hrx_test_options'][ $key ] : $default; }

class Stockland_HR_Replacement_Workforce_Schema {
	public static function table_names() { return array(); }
}

final class HRX_Test_Access {
	public $locations = array( 3 );
	public function assert_branch( $location_id ) {
		if ( ! in_array( (int) $location_id, $this->locations, true ) ) {
			return new WP_Error( 'branch_forbidden', 'outside scope', array( 'status' => 403 ) );
		}
		return true;
	}
	public function scoped_location_ids() { return $this->locations; }
	public function company_scope() { return false; }
}

final class HRX_Test_Repository {
	public $tables = array(
		'org_units' => array(),
		'workforce_plans' => array(),
		'plan_lines' => array(),
		'positions' => array(),
		'requisitions' => array(),
		'workflow_events' => array(),
		'command_receipts' => array(),
		'people' => array(),
		'candidates' => array(),
		'applications' => array(),
		'interviews' => array(),
		'selection_decisions' => array(),
		'employments' => array(),
		'contracts' => array(),
		'documents' => array(),
		'onboarding_tasks' => array(),
	);
	private $next = array();
	private $snapshot = null;

	public function begin() { $this->snapshot = unserialize( serialize( array( $this->tables, $this->next ) ) ); }
	public function commit() { $this->snapshot = null; }
	public function rollback() {
		if ( null !== $this->snapshot ) {
			list( $this->tables, $this->next ) = $this->snapshot;
			$this->snapshot = null;
		}
	}
	private function id( $table ) {
		if ( ! isset( $this->next[ $table ] ) ) $this->next[ $table ] = 0;
		return ++$this->next[ $table ];
	}
	public function receipt( $key ) {
		foreach ( $this->tables['command_receipts'] as $row ) if ( $row['idempotency_key'] === $key ) return $row;
		return null;
	}
	public function create_receipt( $key, $actor, $operation, $hash ) {
		$id = $this->id( 'command_receipts' );
		$this->tables['command_receipts'][ $id ] = array(
			'id'=>$id, 'idempotency_key'=>$key, 'actor_id'=>$actor, 'operation_code'=>sanitize_key( $operation ),
			'request_sha256'=>$hash, 'result_status'=>'PROCESSING', 'result_json'=>null,
		);
		return $id;
	}
	public function finish_receipt( $id, $result, $entity_type = null, $entity_id = null ) {
		if ( ! isset( $this->tables['command_receipts'][ $id ] ) || 'PROCESSING' !== $this->tables['command_receipts'][ $id ]['result_status'] ) {
			throw new RuntimeException( 'receipt state conflict' );
		}
		$this->tables['command_receipts'][ $id ]['result_status'] = 'COMPLETED';
		$this->tables['command_receipts'][ $id ]['result_json'] = json_encode( $result );
	}
	public function get( $entity, $id, $for_update = false ) {
		return isset( $this->tables[ $entity ][ (int) $id ] ) ? $this->tables[ $entity ][ (int) $id ] : null;
	}
	public function estimated_annual_cost_for_plan( $plan_id ) {
		$minor = 0;
		foreach ( $this->tables['plan_lines'] as $line ) {
			if ( (int) $line['plan_id'] !== (int) $plan_id ) continue;
			$parts = explode( '.', (string) $line['estimated_monthly_cost_etb'], 2 );
			$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 2, '0' ) : '00';
			$line_minor = ( (int) $parts[0] * 100 ) + (int) $fraction;
			$minor += $line_minor * (int) $line['openings'] * 12;
		}
		return (string) intdiv( $minor, 100 ) . '.' . str_pad( (string) ( $minor % 100 ), 2, '0', STR_PAD_LEFT );
	}

	public function find_org_unit( $id ) { return $this->get( 'org_units', $id ); }
	public function list_rows( $entity, $filters = array(), $location_ids = array(), $company_scope = false ) {
		$rows = array_values( $this->tables[ $entity ] );
		foreach ( $filters as $key => $value ) {
			$rows = array_values( array_filter( $rows, function ( $row ) use ( $key, $value ) { return isset( $row[ $key ] ) && (string) $row[ $key ] === (string) $value; } ) );
		}
		if ( ! $company_scope && in_array( $entity, array( 'workforce_plans', 'plan_lines', 'positions', 'requisitions', 'applications', 'employments' ), true ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) use ( $location_ids ) { return in_array( (int) $row['location_id'], $location_ids, true ); } ) );
		} elseif ( ! $company_scope && in_array( $entity, array( 'interviews', 'selection_decisions' ), true ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) use ( $location_ids ) {
				$app = $this->get( 'applications', (int) $row['application_id'] );
				return $app && in_array( (int) $app['location_id'], $location_ids, true );
			} ) );
		} elseif ( ! $company_scope && in_array( $entity, array( 'contracts', 'onboarding_tasks' ), true ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) use ( $location_ids ) {
				$employment = $this->get( 'employments', (int) $row['employment_id'] );
				return $employment && in_array( (int) $employment['location_id'], $location_ids, true );
			} ) );
		}
		return $rows;
	}
	public function insert( $entity, $data ) {
		$id = $this->id( $entity );
		$data['id'] = $id;
		$this->tables[ $entity ][ $id ] = $data;
		return $this->tables[ $entity ][ $id ];
	}
	public function update_versioned( $entity, $id, $expected_version, $data ) {
		$row = $this->get( $entity, $id );
		if ( ! $row || (int) $row['version_no'] !== (int) $expected_version ) throw new RuntimeException( 'version mismatch' );
		$row = array_merge( $row, $data );
		$row['version_no'] = (int) $expected_version + 1;
		$this->tables[ $entity ][ $id ] = $row;
		return $row;
	}
	public function transition( $entity, $id, $expected_version, $expected_state, $new_state, $changes = array() ) {
		$row = $this->get( $entity, $id );
		$state_column = 'people' === $entity ? 'person_status' : 'status';
		if ( ! $row || (int) $row['version_no'] !== (int) $expected_version || $row[ $state_column ] !== $expected_state ) {
			throw new RuntimeException( 'state/version conflict' );
		}
		$row = array_merge( $row, $changes, array( $state_column => $new_state, 'version_no' => (int) $expected_version + 1 ) );
		$this->tables[ $entity ][ $id ] = $row;
		return $row;
	}

	public function find_open_employment_for_candidate( $candidate_id ) {
		foreach ( $this->tables['employments'] as $row ) {
			if ( (int) $row['candidate_id'] === (int) $candidate_id && ! in_array( $row['status'], array( 'ENDED', 'CANCELLED', 'DECLINED', 'REJECTED', 'WITHDRAWN' ), true ) ) return $row;
		}
		return null;
	}

	public function count_applications_for_requisition( $requisition_id, $status ) {
		$count = 0;
		foreach ( $this->tables['applications'] as $row ) if ( (int) $row['requisition_id'] === (int) $requisition_id && $row['status'] === $status ) $count++;
		return $count;
	}

	public function reserve_position_slot( $position_id, $expected_version ) {
		$row = $this->get( 'positions', $position_id );
		if ( ! $row || (int) $row['version_no'] !== (int) $expected_version || 'ACTIVE' !== $row['status'] ) throw new RuntimeException( 'position version/state conflict' );
		$reserved = isset( $row['reserved_headcount'] ) ? (int) $row['reserved_headcount'] : 0;
		if ( (int) $row['filled_headcount'] + $reserved >= (int) $row['planned_headcount'] ) throw new RuntimeException( 'position has no available slots' );
		$row['reserved_headcount'] = $reserved + 1;
		$row['version_no'] = (int) $expected_version + 1;
		$row['updated_by'] = get_current_user_id();
		$row['updated_at'] = current_time( 'mysql', true );
		$this->tables['positions'][ $position_id ] = $row;
		return $row;
	}
	public function append_event( $entity, $id, $event, $from, $to, $actor, $correlation, $reason = '', $evidence = '' ) {
		if ( '' === (string) $correlation ) throw new RuntimeException( 'empty correlation id' );
		foreach ( $this->tables['workflow_events'] as $existing ) {
			if ( $existing['correlation_id'] === $correlation ) throw new RuntimeException( 'duplicate event correlation' );
		}
		$event_id = $this->id( 'workflow_events' );
		$this->tables['workflow_events'][ $event_id ] = array(
			'id'=>$event_id, 'entity_type'=>$entity, 'entity_id'=>$id, 'event_type'=>$event,
			'from_state'=>$from, 'to_state'=>$to, 'actor_id'=>$actor, 'correlation_id'=>$correlation, 'reason'=>$reason,
		);
		return $event_id;
	}
}

function hrx_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
}
function hrx_key( $n ) { return 'HRXTEST-KEY-' . str_pad( (string) $n, 12, '0', STR_PAD_LEFT ); }

require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_Workforce_Service.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_Recruitment_Service.php';

$repo = new HRX_Test_Repository();
$access = new HRX_Test_Access();
$service = new Stockland_HR_Replacement_Workforce_Service( $repo, $access );
$checks = 0;
$GLOBALS['hrx_test_user_id'] = 22; // synthetic Management maker

$plan_payload = array(
	'plan_code'=>'TEST-WFP-2026-01',
	'fiscal_year'=>'2026/27',
	'department'=>'Retail Operations',
	'location_id'=>3,
	'justification'=>'Synthetic headcount plan for validation only.',
);
$first = $service->execute_idempotent( hrx_key( 1 ), 'workforce_plan.create', $plan_payload, function () use ( $service, $plan_payload ) { return $service->create_plan( $plan_payload, hrx_key( 1 ) ); } );
hrx_assert( is_array( $first ) && 1 === (int) $first['id'] && 'DRAFT' === $first['record']['status'], 'create workforce plan starts in DRAFT' ); $checks++;
hrx_assert( count( $repo->tables['workforce_plans'] ) === 1 && count( $repo->tables['workflow_events'] ) === 1, 'creation persists one plan and one event' ); $checks++;
$replay = $service->execute_idempotent( hrx_key( 1 ), 'workforce_plan.create', $plan_payload, function () use ( $service, $plan_payload ) { return $service->create_plan( $plan_payload, hrx_key( 1 ) ); } );
hrx_assert( $replay === $first && count( $repo->tables['workforce_plans'] ) === 1, 'same idempotency key returns the exact prior result without duplicate creation' ); $checks++;
$conflict = $service->execute_idempotent( hrx_key( 1 ), 'workforce_plan.create', array_merge( $plan_payload, array( 'department'=>'Finance' ) ), function () { return array(); } );
hrx_assert( is_wp_error( $conflict ) && 'hrx_idempotency_key_conflict' === $conflict->code, 'reusing key for a different request is rejected' ); $checks++;

$line_payload = array(
	'line_code'=>'LINE-001', 'position_title'=>'Branch Supervisor', 'openings'=>2,
	'estimated_monthly_cost_etb'=>'1234.50', 'business_case'=>'Coverage expansion for planned retail opening.',
);
$line = $service->execute_idempotent( hrx_key( 2 ), 'workforce_plan_line.create', $line_payload, function () use ( $service, $first, $line_payload ) { return $service->create_plan_line( $first['id'], $line_payload, hrx_key( 2 ) ); } );
hrx_assert( is_array( $line ) && '1234.50' === $line['record']['estimated_monthly_cost_etb'], 'ETB amounts are normalized to exact two-decimal strings' ); $checks++;

$own_submit = $service->execute_idempotent( hrx_key( 3 ), 'workforce_plan.submit', array( 'version_no'=>1 ), function () use ( $service, $first ) { return $service->submit_plan( $first['id'], array( 'version_no'=>1 ), hrx_key( 3 ) ); } );
hrx_assert( is_array( $own_submit ) && 'SUBMITTED' === $own_submit['record']['status'], 'complete draft plan can be submitted with optimistic version check' ); $checks++;
$before_events = count( $repo->tables['workflow_events'] );
$own_approval = $service->execute_idempotent( hrx_key( 4 ), 'workforce_plan.decide', array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Approve this plan with complete budget review.', 'budget_reference'=>'BUDGET-SYNTH-01', 'approved_budget_etb'=>'10000.00' ), function () use ( $service, $first ) { return $service->decide_plan( $first['id'], array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Approve this plan with complete budget review.', 'budget_reference'=>'BUDGET-SYNTH-01', 'approved_budget_etb'=>'10000.00' ), hrx_key( 4 ) ); } );
hrx_assert( is_wp_error( $own_approval ) && 'hrx_self_approval_blocked' === $own_approval->code && 'SUBMITTED' === $repo->get( 'workforce_plans', $first['id'] )['status'], 'maker cannot approve own plan and rollback preserves SUBMITTED state' ); $checks++;

$GLOBALS['hrx_test_user_id'] = 1; // synthetic independent Owner
$underbudget_payload = array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Budget checked against approved headcount and monthly plan.', 'budget_reference'=>'BUDGET-SYNTH-01', 'approved_budget_etb'=>'10000.00' );
$underbudget = $service->execute_idempotent( hrx_key( 14 ), 'workforce_plan.decide', $underbudget_payload, function () use ( $service, $first, $underbudget_payload ) { return $service->decide_plan( $first['id'], $underbudget_payload, hrx_key( 14 ) ); } );
hrx_assert( is_wp_error( $underbudget ) && 'hrx_budget_below_plan_cost' === $underbudget->code && 'SUBMITTED' === $repo->get( 'workforce_plans', $first['id'] )['status'], 'Owner cannot approve a budget below annualized plan cost' ); $checks++;
$approved = $service->execute_idempotent( hrx_key( 5 ), 'workforce_plan.decide', array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Budget checked against approved headcount and monthly plan.', 'budget_reference'=>'BUDGET-SYNTH-01', 'approved_budget_etb'=>'30000.00' ), function () use ( $service, $first ) { return $service->decide_plan( $first['id'], array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Budget checked against approved headcount and monthly plan.', 'budget_reference'=>'BUDGET-SYNTH-01', 'approved_budget_etb'=>'30000.00' ), hrx_key( 5 ) ); } );
hrx_assert( is_array( $approved ) && 'APPROVED' === $approved['record']['status'] && 'APPROVED' === $repo->get( 'plan_lines', $line['id'] )['status'], 'independent Owner approval gates plan and plan line' ); $checks++;
$GLOBALS['hrx_test_user_id'] = 22; // Management prepares the position; Owner approved the budget above.
$position_payload = array( 'position_code'=>'POS-SYNTH-001', 'workforce_plan_id'=>$first['id'], 'plan_line_id'=>$line['id'] );
$position = $service->execute_idempotent( hrx_key( 6 ), 'position.create', $position_payload, function () use ( $service, $position_payload ) { return $service->create_position( $position_payload, hrx_key( 6 ) ); } );
hrx_assert( is_array( $position ) && 'DRAFT' === $position['record']['status'] && (int)$position['record']['planned_headcount'] === 2, 'position can only be created from approved plan line and carries headcount' ); $checks++;
$position_submit = $service->execute_idempotent( hrx_key( 7 ), 'position.submit', array( 'version_no'=>1 ), function () use ( $service, $position ) { return $service->submit_position( $position['id'], array( 'version_no'=>1 ), hrx_key( 7 ) ); } );
hrx_assert( is_array( $position_submit ) && 'SUBMITTED' === $position_submit['record']['status'], 'position submission changes state' ); $checks++;
$GLOBALS['hrx_test_user_id'] = 1; // Owner independently approves the position prepared by Management.
$position_approved = $service->execute_idempotent( hrx_key( 8 ), 'position.decide', array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Approved against the signed workforce plan and budget.', 'approval_reference'=>'POS-APPROVAL-SYNTH-01' ), function () use ( $service, $position ) { return $service->decide_position( $position['id'], array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Approved against the signed workforce plan and budget.', 'approval_reference'=>'POS-APPROVAL-SYNTH-01' ), hrx_key( 8 ) ); } );
hrx_assert( is_array( $position_approved ) && 'APPROVED' === $position_approved['record']['status'], 'position requires separate approval rationale and reference' ); $checks++;
$position_active = $service->execute_idempotent( hrx_key( 9 ), 'position.activate', array( 'version_no'=>3 ), function () use ( $service, $position ) { return $service->activate_position( $position['id'], array( 'version_no'=>3 ), hrx_key( 9 ) ); } );
hrx_assert( is_array( $position_active ) && 'ACTIVE' === $position_active['record']['status'], 'position activation requires approved status and reference' ); $checks++;

$GLOBALS['hrx_test_user_id'] = 22; // Management opens the request; Owner provides the independent decision below.
$req_payload = array( 'requisition_code'=>'REQ-SYNTH-001', 'position_id'=>$position['id'], 'openings'=>1, 'justification'=>'Backfill required for planned store operations.' );
$req = $service->execute_idempotent( hrx_key( 10 ), 'vacancy_requisition.create', $req_payload, function () use ( $service, $req_payload ) { return $service->create_requisition( $req_payload, hrx_key( 10 ) ); } );
hrx_assert( is_array( $req ) && 'DRAFT' === $req['record']['status'], 'vacancy requisition is bound to approved active position' ); $checks++;
$req_submit = $service->execute_idempotent( hrx_key( 11 ), 'vacancy_requisition.submit', array( 'version_no'=>1 ), function () use ( $service, $req ) { return $service->submit_requisition( $req['id'], array( 'version_no'=>1 ), hrx_key( 11 ) ); } );
hrx_assert( is_array( $req_submit ) && 'SUBMITTED' === $req_submit['record']['status'], 'vacancy requisition submits independently' ); $checks++;
$GLOBALS['hrx_test_user_id'] = 1; // Independent Owner approval of the requisition.
$req_approved = $service->execute_idempotent( hrx_key( 12 ), 'vacancy_requisition.decide', array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Approved after confirming remaining approved headcount.', 'budget_reference'=>'REQ-BUDGET-SYNTH-01' ), function () use ( $service, $req ) { return $service->decide_requisition( $req['id'], array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Approved after confirming remaining approved headcount.', 'budget_reference'=>'REQ-BUDGET-SYNTH-01' ), hrx_key( 12 ) ); } );
hrx_assert( is_array( $req_approved ) && 'APPROVED' === $req_approved['record']['status'], 'vacancy approval requires independent reviewer and budget reference' ); $checks++;
$opened = $service->execute_idempotent( hrx_key( 13 ), 'vacancy_requisition.open', array( 'version_no'=>3 ), function () use ( $service, $req ) { return $service->open_requisition( $req['id'], array( 'version_no'=>3 ), hrx_key( 13 ) ); } );
hrx_assert( is_array( $opened ) && 'OPEN' === $opened['record']['status'], 'approved requisition can be opened for recruitment' ); $checks++;

$events = array_values( $repo->tables['workflow_events'] );
$correlations = array_map( function ( $event ) { return $event['correlation_id']; }, $events );
hrx_assert( count( $events ) === count( array_unique( $correlations ) ), 'workflow event correlation IDs are unique' ); $checks++;
hrx_assert( count( $events ) >= 13, 'all completed transitions, line decisions and draft changes produce auditable events' ); $checks++;
hrx_assert( count( $repo->tables['command_receipts'] ) === 12, 'every successful mutating command has one idempotency receipt; rejected self-approval leaves no receipt' ); $checks++;


/* Recruitment test continues against the same in-memory transaction model. */
$recruitment = new Stockland_HR_Replacement_Recruitment_Service( $repo, $access, $service );
$GLOBALS['hrx_test_options']['stockland_hrx_legal_employer_code'] = 'STOCKLAND_ET_PLC';
$GLOBALS['hrx_test_user_id'] = 22;
$candidate_payload = array(
	'person_code'=>'PERSON-SYNTH-001',
	'candidate_code'=>'CAND-SYNTH-001',
	'full_name'=>'Synthetic Candidate',
	'preferred_name'=>'Candidate',
	'primary_email'=>'candidate@example.com',
	'primary_phone'=>'+251900000000',
	'privacy_notice_version'=>'HR-PRIVACY-2026-01',
	'processing_basis'=>'CONSENT',
	'processing_basis_reference'=>'PRIVACY-CONSENT-POLICY-v1',
	'source_code'=>'REFERRAL',
	'retention_until'=>'2027-10-01',
);
$candidate_result = $service->execute_idempotent( hrx_key( 14 ), 'recruitment.candidate.create', $candidate_payload, function () use ( $recruitment, $candidate_payload ) { return $recruitment->create_candidate( $candidate_payload, hrx_key( 14 ) ); } );
hrx_assert( is_array( $candidate_result ) && 'PENDING_REVIEW' === $candidate_result['status'], 'candidate starts pending privacy review' ); $checks++;
$pending_application = $service->execute_idempotent( hrx_key( 15 ), 'recruitment.application.create', array( 'candidate_id'=>$candidate_result['candidate_id'], 'requisition_id'=>$opened['id'], 'application_code'=>'APP-SYNTH-001' ), function () use ( $recruitment, $candidate_result, $opened ) { return $recruitment->create_application( array( 'candidate_id'=>$candidate_result['candidate_id'], 'requisition_id'=>$opened['id'], 'application_code'=>'APP-SYNTH-001' ), hrx_key( 15 ) ); } );
hrx_assert( is_wp_error( $pending_application ) && 'hrx_candidate_not_available' === $pending_application->code, 'candidate cannot apply before privacy approval' ); $checks++;

$GLOBALS['hrx_test_user_id'] = 1; // Independent Owner reviews privacy basis and evidence.
$privacy_payload = array(
	'confirmed_notice_version'=>'HR-PRIVACY-2026-01',
	'decision'=>'APPROVE',
	'decision_note'=>'Consent evidence and approved privacy notice were reviewed by the authorized reviewer.',
	'review_evidence_reference'=>'CONSENT-FORM-SYNTH-001',
	'consent_status'=>'RECORDED',
);
$privacy = $service->execute_idempotent( hrx_key( 16 ), 'recruitment.candidate.privacy_review', $privacy_payload, function () use ( $recruitment, $candidate_result, $privacy_payload ) { return $recruitment->review_candidate_privacy( $candidate_result['candidate_id'], $privacy_payload, hrx_key( 16 ) ); } );
hrx_assert( is_array( $privacy ) && 'ACTIVE' === $privacy['status'], 'privacy review activates candidate only with recorded consent evidence' ); $checks++;

$GLOBALS['hrx_test_user_id'] = 22;
$app_payload = array( 'candidate_id'=>$candidate_result['candidate_id'], 'requisition_id'=>$opened['id'], 'application_code'=>'APP-SYNTH-001' );
$app = $service->execute_idempotent( hrx_key( 17 ), 'recruitment.application.create', $app_payload, function () use ( $recruitment, $app_payload ) { return $recruitment->create_application( $app_payload, hrx_key( 17 ) ); } );
hrx_assert( is_array( $app ) && 'APPLIED' === $app['stage'] && 'OPEN' === $app['status'], 'application links privacy-cleared candidate to open vacancy' ); $checks++;
$duplicate_app = $service->execute_idempotent( hrx_key( 18 ), 'recruitment.application.create', $app_payload, function () use ( $recruitment, $app_payload ) { return $recruitment->create_application( $app_payload, hrx_key( 18 ) ); } );
hrx_assert( is_wp_error( $duplicate_app ) && 'hrx_duplicate_application' === $duplicate_app->code, 'duplicate candidate-to-vacancy application is rejected' ); $checks++;

$interview_payload = array(
	'interview_code'=>'INT-SYNTH-001',
	'stage_code'=>'PANEL',
	'scheduled_start'=>'2026-11-01 09:00:00',
	'scheduled_end'=>'2026-11-01 10:00:00',
	'timezone_code'=>'Africa/Addis_Ababa',
	'panel_user_ids'=>array( 7, 8 ),
);
$interview = $service->execute_idempotent( hrx_key( 19 ), 'recruitment.interview.schedule', $interview_payload, function () use ( $recruitment, $app, $interview_payload ) { return $recruitment->schedule_interview( $app['application_id'], $interview_payload, hrx_key( 19 ) ); } );
hrx_assert( is_array( $interview ) && 'PLANNED' === $interview['status'], 'interview scheduled with named panel and local time-zone' ); $checks++;

$evaluation = array( 'rubric_version'=>'RETAIL-SUPERVISOR-v1', 'ratings'=>array( array( 'criterion'=>'Customer service', 'rating'=>4 ), array( 'criterion'=>'Team leadership', 'rating'=>4 ) ), 'human_summary'=>'Strong role-relevant responses during the interview.' );
$complete_payload = array( 'evaluation'=>$evaluation, 'reviewer_summary'=>'The candidate met the defined interview criteria; retain human review.', 'evidence_reference'=>'INTERVIEW-NOTES-SYNTH-001' );
$complete = $service->execute_idempotent( hrx_key( 20 ), 'recruitment.interview.complete', $complete_payload, function () use ( $recruitment, $interview, $complete_payload ) { return $recruitment->complete_interview( $interview['interview_id'], $complete_payload, hrx_key( 20 ) ); } );
hrx_assert( is_array( $complete ) && 'COMPLETED' === $complete['status'], 'interview completion records itemized human rubric and evidence' ); $checks++;

$recommend_payload = array(
	'decision_code'=>'SEL-SYNTH-001',
	'recommendation'=>'RECOMMEND',
	'rationale'=>'Human panel recommends the candidate based on the approved interview rubric.',
	'evidence_reference'=>'PANEL-EVIDENCE-SYNTH-001',
);
$recommendation = $service->execute_idempotent( hrx_key( 21 ), 'recruitment.selection.recommend', $recommend_payload, function () use ( $recruitment, $app, $recommend_payload ) { return $recruitment->recommend_selection( $app['application_id'], $recommend_payload, hrx_key( 21 ) ); } );
hrx_assert( is_array( $recommendation ) && 'PENDING_OWNER' === $recommendation['status'], 'human recommendation requires completed interview and separate Owner decision' ); $checks++;
$self_decision_payload = array( 'decision'=>'SELECT', 'owner_decision_note'=>'The recommender cannot approve this same decision.', 'employment_type'=>'FULL_TIME', 'legal_employer_code'=>'STOCKLAND_ET_PLC', 'employment_code'=>'EMP-SYNTH-001' );
$self_decision = $service->execute_idempotent( hrx_key( 22 ), 'recruitment.selection.decide', $self_decision_payload, function () use ( $recruitment, $recommendation, $self_decision_payload ) { return $recruitment->decide_selection( $recommendation['selection_decision_id'], $self_decision_payload, hrx_key( 22 ) ); } );
hrx_assert( is_wp_error( $self_decision ) && 'hrx_selection_self_approval_blocked' === $self_decision->code, 'recommender cannot make final selection decision' ); $checks++;

$GLOBALS['hrx_test_user_id'] = 1; // Independent Owner approves a selected candidate.
$owner_decision_payload = array( 'decision'=>'SELECT', 'owner_decision_note'=>'Selection is approved after reviewing the interview evidence and role requirements.', 'employment_type'=>'FULL_TIME', 'legal_employer_code'=>'STOCKLAND_ET_PLC', 'employment_code'=>'EMP-SYNTH-001' );
$owner_decision = $service->execute_idempotent( hrx_key( 23 ), 'recruitment.selection.decide', $owner_decision_payload, function () use ( $recruitment, $recommendation, $owner_decision_payload ) { return $recruitment->decide_selection( $recommendation['selection_decision_id'], $owner_decision_payload, hrx_key( 23 ) ); } );
hrx_assert( is_array( $owner_decision ) && 'PENDING_OFFER' === $owner_decision['status'], 'Owner selection creates a pending offer employment only; it does not activate or payroll the employee' ); $checks++;
hrx_assert( 1 === (int) $repo->get( 'positions', $position['id'] )['reserved_headcount'] && 0 === (int) $repo->get( 'positions', $position['id'] )['filled_headcount'], 'selected hire reserves a position slot without treating pending offer as active headcount' ); $checks++;
hrx_assert( ! isset( $repo->tables['employees'] ) && ! isset( $repo->tables['payroll_lines'] ), 'recruitment does not write legacy employee or payroll tables' ); $checks++;
$owner_replay = $service->execute_idempotent( hrx_key( 23 ), 'recruitment.selection.decide', $owner_decision_payload, function () use ( $recruitment, $recommendation, $owner_decision_payload ) { return $recruitment->decide_selection( $recommendation['selection_decision_id'], $owner_decision_payload, hrx_key( 23 ) ); } );
hrx_assert( $owner_replay === $owner_decision && count( $repo->tables['employments'] ) === 1, 'retry of Owner selection does not create a duplicate employment' ); $checks++;

echo 'PASS HR replacement workforce + recruitment synthetic service contract: ' . $checks . " assertions.\n";
