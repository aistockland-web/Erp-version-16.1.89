<?php
/**
 * Shipped, Owner-approved Ethiopian payroll rule set (2025 PAYE schedule, 7% / 11% pension): payroll works with nothing to configure.
 * Compares the real calculator against an INDEPENDENT progressive-slice oracle (no credits, no plugin helpers).
 * Disposable site only. Runs after s16/s18 (which approve the HR schema).
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing to run: set STOCKLAND_DISPOSABLE_SITE=yes\n" ); exit( 2 ); }
require_once __DIR__ . '/lib.php';
$fail = 0; $pass = 0;
$ok = function ( $label, $cond, $detail = '' ) use ( &$fail, &$pass ) { if ( $cond ) { ++$pass; echo "PASS $label\n"; } else { ++$fail; echo "FAIL $label" . ( '' !== $detail ? " :: $detail" : '' ) . "\n"; } };
$owner = (int) ( get_users( array( 'role' => 'stockland_owner', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' )[0] ?? 0 ) );
for ( $i = 0; $i < 80 && ! Stockland_HR::is_schema_ready() && $owner; $i++ ) { $r = call( 'POST', '/setup/advance', array( 'approve_hr_schema' => true, 'approval_reason' => 'SYNTHETIC disposable-site Owner approval for release-rule E2E' ), $owner ); if ( $r[0] >= 400 ) { break; } }
$ok( 'HR schema ready and an Owner exists', Stockland_HR::is_schema_ready() && $owner > 0 );
global $wpdb; $t = Stockland_DB::table( 'hr_rule_sets' );
$wpdb->query( "DELETE FROM $t WHERE rule_type='PAYROLL'" );
$r = Stockland_HR_Lifecycle::payroll_rules_for_period( '2026-11-01', '2026-11-30' );
$ok( 'with no rule row payroll stays blocked (fail closed)', is_wp_error( $r ) && 'payroll_rules_unapproved' === $r->get_error_code() );
Stockland_Payroll_Release_Rules::ensure(); Stockland_Payroll_Release_Rules::ensure();
$ok( 'shipped rule is inserted exactly once', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE rule_type='PAYROLL' AND rule_code='et-payroll'" ) );
$row = $wpdb->get_row( "SELECT * FROM $t WHERE rule_code='et-payroll'", ARRAY_A );
$ok( 'rule is APPROVED, effective 2025-07-01, attributed to a real Owner, basis recorded as Owner declaration', 'APPROVED' === $row['review_status'] && '2025-07-01' === $row['effective_from'] && Stockland_Authority::is_owner( (int) $row['approved_by'] ) && false !== strpos( (string) $row['rules_json'], 'OWNER_DECLARATION' ), wp_json_encode( array( $row['review_status'], $row['effective_from'], $row['approved_by'], $owner ) ) );
$ok( 'no independent-adviser claim is made', false === stripos( (string) $row['rules_json'], 'independence_confirmed' ) && false !== strpos( (string) $row['reviewer_note'], 'Owner declaration' ) );
$m = new ReflectionMethod( 'Stockland_REST_HR_Payroll_Cycle', 'legal_rule_bundle' ); $m->setAccessible( true );
$b = $m->invoke( new Stockland_REST_HR_Payroll_Cycle(), '2026-11-01', '2026-11-30' );
$ok( 'the payroll engine accepts the shipped bundle for November 2026', is_array( $b ) && 'APPROVED' === $b['status'], is_wp_error( $b ) ? $b->get_error_message() : '' );
$ok( 'a period before the effective date is still refused', is_wp_error( $m->invoke( new Stockland_REST_HR_Payroll_Cycle(), '2025-05-01', '2025-05-31' ) ) );
/* independent oracle: progressive slices, minor units, half-up */
$slices = array( array( 2000, 0 ), array( 4000, 15 ), array( 7000, 20 ), array( 10000, 25 ), array( 14000, 30 ), array( null, 35 ) );
$oracle = function ( $gross ) use ( $slices ) { $tax = 0; $prev = 0; foreach ( $slices as $s ) { $cap = null === $s[0] ? PHP_INT_MAX : $s[0]; if ( $gross > $prev ) { $tax += ( min( $gross, $cap ) - $prev ) * $s[1]; } $prev = $cap; } return $tax; }; // whole birr x percent = cents of tax
$fmt = function ( $x ) { return number_format( $x, 2, '.', '' ); };
foreach ( array( 1500, 2000, 2500, 4000, 5000, 7000, 8000, 10000, 12000, 14000, 20000, 24000, 100000 ) as $gross ) {
	$items = array( array( 'code' => 'BASE', 'amount' => $fmt( $gross ), 'source_ref' => 'b' ) );
	$c = Stockland_HR_Payroll_Calculator::calculate( array( 'employee_ref' => 'S20', 'period_date' => '2026-11-30', 'items' => $items ), $b )['totals'];
	$tax = $oracle( $gross ) / 100;
	$ok( "PAYE for $gross ETB = " . $fmt( $tax ), $c['payable_income_tax'] === $fmt( $tax ), 'engine ' . $c['payable_income_tax'] );
	$ok( "pension for $gross ETB: 7% employee, 11% employer on basic", $c['employee_pension'] === $fmt( $gross * 0.07 ) && $c['employer_pension'] === $fmt( $gross * 0.11 ) );
}
$c = Stockland_HR_Payroll_Calculator::calculate( array( 'employee_ref' => 'S20', 'period_date' => '2026-11-30', 'items' => array( array( 'code' => 'BASE', 'amount' => '20000.00', 'source_ref' => 'a' ), array( 'code' => 'TAXABLE_ALLOWANCE', 'amount' => '4000.00', 'source_ref' => 'b' ) ) ), $b )['totals'];
$ok( 'taxable allowance is taxed but not pensionable: PAYE 6350, pension 1400/2200, net 16250', '6350.00' === $c['payable_income_tax'] && '1400.00' === $c['employee_pension'] && '2200.00' === $c['employer_pension'] && '16250.00' === $c['net_pay'], wp_json_encode( $c ) );
echo ( $fail ? "RESULT FAIL ($fail failed, $pass passed)\n" : "RESULT PASS ($pass assertions)\n" ); exit( $fail ? 1 : 0 );
