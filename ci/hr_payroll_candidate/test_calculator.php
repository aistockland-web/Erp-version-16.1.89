<?php
require_once __DIR__ . '/Stockland_HR_Payroll_Calculator.php';

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
    }
}
function assertThrowsCode($code, $callback) {
    try { $callback(); } catch (InvalidArgumentException $e) {
        if (strpos($e->getMessage(), $code . ':') !== 0) {
            throw new RuntimeException('Expected error ' . $code . ' but got ' . $e->getMessage());
        }
        return;
    }
    throw new RuntimeException('Expected error ' . $code . ' but no exception was thrown');
}
function toyRules() {
    return [
        'status' => 'SYNTHETIC_TEST_ONLY',
        'synthetic_only' => true,
        'rule_version' => 'TOY-MATH-1',
        'jurisdiction' => 'NOT-ETHIOPIAN-LAW',
        'effective_from' => '2026-01-01',
        'effective_to' => '2026-12-31',
        'source_sha256' => str_repeat('a', 64),
        'reviewer_id' => 'SYNTHETIC_FIXTURE_ONLY',
        'approved_at' => '2026-01-01T00:00:00Z',
        'official_source_url' => 'https://example.invalid/toy-only',
        'approval_evidence_ref' => 'TOY-FIXTURE-NOT-LEGAL-APPROVAL',
        'paye_tax_base_method' => 'TAXABLE_EARNINGS',
        'pensionable_base_method' => 'SUM_PENSIONABLE_ITEMS',
        'employee_pension_rate' => '0.01',
        'employer_pension_rate' => '0.02',
        'rounding_method' => 'HALF_UP_MINOR_UNIT',
        'paye_bands' => [
            ['up_to'=>'100.00','rate'=>'0.01'],
            ['up_to'=>'200.00','rate'=>'0.02'],
            ['up_to'=>null,'rate'=>'0.03']
        ],
        'component_treatments' => [
            'BASE' => ['kind'=>'EARNING','taxable'=>true,'pensionable'=>true],
            'TAXABLE_ALLOWANCE' => ['kind'=>'EARNING','taxable'=>true,'pensionable'=>false],
            'EXEMPT_CANDIDATE' => ['kind'=>'EARNING','taxable'=>false,'pensionable'=>false],
            'REIMBURSEMENT' => ['kind'=>'REIMBURSEMENT','taxable'=>false,'pensionable'=>false],
            'OTHER_DEDUCTION' => ['kind'=>'DEDUCTION','taxable'=>false,'pensionable'=>false],
            'EMPLOYER_COST' => ['kind'=>'EMPLOYER_COST','taxable'=>false,'pensionable'=>false]
        ]
    ];
}
function toyInput($items = null) {
    if ($items === null) {
        $items = [
            ['code'=>'BASE','amount'=>'250.00','source_ref'=>'comp-approved-001'],
            ['code'=>'TAXABLE_ALLOWANCE','amount'=>'50.00','source_ref'=>'allow-approved-001'],
            ['code'=>'REIMBURSEMENT','amount'=>'10.00','source_ref'=>'expense-approved-001'],
            ['code'=>'OTHER_DEDUCTION','amount'=>'5.00','source_ref'=>'deduction-approved-001'],
            ['code'=>'EMPLOYER_COST','amount'=>'7.00','source_ref'=>'employer-benefit-001']
        ];
    }
    return ['employee_ref'=>'SYNTHETIC-EMPLOYEE-001','period_date'=>'2026-06-30','items'=>$items];
}

