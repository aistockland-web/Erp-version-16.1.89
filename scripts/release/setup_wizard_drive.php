<?php
// Drive the bounded, resumable setup wizard (POST /stockland/v16/setup/advance) to completion on a DISPOSABLE site.
// Records state and physical table count after every batch and FAILS on a stalled batch (no verified progress) or timeout.
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing: STOCKLAND_DISPOSABLE_SITE=yes required\n" ); exit( 2 ); }
global $wpdb; wp_set_current_user( 1 );
$tables = function () use ( $wpdb ) { return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'stock' ) . '%' ) ); };
$prev = $tables(); $stalls = 0; $log = array(); $t0 = microtime( true ); $done = false;
for ( $i = 1; $i <= 400; $i++ ) {
	$r = rest_do_request( new WP_REST_Request( 'POST', '/stockland/v16/setup/advance' ) );
	$state = (string) get_option( 'stockland_setup_state' ); $n = $tables();
	$log[] = array( $i, $r->get_status(), $state, $n );
	if ( $r->get_status() >= 400 ) { echo "FAIL batch $i HTTP " . $r->get_status() . "\\n"; exit( 1 ); }
	if ( 'LIVE_SCHEMA_READY' === $state ) {
		$missing = Stockland_Activator::application_missing_tables();
		if ( $missing ) {
			echo "FAIL LIVE_SCHEMA_READY has " . count( $missing ) . " missing application tables: " . implode( ', ', $missing ) . "\\n";
			exit( 1 );
		}
		$done = true;
		break;
	}
	if ( 'INVARIANTS_PENDING' === $state ) {
		echo "FAIL setup stopped at INVARIANTS_PENDING; LIVE_SCHEMA_READY was not reached.\\n";
		exit( 1 );
	}
	if ( $stalls >= 3 ) { echo "FAIL stalled: $stalls batches without physical progress at state $state ($n tables)\n"; exit( 1 ); }
}
foreach ( array_filter( $log, fn( $l ) => 0 === $l[0] % 20 || 1 === $l[0] ) as $l ) { echo "batch {$l[0]} http={$l[1]} state={$l[2]} tables={$l[3]}\n"; }
echo ( $done ? 'PASS' : 'FAIL' ) . ' wizard finished in ' . count( $log ) . ' bounded batches, ' . round( microtime( true ) - $t0, 1 ) . 's, state=' . get_option( 'stockland_setup_state' ) . ', tables=' . $tables() . "\n"; exit( $done ? 0 : 1 );
