<?php
/**
 * Synthetic integration contract for isolated workforce planning and vacancy service.
 *
 * Uses an in-memory repository only. Never loads the production plugin or connects to
 * WordPress/MariaDB; it verifies orchestration contracts, approvals, idempotency, and
 * schema boundaries before an HTTP/UI adapter is designed.
 */
define( 'STOCKLAND_HR_REPLACEMENT_TESTING', true );
require_once __DIR__ . '/../../ci/hr_payroll_candidate/Stockland_HR_Workflow_State_Machine.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_Workforce_Contract.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_Workforce_Repository.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_Workforce_Service.php';
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_Workforce_Schema.php';

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) { return '2026-10-28 00:00:00'; }
}

function hrx_ok( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'Assertion failed: ' . $message ); }
}
function hrx_expect( $code, callable $callback ) {
	try {
		$callback();
	} catch ( Throwable $e ) {
		hrx_ok( 0 === strpos( $e->getMessage(), $code . ':' ), 'expected ' . $code . ', got ' . get_class( $e ) . ': ' . $e->getMessage() );
		return;
	}
	throw new RuntimeException( 'Expected exception ' . $code );
}
function hrx_minor( $amount ) {
	$parts = explode( '.', (string) $amount, 2 );
	return (int) $parts[0] * 100 + (int) str_pad( $parts[1] ?? '0', 2, '0' );
}
function hrx_money( $minor ) {
	return intdiv( $minor, 100 ) . '.' . str_pad( (string) ( $minor % 100 ), 2, '0', STR_PAD_LEFT );
}

final class HRX_Test_Repository implements Stockland_HR_Replacement_Workforce_Repository {
	private $records = array();
	private $events = array();
	private $next_id = 1;
	public $ready = true;

