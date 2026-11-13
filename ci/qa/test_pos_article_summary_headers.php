<?php
/** Exact-column regression for the supplier's "Summarized By Article" XLSX layout. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
$source = $argv[1] ?? __DIR__ . '/../../src/stockland-erp-app/includes/rest/class-rest-import-v16.php';
if ( ! is_file( $source ) ) {
    fwrite( STDERR, "Import controller source not found.\n" );
    exit( 2 );
}
require_once $source;

$controller = ( new ReflectionClass( 'Stockland_REST_Import_V16' ) )->newInstanceWithoutConstructor();
function pos_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
}
function invoke_private( $object, $method, ...$arguments ) {
    $reflection = new ReflectionMethod( $object, $method );
    $reflection->setAccessible( true );
    return $reflection->invoke( $object, ...$arguments );
}

$headers = array(
    0 => 'Article Code', 1 => '', 2 => 'Article Name', 3 => '',
    4 => 'Quantity', 5 => '', 6 => '', 7 => '', 8 => 'Avg.  Amount',
    9 => '', 10 => '', 11 => 'Total  Amount',
);
$aligned = invoke_private( $controller, 'article_summary_header_positions', $headers );
pos_assert( ! isset( $aligned[0] ), 'only the displaced Article Code label leaves column A' );
pos_assert( $aligned[1] === 'Article Code', 'Article Code label is aligned with SKU data in column B' );
pos_assert( $aligned[2] === 'Article Name', 'Article Name remains in column C' );
pos_assert( $aligned[4] === 'Quantity', 'Quantity remains in column E' );
pos_assert( $aligned[8] === 'Avg.  Amount', 'Average amount remains in column I' );
pos_assert( $aligned[11] === 'Total  Amount', 'Total amount remains in column L' );

$values = array(
    1 => 'STK-001', 2 => 'COLABABY', 4 => '1',
    8 => '869.57', 11 => '869.565',
);
$row = invoke_private( $controller, 'stream_assoc_row', $values, $aligned );
$auto = invoke_private( $controller, 'auto_map_headers', $aligned, 'DAILY_SALES' );
$map = $auto['mapping'];
pos_assert( ($map['sku'] ?? null) === 'Article Code', 'automatic SKU mapping selects Article Code' );
pos_assert( ($map['qty'] ?? null) === 'Quantity', 'automatic quantity mapping selects Quantity' );
pos_assert( ($map['selling_price'] ?? null) === 'Avg.  Amount', 'automatic selling-price mapping selects Avg. Amount' );
pos_assert( ($map['line_total'] ?? null) === 'Total  Amount', 'automatic line-total mapping selects Total Amount' );
pos_assert( ($row[$map['sku']] ?? null) === 'STK-001', 'real source SKU value is read from column B' );
pos_assert( ($row[$map['qty']] ?? null) === '1', 'real quantity is read from column E' );
pos_assert( ($row[$map['selling_price']] ?? null) === '869.57', 'average amount is read from column I' );
pos_assert( ($row[$map['line_total']] ?? null) === '869.565', 'line total is read from column L' );

$alreadyAligned = $headers;
$alreadyAligned[0] = '';
$alreadyAligned[1] = 'Article Code';
$normal = invoke_private( $controller, 'article_summary_header_positions', $alreadyAligned );
pos_assert( $normal === $alreadyAligned, 'normal exports with Article Code already in column B are not shifted' );

$conflicting = $headers;
$conflicting[1] = 'Unexpected Header';
$thrown = false;
try {
    invoke_private( $controller, 'article_summary_header_positions', $conflicting );
} catch ( Throwable $error ) {
    $thrown = str_contains( $error->getMessage(), 'column B must be blank' );
}
pos_assert( $thrown, 'conflicting column B header fails closed instead of overwriting source data' );

$self_test = Stockland_REST_Import_V16::article_summary_layout_self_test();
pos_assert( ! empty( $self_test['ok'] ), 'Deep System Health self-test executes the real article-summary mapping contract' );
pos_assert( 9 === count( $self_test['checks'] ) && ! in_array( false, $self_test['checks'], true ), 'all nine source-column and field-mapping assertions pass in Health' );

echo "POS article-summary layout regression: PASS (18 assertions).\n";
