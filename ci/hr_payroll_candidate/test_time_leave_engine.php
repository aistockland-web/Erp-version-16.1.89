<?php
require_once __DIR__ . '/Stockland_HR_Time_Leave_Engine.php';

function leaveAssertSame($expected, $actual, $message) {
    if ($expected !== $actual) throw new RuntimeException($message . '; expected=' . var_export($expected,true) . '; actual=' . var_export($actual,true));
}
function leaveAssertThrows($code, $callback) {
    try { $callback(); } catch (InvalidArgumentException $e) {
        if (strpos($e->getMessage(), $code . ':') !== 0) throw new RuntimeException('Expected ' . $code . ', got ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected leave error ' . $code . ' but none was thrown');
}
function toyLeavePolicy() {
    return [
        'status'=>'SYNTHETIC_TEST_ONLY','synthetic_only'=>true,
        'policy_version'=>'TOY-ANNUAL-1','leave_type'=>'ANNUAL','unit'=>'DAYS',
        'effective_from'=>'2026-01-01','effective_to'=>'2026-12-31',
        'source_sha256'=>str_repeat('a',64),'policy_document_ref'=>'TOY-ONLY-NOT-LAW',
        'allow_negative_balance'=>false
    ];
}
function leaveTx($ref,$type,$quantity,$date,$key,$status='APPROVED',$created='manager',$approved='owner') {
    $t=[
        'txn_ref'=>$ref,'employee_ref'=>'SYNTHETIC-EMP-1','leave_type'=>'ANNUAL',
        'type'=>$type,'quantity'=>$quantity,'effective_date'=>$date,'status'=>$status,
        'policy_version'=>'TOY-ANNUAL-1','idempotency_key'=>$key,'source_ref'=>'source-'.$ref
    ];
    if ($status==='APPROVED') {
        $t['created_by']=$created; $t['approved_by']=$approved; $t['approval_ref']='approval-'.$ref;
    }
    if ($type==='ADJUSTMENT') { $t['direction']='DEBIT'; $t['reason']='Synthetic correction fixture'; }
    return $t;
}

$rules=['TOY-ANNUAL-1'=>toyLeavePolicy()];
$tx=[
    leaveTx('l1','ACCRUAL','2.00','2026-01-31','idem-l1'),
    leaveTx('l2','USE','0.50','2026-02-08','idem-l2'),
    leaveTx('l3','CARRY_FORWARD','3.00','2026-03-01','idem-l3'),
    leaveTx('l4','ADJUSTMENT','0.2500','2026-03-20','idem-l4'),
    leaveTx('l5','USE','1.00','2026-04-01','idem-l5','DRAFT')
];
$result=Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',$tx,$rules,true);
leaveAssertSame('4.2500',$result['balance_display'],'approved leave ledger sum');
leaveAssertSame(4,$result['applied_transaction_count'],'approved transaction count');
leaveAssertSame(1,$result['pending_transaction_count'],'pending transaction count');
leaveAssertSame(1,$result['pending_effective_transaction_count'],'pending effective transaction count');
leaveAssertSame('NEEDS_REVIEW',$result['status'],'pending effective transaction must be visible as an exception');
leaveAssertSame(false,$result['payroll_eligible'],'pending effective transaction blocks payroll use');
leaveAssertSame(false,$result['persistent_write_performed'],'projection must not write to database');

$zero=Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-2','ANNUAL','2026-04-30',[],$rules,true);
leaveAssertSame('0.0000',$zero['balance_display'],'new employee with no leave transactions has a zero balance under current policy');
leaveAssertSame('CALCULATED',$zero['status'],'empty ledger is not an error when policy is known');

leaveAssertThrows('leave_balance_negative',function() use($rules) {
    Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',[
        leaveTx('use-too-much','USE','4.00','2026-04-01','idem-too-much')
    ],$rules,true);
});
leaveAssertThrows('duplicate_leave_idempotency_key',function() use($rules) {
    Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',[
        leaveTx('l1','ACCRUAL','2.00','2026-01-31','duplicate-key'),
        leaveTx('l2','ACCRUAL','2.00','2026-02-28','duplicate-key')
    ],$rules,true);
});
leaveAssertThrows('leave_self_approval_blocked',function() use($rules) {
    Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',[
        leaveTx('l1','ACCRUAL','2.00','2026-01-31','idem-l1','APPROVED','same-user','same-user')
    ],$rules,true);
});
leaveAssertThrows('invalid_leave_quantity',function() use($rules) {
    Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',[
        leaveTx('l1','ACCRUAL',2.00,'2026-01-31','idem-l1')
    ],$rules,true);
});
leaveAssertThrows('leave_policy_missing',function() {
    Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',[
        ['employee_ref'=>'SYNTHETIC-EMP-1','leave_type'=>'ANNUAL','type'=>'ACCRUAL','quantity'=>'1.00','effective_date'=>'2026-01-31','status'=>'APPROVED','policy_version'=>'UNKNOWN','idempotency_key'=>'k','source_ref'=>'s']
    ],[],true);
});
leaveAssertThrows('synthetic_policy_blocked',function() use($rules) {
    Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',[
        leaveTx('l1','ACCRUAL','1.00','2026-01-31','idem-l1')
    ],$rules,false);
});
leaveAssertThrows('leave_adjustment_reason_missing',function() use($rules) {
    $t=leaveTx('adj','ADJUSTMENT','0.50','2026-02-01','idem-adj');
    unset($t['reason']);
    Stockland_HR_Time_Leave_Engine::balance('SYNTHETIC-EMP-1','ANNUAL','2026-04-30',[$t],$rules,true);
});
echo "PASS synthetic leave ledger, immutable projection, idempotency and fail-closed tests (toy policy only)\n";
