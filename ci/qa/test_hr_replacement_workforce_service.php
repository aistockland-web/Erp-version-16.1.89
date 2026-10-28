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
	public function find_org_unit( $id ) { return $this->get( 'org_units', $id ); }
	public function list_rows( $entity, $filters = array(), $location_ids = array(), $company_scope = false ) {
		$rows = array_values( $this->tables[ $entity ] );
		foreach ( $filters as $key => $value ) {
			$rows = array_values( array_filter( $rows, function ( $row ) use ( $key, $value ) { return isset( $row[ $key ] ) && (string) $row[ $key ] === (string) $value; } ) );
		}
		if ( ! $company_scope && in_array( $entity, array( 'workforce_plans', 'plan_lines', 'positions', 'requisitions' ), true ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) use ( $location_ids ) { return in_array( (int) $row['location_id'], $location_ids, true ); } ) );
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
		if ( ! $row || (int) $row['version_no'] !== (int) $expected_version || $row['status'] !== $expected_state ) {
			throw new RuntimeException( 'state/version conflict' );
		}
		$row = array_merge( $row, $changes, array( 'status' => $new_state, 'version_no' => (int) $expected_version + 1 ) );
		$this->tables[ $entity ][ $id ] = $row;
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
$approved = $service->execute_idempotent( hrx_key( 5 ), 'workforce_plan.decide', array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Budget checked against approved headcount and monthly plan.', 'budget_reference'=>'BUDGET-SYNTH-01', 'approved_budget_etb'=>'10000.00' ), function () use ( $service, $first ) { return $service->decide_plan( $first['id'], array( 'decision'=>'APPROVE', 'version_no'=>2, 'decision_note'=>'Budget checked against approved headcount and monthly plan.', 'budget_reference'=>'BUDGET-SYNTH-01', 'approved_budget_etb'=>'10000.00' ), hrx_key( 5 ) ); } );
hrx_assert( is_array( $approved ) && 'APPROVED' === $approved['record']['status'] && 'APPROVED' === $repo->get( 'plan_lines', $line['id'] )['status'], 'independent Owner approval gates plan and plan line' ); $checks++;
$GLOBALS['hrx_test_user_id'] = 22; // Management prepares the position; Owner approved the budget above.
$position_payload = array( 'position_code'=>'POS-SYNTH-001', 'workforce_plan_id'=>$first['id'], 'plan_line_id'=>$line['id'] );
$position = $service->execute_idempotent( hrx_key( 6 ), 'position.create', $position_payload, function () use ( $service, $position_payload ) { return $service->create_position( $position_payload, hrx_key( 6 ) ); } );
hrx_assert( is_array( $position ) && 'DRAFT' === $position['record']['status'] && (int)$position['record']['planned_headcount'] === 2, 'position can only be created from approved plan line and carries headcount' ); $checks++;
$position_submit = $service->execute_idempotent( hrx_key( 7 ), 'position.submit', array( 'version_no'=>1 ), function () use ( $service, $position ) { return $service->submit_position( $position['id'], array( 'version_no'=>1 ), hrx_key( 7 ) ); } );
hrx_assert( is_array( $position_submit ) && 'SUBMITTED' === $position_submit['record']['status'], 'position submission changes state' ); $checks++;
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
hrx_assert( count( $events ) >= 12, 'all completed transitions and draft changes produce auditable events' ); $checks++;
hrx_assert( count( $repo->tables['command_receipts'] ) === 12, 'every successful mutating command has one idempotency receipt; rejected self-approval leaves no receipt' ); $checks++;

echo 'PASS HR replacement workforce synthetic service contract: ' . $checks . " assertions.\n";
