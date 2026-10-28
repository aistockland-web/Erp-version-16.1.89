<?php
/**
 * WordPress authorization adapter for the HR replacement workforce vertical slice.
 *
 * Does not grant capabilities. It delegates to the established Stockland authority
 * service and keeps the replacement module's authorization contract explicit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

final class Stockland_HR_Replacement_Workforce_WP_Access {
	public function can_view() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_view_hr();
	}

	public function can_prepare() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_prepare_hr();
	}

	public function can_approve() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_approve_payroll();
	}

	public function can_manage_structure() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_manage_hr();
	}

	public function assert_branch( $location_id ) {
		if ( ! class_exists( 'Stockland_Authority' ) ) {
			return new WP_Error( 'hrx_authority_unavailable', 'The Stockland authorization service is unavailable.', array( 'status' => 503 ) );
		}
		$location_id = absint( $location_id );
		if ( $location_id < 1 ) {
			return new WP_Error( 'hrx_location_required', 'A valid Stockland work location is required.', array( 'status' => 400 ) );
		}
		return Stockland_Authority::assert_branch( $location_id );
	}

	public function company_scope() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::company_scope();
	}

	public function scoped_location_ids() {
		if ( ! class_exists( 'Stockland_Authority' ) ) {
			return array();
		}
		if ( Stockland_Authority::company_scope() ) {
			return array();
		}
		if ( method_exists( 'Stockland_Authority', 'location_ids' ) ) {
			return array_values( array_filter( array_map( 'absint', Stockland_Authority::location_ids() ) ) );
		}
		return array();
	}
}