	public function __construct() {
		foreach ( array( 'org_units', 'workforce_plans', 'plan_lines', 'positions', 'requisitions' ) as $entity ) {
			$this->records[ $entity ] = array();
		}
	}
	public function transaction( callable $callback ) {
		$records = unserialize( serialize( $this->records ) );
		$events = unserialize( serialize( $this->events ) );
		$next = $this->next_id;
		try {
			return $callback();
		} catch ( Throwable $e ) {
			$this->records = $records;
			$this->events = $events;
			$this->next_id = $next;
			throw $e;
		}
	}
	private function entity( $entity ) {
		if ( ! isset( $this->records[ $entity ] ) ) {
			throw new InvalidArgumentException( 'HRX_ENTITY_UNKNOWN: Unknown fake entity ' . $entity );
		}
		return $entity;
	}
	public function find( $entity, $id, $for_update = false ) {
		$entity = $this->entity( $entity );
		return isset( $this->records[ $entity ][ (int) $id ] ) ? $this->records[ $entity ][ (int) $id ] : null;
	}
	public function find_by_code( $entity, $field, $value ) {
		$entity = $this->entity( $entity );
		foreach ( $this->records[ $entity ] as $row ) {
			if ( isset( $row[ $field ] ) && (string) $row[ $field ] === (string) $value ) { return $row; }
		}
		return null;
	}
	public function list_records( $entity, $filters = array(), $limit = 50 ) {
		$entity = $this->entity( $entity );
		$rows = array_values( $this->records[ $entity ] );
		foreach ( $filters as $key => $value ) {
			if ( null === $value || '' === $value || 'after_id' === $key ) { continue; }
			$rows = array_values( array_filter( $rows, function ( $row ) use ( $key, $value ) {
				return array_key_exists( $key, $row ) && (string) $row[ $key ] === (string) $value;
			} ) );
		}
		usort( $rows, function ( $a, $b ) { return (int) $b['id'] <=> (int) $a['id']; } );
		return array_slice( $rows, 0, max( 1, min( 100, (int) $limit ) ) );
	}
	public function insert( $entity, $data ) {
		$entity = $this->entity( $entity );
		$row = array_merge(
			array( 'id' => $this->next_id++, 'created_at' => '2026-10-28 00:00:00', 'updated_at' => '2026-10-28 00:00:00' ),
			$data
		);
		$this->records[ $entity ][ $row['id'] ] = $row;
		return $row;
	}
	public function update_expected( $entity, $id, $version, $status, $data ) {
		$entity = $this->entity( $entity );
		$row = $this->find( $entity, $id );
		if ( ! $row || (int) $row['version_no'] !== (int) $version || (string) $row['status'] !== (string) $status ) {
			throw new DomainException( 'HRX_STALE_VERSION: Version or state changed.', 409 );
		}
		$this->records[ $entity ][ (int) $id ] = array_merge( $row, $data, array( 'updated_at' => '2026-10-28 00:00:00' ) );
		return $this->records[ $entity ][ (int) $id ];
	}
	public function update_line_expected( $id, $version, $data ) {
		$row = $this->find( 'plan_lines', $id );
		if ( ! $row || (int) $row['version_no'] !== (int) $version ) {
			throw new DomainException( 'HRX_STALE_VERSION: Plan line version changed.', 409 );
		}
		$this->records['plan_lines'][ (int) $id ] = array_merge( $row, $data, array( 'updated_at' => '2026-10-28 00:00:00' ) );
		return $this->records['plan_lines'][ (int) $id ];
	}
	public function append_event( $event ) {
		$key = (string) $event['idempotency_key'];
		if ( isset( $this->events[ $key ] ) ) { throw new DomainException( 'HRX_IDEMPOTENCY_CONFLICT: Duplicate event key.', 409 ); }
		$event['id'] = $this->next_id++;
		$this->events[ $key ] = $event;
		return true;
	}
	public function find_event_by_key( $idempotency_key ) {
		return isset( $this->events[ (string) $idempotency_key ] ) ? $this->events[ (string) $idempotency_key ] : null;
	}
	public function org_unit_exists( $id, $active_only = true ) {
		$row = $this->find( 'org_units', $id );
		return $row && ( ! $active_only || 'ACTIVE' === (string) $row['status'] );
	}
	public function location_exists( $id ) { return in_array( (int) $id, array( 1, 2, 3, 4, 5 ), true ); }
	public function count_reserved_openings( $plan_line_id, $exclude_requisition_id = 0 ) {
		$total = 0;
		foreach ( $this->records['requisitions'] as $row ) {
			if ( (int) $row['plan_line_id'] !== (int) $plan_line_id || (int) $row['id'] === (int) $exclude_requisition_id ) { continue; }
			if ( in_array( $row['status'], array( 'APPROVED', 'OPEN', 'FILLED' ), true ) ) { $total += (int) $row['openings']; }
		}
		return $total;
	}
	public function sum_plan_lines( $plan_id ) {
		$rows = array_values( array_filter( $this->records['plan_lines'], function ( $row ) use ( $plan_id ) {
			return (int) $row['plan_id'] === (int) $plan_id;
		} ) );
		$headcount = 0; $minor = 0; $currencies = array();
		foreach ( $rows as $row ) {
			$headcount += (int) $row['budgeted_headcount'];
			$minor += hrx_minor( $row['budget_amount'] );
			$currencies[ (string) $row['currency'] ] = true;
		}
		return array(
			'line_count' => count( $rows ),
			'headcount_total' => $headcount,
			'budget_total' => hrx_money( $minor ),
			'currency_count' => count( $currencies ),
			'currency' => $currencies ? (string) array_key_first( $currencies ) : '',
		);
	}
	public function list_plan_lines( $plan_id ) {
		$out = array();
		foreach ( $this->records['plan_lines'] as $row ) {
			if ( (int) $row['plan_id'] !== (int) $plan_id ) { continue; }
			$position = $this->find( 'positions', $row['position_id'] );
			$out[] = array_merge( $row, array(
				'position_code' => $position['position_code'] ?? null,
				'position_title' => $position['title'] ?? null,
				'position_status' => $position['status'] ?? null,
			) );
		}
		return $out;
	}
	public function tables_ready() { return $this->ready; }
}

