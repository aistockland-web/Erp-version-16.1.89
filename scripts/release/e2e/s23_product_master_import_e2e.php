<?php
/**
 * Product Master import regression (real multipart XLSX over loopback HTTP).
 * Guards the 16.2.18 defects: wrong prepare() argument order in the barcode-uniqueness check (preview)
 * and the clobbered closure variable in the commit loop (commit).
 * Disposable site only; needs STOCKLAND_E2E_SITE_URL and WP_ENVIRONMENT_TYPE=local (Application Passwords over HTTP).
 */
require __DIR__ . '/lib.php';
global $wpdb;
$u      = get_option( 'e2e_users', array() );
$failed = 0;
$check  = static function ( $label, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail ? ' — ' . $detail : '' ) . PHP_EOL;
	if ( ! $ok ) { ++$failed; }
};
$base_url = rtrim( (string) getenv( 'STOCKLAND_E2E_SITE_URL' ), '/' );
$check( 'loopback endpoint and synthetic users are configured', '' !== $base_url && ! empty( $u['mg'] ) && ! empty( $u['se'] ) );
$check( 'cURL, CURLFile, ZipArchive and Application Password APIs available', function_exists( 'curl_init' ) && class_exists( 'CURLFile' ) && class_exists( 'ZipArchive' ) && class_exists( 'WP_Application_Passwords' ) );
if ( $failed ) { exit( 1 ); }

$make_password = static function ( $user_id, $name ) {
	$user    = get_userdata( (int) $user_id );
	$created = $user ? WP_Application_Passwords::create_new_application_password( (int) $user_id, array( 'name' => $name ) ) : null;
	if ( ! is_array( $created ) ) { throw new RuntimeException( 'Could not create temporary REST credentials.' ); }
	return array( 'user_id' => (int) $user_id, 'login' => (string) $user->user_login, 'password' => (string) $created[0], 'uuid' => (string) $created[1]['uuid'] );
};
$http = static function ( $route, $credential, $json = null, $upload = null ) use ( $base_url ) {
	$h   = curl_init( $base_url . '/wp-json/stockland/v16' . $route );
	$opt = array( CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_USERPWD => $credential['login'] . ':' . $credential['password'], CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_TIMEOUT => 90, CURLOPT_HTTPHEADER => array( 'Accept: application/json' ) );
	if ( null !== $upload ) {
		$opt[ CURLOPT_POSTFIELDS ] = array( 'file' => new CURLFile( $upload, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'product-master-e2e.xlsx' ) );
	} elseif ( null !== $json ) {
		$opt[ CURLOPT_HTTPHEADER ][] = 'Content-Type: application/json';
		$opt[ CURLOPT_POSTFIELDS ]   = wp_json_encode( $json );
	}
	curl_setopt_array( $h, $opt );
	$body   = curl_exec( $h );
	$status = (int) curl_getinfo( $h, CURLINFO_RESPONSE_CODE );
	curl_close( $h );
	$data = json_decode( (string) $body, true );
	return array( 'status' => $status, 'data' => is_array( $data ) ? $data : array( 'raw' => substr( (string) $body, 0, 500 ) ) );
};

