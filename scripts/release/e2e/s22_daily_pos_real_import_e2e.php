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

$base_url = rtrim( (string) getenv( 'STOCKLAND_E2E_SITE_URL' ), '/' );
$check( 'disposable WordPress HTTP endpoint is configured for the real multipart E2E', '' !== $base_url && ! empty( $u['se'] ) && ! empty( $u['bm'] ) );
$check( 'PHP cURL and multipart upload support are available', function_exists( 'curl_init' ) && class_exists( 'CURLFile' ) );
if ( $fail ) {
	exit( 1 );
}

$create_app_password = static function ( $user_id, $name ) {
	$user = get_userdata( (int) $user_id );
	if ( ! $user || ! function_exists( 'wp_create_application_password' ) ) {
		throw new RuntimeException( 'WordPress Application Password support is unavailable for the synthetic E2E user.' );
	}
	$created = wp_create_application_password( (int) $user_id, $name );
	if ( is_wp_error( $created ) || ! is_array( $created ) || empty( $created[0] ) || empty( $created[1]['uuid'] ) ) {
		throw new RuntimeException( 'Could not create a temporary synthetic Application Password for the REST E2E.' );
	}
	return array(
		'user_id' => (int) $user_id,
		'login' => (string) $user->user_login,
		'password' => (string) $created[0],
		'uuid' => (string) $created[1]['uuid'],
	);
};

$http = static function ( $path, $method, $credential, $json = null, $csv_path = null ) use ( $base_url ) {
	$url = $base_url . '/wp-json/stockland/v16' . $path;
	$handle = curl_init( $url );
	if ( false === $handle ) {
		throw new RuntimeException( 'Could not initialize the local WordPress HTTP client.' );
	}
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => strtoupper( $method ),
		CURLOPT_USERPWD => $credential['login'] . ':' . $credential['password'],
		CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_HTTPHEADER => array( 'Accept: application/json' ),
	);
	if ( null !== $csv_path ) {
		$options[ CURLOPT_POST ] = true;
		$options[ CURLOPT_POSTFIELDS ] = array(
			'file' => new CURLFile( $csv_path, 'text/csv', 'stockland-pos-real-import-e2e.csv' ),
			'business_date' => '2026-11-20',
		);
	} elseif ( null !== $json ) {
		$options[ CURLOPT_POST ] = true;
		$options[ CURLOPT_HTTPHEADER ][] = 'Content-Type: application/json';
		$options[ CURLOPT_POSTFIELDS ] = wp_json_encode( $json );
	}
	curl_setopt_array( $handle, $options );
	$body = curl_exec( $handle );
	$error = curl_error( $handle );
	$code = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	curl_close( $handle );
	if ( false === $body ) {
		throw new RuntimeException( 'Local WordPress HTTP request failed: ' . $error );
	}
	$data = json_decode( (string) $body, true );
	return array(
		'status' => $code,
		'data' => is_array( $data ) ? $data : array( 'raw' => substr( (string) $body, 0, 1200 ) ),
	);
};

