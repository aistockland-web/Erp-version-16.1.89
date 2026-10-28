<?php
/**
 * WordPress authority adapter for the isolated HR replacement workforce service.
 *
 * This adapter delegates to established Stockland Authority checks. It does not
 * infer roles from UI state and must not be instantiated until the replacement
 * schema, routes and lifecycle acceptance gates are separately approved.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

final class Stockland_HR_Replacement_Workforce_WP_Access implements Stockland_HR_Replacement_Workforce_Access {
	public function actor_id() {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	public function can_view() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_view_hr();
	}

	/** Management prepares workforce records; final approval remains an Owner action. */
	public function can_prepare() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_prepare_hr();
	}

	public function can_approve() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_approve_payroll();
	}

	/** Organization-tree maintenance is restricted to Owner-controlled HR authority. */
	public function can_manage_org_structure() {
		return class_exists( 'Stockland_Authority' )
			&& Stockland_Authority::is_owner()
			&& Stockland_Authority::can_manage_hr();
	}

	public function can_access_location( $location_id ) {
		if ( ! class_exists( 'Stockland_Authority' ) || ! $this->can_view() ) {
			return false;
		}
		$user_id = $this->actor_id();
		if ( null === $location_id || '' === $location_id || 0 === (int) $location_id ) {
			// Only company-scope users may read or prepare company-wide HR records.
			return Stockland_Authority::company_scope( $user_id );
		}
		$result = Stockland_Authority::assert_branch( (int) $location_id, $user_id );
		return true === $result;
	}
}
