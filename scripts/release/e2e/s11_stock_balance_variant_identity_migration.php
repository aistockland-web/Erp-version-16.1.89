<?php
/**
 * Migration regression for immutable Product Master identity in stock_balances.
 * Runs only in the disposable WordPress/database workflow.
 */
require __DIR__ . '/lib.php';
global $wpdb;

if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) {
	echo "Refusing stock-balance migration test outside a disposable site.\n";
	exit( 2 );
}

$failed = 0;
$check = static function ( $label, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : ' :: ' . $detail ) . "\n";
	if ( ! $ok ) {
		++$failed;
	}
};

$balance_table = Stockland_DB::table( 'balances' );
$product_table = Stockland_DB::table( 'products' );
$suffix = strtoupper( substr( hash( 'sha256', wp_generate_uuid4() ), 0, 8 ) );
$saved_table = $balance_table . '_s11_saved_' . strtolower( $suffix );
$good_table = $balance_table . '_s11_good_' . strtolower( $suffix );
$stage_table = $balance_table . '_v161_stage';
$old_rollback_option = get_option( 'stockland_balance_rollback_snapshot', false );
$old_user_id = get_current_user_id();
$product_ids = array();
$owned_tables = array();

$run_migration = static function () use ( $wpdb ) {
	$method = new ReflectionMethod( 'Stockland_Activator', 'apply_schema_1600_migrations' );
	$method->setAccessible( true );
	$method->invoke( null, $wpdb->prefix );
};

$drop_table = static function ( $table ) use ( $wpdb ) {
	Stockland_DB::execute_query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
};

