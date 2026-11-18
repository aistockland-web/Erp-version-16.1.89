<?php
/**
 * Real REST + database acceptance for the universal POS integration registry.
 * Runs only through run_all.sh on a disposable synthetic QA database.
 */
require __DIR__ . '/lib.php';
global $wpdb;

$fail = 0;
$checks = 0;
$check = static function ( $label, $condition, $detail = '' ) use ( &$fail, &$checks ) {
	++$checks;
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . ( $condition ? '' : ' :: ' . $detail ) . "\n";
	if ( ! $condition ) {
		++$fail;
	}
};

$users = get_option( 'e2e_users', array() );
$branch_manager_id = (int) ( $users['bm'] ?? 0 );
$management_id = (int) ( $users['mg'] ?? 0 );
$check( 'existing Branch Manager and Management fixtures are available', $branch_manager_id > 0 && $management_id > 0 );
if ( $branch_manager_id < 1 || $management_id < 1 ) {
	echo "RESULT FAIL (missing role fixtures)\n";
	exit( 1 );
}

$locations_table = Stockland_DB::table( 'locations' );
$branches = Stockland_DB::read_results(
	$wpdb->prepare( "SELECT id,location_name FROM %i WHERE location_type='Branch' AND is_active=1 ORDER BY id ASC", $locations_table ),
	ARRAY_A
);
$branch_ids = array_map( 'intval', array_column( (array) $branches, 'id' ) );
$branch_id = (int) ( Stockland_Authority::location_ids( $branch_manager_id )[0] ?? 0 );
$other_branch_id = 0;
foreach ( $branch_ids as $candidate ) {
	if ( $candidate !== $branch_id ) {
		$other_branch_id = $candidate;
		break;
	}
}
$check( 'Branch Manager has one assigned retail branch and a separate test branch exists', $branch_id > 0 && $other_branch_id > 0 );
if ( $branch_id < 1 || $other_branch_id < 1 ) {
	echo "RESULT FAIL (branch fixtures incomplete)\n";
	exit( 1 );
}

$branch_manager_id = mkuser( 'pos-hub-branch-manager', 'subscriber', 'branch_manager', $branch_id );
$branch_manager_user = new WP_User( $branch_manager_id );
$branch_manager_user->set_role( Stockland_Roles::ROLE_BRANCH_MANAGER );
$management_user = new WP_User( $management_id );
$management_user->set_role( Stockland_Roles::ROLE_MANAGEMENT );

$sales_employee_id = mkuser( 'pos-hub-sales-employee', 'subscriber', 'sales_employee', $branch_id );
$sales_user = new WP_User( $sales_employee_id );
$sales_user->set_role( Stockland_Roles::ROLE_SALES_EMPLOYEE );
$auditor_id = mkuser( 'pos-hub-auditor', 'subscriber', 'auditor' );
$auditor_user = new WP_User( $auditor_id );
$auditor_user->set_role( Stockland_Roles::ROLE_AUDITOR );

$adapter_catalog = call( 'GET', '/pos-integrations/adapters', array(), $management_id );
$catalog_rows = is_array( $adapter_catalog[1] ) ? ( $adapter_catalog[1]['connection_patterns'] ?? array() ) : array();
$catalog_codes = array_column( (array) $catalog_rows, 'code' );
$check( 'Management can read the universal connector pattern catalogue', 200 === $adapter_catalog[0] && in_array( 'REST_API', $catalog_codes, true ) && in_array( 'WEBHOOK', $catalog_codes, true ) && in_array( 'LOCAL_GATEWAY', $catalog_codes, true ) && in_array( 'DEVICE_SDK', $catalog_codes, true ) && in_array( 'FILE_EXCHANGE', $catalog_codes, true ) );
$settings_table = Stockland_DB::table( 'settings' );
$audit_table = Stockland_DB::table( 'audit_logs' );
$check( 'existing Core settings and immutable audit stores are available', Stockland_DB::table_exists( $settings_table ) && Stockland_DB::table_exists( $audit_table ) );

