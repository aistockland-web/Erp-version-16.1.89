<?php
require __DIR__ . '/lib.php';
global $wpdb;
$u = get_option( 'e2e_users', array() );
$failed = 0;
$check = static function ( $label, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail ? ' — ' . $detail : '' ) . PHP_EOL;
	if ( ! $ok ) { ++$failed; }
};
$sku = 'STK-TEE-001';
$branch_ids = Stockland_Authority::location_ids( (int) ( $u['se'] ?? 0 ) );
$branch_id = (int) ( $branch_ids[0] ?? 0 );
$warehouse_id = (int) Stockland_DB::get_location_id( 'Central Warehouse' );
$check( 'required synthetic approver/requester fixtures exist', ! empty( $u['se'] ) && ! empty( $u['bm'] ) && ! empty( $u['mg'] ) && ! empty( $u['ow2'] ) && $branch_id > 0 && $warehouse_id > 0 );
$available_before = (float) Stockland_DB::get_available_stock( $warehouse_id, $sku );
$branch_before = (float) Stockland_DB::get_available_stock( $branch_id, $sku );
$check( 'Central Warehouse has live stock for approval-chain E2E', $available_before >= 1.0, 'available=' . $available_before );
if ( $failed || $available_before < 1.0 ) { exit( 1 ); }

$we = mkuser( 'whstaff', 'subscriber', 'warehouse_employee', $warehouse_id );
Stockland_Roles::sync_roles();
$check( 'Warehouse Employee fixture is assigned to Central Warehouse', Stockland_Authority::role( $we ) === Stockland_Authority::WAREHOUSE_EMPLOYEE );

$request = array(
	'location_id' => $branch_id,
	'request_key' => 'STOCKLAND_REQ_E2E_20261122_MAIN',
	'notes' => 'Synthetic disposable requisition approval-chain E2E fixture.',
	'items' => array( array( 'sku' => $sku, 'quantity' => 1 ) ),
);
$created = call( 'POST', '/requisitions', $request, (int) $u['se'] );
$id = sanitize_text_field( (string) ( $created[1]['requisition_id'] ?? '' ) );
$check( 'Sales Employee creates a real branch-scoped requisition', 200 === $created[0] && '' !== $id, 'HTTP ' . $created[0] );
if ( '' === $id ) { exit( 1 ); }
$replay = call( 'POST', '/requisitions', $request, (int) $u['se'] );
$check( 'same idempotency key replays without a duplicate requisition', 200 === $replay[0] && ! empty( $replay[1]['replayed'] ) && $id === (string) ( $replay[1]['requisition_id'] ?? '' ) );
$employee_review = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/decision', array( 'stage' => 'review', 'decision' => 'REVIEW' ), (int) $u['se'] );
$check( 'Sales Employee cannot perform Branch Manager review', 403 === $employee_review[0], 'HTTP ' . $employee_review[0] );
$bm_final = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/decision', array( 'stage' => 'management', 'decision' => 'APPROVE' ), (int) $u['bm'] );
$check( 'Branch Manager cannot bypass review to make the final decision', 403 === $bm_final[0], 'HTTP ' . $bm_final[0] );

$review = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/decision', array( 'stage' => 'review', 'decision' => 'REVIEW' ), (int) $u['bm'] );
$review_state = call( 'GET', '/requisitions/' . rawurlencode( $id ), array(), (int) $u['bm'] );
$review_status = (string) ( $review_state[1]['requisition']['status'] ?? '' );
$detail = call( 'GET', '/requisitions/' . rawurlencode( $id ), array(), (int) $u['mg'] );
$items = (array) ( $detail[1]['items'] ?? array() );
$item_id = (int) ( $items[0]['id'] ?? 0 );
$check( 'assigned Branch Manager moves Requested to Reviewed', 200 === $review[0] && 200 === $review_state[0] && 'Reviewed' === $review_status, 'POST HTTP ' . $review[0] . ', detail HTTP ' . $review_state[0] . ', status=' . $review_status );
$check( 'server exposes the ONE_OF approval contract and live item identity', 200 === $detail[0] && 'ONE_OF' === ( $detail[1]['approval_mode'] ?? '' ) && $item_id > 0 );
$list = call( 'GET', '/requisitions', array(), (int) $u['mg'] );
$row = array();
foreach ( (array) ( $list[1] ?? array() ) as $candidate ) {
	if ( (string) ( $candidate['requisition_id'] ?? '' ) === $id ) { $row = $candidate; break; }
}
$check( 'Management queue contains its authorized MANAGEMENT_APPROVE action', in_array( 'MANAGEMENT_APPROVE', (array) ( $row['available_actions'] ?? array() ), true ) );

