<?php
/**
 * WordPress/MariaDB adapter for the HR replacement workforce slice.
 *
 * Only the fixed hrx entity map is queryable. The adapter never accepts table or
 * column names supplied by API callers, and it refuses to fall back to the legacy
 * four-table HR schema.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

final class Stockland_HR_Replacement_Workforce_WPDB_Repository implements Stockland_HR_Replacement_Workforce_Repository {
	private $wpdb;
	private $depth = 0;

	private $tables = array(
		'org_units'       => 'org_units',
		'workforce_plans' => 'workforce_plans',
		'plan_lines'      => 'workforce_plan_lines',
		'positions'       => 'positions',
		'requisitions'    => 'requisitions',
		'events'          => 'events',
		'migrations'      => 'migrations',
	);

	private $code_fields = array(
		'org_units'       => 'org_code',
		'workforce_plans' => 'plan_code',
		'positions'       => 'position_code',
		'requisitions'    => 'requisition_code',
	);

	private $filter_fields = array(
		'org_units'       => array( 'parent_id', 'status' ),
		'workforce_plans' => array( 'org_unit_id', 'location_id', 'status' ),
		'plan_lines'      => array( 'plan_id', 'position_id' ),
		'positions'       => array( 'org_unit_id', 'location_id', 'parent_position_id', 'status' ),
		'requisitions'    => array( 'plan_line_id', 'location_id', 'status' ),
	);

	public function __construct( $wpdb = null ) {
		if ( $wpdb ) {
			$this->wpdb = $wpdb;
		} else {
			global $wpdb;
			$this->wpdb = $wpdb;
		}
		if ( ! is_object( $this->wpdb ) ) {
			throw new RuntimeException( 'HRX_DATABASE_UNAVAILABLE: WordPress database gateway is unavailable.' );
		}
	}

	private function table( $entity ) {
		if ( ! isset( $this->tables[ $entity ] ) ) {
			throw new InvalidArgumentException( 'HRX_ENTITY_UNKNOWN: Unknown workforce repository entity.' );
		}
		return (string) $this->wpdb->prefix . 'stockland_hrx_' . $this->tables[ $entity ];
	}

	private function query( $sql, $failure_code = 'HRX_DATABASE_ERROR' ) {
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) {
			throw new RuntimeException( $failure_code . ': ' . (string) $this->wpdb->last_error );
		}
		return $result;
	}

	public function transaction( callable $callback ) {
		$depth_before = (int) $this->depth;
		$savepoint = 'stockland_hrx_sp_' . $depth_before;
		if ( 0 === $depth_before ) {
			$this->query( 'START TRANSACTION', 'HRX_TRANSACTION_START_FAILED' );
		} else {
			$this->query( 'SAVEPOINT ' . $savepoint, 'HRX_SAVEPOINT_FAILED' );
		}
		$this->depth = $depth_before + 1;
		try {
			$result = $callback();
		} catch ( Throwable $e ) {
			$this->depth = $depth_before;
			if ( 0 === $depth_before ) {
				$this->wpdb->query( 'ROLLBACK' );
			} else {
				$this->wpdb->query( 'ROLLBACK TO SAVEPOINT ' . $savepoint );
			}
			throw $e;
		}
		$this->depth = $depth_before;
		try {
			if ( 0 === $depth_before ) {
				$this->query( 'COMMIT', 'HRX_TRANSACTION_COMMIT_FAILED' );
			} else {
				$this->query( 'RELEASE SAVEPOINT ' . $savepoint, 'HRX_SAVEPOINT_RELEASE_FAILED' );
			}
		} catch ( Throwable $e ) {
			if ( 0 === $depth_before ) {
				$this->wpdb->query( 'ROLLBACK' );
			} else {
				$this->wpdb->query( 'ROLLBACK TO SAVEPOINT ' . $savepoint );
			}
			throw $e;
		}
		return $result;
	}

	public function find( $entity, $id, $for_update = false ) {
		$table = $this->table( $entity );
		$sql   = $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), $table, (int) $id );
		$row   = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) {
			throw new RuntimeException( 'HRX_DATABASE_READ_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return is_array( $row ) ? $row : null;
	}

	public function find_by_code( $entity, $field, $value ) {
		if ( ! isset( $this->code_fields[ $entity ] ) || $this->code_fields[ $entity ] !== $field ) {
			throw new InvalidArgumentException( 'HRX_CODE_LOOKUP_NOT_ALLOWED: Unsupported code lookup.' );
		}
		$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE %i = %s LIMIT 1', $this->table( $entity ), $field, (string) $value );
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) {
			throw new RuntimeException( 'HRX_DATABASE_READ_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return is_array( $row ) ? $row : null;
	}

	public function list_records( $entity, $filters = array(), $limit = 50 ) {
		if ( ! isset( $this->filter_fields[ $entity ] ) ) {
			throw new InvalidArgumentException( 'HRX_LIST_NOT_ALLOWED: Entity cannot be listed through this method.' );
		}
		$limit = max( 1, min( 100, (int) $limit ) );
		$sql   = 'SELECT * FROM %i';
		$args  = array( $this->table( $entity ) );
		$where = array();
		foreach ( $this->filter_fields[ $entity ] as $field ) {
			if ( ! array_key_exists( $field, $filters ) || null === $filters[ $field ] || '' === $filters[ $field ] ) {
				continue;
			}
			$where[] = '%i = ' . ( in_array( $field, array( 'status' ), true ) ? '%s' : '%d' );
			$args[]  = $field;
			$args[]  = $filters[ $field ];
		}
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		if ( isset( $filters['after_id'] ) && (int) $filters['after_id'] > 0 ) {
			$sql    .= $where ? ' AND id < %d' : ' WHERE id < %d';
			$args[]  = (int) $filters['after_id'];
		}
		$sql   .= ' ORDER BY id DESC LIMIT %d';
		$args[] = $limit;
		$rows   = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $rows ) ) {
			throw new RuntimeException( 'HRX_DATABASE_READ_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return $rows;
	}

	public function insert( $entity, $data ) {
		$table = $this->table( $entity );
		if ( ! is_array( $data ) || empty( $data ) ) {
			throw new InvalidArgumentException( 'HRX_INSERT_INVALID: Insert fields are required.' );
		}
		$result = $this->wpdb->insert( $table, $data );
		if ( false === $result ) {
			$message = (string) $this->wpdb->last_error;
			if ( false !== stripos( $message, 'duplicate' ) ) {
				throw new DomainException( 'HRX_UNIQUE_CONFLICT: This code or reference already exists.', 409 );
			}
			throw new RuntimeException( 'HRX_DATABASE_INSERT_FAILED: ' . $message );
		}
		return $this->find( $entity, (int) $this->wpdb->insert_id );
	}

	public function update_expected( $entity, $id, $version, $status, $data ) {
		if ( ! is_array( $data ) || empty( $data ) || ! array_key_exists( 'version_no', $data ) ) {
			throw new InvalidArgumentException( 'HRX_UPDATE_INVALID: Update must advance the version.' );
		}
		$result = $this->wpdb->update(
			$this->table( $entity ),
			$data,
			array(
				'id'         => (int) $id,
				'version_no' => (int) $version,
				'status'     => (string) $status,
			)
		);
		if ( false === $result ) {
			throw new RuntimeException( 'HRX_DATABASE_UPDATE_FAILED: ' . (string) $this->wpdb->last_error );
		}
		if ( 0 === $result ) {
			throw new DomainException( 'HRX_STALE_VERSION: The record changed since it was loaded. Refresh and try again.', 409 );
		}
		return $this->find( $entity, (int) $id );
	}

	public function update_line_expected( $id, $version, $data ) {
		if ( ! is_array( $data ) || empty( $data ) || ! array_key_exists( 'version_no', $data ) ) {
			throw new InvalidArgumentException( 'HRX_UPDATE_INVALID: Plan-line update must advance the version.' );
		}
		$result = $this->wpdb->update(
			$this->table( 'plan_lines' ),
			$data,
			array(
				'id'         => (int) $id,
				'version_no' => (int) $version,
			)
		);
		if ( false === $result ) {
			throw new RuntimeException( 'HRX_DATABASE_UPDATE_FAILED: ' . (string) $this->wpdb->last_error );
		}
		if ( 0 === $result ) {
			throw new DomainException( 'HRX_STALE_VERSION: The plan line changed since it was loaded.', 409 );
		}
		return $this->find( 'plan_lines', (int) $id );
	}

	public function append_event( $event ) {
		$data = array(
			'idempotency_key' => (string) $event['idempotency_key'],
			'request_hash'    => (string) $event['request_hash'],
			'entity_type'     => (string) $event['entity_type'],
			'entity_id'       => (int) $event['entity_id'],
			'event_type'      => (string) $event['event_type'],
			'from_status'     => isset( $event['from_status'] ) ? $event['from_status'] : null,
			'to_status'       => isset( $event['to_status'] ) ? $event['to_status'] : null,
			'actor_id'        => (int) $event['actor_id'],
			'maker_id'        => isset( $event['maker_id'] ) ? (int) $event['maker_id'] : null,
			'reason'          => isset( $event['reason'] ) ? (string) $event['reason'] : null,
			'evidence_sha256' => isset( $event['evidence_sha256'] ) ? $event['evidence_sha256'] : null,
			'record_version'  => (int) $event['record_version'],
			'metadata_json'   => wp_json_encode( isset( $event['metadata'] ) ? (array) $event['metadata'] : array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'occurred_at'     => current_time( 'mysql', true ),
		);
		$result = $this->wpdb->insert( $this->table( 'events' ), $data );
		if ( false === $result ) {
			$message = (string) $this->wpdb->last_error;
			if ( false !== stripos( $message, 'duplicate' ) ) {
				throw new DomainException( 'HRX_IDEMPOTENCY_CONFLICT: This request key has already been used.', 409 );
			}
			throw new RuntimeException( 'HRX_AUDIT_EVENT_WRITE_FAILED: ' . $message );
		}
		return true;
	}

	public function find_event_by_key( $idempotency_key ) {
		$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE idempotency_key = %s LIMIT 1', $this->table( 'events' ), (string) $idempotency_key );
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) {
			throw new RuntimeException( 'HRX_DATABASE_READ_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return is_array( $row ) ? $row : null;
	}

	public function org_unit_exists( $id, $active_only = true ) {
		$sql  = 'SELECT id FROM %i WHERE id = %d' . ( $active_only ? " AND status = 'ACTIVE'" : '' ) . ' LIMIT 1';
		$found = $this->wpdb->get_var( $this->wpdb->prepare( $sql, $this->table( 'org_units' ), (int) $id ) );
		if ( '' !== (string) $this->wpdb->last_error ) {
			throw new RuntimeException( 'HRX_DATABASE_READ_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return null !== $found;
	}

	public function location_exists( $id ) {
		$table = (string) $this->wpdb->prefix . 'stock_locations';
		$found = $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT id FROM %i WHERE id = %d LIMIT 1', $table, (int) $id ) );
		if ( '' !== (string) $this->wpdb->last_error ) {
			throw new RuntimeException( 'HRX_LOCATION_LOOKUP_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return null !== $found;
	}

	public function count_reserved_openings( $plan_line_id, $exclude_requisition_id = 0 ) {
		$table = $this->table( 'requisitions' );
		$sql   = "SELECT COALESCE(SUM(openings),0) FROM %i WHERE plan_line_id = %d AND status IN ('APPROVED','OPEN','FILLED')";
		$args  = array( $table, (int) $plan_line_id );
		if ( (int) $exclude_requisition_id > 0 ) {
			$sql   .= ' AND id <> %d';
			$args[] = (int) $exclude_requisition_id;
		}
		$value = $this->wpdb->get_var( $this->wpdb->prepare( $sql, $args ) );
		if ( '' !== (string) $this->wpdb->last_error || ! is_numeric( $value ) ) {
			throw new RuntimeException( 'HRX_HEADCOUNT_QUERY_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return (int) $value;
	}

	public function sum_plan_lines( $plan_id ) {
		$sql = $this->wpdb->prepare(
			'SELECT COUNT(*) AS line_count, COALESCE(SUM(budgeted_headcount),0) AS headcount_total, COALESCE(SUM(CAST(budget_amount AS DECIMAL(14,2))),0.00) AS budget_total, COUNT(DISTINCT currency) AS currency_count, MIN(currency) AS currency FROM %i WHERE plan_id = %d',
			$this->table( 'plan_lines' ),
			(int) $plan_id
		);
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $row ) ) {
			throw new RuntimeException( 'HRX_PLAN_TOTALS_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return array(
			'line_count'      => (int) $row['line_count'],
			'headcount_total' => (int) $row['headcount_total'],
			'budget_total'    => (string) $row['budget_total'],
			'currency_count'  => (int) $row['currency_count'],
			'currency'        => (string) $row['currency'],
		);
	}

	public function list_plan_lines( $plan_id ) {
		$sql = $this->wpdb->prepare(
			'SELECT l.*, p.position_code, p.title AS position_title, p.status AS position_status FROM %i l LEFT JOIN %i p ON p.id = l.position_id WHERE l.plan_id = %d ORDER BY l.id ASC',
			$this->table( 'plan_lines' ),
			$this->table( 'positions' ),
			(int) $plan_id
		);
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $rows ) ) {
			throw new RuntimeException( 'HRX_PLAN_LINES_READ_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return $rows;
	}

	public function tables_ready() {
		foreach ( array_keys( $this->tables ) as $entity ) {
			$table = $this->table( $entity );
			$row   = $this->wpdb->get_row( $this->wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( '' !== (string) $this->wpdb->last_error || ! is_array( $row ) || 'InnoDB' !== $row['Engine'] ) {
				return false;
			}
		}
		return true;
	}
}
