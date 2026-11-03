<?php
/**
 * Stockland WordPress authority adapter for the isolated People lifecycle.
 * PII access and changes use server-side authority, not UI visibility alone.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }
final class Stockland_HR_Replacement_People_WP_Access implements Stockland_HR_Replacement_People_Access {
	public function actor_id() { return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0; }
	public function can_view() { return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_view_hr(); }
	public function can_prepare() { return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_prepare_hr(); }
	public function can_approve() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::is_owner() && Stockland_Authority::can_approve_payroll();
	}
	public function can_access_location( $location_id ) {
		if ( ! $this->can_view() || (int) $location_id < 1 ) { return false; }
		return true === Stockland_Authority::assert_branch( (int) $location_id, $this->actor_id() );
	}
}
