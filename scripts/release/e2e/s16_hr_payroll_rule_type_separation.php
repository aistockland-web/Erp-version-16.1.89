<?php
/**
 * Typed payroll legal-review scope regression.
 * Review evidence and rule identifiers are synthetic fixtures only, not Ethiopian legal sources.
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) {
	fwrite( STDERR, "Refusing to run outside a disposable site.\n" );
	exit( 2 );
}
$fail = 0;
$check = static function ( $label, $condition ) use ( &$fail ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $condition ) { ++$fail; }
};
$common = array(
	'decision' => 'APPROVED',
	'independence_confirmed' => true,
	'reviewer_name' => 'Synthetic Reviewer',
	'reviewer_id' => 'SYNTH-REVIEWER-0001',
	'professional_basis' => 'Synthetic qualification fixture only',
	'review_date' => '2026-01-01',
	'opinion_reference' => 'SYNTHETIC-OPINION-0001',
	'opinion_sha256' => hash( 'sha256', 'synthetic reviewer opinion only' ),
	'clauses_reviewed' => array( 'SYNTHETIC-CLAUSE' ),
);
$legacy = array( 'qualified_legal_review' => $common + array(
	'approved_parameters' => array( 'monthly_tax_bands', 'tax_base_method', 'employee_pension_rate', 'employer_pension_rate' ),
) );
$cycle = array( 'qualified_legal_review' => $common + array(
	'approved_parameters' => array( 'paye_bands', 'paye_tax_base_method', 'pensionable_base_method', 'employee_pension_rate', 'employer_pension_rate', 'component_treatments', 'rounding_method' ),
) );
$check( 'legacy PAYROLL review accepts the legacy parameter scope', Stockland_HR_Lifecycle::has_qualified_legacy_payroll_review( $legacy ) );
$check( 'legacy PAYROLL review cannot authorize PAYROLL_CYCLE', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $legacy ) );
$check( 'PAYROLL_CYCLE review accepts the frozen-cycle parameter scope', Stockland_HR_Lifecycle::has_qualified_legal_review( $cycle ) );
$check( 'PAYROLL_CYCLE review cannot authorize legacy PAYROLL', ! Stockland_HR_Lifecycle::has_qualified_legacy_payroll_review( $cycle ) );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
exit( $fail ? 1 : 0 );
