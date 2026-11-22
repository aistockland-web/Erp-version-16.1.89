<?php
require __DIR__ . '/lib.php';
global $wpdb;
$u = get_option( 'e2e_users', array() );
$failed = 0;
$check = static function ( $label, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail ? ' — ' . $detail : '' ) . PHP_EOL;
	if ( ! $ok ) { ++$failed; }
};
$base_url = rtrim( (string) getenv( 'STOCKLAND_E2E_SITE_URL' ), '/' );
$check( 'loopback disposable WordPress HTTP endpoint is configured', '' !== $base_url && ! empty( $u['se'] ) && ! empty( $u['bm'] ) );
$check( 'PHP cURL, CURLFile and Application Password APIs are available', function_exists( 'curl_init' ) && class_exists( 'CURLFile' ) && class_exists( 'ZipArchive' ) && class_exists( 'WP_Application_Passwords' ) && method_exists( 'WP_Application_Passwords', 'create_new_application_password' ) && method_exists( 'WP_Application_Passwords', 'delete_application_password' ) );
if ( $failed ) { exit( 1 ); }

$make_password = static function ( $user_id, $name ) {
	$user = get_userdata( (int) $user_id );
	if ( ! $user ) { throw new RuntimeException( 'Synthetic REST user not found.' ); }
	$created = WP_Application_Passwords::create_new_application_password( (int) $user_id, array( 'name' => $name ) );
	if ( is_wp_error( $created ) || ! is_array( $created ) || empty( $created[0] ) || empty( $created[1]['uuid'] ) ) {
		$message = is_wp_error( $created ) ? $created->get_error_message() : 'invalid Application Password response';
		throw new RuntimeException( 'Could not create temporary disposable REST credentials: ' . $message );
	}
	return array( 'user_id' => (int) $user_id, 'login' => (string) $user->user_login, 'password' => (string) $created[0], 'uuid' => (string) $created[1]['uuid'] );
};
$http = static function ( $route, $credential, $json = null, $upload_path = null ) use ( $base_url ) {
	$handle = curl_init( $base_url . '/wp-json/stockland/v16' . $route );
	if ( false === $handle ) { throw new RuntimeException( 'Could not initialize loopback REST request.' ); }
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_USERPWD => $credential['login'] . ':' . $credential['password'],
		CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_HTTPHEADER => array( 'Accept: application/json' ),
	);
	if ( null !== $upload_path ) {
		$options[ CURLOPT_POSTFIELDS ] = array(
			'file' => new CURLFile( $upload_path, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'stockland-pos-real-import-e2e.xlsx' ),
			'business_date' => '2026-11-23',
		);
	} elseif ( null !== $json ) {
		$options[ CURLOPT_HTTPHEADER ][] = 'Content-Type: application/json';
		$options[ CURLOPT_POSTFIELDS ] = wp_json_encode( $json );
	}
	curl_setopt_array( $handle, $options );
	$body = curl_exec( $handle );
	$error = curl_error( $handle );
	$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	curl_close( $handle );
	if ( false === $body ) { throw new RuntimeException( 'Loopback WordPress REST request failed: ' . $error ); }
	$data = json_decode( (string) $body, true );
	return array( 'status' => $status, 'data' => is_array( $data ) ? $data : array( 'raw' => substr( (string) $body, 0, 1000 ) ) );
};

