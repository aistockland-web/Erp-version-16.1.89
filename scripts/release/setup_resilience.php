<?php
// Setup resilience on a DISPOSABLE site that already runs the master installer: deep scan, repeat setup, retry after a lost table,
// resume after an interrupted run, and refusal while another installer holds the lock. Never run on production.
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing: STOCKLAND_DISPOSABLE_SITE=yes required\n" ); exit( 2 ); }
global $wpdb; wp_set_current_user( 1 ); $fail = 0;
$chk = function ( $l, $c, $d = '' ) use ( &$fail ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $l . ( $c ? '' : " :: $d" ) . "\n"; if ( ! $c ) { ++$fail; } };
$deep = function () { $id = ''; $off = 0; for ( $i = 0; $i < 40; $i++ ) { $q = new WP_REST_Request( 'GET', '/stockland/v16/system/health' ); $q->set_param( 'mode', 'deep' ); $q->set_param( 'scan_id', $id ); $q->set_param( 'scan_offset', $off ); $s = rest_do_request( $q )->get_data()['diagnostics']['supplemental_integrity_scan']; $id = $s['scan_id']; if ( ! empty( $s['complete'] ) || empty( $s['next_offset'] ) ) { return $s; } $off = $s['next_offset']; } return array(); };
$count = function () { return count( Stockland_Activator::application_table_manifest() ) - count( Stockland_Activator::application_missing_tables() ); };
$s = $deep(); $chk( 'deep integrity scan 52/52', 'RELATIONAL_CHECKS_PASS' === ( $s['overall_status'] ?? '' ) && 52 === (int) ( $s['completed_check_count'] ?? 0 ), (string) ( $s['overall_status'] ?? '' ) );
$before = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'products' ) ) . '/' . $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'transactions' ) );
$r1 = Stockland_Installer::run( 1, 20 ); $r2 = Stockland_Installer::run( 1, 20 );
$chk( 'repeat setup is a no-op and stays complete', ! empty( $r1['complete'] ) && ! empty( $r2['complete'] ) && 86 === $count() );
$chk( 'repeat setup never touches business rows', $before === $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'products' ) ) . '/' . $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'transactions' ) ) );
// Retry after a table is lost (empty, unreferenced table only: the QA site's notification table).
$tn = Stockland_DB::table( 'notifications' ); $wpdb->query( "DROP TABLE `$tn`" );
$chk( 'lost table is detected', 85 === $count() );
$r = Stockland_Installer::run( 1, 20 );
$chk( 'retry recreates only the missing table and completes', ! empty( $r['complete'] ) && 86 === $count() && 'LIVE_SCHEMA_READY' === get_option( 'stockland_setup_state' ), json_encode( $r['errors'] ?? array() ) );
// Interrupted run: zero time budget must return a resumable partial result without marking ready, then resume.
$wpdb->query( "DROP TABLE `$tn`" ); update_option( 'stockland_setup_state', 'INVARIANTS_PENDING' );
$r = Stockland_Installer::run( 1, 0 );
$chk( 'interrupted run is reported as incomplete, not ready', empty( $r['complete'] ) && 'LIVE_SCHEMA_READY' !== get_option( 'stockland_setup_state' ) );
$r = Stockland_Installer::run( 1, 20 );
$chk( 'resume completes the remaining work', ! empty( $r['complete'] ) && 86 === $count() && 'LIVE_SCHEMA_READY' === get_option( 'stockland_setup_state' ) );
// Concurrency: a second installer is refused while the lock is held.
set_transient( Stockland_Installer::LOCK, 1, 60 ); $r = Stockland_Installer::run( 1, 5 ); delete_transient( Stockland_Installer::LOCK );
$chk( 'concurrent installer is refused while locked', empty( $r['complete'] ) && ! empty( $r['errors'] ) );
$r = Stockland_Installer::run( 1, 20 ); $chk( 'installer works again after the lock clears', ! empty( $r['complete'] ) );
$s = $deep(); $chk( 'deep integrity scan 52/52 after the drills', 'RELATIONAL_CHECKS_PASS' === ( $s['overall_status'] ?? '' ) && 52 === (int) ( $s['completed_check_count'] ?? 0 ) );
echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n"; if ( $fail ) { exit( 1 ); }
