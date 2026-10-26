<?php
define( 'STOCKLAND_HR_CALCULATOR_STANDALONE', true );
require_once __DIR__ . '/Stockland_HR_Payroll_Calculator.php';

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
};
$throws = static function ( callable $fn, $message ) use ( $assert ) {
	try {
		$fn();
	} catch ( DomainException $e ) {
		$assert( '' !== $e->getMessage(), $message . ' must provide an actionable failure reason' );
		return $e->getMessage();
	}
	throw new RuntimeException( 'FAIL: expected fail-closed exception for ' . $message );
};
$rule = array(
	'version' => 'SYNTHETIC-LAB-1',
	'approval_status' => 'APPROVED',
	'effective_from' => '2026-01-01',
	'effective_to' => null,
	'source_reference' => 'SYNTHETIC TEST FIXTURE — NOT ETHIOPIAN LAW',
	'source_sha256' => hash( 'sha256', 'synthetic test source only' ),
	'reviewer_id' => 77,
	'reviewed_at' => '2026-01-01T00:00:00Z',
	'reviewer_qualification' => 'QUALIFIED_ET_TAX_PENSION_REVIEWER',
	'approval_reference' => 'SYNTHETIC TEST APPROVAL — NOT LEGAL SIGN-OFF',
	'taxable_base_mode' => Stockland_HR_Payroll_Calculator::TAXABLE_GROSS,
	'tax_rounding_mode' => Stockland_HR_Payroll_Calculator::ROUND_TOTAL,
	'employee_pension_rate_bps' => 500,
	'employer_pension_rate_bps' => 800,
	'tax_bands' => array(
		array( 'lower_minor' => 0, 'upper_minor' => 200000, 'rate_bps' => 0 ),
		array( 'lower_minor' => 200000, 'upper_minor' => 500000, 'rate_bps' => 1000 ),
		array( 'lower_minor' => 500000, 'upper_minor' => null, 'rate_bps' => 2000 ),
	),
	'components' => array(
		'TEST_SALARY' => array( 'kind' => 'EARNING', 'tax_treatment' => 'TAXABLE', 'pension_treatment' => 'PENSIONABLE', 'classification_status' => 'APPROVED', 'source_reference' => 'SYNTHETIC COMPONENT FIXTURE', 'evidence_required' => true ),
		'TEST_OVERTIME' => array( 'kind' => 'EARNING', 'tax_treatment' => 'TAXABLE', 'pension_treatment' => 'PENSIONABLE', 'classification_status' => 'APPROVED', 'source_reference' => 'SYNTHETIC COMPONENT FIXTURE', 'evidence_required' => true ),
		'TEST_BENEFIT' => array( 'kind' => 'EARNING', 'tax_treatment' => 'EXEMPT', 'pension_treatment' => 'NON_PENSIONABLE', 'classification_status' => 'APPROVED', 'source_reference' => 'SYNTHETIC COMPONENT FIXTURE', 'evidence_required' => true ),
		'TEST_REIMBURSEMENT' => array( 'kind' => 'REIMBURSEMENT', 'tax_treatment' => 'NON_TAXABLE_REIMBURSEMENT', 'pension_treatment' => 'NOT_APPLICABLE', 'classification_status' => 'APPROVED', 'source_reference' => 'SYNTHETIC COMPONENT FIXTURE', 'evidence_required' => true ),
		'TEST_DEDUCTION' => array( 'kind' => 'DEDUCTION', 'tax_treatment' => 'NOT_APPLICABLE', 'pension_treatment' => 'NOT_APPLICABLE', 'classification_status' => 'APPROVED', 'source_reference' => 'SYNTHETIC COMPONENT FIXTURE', 'evidence_required' => true ),
	),
);
$synthetic_component_hash = hash( 'sha256', 'synthetic component source only; not Ethiopian law' );
foreach ( $rule['components'] as &$synthetic_component ) {
	$synthetic_component['source_sha256'] = $synthetic_component_hash;
}
unset( $synthetic_component );