$passwords = array();
$workbook_path = '';
try {
	$passwords['sales'] = $make_password( (int) $u['se'], 'Stockland disposable Daily POS E2E uploader' );
	$passwords['manager'] = $make_password( (int) $u['bm'], 'Stockland disposable Daily POS E2E release approver' );
	$workbook_path = tempnam( sys_get_temp_dir(), 'stockland-pos-e2e-' );
	if ( ! $workbook_path ) { throw new RuntimeException( 'Could not create temporary POS XLSX workbook.' ); }
	$zip = new ZipArchive();
	if ( true !== $zip->open( $workbook_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( 'Could not initialize disposable POS XLSX workbook.' );
	}
	$cell = static function ( $ref, $value ) {
		return '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>';
	};
	$rows = array(
		'<row r="3">' . $cell( 'J3', 'Monday, November 23, 2026' ) . '</row>',
		'<row r="4">' . $cell( 'D4', 'Summarized By Article' ) . '</row>',
		'<row r="7">' . $cell( 'A7', 'Cash Sales Voucher, Report filtered by date criteria: 11/23/2026' ) . '</row>',
		'<row r="8">' .
			$cell( 'A8', 'Article Code' ) .
			$cell( 'C8', 'Article Name' ) .
			$cell( 'E8', 'Quantity' ) .
			$cell( 'I8', 'Avg.  Amount' ) .
			$cell( 'L8', 'Total  Amount' ) .
			'</row>',
		'<row r="9">' .
			$cell( 'B9', 'STK-TEE-001' ) .
			$cell( 'C9', 'Stockland TEE' ) .
			$cell( 'E9', '1' ) .
			$cell( 'I9', '450.00' ) .
			$cell( 'L9', '450.00' ) .
			'</row>',
		'<row r="28">' . $cell( 'F28', 'Sub Total' ) . $cell( 'K28', '450.000' ) . '</row>',
		'<row r="29">' . $cell( 'F29', 'Add. Charge' ) . $cell( 'K29', '0.000' ) . '</row>',
		'<row r="31">' . $cell( 'F31', 'Discount' ) . $cell( 'K31', '0.000' ) . '</row>',
		'<row r="32">' . $cell( 'F32', 'Total Tax' ) . $cell( 'K32', '67.500' ) . '</row>',
		'<row r="33">' . $cell( 'F33', 'Grand Total' ) . $cell( 'K33', '517.500' ) . '</row>'
	);
	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' .
		implode( '', $rows ) .
		'</sheetData><mergeCells count="1"><mergeCell ref="A8:B8"/></mergeCells></worksheet>';
	$entries = array(
		'[Content_Types].xml' =>
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
			'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
			'<Default Extension="xml" ContentType="application/xml"/>' .
			'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
			'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
			'</Types>',
		'_rels/.rels' =>
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
			'</Relationships>',
		'xl/workbook.xml' =>
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
			'<sheets><sheet name="Daily POS" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' =>
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
			'</Relationships>',
		'xl/worksheets/sheet1.xml' => $sheet_xml,
	);
	foreach ( $entries as $name => $content ) {
		if ( ! $zip->addFromString( $name, $content ) ) {
			$zip->close();
			throw new RuntimeException( 'Could not write XLSX entry ' . $name );
		}
	}
	if ( true !== $zip->close() ) {
		throw new RuntimeException( 'Could not close disposable POS XLSX workbook.' );
	}
	$preview = $http( '/import/daily-sales/preview', $passwords['sales'], null, $workbook_path );
	$job_id = (int) ( $preview['data']['job_id'] ?? 0 );
	$summary = (array) ( $preview['data']['summary'] ?? array() );
	$check( 'Sales Employee uploads genuine multipart XLSX workbook to the production preview route', 200 === $preview['status'] && $job_id > 0, 'HTTP ' . $preview['status'] . ' ' . wp_json_encode( $preview['data'] ) );
	$check( 'preview validates SKU, quantity, selling price and VAT then stages one READY row', 'READY' === ( $preview['data']['status'] ?? '' ) && 1 === (int) ( $summary['valid_rows'] ?? 0 ) && 0 === (int) ( $summary['error_rows'] ?? -1 ), wp_json_encode( $summary ) );
	if ( $job_id <= 0 || 'READY' !== ( $preview['data']['status'] ?? '' ) ) { throw new RuntimeException( 'No releasable READY job was created; stop before commit.' ); }

	$preview_replay = $http( '/import/daily-sales/preview', $passwords['sales'], null, $workbook_path );
	$check( 'same workbook bytes return the existing import job without duplicate staging', 200 === $preview_replay['status'] && ! empty( $preview_replay['data']['existing_job'] ) && $job_id === (int) ( $preview_replay['data']['job_id'] ?? 0 ) );

	$sales_release = $http( '/import/daily-sales/commit', $passwords['sales'], array( 'job_id' => $job_id ) );
	$check( 'Sales Employee is denied the final Daily POS release endpoint', 403 === $sales_release['status'], 'HTTP ' . $sales_release['status'] );
	$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_job_id=%d', Stockland_DB::table( 'sales' ), $job_id ) );
	$manager_release = $http( '/import/daily-sales/commit', $passwords['manager'], array( 'job_id' => $job_id ) );
	$manager_release_ok = 200 === $manager_release['status'] && 'POSTED' === ( $manager_release['data']['status'] ?? '' );
	$check( 'assigned Branch Manager explicitly releases staged import through the real REST commit route', $manager_release_ok, 'HTTP ' . $manager_release['status'] . ' ' . wp_json_encode( $manager_release['data'] ) );
	if ( ! $manager_release_ok ) {
		throw new RuntimeException( 'Branch Manager release did not post this unique business-day job. Existing or blocked jobs must be resolved, not bypassed.' );
	}

	$job = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Stockland_DB::table( 'import_jobs' ), $job_id ), ARRAY_A );
	$rows = Stockland_DB::read_results( $wpdb->prepare( 'SELECT status,posted_sale_id FROM %i WHERE job_id=%d ORDER BY source_row_number', Stockland_DB::table( 'import_rows' ), $job_id ), ARRAY_A );
	$sales_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_job_id=%d', Stockland_DB::table( 'sales' ), $job_id ) );
	$check( 'uploading Sales Employee and releasing Branch Manager are separate audited actors', (int) ( $job['created_by'] ?? 0 ) === (int) $u['se'] && (int) ( $job['approved_by'] ?? 0 ) === (int) $u['bm'] );
	$check( 'staged row is POSTED and linked to exactly one ERP sale', 'POSTED' === ( $job['status'] ?? '' ) && 1 === (int) ( $job['processed_rows'] ?? 0 ) && 1 === $sales_count && 1 === count( $rows ) && 'POSTED' === ( $rows[0]['status'] ?? '' ) && ! empty( $rows[0]['posted_sale_id'] ), 'sales=' . $sales_count );

	$sale_id = (string) ( $rows[0]['posted_sale_id'] ?? '' );
	$ledger = $sale_id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE reference_type=%s AND reference_id=%s', Stockland_DB::table( 'transactions' ), 'sale', $sale_id ) ) : 0;
	$check( 'posted sale has its matching inventory ledger movement', $ledger >= 1, 'ledger rows=' . $ledger );
	$source_id = $job_id . '-0';
	$journal_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_type=%s AND source_id=%s', Stockland_DB::table( 'gl_journals' ), 'DAILY_SALES_CHUNK', $source_id ) );
	$totals = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(l.debit),0) AS debit_total,COALESCE(SUM(l.credit),0) AS credit_total FROM %i l JOIN %i j ON j.id=l.journal_id WHERE j.source_type=%s AND j.source_id=%s',
			Stockland_DB::table( 'gl_lines' ), Stockland_DB::table( 'gl_journals' ), 'DAILY_SALES_CHUNK', $source_id
		),
		ARRAY_A
	);
	$check( 'Daily POS posting produces a balanced GL journal chunk', $journal_count >= 1 && $totals && abs( (float) $totals['debit_total'] - (float) $totals['credit_total'] ) < 0.00001, wp_json_encode( $totals ) );

	$replay = $http( '/import/daily-sales/commit', $passwords['manager'], array( 'job_id' => $job_id ) );
	$replay_status = (string) ( $replay['data']['status'] ?? $replay['data']['job']['status'] ?? '' );
	$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_job_id=%d', Stockland_DB::table( 'sales' ), $job_id ) );
	$check( 'replayed Branch Manager release remains POSTED and creates no second sale', 200 === $replay['status'] && 'POSTED' === $replay_status && 0 === $before && 1 === $after, 'sales before=' . $before . ' after=' . $after );
} catch ( Throwable $error ) {
	++$failed;
	echo 'FAIL real Daily POS HTTP XLSX E2E — ' . substr( str_replace( array( "\r", "\n" ), ' ', $error->getMessage() ), 0, 1000 ) . PHP_EOL;
} finally {
	if ( $workbook_path && is_file( $workbook_path ) ) { unlink( $workbook_path ); }
	foreach ( $passwords as $credential ) {
		if ( ! empty( $credential['uuid'] ) && class_exists( 'WP_Application_Passwords' ) ) {
			WP_Application_Passwords::delete_application_password( (int) $credential['user_id'], (string) $credential['uuid'] );
		}
	}
}
if ( $failed ) { echo 'RESULT FAIL (' . $failed . ')' . PHP_EOL; exit( 1 ); }
echo 'RESULT PASS: real multipart POS XLSX upload → validation → staging → duplicate guard → Branch Manager-only release → sale, stock ledger, balanced GL and replay guard.' . PHP_EOL;
