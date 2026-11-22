<?php
require __DIR__ . '/lib.php';

global $wpdb;
$u = get_option( 'e2e_users', array() );
$fail = 0;
$check = static function ( $label, $condition, $detail = '' ) use ( &$fail ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . ( $detail ? ' — ' . $detail : '' ) . PHP_EOL;
	if ( ! $condition ) {
		++$fail;
	}
};

$sku = 'STK-TEE-001';
$branch_ids = Stockland_Authority::location_ids( (int) ( $u['se'] ?? 0 ) );
$branch_id = (int) ( $branch_ids[0] ?? 0 );
$warehouse_id = (int) Stockland_DB::get_location_id( 'Central Warehouse' );
$check( 'synthetic Sales Employee, Branch Manager, Management and Warehouse Manager fixtures exist', ! empty( $u['se'] ) && ! empty( $u['bm'] ) && ! empty( $u['mg'] ) && ! empty( $u['wm'] ) && $branch_id > 0 && $warehouse_id > 0 );
if ( $fail ) {
	exit( 1 );
}

$product = Stockland_DB::get_product( $sku );
$check( 'requisition fixture SKU has a live immutable Product Master variant', is_array( $product ) && ! empty( $product['variant_id'] ) );
$central_before = (float) Stockland_DB::get_available_stock( $warehouse_id, $sku );
$branch_before = (float) Stockland_DB::get_available_stock( $branch_id, $sku );
$check( 'Central Warehouse has enough live stock for the one-unit E2E request', $central_before >= 1.0, 'available=' . $central_before );

$request = array(
	'location_id' => $branch_id,
	'request_key' => 'STOCKLAND_REQ_E2E_20261122_01',
	'notes'       => 'Synthetic disposable requisition approval-chain E2E fixture.',
	'items'       => array(
		array(
			'sku'      => $sku,
			'quantity' => 1,
		),
	),
);
$created = call( 'POST', '/requisitions', $request, (int) $u['se'] );
$check( 'Sales Employee creates a branch-scoped requisition through the real REST route', 200 === $created[0] && ! empty( $created[1]['requisition_id'] ), 'HTTP ' . $created[0] );
$requisition_id = sanitize_text_field( (string) ( $created[1]['requisition_id'] ?? '' ) );
if ( '' === $requisition_id ) {
	echo "RESULT FAIL (requisition creation did not return an ID)" . PHP_EOL;
	exit( 1 );
}

$replay = call( 'POST', '/requisitions', $request, (int) $u['se'] );
$check( 'same request_key replays the existing requisition instead of creating a duplicate', 200 === $replay[0] && ! empty( $replay[1]['replayed'] ) && $requisition_id === (string) ( $replay[1]['requisition_id'] ?? '' ), 'HTTP ' . $replay[0] );

$wrong_reviewer = call( 'POST', '/requisitions/' . rawurlencode( $requisition_id ) . '/decision', array( 'stage' => 'review', 'decision' => 'REVIEW' ), (int) $u['se'] );
$check( 'Sales Employee cannot perform the Branch Manager review action', 403 === $wrong_reviewer[0], 'HTTP ' . $wrong_reviewer[0] );

$review = call( 'POST', '/requisitions/' . rawurlencode( $requisition_id ) . '/decision', array( 'stage' => 'review', 'decision' => 'REVIEW' ), (int) $u['bm'] );
$review_status = (string) ( $review[1]['requisition']['status'] ?? '' );
$check( 'assigned Branch Manager review moves Requested to Reviewed and returns live detail', 200 === $review[0] && 'Reviewed' === $review_status, 'HTTP ' . $review[0] . ' status=' . $review_status );

$management_detail = call( 'GET', '/requisitions/' . rawurlencode( $requisition_id ), array(), (int) $u['mg'] );
$items = (array) ( $management_detail[1]['items'] ?? array() );
$item_id = (int) ( $items[0]['id'] ?? 0 );
$mode = (string) ( $management_detail[1]['approval_mode'] ?? '' );
$check( 'detail endpoint declares ONE_OF approval and returns the server-side item identity', 200 === $management_detail[0] && 'ONE_OF' === $mode && $item_id > 0, 'mode=' . $mode );

