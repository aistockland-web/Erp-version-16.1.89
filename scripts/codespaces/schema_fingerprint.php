<?php
/**
 * Capture a data-free physical schema fingerprint from an isolated WordPress QA DB.
 * No row contents, credentials, employee records, or transaction values are emitted.
 */
if ( ! defined( 'ABSPATH' ) || ! isset( $GLOBALS['wpdb'] ) ) {
	fwrite( STDERR, "WordPress database is not loaded.\n" );
	exit( 2 );
}
global $wpdb;
$prefix = (string) $wpdb->prefix . 'stock_';
$tables = $wpdb->get_col( 'SHOW TABLES' );
if ( ! is_array( $tables ) || ! empty( $wpdb->last_error ) ) {
	throw new RuntimeException( 'Could not read physical table inventory: ' . $wpdb->last_error );
}
$managed = array();
foreach ( $tables as $table_name ) {
	$table_name = (string) $table_name;
	if ( 0 !== strpos( $table_name, $prefix ) ) {
		continue;
	}
	$quoted = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $table_name ) . chr( 96 );
	$wpdb->last_error = '';
	$columns = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $quoted, ARRAY_A );
	if ( ! is_array( $columns ) || ! empty( $wpdb->last_error ) ) {
		throw new RuntimeException( 'Could not read columns for ' . $table_name . ': ' . $wpdb->last_error );
	}
	$wpdb->last_error = '';
	$indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . $quoted, ARRAY_A );
	if ( ! is_array( $indexes ) || ! empty( $wpdb->last_error ) ) {
		throw new RuntimeException( 'Could not read indexes for ' . $table_name . ': ' . $wpdb->last_error );
	}
	$wpdb->last_error = '';
	$row_count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $quoted );
	if ( null === $row_count || ! empty( $wpdb->last_error ) ) {
		throw new RuntimeException( 'Could not read row count for ' . $table_name . ': ' . $wpdb->last_error );
	}
	$column_map = array();
	foreach ( $columns as $column ) {
		$column_map[ (string) $column['Field'] ] = array(
			'type' => (string) $column['Type'],
			'null' => (string) $column['Null'],
			'key' => (string) $column['Key'],
			'default' => $column['Default'],
			'extra' => (string) $column['Extra'],
		);
	}
	$index_map = array();
	foreach ( $indexes as $index ) {
		$key = (string) $index['Key_name'];
		if ( ! isset( $index_map[ $key ] ) ) {
			$index_map[ $key ] = array(
				'unique' => 0 === (int) $index['Non_unique'],
				'type' => (string) $index['Index_type'],
				'columns' => array(),
			);
		}
		$index_map[ $key ]['columns'][ (int) $index['Seq_in_index'] ] = (string) $index['Column_name'];
	}
	foreach ( $index_map as &$index_data ) {
		ksort( $index_data['columns'], SORT_NUMERIC );
		$index_data['columns'] = array_values( $index_data['columns'] );
	}
	unset( $index_data );
	ksort( $column_map, SORT_STRING );
	ksort( $index_map, SORT_STRING );
	$managed[ substr( $table_name, strlen( $prefix ) ) ] = array(
		'columns' => $column_map,
		'indexes' => $index_map,
		'row_count' => (int) $row_count,
	);
}
ksort( $managed, SORT_STRING );
$core = class_exists( 'Stockland_Activator' ) ? Stockland_Activator::core_table_keys() : array();
$finance = class_exists( 'Stockland_Accounting' ) ? Stockland_Accounting::table_keys() : array();
$hr = class_exists( 'Stockland_HR' ) ? Stockland_HR::table_keys() : array();
$expected = array_values( array_unique( array_merge( $core, $finance, $hr ) ) );
sort( $expected, SORT_STRING );
$observed = array_keys( $managed );
$missing = array_values( array_diff( $expected, $observed ) );
$unexpected = array_values( array_diff( $observed, $expected ) );
$receipt = array(
	'fingerprint_type' => 'stockland-isolated-qa-schema-fingerprint-v1',
	'captured_at_utc' => gmdate( 'c' ),
	'database_server' => (string) $wpdb->get_var( 'SELECT VERSION()' ),
	'wordpress_version' => (string) get_bloginfo( 'version' ),
	'plugin_version' => defined( 'STOCKLAND_VERSION' ) ? STOCKLAND_VERSION : 'unknown',
	'database_schema_version' => (string) get_option( 'stockland_db_version', 'unknown' ),
	'application_schema_ready_option' => (string) get_option( 'stockland_application_schema_ready', '0' ),
	'setup_state' => (string) get_option( 'stockland_setup_state', 'unknown' ),
	'declared_runtime_key_counts' => array(
		'core' => count( $core ),
		'finance' => count( $finance ),
		'hr_including_legacy' => count( $hr ),
		'union' => count( $expected ),
	),
	'physical_stockland_table_count' => count( $managed ),
	'missing_declared_tables' => $missing,
	'unexpected_stockland_tables' => $unexpected,
	'schema_approval' => 'NOT_ASSESSED_OR_GRANTED_BY_FINGERPRINT',
	'deep_integrity' => 'NOT_RUN_BY_FINGERPRINT',
	'tables' => $managed,
);
echo wp_json_encode( $receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
