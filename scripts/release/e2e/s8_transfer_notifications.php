<?php
// Stock transfer (movement between locations) must notify the right audiences at every stage. Runs after s3_transfer.php.
require_once __DIR__ . '/lib.php';
$fail = 0;
$check = function ( $label, $cond ) use ( &$fail ) { echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $cond ) { ++$fail; } };
$tid = qv( "select transfer_id from wp_stock_transfers limit 1" );
$count = function ( $type, $role = null ) use ( $tid ) { $w = $role ? " and audience_role='$role'" : ''; return (int) qv( "select count(*) from wp_stock_notifications where entity_type='transfer' and entity_id='$tid' and notification_type='$type'$w" ); };
$check( 'requested: source warehouse is asked to approve', $count( 'transfer_requested', 'warehouse_manager' ) >= 1 );
$check( 'requested: management informed', $count( 'transfer_requested', 'management' ) >= 1 );
$check( 'approved: warehouse told to reserve and pick', $count( 'transfer_approved', 'warehouse_manager' ) >= 1 );
$check( 'approved: destination branch informed', $count( 'transfer_approved', 'branch_manager' ) >= 1 );
$check( 'in transit: destination branch told to receive', $count( 'transfer_in_transit', 'branch_manager' ) >= 1 );
$check( 'in transit: destination sales staff informed', $count( 'transfer_in_transit', 'sales_employee' ) >= 1 );
$check( 'requester gets personal updates', (int) qv( "select count(*) from wp_stock_notifications where entity_type='transfer' and entity_id='$tid' and user_id is not null" ) >= 1 );
$check( 'receipt notification still raised', $count( 'transfer_received' ) + $count( 'transfer_received', 'owner' ) >= 1 || (int) qv( "select count(*) from wp_stock_notifications where entity_id='$tid' and title like 'Receiving variance%'" ) >= 1 );
$check( 'in-transit notice targets the destination location', (int) qv( "select count(*) from wp_stock_notifications where entity_id='$tid' and notification_type='transfer_in_transit' and audience_role='branch_manager' and location_id=(select to_location_id from wp_stock_transfers where transfer_id='$tid')" ) >= 1 );
echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
if ( $fail ) { exit( 1 ); }