$management_list = call( 'GET', '/requisitions', array(), (int) $u['mg'] );
$management_row = array();
foreach ( (array) ( $management_list[1] ?? array() ) as $row ) {
	if ( (string) ( $row['requisition_id'] ?? '' ) === $requisition_id ) {
		$management_row = $row;
		break;
	}
}
$check( 'Management queue exposes the actual MANAGEMENT_APPROVE action', in_array( 'MANAGEMENT_APPROVE', (array) ( $management_row['available_actions'] ?? array() ), true ) );

$unauthorized_approval = call(
	'POST',
	'/requisitions/' . rawurlencode( $requisition_id ) . '/decision',
	array( 'stage' => 'management', 'decision' => 'APPROVE', 'approved_items' => array( $item_id => 1 ) ),
	(int) $u['se']
);
$check( 'Sales Employee cannot invoke the management approval endpoint', 403 === $unauthorized_approval[0], 'HTTP ' . $unauthorized_approval[0] );

$approval = call(
	'POST',
	'/requisitions/' . rawurlencode( $requisition_id ) . '/decision',
	array(
		'stage'          => 'management',
		'decision'       => 'APPROVE',
		'approved_items' => array( $item_id => 1 ),
		'note'           => '',
	),
	(int) $u['mg']
);
$approved_status = (string) ( $approval[1]['requisition']['status'] ?? '' );
$check( 'Management can make the single final ONE_OF approval and create warehouse supply', 200 === $approval[0] && 'Approved' === $approved_status, 'HTTP ' . $approval[0] . ' status=' . $approved_status );

$owner_second_decision = call(
	'POST',
	'/requisitions/' . rawurlencode( $requisition_id ) . '/decision',
	array(
		'stage'          => 'owner',
		'decision'       => 'APPROVE',
		'approved_items' => array( $item_id => 1 ),
		'note'           => '',
	),
	(int) $u['ow2']
);
$check( 'Owner is not asked for a second approval after Management finalizes ONE_OF', 409 === $owner_second_decision[0], 'HTTP ' . $owner_second_decision[0] );

$approved_row = Stockland_DB::read_row(
	$wpdb->prepare( 'SELECT status,management_decision,owner_decision FROM %i WHERE requisition_id=%s', Stockland_DB::table( 'requisitions' ), $requisition_id ),
	ARRAY_A
);
$check( 'database records one Management decision and no second Owner decision', 'Approved' === ( $approved_row['status'] ?? '' ) && 'APPROVE' === ( $approved_row['management_decision'] ?? '' ) && empty( $approved_row['owner_decision'] ) );

$reserved = call(
	'POST',
	'/requisitions/' . rawurlencode( $requisition_id ) . '/warehouse-action',
	array( 'action' => 'reserve', 'pickup_date' => '2026-11-23' ),
	(int) $u['wm']
);
$reserved_status = (string) ( $reserved[1]['requisition']['status'] ?? '' );
$check( 'Central Warehouse Manager reserves approved stock with a pickup appointment', 200 === $reserved[0] && 'Reserved' === $reserved_status, 'HTTP ' . $reserved[0] . ' status=' . $reserved_status );

$picked = call(
	'POST',
	'/requisitions/' . rawurlencode( $requisition_id ) . '/warehouse-action',
	array( 'action' => 'pick' ),
	(int) $u['wm']
);
$picked_status = (string) ( $picked[1]['requisition']['status'] ?? '' );
$check( 'Central Warehouse Manager picks the reserved supply through the real REST route', 200 === $picked[0] && 'Picked' === $picked_status, 'HTTP ' . $picked[0] . ' status=' . $picked_status );