$credentials = array();
$csv_path = '';
try {
	$credentials['sales'] = $create_app_password( (int) $u['se'], 'Stockland disposable Daily POS E2E sales uploader' );
	$credentials['manager'] = $create_app_password( (int) $u['bm'], 'Stockland disposable Daily POS E2E branch release' );

	$csv_path = tempnam( sys_get_temp_dir(), 'stockland-pos-e2e-' );
	if ( ! $csv_path ) {
		throw new RuntimeException( 'Could not create the synthetic POS CSV fixture.' );
	}
	$csv = "SKU,Quantity,Selling Price,Line Total,VAT Mode\r\nSTK-TEE-001,1,450.00,517.50,VAT_INCLUDED\r\n";
	if ( false === file_put_contents( $csv_path, $csv ) ) {
		throw new RuntimeException( 'Could not write the synthetic POS CSV fixture.' );
	}

	$preview = $http( '/import/daily-sales/preview', 'POST', $credentials['sales'], null, $csv_path );
	$job_id = (int) ( $preview['data']['job_id'] ?? 0 );
	$summary = (array) ( $preview['data']['summary'] ?? array() );
	$check( 'Sales Employee uploads a real multipart CSV to the production Daily POS preview REST route', 200 === $preview['status'] && $job_id > 0, 'HTTP ' . $preview['status'] . ' ' . wp_json_encode( $preview['data'] ) );
	$check( 'preview performs real SKU, quantity, amount and VAT validation and stages one READY row', 'READY' === ( $preview['data']['status'] ?? '' ) && 1 === (int) ( $summary['valid_rows'] ?? 0 ) && 0 === (int) ( $summary['error_rows'] ?? -1 ), wp_json_encode( $summary ) );
	if ( $job_id <= 0 || 'READY' !== ( $preview['data']['status'] ?? '' ) ) {
		throw new RuntimeException( 'POS preview did not produce a releasable READY job; stop before attempting a release.' );
	}

	$preview_replay = $http( '/import/daily-sales/preview', 'POST', $credentials['sales'], null, $csv_path );
	$check( 're-uploading the exact same source bytes resolves to the existing job without duplicate staging', 200 === $preview_replay['status'] && ! empty( $preview_replay['data']['existing_job'] ) && $job_id === (int) ( $preview_replay['data']['job_id'] ?? 0 ), 'HTTP ' . $preview_replay['status'] );

	$sales_release = $http( '/import/daily-sales/commit', 'POST', $credentials['sales'], array( 'job_id' => $job_id ) );
	$check( 'Sales Employee cannot release/post the staged Daily POS job', 403 === $sales_release['status'], 'HTTP ' . $sales_release['status'] );

	$before_sales = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_job_id=%d', Stockland_DB::table( 'sales' ), $job_id ) );
	$released = $http( '/import/daily-sales/commit', 'POST', $credentials['manager'], array( 'job_id' => $job_id ) );
	$check( 'assigned Branch Manager explicitly releases the staged job through the real commit REST route', 200 === $released['status'] && 'POSTED' === ( $released['data']['status'] ?? '' ), 'HTTP ' . $released['status'] . ' ' . wp_json_encode( $released['data'] ) );

	$job = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Stockland_DB::table( 'import_jobs' ), $job_id ), ARRAY_A );
	$rows = Stockland_DB::read_results( $wpdb->prepare( 'SELECT status,posted_sale_id FROM %i WHERE job_id=%d ORDER BY source_row_number', Stockland_DB::table( 'import_rows' ), $job_id ), ARRAY_A );
	$sale_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_job_id=%d', Stockland_DB::table( 'sales' ), $job_id ) );
	$check( 'release is attributed to the Branch Manager while the uploader remains the Sales Employee', (int) ( $job['created_by'] ?? 0 ) === (int) $u['se'] && (int) ( $job['approved_by'] ?? 0 ) === (int) $u['bm'] );
	$check( 'posted source row and one final ERP sale are durably linked to the import job', 'POSTED' === ( $job['status'] ?? '' ) && 1 === (int) ( $job['processed_rows'] ?? 0 ) && 1 === $sale_count && 1 === count( $rows ) && 'POSTED' === ( $rows[0]['status'] ?? '' ) && ! empty( $rows[0]['posted_sale_id'] ), 'sales=' . $sale_count );
	$sale_id = (string) ( $rows[0]['posted_sale_id'] ?? '' );
	$ledger_count = $sale_id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE reference_type=%s AND reference_id=%s', Stockland_DB::table( 'transactions' ), 'sale', $sale_id ) ) : 0;
	$check( 'the sale has its corresponding immutable inventory ledger movement', $ledger_count >= 1, 'ledger rows=' . $ledger_count );

	$source_id = $job_id . '-0';
	$journal_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_type=%s AND source_id=%s', Stockland_DB::table( 'gl_journals' ), 'DAILY_SALES_CHUNK', $source_id ) );
	$journal_totals = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(l.debit),0) AS debit_total,COALESCE(SUM(l.credit),0) AS credit_total FROM %i l JOIN %i j ON j.id=l.journal_id WHERE j.source_type=%s AND j.source_id=%s',
			Stockland_DB::table( 'gl_lines' ),
			Stockland_DB::table( 'gl_journals' ),
			'DAILY_SALES_CHUNK',
			$source_id
		),
		ARRAY_A
	);
	$balanced = $journal_totals && abs( (float) $journal_totals['debit_total'] - (float) $journal_totals['credit_total'] ) < 0.00001;
	$check( 'the committed Daily POS job produces one balanced GL journal chunk', $journal_count >= 1 && $balanced, wp_json_encode( $journal_totals ) );

	$replay_release = $http( '/import/daily-sales/commit', 'POST', $credentials['manager'], array( 'job_id' => $job_id ) );
	$after_sales = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_job_id=%d', Stockland_DB::table( 'sales' ), $job_id ) );
	$check( 'replaying the Branch Manager release returns POSTED and does not create another sale', 200 === $replay_release['status'] && 'POSTED' === ( $replay_release['data']['status'] ?? '' ) && $before_sales === 0 && $after_sales === 1, 'sales before=' . $before_sales . ' after=' . $after_sales );
} catch ( Throwable $error ) {
	++$fail;
	echo 'FAIL real Daily POS import E2E — ' . substr( str_replace( array( "\r", "\n" ), ' ', $error->getMessage() ), 0, 1200 ) . PHP_EOL;
} finally {
	if ( $csv_path && is_file( $csv_path ) ) {
		unlink( $csv_path );
	}
	foreach ( $credentials as $credential ) {
		if ( ! empty( $credential['uuid'] ) && function_exists( 'wp_delete_application_password' ) ) {
			wp_delete_application_password( (int) $credential['user_id'], (string) $credential['uuid'] );
		}
	}
}

if ( $fail ) {
	echo 'RESULT FAIL (' . $fail . ')' . PHP_EOL;
	exit( 1 );
}
echo 'RESULT PASS: real multipart POS upload → validation → staging → duplicate protection → Branch Manager-only release → sale/stock ledger/GL posting and replay guard.' . PHP_EOL;
