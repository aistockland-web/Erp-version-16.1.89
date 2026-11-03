<?php
/**
 * Repository contract for the isolated HR replacement workforce slice.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

interface Stockland_HR_Replacement_Workforce_Repository {
	public function transaction( callable $callback );
	public function find( $entity, $id, $for_update = false );
	public function find_by_code( $entity, $field, $value );
	public function list_records( $entity, $filters = array(), $limit = 50 );
	public function insert( $entity, $data );
	public function update_expected( $entity, $id, $version, $status, $data );
	public function update_line_expected( $id, $version, $data );
	public function append_event( $event );
	public function find_event_by_key( $idempotency_key );
	public function org_unit_exists( $id, $active_only = true );
	public function location_exists( $id );
	public function count_reserved_openings( $plan_line_id, $exclude_requisition_id = 0 );
	public function sum_plan_lines( $plan_id );
	public function list_plan_lines( $plan_id );
	public function tables_ready();
}
