<?php
/** Standalone regression tests for the bounded Daily POS CSV adapter. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
$reader_file = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../runtime/class-stockland-csv.php';
if ( ! is_file( $reader_file ) ) {
	fwrite( STDERR, "CSV reader file does not exist.\n" );
	exit( 2 );
}
require_once $reader_file;

$passed = 0;
function assert_csv_true( $condition, $message ) {
	global $passed;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: " . $message . "\n" );
		exit( 1 );
	}
	++$passed;
}
function assert_csv_throws( $callback, $message, $contains = '' ) {
	try {
		$callback();
	} catch ( Throwable $error ) {
		if ( '' !== $contains && false === strpos( $error->getMessage(), $contains ) ) {
			fwrite( STDERR, 'FAIL: ' . $message . ' (unexpected error: ' . $error->getMessage() . ")\n" );
			exit( 1 );
		}
		assert_csv_true( true, $message );
		return;
	}
	assert_csv_true( false, $message . ' (no exception thrown)' );
}
function csv_test_file( $contents ) {
	$path = tempnam( sys_get_temp_dir(), 'stockland-csv-' );
	if ( false === $path ) {
		throw new RuntimeException( 'Could not create CSV test file.' );
	}
	file_put_contents( $path, $contents );
	return $path;
}
function csv_test_rows( $reader, $limit = Stockland_CSV::MAX_PHYSICAL_ROW_NODES ) {
	return iterator_to_array( $reader->stream_rows( 'CSV', $limit ), false );
}

$tests = array(
	'UTF-8 BOM, commas, escaped quotes, comma-containing text and multiline fields' => function () {
		$path = csv_test_file( "\xEF\xBB\xBFSKU,Product,Qty,Receipt\r\nSTK-1,\"Dress, blue\",2,RC-1\r\nSTK-2,\"Top \"\"Classic\"\"\",1,RC-2\r\nSTK-3,\"Line one\nLine two\",1,RC-3\r\n" );
		try {
			$reader = new Stockland_CSV( $path );
			$rows = csv_test_rows( $reader );
			assert_csv_true( $reader->sheet_names() === array( 'CSV' ), 'CSV exposes the single logical sheet used by existing mapping' );
			assert_csv_true( $rows[0]['row_number'] === 1 && $rows[0]['values'][0] === 'SKU', 'UTF-8 BOM is removed from first header only' );
			assert_csv_true( $rows[1]['values'][1] === 'Dress, blue', 'Quoted commas remain inside a field' );
			assert_csv_true( $rows[2]['values'][1] === 'Top "Classic"', 'Escaped quote pairs are decoded' );
			assert_csv_true( $rows[3]['values'][1] === "Line one\nLine two", 'Quoted multiline text is preserved as one logical record' );
		} finally { unlink( $path ); }
	},
	'semicolon delimiter' => function () {
		$path = csv_test_file( "SKU;Qty;Price\nSTK-1;3;19.95\n" );
		try { $rows = csv_test_rows( new Stockland_CSV( $path ) ); assert_csv_true( $rows[1]['values'] === array( 'STK-1', '3', '19.95' ), 'Semicolon-delimited export is parsed' ); } finally { unlink( $path ); }
	},
	'tab delimiter' => function () {
		$path = csv_test_file( "SKU\tQty\tPrice\nSTK-1\t3\t19.95\n" );
		try { $rows = csv_test_rows( new Stockland_CSV( $path ) ); assert_csv_true( count( $rows[1]['values'] ) === 3, 'Tab-delimited export is parsed' ); } finally { unlink( $path ); }
	},
	'pipe delimiter' => function () {
		$path = csv_test_file( "SKU|Qty|Price\nSTK-1|3|19.95\n" );
		try { $rows = csv_test_rows( new Stockland_CSV( $path ) ); assert_csv_true( $rows[1]['values'][0] === 'STK-1', 'Pipe-delimited export is parsed' ); } finally { unlink( $path ); }
	},
	'reject single-column file' => function () {
		$path = csv_test_file( "SKU only\nSTK-1\n" );
		try { assert_csv_throws( function () use ( $path ) { new Stockland_CSV( $path ); }, 'Single-column file is rejected', 'delimiter' ); } finally { unlink( $path ); }
	},
	'reject excessive columns' => function () {
		$path = csv_test_file( implode( ',', array_fill( 0, 251, 'x' ) ) . "\n" );
		try { $reader = new Stockland_CSV( $path ); assert_csv_throws( function () use ( $reader ) { csv_test_rows( $reader ); }, 'Rows beyond 250 columns are rejected', 'too many columns' ); } finally { unlink( $path ); }
	},
	'reject oversized field' => function () {
		$path = csv_test_file( "A,B\n\"" . str_repeat( 'x', 65537 ) . "\",1\n" );
		try { $reader = new Stockland_CSV( $path ); assert_csv_throws( function () use ( $reader ) { csv_test_rows( $reader ); }, 'Cells above 64 KB are rejected', '64 KB' ); } finally { unlink( $path ); }
	},
	'reject NUL bytes' => function () {
		$path = csv_test_file( "A,B\nSTK-1,abc\0def\n" );
		try { $reader = new Stockland_CSV( $path ); assert_csv_throws( function () use ( $reader ) { csv_test_rows( $reader ); }, 'Binary NUL byte is rejected', 'NUL' ); } finally { unlink( $path ); }
	},
	'reject invalid UTF-8' => function () {
		$path = csv_test_file( "A,B\nSTK-1,\xC3\x28\n" );
		try { $reader = new Stockland_CSV( $path ); assert_csv_throws( function () use ( $reader ) { csv_test_rows( $reader ); }, 'Invalid UTF-8 is rejected', 'UTF-8' ); } finally { unlink( $path ); }
	},
	'respect requested row cap' => function () {
		$path = csv_test_file( "A,B\n1,2\n3,4\n5,6\n" );
		try { $reader = new Stockland_CSV( $path ); assert_csv_throws( function () use ( $reader ) { csv_test_rows( $reader, 3 ); }, 'Reader stops after the requested physical-record cap', 'row safety limit' ); } finally { unlink( $path ); }
	},
	'reject empty file' => function () {
		$path = csv_test_file( '' );
		try { assert_csv_throws( function () use ( $path ) { new Stockland_CSV( $path ); }, 'Empty CSV is rejected', 'empty' ); } finally { unlink( $path ); }
	},
);

foreach ( $tests as $label => $test ) {
	$test();
	fwrite( STDOUT, "PASS: " . $label . "\n" );
}
fwrite( STDOUT, 'CSV reader regression: ' . $passed . " assertions passed.\n" );