$application_tables_before = Stockland_DB::read_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'stock_' ) . '%' ) );
$ledger_keys = array( 'transactions', 'sales', 'journals', 'journal_entries', 'gl_entries', 'stock_balances' );
$ledger_before = array();
foreach ( $ledger_keys as $key ) {
	$table = Stockland_DB::table( $key );
	if ( Stockland_DB::table_exists( $table ) ) {
		$ledger_before[ $key ] = (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}
}

$branch_registration = call(
	'POST',
	'/pos-integrations/devices',
	array(
		'branch_location_id' => $branch_id,
		'vendor' => 'Synthetic POS Vendor A',
		'device_name' => 'Branch Manager POS Terminal A',
		'model' => 'QA Model 1',
		'connection_type' => 'REST_API',
	),
	$branch_manager_id
);
list( $branch_status, $branch_data ) = $branch_registration;
$branch_device = is_array( $branch_data ) ? ( $branch_data['device'] ?? array() ) : array();
$branch_device_id = (string) ( $branch_device['device_id'] ?? '' );
$branch_source_id = (string) ( $branch_device['source_system'] ?? '' );
$check( 'Branch Manager registers a device for an assigned branch', 200 === $branch_status && preg_match( '/^[A-F0-9]{16}$/', $branch_device_id ), 'HTTP ' . $branch_status . ' :: ' . wp_json_encode( $branch_data ) );
$check( 'registration remains NOT_CONNECTED with no configured credentials', 'NOT_CONNECTED' === ( $branch_device['connection_status'] ?? '' ) && 'NOT_CONFIGURED' === ( $branch_device['credential_status'] ?? '' ) );
$check( 'registration defaults to automatic validate-and-post with no manual release layer', true === ( $branch_device['auto_post'] ?? null ) && 'AUTOMATIC_VALIDATE_AND_POST' === ( $branch_device['posting_path'] ?? '' ), wp_json_encode( $branch_device ) );
$check( 'device is assigned its own unique POS source-system namespace', (bool) preg_match( '/^POS_[a-f0-9]{16}$/', $branch_source_id ) );

$branch_list = call( 'GET', '/pos-integrations/devices', array(), $branch_manager_id );
$visible_devices = is_array( $branch_list[1] ) ? ( $branch_list[1]['devices'] ?? array() ) : array();
$check( 'Branch Manager can read the registry for the assigned branch', 200 === $branch_list[0] && (bool) array_filter( $visible_devices, static function ( $device ) use ( $branch_device_id ) {
	return ( $device['device_id'] ?? '' ) === $branch_device_id;
} ) );

$wrong_branch_registration = call(
	'POST',
	'/pos-integrations/devices',
	array(
		'branch_location_id' => $other_branch_id,
		'vendor' => 'Synthetic POS Vendor A',
		'device_name' => 'Forbidden Cross-Branch Device',
		'connection_type' => 'LOCAL_GATEWAY',
	),
	$branch_manager_id
);
$check( 'Branch Manager cannot register a device for another branch', 403 === $wrong_branch_registration[0], 'HTTP ' . $wrong_branch_registration[0] );

$secret_registration = call(
	'POST',
	'/pos-integrations/devices',
	array(
		'branch_location_id' => $branch_id,
		'vendor' => 'Synthetic POS Vendor A',
		'device_name' => 'Rejected Secret Payload',
		'connection_type' => 'WEBHOOK',
		'api_key' => 'synthetic-secret-must-not-persist',
	),
	$branch_manager_id
);
$check( 'device registration rejects inline API credentials', 400 === $secret_registration[0], 'HTTP ' . $secret_registration[0] );

$sales_list = call( 'GET', '/pos-integrations/devices', array(), $sales_employee_id );
$sales_register = call(
	'POST',
	'/pos-integrations/devices',
	array(
		'branch_location_id' => $branch_id,
		'vendor' => 'Synthetic POS Vendor A',
		'device_name' => 'Sales Employee Must Not Register',
		'connection_type' => 'FILE_EXCHANGE',
	),
	$sales_employee_id
);
$check( 'Sales Employee may read devices inside assigned branch scope', 200 === $sales_list[0] );
$check( 'Sales Employee cannot register or manage POS devices', 403 === $sales_register[0] );

$auditor_list = call( 'GET', '/pos-integrations/devices', array(), $auditor_id );
$check( 'Auditor cannot use Branch POS integration registry', 403 === $auditor_list[0] );

$management_registration = call(
	'POST',
	'/pos-integrations/devices',
	array(
		'branch_location_id' => $other_branch_id,
		'vendor' => 'Synthetic POS Vendor B',
		'device_name' => 'Management Registered POS Terminal B',
		'model' => 'QA Model 2',
		'connection_type' => 'DEVICE_SDK',
	),
	$management_id
);
$management_device = is_array( $management_registration[1] ) ? ( $management_registration[1]['device'] ?? array() ) : array();
$management_source_id = (string) ( $management_device['source_system'] ?? '' );
$check( 'Management may register a POS device for another active branch', 200 === $management_registration[0] );
$check( 'different devices never share the same source-system mapping namespace', '' !== $management_source_id && $management_source_id !== $branch_source_id );

$sku_suffix = strtoupper( substr( hash( 'sha256', $branch_device_id . '|' . $management_source_id ), 0, 8 ) );
$product_a_sku = 'STK-POS-A-' . $sku_suffix;
$product_b_sku = 'STK-POS-B-' . $sku_suffix;
$product_a_create = call(
	'POST',
	'/products',
	array(
		'sku' => $product_a_sku,
		'product_name' => 'Synthetic POS Mapping Product A',
		'category' => 'E2E Integration Fixture',
		'unit' => 'PCS',
		'product_type' => 'SIMPLE',
		'purchase_cost' => '10.00',
		'retail_price' => '20.00',
		'wholesale_price' => '15.00',
	),
	$management_id
);
$product_b_create = call(
	'POST',
	'/products',
	array(
		'sku' => $product_b_sku,
		'product_name' => 'Synthetic POS Mapping Product B',
		'category' => 'E2E Integration Fixture',
		'unit' => 'PCS',
		'product_type' => 'SIMPLE',
		'purchase_cost' => '12.00',
		'retail_price' => '24.00',
		'wholesale_price' => '18.00',
	),
	$management_id
);
$check( 'Management creates synthetic Product Master SKU fixture A', 200 === $product_a_create[0], 'HTTP ' . $product_a_create[0] );
$check( 'Management creates synthetic Product Master SKU fixture B', 200 === $product_b_create[0], 'HTTP ' . $product_b_create[0] );
$products_for_maps = Stockland_DB::read_results(
	$wpdb->prepare(
		'SELECT sku,variant_id FROM %i WHERE sku IN(%s,%s) AND is_active=1 ORDER BY sku ASC',
		Stockland_DB::table( 'products' ),
		$product_a_sku,
		$product_b_sku
	),
	ARRAY_A
);
$products_for_maps = is_array( $products_for_maps ) ? $products_for_maps : array();
$products_by_sku = array();
foreach ( $products_for_maps as $product_for_map ) {
	$products_by_sku[ (string) $product_for_map['sku'] ] = $product_for_map;
}
$products_for_maps = array(
	$products_by_sku[ $product_a_sku ] ?? array(),
	$products_by_sku[ $product_b_sku ] ?? array(),
);
$fixtures_ready = ! empty( $products_for_maps[0]['variant_id'] ) && ! empty( $products_for_maps[1]['variant_id'] );
$check( 'two synthetic active Product Master variants exist for source-mapping isolation', $fixtures_ready );
if ( $fixtures_ready ) {
	$shared_external_code = 'POS-HUB-OVERLAP-' . strtoupper( substr( hash( 'sha256', $branch_device_id . '|' . ( $management_device['device_id'] ?? '' ) ), 0, 8 ) );
	$map_device_a = call(
		'POST',
		'/import/code-maps',
		array(
			'source_system' => $branch_source_id,
			'external_code' => $shared_external_code,
			'live_sku' => $product_a_sku,
			'source_name' => 'Synthetic POS Device A',
			'mapping_note' => 's17 disposable integration source-isolation test',
		),
		$management_id
	);
	$map_device_b = call(
		'POST',
		'/import/code-maps',
		array(
			'source_system' => $management_source_id,
			'external_code' => $shared_external_code,
			'live_sku' => $product_b_sku,
			'source_name' => 'Synthetic POS Device B',
			'mapping_note' => 's17 disposable integration source-isolation test',
		),
		$management_id
	);
	$check( 'Management may map the same external PLU independently for POS device A', 200 === $map_device_a[0], 'HTTP ' . $map_device_a[0] );
	$check( 'Management may map the same external PLU independently for POS device B', 200 === $map_device_b[0], 'HTTP ' . $map_device_b[0] );
	$reflection = new ReflectionMethod( Stockland_REST_Import_V16::class, 'daily_resolution_bulk' );
	$reflection->setAccessible( true );
	$import_controller = ( new ReflectionClass( Stockland_REST_Import_V16::class ) )->newInstanceWithoutConstructor();
	$resolved_device_a = $reflection->invoke( $import_controller, array( $shared_external_code ), '2026-11-16', $branch_source_id );
	$resolved_device_b = $reflection->invoke( $import_controller, array( $shared_external_code ), '2026-11-16', $management_source_id );
	$check( 'same external PLU resolves to device A Product Master SKU only in device A namespace', $product_a_sku === (string) ( $resolved_device_a['map'][ $shared_external_code ][0] ?? '' ) );
	$check( 'same external PLU resolves to device B Product Master SKU only in device B namespace', $product_b_sku === (string) ( $resolved_device_b['map'][ $shared_external_code ][0] ?? '' ) );
} else {
	$check( 'per-device product-code mapping isolation has two active SKU fixtures', false, 'The disposable Product Master fixture must provide two active SKUs.' );
	$check( 'POS device A mapping route remains independently testable', false, 'Missing Product Master fixtures.' );
	$check( 'POS device B mapping route remains independently testable', false, 'Missing Product Master fixtures.' );
	$check( 'POS device A source namespace resolution remains independently testable', false, 'Missing Product Master fixtures.' );
	$check( 'POS device B source namespace resolution remains independently testable', false, 'Missing Product Master fixtures.' );
}
$resolved = Stockland_REST_POS_Integrations::resolve_device_for_import( $branch_device_id, $branch_id );
$wrong_branch_resolution = Stockland_REST_POS_Integrations::resolve_device_for_import( $branch_device_id, $other_branch_id );
$check( 'registered device resolves to its own branch/source namespace', is_array( $resolved ) && $branch_source_id === ( $resolved['source_system'] ?? '' ) );
$check( 'registered device cannot be used by another branch import', is_wp_error( $wrong_branch_resolution ) && 'pos_device_branch_mismatch' === $wrong_branch_resolution->get_error_code() );

$stored_json = '' !== $branch_device_id ? Stockland_DB::get_setting( 'pos_int_device_' . strtolower( $branch_device_id ) ) : null;
$stored_record = is_string( $stored_json ) ? json_decode( $stored_json, true ) : null;
$audit_pointer = 'CORE_SETTINGS:pos_int_device_' . strtolower( $branch_device_id ) . ':sha256:' . hash( 'sha256', (string) $stored_json );
$audit_row = '' !== $branch_device_id ? Stockland_DB::read_row(
	$wpdb->prepare( 'SELECT new_value,branch_location_id FROM %i WHERE action=%s AND reference=%s ORDER BY id DESC LIMIT 1', $audit_table, 'POS_DEVICE_REGISTER', $branch_device_id ),
	ARRAY_A
) : null;
$check( 'device record persists in existing Core settings storage', is_array( $stored_record ) && $branch_device_id === ( $stored_record['device_id'] ?? '' ) );
$check( 'device registration has a matching immutable audit pointer', is_array( $audit_row ) && $audit_pointer === (string) $audit_row['new_value'] && (int) $audit_row['branch_location_id'] === $branch_id );
$check( 'no raw API key or password is stored in device metadata', is_array( $stored_record ) && ! isset( $stored_record['api_key'], $stored_record['password'], $stored_record['secret'], $stored_record['access_token'] ) );

if ( ! empty( $shared_external_code ) ) {
	Stockland_DB::delete_record( Stockland_DB::table( 'product_code_maps' ), array( 'external_code' => $shared_external_code ) );
}
Stockland_DB::delete_record( Stockland_DB::table( 'products' ), array( 'sku' => $product_a_sku ) );
Stockland_DB::delete_record( Stockland_DB::table( 'products' ), array( 'sku' => $product_b_sku ) );

$application_tables_after = Stockland_DB::read_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'stock_' ) . '%' ) );
$check( 'integration registry adds no database tables or schema changes', count( (array) $application_tables_before ) === count( (array) $application_tables_after ) );

$ledger_after = array();
foreach ( $ledger_before as $key => $before_count ) {
	$table = Stockland_DB::table( $key );
	$ledger_after[ $key ] = (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
}
$check( 'device registration creates no sales, inventory-ledger or GL records', $ledger_before === $ledger_after );

echo "RESULT " . ( $fail ? 'FAIL' : 'PASS' ) . " (" . ( $checks - $fail ) . "/" . $checks . " checks)\n";
exit( $fail ? 1 : 0 );