$employee_approve = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/decision', array( 'stage' => 'management', 'decision' => 'APPROVE', 'approved_items' => array( $item_id => 1 ) ), (int) $u['se'] );
$check( 'Sales Employee cannot call the Management approval endpoint', 403 === $employee_approve[0] );
$management_approve = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/decision', array( 'stage' => 'management', 'decision' => 'APPROVE', 'approved_items' => array( $item_id => 1 ), 'note' => '' ), (int) $u['mg'] );
$management_state = call( 'GET', '/requisitions/' . rawurlencode( $id ), array(), (int) $u['mg'] );
$management_status = (string) ( $management_state[1]['requisition']['status'] ?? '' );
$check( 'Management provides the single final approval', 200 === $management_approve[0] && 200 === $management_state[0] && 'Approved' === $management_status, 'POST HTTP ' . $management_approve[0] . ', detail HTTP ' . $management_state[0] . ', status=' . $management_status );
$owner_second = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/decision', array( 'stage' => 'owner', 'decision' => 'APPROVE', 'approved_items' => array( $item_id => 1 ) ), (int) $u['ow2'] );
$check( 'Owner is not required for a second approval after Management finalizes ONE_OF', 409 === $owner_second[0] );
$dbrow = Stockland_DB::read_row( $wpdb->prepare( 'SELECT status,management_decision,owner_decision,management_approved_by,owner_approved_by FROM %i WHERE requisition_id=%s', Stockland_DB::table( 'requisitions' ), $id ), ARRAY_A );
$check( 'database records Management approval and no Owner approval', 'Approved' === ( $dbrow['status'] ?? '' ) && 'APPROVE' === ( $dbrow['management_decision'] ?? '' ) && ! empty( $dbrow['management_approved_by'] ) && empty( $dbrow['owner_approved_by'] ) );

$employee_direct_pick = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/warehouse-action', array( 'action' => 'pick' ), $we );
$check( 'Warehouse Employee cannot pick an unreserved Approved requisition', 403 === $employee_direct_pick[0] );
$reserve = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/warehouse-action', array( 'action' => 'reserve', 'pickup_date' => '2026-11-23' ), (int) $u['wm'] );
$check( 'Central Warehouse Manager reserves stock and confirms pickup date', 200 === $reserve[0] && 'Reserved' === ( $reserve[1]['requisition']['status'] ?? '' ) );
$pick = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/warehouse-action', array( 'action' => 'pick' ), $we );
$check( 'Warehouse Employee picks only after Manager reservation', 200 === $pick[0] && 'Picked' === ( $pick[1]['requisition']['status'] ?? '' ) );
$pick_replay = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/warehouse-action', array( 'action' => 'pick' ), $we );
$check( 'replayed warehouse pick is idempotent', 200 === $pick_replay[0] && ! empty( $pick_replay[1]['idempotent'] ) );

$branch_detail = call( 'GET', '/requisitions/' . rawurlencode( $id ), array(), (int) $u['bm'] );
$receipt_lines = array();
foreach ( (array) ( $branch_detail[1]['transfers'] ?? array() ) as $t ) {
	if ( 'Picked' === (string) ( $t['status'] ?? '' ) ) {
		$receipt_lines[] = array( 'transfer_id' => (string) $t['transfer_id'], 'received_qty' => (float) ( $t['dispatched_qty'] ?? $t['quantity'] ?? 0 ), 'reason' => '' );
	}
}
$check( 'Branch Manager sees the exact Picked supply line to receive', 200 === $branch_detail[0] && 1 === count( $receipt_lines ) && $receipt_lines[0]['received_qty'] > 0 );
$receive = call( 'POST', '/requisitions/' . rawurlencode( $id ) . '/receive', array( 'receipts' => $receipt_lines ), (int) $u['bm'] );
$receive_state = call( 'GET', '/requisitions/' . rawurlencode( $id ), array(), (int) $u['bm'] );
$received_status = (string) ( $receive_state[1]['requisition']['status'] ?? '' );
$check( 'assigned Branch Manager receives picked stock into branch ledger', 200 === $receive[0] && 200 === $receive_state[0] && 'Received' === $received_status, 'POST HTTP ' . $receive[0] . ', detail HTTP ' . $receive_state[0] . ', status=' . $received_status );
$transfer_rows = Stockland_DB::read_results( $wpdb->prepare( 'SELECT status,dispatched_qty,received_qty FROM %i WHERE requisition_id=%s', Stockland_DB::table( 'transfers' ), $id ), ARRAY_A );
$check( 'transfer status and dispatched/received quantities reconcile', 1 === count( $transfer_rows ) && 'Received' === ( $transfer_rows[0]['status'] ?? '' ) && abs( (float) ( $transfer_rows[0]['dispatched_qty'] ?? 0 ) - (float) ( $transfer_rows[0]['received_qty'] ?? 0 ) ) < 0.00001 );
$branch_after = (float) Stockland_DB::get_available_stock( $branch_id, $sku );
$central_after = (float) Stockland_DB::get_available_stock( $warehouse_id, $sku );
$check( 'branch ledger availability increases by the received unit', $branch_after >= $branch_before + 0.999, 'before=' . $branch_before . ' after=' . $branch_after );
$check( 'Central Warehouse availability decreases by the dispatched unit', $central_after <= $available_before - 0.999, 'before=' . $available_before . ' after=' . $central_after );
$events = Stockland_DB::read_results( $wpdb->prepare( 'SELECT from_status,to_status FROM %i WHERE entity_type=%s AND entity_id=%s', Stockland_DB::table( 'workflow_events' ), 'requisition', $id ), ARRAY_A );
$pairs = array();
foreach ( $events as $event ) { $pairs[] = (string) $event['from_status'] . '>' . (string) $event['to_status']; }
$expected = array( 'Requested>Reviewed', 'Reviewed>Approved', 'Approved>Reserved', 'Reserved>Picked', 'Picked>Received' );
foreach ( $expected as $pair ) { $check( 'workflow event recorded exactly once: ' . $pair, 1 === count( array_keys( $pairs, $pair, true ) ) ); }

