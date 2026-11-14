<?php
/**
 * HR/payroll calculator contract regression using SYNTHETIC review evidence and amounts only.
 *
 * This does not encode Ethiopian tax/pension law, write a rule set, approve a real rule,
 * create employee records, post a journal, or enable production payroll.
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) {
	fwrite( STDERR, "Refusing to run outside a disposable site.\n" );
	exit( 2 );
}

$fail = 0;
$check = static function ( $label, $condition, $detail = '' ) use ( &$fail ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . ( $condition ? '' : ' :: ' . $detail ) . "\n";
	if ( ! $condition ) {
		++$fail;
	}
};

$review = array(
	'decision'              => 'APPROVED',
	'independence_confirmed'=> true,
	'reviewer_name'         => 'Synthetic Reviewer — TEST ONLY',
	'reviewer_id'           => 'SYNTH-REVIEWER-0001',
	'professional_basis'    => 'Synthetic reviewer qualification fixture only',
	'review_date'           => '2026-01-02',
	'opinion_reference'     => 'SYNTHETIC-OPINION-ONLY-0001',
	'opinion_sha256'        => hash( 'sha256', 'synthetic reviewer opinion fixture; not a legal opinion' ),
	'clauses_reviewed'      => array( 'SYNTHETIC-CLAUSE-ONLY' ),
	'approved_parameters'  => array(
		'paye_bands',
		'paye_tax_base_method',
		'pensionable_base_method',
		'employee_pension_rate',
		'employer_pension_rate',
		'component_treatments',
		'rounding_method',
		'monthly_tax_bands',
		'tax_base_method',
	),
);
$payload = array( 'qualified_legal_review' => $review );
$check( 'synthetic review payload is recognized when it has a reviewer identifier', Stockland_HR_Lifecycle::has_qualified_legal_review( $payload ) );
$missing_identifier = $payload;
unset( $missing_identifier['qualified_legal_review']['reviewer_id'] );
$check( 'review payload without reviewer identifier is rejected', ! Stockland_HR_Lifecycle::has_qualified_legal_review( $missing_identifier ) );

$rules = array(
	'status'                    => 'APPROVED',
	'rule_version'              => 'SYNTHETIC-HR-PAYROLL-V1',
	'rule_set_id'               => 999999,
	'jurisdiction'              => 'SYNTHETIC-TEST-ONLY',
	'effective_from'            => '2026-01-01',
	'effective_to'              => null,
	'source_sha256'             => hash( 'sha256', 'synthetic source only; not law' ),
	'reviewer_id'               => 'SYNTH-REVIEWER-0001',
	'approved_at'               => '2026-01-03 00:00:00',
	'approved_by'               => 999998,
	'official_source_url'       => 'https://example.invalid/synthetic-only',
	'source_url'                => 'https://example.invalid/synthetic-only',
	'approval_evidence_ref'     => 'SYNTHETIC-OPINION-ONLY-0001',
	'paye_tax_base_method'      => 'TAXABLE_EARNINGS_LESS_EMPLOYEE_PENSION',
	'tax_base_method'           => 'TAXABLE_GROSS_LESS_EMPLOYEE_PENSION',
	'monthly_tax_bands'         => array(
		array( 'up_to' => '1000.00', 'rate' => '0.00', 'credit' => '0.00' ),
		array( 'up_to' => '3000.00', 'rate' => '0.10', 'credit' => '100.00' ),
		array( 'up_to' => null, 'rate' => '0.20', 'credit' => '400.00' ),
	),
	'paye_bands'                => array(
		array( 'up_to' => '1000.00', 'rate' => '0.00', 'credit' => '0.00' ),
		array( 'up_to' => '3000.00', 'rate' => '0.10', 'credit' => '100.00' ),
		array( 'up_to' => null, 'rate' => '0.20', 'credit' => '400.00' ),
	),
	'employee_pension_rate'     => '0.05',
	'employer_pension_rate'     => '0.08',
	'pensionable_base_method'   => 'SUM_PENSIONABLE_ITEMS',
	'component_treatments'      => array(
		'BASE' => array( 'kind' => 'EARNING', 'taxable' => true, 'pensionable' => true ),
		'OTHER_DEDUCTION' => array( 'kind' => 'DEDUCTION', 'taxable' => false, 'pensionable' => false ),
	),
	'rounding_method'           => 'HALF_UP_MINOR_UNIT',
	'qualified_legal_review'    => $review,
);

$check( 'synthetic rule passes both calculator contract checks', Stockland_HR_Lifecycle::has_payroll_calculator_contract( $rules ) );
$bad_base_map = $rules;
$bad_base_map['paye_tax_base_method'] = 'TAXABLE_EARNINGS';
$check( 'dual contract rejects conflicting legacy and frozen-run PAYE bases', ! Stockland_HR_Lifecycle::has_payroll_calculator_contract( $bad_base_map ) );
$bad_band_map = $rules;
$bad_band_map['paye_bands'][1]['rate'] = '0.15';
$check( 'dual contract rejects different tax rates in the two band schedules', ! Stockland_HR_Lifecycle::has_payroll_calculator_contract( $bad_band_map ) );
$bad_credit_map = $rules;
$bad_credit_map['paye_bands'][2]['credit'] = '400.01';
$check( 'dual contract rejects different tax credits between the two schedules', ! Stockland_HR_Lifecycle::has_payroll_calculator_contract( $bad_credit_map ) );
$discontinuous_credit_map = $rules;
$discontinuous_credit_map['monthly_tax_bands'][2]['credit'] = '399.99';
$discontinuous_credit_map['paye_bands'][2]['credit'] = '399.99';
$check( 'dual contract rejects a credit that breaks continuity at a bracket boundary', ! Stockland_HR_Lifecycle::has_payroll_calculator_contract( $discontinuous_credit_map ) );
$missing_legacy_signoff = $rules;
$missing_legacy_signoff['qualified_legal_review']['approved_parameters'] = array_values( array_diff( $review['approved_parameters'], array( 'monthly_tax_bands' ) ) );
$check( 'dual contract rejects a legacy schedule omitted from reviewer scope', ! Stockland_HR_Lifecycle::has_payroll_calculator_contract( $missing_legacy_signoff ) );

$result = Stockland_HR_Payroll_Calculator::calculate(
	array(
		'employee_ref' => 'SYNTH-EMPLOYEE-0001',
		'period_date'  => '2026-01-31',
		'items'        => array(
			array( 'code' => 'BASE', 'amount' => '1500.00', 'source_ref' => 'SYNTH-COMP-0001' ),
			array( 'code' => 'OTHER_DEDUCTION', 'amount' => '50.00', 'source_ref' => 'SYNTH-DEDUCT-0001' ),
		),
	),
	$rules
);

$legacy_result = Stockland_Payroll_Statutory::calculate( '1500.00', '0.00', '0.00', '1500.00', '50.00', $rules );
$check( 'legacy and frozen-run engines reconcile PAYE for a synthetic period', $legacy_result['income_tax'] === $result['totals']['payable_income_tax'] );
$check( 'legacy and frozen-run engines reconcile employee pension', $legacy_result['employee_pension'] === $result['totals']['employee_pension'] );
$check( 'legacy and frozen-run engines reconcile employer pension', $legacy_result['employer_pension'] === $result['totals']['employer_pension'] );
$check( 'legacy and frozen-run engines reconcile net pay', $legacy_result['net_pay'] === $result['totals']['net_pay'] );

foreach ( array( '1052.62', '1052.63', '1052.64', '3157.89', '3157.90', '3157.91', '4000.00' ) as $gross ) {
	$cycle_boundary = Stockland_HR_Payroll_Calculator::calculate(
		array(
			'employee_ref' => 'SYNTH-EMPLOYEE-BOUNDARY',
			'period_date'  => '2026-01-31',
			'items'        => array( array( 'code' => 'BASE', 'amount' => $gross, 'source_ref' => 'SYNTH-BOUNDARY-' . str_replace( '.', '', $gross ) ) ),
		),
		$rules
	);
	$legacy_boundary = Stockland_Payroll_Statutory::calculate( $gross, '0.00', '0.00', $gross, '0.00', $rules );
	$check(
		'legacy and frozen-run engines reconcile at bracket boundary gross ETB ' . $gross,
		$legacy_boundary['income_tax'] === $cycle_boundary['totals']['payable_income_tax']
			&& $legacy_boundary['employee_pension'] === $cycle_boundary['totals']['employee_pension']
			&& $legacy_boundary['employer_pension'] === $cycle_boundary['totals']['employer_pension']
			&& $legacy_boundary['net_pay'] === $cycle_boundary['totals']['net_pay']
	);
}

$check( 'calculator returns preview-only status', 'CALCULATED_REQUIRES_APPROVAL' === ( $result['status'] ?? '' ) );
$check( 'calculator prohibits automatic posting', false === ( $result['posting_allowed'] ?? true ) );
$check( 'calculator prohibits automatic payment', false === ( $result['payment_allowed'] ?? true ) );
$check( 'employee pension synthetic vector is ETB 75.00', '75.00' === ( $result['totals']['employee_pension'] ?? null ) );
$check( 'employer pension synthetic vector is ETB 120.00', '120.00' === ( $result['totals']['employer_pension'] ?? null ) );
$check( 'synthetic PAYE vector is ETB 42.50', '42.50' === ( $result['totals']['payable_income_tax'] ?? null ) );
$check( 'net pay synthetic vector reconciles to ETB 1,332.50', '1332.50' === ( $result['totals']['net_pay'] ?? null ) );

$expect_refusal = static function ( $label, $mutate ) use ( $rules, $check ) {
	$changed = $mutate( $rules );
	try {
		Stockland_HR_Payroll_Calculator::calculate(
			array(
				'employee_ref' => 'SYNTH-EMPLOYEE-0001',
				'period_date'  => '2026-01-31',
				'items'        => array( array( 'code' => 'BASE', 'amount' => '1500.00', 'source_ref' => 'SYNTH-COMP-0001' ) ),
			),
			$changed
		);
		$check( $label, false, 'invalid contract was accepted' );
	} catch ( Throwable $e ) {
		$check( $label, true );
	}
};
$expect_refusal( 'calculator refuses missing reviewer ID', static function ( $r ) { $r['reviewer_id'] = ''; return $r; } );
$expect_refusal( 'calculator refuses a review without an opinion reference', static function ( $r ) { $r['approval_evidence_ref'] = ''; return $r; } );
$expect_refusal( 'calculator refuses a period before rule effective date', static function ( $r ) { $r['effective_from'] = '2026-02-01'; return $r; } );
$duplicate_source_refused = false;
try {
	Stockland_HR_Payroll_Calculator::calculate(
		array(
			'employee_ref' => 'SYNTH-EMPLOYEE-0001',
			'period_date'  => '2026-01-31',
			'items'        => array(
				array( 'code' => 'BASE', 'amount' => '1500.00', 'source_ref' => 'SYNTH-DUPLICATE-0001' ),
				array( 'code' => 'OTHER_DEDUCTION', 'amount' => '50.00', 'source_ref' => 'SYNTH-DUPLICATE-0001' ),
			),
		),
		$rules
	);
} catch ( Throwable $e ) {
	$duplicate_source_refused = false !== strpos( $e->getMessage(), 'duplicate_input_source' );
}
$check( 'calculator rejects duplicate frozen input source references', $duplicate_source_refused );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
exit( $fail ? 1 : 0 );
