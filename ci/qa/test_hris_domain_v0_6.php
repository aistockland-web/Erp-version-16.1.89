<?php
require __DIR__ . '/../hris_overlay_v0_6/class-stockland-hris-domain.php';
function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
function rule( $type = 'PAYE' ) {
	return array(
		'id' => 7, 'rule_type' => $type, 'status' => 'APPROVED',
		'effective_from' => '2025-08-07', 'effective_to' => null,
		'source_url' => 'https://example.gov.et/source.pdf', 'source_sha256' => str_repeat( 'a', 64 ),
		'legal_clause' => 'SYNTHETIC QA RULE ONLY', 'created_by' => 1, 'approved_by' => 2,
		'approved_at' => '2026-01-01 10:00:00', 'rule_version' => 'SYNTHETIC-1',
		'calculation_method' => 'PROGRESSIVE_MARGINAL',
		'bands' => array(
			array( 'up_to' => '2000.00', 'rate_bps' => 0 ),
			array( 'up_to' => '4000.00', 'rate_bps' => 1500 ),
			array( 'up_to' => '7000.00', 'rate_bps' => 2000 ),
			array( 'up_to' => '10000.00', 'rate_bps' => 2500 ),
			array( 'up_to' => '14000.00', 'rate_bps' => 3000 ),
			array( 'up_to' => null, 'rate_bps' => 3500 ),
		),
	);
}
$synthetic = rule();
foreach ( array(
	'0' => '0.00', '2000.00' => '0.00', '3000.00' => '150.00',
	'4000.00' => '300.00', '4001.00' => '300.20', '6000.00' => '700.00',
	'7000.00' => '900.00', '10000.00' => '1650.00', '12000.00' => '2250.00',
	'14000.00' => '2850.00', '20000.00' => '4950.00',
) as $income => $expected ) {
	$result = Stockland_HRIS_Domain::calculate_progressive_tax( $income, $synthetic, '2026-10-01' );
	check( ! empty( $result['ok'] ), "calculation should pass for {$income}: " . ( $result['message'] ?? '' ) );
	check( $result['tax'] === $expected, "income {$income} tax expected {$expected}, got " . $result['tax'] );
}
$missing = Stockland_HRIS_Domain::calculate_progressive_tax( '5000.00', null, '2026-10-01' );
check( empty( $missing['ok'] ) && 'rule_missing' === $missing['code'], 'missing statutory rule fails closed' );
$draft = $synthetic; $draft['status'] = 'LEGAL_REVIEW';
check( empty( Stockland_HRIS_Domain::calculate_progressive_tax( '5000.00', $draft, '2026-10-01' )['ok'] ), 'unapproved rule fails closed' );
$expired = $synthetic; $expired['effective_to'] = '2026-09-30';
check( empty( Stockland_HRIS_Domain::calculate_progressive_tax( '5000.00', $expired, '2026-10-01' )['ok'] ), 'expired rule fails closed' );
$badHash = $synthetic; $badHash['source_sha256'] = 'xyz';
check( empty( Stockland_HRIS_Domain::calculate_progressive_tax( '5000.00', $badHash, '2026-10-01' )['ok'] ), 'invalid source hash fails closed' );
$pensionRule = rule('PENSION');
$run = array('status'=>'SUBMITTED','created_by'=>1,'input_snapshot_sha256'=>str_repeat('c',64),'current_input_snapshot_sha256'=>str_repeat('c',64),'frozen_calculation_sha256'=>str_repeat('d',64),'recomputed_calculation_sha256'=>str_repeat('d',64),'unresolved_exceptions'=>array(),'totals_reconciled'=>true,'gl_preview_balanced'=>true);
check( ! empty( Stockland_HRIS_Domain::validate_payroll_approval($run,2,$synthetic,$pensionRule,'2026-10-01')['ok'] ), 'frozen and recomputed payroll is eligible for domain approval' );
$drift = $run; $drift['current_input_snapshot_sha256'] = str_repeat('e',64);
check( empty( Stockland_HRIS_Domain::validate_payroll_approval($drift,2,$synthetic,$pensionRule,'2026-10-01')['ok'] ), 'input snapshot drift blocks approval' );
$calcDrift = $run; $calcDrift['recomputed_calculation_sha256'] = str_repeat('f',64);
check( empty( Stockland_HRIS_Domain::validate_payroll_approval($calcDrift,2,$synthetic,$pensionRule,'2026-10-01')['ok'] ), 'calculation snapshot drift blocks approval' );
check( empty( Stockland_HRIS_Domain::calculate_progressive_tax( 5000.00, $synthetic, '2026-10-01' )['ok'] ), 'binary float financial input rejected' );
$badBands = $synthetic; $badBands['bands'][2]['up_to'] = '3500.00';
check( empty( Stockland_HRIS_Domain::calculate_progressive_tax( '5000.00', $badBands, '2026-10-01' )['ok'] ), 'non-increasing schedule rejected' );
$earlyOpen = $synthetic; $earlyOpen['bands'][1]['up_to'] = null;
check( empty( Stockland_HRIS_Domain::calculate_progressive_tax( '5000.00', $earlyOpen, '2026-10-01' )['ok'] ), 'non-final open-ended band rejected' );
$fractionalRate = $synthetic; $fractionalRate['bands'][1]['rate_bps'] = '1500.5';
check( empty( Stockland_HRIS_Domain::calculate_progressive_tax( '5000.00', $fractionalRate, '2026-10-01' )['ok'] ), 'fractional basis point rate rejected' );
$transition = Stockland_HRIS_Domain::validate_transition( 'application', 'OFFERED', 'HIRED', 1, 1 );
check( empty( $transition['ok'] ) && 'maker_checker_violation' === $transition['code'], 'maker cannot finalize own application' );
check( ! empty( Stockland_HRIS_Domain::validate_transition( 'application', 'OFFERED', 'HIRED', 2, 1 )['ok'] ), 'independent transition is allowed by domain graph' );
check( empty( Stockland_HRIS_Domain::validate_transition( 'payroll_run', 'DRAFT', 'POSTED', 2, 1 )['ok'] ), 'illegal state skip denied' );
$payment = Stockland_HRIS_Domain::validate_payment_settlement( array( 'external_reference'=>'BANK-1','payment_method'=>'BANK','payment_date'=>'2026-10-20','expected_total'=>'150.00' ), array(
	array('beneficiary_id'=>1,'status'=>'SETTLED','amount'=>'100.00'),
	array('beneficiary_id'=>2,'status'=>'FAILED','amount'=>'50.00'),
) );
check( ! empty( $payment['ok'] ), 'partial payment with per-beneficiary results is structurally valid' );
$duplicate = Stockland_HRIS_Domain::validate_payment_settlement( array( 'external_reference'=>'BANK-2','payment_method'=>'BANK','payment_date'=>'2026-10-20','expected_total'=>'110.00' ), array(
	array('beneficiary_id'=>1,'status'=>'SETTLED','amount'=>'100.00'),
	array('beneficiary_id'=>1,'status'=>'SETTLED','amount'=>'10.00'),
) );
check( empty( $duplicate['ok'] ) && 'payment_duplicate_beneficiary' === $duplicate['code'], 'duplicate beneficiary outcome rejected' );
$mismatch = Stockland_HRIS_Domain::validate_payment_settlement( array( 'external_reference'=>'BANK-3','payment_method'=>'BANK','payment_date'=>'2026-10-20','expected_total'=>'200.00' ), array( array('beneficiary_id'=>1,'status'=>'SETTLED','amount'=>'100.00') ) );
check( empty( $mismatch['ok'] ) && 'payment_total_mismatch' === $mismatch['code'], 'payment total mismatch rejected' );
$exit = array('employee_id'=>12,'exit_type'=>'RESIGNATION','effective_date'=>'2026-10-20');
check( empty( Stockland_HRIS_Domain::validate_exit_settlement( $exit, false )['ok'] ), 'exit settlement blocked without approved rules' );
$exit['attendance_closed']=$exit['leave_reconciled']=$exit['assets_cleared']=$exit['access_reviewed']=$exit['calculation_reconciled']=$exit['finance_reviewed']=$exit['owner_approved']=true;
$exit['settlement_snapshot_sha256']=str_repeat('b',64);
check( ! empty( Stockland_HRIS_Domain::validate_exit_settlement( $exit, true )['ok'] ), 'complete exit clearance passes domain preflight' );
$badExit=$exit; $badExit['owner_approved']=false;
check( empty( Stockland_HRIS_Domain::validate_exit_settlement( $badExit, true )['ok'] ), 'final settlement blocked without Owner approval' );
check( Stockland_HRIS_Domain::decimal_to_minor( '123.45' ) === 12345, 'decimal string uses integer cents' );
try { Stockland_HRIS_Domain::decimal_to_minor( '12.345' ); check( false, 'too many decimals must throw' ); } catch ( InvalidArgumentException $e ) { }
echo "Stockland HRIS domain-kernel tests PASS: 35 assertions. Rule schedule uses synthetic QA-only data and is not an approved Ethiopian tax rule.\n";
