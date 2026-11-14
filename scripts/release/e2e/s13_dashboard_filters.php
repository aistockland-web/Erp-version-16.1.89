<?php
// Dashboard report filters: period and branch narrow the authorized scope and never widen it.
require_once __DIR__ . '/lib.php';
$fail = 0;
$check = function ( $label, $cond ) use ( &$fail ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $cond ) {
		++$fail;
	}
};
$uid = static function ( $login ) {
	$u = get_user_by( 'login', $login );
	return $u ? (int) $u->ID : 0;
};
$owner = $uid( 'owner2' );
$brmgr = $uid( 'brmgr' );
$check( 'fixture users exist', $owner > 0 && $brmgr > 0 );

list( $s, $d ) = call( 'GET', '/app/dashboard/insights', array(), $owner );
$check( 'default call returns 200 with 7 trend points', 200 === $s && 7 === count( $d['trend'] ?? array() ) );
$check( 'default filters echo 7d and no location', '7d' === ( $d['filters']['period'] ?? '' ) && 0 === (int) ( $d['filters']['location_id'] ?? -1 ) );
$base_total = array_sum( array_column( $d['trend'] ?? array(), 'sales' ) );

foreach ( array( '14d' => 14, '30d' => 30 ) as $p => $n ) {
	list( $s, $d ) = call( 'GET', '/app/dashboard/insights', array(), $owner, array( 'period' => $p ) );
	$check( "period $p returns $n trend points", 200 === $s && $n === count( $d['trend'] ?? array() ) );
}
list( $s, $d ) = call( 'GET', '/app/dashboard/insights', array(), $owner, array( 'period' => 'mtd' ) );
$check( 'period mtd returns one point per elapsed day of the month', 200 === $s && (int) current_time( 'j' ) === count( $d['trend'] ?? array() ) );
list( $s ) = call( 'GET', '/app/dashboard/insights', array(), $owner, array( 'period' => 'bogus' ) );
$check( 'unknown period is rejected', 400 === $s );

list( $s, $d ) = call( 'GET', '/app/dashboard/insights', array(), $owner, array( 'location_id' => 2 ) );
$loc_total = array_sum( array_column( $d['trend'] ?? array(), 'sales' ) );
$check( 'owner can filter to branch 2', 200 === $s && 2 === (int) ( $d['filters']['location_id'] ?? 0 ) );
$check( 'branch filter never exceeds the company total', $loc_total <= $base_total + 0.005 );
list( $s, $d ) = call( 'GET', '/app/dashboard/insights', array(), $owner, array( 'location_id' => 1 ) );
$wh_total = array_sum( array_column( $d['trend'] ?? array(), 'sales' ) );
$check( 'warehouse (no retail sales) filter returns zero sales', 200 === $s && abs( $wh_total ) < 0.005 );

list( $s ) = call( 'GET', '/app/dashboard/insights', array(), $brmgr, array( 'location_id' => 2 ) );
$check( 'branch manager can filter to own branch', 200 === $s );
list( $s, $d ) = call( 'GET', '/app/dashboard/insights', array(), $brmgr, array( 'location_id' => 1 ) );
$check( 'branch manager is refused another location (403)', 403 === $s && 'location_out_of_scope' === ( $d['code'] ?? '' ) );
list( $s, $d ) = call( 'GET', '/app/dashboard/insights', array(), $brmgr );
$check( 'branch manager default view stays inside scope', 200 === $s );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
exit( $fail ? 1 : 0 );
