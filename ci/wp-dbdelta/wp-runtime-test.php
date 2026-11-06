<?php
/**
 * Disposable WordPress + MySQL/MariaDB runtime tests for the Stockland ERP.
 *
 * This file is invoked with WP-CLI eval-file. It uses a real WordPress bootstrap,
 * WP REST server, $wpdb, dbDelta(), and the protected /setup/advance route.
 * All records created here are synthetic and must only be used in a disposable DB.
 */

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'wp_get_current_user' ) ) {
	fwrite( STDERR, "FAIL: WordPress is not bootstrapped.\n" );
	exit( 2 );
}

$mode = getenv( 'STOCKLAND_WP_DBDELTA_MODE' );
if ( ! is_string( $mode ) || '' === $mode ) {
	fwrite( STDERR, "FAIL: STOCKLAND_WP_DBDELTA_MODE is required.\n" );
	exit( 2 );
}

function sl_lab_check( $condition, $label, $detail = '' ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $label . ( '' !== $detail ? ' — ' . $detail : '' ) . "\n" );
		exit( 1 );
	}
	echo 'PASS: ' . $label . ( '' !== $detail ? ' — ' . $detail : '' ) . "\n";
}

function sl_lab_admin_id() {
	$users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	return empty( $users ) ? 0 : (int) $users[0];
}

function sl_lab_physical_stock_tables() {
	global $wpdb;
	$prefix = $wpdb->prefix . 'stock_';
	$found  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared LIKE pattern.
	return array_values(
		array_filter(
			(array) $found,
			static function ( $table ) use ( $prefix ) {
				return 0 === strpos( $table, $prefix );
			}
		)
	);
}

