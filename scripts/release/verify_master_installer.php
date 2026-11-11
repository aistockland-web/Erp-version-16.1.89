<?php
// Run via: wp --user=admin eval-file verify_master_installer.php   (disposable site only)
// Prints PASS/FAIL lines. Never run against a production database.
wp_set_current_user( 1 );
$GLOBALS['stockland_verify_fail'] = 0;
function chk( $label, $ok, $detail = '' ) { echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail ? " ($detail)" : '' ) . "\n"; if ( ! $ok ) { $GLOBALS['stockland_verify_fail']++; } }
global $wpdb;
$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'stock_' ) . '%' ) );
chk( 'physical tables = 86 (29 core + 16 accounting + 41 HR)', 86 === count( $tables ), (string) count( $tables ) );
chk( 'setup state LIVE_SCHEMA_READY', 'LIVE_SCHEMA_READY' === get_option( 'stockland_setup_state' ) );
chk( 'no setup/installer error recorded', ! get_option( 'stockland_setup_error' ) && ! get_option( 'stockland_hr_install_error' ) );
chk( 'Owner profile for installing admin', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}stock_users WHERE wp_user_id=1 AND role='owner' AND is_active=1" ) === 1 );
chk( 'five inventory locations seeded (Central Warehouse + 4 branches)', 5 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}stock_locations WHERE location_type IN ('Warehouse','Branch')" ) );
chk( 'HR approval recorded in audit chain', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}stock_audit_logs WHERE action='HR_SCHEMA_MIGRATION_APPROVED'" ) >= 1 );
$h = rest_do_request( new WP_REST_Request( 'GET', '/stockland/v16/system/health' ) )->get_data();
chk( 'live_ready is false before Owner authorization', empty( $h['checks']['live_ready'] ) );
$q = new WP_REST_Request( 'POST', '/stockland/v16/system/release-authorization' ); $q->set_param( 'reason', 'Automated verification on disposable site' );
chk( 'Owner authorization accepted (HTTP 200)', 200 === rest_do_request( $q )->get_status() );
$cnt = array(); $n = 0;
foreach ( rest_get_server()->get_routes( 'stockland/v16' ) as $route => $hs ) {
	if ( false !== strpos( $route, '(?P' ) ) { continue; }
	foreach ( $hs as $h2 ) { if ( ! isset( $h2['methods']['GET'] ) ) { continue; } $n++; $s = rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_status(); $cnt[ $s ] = ( $cnt[ $s ] ?? 0 ) + 1; }
}
$five = array_sum( array_filter( $cnt, function ( $k ) { return $k >= 500; }, ARRAY_FILTER_USE_KEY ) );
chk( "GET routes return no 5xx ($n routes)", 0 === $five, wp_json_encode( $cnt ) );
$id = ''; $off = 0; $d = array(); $s = array();
for ( $i = 0; $i < 30; $i++ ) {
	$q = new WP_REST_Request( 'GET', '/stockland/v16/system/health' ); $q->set_param( 'mode', 'deep' ); $q->set_param( 'scan_id', $id ); $q->set_param( 'scan_offset', $off );
	$d = rest_do_request( $q )->get_data(); $s = $d['diagnostics']['supplemental_integrity_scan']; $id = $s['scan_id']; if ( ! empty( $s['complete'] ) || empty( $s['next_offset'] ) ) { break; } $off = $s['next_offset'];
}
chk( 'deep integrity scan 52/52', 'RELATIONAL_CHECKS_PASS' === ( $s['overall_status'] ?? '' ) && 52 === (int) ( $s['completed_check_count'] ?? 0 ), ( $s['overall_status'] ?? '' ) );
$bad = array_keys( array_filter( $d['checks'], function ( $v, $k ) { return false === $v && ! in_array( $k, array( 'ok', 'live_ready' ), true ); }, ARRAY_FILTER_USE_BOTH ) );
chk( 'live_ready true after authorization', ! empty( $d['checks']['live_ready'] ), $bad ? 'failing: ' . implode( ',', $bad ) : '' );
$f = $GLOBALS['stockland_verify_fail'];
echo $f ? "RESULT: FAIL ($f)\n" : "RESULT: PASS\n";
exit( $f ? 1 : 0 );
