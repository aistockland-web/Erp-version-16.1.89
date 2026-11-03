<?php
/**
 * Deterministic POS / money / VAT regression fixtures.
 *
 * Synthetic-only. Loads the exact candidate money and sale-math source and
 * never opens WordPress, connects to a database, creates sale rows or posts GL.
 */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! isset( $argv[1] ) || ! is_dir( $argv[1] ) ) {
    fwrite( STDERR, "Usage: php test_pos_finance_inventory_16_2_4.php <extracted-plugin-root>\n" );
    exit( 2 );
}
$root = rtrim( $argv[1], '/\\' );
require_once $root . '/includes/class-stockland-money.php';
require_once $root . '/includes/class-stockland-sale-math.php';

function require_equal( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAIL {$message}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
    echo "PASS {$message}\n";
}
function require_throws( $callback, $message ) {
    try {
        $callback();
    } catch ( InvalidArgumentException $e ) {
        echo "PASS {$message}\n";
        return;
    }
    fwrite( STDERR, "FAIL {$message}: expected InvalidArgumentException\n" );
    exit( 1 );
}

/* VAT included, 2.5 units at ETB 115.00, 15% VAT. */
$included = Stockland_Sale_Math::calculate( '2.5', '115.00', 'VAT_INCLUDED', '0.15' );
require_equal( '287.50', $included['sales_total'], 'inclusive VAT: quantity × unit price is gross total' );
require_equal( '37.50', $included['vat_amount'], 'inclusive VAT: VAT extracted from gross' );
require_equal( '250.00', $included['net_sales'], 'inclusive VAT: gross reconciles to net + VAT' );

/* VAT excluded, same net base and 15% VAT. */
$excluded = Stockland_Sale_Math::calculate( '2.5', '100.00', 'WITHOUT_VAT', '0.15' );
require_equal( '250.00', $excluded['net_sales'], 'exclusive VAT: net subtotal remains untaxed base' );
require_equal( '37.50', $excluded['vat_amount'], 'exclusive VAT: VAT is calculated on net subtotal' );
require_equal( '287.50', $excluded['sales_total'], 'exclusive VAT: gross reconciles to net + VAT' );

/* Cent-rounding boundaries at the system's half-away-from-zero minor-unit rule. */
$small_included = Stockland_Sale_Math::calculate( '1', '0.05', 'VAT_INCLUDED', '0.15' );
require_equal( '0.01', $small_included['vat_amount'], 'inclusive VAT rounds the 0.05 ETB edge to one cent VAT' );
require_equal( '0.04', $small_included['net_sales'], 'inclusive VAT edge reconciles after cent rounding' );
$small_excluded = Stockland_Sale_Math::calculate( '1', '0.05', 'WITHOUT_VAT', '0.15' );
require_equal( '0.01', $small_excluded['vat_amount'], 'exclusive VAT rounds the 0.05 ETB edge to one cent VAT' );
require_equal( '0.06', $small_excluded['sales_total'], 'exclusive VAT edge reconciles after cent rounding' );

/* COGS and illustrative order-to-cash journal equality, independently asserted. */
$cogs = Stockland_Money::multiply( '2.5', '60.00' );
$gross_profit = Stockland_Money::subtract( $included['net_sales'], $cogs );
require_equal( '150.00', $cogs, 'quantity × staged unit cost calculates COGS' );
require_equal( '100.00', $gross_profit, 'gross profit uses net sales less staged COGS' );
$debits = Stockland_Money::add( $included['sales_total'], $cogs );
$credits = Stockland_Money::add( $included['net_sales'], $included['vat_amount'] );
$credits = Stockland_Money::add( $credits, $cogs );
require_equal( '437.50', $debits, 'illustrative sale + COGS journal debit total' );
require_equal( $debits, $credits, 'illustrative sale + COGS journal is balanced to the cent' );

/* Guard rails: no zero/negative quantity, zero/negative price or invalid VAT rate. */
require_throws( function () { Stockland_Sale_Math::calculate( '0', '10.00', 'VAT_INCLUDED', '0.15' ); }, 'zero quantity rejected' );
require_throws( function () { Stockland_Sale_Math::calculate( '-1', '10.00', 'VAT_INCLUDED', '0.15' ); }, 'negative quantity rejected' );
require_throws( function () { Stockland_Sale_Math::calculate( '1', '0.00', 'VAT_INCLUDED', '0.15' ); }, 'zero selling price rejected' );
require_throws( function () { Stockland_Sale_Math::calculate( '1', '10.00', 'VAT_INCLUDED', '1.01' ); }, 'VAT rate above 100 percent rejected' );

echo "PASS all deterministic POS/VAT/money fixtures (synthetic-only; no database writes)\n";
