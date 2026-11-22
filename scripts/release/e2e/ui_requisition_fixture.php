<?php
require __DIR__ . '/lib.php';

$users = get_option( 'e2e_users', array() );
foreach ( array( 'se', 'bm', 'mg', 'wm' ) as $key ) {
	if ( empty( $users[ $key ] ) ) {
		fwrite( STDERR, 'Missing disposable UI test user: ' . $key . PHP_EOL );
		exit( 1 );
	}
}
$branch_ids = Stockland_Authority::location_ids( (int) $users['se'] );
$branch_id = (int) ( $branch_ids[0] ?? 0 );
if ( $branch_id <= 0 ) {
	fwrite( STDERR, "Sales Employee has no assigned test branch." . PHP_EOL );
	exit( 1 );
}
$request = array(
	'location_id' => $branch_id,
	'request_key' => 'STOCKLAND_UI_BUTTON_E2E_20261122',
	'notes' => 'Disposable Playwright click-through fixture. Synthetic data only.',
	'items' => array( array( 'sku' => 'STK-TEE-001', 'quantity' => 1 ) ),
);
$result = call( 'POST', '/requisitions', $request, (int) $users['se'] );
$id = sanitize_text_field( (string) ( $result[1]['requisition_id'] ?? '' ) );
if ( 200 !== $result[0] || '' === $id ) {
	fwrite( STDERR, 'Could not create disposable UI requisition: HTTP ' . $result[0] . ' ' . wp_json_encode( $result[1] ) . PHP_EOL );
	exit( 1 );
}
update_option( 'stockland_ui_requisition_id', $id, false );
echo 'PASS disposable UI requisition fixture created: ' . $id . PHP_EOL;
