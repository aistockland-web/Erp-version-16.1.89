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

/* Browser credentials exist only on this disposable CI host and are never committed. */
$warehouse_id = (int) Stockland_DB::get_location_id( 'Central Warehouse' );
$warehouse_employee_id = mkuser( 'whstaff', 'subscriber', 'warehouse_employee', $warehouse_id );
Stockland_Roles::sync_roles();
$warehouse_employee = get_userdata( $warehouse_employee_id );
if ( ! $warehouse_employee || ! $warehouse_employee->set_role( Stockland_Roles::ROLE_WAREHOUSE_EMPLOYEE ) ) {
	fwrite( STDERR, "Could not assign the disposable Warehouse Employee WordPress role." . PHP_EOL );
	exit( 1 );
}
if ( ! user_can( $warehouse_employee_id, 'stockland_view_warehouse' ) || ! user_can( $warehouse_employee_id, 'stockland_manage_transfers' ) ) {
	fwrite( STDERR, "Disposable Warehouse Employee is missing the required Warehouse UI capabilities." . PHP_EOL );
	exit( 1 );
}
$users['we'] = $warehouse_employee_id;

$credentials = array();
foreach ( array( 'bm', 'mg', 'wm', 'we' ) as $key ) {
	$user_id = (int) ( $users[ $key ] ?? 0 );
	$user    = $user_id ? get_userdata( $user_id ) : false;
	if ( ! $user ) {
		fwrite( STDERR, 'Missing disposable UI login for role key ' . $key . PHP_EOL );
		exit( 1 );
	}
	$password = wp_generate_password( 40, true, true );
	wp_set_password( $password, $user_id );
	$credentials[ $key ] = array(
		'login'    => (string) $user->user_login,
		'password' => $password,
	);
}
$credential_path = '/tmp/stockland-ui-credentials.json';
$written = file_put_contents( $credential_path, wp_json_encode( $credentials ), LOCK_EX );
if ( false === $written ) {
	fwrite( STDERR, "Could not write temporary UI credentials." . PHP_EOL );
	exit( 1 );
}
chmod( $credential_path, 0600 );
echo 'PASS disposable UI requisition fixture and short-lived role credentials created: ' . $id . PHP_EOL;
