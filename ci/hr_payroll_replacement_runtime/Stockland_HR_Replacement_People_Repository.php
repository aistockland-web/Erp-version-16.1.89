<?php
/** Persistence contract for the isolated People/Recruitment/Contract/Onboarding slice. */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }
interface Stockland_HR_Replacement_People_Repository {
	public function transaction( callable $callback );
	public function find( $entity, $id, $for_update = false );
	public function list_records( $entity, $filters = array(), $limit = 50 );
	public function insert( $entity, $data );
	public function update_expected( $entity, $id, $version, $status, $data );
	public function find_event_by_key( $key );
	public function person_email_exists( $email );
	public function append_event( $event );
	public function requisition( $id, $for_update = false );
	public function requisition_slot_available( $id );
	public function mandatory_onboarding_complete( $employment_id );
	public function tables_ready();
}

/**
 * Fixed-allowlist wpdb repository. It never accepts SQL identifiers from clients and
 * deliberately has no map for any of the four legacy HR/payroll tables.
 */
final class Stockland_HR_Replacement_People_WPDB_Repository implements Stockland_HR_Replacement_People_Repository {
	private $wpdb;
	private $depth = 0;
	private $tables = array(
		'people' => 'people',
		'candidates' => 'candidate_profiles',
		'applications' => 'applications',
		'interviews' => 'interviews',
		'employments' => 'employment_relationships',
		'contracts' => 'contract_versions',
		'documents' => 'private_documents',
		'onboarding' => 'onboarding_tasks',
		'events' => 'events',
		'migrations' => 'migrations',
		'requisitions' => 'requisitions',
		'plan_lines' => 'workforce_plan_lines',
		'positions' => 'positions',
	);
	private $list_fields = array(
		'people' => array( 'status' ),
		'candidates' => array( 'status', 'person_id' ),
		'applications' => array( 'stage', 'candidate_id', 'requisition_id', 'location_id' ),
		'interviews' => array( 'application_id', 'recommendation' ),
		'employments' => array( 'person_id', 'application_id', 'location_id', 'status' ),
		'contracts' => array( 'employment_id', 'status' ),
		'documents' => array( 'subject_type', 'subject_id', 'document_type', 'status' ),
		'onboarding' => array( 'employment_id', 'status', 'mandatory' ),
	);
	public function __construct( $wpdb = null ) {
		if ( $wpdb ) { $this->wpdb = $wpdb; } else { global $wpdb; $this->wpdb = $wpdb; }
		if ( ! is_object( $this->wpdb ) ) { throw new RuntimeException( 'HRXP_DATABASE_UNAVAILABLE: WordPress database gateway is unavailable.' ); }
	}
	private function table( $entity ) {
		if ( ! isset( $this->tables[ $entity ] ) ) { throw new InvalidArgumentException( 'HRXP_ENTITY_UNKNOWN: Unsupported entity.' ); }
		return (string) $this->wpdb->prefix . 'stockland_hrx_' . $this->tables[ $entity ];
	}
	private function query( $sql, $failure = 'HRXP_DATABASE_ERROR' ) {
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) { throw new RuntimeException( $failure . ': ' . (string) $this->wpdb->last_error ); }
		return $result;
	}
	public function transaction( callable $callback ) {
		$before = $this->depth; $savepoint = 'stockland_hrxp_people_sp_' . $before;
		if ( 0 === $before ) { $this->query( 'START TRANSACTION', 'HRXP_TX_START_FAILED' ); }
		else { $this->query( 'SAVEPOINT ' . $savepoint, 'HRXP_SAVEPOINT_FAILED' ); }
		$this->depth = $before + 1;
		try { $result = $callback(); }
		catch ( Throwable $e ) {
			$this->depth = $before;
			if ( 0 === $before ) { $this->wpdb->query( 'ROLLBACK' ); } else { $this->wpdb->query( 'ROLLBACK TO SAVEPOINT ' . $savepoint ); }
			throw $e;
		}
		$this->depth = $before;
		try {
			if ( 0 === $before ) { $this->query( 'COMMIT', 'HRXP_TX_COMMIT_FAILED' ); }
			else { $this->query( 'RELEASE SAVEPOINT ' . $savepoint, 'HRXP_SAVEPOINT_RELEASE_FAILED' ); }
		} catch ( Throwable $e ) {
			if ( 0 === $before ) { $this->wpdb->query( 'ROLLBACK' ); } else { $this->wpdb->query( 'ROLLBACK TO SAVEPOINT ' . $savepoint ); }
			throw $e;
		}
		return $result;
	}
	public function find( $entity, $id, $for_update = false ) {
		$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), $this->table( $entity ), (int) $id );
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXP_DATABASE_READ_FAILED: Could not read the requested HR record.' ); }
		return is_array( $row ) ? $row : null;
	}
	public function list_records( $entity, $filters = array(), $limit = 50 ) {
		if ( ! isset( $this->list_fields[ $entity ] ) ) { throw new InvalidArgumentException( 'HRXP_LIST_NOT_ALLOWED: Unsupported listing entity.' ); }
		$limit = max( 1, min( 100, (int) $limit ) );
		$sql = 'SELECT * FROM %i'; $args = array( $this->table( $entity ) ); $where = array();
		foreach ( $this->list_fields[ $entity ] as $field ) {
			if ( ! array_key_exists( $field, $filters ) || null === $filters[ $field ] || '' === $filters[ $field ] ) { continue; }
			$where[] = '%i = ' . ( in_array( $field, array( 'status', 'stage', 'recommendation', 'subject_type', 'document_type' ), true ) ? '%s' : '%d' );
			$args[] = $field; $args[] = $filters[ $field ];
		}
		if ( $where ) { $sql .= ' WHERE ' . implode( ' AND ', $where ); }
		$sql .= ' ORDER BY id DESC LIMIT %d'; $args[] = $limit;
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $rows ) ) { throw new RuntimeException( 'HRXP_DATABASE_LIST_FAILED: Could not list the requested HR records.' ); }
		return $rows;
	}
	public function insert( $entity, $data ) {
		if ( ! is_array( $data ) || empty( $data ) ) { throw new InvalidArgumentException( 'HRXP_INSERT_INVALID: Valid insert fields are required.' ); }
		if ( false === $this->wpdb->insert( $this->table( $entity ), $data ) ) {
			if ( false !== stripos( (string) $this->wpdb->last_error, 'duplicate' ) ) { throw new DomainException( 'HRXP_UNIQUE_CONFLICT: The requested record or source reference already exists.', 409 ); }
			throw new RuntimeException( 'HRXP_DATABASE_INSERT_FAILED: ' . (string) $this->wpdb->last_error );
		}
		return $this->find( $entity, (int) $this->wpdb->insert_id );
	}
	public function update_expected( $entity, $id, $version, $status, $data ) {
		if ( ! is_array( $data ) || empty( $data ) || ! array_key_exists( 'version_no', $data ) ) { throw new InvalidArgumentException( 'HRXP_VERSIONED_UPDATE_REQUIRED: Updates must advance version_no.' ); }
		$result = $this->wpdb->update( $this->table( $entity ), $data, array( 'id' => (int) $id, 'version_no' => (int) $version, 'status' => (string) $status ) );
		if ( false === $result ) { throw new RuntimeException( 'HRXP_DATABASE_UPDATE_FAILED: Could not update the HR record.' ); }
		if ( 1 !== (int) $result ) { throw new DomainException( 'HRXP_STALE_OR_MISSING: The record was changed by another user or its state is no longer valid.', 409 ); }
		return $this->find( $entity, (int) $id );
	}
	public function person_email_exists( $email ) {
		if ( ! is_string( $email ) || '' === trim( $email ) ) { return false; }
		$count = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE LOWER(email) = %s', $this->table( 'people' ), strtolower( trim( $email ) ) ) );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXP_PERSON_MATCH_LOOKUP_FAILED: Could not check potential duplicate identity.' ); }
		return $count > 0;
	}
	public function find_event_by_key( $key ) {
		$row = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE idempotency_key = %s LIMIT 1', $this->table( 'events' ), (string) $key ), ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXP_EVENT_LOOKUP_FAILED: Could not verify request idempotency.' ); }
		return is_array( $row ) ? $row : null;
	}
	public function append_event( $event ) {
		if ( false === $this->wpdb->insert( $this->table( 'events' ), $event ) ) {
			if ( false !== stripos( (string) $this->wpdb->last_error, 'duplicate' ) ) { throw new DomainException( 'HRXP_IDEMPOTENCY_CONFLICT: Request key was already consumed.', 409 ); }
			throw new RuntimeException( 'HRXP_EVENT_WRITE_FAILED: The workflow audit event was not saved.' );
		}
		return (int) $this->wpdb->insert_id;
	}
	public function requisition( $id, $for_update = false ) {
		$sql = $this->wpdb->prepare( 'SELECT r.*, pl.budgeted_headcount, pl.position_id, p.org_unit_id FROM %i r INNER JOIN %i pl ON pl.id = r.plan_line_id INNER JOIN %i p ON p.id = pl.position_id WHERE r.id = %d LIMIT 1' . ( $for_update ? ' FOR UPDATE' : '' ), $this->table( 'requisitions' ), $this->table( 'plan_lines' ), $this->table( 'positions' ), (int) $id );
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXP_REQUISITION_READ_FAILED: Could not verify the approved vacancy.' ); }
		return is_array( $row ) ? $row : null;
	}
	public function requisition_slot_available( $id ) {
		$r = $this->requisition( $id, true );
		if ( ! $r || 'OPEN' !== strtoupper( (string) $r['status'] ) ) { return false; }
		$sql = $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE requisition_id = %d AND stage IN ('SELECTED','OFFERED','ACCEPTED')", $this->table( 'applications' ), (int) $id );
		$reserved = (int) $this->wpdb->get_var( $sql );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXP_CAPACITY_READ_FAILED: Could not verify vacancy capacity.' ); }
		return ( (int) $r['openings'] - (int) $r['filled_openings'] - $reserved ) > 0;
	}
	public function mandatory_onboarding_complete( $employment_id ) {
		$sql = $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE employment_id = %d AND mandatory = 1 AND status <> 'COMPLETE'", $this->table( 'onboarding' ), (int) $employment_id );
		$n = (int) $this->wpdb->get_var( $sql );
		if ( '' !== (string) $this->wpdb->last_error ) { throw new RuntimeException( 'HRXP_ONBOARDING_READ_FAILED: Could not verify mandatory onboarding.' ); }
		return 0 === $n;
	}
	public function tables_ready() {
		foreach ( array( 'migrations', 'events', 'requisitions', 'plan_lines', 'positions', 'people', 'candidates', 'applications', 'interviews', 'employments', 'contracts', 'documents', 'onboarding' ) as $entity ) {
			$table = $this->table( $entity );
			$exists = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $table ) ) );
			if ( null === $exists || '' === (string) $exists || '' !== (string) $this->wpdb->last_error ) { return false; }
		}
		return true;
	}
}