/* Alternative Owner final decision plus live shortage/partial-approval check. */
$central_remaining = (float) Stockland_DB::get_available_stock( $warehouse_id, $sku );
$short_request = array(
	'location_id' => $branch_id,
	'request_key' => 'STOCKLAND_REQ_E2E_20261122_SHORT',
	'notes' => 'Synthetic disposable shortage and partial-approval fixture.',
	'items' => array( array( 'sku' => $sku, 'quantity' => $central_remaining + 3.0 ) ),
);
$short_created = call( 'POST', '/requisitions', $short_request, (int) $u['se'] );
$short_id = sanitize_text_field( (string) ( $short_created[1]['requisition_id'] ?? '' ) );
$check( 'shortage fixture requisition is created', 200 === $short_created[0] && '' !== $short_id );
if ( '' === $short_id ) { exit( 1 ); }
$short_review = call( 'POST', '/requisitions/' . rawurlencode( $short_id ) . '/decision', array( 'stage' => 'review', 'decision' => 'REVIEW' ), (int) $u['bm'] );
$short_detail = call( 'GET', '/requisitions/' . rawurlencode( $short_id ), array(), (int) $u['ow2'] );
$short_item_row = Stockland_DB::read_row(
	$wpdb->prepare( 'SELECT id,quantity_requested FROM %i WHERE requisition_id=%s ORDER BY id ASC LIMIT 1', Stockland_DB::table( 'requisition_items' ), $short_id ),
	ARRAY_A
);
$short_item_id = (int) ( $short_item_row['id'] ?? 0 );
$short_requested = (float) ( $short_item_row['quantity_requested'] ?? 0 );
$short_available = (float) Stockland_DB::get_available_stock( $warehouse_id, $sku );
$check( 'live requisition detail exposes the Central Warehouse shortfall', 200 === $short_review[0] && 200 === $short_detail[0] && $short_item_id > 0 && $short_requested > $short_available, 'requested=' . $short_requested . ', Central available=' . $short_available );
$owner_full = call( 'POST', '/requisitions/' . rawurlencode( $short_id ) . '/decision', array( 'stage' => 'owner', 'decision' => 'APPROVE', 'approved_items' => array( $short_item_id => $short_requested ) ), (int) $u['ow2'] );
$check( 'Owner full approval is refused when requested quantity exceeds live availability', 409 === $owner_full[0] );
$owner_partial = call( 'POST', '/requisitions/' . rawurlencode( $short_id ) . '/decision', array( 'stage' => 'owner', 'decision' => 'PARTIAL', 'approved_items' => array( $short_item_id => 1 ), 'note' => 'Synthetic E2E partial approval capped below live availability.' ), (int) $u['ow2'] );
$owner_partial_state = call( 'GET', '/requisitions/' . rawurlencode( $short_id ), array(), (int) $u['ow2'] );
$owner_partial_status = (string) ( $owner_partial_state[1]['requisition']['status'] ?? '' );
$check( 'Owner can make the alternative final partial decision with a reason', 200 === $owner_partial[0] && 200 === $owner_partial_state[0] && 'Approved' === $owner_partial_status, 'POST HTTP ' . $owner_partial[0] . ', detail HTTP ' . $owner_partial_state[0] . ', status=' . $owner_partial_status );
$management_second = call( 'POST', '/requisitions/' . rawurlencode( $short_id ) . '/decision', array( 'stage' => 'management', 'decision' => 'APPROVE', 'approved_items' => array( $short_item_id => 1 ) ), (int) $u['mg'] );
$check( 'Management is not required for a second approval after Owner finalizes ONE_OF', 409 === $management_second[0] );
$owner_row = Stockland_DB::read_row( $wpdb->prepare( 'SELECT status,management_decision,owner_decision,management_approved_by,owner_approved_by FROM %i WHERE requisition_id=%s', Stockland_DB::table( 'requisitions' ), $short_id ), ARRAY_A );
$check( 'database records one Owner partial decision and no Management approval', 'Approved' === ( $owner_row['status'] ?? '' ) && 'PARTIAL' === ( $owner_row['owner_decision'] ?? '' ) && ! empty( $owner_row['owner_approved_by'] ) && empty( $owner_row['management_approved_by'] ) );

if ( $failed ) {
	echo 'RESULT FAIL (' . $failed . ')' . PHP_EOL;
	exit( 1 );
}
echo 'RESULT PASS: Branch Manager review → one final Owner OR Management decision → Manager reserve → Warehouse Employee pick → Branch Manager receipt; shortage, idempotency, ledger and audit transitions verified.' . PHP_EOL;