$headers = array( 'SKU', 'Product Name', 'Product Type', 'Parent SKU', 'Style Code', 'Brand', 'Department', 'Barcode', 'Color', 'Size', 'Category', 'Unit', 'Purchase Cost ETB (Management only)', 'Retail Price (ETB)', 'Wholesale Price (ETB)', 'Branch Reorder Level (PCS)', 'Central Reorder Level (PCS)' );
$make_book = static function ( array $data_rows ) use ( $headers ) {
	$path = tempnam( sys_get_temp_dir(), 'stockland-pm-e2e-' );
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$col  = static function ( $i ) { return chr( 65 + $i ); };
	$cell = static function ( $ref, $v ) { return '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>'; };
	$rows = array( '<row r="1">' . $cell( 'A1', 'STOCKLAND ETHIOPIA — CONTROLLED MASTER IMPORT TEMPLATE' ) . '</row>', '<row r="2">' . $cell( 'A2', 'Product Master never creates physical stock.' ) . '</row>' );
	$line = '';
	foreach ( $headers as $i => $hd ) { $line .= $cell( $col( $i ) . '3', $hd ); }
	$rows[] = '<row r="3">' . $line . '</row>';
	foreach ( $data_rows as $n => $row ) {
		$r = $n + 4; $line = '';
		foreach ( $row as $i => $v ) { if ( '' !== (string) $v ) { $line .= $cell( $col( $i ) . $r, $v ); } }
		$rows[] = '<row r="' . $r . '">' . $line . '</row>';
	}
	$ns = 'http://schemas.openxmlformats.org/';
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="' . $ns . 'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
	$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
	$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships"><sheets><sheet name="PRODUCT MASTER" sheetId="1" r:id="rId1"/></sheets></workbook>' );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );
	$zip->addFromString( 'xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="' . $ns . 'spreadsheetml/2006/main"><sheetData>' . implode( '', $rows ) . '</sheetData></worksheet>' );
	$zip->close();
	return $path;
};
// Row layout follows $headers order.
$row = static function ( $sku, $type, $parent, $barcode, $size, $retail = 1200, $cost = 600 ) {
	return array( $sku, 'E2E Import Shirt', $type, $parent, 'E2EIMP', 'Stockland', 'Apparel', $barcode, 'Black', $size, 'Shirts', 'PCS', $cost, $retail, 900, 5, 20 );
};
$run   = substr( md5( (string) microtime( true ) ), 0, 6 );
$style = 'STK-E2E' . strtoupper( $run );
$v1    = $style . '-BLK-M';
$v2    = $style . '-BLK-L';
$bc1   = '61' . substr( preg_replace( '/\D/', '', (string) hexdec( $run ) ) . '0000000000', 0, 10 ) . '1';
$bc2   = '61' . substr( preg_replace( '/\D/', '', (string) hexdec( $run ) ) . '0000000000', 0, 10 ) . '2';

