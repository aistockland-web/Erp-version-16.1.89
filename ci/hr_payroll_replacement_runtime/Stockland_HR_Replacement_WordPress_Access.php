<?php
/**
 * WordPress authorization adapter for the HR replacement workforce slice.
 * The candidate reuses Stockland identity/capabilities; it never creates WP users.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}
if ( ! interface_exists( 'Stockland_HR_Replacement_Workforce_Access' ) ) {
	require_once __DIR__ . '/Stockland_HR_Replacement_Workforce_Service.php';
}

final class Stockland_HR_Replacement_WordPress_Access implements Stockland_HR_Replacement_Workforce_Access {
	public function actor_id() {
		return (int) get_current_user_id();
	}

	public function can_view() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_view_hr();
	}

	public function can_prepare() {
		return class_exists( 'Stockland_Authority' ) && Stockland_Authority::can_prepare_hr();
	}

	public function can_approve() {
		return class_exists( 'Stockland_Authority' )
			&& Stockland_Authority::is_owner()
			&& Stockland_Authority::can_manage_hr();
	}

	public function can_manage_org_structure() {
		return $this->can_approve();
	}

	public function can_access_location( $location_id ) {
		if ( null === $location_id || '' === $location_id || 0 === (int) $location_id ) {
			return true;
		}
		if ( ! class_exists( 'Stockland_Authority' ) ) {
			return false;
		}
		if ( Stockland_Authority::company_scope() ) {
			return true;
		}
		return Stockland_Authority::can_access_location( (int) $location_id );
	}
}
