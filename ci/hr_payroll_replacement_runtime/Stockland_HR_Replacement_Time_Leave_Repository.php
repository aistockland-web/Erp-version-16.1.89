<?php
/** Repository contracts and fixed-allowlist WordPress/MariaDB adapter for HR time/leave. */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }

interface Stockland_HR_Replacement_Time_Leave_Repository {
	public function transaction( callable $callback );
	public function find( $entity, $id, $for_update = false );
	public function find_by_code( $entity, $field, $value );
	public function list_records( $entity, $filters = array(), $limit = 100 );
	public function insert( $entity, $data );
	public function update_expected( $entity, $id, $version, $status, $data );
	public function find_event_by_key( $key );
	public function append_event( $event );
	public function location_exists( $id );
	public function tables_ready();
}

/**
 * Every SQL identifier is mapped internally. This adapter never exposes legacy
 * employees/employee_compensation/payroll_periods/payroll_lines as writable entities.
 */
final class Stockland_HR_Replacement_Time_Leave_WPDB_Repository implements Stockland_HR_Replacement_Time_Leave_Repository {
	private $wpdb;
	private $depth = 0;
	private $tables = array(
		'shift_patterns' => 'shift_patterns',
		'shift_assignments' => 'shift_assignments',
		'attendance_records' => 'attendance_records',
		'time_rule_sets' => 'time_rule_sets',
		'leave_policies' => 'leave_policy_versions',
		'leave_requests' => 'leave_requests',
		'leave_ledger' => 'leave_ledger',
		'cutoff_snapshots' => 'time_cutoff_snapshots',
		'employments' => 'employment_relationships',
		'people' => 'people',
		'events' => 'events',
		'migrations' => 'migrations',
		'positions' => 'positions',
		'requisitions' => 'requisitions',
		'plan_lines' => 'workforce_plan_lines',
	);
	private $code_fields = array(
		'shift_patterns' => 'pattern_code',
		'shift_assignments' => 'assignment_code',
		'attendance_records' => 'record_code',
		'time_rule_sets' => 'rule_code',
		'leave_policies' => 'policy_code',
		'leave_requests' => 'request_code',
		'leave_ledger' => 'transaction_code',
		'cutoff_snapshots' => 'snapshot_code',
	);
	private $list_fields = array(
		'shift_patterns' => array( 'pattern_code', 'status', 'effective_from', 'effective_to', 'version_no' ),
		'shift_assignments' => array( 'employment_id', 'shift_pattern_id', 'location_id', 'status', 'effective_from', 'effective_to' ),
		'attendance_records' => array( 'employment_id', 'location_id', 'work_date', 'record_kind', 'supersedes_record_id', 'shift_assignment_id', 'status', 'source_type' ),
		'time_rule_sets' => array( 'rule_code', 'rule_type', 'classification', 'status', 'effective_from', 'effective_to', 'version_no' ),
		'leave_policies' => array( 'policy_code', 'leave_type', 'classification', 'status', 'effective_from', 'effective_to', 'version_no' ),
		'leave_requests' => array( 'employment_id', 'location_id', 'request_type', 'leave_type', 'policy_code', 'status', 'effective_date', 'start_date', 'end_date' ),
		'leave_ledger' => array( 'employment_id', 'location_id', 'leave_type', 'policy_code', 'effective_date', 'request_id', 'entry_type', 'direction' ),
		'cutoff_snapshots' => array( 'employment_id', 'location_id', 'period_start', 'period_end', 'status', 'snapshot_version' ),
		'employments' => array( 'person_id', 'location_id', 'status', 'start_date', 'end_date' ),
		'people' => array( 'status' ),
		'positions' => array( 'status', 'location_id' ),
		'requisitions' => array( 'status', 'location_id' ),
		'plan_lines' => array( 'plan_id', 'position_id' ),
	);
	public function __construct( $wpdb = null ) {
		if ( $wpdb ) { $this->wpdb = $wpdb; } else { global $wpdb; $this->wpdb = $wpdb; }
		if ( ! is_object( $this->wpdb ) ) { throw new RuntimeException( 'HRXTL_DATABASE_UNAVAILABLE: WordPress database gateway is unavailable.' ); }
	}
	private function table( $entity ) {
		if ( ! isset( $this->tables[ $entity ] ) ) { throw new InvalidArgumentException( 'HRXTL_ENTITY_UNKNOWN: Unsupported repository entity.' ); }
		return (string) $this->wpdb->prefix . 'stockland_hrx_' . $this->tables[ $entity ];
	}
	private function query( $sql, $failure_code = 'HRXTL_DATABASE_ERROR' ) {
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) { throw new RuntimeException( $failure_code . ': ' . (string) $this->wpdb->last_error ); }
		return $result;
	}
	public function transaction( callable $callback ) {
		$before = (int) $this->depth;
		$savepoint = 'stockland_hrx_tl_sp_' . $before;
		if ( 0 === $before ) { $this->query( 'START TRANSACTION', 'HRXTL_TX_START_FAILED' ); }
		else { $this->query( 'SAVEPOINT ' . $savepoint, 'HRXTL_SAVEPOINT_FAILED' ); }
		$this->depth = $before + 1;
		try { $result = $callback(); }
		catch ( Throwable $e ) {
			$this->depth = $before;
			$this->wpdb->query( 0 === $before ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT ' . $savepoint );
			throw $e;
		}
		$this->depth = $before;
		try { $this->query( 0 === $before ? 'COMMIT' : 'RELEASE SAVEPOINT ' . $savepoint, 'HRXTL_TX_COMMIT_FAILED' ); }
		catch ( Throwable $e ) { $this->wpdb->query( 0 === $before ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT ' . $savepoint ); throw $e; }
		return $result;
	}
	public function find( $entity, $id, $for_update = false ) {
		$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), $this->table( $entity ), (int) $id );
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXTL_DATABASE_READ_FAILED: Could not read the requested time/leave record.' ); }
		return is_array( $row ) ? $row : null;
	}
	public function find_by_code( $entity, $field, $value ) {
		if ( ! isset( $this->code_fields[ $entity ] ) || $this->code_fields[ $entity ] !== $field ) { throw new InvalidArgumentException( 'HRXTL_CODE_LOOKUP_NOT_ALLOWED: Unsupported code lookup.' ); }
		$row = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE %i = %s LIMIT 1', $this->table( $entity ), $field, (string) $value ), ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXTL_DATABASE_READ_FAILED: Could not look up the requested reference.' ); }
		return is_array( $row ) ? $row : null;
	}
	public function list_records( $entity, $filters = array(), $limit = 100 ) {
		if ( ! isset( $this->list_fields[ $entity ] ) ) { throw new InvalidArgumentException( 'HRXTL_LIST_NOT_ALLOWED: The entity cannot be listed.' ); }
		$limit = max( 1, min( 500, (int) $limit ) );
		$sql = 'SELECT * FROM %i'; $args = array( $this->table( $entity ) ); $where = array();
		foreach ( $filters as $raw_field => $value ) {
			if ( null === $value || '' === $value ) { continue; }
			$parts = explode( '__', (string) $raw_field, 2 );
			$field = $parts[0]; $op = isset( $parts[1] ) ? $parts[1] : 'eq';
			if ( ! in_array( $field, $this->list_fields[ $entity ], true ) ) { throw new InvalidArgumentException( 'HRXTL_FILTER_NOT_ALLOWED: Unsupported filter field.' ); }
			$operators = array( 'eq' => '=', 'gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<' );
			if ( ! isset( $operators[ $op ] ) ) { throw new InvalidArgumentException( 'HRXTL_FILTER_OPERATOR_NOT_ALLOWED: Unsupported filter operation.' ); }
			$is_numeric = in_array( $field, array( 'id', 'employment_id', 'location_id', 'shift_pattern_id', 'supersedes_record_id', 'shift_assignment_id', 'version_no', 'snapshot_version', 'request_id', 'plan_id', 'position_id' ), true );
			$where[] = '%i ' . $operators[ $op ] . ( $is_numeric ? ' %d' : ' %s' );
			$args[] = $field; $args[] = $value;
		}
		if ( $where ) { $sql .= ' WHERE ' . implode( ' AND ', $where ); }
		$sql .= ' ORDER BY id DESC LIMIT %d'; $args[] = $limit;
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $rows ) ) { throw new RuntimeException( 'HRXTL_DATABASE_READ_FAILED: Could not list time/leave records.' ); }
		return $rows;
	}
	public function insert( $entity, $data ) {
		if ( ! is_array( $data ) || empty( $data ) ) { throw new InvalidArgumentException( 'HRXTL_INSERT_INVALID: Insert data is required.' ); }
		$result = $this->wpdb->insert( $this->table( $entity ), $data );
		if ( false === $result ) {
			$error = (string) $this->wpdb->last_error;
			if ( false !== stripos( $error, 'duplicate' ) ) { throw new DomainException( 'HRXTL_UNIQUE_CONFLICT: The reference already exists.', 409 ); }
			throw new RuntimeException( 'HRXTL_DATABASE_INSERT_FAILED: Database rejected the write.' );
		}
		return $this->find( $entity, (int) $this->wpdb->insert_id );
	}
	public function update_expected( $entity, $id, $version, $status, $data ) {
		if ( ! is_array( $data ) || empty( $data ) ) { throw new InvalidArgumentException( 'HRXTL_UPDATE_INVALID: Update data is required.' ); }
		$data['version_no'] = (int) $version + 1;
		$result = $this->wpdb->update( $this->table( $entity ), $data, array( 'id' => (int) $id, 'version_no' => (int) $version, 'status' => (string) $status ) );
		if ( false === $result ) { throw new RuntimeException( 'HRXTL_DATABASE_UPDATE_FAILED: Database rejected the update.' ); }
		if ( 0 === $result ) { throw new DomainException( 'HRXTL_STALE_VERSION: The record changed or state advanced; refresh and retry.', 409 ); }
		return $this->find( $entity, (int) $id );
	}
	public function find_event_by_key( $key ) {
		$row = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE idempotency_key = %s LIMIT 1', $this->table( 'events' ), (string) $key ), ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXTL_EVENT_LOOKUP_FAILED: Could not inspect idempotency history.' ); }
		return is_array( $row ) ? $row : null;
	}
	public function append_event( $event ) {
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( (array) ( $event['metadata'] ?? array() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : json_encode( (array) ( $event['metadata'] ?? array() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$data = array(
			'idempotency_key' => (string) $event['idempotency_key'],
			'request_hash' => (string) $event['request_hash'],
			'entity_type' => (string) $event['entity_type'],
			'entity_id' => (int) $event['entity_id'],
			'event_type' => (string) $event['event_type'],
			'from_status' => $event['from_status'] ?? null,
			'to_status' => $event['to_status'] ?? null,
			'actor_id' => (int) $event['actor_id'],
			'maker_id' => isset( $event['maker_id'] ) ? (int) $event['maker_id'] : null,
			'reason' => isset( $event['reason'] ) ? substr( (string) $event['reason'], 0, 190 ) : null,
			'evidence_sha256' => $event['evidence_sha256'] ?? null,
			'record_version' => max( 1, (int) $event['record_version'] ),
			'metadata_json' => is_string( $encoded ) ? $encoded : '{}',
			'occurred_at' => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ),
		);
		$result = $this->wpdb->insert( $this->table( 'events' ), $data );
		if ( false === $result ) {
			if ( false !== stripos( (string) $this->wpdb->last_error, 'duplicate' ) ) { throw new DomainException( 'HRXTL_IDEMPOTENCY_CONFLICT: The idempotency key was already used.', 409 ); }
			throw new RuntimeException( 'HRXTL_EVENT_WRITE_FAILED: Audit event write failed.' );
		}
		return true;
	}
	public function location_exists( $id ) {
		$table = (string) $this->wpdb->prefix . 'stock_locations';
		$value = $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT id FROM %i WHERE id = %d LIMIT 1', $table, (int) $id ) );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXTL_LOCATION_LOOKUP_FAILED: Could not validate workplace.' ); }
		return null !== $value;
	}
	public function tables_ready() {
		foreach ( array_keys( $this->tables ) as $entity ) {
			$table = $this->table( $entity );
			$row = $this->wpdb->get_row( $this->wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( '' !== (string) $this->wpdb->last_error || ! is_array( $row ) || 'InnoDB' !== (string) ( $row['Engine'] ?? '' ) ) { return false; }
		}
		return true;
	}
}