$rules = toyRules();
$result = Stockland_HR_Payroll_Calculator::calculate(toyInput(), $rules, true);
assertSameValue('SYNTHETIC_TEST_ONLY', $result['status'], 'result must remain test-only');
assertSameValue(false, $result['posting_allowed'], 'candidate must never authorize posting');
assertSameValue(false, $result['payment_allowed'], 'candidate must never authorize payment');
assertSameValue('300.00', $result['totals']['earnings'], 'earnings total');
assertSameValue('300.00', $result['totals']['taxable_earnings'], 'taxable earnings');
assertSameValue('250.00', $result['totals']['pensionable_earnings'], 'pensionable earnings');
assertSameValue('10.00', $result['totals']['reimbursements'], 'reimbursement remains separately identified');
assertSameValue('2.50', $result['totals']['employee_pension'], 'employee pension from supplied toy rule');
assertSameValue('5.00', $result['totals']['employer_pension'], 'employer pension is employer cost, not employee deduction');
assertSameValue('6.00', $result['totals']['payable_income_tax'], 'progressive marginal toy tax');
assertSameValue('296.50', $result['totals']['net_pay'], 'net pay arithmetic');
assertSameValue('322.00', $result['totals']['employer_total_cost'], 'employer cash-cost arithmetic');
assertSameValue(3, count($result['paye_progressive_band_trace']), 'all toy bands must have an explainable trace');

assertThrowsCode('synthetic_rules_blocked', function() use ($rules) {
    Stockland_HR_Payroll_Calculator::calculate(toyInput(), $rules, false);
});
assertThrowsCode('rule_not_approved', function() use ($rules) {
    $r=$rules; $r['status']='DRAFT';
    Stockland_HR_Payroll_Calculator::calculate(toyInput(), $r, true);
});
assertThrowsCode('rule_out_of_date', function() use ($rules) {
    $r=$rules; $r['effective_to']='2026-05-31';
    Stockland_HR_Payroll_Calculator::calculate(toyInput(), $r, true);
});
assertThrowsCode('rule_source_hash_invalid', function() use ($rules) {
    $r=$rules; $r['source_sha256']='not-a-hash';
    Stockland_HR_Payroll_Calculator::calculate(toyInput(), $r, true);
});
assertThrowsCode('paye_bands_incomplete', function() use ($rules) {
    $r=$rules; array_pop($r['paye_bands']);
    Stockland_HR_Payroll_Calculator::calculate(toyInput(), $r, true);
});
assertThrowsCode('paye_band_order_invalid', function() use ($rules) {
    $r=$rules; $r['paye_bands']=[['up_to'=>'200.00','rate'=>'0.01'],['up_to'=>'100.00','rate'=>'0.02'],['up_to'=>null,'rate'=>'0.03']];
    Stockland_HR_Payroll_Calculator::calculate(toyInput(), $r, true);
});
assertThrowsCode('component_unknown', function() use ($rules) {
    Stockland_HR_Payroll_Calculator::calculate(toyInput([['code'=>'UNMAPPED','amount'=>'10.00','source_ref'=>'source-1']]), $rules, true);
});
assertThrowsCode('invalid_money', function() use ($rules) {
    Stockland_HR_Payroll_Calculator::calculate(toyInput([['code'=>'BASE','amount'=>10.00,'source_ref'=>'source-1']]), $rules, true);
});
assertThrowsCode('invalid_money', function() use ($rules) {
    Stockland_HR_Payroll_Calculator::calculate(toyInput([['code'=>'BASE','amount'=>'-10.00','source_ref'=>'source-1']]), $rules, true);
});
assertThrowsCode('duplicate_input_source', function() use ($rules) {
    Stockland_HR_Payroll_Calculator::calculate(toyInput([
        ['code'=>'BASE','amount'=>'10.00','source_ref'=>'same-source'],
        ['code'=>'TAXABLE_ALLOWANCE','amount'=>'5.00','source_ref'=>'same-source']
    ]), $rules, true);
});
assertThrowsCode('negative_net_pay', function() use ($rules) {
    $r=$rules;
    $r['paye_bands']=[['up_to'=>'100.00','rate'=>'0.00'],['up_to'=>null,'rate'=>'0.00']];
    Stockland_HR_Payroll_Calculator::calculate(toyInput([
        ['code'=>'BASE','amount'=>'1.00','source_ref'=>'base'],
        ['code'=>'OTHER_DEDUCTION','amount'=>'10.00','source_ref'=>'deduct']
    ]), $r, true);
});

echo "PASS synthetic payroll calculator contract (toy rules only; not Ethiopian legal or payroll approval)\n";