final class HRX_Test_Access implements Stockland_HR_Replacement_Workforce_Access {
	public $actor = 200;
	public $can_prepare_flag = true;
	public $can_approve_flag = false;
	public $can_view_flag = true;
	public $can_manage_org_flag = false;
	public $allowed_locations = array( 1 );
	public function actor_id() { return $this->actor; }
	public function can_view() { return $this->can_view_flag; }
	public function can_prepare() { return $this->can_prepare_flag; }
	public function can_approve() { return $this->can_approve_flag; }
	public function can_manage_org_structure() { return $this->can_manage_org_flag; }
	public function can_access_location( $location_id ) { return null === $location_id || in_array( (int) $location_id, $this->allowed_locations, true ); }
}

$checks = 0;
$ddl = Stockland_HR_Replacement_Workforce_Schema::ddl( 'wp_stockland_hrx_' );
hrx_ok( count( $ddl ) === 7, 'seven target tables are specified in the workforce slice' ); $checks++;
foreach ( $ddl as $key => $sql ) {
	hrx_ok( false !== strpos( $sql, 'ENGINE=InnoDB' ), $key . ' explicitly uses InnoDB' );
	hrx_ok( 0 === stripos( ltrim( $sql ), 'CREATE TABLE' ), $key . ' is additive CREATE TABLE only' );
	hrx_ok( false === stripos( $sql, 'stock_employees' ) && false === stripos( $sql, 'stock_employee_compensation' ) && false === stripos( $sql, 'stock_payroll_lines' ), $key . ' does not target legacy tables' );
	hrx_ok( false === stripos( $sql, 'DROP TABLE' ) && false === stripos( $sql, 'ALTER TABLE' ), $key . ' has no destructive DDL' );
	$checks += 4;
}
hrx_expect( 'HRX_SCHEMA_INSTALL_NOT_AUTHORIZED', function () {
	Stockland_HR_Replacement_Workforce_Schema::install_missing_tables( new stdClass(), false );
} ); $checks++;
hrx_expect( 'HRX_SCHEMA_PREFIX_INVALID', function () {
	Stockland_HR_Replacement_Workforce_Schema::ddl( 'wp_; DROP TABLE stock_employees; ' );
} ); $checks++;

$repo = new HRX_Test_Repository();
$access = new HRX_Test_Access();
$service = new Stockland_HR_Replacement_Workforce_Service( $repo, $access );
$access->actor = 100; $access->can_approve_flag = true; $access->can_manage_org_flag = true; $access->can_prepare_flag = true;
$org = $service->create_org_unit(
	array( 'org_code' => 'ORG-RETAIL', 'name' => 'Retail Operations', 'parent_id' => null ),
	'org.create.0001'
);
$org_id = (int) $org['record']['id'];
hrx_ok( 'ACTIVE' === $org['record']['status'] && false === $org['replayed'], 'authorized org unit created once' ); $checks++;

$replay = $service->create_org_unit(
	array( 'org_code' => 'ORG-RETAIL', 'name' => 'Retail Operations', 'parent_id' => null ),
	'org.create.0001'
);
hrx_ok( true === $replay['replayed'] && (int) $replay['record']['id'] === $org_id, 'identical idempotency replay returns the original record' ); $checks++;
hrx_expect( 'HRX_IDEMPOTENCY_CONFLICT', function () use ( $service ) {
	$service->create_org_unit(
		array( 'org_code' => 'ORG-RETAIL', 'name' => 'Retail Operations Changed', 'parent_id' => null ),
		'org.create.0001'
	);
} ); $checks++;

$access->actor = 200; $access->can_approve_flag = false; $access->can_manage_org_flag = false; $access->can_prepare_flag = true;
$plan = $service->create_plan(
	array(
		'plan_code' => 'WF-2027-001',
		'fiscal_cycle' => '2026-2027',
		'org_unit_id' => $org_id,
		'location_id' => 1,
		'title' => 'Retail workforce plan',
		'justification' => 'Plan staffing and approved budget for retail locations.',
	),
	'plan.create.0001'
);
$plan_id = (int) $plan['record']['id'];
hrx_expect( 'HRX_PLAN_LINES_INCOMPLETE', function () use ( $service, $plan_id ) {
	$service->submit_plan( $plan_id, 1, 'plan.submit.empty1' );
} ); $checks++;

