<?php
/** Offline regression tests against the extracted 16.1.89 plugin source. */
define( 'ABSPATH', __DIR__ . '/' );
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) { return (string) $value; }
}
$plugin = isset( $argv[1] ) ? rtrim( $argv[1], '/\\' ) : '';
if ( ! is_file( $plugin . '/includes/class-stockland-payroll-statutory.php' ) ) {
	fwrite( STDERR, "Pass extracted plugin path as the first argument.\n" );
	exit( 2 );
}
require_once $plugin . '/includes/class-stockland-money.php';
require_once $plugin . '/includes/class-stockland-payroll-statutory.php';
$passed = 0;
$failed = array();
$check = static function ( $condition, $name ) use ( &$passed, &$failed ) {
	if ( $condition ) { ++$passed; echo "PASS {$name}\n"; }
	else { $failed[] = $name; echo "FAIL {$name}\n"; }
};
$base = array(
	'base_salary' => '30000.00',
	'taxable_allowance_total' => '100.00',
	'exempt_allowance_total' => '0.00',
	'pensionable_salary' => '30000.00',
	'standard_deduction_total' => '0.00',
	'statutory_classification_status' => 'MANAGEMENT_PREPARED',
	'statutory_classification_note' => '',
	'allowance_breakdown_json' => '[{"category":"GENERAL_CASH","amount":"100.00","tax_treatment":"TAXABLE"}]',
);
$check( Stockland_Payroll_Statutory::RULE_VERSION === 'ET-PAYE-1395-2025-v3-PENSION-BASE', 'approved v3 PAYE rule remains selected' );
$result = Stockland_Payroll_Statutory::calculate( '30000.00', '0.00', '0.00', '30000.00', '0.00' );
$check( $result['taxable_income'] === '27900.00' && $result['income_tax'] === '7715.00' && $result['employee_pension'] === '2100.00' && $result['net_pay'] === '20185.00', 'ETB 30,000 pension-first regression is exact' );
$check( '' === Stockland_Payroll_Statutory::compensation_record_validation_error( $base ), 'valid Management-prepared taxable compensation is accepted' );
$cases = array();
$t = $base; $t['exempt_allowance_total'] = '1.00'; $t['allowance_breakdown_json'] = '[{"amount":"100.00","tax_treatment":"TAXABLE"},{"amount":"1.00","tax_treatment":"EXEMPT","owner_verified":true,"legal_basis":"Documented basis.","evidence_reference":"CONTRACT-2026"}]'; $cases[] = array( $t, 'Management cannot prepare an exemption claim' );
$t = $base; $t['pensionable_salary'] = '29000.00'; $cases[] = array( $t, 'Management cannot prepare non-standard pensionable salary' );
$t = $base; $t['statutory_classification_status'] = 'REVIEW_REQUIRED'; $cases[] = array( $t, 'unknown classification fails closed' );
$t = $base; $t['pensionable_salary'] = null; $cases[] = array( $t, 'missing pensionable salary fails closed' );
$t = $base; $t['base_salary'] = '100.00'; $t['pensionable_salary'] = '200.00'; $cases[] = array( $t, 'pensionable salary cannot exceed gross pay' );
$t = $base; $t['standard_deduction_total'] = '50000.00'; $cases[] = array( $t, 'excessive deductions fail closed' );
$t = $base; $t['taxable_allowance_total'] = '150.00'; $cases[] = array( $t, 'allowance snapshot total mismatch fails closed' );
$t = $base; $t['allowance_breakdown_json'] = 'not-json'; $cases[] = array( $t, 'malformed allowance JSON fails closed' );
$t = $base; $t['allowance_breakdown_json'] = '[]'; $cases[] = array( $t, 'empty snapshot cannot conceal taxable allowance totals' );
$t = $base; $t['allowance_breakdown_json'] = '[{"category":"GENERAL_CASH","amount":"100.00","tax_treatment":"OTHER"}]'; $cases[] = array( $t, 'unknown tax treatment fails closed' );
foreach ( $cases as $case ) {
	$check( '' !== Stockland_Payroll_Statutory::compensation_record_validation_error( $case[0] ), $case[1] );
}
$owner = $base;
$owner['statutory_classification_status'] = 'OWNER_CLASSIFIED';
$owner['exempt_allowance_total'] = '100.00';
$owner['statutory_classification_note'] = 'Documented legal/contract basis for this specific allowance.';
$owner['allowance_breakdown_json'] = '[{"category":"GENERAL_CASH","amount":"100.00","tax_treatment":"TAXABLE"},{"category":"OTHER","amount":"100.00","tax_treatment":"EXEMPT","owner_verified":true,"legal_basis":"Current legal basis.","evidence_reference":"CONTRACT-2026"}]';
$check( '' === Stockland_Payroll_Statutory::compensation_record_validation_error( $owner ), 'Owner-evidenced exemption is accepted' );
$owner['allowance_breakdown_json'] = '[{"category":"GENERAL_CASH","amount":"100.00","tax_treatment":"TAXABLE"},{"category":"OTHER","amount":"100.00","tax_treatment":"EXEMPT","owner_verified":false,"legal_basis":"Current legal basis.","evidence_reference":"CONTRACT-2026"}]';
$check( '' !== Stockland_Payroll_Statutory::compensation_record_validation_error( $owner ), 'unverified exemption fails closed' );
$owner['allowance_breakdown_json'] = '[{"category":"GENERAL_CASH","amount":"200.00","tax_treatment":"TAXABLE"}]';
$check( '' !== Stockland_Payroll_Statutory::compensation_record_validation_error( $owner ), 'inconsistent allowance snapshot fails closed' );
$nonstandard = $base;
$nonstandard['statutory_classification_status'] = 'OWNER_CLASSIFIED';
$nonstandard['pensionable_salary'] = '29000.00';
$check( '' !== Stockland_Payroll_Statutory::compensation_record_validation_error( $nonstandard ), 'Owner non-standard pensionability needs a documented basis' );
$nonstandard['statutory_classification_note'] = 'Approved current contract basis for pensionable salary classification.';
$check( '' === Stockland_Payroll_Statutory::compensation_record_validation_error( $nonstandard ), 'Owner non-standard pensionability with a basis is accepted' );
$legacy = $base;
$legacy['allowance_breakdown_json'] = '';
$check( '' === Stockland_Payroll_Statutory::compensation_record_validation_error( $legacy ), 'legacy taxable totals stay compatible with migration policy' );