try {
	$check( 'current stock-balance table exists', Stockland_DB::table_exists( $balance_table ) );
	if ( ! Stockland_DB::table_exists( $balance_table ) ) {
		throw new RuntimeException( 'The installed master application has no stock_balances table.' );
	}
	$check( 'test snapshot name does not already exist', ! Stockland_DB::table_exists( $saved_table ) );
	if ( Stockland_DB::table_exists( $saved_table ) ) {
		throw new RuntimeException( 'The isolated save-table name already exists.' );
	}

	// Save the exact pre-test read model before replacing it with legacy fixtures.
	$wpdb->last_error = '';
	Stockland_DB::execute_query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $saved_table, $balance_table ) );
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not snapshot original stock_balances: ' . $wpdb->last_error );
	}
	Stockland_DB::execute_query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', $saved_table, $balance_table ) );
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not copy original stock_balances rows: ' . $wpdb->last_error );
	}

	$sku_a = 'STK-E2E-A-' . $suffix;
	$sku_b = 'STK-E2E-B-' . $suffix;
	foreach ( array(
		array( 'sku' => $sku_a, 'product_name' => 'S11 identity fixture A', 'product_type' => 'SIMPLE' ),
		array( 'sku' => $sku_b, 'product_name' => 'S11 identity fixture B', 'product_type' => 'SIMPLE' ),
	) as $product ) {
		$wpdb->last_error = '';
		$inserted = $wpdb->insert( $product_table, $product, array( '%s', '%s', '%s' ) );
		if ( false === $inserted || $wpdb->last_error ) {
			throw new RuntimeException( 'Could not insert synthetic Product Master fixture: ' . $wpdb->last_error );
		}
		$product_ids[ $product['sku'] ] = (int) $wpdb->insert_id;
	}
	$variant_a = $product_ids[ $sku_a ];
	$variant_b = $product_ids[ $sku_b ];
	$check( 'two distinct synthetic immutable variants were created', $variant_a > 0 && $variant_b > 0 && $variant_a !== $variant_b );

	$drop_table( $balance_table );
	$legacy_ddl = $wpdb->prepare(
		'CREATE TABLE %i (
			location_id BIGINT UNSIGNED NOT NULL,
			sku VARCHAR(64) NOT NULL,
			variant_id BIGINT UNSIGNED NULL,
			physical_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (location_id,sku),
			KEY variant_id (variant_id)
		) ENGINE=InnoDB',
		$balance_table
	);
	$legacy_ddl .= ' ' . $wpdb->get_charset_collate();
	$wpdb->last_error = '';
	Stockland_DB::execute_query( $legacy_ddl );
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not create SKU-keyed legacy balance fixture: ' . $wpdb->last_error );
	}

	// Row one has a valid immutable variant A but a recycled/stale SKU now owned by B.
	// Row two is genuinely SKU-only legacy data and must resolve to B.
	$wpdb->last_error = '';
	$wpdb->insert(
		$balance_table,
		array( 'location_id' => 900001, 'sku' => $sku_b, 'variant_id' => $variant_a, 'physical_qty' => '10.00' ),
		array( '%d', '%s', '%d', '%f' )
	);
	$wpdb->insert(
		$balance_table,
		array( 'location_id' => 900002, 'sku' => $sku_b, 'variant_id' => null, 'physical_qty' => '5.00' ),
		array( '%d', '%s', '%d', '%f' )
	);
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not seed SKU-keyed legacy balance fixture: ' . $wpdb->last_error );
	}

	$run_migration();
	$current_rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT location_id,sku,variant_id,physical_qty FROM %i ORDER BY location_id',
		$balance_table
	), ARRAY_A );
	$check( 'legacy balance rows cut over to two immutable location/variant rows', is_array( $current_rows ) && 2 === count( $current_rows ) );
	$check(
		'valid variant ID wins over a recycled SKU',
		(int) ( $current_rows[0]['location_id'] ?? 0 ) === 900001
			&& (int) ( $current_rows[0]['variant_id'] ?? 0 ) === $variant_a
			&& (string) ( $current_rows[0]['sku'] ?? '' ) === $sku_a
			&& abs( (float) ( $current_rows[0]['physical_qty'] ?? 0 ) - 10.0 ) < 0.00001
	);
	$check(
		'NULL variant ID resolves only through its exact current SKU',
		(int) ( $current_rows[1]['location_id'] ?? 0 ) === 900002
			&& (int) ( $current_rows[1]['variant_id'] ?? 0 ) === $variant_b
			&& (string) ( $current_rows[1]['sku'] ?? '' ) === $sku_b
			&& abs( (float) ( $current_rows[1]['physical_qty'] ?? 0 ) - 5.0 ) < 0.00001
	);

	$primary = $wpdb->get_col( $wpdb->prepare(
		"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",
		DB_NAME,
		$balance_table
	) );
	$variant_nullable = $wpdb->get_var( $wpdb->prepare(
		'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME=%s',
		DB_NAME,
		$balance_table,
		'variant_id'
	) );
	$check( 'new primary key is location_id + variant_id', array( 'location_id', 'variant_id' ) === $primary );
	$check( 'new variant_id primary-key column is NOT NULL', 'NO' === $variant_nullable );

	$rollback_table = (string) get_option( 'stockland_balance_rollback_snapshot', '' );
	$backup_rows = array();
	if ( '' !== $rollback_table && Stockland_DB::table_exists( $rollback_table ) ) {
		$backup_rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT location_id,sku,variant_id,physical_qty FROM %i ORDER BY location_id',
			$rollback_table
		), ARRAY_A );
	}
	$check(
		'rollback snapshot preserves original NULL variant row and source quantities',
		is_array( $backup_rows )
			&& 2 === count( $backup_rows )
			&& null === $backup_rows[1]['variant_id']
			&& abs( (float) $backup_rows[0]['physical_qty'] - 10.0 ) < 0.00001
			&& abs( (float) $backup_rows[1]['physical_qty'] - 5.0 ) < 0.00001
	);

	$location_totals = $wpdb->get_results( $wpdb->prepare(
		'SELECT location_id,SUM(physical_qty) AS qty FROM %i GROUP BY location_id ORDER BY location_id',
		$balance_table
	), ARRAY_A );
	$check(
		'physical quantities reconcile independently per location',
		is_array( $location_totals )
			&& 2 === count( $location_totals )
			&& 900001 === (int) $location_totals[0]['location_id']
			&& abs( (float) $location_totals[0]['qty'] - 10.0 ) < 0.00001
			&& 900002 === (int) $location_totals[1]['location_id']
			&& abs( (float) $location_totals[1]['qty'] - 5.0 ) < 0.00001
	);

	// Replaying the migration must not duplicate, remove, or remap the derived balances.
	$backup_count_before = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME LIKE %s',
		DB_NAME,
		$wpdb->esc_like( $balance_table . '_v161_backup_' ) . '%'
	) );
	$run_migration();
	$after_replay = $wpdb->get_results( $wpdb->prepare(
		'SELECT location_id,sku,variant_id,physical_qty FROM %i ORDER BY location_id',
		$balance_table
	), ARRAY_A );
	$backup_count_after = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME LIKE %s',
		DB_NAME,
		$wpdb->esc_like( $balance_table . '_v161_backup_' ) . '%'
	) );
	$check( 'migration replay is idempotent with no extra rollback snapshot', $after_replay === $current_rows && $backup_count_before === $backup_count_after );

	// Negative case: unknown SKU with no ID and orphan non-NULL ID with a known/reused SKU.
	// Neither row may be remapped or cut over by SKU fallback.
	$drop_table( $good_table );
	Stockland_DB::execute_query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $balance_table, $good_table ) );
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not isolate positive-case balances before the negative-case test.' );
	}
	Stockland_DB::execute_query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $balance_table, $good_table ) );
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not create the negative-case legacy table.' );
	}
	// Recreate the legacy SKU primary key; CREATE LIKE copies the current variant key.
	Stockland_DB::execute_query( $wpdb->prepare( 'ALTER TABLE %i DROP PRIMARY KEY, ADD PRIMARY KEY (location_id,sku), MODIFY variant_id BIGINT UNSIGNED NULL', $balance_table ) );
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not prepare the negative-case legacy primary key.' );
	}
	$wpdb->insert( $balance_table, array( 'location_id' => 900003, 'sku' => 'STK-E2E-UNKNOWN-' . $suffix, 'variant_id' => null, 'physical_qty' => '7.00' ), array( '%d', '%s', '%d', '%f' ) );
	$wpdb->insert( $balance_table, array( 'location_id' => 900004, 'sku' => $sku_b, 'variant_id' => 999999, 'physical_qty' => '8.00' ), array( '%d', '%s', '%d', '%f' ) );
	if ( $wpdb->last_error ) {
		throw new RuntimeException( 'Could not seed the negative-case legacy rows: ' . $wpdb->last_error );
	}
	$negative_ddl_before = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW CREATE TABLE %i', $balance_table ) );
	$negative_rows_before = $wpdb->get_results( $wpdb->prepare(
		'SELECT location_id,sku,variant_id,physical_qty FROM %i ORDER BY location_id',
		$balance_table
	), ARRAY_A );
	$negative_failed_closed = false;
	try {
		$run_migration();
	} catch ( RuntimeException $e ) {
		$negative_failed_closed = false !== strpos( $e->getMessage(), 'unresolved or orphan immutable Product Master variant identity' );
	}
	$negative_ddl_after = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW CREATE TABLE %i', $balance_table ) );
	$negative_rows_after = $wpdb->get_results( $wpdb->prepare(
		'SELECT location_id,sku,variant_id,physical_qty FROM %i ORDER BY location_id',
		$balance_table
	), ARRAY_A );
	$check( 'unresolved/orphan identities fail closed before cutover', $negative_failed_closed );
	$check( 'failed migration leaves the original legacy rows unchanged', $negative_rows_before === $negative_rows_after );
	$check( 'failed migration leaves the original legacy DDL unchanged', $negative_ddl_before === $negative_ddl_after );
	$check( 'failed migration does not create a staging table', ! Stockland_DB::table_exists( $stage_table ) );

} catch ( Throwable $e ) {
	$check( 'migration fixture execution completed without unexpected exception', false, $e->getMessage() );
} finally {
	// Restore the original installed read model and option exactly, even if an assertion failed.
	$drop_table( $balance_table );
	$drop_table( $good_table );
	$drop_table( $stage_table );
	$backup_names = $wpdb->get_col( $wpdb->prepare(
		'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME LIKE %s',
		DB_NAME,
		$wpdb->esc_like( $balance_table . '_v161_backup_' ) . '%'
	) );
	foreach ( (array) $backup_names as $name ) {
		$drop_table( $name );
	}
	if ( Stockland_DB::table_exists( $saved_table ) ) {
		Stockland_DB::execute_query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $saved_table, $balance_table ) );
	}
	foreach ( $product_ids as $variant_id ) {
		$wpdb->delete( $product_table, array( 'variant_id' => (int) $variant_id ), array( '%d' ) );
	}
	if ( false === $old_rollback_option ) {
		delete_option( 'stockland_balance_rollback_snapshot' );
	} else {
		update_option( 'stockland_balance_rollback_snapshot', $old_rollback_option, false );
	}
	wp_set_current_user( $old_user_id );
}

echo $failed ? "RESULT FAIL ($failed)\n" : "RESULT PASS\n";
if ( $failed ) {
	exit( 1 );
}