$position = $service->create_position(
	array(
		'position_code' => 'POS-STORE-MGR',
		'org_unit_id' => $org_id,
		'title' => 'Store Manager',
		'grade_code' => 'GR3',
		'parent_position_id' => null,
		'location_id' => 1,
		'position_justification' => 'Required to manage store operations and team controls.',
	),
	'position.create.0001'
);
$position_id = (int) $position['record']['id'];
$submitted_position = $service->submit_position( $position_id, 1, 'position.submit.0001' );
hrx_ok( 'SUBMITTED' === $submitted_position['record']['status'] && 2 === (int) $submitted_position['record']['version_no'], 'position submission advances state and version' ); $checks++;

$access->actor = 100; $access->can_approve_flag = true; $access->can_prepare_flag = true;
$approved_position = $service->decide_position(
	$position_id, 2, 'APPROVE', 'Position approved after independent business review.', 'position.approve.0001'
);
hrx_ok( 'APPROVED' === $approved_position['record']['status'] && 3 === (int) $approved_position['record']['version_no'], 'independent Owner approval is recorded with a version increment' ); $checks++;

// Maker cannot approve a position created by the same actor, even when that actor
// has the Owner approval capability.
$access->actor = 100; $access->can_approve_flag = true; $access->can_prepare_flag = true;
$self_position = $service->create_position(
	array(
		'position_code' => 'POS-SELF-CHECK',
		'org_unit_id' => $org_id,
		'title' => 'Self Approval Test Position',
		'grade_code' => null,
		'parent_position_id' => null,
		'location_id' => 1,
		'position_justification' => 'Synthetic position used to confirm maker checker enforcement.',
	),
	'position.self.create1'
);
$self_position_id = (int) $self_position['record']['id'];
$self_submitted = $service->submit_position( $self_position_id, 1, 'position.self.submit1' );
hrx_ok( 'SUBMITTED' === $self_submitted['record']['status'], 'owner-created synthetic position can be submitted for independent review' ); $checks++;
hrx_expect( 'HRX_SELF_APPROVAL_BLOCKED', function () use ( $service, $self_position_id ) {
	$service->decide_position( $self_position_id, 2, 'APPROVE', 'This approval attempt must be blocked as self approval.', 'position.self.approve1' );
} ); $checks++;

// Continue with the manager as maker. Only the independent Owner can approve the
// position plan and budget reference.
$access->actor = 200; $access->can_approve_flag = false; $access->can_manage_org_flag = false; $access->can_prepare_flag = true;
$line = $service->add_plan_line(
	$plan_id,
	array(
		'position_id' => $position_id,
		'budgeted_headcount' => 3,
		'budget_amount' => '100000.00',
		'currency' => 'ETB',
		'justification' => 'Approved headcount and budget to support retail operations.',
	),
	'plan.line.create1'
);
$line_id = (int) $line['record']['id'];
hrx_ok( 1 === (int) $line['record']['version_no'], 'plan line begins at version one' ); $checks++;

$updated_line = $service->update_plan_line(
	$plan_id, $line_id,
	array(
		'budgeted_headcount' => 3,
		'budget_amount' => '120000.00',
		'currency' => 'ETB',
		'justification' => 'Revised budget approved for the defined staffing period.',
	),
	1,
	'plan.line.update1'
);
hrx_ok( 2 === (int) $updated_line['record']['version_no'] && '120000.00' === (string) $updated_line['record']['budget_amount'], 'optimistic update preserves decimal budget and increments version' ); $checks++;
hrx_expect( 'HRX_STALE_VERSION', function () use ( $service, $plan_id, $line_id ) {
	$service->update_plan_line(
		$plan_id, $line_id,
		array(
			'budgeted_headcount' => 4,
			'budget_amount' => '125000.00',
			'currency' => 'ETB',
			'justification' => 'This stale update must not overwrite a more recent budget line.',
		),
		1,
		'plan.line.update.stale1'
	);
} ); $checks++;