$passwords = array();
$files     = array();
try {
	$passwords['mgmt']  = $make_password( (int) $u['mg'], 'Stockland disposable Product Master E2E (management)' );
	$passwords['sales'] = $make_password( (int) $u['se'], 'Stockland disposable Product Master E2E (sales)' );
	$good               = $make_book( array( $row( $style, 'STYLE', '', '', '' ), $row( $v1, 'VARIANT', $style, $bc1, 'M' ), $row( $v2, 'VARIANT', $style, $bc2, 'L' ) ) );
	$files[]            = $good;

	$denied = $http( '/import/products/preview', $passwords['sales'], null, $good );
	$check( 'a Sales user cannot preview a Product Master import', in_array( $denied['status'], array( 401, 403 ), true ), 'HTTP ' . $denied['status'] );

	$p = $http( '/import/products/preview', $passwords['mgmt'], null, $good );
	$check( 'valid workbook previews without the barcode-uniqueness error (prepare() argument-order regression)', 200 === $p['status'] && 'PREVIEW' === ( $p['data']['status'] ?? '' ) && ! empty( $p['data']['job_id'] ), 'HTTP ' . $p['status'] . ' ' . substr( wp_json_encode( $p['data']['message'] ?? ( $p['data']['status'] ?? '' ) ), 0, 160 ) );
	$job = (int) ( $p['data']['job_id'] ?? 0 );

	$again = $http( '/import/products/preview', $passwords['mgmt'], null, $good );
	$check( 'previewing the identical file again returns the existing job (duplicate-file guard)', 200 === $again['status'] && (int) ( $again['data']['job_id'] ?? 0 ) === $job );

	$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE sku IN (%s,%s,%s)', Stockland_DB::table( 'products' ), $style, $v1, $v2 ) );
	$check( 'preview writes no product rows', 0 === $before, 'rows=' . $before );

	$c = $http( '/import/products/commit', $passwords['mgmt'], array( 'job_id' => $job ) );
	$check( 'commit succeeds (clobbered-closure-variable regression)', 200 === $c['status'], 'HTTP ' . $c['status'] . ' ' . substr( (string) ( $c['data']['message'] ?? '' ), 0, 160 ) );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT sku,product_type,barcode,retail_price FROM %i WHERE sku IN (%s,%s,%s) ORDER BY sku', Stockland_DB::table( 'products' ), $style, $v1, $v2 ), ARRAY_A );
	$check( 'commit created exactly the style and its two variants', 3 === count( $rows ), wp_json_encode( $rows ) );
	$by = array();
	foreach ( $rows as $r ) { $by[ $r['sku'] ] = $r; }
	$check( 'variant barcodes and retail price are stored intact', isset( $by[ $v1 ] ) && (string) $by[ $v1 ]['barcode'] === $bc1 && abs( (float) $by[ $v1 ]['retail_price'] - 1200 ) < 0.001 && isset( $by[ $v2 ] ) && (string) $by[ $v2 ]['barcode'] === $bc2 );
	$stock = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(physical_qty),0) FROM %i WHERE sku IN (%s,%s,%s)', Stockland_DB::table( 'balances' ), $style, $v1, $v2 ) );
	$check( 'Product Master creates no physical stock', 0.0 === $stock, 'qty=' . $stock );

	$replay = $http( '/import/products/commit', $passwords['mgmt'], array( 'job_id' => $job ) );
	$after  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE sku IN (%s,%s,%s)', Stockland_DB::table( 'products' ), $style, $v1, $v2 ) );
	$check( 'replayed commit creates no duplicate products', 3 === $after, 'HTTP ' . $replay['status'] . ' rows=' . $after );

	// SKU scheme is free-form and defined in one place (Stockland_Product_Service::valid_sku): every layer must agree.
	$free  = 'Nike.' . $run . '_X-1';
	$fprev = $http( '/import/products/preview', $passwords['mgmt'], null, $files[] = $make_book( array( $row( $free, 'SIMPLE', '', '', '' ) ) ) );
	$check( 'import accepts a SKU that does not start with STK-', 'PREVIEW' === ( $fprev['data']['status'] ?? '' ), 'status=' . ( $fprev['data']['status'] ?? $fprev['status'] ) );
	$http( '/import/jobs/' . (int) ( $fprev['data']['job_id'] ?? 0 ) . '/cancel', $passwords['mgmt'], array( 'x' => 1 ) );
	$rest_ok  = call( 'POST', '/products', array( 'sku' => 'Zed.' . $run, 'product_name' => 'Free scheme product', 'category' => 'Tops', 'unit' => 'PCS', 'purchase_cost' => '10.00', 'retail_price' => '20.00', 'wholesale_price' => '15.00' ), (int) $u['mg'] );
	$rest_bad = call( 'POST', '/products', array( 'sku' => '-bad sku', 'product_name' => 'Bad', 'category' => 'Tops', 'unit' => 'PCS', 'purchase_cost' => '10.00', 'retail_price' => '20.00', 'wholesale_price' => '15.00' ), (int) $u['mg'] );
	$check( 'REST product create accepts the same free-form SKU rule as import', in_array( $rest_ok[0], array( 200, 201 ), true ), 'HTTP ' . $rest_ok[0] . ' ' . substr( wp_json_encode( $rest_ok[1]['message'] ?? '' ), 0, 100 ) );
	$check( 'REST product create rejects a malformed SKU with the shared rule text', 400 === $rest_bad[0] && false !== stripos( (string) ( $rest_bad[1]['message'] ?? '' ), 'start with a letter or number' ), 'HTTP ' . $rest_bad[0] );

	$neg = $http( '/import/products/preview', $passwords['mgmt'], null, $files[] = $make_book( array( $row( 'STK-NEG' . strtoupper( $run ), 'SIMPLE', '', '', '', -5 ) ) ) );
	$check( 'negative retail price is rejected at preview', 'ERROR' === ( $neg['data']['status'] ?? '' ) || (int) ( $neg['data']['error_rows'] ?? 0 ) > 0 || $neg['status'] >= 400, 'status=' . ( $neg['data']['status'] ?? $neg['status'] ) );

	$dup = $http( '/import/products/preview', $passwords['mgmt'], null, $files[] = $make_book( array( $row( 'STK-DUP' . strtoupper( $run ), 'SIMPLE', '', $bc1, '' ) ) ) );
	$check( 'a barcode already owned by another product is rejected at preview', 'ERROR' === ( $dup['data']['status'] ?? '' ) || (int) ( $dup['data']['error_rows'] ?? 0 ) > 0 || $dup['status'] >= 400, 'status=' . ( $dup['data']['status'] ?? $dup['status'] ) );

	$orphan = $http( '/import/products/preview', $passwords['mgmt'], null, $files[] = $make_book( array( $row( 'STK-ORPH' . strtoupper( $run ) . '-M', 'VARIANT', '', '', 'M' ) ) ) );
	$check( 'a VARIANT without a Parent SKU is rejected at preview', 'ERROR' === ( $orphan['data']['status'] ?? '' ) || (int) ( $orphan['data']['error_rows'] ?? 0 ) > 0 || $orphan['status'] >= 400, 'status=' . ( $orphan['data']['status'] ?? $orphan['status'] ) );

	// ---- 16.2.18 audit hardening: visibility, cancellation, audit trail, mapping safety, type-change guard ----
	$flag = $http( '/import/products/preview', $passwords['mgmt'], null, $good );
	$check( 'identical already-imported file is reported as existing_job with a clear message', ! empty( $flag['data']['existing_job'] ) && 'POSTED' === ( $flag['data']['status'] ?? '' ) && false !== stripos( (string) ( $flag['data']['message'] ?? '' ), 'already imported' ), wp_json_encode( array( $flag['data']['status'] ?? null, $flag['data']['message'] ?? null ) ) );

	$posted_audit = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE action=%s AND reference=%s', Stockland_DB::table( 'audit_logs' ), 'PRODUCT_IMPORT_JOB_POSTED', 'PRODUCT_MASTER_IMPORT_JOB|' . $job ) );
	$check( 'job completion is written to the audit chain', 1 === $posted_audit, 'rows=' . $posted_audit );

	$big_rows = array();
	for ( $n = 1; $n <= 230; $n++ ) { $big_rows[] = $row( sprintf( 'STK-BIG%s-%03d', strtoupper( $run ), $n ), 'SIMPLE', '', '', '', 100 + $n ); }
	$big_rows[] = $row( 'STK-BIGBAD' . strtoupper( $run ), 'SIMPLE', '', '', '', -1 );
	$big = $http( '/import/products/preview', $passwords['mgmt'], null, $files[] = $make_book( $big_rows ) );
	$first = $big['data']['preview'][0] ?? array();
	$check( 'an error beyond row 200 is shown first and the preview is marked truncated', 'ERROR' === ( $big['data']['status'] ?? '' ) && ! empty( $first['error'] ) && ! empty( $big['data']['preview_truncated'] ) && 231 === (int) ( $big['data']['preview_total'] ?? 0 ), 'first=' . substr( wp_json_encode( $first['error'] ?? null ), 0, 80 ) );
	$big_job = (int) ( $big['data']['job_id'] ?? 0 );

	$deny_cancel = $http( '/import/jobs/' . $big_job . '/cancel', $passwords['sales'], array( 'x' => 1 ) );
	$check( 'a Sales user cannot cancel a Product Master job', in_array( $deny_cancel['status'], array( 401, 403 ), true ), 'HTTP ' . $deny_cancel['status'] );
	$again_err = $http( '/import/products/preview', $passwords['mgmt'], null, $files[ count( $files ) - 1 ] );
	$check( 'the identical erroring file is blocked with guidance to correct or cancel', ! empty( $again_err['data']['existing_job'] ) && 'ERROR' === ( $again_err['data']['status'] ?? '' ) && false !== stripos( (string) ( $again_err['data']['message'] ?? '' ), 'cancel' ) );
	$cancel = $http( '/import/jobs/' . $big_job . '/cancel', $passwords['mgmt'], array( 'x' => 1 ) );
	$check( 'Management can cancel an unreleased job', 200 === $cancel['status'] && 'CANCELLED' === ( $cancel['data']['status'] ?? '' ), 'HTTP ' . $cancel['status'] );
	$cancel_audit = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE action=%s AND reference=%s', Stockland_DB::table( 'audit_logs' ), 'IMPORT_JOB_CANCELLED', 'PRODUCT_MASTER_JOB|' . $big_job ) );
	$check( 'cancellation is written to the audit chain', 1 === $cancel_audit );
	$restage = $http( '/import/products/preview', $passwords['mgmt'], null, $files[ count( $files ) - 1 ] );
	$check( 'after cancellation the same file can be checked again as a new job', 200 === $restage['status'] && empty( $restage['data']['existing_job'] ) && (int) ( $restage['data']['job_id'] ?? 0 ) > $big_job, 'HTTP ' . $restage['status'] );
	$http( '/import/jobs/' . (int) ( $restage['data']['job_id'] ?? 0 ) . '/cancel', $passwords['mgmt'], array( 'x' => 1 ) );
	$cancel_posted = $http( '/import/jobs/' . $job . '/cancel', $passwords['mgmt'], array( 'x' => 1 ) );
	$check( 'a POSTED job can never be cancelled', 409 === $cancel_posted['status'], 'HTTP ' . $cancel_posted['status'] );
	$still = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE sku IN (%s,%s,%s)', Stockland_DB::table( 'products' ), $style, $v1, $v2 ) );
	$check( 'cancelled and refused cancellations leave the committed catalog untouched', 3 === $still );

	$retype = $http( '/import/products/preview', $passwords['mgmt'], null, $files[] = $make_book( array( $row( $style, 'SIMPLE', '', '', '' ) ) ) );
	$check( 'a STYLE that has variants cannot be re-typed by import', 'ERROR' === ( $retype['data']['status'] ?? '' ), 'status=' . ( $retype['data']['status'] ?? $retype['status'] ) );

	$sci = $http( '/import/products/preview', $passwords['mgmt'], null, $files[] = $make_book( array( $row( 'STK-SCI' . strtoupper( $run ), 'SIMPLE', '', '6.111000001001E+15', '' ) ) ) );
	$check( 'a barcode Excel rounded into scientific notation is rejected', 'ERROR' === ( $sci['data']['status'] ?? '' ), 'status=' . ( $sci['data']['status'] ?? $sci['status'] ) );

	$map_headers = array( 'SKU', 'Product Name' );
	$bad_type = $http( '/import/mappings', $passwords['mgmt'], array( 'import_type' => 'EVIL', 'headers' => $map_headers, 'mapping' => array( 'sku' => 'SKU', 'product_name' => 'Product Name' ) ) );
	$check( 'a mapping with an unknown import type is refused', 400 === $bad_type['status'], 'HTTP ' . $bad_type['status'] );
	$bad_hdr = $http( '/import/mappings', $passwords['mgmt'], array( 'import_type' => 'PRODUCT_MASTER', 'headers' => $map_headers, 'mapping' => array( 'sku' => 'SKU', 'product_name' => 'No Such Column' ) ) );
	$check( 'a mapping pointing at a column that does not exist is refused', 400 === $bad_hdr['status'], 'HTTP ' . $bad_hdr['status'] );
	$no_sku = $http( '/import/mappings', $passwords['mgmt'], array( 'import_type' => 'PRODUCT_MASTER', 'headers' => $map_headers, 'mapping' => array( 'product_name' => 'Product Name' ) ) );
	$check( 'a mapping without the required SKU field is refused', 400 === $no_sku['status'], 'HTTP ' . $no_sku['status'] );
	$good_map = $http( '/import/mappings', $passwords['mgmt'], array( 'import_type' => 'product_master', 'source_system' => 'E2E_SRC', 'headers' => $map_headers, 'mapping' => array( 'sku' => 'SKU', 'product_name' => 'Product Name' ) ) );
	$prof_type = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT import_type FROM %i WHERE source_system=%s ORDER BY id DESC LIMIT 1', Stockland_DB::table( 'import_profiles' ), 'E2E_SRC' ) );
	$check( 'a valid mapping saves with the canonical upper-case import type', 200 === $good_map['status'] && 'PRODUCT_MASTER' === $prof_type, 'HTTP ' . $good_map['status'] . ' type=' . $prof_type );
	$map_audit = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE action=%s', Stockland_DB::table( 'audit_logs' ), 'IMPORT_MAPPING_SAVED' ) );
	$check( 'saving a mapping is written to the audit chain', $map_audit >= 1, 'rows=' . $map_audit );
	$chain = Stockland_Audit::verify_chain();
	$check( 'the audit hash chain still verifies after all of the above', is_array( $chain ) ? ! empty( $chain['ok'] ) || ( isset( $chain['status'] ) && 'OK' === strtoupper( (string) $chain['status'] ) ) : (bool) $chain, wp_json_encode( $chain ) );

	// Daily POS job cancellation rules (synthetic staged job, no sale released).
	$jobs_tbl = Stockland_DB::table( 'import_jobs' );
	$branch   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT branch_location_id FROM %i WHERE wp_user_id=%d', Stockland_DB::table( 'users' ), (int) $u['bm'] ) );
	$ins      = static function ( $key ) use ( $wpdb, $jobs_tbl, $branch ) {
		$wpdb->insert( $jobs_tbl, array( 'import_key' => hash( 'sha256', $key ), 'import_type' => 'DAILY_SALES', 'branch_location_id' => $branch, 'business_date' => '2026-11-20', 'source_filename' => 'e2e-cancel.xlsx', 'source_sha256' => hash( 'sha256', $key ), 'source_system' => 'THIRD_PARTY_POS', 'status' => 'READY', 'total_rows' => 1, 'valid_rows' => 1, 'error_rows' => 0, 'processed_rows' => 0, 'created_by' => 1 ) );
		return (int) $wpdb->insert_id;
	};
	$dj = $ins( 'e2e-cancel-' . $run );
	$sales_cancel = $http( '/import/jobs/' . $dj . '/cancel', $passwords['sales'], array( 'x' => 1 ) );
	$check( 'a Sales Employee cannot cancel a Daily POS job (Branch Manager only)', in_array( $sales_cancel['status'], array( 401, 403 ), true ), 'HTTP ' . $sales_cancel['status'] );
	$passwords['bm'] = $make_password( (int) $u['bm'], 'Stockland disposable Product Master E2E (branch manager)' );
	$bm_cancel = $http( '/import/jobs/' . $dj . '/cancel', $passwords['bm'], array( 'x' => 1 ) );
	$check( 'the assigned Branch Manager can cancel a staged Daily POS job', 200 === $bm_cancel['status'] && 'CANCELLED' === ( $bm_cancel['data']['status'] ?? '' ), 'HTTP ' . $bm_cancel['status'] );
	$pj = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE import_type='PRODUCT_MASTER' AND status='PREVIEW' ORDER BY id DESC LIMIT 1", $jobs_tbl ) );
	$bm_pm = $pj ? $http( '/import/jobs/' . $pj . '/cancel', $passwords['bm'], array( 'x' => 1 ) ) : array( 'status' => 403 );
	$check( 'a Branch Manager cannot cancel a Product Master job', in_array( $bm_pm['status'], array( 401, 403 ), true ), 'HTTP ' . $bm_pm['status'] );
	$dj2 = $ins( 'e2e-cancel-posted-' . $run );
	$wpdb->update( $jobs_tbl, array( 'processed_rows' => 1, 'status' => 'PARTIAL' ), array( 'id' => $dj2 ) );
	$partial_cancel = $http( '/import/jobs/' . $dj2 . '/cancel', $passwords['bm'], array( 'x' => 1 ) );
	$check( 'a Daily POS job that has already released rows cannot be cancelled', 409 === $partial_cancel['status'], 'HTTP ' . $partial_cancel['status'] );
	$wpdb->delete( $jobs_tbl, array( 'id' => $dj2 ) );

	$none = $http( '/import/products/commit', $passwords['mgmt'], array( 'job_id' => 999999 ) );
	$check( 'commit of an unknown job is refused', $none['status'] >= 400, 'HTTP ' . $none['status'] );
} catch ( Throwable $e ) {
	++$failed;
	echo 'FAIL Product Master import E2E — ' . substr( str_replace( array( "\r", "\n" ), ' ', $e->getMessage() ), 0, 800 ) . PHP_EOL;
} finally {
	foreach ( $files as $f ) { if ( is_string( $f ) && is_file( $f ) ) { unlink( $f ); } }
	foreach ( $passwords as $c ) {
		if ( ! empty( $c['uuid'] ) ) { WP_Application_Passwords::delete_application_password( (int) $c['user_id'], (string) $c['uuid'] ); }
	}
}
if ( $failed ) { echo 'RESULT FAIL (' . $failed . ')' . PHP_EOL; exit( 1 ); }
echo 'RESULT PASS: Product Master XLSX upload → preview → duplicate guard → commit → replay, with validation rejections and role denial.' . PHP_EOL;