$pick_replay = call(
	'POST',
	'/requisitions/' . rawurlencode( $requisition_id ) . '/warehouse-action',
	array( 'action' => 'pick' ),
	(int) $u['wm']
);
$check( 'replayed warehouse pick is idempotent and posts no second dispatch', 200 === $pick_replay[0] && ! empty( $pick_replay[1]['idempotent'] ), 'HTTP ' . $pick_replay[0] );

$branch_detail = call( 'GET', '/requisitions/' . rawurlencode( $requisition_id ), array(), (int) $u['bm'] );
$transfers = (array) ( $branch_detail[1]['transfers'] ?? array() );
$receipts = array();
foreach ( $transfers as $transfer ) {
	if ( 'Picked' === (string) ( $transfer['status'] ?? '' ) ) {
		$receipts[] = array(
			'transfer_id' => (string) $transfer['transfer_id'],
			'received_qty' => (float) ( $transfer['dispatched_qty'] ?? $transfer['quantity'] ?? 0 ),
			'reason' => '',
		);
	}
}
$check( 'Branch Manager detail exposes the exact Picked transfer to receive', 200 === $branch_detail[0] && 1 === count( $receipts ) && $receipts[0]['received_qty'] > 0 );

$received = call(
	'POST',
	'/requisitions/' . rawurlencode( $requisition_id ) . '/receive',
	array( 'receipts' => $receipts ),
	(int) $u['bm']
);
$received_status = (string) ( $received[1]['requisition']['status'] ?? '' );
$check( 'assigned Branch Manager receives the picked stock into the branch ledger', 200 === $received[0] && 'Received' === $received_status, 'HTTP ' . $received[0] . ' status=' . $received_status );

$transfer_rows = Stockland_DB::read_results(
	$wpdb->prepare( 'SELECT transfer_id,status,dispatched_qty,received_qty FROM %i WHERE requisition_id=%s', Stockland_DB::table( 'transfers' ), $requisition_id ),
	ARRAY_A
);
$check( 'supply transfer is Received and its dispatched/received quantities reconcile', 1 === count( $transfer_rows ) && 'Received' === ( $transfer_rows[0]['status'] ?? '' ) && abs( (float) ( $transfer_rows[0]['dispatched_qty'] ?? 0 ) - (float) ( $transfer_rows[0]['received_qty'] ?? 0 ) ) < 0.00001 );

$branch_after = (float) Stockland_DB::get_available_stock( $branch_id, $sku );
$central_after = (float) Stockland_DB::get_available_stock( $warehouse_id, $sku );
$check( 'ledger receipt increases branch availability by the delivered unit', $branch_after >= $branch_before + 0.999, 'before=' . $branch_before . ' after=' . $branch_after );
$check( 'ledger dispatch reduces Central Warehouse availability by the delivered unit', $central_after <= $central_before - 0.999, 'before=' . $central_before . ' after=' . $central_after );

$events = Stockland_DB::read_results(
	$wpdb->prepare( 'SELECT from_status,to_status FROM %i WHERE entity_type=%s AND entity_id=%s', Stockland_DB::table( 'workflow_events' ), 'requisition', $requisition_id ),
	ARRAY_A
);
$event_pairs = array();
foreach ( $events as $event ) {
	$event_pairs[] = (string) $event['from_status'] . '>' . (string) $event['to_status'];
}
$expected_pairs = array( 'Requested>Reviewed', 'Reviewed>Approved', 'Approved>Reserved', 'Reserved>Picked', 'Picked>Received' );
$all_events = true;
foreach ( $expected_pairs as $pair ) {
	if ( ! in_array( $pair, $event_pairs, true ) ) {
		$all_events = false;
	}
}
$check( 'all five immutable requisition workflow transitions were recorded', $all_events, implode( ', ', $event_pairs ) );

if ( $fail ) {
	echo 'RESULT FAIL (' . $fail . ')' . PHP_EOL;
	exit( 1 );
}
echo 'RESULT PASS: Branch review → one authorized Management approval → warehouse reserve/pick → Branch Manager receipt; live stock, replay and workflow history verified.' . PHP_EOL;
