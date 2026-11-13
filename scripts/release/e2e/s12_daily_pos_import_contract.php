<?php
/**
 * Daily POS source-format contract test.
 *
 * Runs only inside the disposable WordPress E2E site. It exercises the actual
 * streaming XLSX reader and importer source adapter without inserting ERP data.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'Stockland_REST_Import_V16' ) || ! class_exists( 'Stockland_XLSX' ) ) {
	throw new RuntimeException( 'Daily POS importer classes are not available in the disposable test site.' );
}
if ( ! class_exists( 'ZipArchive' ) || ! class_exists( 'XMLReader' ) ) {
	throw new RuntimeException( 'The XLSX streaming test requires ZipArchive and XMLReader.' );
}

function stockland_daily_pos_test_cell( $ref, $value ) {
	return '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>';
}

function stockland_daily_pos_test_row( $number, $cells ) {
	$xml = '<row r="' . (int) $number . '">';
	foreach ( $cells as $cell ) {
		$xml .= $cell;
	}
	return $xml . '</row>';
}

$fixture = tempnam( sys_get_temp_dir(), 'stockland-daily-pos-' );
if ( ! $fixture ) {
	throw new RuntimeException( 'Could not create a temporary Daily POS workbook fixture.' );
}
$zip = new ZipArchive();
try {
	if ( true !== $zip->open( $fixture, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( 'Could not initialize the Daily POS XLSX fixture.' );
	}
	$zip->addFromString(
		'[Content_Types].xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
		'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
		'<Default Extension="xml" ContentType="application/xml"/>' .
		'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
		'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
		'</Types>'
	);
	$zip->addFromString(
		'_rels/.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
		'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
		'</Relationships>'
	);
	$zip->addFromString(
		'xl/workbook.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
		'<sheets><sheet name="Document" sheetId="1" r:id="rId1"/></sheets></workbook>'
	);
	$zip->addFromString(
		'xl/_rels/workbook.xml.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
		'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
		'</Relationships>'
	);

	$rows = array();
	$rows[] = stockland_daily_pos_test_row( 3, array( stockland_daily_pos_test_cell( 'J3', 'Saturday, November 7, 2026' ) ) );
	$rows[] = stockland_daily_pos_test_row( 4, array( stockland_daily_pos_test_cell( 'D4', 'Summarized By Article' ) ) );
	$rows[] = stockland_daily_pos_test_row( 7, array( stockland_daily_pos_test_cell( 'A7', 'Cash Sales Voucher, Report filtered by date criteria: 11/07/2026' ) ) );
	$rows[] = stockland_daily_pos_test_row(
		8,
		array(
			stockland_daily_pos_test_cell( 'A8', 'Article Code' ),
			stockland_daily_pos_test_cell( 'C8', 'Article Name' ),
			stockland_daily_pos_test_cell( 'E8', 'Quantity' ),
			stockland_daily_pos_test_cell( 'I8', 'Avg.  Amount' ),
			stockland_daily_pos_test_cell( 'L8', 'Total  Amount' ),
		)
	);
	$pos_items = array(
		array( 'STK-001', 'COLABABY', 1, '869.57', '869.565', 9 ),
		array( 'STK-021', 'ELEVET SHIRT', 1, '1391.30', '1391.304', 11 ),
		array( 'STK-003', 'FLIES BJAMA', 2, '1347.83', '2695.652', 13 ),
		array( 'STK-043', 'GERRY SIWMING', 1, '869.57', '869.565', 15 ),
		array( 'STK-017', 'H&M T-SHIRT', 2, '1304.35', '2608.696', 17 ),
		array( 'STK-044', 'SIWMING SHORT', 3, '1043.48', '3130.434', 19 ),
		array( 'STK-047', 'SIWMING SHORT ADULT', 1, '1043.48', '1043.478', 21 ),
		array( 'IT-00001-HO', 'TEST ITEM', 1, '0.09', '0.087', 23 ),
		array( 'STK-038', 'VEST FER SEDERIYA', 1, '2347.83', '2347.826', 25 ),
	);
	foreach ( $pos_items as $item ) {
		$rows[] = stockland_daily_pos_test_row(
			$item[5],
			array(
				stockland_daily_pos_test_cell( 'B' . $item[5], $item[0] ),
				stockland_daily_pos_test_cell( 'C' . $item[5], $item[1] ),
				stockland_daily_pos_test_cell( 'E' . $item[5], $item[2] ),
				stockland_daily_pos_test_cell( 'I' . $item[5], $item[3] ),
				stockland_daily_pos_test_cell( 'L' . $item[5], $item[4] ),
			)
		);
	}
	$rows[] = stockland_daily_pos_test_row( 28, array( stockland_daily_pos_test_cell( 'F28', 'Sub Total' ), stockland_daily_pos_test_cell( 'K28', '14956.607' ) ) );
	$rows[] = stockland_daily_pos_test_row( 29, array( stockland_daily_pos_test_cell( 'F29', 'Add. Charge' ), stockland_daily_pos_test_cell( 'K29', '0.000' ) ) );
	$rows[] = stockland_daily_pos_test_row( 31, array( stockland_daily_pos_test_cell( 'F31', 'Discount' ), stockland_daily_pos_test_cell( 'K31', '0.000' ) ) );
	$rows[] = stockland_daily_pos_test_row( 32, array( stockland_daily_pos_test_cell( 'F32', 'Total Tax' ), stockland_daily_pos_test_cell( 'K32', '2243.480' ) ) );
	$rows[] = stockland_daily_pos_test_row( 33, array( stockland_daily_pos_test_cell( 'F33', 'Grand Total' ), stockland_daily_pos_test_cell( 'K33', '17200.087' ) ) );
	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' .
		implode( '', $rows ) .
		'</sheetData><mergeCells count="1"><mergeCell ref="A8:B8"/></mergeCells></worksheet>';
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
	$zip->close();
	$zip = null;

	$workbook = new Stockland_XLSX( $fixture );
	$controller = new Stockland_REST_Import_V16();
	$reflection = new ReflectionMethod( Stockland_REST_Import_V16::class, 'scan_workbook' );
	$reflection->setAccessible( true );
	$found = $reflection->invoke( $controller, $workbook, 'DAILY_SALES', 'THIRD_PARTY_POS' );

	$assert = static function ( $condition, $message ) {
		if ( ! $condition ) {
			throw new RuntimeException( 'Daily POS contract failed: ' . $message );
		}
	};
	$layout_health = Stockland_REST_Import_V16::article_summary_layout_self_test();
	$assert( ! empty( $layout_health['ok'] ), 'Deep System Health self-test must exercise the exact supplier column alignment and field mapping.' );
	$assert( 9 === count( array_filter( (array) ( $layout_health['checks'] ?? array() ) ) ), 'All nine Daily POS layout health assertions must pass.' );
	$assert( 'CNET_SUMMARIZED_BY_ARTICLE' === ( $found['source_meta']['format'] ?? '' ), 'CNET Cash Sales Voucher summarized-by-article layout must be detected.' );
	$assert( '2026-11-07' === ( $found['source_meta']['source_date'] ?? '' ), 'The embedded business date must be read from J3.' );
	$assert( 'Article Code' === ( $found['mapping']['sku'] ?? '' ), 'Article Code must map to the external POS product code.' );
	$assert( 'Quantity' === ( $found['mapping']['qty'] ?? '' ), 'Quantity must map from the original report.' );
	$assert( 'Avg.  Amount' === ( $found['mapping']['selling_price'] ?? '' ), 'Avg. Amount must map to selling price.' );
	$assert( 'Total  Amount' === ( $found['mapping']['line_total'] ?? '' ), 'Total Amount must map to the article-line total.' );
	$assert( 9 === count( $found['rows'] ), 'All nine source article rows must be read; footer/blank rows must not become sales.' );
	$assert( 'STK-001' === ( $found['rows'][0]['Article Code'] ?? '' ), 'Merged A8:B8 must not hide the first SKU stored in column B.' );
	$assert( 'STK-038' === ( $found['rows'][8]['Article Code'] ?? '' ), 'The final SKU stored in column B must be preserved.' );
	$assert( 14956.607 === (float) ( $found['source_meta']['totals']['subtotal'] ?? 0 ), 'The original source subtotal must be captured without changing its three decimals.' );
	$assert( 2243.480 === (float) ( $found['source_meta']['totals']['tax'] ?? 0 ), 'The separate source tax amount must be captured.' );
	$assert( 17200.087 === (float) ( $found['source_meta']['totals']['grand_total'] ?? 0 ), 'The original source grand total must be captured.' );

	$erp_net = '0.00'; $erp_vat = '0.00'; $erp_gross = '0.00'; $erp_units = 0;
	foreach ( $pos_items as $item ) {
		$calc = Stockland_Sale_Math::calculate( $item[2], $item[3], 'WITHOUT_VAT', 0.15 );
		$erp_net = Stockland_Money::add( $erp_net, $calc['net_sales'] );
		$erp_vat = Stockland_Money::add( $erp_vat, $calc['vat_amount'] );
		$erp_gross = Stockland_Money::add( $erp_gross, $calc['sales_total'] );
		$erp_units += (float) $item[2];
	}
	$assert( '14956.64' === $erp_net, 'ERP cents-based net amount must be calculated from the nine article quantities and average prices.' );
	$assert( '2243.51' === $erp_vat, 'ERP VAT must be calculated at the 15% company rate with line-level cent rounding.' );
	$assert( '17200.15' === $erp_gross, 'ERP gross amount must equal net plus VAT.' );
	$assert( 13.0 === $erp_units, 'The original report contains 13 sold units.' );

	$reconcile = new ReflectionMethod( Stockland_REST_Import_V16::class, 'daily_pos_reconcile_summary' );
	$reconcile->setAccessible( true );
	$matched = $reconcile->invoke( $controller, $found['source_meta'], $erp_net, $erp_vat, $erp_gross, count( $pos_items ) );
	$assert( 'MATCH' === ( $matched['status'] ?? '' ), 'The actual uploaded report totals must reconcile within nine cents of line-rounding allowance.' );
	$assert( 0.09 === (float) ( $matched['tolerance'] ?? 0 ), 'The rounding tolerance must be bounded to one cent per article line.' );
	$bad_gross = Stockland_Money::add( $erp_gross, '0.20' );
	$review = $reconcile->invoke( $controller, $found['source_meta'], $erp_net, $erp_vat, $bad_gross, count( $pos_items ) );
	$assert( 'REVIEW' === ( $review['status'] ?? '' ), 'A 20-cent discrepancy must force REVIEW and block release.' );

	$money_match = new ReflectionMethod( Stockland_REST_Import_V16::class, 'daily_money_matches' );
	$money_match->setAccessible( true );
	$assert( true === $money_match->invoke( $controller, '869.565', '869.57', 1 ), 'Three-decimal POS article totals must allow the valid one-cent line rounding.' );
	$assert( false === $money_match->invoke( $controller, '869.545', '869.57', 1 ), 'Rounding tolerance must not hide a difference above one cent.' );

	echo "RESULT PASS: actual streaming XLSX parser, nine exact-column health assertions detect CNET report, embedded date, merged Article Code data, quantity, Avg Amount, Total Amount, source totals and reconciliation gates.\n";
} finally {
	if ( $zip instanceof ZipArchive ) {
		$zip->close();
	}
	@unlink( $fixture );
}
