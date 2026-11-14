<?php
/**
 * Payroll calculator regression with a SYNTHETIC rule set (not Ethiopian law).
 * The bands, rates and review evidence below are placeholders that exist only to prove the arithmetic,
 * rounding, gating and independent-review checks. They are never written to the database.
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
	'qualified_legal_review' => array( 'decision' => 'APPROVED', 'independence_confirmed' => true, 'reviewer_name' => 'Synthetic Reviewer', 'reviewer_id' => 'SYNTH-REVIEWER-0001', 'professional_basis' => 'synthetic test vector', 'review_date' => '2026-01-01', 'opinion_reference' => 'SYNTH-0001', 'opinion_sha256' => str_repeat( 'b', 64 ), 'clauses_reviewed' => array( 'synthetic' ), 'approved_parameters' => array( 'monthly_tax_bands', 'tax_base_method', 'employee_pension_rate', 'employer_pension_rate' ) ),
);
// Independent exact oracle: decimal strings and integer minor units only (no plugin money helpers or floats).
$to_minor = static function ( $value ) {
	$text = trim( (string) $value );
	if ( ! preg_match( '/^(\d+)(?:\.(\d{1,2}))?$/D', $text, $matches ) ) {
		throw new RuntimeException( 'Invalid synthetic oracle amount.' );
	}
	$fraction = str_pad( $matches[2] ?? '', 2, '0' );
	return ( (int) $matches[1] * 100 ) + (int) $fraction;
};
$percent_minor = static function ( $minor, $rate ) {
	if ( ! preg_match( '/^(\d+)(?:\.(\d+))?$/D', (string) $rate, $matches ) ) {
		throw new RuntimeException( 'Invalid synthetic oracle rate.' );
	}
	$fraction = $matches[2] ?? '';
	$scale = 1;
	for ( $i = 0, $length = strlen( $fraction ); $i < $length; ++$i ) {
		$scale *= 10;
	}
	$numerator = ( (int) $matches[1] * $scale ) + ( '' === $fraction ? 0 : (int) $fraction );
	$product = (int) $minor * $numerator;
	return intdiv( $product + intdiv( $scale, 2 ), $scale ); // non-negative, half-up / half-away-from-zero.
};
$oracle = function ( $base, $taxable, $exempt, $pensionable, $other ) use ( $rules, $to_minor, $percent_minor ) {
	$gross = $to_minor( $base ) + $to_minor( $taxable ) + $to_minor( $exempt );
	$employee_pension = $percent_minor( $to_minor( $pensionable ), $rules['employee_pension_rate'] );
	$employer_pension = $percent_minor( $to_minor( $pensionable ), $rules['employer_pension_rate'] );
	$taxable_income = max( 0, $to_minor( $base ) + $to_minor( $taxable ) - $employee_pension );
	$tax = 0;
	foreach ( $rules['monthly_tax_bands'] as $band ) {
		if ( null === $band['up_to'] || $taxable_income <= $to_minor( $band['up_to'] ) ) {
			$tax = max( 0, $percent_minor( $taxable_income, $band['rate'] ) - $to_minor( $band['credit'] ) );
			break;
		}
	}
	return array(
		'gross' => $gross,
		'ep' => $employee_pension,
		'er' => $employer_pension,
		'tax' => $tax,
		'net' => $gross - $tax - $employee_pension - $to_minor( $other ),
	);
};
$vectors = array( array( '800.00', '0.00', '0.00', '800.00', '0.00' ), array( '3000.00', '500.00', '200.00', '3000.00', '50.00' ), array( '12000.00', '1500.00', '0.00', '12000.00', '0.00' ), array( '100.00', '0.00', '0.00', '100.00', '0.00' ), array( '5263.16', '0.00', '0.00', '5263.16', '10.33' ) );
foreach ( $vectors as $i => $v ) {
	$r = Stockland_Payroll_Statutory::calculate( $v[0], $v[1], $v[2], $v[3], $v[4], $rules ); $o = $oracle( ...$v );
	$got = array( 'gross' => Stockland_Money::minor( $r['gross_pay'] ?? $r['gross_total'] ?? '0' ), 'net' => Stockland_Money::minor( $r['net_pay'] ?? $r['net_total'] ?? '0' ), 'tax' => Stockland_Money::minor( $r['income_tax'] ?? $r['income_tax_total'] ?? '0' ), 'ep' => Stockland_Money::minor( $r['employee_pension'] ?? '0' ), 'er' => Stockland_Money::minor( $r['employer_pension'] ?? '0' ) );
	foreach ( $o as $k => $exp ) { $ok( "vector $i $k", (int) $got[ $k ] === (int) $exp, "plugin={$got[$k]} oracle=$exp" ); }
	$ok( "vector $i net reconciles", (int) $got['net'] === (int) $got['gross'] - (int) $got['tax'] - (int) $got['ep'] - (int) $to_minor( $v[4] ) );
}
$expect_invalid_argument = static function ( $label, $operation ) use ( $ok ) {
	try {
		$operation();
		$ok( $label, false, 'calculation was not refused' );
	} catch ( InvalidArgumentException $e ) {
		$ok( $label, true );
	} catch ( Throwable $e ) {
		$ok( $label, false, 'unexpected exception type ' . get_class( $e ) . ': ' . $e->getMessage() );
	}
};
$expect_refusal = static function ( $label, $mutate ) use ( $rules, $expect_invalid_argument ) {
	$rule_set = $mutate( $rules );
	$expect_invalid_argument( $label, static function () use ( $rule_set ) {
		Stockland_Payroll_Statutory::calculate( '3000.00', '0.00', '0.00', '3000.00', '0.00', $rule_set );
	} );
};
$expect_refusal( 'refuses without any rule set', static fn( $r ) => null );
$expect_refusal( 'refuses a DRAFT rule set', static function ( $r ) { $r['status'] = 'DRAFT'; return $r; } );
$expect_refusal( 'refuses missing source hash', static function ( $r ) { $r['source_sha256'] = ''; return $r; } );
$expect_refusal( 'refuses missing legal review', static function ( $r ) { unset( $r['qualified_legal_review'] ); return $r; } );
$expect_refusal( 'refuses review without independence', static function ( $r ) { $r['qualified_legal_review']['independence_confirmed'] = false; return $r; } );
$expect_refusal( 'refuses a pension rate above 100%', static function ( $r ) { $r['employee_pension_rate'] = '1.5'; return $r; } );
$expect_refusal( 'refuses unsupported tax base method', static function ( $r ) { $r['tax_base_method'] = 'GUESS'; return $r; } );
$expect_invalid_argument( 'refuses pensionable above gross', static function () use ( $rules ) {
	Stockland_Payroll_Statutory::calculate( '100.00', '0.00', '0.00', '500.00', '0.00', $rules );
} );
$expect_invalid_argument( 'refuses deductions above gross', static function () use ( $rules ) {
	Stockland_Payroll_Statutory::calculate( '100.00', '0.00', '0.00', '100.00', '900.00', $rules );
} );
echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n"; if ( $fail ) { exit( 1 ); }