$rest_app = file_get_contents( $plugin . '/includes/rest/class-rest-app.php' );
$rest_hr = file_get_contents( $plugin . '/includes/rest/class-rest-hr.php' );
$app_js = file_get_contents( $plugin . '/assets/app.js' );
$check( str_contains( $rest_app, 'Stockland_Payroll_Statutory::compensation_record_validation_error( $compensation_row )' ), 'System Health uses shared compensation validator' );
$check( str_contains( $rest_hr, 'Stockland_Payroll_Statutory::compensation_record_validation_error( $c )' ), 'HR compensation path uses shared validator' );
$check( str_contains( $rest_app, "'unresolved_reasons'" ) && str_contains( $app_js, 'sl-payroll-readiness-reasons' ), 'Health shows grouped reasons rather than salary values' );
$check( str_contains( strtolower( $app_js ), 'no employee salary or personal details are exposed here.' ), 'readiness message explicitly protects salary/person data' );
$check( str_contains( $app_js, "payrollOnlyIssue?'OPERATIONAL • PAYROLL CHECK REQUIRED'" ), 'payroll-only blocker is not presented as ERP-wide shutdown' );
echo sprintf( "PAYROLL/HEALTH POLICY: %d passed, %d failed\n", $passed, count( $failed ) );
if ( $failed ) { fwrite( STDERR, implode( "\n", $failed ) . "\n" ); exit( 1 ); }
