<?php
/**
 * Payroll calculator regression with a SYNTHETIC rule set (not Ethiopian law).
 * The bands, rates and review evidence below are placeholders that exist only to prove the arithmetic,
 * rounding, gating and maker-checker behaviour of the calculator. They are never written to the database.
 * Run: STOCKLAND_DISPOSABLE_SITE=yes wp eval-file scripts/release/e2e/s7_payroll_calculator_vectors.php
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing to run: set STOCKLAND_DISPOSABLE_SITE=yes\n" ); exit( 2 ); }
$fail = 0;
$ok = function ( $label, $cond, $detail = '' ) use ( &$fail ) { echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . ( $cond ? '' : " :: $detail" ) . "\n"; if ( ! $cond ) { ++$fail; } };
$rules = array(
	'status' => 'APPROVED', 'rule_version' => 'SYNTHETIC-V1', 'rule_set_id' => 1, 'approved_by' => 1, 'approved_at' => '2026-01-01 00:00:00',
	'source_url' => 'https://example.invalid/synthetic-test-vector', 'source_sha256' => str_repeat( 'a', 64 ),
	'employee_pension_rate' => '0.05', 'employer_pension_rate' => '0.08', 'tax_base_method' => 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION',
	'monthly_tax_bands' => array(
		array( 'up_to' => '1000.00', 'rate' => '0.00', 'credit' => '0.00' ),
		array( 'up_to' => '5000.00', 'rate' => '0.10', 'credit' => '100.00' ),
		array( 'up_to' => null, 'rate' => '0.20', 'credit' => '600.00' ),
	),
	'qualified_legal_review' => array( 'decision' => 'APPROVED', 'independence_confirmed' => true, 'reviewer_name' => 'Synthetic Reviewer', 'professional_basis' => 'synthetic test vector', 'review_date' => '2026-01-01', 'opinion_reference' => 'SYNTH-0001', 'opinion_sha256' => str_repeat( 'b', 64 ), 'clauses_reviewed' => array( 'synthetic' ), 'approved_parameters' => array( 'paye_bands', 'paye_tax_base_method', 'pensionable_base_method', 'employee_pension_rate', 'employer_pension_rate', 'component_treatments', 'rounding_method' ) ),
);
// Independent oracle in integer cents (no use of the plugin's money class).
$oracle = function ( $base, $taxable, $exempt, $pensionable, $other ) use ( $rules ) {
	$c = fn( $v ) => (int) round( (float) $v * 100 );
	$pct = fn( $cents, $rate ) => (int) round( $cents * (float) $rate );
	$gross = $c( $base ) + $c( $taxable ) + $c( $exempt );
	$ep = $pct( $c( $pensionable ), $rules['employee_pension_rate'] ); $er = $pct( $c( $pensionable ), $rules['employer_pension_rate'] );
	$ti = max( 0, $c( $base ) + $c( $taxable ) - $ep ); $tax = 0;
	foreach ( $rules['monthly_tax_bands'] as $b ) { if ( null === $b['up_to'] || $ti <= $c( $b['up_to'] ) ) { $tax = max( 0, $pct( $ti, $b['rate'] ) - $c( $b['credit'] ) ); break; } }
	return array( 'gross' => $gross, 'ep' => $ep, 'er' => $er, 'tax' => $tax, 'net' => $gross - $tax - $ep - $c( $other ) );
};
$vectors = array( array( '800.00', '0.00', '0.00', '800.00', '0.00' ), array( '3000.00', '500.00', '200.00', '3000.00', '50.00' ), array( '12000.00', '1500.00', '0.00', '12000.00', '0.00' ), array( '100.00', '0.00', '0.00', '100.00', '0.00' ), array( '5263.16', '0.00', '0.00', '5263.16', '10.33' ) );
foreach ( $vectors as $i => $v ) {
	$r = Stockland_Payroll_Statutory::calculate( $v[0], $v[1], $v[2], $v[3], $v[4], $rules ); $o = $oracle( ...$v );
	$got = array( 'gross' => Stockland_Money::minor( $r['gross_pay'] ?? $r['gross_total'] ?? '0' ), 'net' => Stockland_Money::minor( $r['net_pay'] ?? $r['net_total'] ?? '0' ), 'tax' => Stockland_Money::minor( $r['income_tax'] ?? $r['income_tax_total'] ?? '0' ), 'ep' => Stockland_Money::minor( $r['employee_pension'] ?? '0' ), 'er' => Stockland_Money::minor( $r['employer_pension'] ?? '0' ) );
	foreach ( $o as $k => $exp ) { $ok( "vector $i $k", (int) $got[ $k ] === (int) $exp, "plugin={$got[$k]} oracle=$exp" ); }
	$ok( "vector $i net reconciles", (int) $got['net'] === (int) $got['gross'] - (int) $got['tax'] - (int) $got['ep'] - (int) Stockland_Money::minor( $v[4] ) );
}
$expect_refusal = function ( $label, $mutate ) use ( $rules, $ok ) { $r = $mutate( $rules ); try { Stockland_Payroll_Statutory::calculate( '3000.00', '0.00', '0.00', '3000.00', '0.00', $r ); $ok( $label, false, 'calculation was not refused' ); } catch ( Throwable $e ) { $ok( $label, true ); } };
$expect_refusal( 'refuses without any rule set', fn( $r ) => null );
$expect_refusal( 'refuses a DRAFT rule set', function ( $r ) { $r['status'] = 'DRAFT'; return $r; } );
$expect_refusal( 'refuses missing source hash', function ( $r ) { $r['source_sha256'] = ''; return $r; } );
$expect_refusal( 'refuses missing legal review', function ( $r ) { unset( $r['qualified_legal_review'] ); return $r; } );
$expect_refusal( 'refuses review without independence', function ( $r ) { $r['qualified_legal_review']['independence_confirmed'] = false; return $r; } );
$expect_refusal( 'refuses a pension rate above 100%', function ( $r ) { $r['employee_pension_rate'] = '1.5'; return $r; } );
$expect_refusal( 'refuses unsupported tax base method', function ( $r ) { $r['tax_base_method'] = 'GUESS'; return $r; } );
try { Stockland_Payroll_Statutory::calculate( '100.00', '0.00', '0.00', '500.00', '0.00', $rules ); $ok( 'refuses pensionable above gross', false ); } catch ( Throwable $e ) { $ok( 'refuses pensionable above gross', true ); }
try { Stockland_Payroll_Statutory::calculate( '100.00', '0.00', '0.00', '100.00', '900.00', $rules ); $ok( 'refuses deductions above gross', false ); } catch ( Throwable $e ) { $ok( 'refuses deductions above gross', true ); }
echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n"; if ( $fail ) { exit( 1 ); }