function sl_lab_schema_fingerprint() {
	global $wpdb;
	$tables = sl_lab_physical_stock_tables();
	sort( $tables );
	$parts = array();
	foreach ( $tables as $table ) {
		$row = $wpdb->get_row( 'SHOW CREATE TABLE `' . esc_sql( $table ) . '`', ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names originate from SHOW TABLES under the fixed plugin prefix.
		if ( ! is_array( $row ) || ! isset( $row[1] ) ) {
			throw new RuntimeException( 'Unable to read CREATE TABLE for ' . $table );
		}
		$create_sql = preg_replace( '/ AUTO_INCREMENT=\d+/', '', $row[1] );
		$parts[] = $table . "\n" . $create_sql;
	}
	return hash( 'sha256', implode( "\n--TABLE--\n", $parts ) );
}

function sl_lab_contract_counts() {
	$core = Stockland_Activator::core_table_keys();
	$acct = Stockland_Accounting::table_keys();
	$hr   = Stockland_HR::table_keys();
	return array(
		'core'     => count( $core ),
		'finance'  => count( $acct ),
		'hr'       => count( $hr ),
		'expected' => count( $core ) + count( $acct ) + count( $hr ),
		'actual'   => count( sl_lab_physical_stock_tables() ),
		'missing'  => array_merge(
			Stockland_Activator::missing_core_tables(),
			Stockland_Accounting::missing_tables(),
			Stockland_HR::missing_tables()
		),
		'finance_gaps' => Stockland_Accounting::upgrade_gaps(),
		'hr_gaps'      => Stockland_HR::upgrade_gaps(),
	);
}

function sl_lab_advance_via_rest() {
	$admin_id = sl_lab_admin_id();
	sl_lab_check( $admin_id > 0, 'WordPress administrator exists' );

	// Force the genuine WordPress REST server to register plugin routes.
	$server = rest_get_server();
	sl_lab_check( isset( $server->get_routes()['/stockland/v16/setup/advance'] ), 'protected ERP setup REST route is registered' );

	// Verify the authorization boundary before advancing any setup operation.
	wp_set_current_user( 0 );
	$anonymous = rest_do_request( new WP_REST_Request( 'POST', '/stockland/v16/setup/advance' ) );
	$anonymous_status = is_wp_error( $anonymous ) ? 401 : ( $anonymous instanceof WP_REST_Response ? (int) $anonymous->get_status() : 0 );
	sl_lab_check( $anonymous_status >= 400, 'anonymous caller is denied setup advancement', 'HTTP ' . $anonymous_status );

	wp_set_current_user( $admin_id );
	sl_lab_check( current_user_can( 'stockland_install_schema' ), 'administrator has the plugin schema-install capability' );

	global $wpdb;
	echo 'RUNTIME: PHP=' . PHP_VERSION . ' WordPress=' . get_bloginfo( 'version' ) . ' DB=' . $wpdb->db_version() . "\n";
	$ready = false;
	for ( $attempt = 1; $attempt <= 160; $attempt++ ) {
		$request = new WP_REST_Request( 'POST', '/stockland/v16/setup/advance' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );
		if ( is_wp_error( $response ) ) {
			$data   = $response->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
			throw new RuntimeException( 'Protected setup REST request failed (' . $response->get_error_code() . ', HTTP ' . $status . '): ' . $response->get_error_message() );
		}
		if ( ! $response instanceof WP_REST_Response ) {
			throw new RuntimeException( 'Protected setup REST request returned an unexpected response type.' );
		}
		$body  = $response->get_data();
		$state = isset( $body['state'] ) ? (string) $body['state'] : 'UNKNOWN';
		$have  = isset( $body['total']['present'] ) ? (int) $body['total']['present'] : -1;
		$total = isset( $body['total']['total'] ) ? (int) $body['total']['total'] : -1;
		echo sprintf( "SETUP BATCH %03d: state=%s physical_tables=%d/%d\n", $attempt, $state, $have, $total );
		if ( ! empty( $body['ready'] ) ) {
			$ready = true;
			break;
		}
		if ( ! empty( $body['error']['message'] ) ) {
			throw new RuntimeException( 'Setup returned an error: ' . (string) $body['error']['message'] );
		}
	}
	sl_lab_check( $ready, 'protected REST setup completed within the bounded batch limit' );
}

function sl_lab_assert_exact_contract( $expected ) {
	$counts = sl_lab_contract_counts();
	sl_lab_check( $counts['core'] === 29, 'Core table contract count', (string) $counts['core'] );
	sl_lab_check( $counts['finance'] === 16, 'Accounting/Procurement table contract count', (string) $counts['finance'] );
	sl_lab_check( $counts['hr'] === $expected - 45, 'HR table contract count', (string) $counts['hr'] );
	sl_lab_check( $counts['expected'] === $expected, 'runtime contract total', (string) $counts['expected'] );
	sl_lab_check( $counts['actual'] === $expected, 'physical Stockland table count matches contract', (string) $counts['actual'] );
	sl_lab_check( empty( $counts['missing'] ), 'no required Stockland tables are missing', implode( ', ', $counts['missing'] ) );
	sl_lab_check( empty( $counts['finance_gaps'] ), 'no Accounting/Procurement schema upgrade gaps', implode( ', ', $counts['finance_gaps'] ) );
	sl_lab_check( empty( $counts['hr_gaps'] ), 'no HR schema upgrade gaps', implode( ', ', $counts['hr_gaps'] ) );
}

function sl_lab_test_dbdelta_engine_and_plugin_idempotency() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// First prove this exact WordPress + DB engine can apply and repeat dbDelta().
	$probe = $wpdb->prefix . 'stockland_dbdelta_probe';
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $probe ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- test-only allow-listed probe table.
	$charset = $wpdb->get_charset_collate();
	$ddl1    = "CREATE TABLE {$probe} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(40) NOT NULL,
		PRIMARY KEY  (id),
		KEY code (code)
	) ENGINE=InnoDB {$charset};";
	$created = dbDelta( $ddl1 );
	sl_lab_check( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $probe ) ) === $probe, 'WordPress dbDelta creates a disposable probe table' );
	$wpdb->insert( $probe, array( 'code' => 'retained-row' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- isolated disposable test table.
	$ddl2 = "CREATE TABLE {$probe} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(40) NOT NULL,
		state varchar(24) NOT NULL DEFAULT 'NEW',
		PRIMARY KEY  (id),
		KEY code (code)
	) ENGINE=InnoDB {$charset};";
	dbDelta( $ddl2 );
	dbDelta( $ddl2 );
	$columns = $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $probe ) . '`', 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed test table name.
	$row     = $wpdb->get_row( 'SELECT * FROM `' . esc_sql( $probe ) . '` LIMIT 1', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed test table name.
	sl_lab_check( in_array( 'state', $columns, true ), 'dbDelta adds an allow-listed column on an existing table' );
	sl_lab_check( is_array( $row ) && 'retained-row' === $row['code'] && 'NEW' === $row['state'], 'dbDelta preserves an existing test row while adding the column' );
	$wpdb->query( 'DROP TABLE `' . esc_sql( $probe ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- cleanup of the isolated probe table only.

	// Re-enter through the supported protected REST/setup path. Bounded Core and
	// Accounting repairs use CREATE TABLE IF NOT EXISTS; HR uses its bounded
	// add-only path. The separate dbDelta() probe above covers WordPress's
	// create/add-column/repeat/data-preservation behavior.
	$before = sl_lab_schema_fingerprint();
	sl_lab_advance_via_rest();
	$after = sl_lab_schema_fingerprint();
	sl_lab_check( hash_equals( $before, $after ), 're-entering the supported bounded setup route leaves the complete 49-table baseline schema unchanged' );
	sl_lab_assert_exact_contract( 49 );
}

function sl_lab_seed_synthetic_legacy_hr_rows() {
	global $wpdb;
	$baseline_table_names = sl_lab_physical_stock_tables();
	sort( $baseline_table_names );
	sl_lab_check( 49 === count( $baseline_table_names ), 'baseline physical table inventory captured before candidate update', (string) count( $baseline_table_names ) );
	update_option( 'stockland_wp_dbdelta_baseline_table_names', $baseline_table_names, false );
	$comp_table  = Stockland_DB::table( 'employee_compensation' );
	$period_table = Stockland_DB::table( 'payroll_periods' );
	$line_table  = Stockland_DB::table( 'payroll_lines' );

	$wpdb->insert(
		$comp_table,
		array(
			'employee_id'             => 990001,
			'effective_from'          => '2026-11-01',
			'base_salary'             => '12500.00',
			'allowance_total'         => '0.00',
			'standard_deduction_total'=> '0.00',
			'currency'                => 'ETB',
			'notes'                   => 'SYNTHETIC DBDELTA TEST FIXTURE — NOT A REAL EMPLOYEE',
			'created_by'              => 1,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
	);
	sl_lab_check( (int) $wpdb->insert_id > 0, 'synthetic legacy compensation row inserted' );
	$comp_id = (int) $wpdb->insert_id;

	$wpdb->insert(
		$period_table,
		array(
			'period_key' => 'SYNTHETIC-DBDELTA-2026-11',
			'start_date' => '2026-11-01',
			'end_date'   => '2026-11-30',
			'created_by' => 1,
		),
		array( '%s', '%s', '%s', '%d' )
	);
	sl_lab_check( (int) $wpdb->insert_id > 0, 'synthetic legacy payroll period inserted' );
	$period_id = (int) $wpdb->insert_id;

	$wpdb->insert(
		$line_table,
		array(
			'period_id'     => $period_id,
			'employee_id'   => 990001,
			'employee_code' => 'SYNTH-990001',
			'employee_name' => 'Synthetic DBDelta Fixture',
			'base_salary'   => '12500.00',
			'gross_pay'     => '12500.00',
			'net_pay'       => '10000.00',
			'notes'         => 'SYNTHETIC DBDELTA TEST FIXTURE — NOT A REAL PAYROLL',
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	sl_lab_check( (int) $wpdb->insert_id > 0, 'synthetic legacy payroll line inserted' );
	$line_id = (int) $wpdb->insert_id;

	update_option(
		'stockland_wp_dbdelta_fixture',
		array(
			'compensation_id' => $comp_id,
			'period_id'       => $period_id,
			'line_id'         => $line_id,
			'employee_id'     => 990001,
		),
		false
	);
	echo 'FIXTURE IDS: ' . wp_json_encode( get_option( 'stockland_wp_dbdelta_fixture' ) ) . "\n";
}

function sl_lab_assert_candidate_upgrade() {
	global $wpdb;
	sl_lab_check( defined( 'STOCKLAND_VERSION' ) && '16.2.9' === STOCKLAND_VERSION, 'the selected 16.2.9 candidate is the active runtime' );
	sl_lab_assert_exact_contract( 86 );

	$baseline_table_names = get_option( 'stockland_wp_dbdelta_baseline_table_names' );
	$current_table_names  = sl_lab_physical_stock_tables();
	sort( $current_table_names );
	sl_lab_check( is_array( $baseline_table_names ) && 49 === count( $baseline_table_names ), 'baseline physical table inventory is available for preservation check' );
	$missing_baseline_tables = array_values( array_diff( $baseline_table_names, $current_table_names ) );
	sl_lab_check( empty( $missing_baseline_tables ), 'all original 49 physical Stockland tables remain after HR candidate update', implode( ', ', $missing_baseline_tables ) );

	$fixture = get_option( 'stockland_wp_dbdelta_fixture' );
	sl_lab_check( is_array( $fixture ) && ! empty( $fixture['compensation_id'] ) && ! empty( $fixture['line_id'] ), 'baseline synthetic HR/payroll fixture identifiers survived update' );

	$comp = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Stockland_DB::table( 'employee_compensation' ), (int) $fixture['compensation_id'] ), ARRAY_A );
	$line = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Stockland_DB::table( 'payroll_lines' ), (int) $fixture['line_id'] ), ARRAY_A );
	sl_lab_check( is_array( $comp ) && '12500.00' === (string) $comp['base_salary'], 'legacy compensation amount is preserved through update' );
	sl_lab_check( is_array( $line ) && '10000.00' === (string) $line['net_pay'], 'legacy payroll-line amount is preserved through update' );

	// Important characterization: the migration's approval_status default differs
	// from the fresh-install DDL. This is a release blocker, not a passing approval.
	$approval = is_array( $comp ) ? (string) $comp['approval_status'] : '';
	$approved_by = is_array( $comp ) ? $comp['approved_by'] : null;
	$approved_at = is_array( $comp ) ? $comp['approved_at'] : null;
	echo 'MIGRATION FINDING: existing compensation row approval_status=' . $approval . ', approved_by=' . var_export( $approved_by, true ) . ', approved_at=' . var_export( $approved_at, true ) . "\n";
	sl_lab_check( 'APPROVED' === $approval && null === $approved_by && null === $approved_at, 'candidate migration behavior is reproduced: existing compensation receives APPROVED without an approver/event' );

	// Pension contribution-rate columns already exist in the locked 16.1.95
	// baseline; this exact comparison prevents attributing them to this candidate.
	$line_columns = $wpdb->get_results( 'SHOW FULL COLUMNS FROM `' . esc_sql( Stockland_DB::table( 'payroll_lines' ) ) . '`', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted fixed table name.
	$line_column_map = array();
	foreach ( (array) $line_columns as $column ) {
		$line_column_map[ $column['Field'] ] = $column;
	}
	sl_lab_check( isset( $line_column_map['employee_pension_rate'], $line_column_map['employer_pension_rate'] ), 'historical payroll pension-rate columns are present (also present in the protected 16.1.95 baseline)' );
	sl_lab_check( array_key_exists( 'statutory_rule_set_id', $line_column_map ), 'candidate adds statutory rule provenance to the legacy payroll-line schema' );
	sl_lab_check( '12500.00' === (string) $comp['base_salary'] && '10000.00' === (string) $line['net_pay'], 'synthetic legacy business values remain unchanged after all HR table additions' );
}

function sl_lab_assert_candidate_fresh_install() {
	global $wpdb;
	sl_lab_check( defined( 'STOCKLAND_VERSION' ) && '16.2.9' === STOCKLAND_VERSION, 'the fresh-install runtime is the selected 16.2.9 candidate' );
	sl_lab_assert_exact_contract( 86 );
	$wpdb->insert(
		Stockland_DB::table( 'employee_compensation' ),
		array(
			'employee_id'              => 990002,
			'effective_from'           => '2026-11-01',
			'base_salary'              => '12500.00',
			'allowance_total'          => '0.00',
			'standard_deduction_total' => '0.00',
			'currency'                 => 'ETB',
			'notes'                    => 'SYNTHETIC FRESH-INSTALL DBDELTA FIXTURE',
			'created_by'               => 1,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
	);
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Stockland_DB::table( 'employee_compensation' ), (int) $wpdb->insert_id ), ARRAY_A );
	sl_lab_check( is_array( $row ) && 'DRAFT' === (string) $row['approval_status'], 'fresh-install compensation default is DRAFT' );
	echo "FRESH INSTALL FINDING: approval_status=DRAFT; upgrade migration path uses APPROVED by default.\n";
}

try {
	switch ( $mode ) {
		case 'advance':
			sl_lab_advance_via_rest();
			break;
		case 'assert-baseline':
			sl_lab_check( defined( 'STOCKLAND_VERSION' ) && '16.1.95' === STOCKLAND_VERSION, 'protected 16.1.95 baseline is the active runtime' );
			sl_lab_assert_exact_contract( 49 );
			break;
		case 'dbdelta-idempotency':
			sl_lab_test_dbdelta_engine_and_plugin_idempotency();
			break;
		case 'seed-legacy-fixtures':
			sl_lab_seed_synthetic_legacy_hr_rows();
			break;
		case 'assert-candidate-upgrade':
			sl_lab_assert_candidate_upgrade();
			break;
		case 'assert-candidate-fresh':
			sl_lab_assert_candidate_fresh_install();
			break;
		default:
			throw new RuntimeException( 'Unknown mode: ' . $mode );
	}
	echo 'WP_RUNTIME_TEST_MODE ' . $mode . ': PASS (the mode may intentionally reproduce a documented migration blocker). PHP=' . PHP_VERSION . ' WordPress=' . get_bloginfo( 'version' ) . "\n";
} catch ( Throwable $e ) {
	fwrite( STDERR, 'WP_RUNTIME_TEST_MODE ' . $mode . ': FAIL — ' . $e->getMessage() . "\n" );
	exit( 1 );
}
