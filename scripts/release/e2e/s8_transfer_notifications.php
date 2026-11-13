<?php
// Stock transfer (movement between locations) must notify the right audiences at every stage.
// Runs immediately after s3_transfer.php, which stores the exact created transfer fixture.
require_once __DIR__ . '/lib.php';
global $wpdb;

$fail = 0;
$check = function ( $label, $cond ) use ( &$fail ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $cond ) {
		++$fail;
	}
};

$fixture = get_option( 'stockland_e2e_transfer_notification_fixture', array() );
$tid = sanitize_text_field( (string) ( $fixture['transfer_id'] ?? '' ) );
$requester = (int) ( $fixture['requester_id'] ?? 0 );
$notification_table = Stockland_DB::table( 'notifications' );
$transfer_table = Stockland_DB::table( 'transfers' );

$check( 'exact transfer fixture was captured by the preceding workflow', '' !== $tid && $requester > 0 );
if ( '' === $tid || $requester < 1 ) {
	echo "RESULT FAIL (missing transfer fixture)\n";
	exit( 1 );
}

$count = static function ( $type, $role = null ) use ( $wpdb, $notification_table, $tid ) {
	if ( null === $role ) {
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE entity_type=%s AND entity_id=%s AND notification_type=%s',
			$notification_table,
			'transfer',
			$tid,
			$type
		) );
	}
	return (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM %i WHERE entity_type=%s AND entity_id=%s AND notification_type=%s AND audience_role=%s',
		$notification_table,
		'transfer',
		$tid,
		$type,
		$role
	) );
};

$personal_count = static function ( $type ) use ( $wpdb, $notification_table, $tid, $requester ) {
	return (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM %i WHERE entity_type=%s AND entity_id=%s AND notification_type=%s AND user_id=%d',
		$notification_table,
		'transfer',
		$tid,
		$type,
		$requester
	) );
};

$check( 'requested: source warehouse is asked to approve', $count( 'transfer_requested', 'warehouse_manager' ) >= 1 );
$check( 'requested: management informed', $count( 'transfer_requested', 'management' ) >= 1 );
$check( 'approved: warehouse told to reserve and pick', $count( 'transfer_approved', 'warehouse_manager' ) >= 1 );
$check( 'approved: destination branch informed', $count( 'transfer_approved', 'branch_manager' ) >= 1 );
$check( 'reserved: source warehouse receives status', $count( 'transfer_reserved', 'warehouse_manager' ) >= 1 );
$check( 'picked: source warehouse receives status', $count( 'transfer_picked', 'warehouse_manager' ) >= 1 );
$check( 'in transit: destination branch told to receive', $count( 'transfer_in_transit', 'branch_manager' ) >= 1 );
$check( 'in transit: destination sales staff informed', $count( 'transfer_in_transit', 'sales_employee' ) >= 1 );

foreach ( array( 'requested', 'approved', 'reserved', 'picked', 'in_transit' ) as $stage ) {
	$check( 'requester receives personal ' . $stage . ' update', $personal_count( 'transfer_' . $stage ) >= 1 );
}

$check(
	'receipt notification still raised',
	$count( 'transfer_received' ) >= 1
		|| (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE entity_type=%s AND entity_id=%s AND title LIKE %s',
			$notification_table,
			'transfer',
			$tid,
			'Receiving variance%'
		) ) >= 1
);

$destination = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT to_location_id FROM %i WHERE transfer_id=%s',
	$transfer_table,
	$tid
) );
$check(
	'in-transit notice targets the destination location',
	(int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM %i WHERE entity_type=%s AND entity_id=%s AND notification_type=%s AND audience_role=%s AND location_id=%d',
		$notification_table,
		'transfer',
		$tid,
		'transfer_in_transit',
		'branch_manager',
		$destination
	) ) >= 1
);

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
if ( $fail ) {
	exit( 1 );
}