$submitted_plan = $service->submit_plan( $plan_id, 1, 'plan.submit.0001' );
hrx_ok(
	'SUBMITTED' === $submitted_plan['record']['status'] &&
	3 === (int) $submitted_plan['record']['requested_headcount_total'] &&
	'120000.00' === (string) $submitted_plan['record']['requested_budget_total'],
	'plan submission freezes expected headcount and decimal budget totals'
); $checks++;

$access->actor = 100; $access->can_approve_flag = true; $access->can_prepare_flag = true;
$approved_plan = $service->decide_plan(
	$plan_id, 2, 'APPROVE', 'Budget and headcount approved for the defined fiscal cycle.',
	'BUD-2026-RETAIL-01', 'plan.approve.0001'
);
hrx_ok(
	'APPROVED' === $approved_plan['record']['status'] &&
	3 === (int) $approved_plan['record']['version_no'] &&
	'BUD-2026-RETAIL-01' === $approved_plan['record']['budget_reference'],
	'Owner approval pins the workforce plan budget reference'
); $checks++;

$access->actor = 200; $access->can_approve_flag = false;
$requisition = $service->create_requisition(
	array(
		'requisition_code' => 'REQ-RETAIL-001',
		'plan_line_id' => $line_id,
		'openings' => 2,
		'priority' => 'HIGH',
		'requested_start_date' => '2026-11-15',
		'justification' => 'Two vacancies are required to sustain approved retail service coverage.',
		'location_id' => 1,
	),
	'requisition.create.0001'
);
$requisition_id = (int) $requisition['record']['id'];
$submitted_req = $service->submit_requisition( $requisition_id, 1, 'requisition.submit.0001' );
hrx_ok( 'SUBMITTED' === $submitted_req['record']['status'], 'vacancy requisition requires and follows an approved plan' ); $checks++;

$access->actor = 100; $access->can_approve_flag = true;
$approved_req = $service->decide_requisition(
	$requisition_id, 2, 'APPROVE', 'Openings and funding confirmed against the approved plan.',
	'BUD-2026-RETAIL-01', 'requisition.approve.0001'
);
hrx_ok( 'APPROVED' === $approved_req['record']['status'], 'requisition approval verifies exact approved budget reference' ); $checks++;

$access->actor = 200; $access->can_approve_flag = false;
$opened_req = $service->open_requisition( $requisition_id, 3, 'requisition.open.0001' );
hrx_ok( 'OPEN' === $opened_req['record']['status'] && 4 === (int) $opened_req['record']['version_no'], 'approved vacancy can be opened for human-led recruitment' ); $checks++;
hrx_ok( 2 === $repo->count_reserved_openings( $line_id ), 'opened vacancies reserve against approved headcount' ); $checks++;

hrx_expect( 'HRX_LOCATION_FORBIDDEN', function () use ( $service ) {
	$service->list_positions( array( 'location_id' => 2 ) );
} ); $checks++;

$access->can_prepare_flag = false;
hrx_expect( 'HRX_FORBIDDEN', function () use ( $service, $org_id ) {
	$service->create_plan(
		array(
			'plan_code' => 'WF-FORBIDDEN',
			'fiscal_cycle' => '2026-2027',
			'org_unit_id' => $org_id,
			'location_id' => 1,
			'title' => 'Unauthorized plan',
			'justification' => 'This request must be rejected without create permission.',
		),
		'plan.forbidden.01'
	);
} ); $checks++;

$repo->ready = false;
hrx_expect( 'HRX_SCHEMA_NOT_READY', function () use ( $service ) {
	$service->list_org_units();
} ); $checks++;

echo 'PASS workforce slice synthetic contract: ' . $checks . " assertions; in-memory only; no WordPress or database connection; no production writes.\n";
