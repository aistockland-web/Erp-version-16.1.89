<?php
/**
 * Reports Explorer column contract and authorization regression.
 * Runs only through run_all.sh on a disposable synthetic WordPress database.
 */
require_once __DIR__ . '/lib.php';

$fail = 0;
$check = static function ( $label, $condition, $detail = '' ) use ( &$fail ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . ( $condition ? '' : ' :: ' . $detail ) . "\n";
	if ( ! $condition ) {
		++$fail;
	}
};

$owner = get_user_by( 'login', 'owner2' );
$branch_manager = get_user_by( 'login', 'brmgr' );
$check( 'report owner and branch-manager fixtures exist', $owner && $branch_manager );
if ( ! $owner || ! $branch_manager ) {
	echo "RESULT FAIL (missing role fixtures)\n";
	exit( 1 );
}

$today = current_time( 'Y-m-d' );
$types = array(
	'branch_performance',
	'sales_performance',
	'product_sku',
	'inventory_availability',
	'critical_reorder',
	'transfer_status',
	'transfer_variance',
	'physical_count_variance',
	'returns_adjustments',
);
foreach ( $types as $type ) {
	list( $status, $data ) = call(
		'GET',
		'/app/reports/explorer',
		array(),
		(int) $owner->ID,
		array(
			'type'        => $type,
			'from'        => $today,
			'to'          => $today,
			'location_id' => 'all',
		)
	);
	$check( $type . ' returns a successful selected report', 200 === $status && is_array( $data ) && isset( $data['columns'] ), is_array( $data ) ? ( $data['message'] ?? '' ) : '' );
	if ( 200 !== $status || ! isset( $data['columns'] ) ) {
		continue;
	}
	$keys = array_map(
		static function ( $column ) {
			return (string) ( $column['key'] ?? '' );
		},
		$data['columns']
	);
	$check( $type . ' column keys are non-empty and unique', ! in_array( '', $keys, true ) && count( $keys ) === count( array_unique( $keys ) ), implode( ',', $keys ) );
	if ( in_array( $type, array( 'inventory_availability', 'critical_reorder' ), true ) ) {
		$check( $type . ' exposes inbound transfer exactly once', 1 === count( array_keys( $keys, 'inbound_stock', true ) ), implode( ',', $keys ) );
		$check( $type . ' exposes reorder position exactly once', 1 === count( array_keys( $keys, 'reorder_position', true ) ), implode( ',', $keys ) );
	}
}

list( $status, $data ) = call(
	'GET',
	'/app/reports/explorer',
	array(),
	(int) $branch_manager->ID,
	array( 'type' => 'critical_reorder', 'from' => $today, 'to' => $today, 'location_id' => 'all' )
);
$check( 'critical reorder remains Owner-only (403)', 403 === $status && 'reports_owner_only' === ( $data['code'] ?? '' ) );

list( $status, $data ) = call(
	'GET',
	'/app/reports/explorer',
	array(),
	(int) $owner->ID,
	array( 'type' => 'sales_performance', 'from' => $today, 'to' => $today, 'location_id' => 'all', 'sku' => 'STK-E2E' )
);
$check( 'unsupported SKU/report combination is rejected (400)', 400 === $status && 'sku_filter_not_supported' === ( $data['code'] ?? '' ) );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
exit( $fail ? 1 : 0 );