$items = array(
	array( 'item_code' => 'TEST_SALARY', 'amount' => '2000.00', 'source_reference' => 'compensation:fixture-001', 'evidence_reference' => 'fixture:salary-assignment', 'basis_reference' => 'fixture:contract' ),
	array( 'item_code' => 'TEST_OVERTIME', 'amount' => '400.00', 'source_reference' => 'attendance:fixture-001', 'evidence_reference' => 'fixture:approved-overtime', 'basis_reference' => 'fixture:approved-overtime-policy' ),
	array( 'item_code' => 'TEST_BENEFIT', 'amount' => '100.00', 'source_reference' => 'benefit:fixture-001', 'evidence_reference' => 'fixture:benefit-valuation', 'basis_reference' => 'fixture:exemption-basis' ),
	array( 'item_code' => 'TEST_REIMBURSEMENT', 'amount' => '150.00', 'source_reference' => 'expense:fixture-001', 'evidence_reference' => 'fixture:receipts', 'basis_reference' => 'fixture:approved-reimbursement' ),
	array( 'item_code' => 'TEST_DEDUCTION', 'amount' => '200.00', 'source_reference' => 'deduction:fixture-001', 'evidence_reference' => 'fixture:employee-authorization', 'basis_reference' => 'fixture:contractual-basis' ),
);

foreach ( $items as $index => &$synthetic_item ) {
	$synthetic_item['input_reference'] = 'fixture-input-' . ( $index + 1 );
}
unset( $synthetic_item );

$shared_source_items = $items;
$shared_source_items[1]['source_reference'] = $shared_source_items[0]['source_reference'];
$shared_source_result = Stockland_HR_Payroll_Calculator::calculate( $shared_source_items, $rule, '2026-01-31' );
$assert( 5 === count( $shared_source_result['items'] ), 'multiple distinct input items may share a source document without being treated as duplicate lines' );

$result = Stockland_HR_Payroll_Calculator::calculate( $items, $rule, '2026-01-31' );
$assert( '2500.00' === $result['totals']['earnings'], 'gross earnings reconcile' );
$assert( '150.00' === $result['totals']['reimbursements'], 'reimbursements remain separated from earnings' );
$assert( '2400.00' === $result['totals']['taxable_gross'], 'only rule-classified taxable components enter taxable gross' );
$assert( '100.00' === $result['totals']['exempt_earnings'], 'exempt compensation is separately visible' );
$assert( '2400.00' === $result['totals']['pensionable_base'], 'pensionable basis comes from approved component mapping' );
$assert( '40.00' === $result['totals']['income_tax'], 'synthetic progressive marginal PAYE schedule' );
$assert( '120.00' === $result['totals']['employee_pension'], 'synthetic employee contribution' );
$assert( '192.00' === $result['totals']['employer_pension'], 'synthetic employer contribution' );
$assert( '2290.00' === $result['totals']['net_payable'], 'gross-to-net reconciliation' );
$assert( '2842.00' === $result['totals']['employer_cost'], 'employer cost keeps employer contribution separate' );
$assert( 64 === strlen( $result['calculation_sha256'] ), 'calculation hash generated' );
$reordered_result = Stockland_HR_Payroll_Calculator::calculate( array_reverse( $items ), $rule, '2026-01-31' );
$assert( $result['calculation_sha256'] === $reordered_result['calculation_sha256'], 'input ordering does not change the canonical calculation hash' );

$unapproved = $rule;
$unapproved['approval_status'] = 'LEGAL_REVIEW';
$throws( static function () use ( $items, $unapproved ) { Stockland_HR_Payroll_Calculator::calculate( $items, $unapproved, '2026-01-31' ); }, 'unapproved legal rule' );

$future = $rule;
$future['effective_from'] = '2026-02-01';
$throws( static function () use ( $items, $future ) { Stockland_HR_Payroll_Calculator::calculate( $items, $future, '2026-01-31' ); }, 'not-yet-effective rule' );

$gapped = $rule;
$gapped['tax_bands'][1]['lower_minor'] = 200001;
$throws( static function () use ( $items, $gapped ) { Stockland_HR_Payroll_Calculator::calculate( $items, $gapped, '2026-01-31' ); }, 'gapped marginal bands' );

$bad_qualification = $rule;
$bad_qualification['reviewer_qualification'] = 'UNVERIFIED';
$throws( static function () use ( $items, $bad_qualification ) { Stockland_HR_Payroll_Calculator::calculate( $items, $bad_qualification, '2026-01-31' ); }, 'unqualified statutory reviewer' );

