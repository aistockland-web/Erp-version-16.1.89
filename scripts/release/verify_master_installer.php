<?php
// Synthetic disposable-site acceptance for the governed 49-key baseline.
// Run only after complete_baseline.php. No HR approval or live authorization is recorded.
wp_set_current_user( 1 );
$GLOBALS['stockland_verify_fail'] = 0;
function chk( $label, $ok, $detail = '' ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail ? " ($detail)" : '' ) . "\n";
	if ( ! $ok ) { $GLOBALS['stockland_verify_fail']++; }
}
global $wpdb;
$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'stock_' ) . '%' ) );
chk( 'physical baseline has exactly 49 application tables (29 Core + 16 Finance + 4 legacy HR)', 49 === count( $tables ), (string) count( $tables ) );
chk( 'Core schema complete', empty( Stockland_Activator::missing_core_tables() ) );
chk( 'Finance/Procurement schema complete', empty( Stockland_Accounting::missing_tables() ) && empty( Stockland_Accounting::upgrade_gaps() ) );
chk( 'four-table legacy HR baseline complete', empty( Stockland_HR::missing_legacy_tables() ) );
chk( '37 lifecycle HR tables remain outside unapproved DDL scope', 37 === count( Stockland_HR::missing_lifecycle_tables() ), (string) count( Stockland_HR::missing_lifecycle_tables() ) );
chk( 'no HR schema approval is auto-created', ! get_option( 'stockland_hr_schema_migration_approval' ) );
chk( 'no HR schema approval audit event is auto-created', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}stock_audit_logs WHERE action='HR_SCHEMA_MIGRATION_APPROVED'" ) );
chk( 'Core and Finance application routes are enabled independently', 1 === (int) get_option( 'stockland_application_schema_ready' ) && 1 === (int) get_option( 'stockland_core_finance_schema_ready' ) );
chk( 'five inventory locations seeded (Central Warehouse + 4 branches)', 5 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}stock_locations WHERE location_type IN ('Warehouse','Branch')" ) );
chk( 'installing administrator has an Owner profile', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}stock_users WHERE wp_user_id=1 AND role='owner' AND is_active=1" ) );
chk( 'HR schema readiness remains fail-closed', ! Stockland_HR::is_schema_ready() );
$health = rest_do_request( new WP_REST_Request( 'GET', '/stockland/v16/system/health' ) )->get_data();
chk( 'live_ready false before Owner authorization', empty( $health['checks']['live_ready'] ) );
$auth = new WP_REST_Request( 'POST', '/stockland/v16/system/release-authorization' );
$auth->set_param( 'reason', 'Automated disposable baseline verification' );
chk( 'live release authorization refused while HR approval is pending (HTTP 409)', 409 === rest_do_request( $auth )->get_status() );
$routes = rest_get_server()->get_routes( 'stockland/v16' );
$lifecycle_routes = array_filter( array_keys( $routes ), static function ( $route ) { return false !== strpos( $route, '/hr/lifecycle' ) || false !== strpos( $route, '/hr/payroll-runs' ); } );
chk( 'HR lifecycle and payroll mutation routes are not registered before approval', empty( $lifecycle_routes ), implode( ',', $lifecycle_routes ) );
$failures = $GLOBALS['stockland_verify_fail'];
echo $failures ? "RESULT: FAIL ($failures)\n" : "RESULT: PASS\n";
exit( $failures ? 1 : 0 );
