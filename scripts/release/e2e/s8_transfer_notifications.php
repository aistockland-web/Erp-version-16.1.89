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

$location_count = static function ( $type, $role, $location_id ) use ( $wpdb, $notification_table, $tid ) {
	return (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM %i WHERE entity_type=%s AND entity_id=%s AND notification_type=%s AND audience_role=%s AND location_id=%d',
		$notification_table,
		'transfer',
		$tid,
		$type,
		$role,
		(int) $location_id
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

$check( 'received: source warehouse manager informed', $count( 'transfer_received', 'warehouse_manager' ) >= 1 );
$check( 'received: destination branch manager informed', $count( 'transfer_received', 'branch_manager' ) >= 1 );
$check( 'received: destination sales staff informed', $count( 'transfer_received', 'sales_employee' ) >= 1 );
$check( 'received: management informed', $count( 'transfer_received', 'management' ) >= 1 );
$check( 'received: owner informed', $count( 'transfer_received', 'owner' ) >= 1 );

$check( 'completed: source warehouse manager informed', $count( 'transfer_completed', 'warehouse_manager' ) >= 1 );
$check( 'completed: destination branch manager informed', $count( 'transfer_completed', 'branch_manager' ) >= 1 );
$check( 'completed: destination sales staff informed', $count( 'transfer_completed', 'sales_employee' ) >= 1 );
$check( 'completed: management informed', $count( 'transfer_completed', 'management' ) >= 1 );
$check( 'completed: owner informed', $count( 'transfer_completed', 'owner' ) >= 1 );

foreach ( array( 'requested', 'approved', 'reserved', 'picked', 'in_transit', 'received', 'completed' ) as $stage ) {
	$check( 'requester receives personal ' . $stage . ' update', $personal_count( 'transfer_' . $stage ) >= 1 );
}

$source = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT from_location_id FROM %i WHERE transfer_id=%s',
	$transfer_table,
	$tid
) );
$destination = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT to_location_id FROM %i WHERE transfer_id=%s',
	$transfer_table,
	$tid
) );

$check( 'in-transit branch notice targets the destination location', $location_count( 'transfer_in_transit', 'branch_manager', $destination ) >= 1 );
$check( 'receipt warehouse notice targets the source location', $location_count( 'transfer_received', 'warehouse_manager', $source ) >= 1 );
$check( 'receipt branch notice targets the destination location', $location_count( 'transfer_received', 'branch_manager', $destination ) >= 1 );
$check( 'receipt sales notice targets the destination location', $location_count( 'transfer_received', 'sales_employee', $destination ) >= 1 );
$check( 'completion warehouse notice targets the source location', $location_count( 'transfer_completed', 'warehouse_manager', $source ) >= 1 );
$check( 'completion branch notice targets the destination location', $location_count( 'transfer_completed', 'branch_manager', $destination ) >= 1 );
$check( 'completion sales notice targets the destination location', $location_count( 'transfer_completed', 'sales_employee', $destination ) >= 1 );

// Exercise the exact read endpoints used by the PWA notification bell.
$wm_read = call( 'GET', '/notifications', array(), (int) $requester );
$bm_read = call( 'GET', '/notifications', array(), (int) $u['bm'] );
$se_read = call( 'GET', '/notifications', array(), (int) $u['se'] );
$check( 'PWA notification GET works for source warehouse manager', 200 === $wm_read[0] && is_array( $wm_read[1] ) );
$check( 'PWA notification GET works for destination branch manager', 200 === $bm_read[0] && is_array( $bm_read[1] ) );
$check( 'PWA notification GET works for destination sales employee', 200 === $se_read[0] && is_array( $se_read[1] ) );

$find_role_notification = static function ( $rows, $type, $role, $location_id ) use ( $tid ) {
	foreach ( (array) $rows as $row ) {
		if (
			(string) ( $row['entity_id'] ?? '' ) === $tid &&
			(string) ( $row['notification_type'] ?? '' ) === $type &&
			(string) ( $row['audience_role'] ?? '' ) === $role &&
			(int) ( $row['location_id'] ?? 0 ) === (int) $location_id
		) {
			return $row;
		}
	}
	return array();
};
$wm_receipt_row = $find_role_notification( $wm_read[1] ?? array(), 'transfer_received', 'warehouse_manager', $source );
$bm_receipt_row = $find_role_notification( $bm_read[1] ?? array(), 'transfer_received', 'branch_manager', $destination );
$se_receipt_row = $find_role_notification( $se_read[1] ?? array(), 'transfer_received', 'sales_employee', $destination );
$check( 'bell GET delivers receipt to source warehouse manager', ! empty( $wm_receipt_row ) );
$check( 'bell GET delivers receipt to destination branch manager', ! empty( $bm_receipt_row ) );
$check( 'bell GET delivers receipt to destination sales employee', ! empty( $se_receipt_row ) );
$check( 'bell GET hides source warehouse-only alert from destination branch manager', empty( $find_role_notification( $bm_read[1] ?? array(), 'transfer_received', 'warehouse_manager', $source ) ) );
$check( 'bell GET hides destination branch-only alert from source warehouse manager', empty( $find_role_notification( $wm_read[1] ?? array(), 'transfer_received', 'branch_manager', $destination ) ) );

$wm_notification_id = (int) ( $wm_receipt_row['id'] ?? 0 );
$bm_notification_id = (int) ( $bm_receipt_row['id'] ?? 0 );
$mark_own = $wm_notification_id ? call( 'POST', '/notifications/' . $wm_notification_id . '/read', array(), (int) $requester ) : array( 0, null );
$check( 'PWA mark-read endpoint accepts an in-scope warehouse alert', 200 === $mark_own[0] && ! empty( $mark_own[1]['success'] ) );
$wm_after_mark = call( 'GET', '/notifications', array(), (int) $requester );
$check( 'marked warehouse alert no longer appears unread', ! in_array( $wm_notification_id, array_map( 'intval', array_column( (array) ( $wm_after_mark[1] ?? array() ), 'id' ) ), true ) );

$foreign_mark = $bm_notification_id ? call( 'POST', '/notifications/' . $bm_notification_id . '/read', array(), (int) $requester ) : array( 0, null );
$check( 'PWA mark-read denies another location role notification', 404 === $foreign_mark[0] );
$bm_after_foreign = call( 'GET', '/notifications', array(), (int) $u['bm'] );
$check( 'foreign mark-read leaves destination alert unread', in_array( $bm_notification_id, array_map( 'intval', array_column( (array) ( $bm_after_foreign[1] ?? array() ), 'id' ) ), true ) );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
if ( $fail ) {
	exit( 1 );
}