$bad_component_hash = $rule;
$bad_component_hash['components']['TEST_SALARY']['source_sha256'] = 'invalid-component-hash';
$throws( static function () use ( $items, $bad_component_hash ) { Stockland_HR_Payroll_Calculator::calculate( $items, $bad_component_hash, '2026-01-31' ); }, 'component classification source hash' );

$bad_hash = $rule;
$bad_hash['source_sha256'] = 'not-a-hash';
$throws( static function () use ( $items, $bad_hash ) { Stockland_HR_Payroll_Calculator::calculate( $items, $bad_hash, '2026-01-31' ); }, 'missing official source fingerprint' );

$unclassified = $rule;
$unclassified['components']['TEST_BENEFIT']['classification_status'] = 'LEGAL_REVIEW';
$throws( static function () use ( $items, $unclassified ) { Stockland_HR_Payroll_Calculator::calculate( $items, $unclassified, '2026-01-31' ); }, 'unresolved compensation classification' );

$unknown_item = $items;
$unknown_item[0]['item_code'] = 'UNKNOWN_COMPONENT';
$throws( static function () use ( $unknown_item, $rule ) { Stockland_HR_Payroll_Calculator::calculate( $unknown_item, $rule, '2026-01-31' ); }, 'unknown item classification' );

$duplicate = $items;
$duplicate[1]['input_reference'] = $duplicate[0]['input_reference'];
$throws( static function () use ( $duplicate, $rule ) { Stockland_HR_Payroll_Calculator::calculate( $duplicate, $rule, '2026-01-31' ); }, 'duplicate source reference' );

$floats = $items;
$floats[0]['amount'] = 2000.00;
$throws( static function () use ( $floats, $rule ) { Stockland_HR_Payroll_Calculator::calculate( $floats, $rule, '2026-01-31' ); }, 'floating-point money input' );

$missing_evidence = $items;
$missing_evidence[2]['basis_reference'] = '';
$throws( static function () use ( $missing_evidence, $rule ) { Stockland_HR_Payroll_Calculator::calculate( $missing_evidence, $rule, '2026-01-31' ); }, 'exemption without supporting evidence' );

$bad_date = $throws( static function () use ( $items, $rule ) { Stockland_HR_Payroll_Calculator::calculate( $items, $rule, '2026-02-30' ); }, 'invalid payroll date' );
$assert( is_string( $bad_date ), 'invalid period date is rejected' );

$base_rule = $rule;
$base_rule['taxable_base_mode'] = Stockland_HR_Payroll_Calculator::TAXABLE_GROSS_LESS_EMPLOYEE_PENSION;
$base_result = Stockland_HR_Payroll_Calculator::calculate( $items, $base_rule, '2026-01-31' );
$assert( '2280.00' === $base_result['totals']['taxable_income'], 'tax-base behavior is explicitly selected by the approved rule' );
$assert( '28.00' === $base_result['totals']['income_tax'], 'synthetic alternate tax-base method' );

$rounding_rule = $rule;
$rounding_rule['employee_pension_rate_bps'] = 750;
$rounding_rule['tax_bands'] = array( array( 'lower_minor' => 0, 'upper_minor' => null, 'rate_bps' => 1000 ) );
$rounding_items = array(
	array( 'item_code' => 'TEST_SALARY', 'input_reference' => 'fixture-input-rounding-1', 'amount' => '100.09', 'source_reference' => 'compensation:rounding-fixture', 'evidence_reference' => 'fixture:salary', 'basis_reference' => 'fixture:contract' ),
);
$rounding = Stockland_HR_Payroll_Calculator::calculate( $rounding_items, $rounding_rule, '2026-01-31' );
$assert( '10.01' === $rounding['totals']['income_tax'], 'small taxable amount rounds through the configured marginal rule' );
$assert( '7.51' === $rounding['totals']['employee_pension'], 'half-up rounding is deterministic in minor currency units' );

echo "PASS HR calculator: synthetic gross-to-net, progressive bands, basis selection, hashes, and fail-closed statutory/evidence guards\n";
