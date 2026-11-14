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

$allowance = array( 'qualified_legal_review' => $common + array(
	'approved_parameters' => array( 'allowance_tax_treatment', 'allowance_exemption_cap', 'allowance_pensionable_treatment', 'allowance_pensionable_cap' ),
) );
$settlement = array( 'qualified_legal_review' => $common + array(
	'approved_parameters' => array( 'component_rules' ),
) );
$payee = array(
	'withholding_method' => 'RATE_ON_GROSS_CASH',
	'qualified_legal_review' => $common + array(
		'approved_parameters' => array( 'withholding_method', 'cash_in_kind_tax_basis', 'withholding_rate' ),
	),
);
$no_withholding_payee = array(
	'withholding_method' => 'NO_WITHHOLDING',
	'qualified_legal_review' => $common + array(
		'approved_parameters' => array( 'withholding_method', 'cash_in_kind_tax_basis' ),
	),
);
$check( 'ALLOWANCE review accepts allowance component parameter scope', Stockland_HR_Lifecycle::has_qualified_allowance_review( $allowance ) );
$check( 'ALLOWANCE review cannot authorize PAYROLL_CYCLE', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $allowance ) );
$check( 'FINAL_SETTLEMENT review accepts component-rule scope', Stockland_HR_Lifecycle::has_qualified_final_settlement_review( $settlement ) );
$check( 'FINAL_SETTLEMENT review cannot authorize PAYROLL_CYCLE', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $settlement ) );
$check( 'INDIVIDUAL_PAYEE review accepts withholding and cash/in-kind scope', Stockland_HR_Lifecycle::has_qualified_individual_payee_review( $payee ) );
$check( 'INDIVIDUAL_PAYEE requires signed rate when withholding applies', ! Stockland_HR_Lifecycle::has_qualified_individual_payee_review( array(
	'withholding_method' => 'RATE_ON_GROSS_CASH',
	'qualified_legal_review' => $common + array( 'approved_parameters' => array( 'withholding_method', 'cash_in_kind_tax_basis' ) ),
) ) );
$check( 'NO_WITHHOLDING review does not require a rate', Stockland_HR_Lifecycle::has_qualified_individual_payee_review( $no_withholding_payee ) );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
exit( $fail ? 1 : 0 );
