<?php
require_once __DIR__ . '/Stockland_HR_Workflow_State_Machine.php';

function ok($condition, $message) {
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}
function expectDomainCode($code, $callback) {
    try { $callback(); } catch (DomainException $e) {
        ok(strpos($e->getMessage(), $code . ':') === 0, 'expected ' . $code . ', got ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected workflow error: ' . $code);
}
function go($aggregate, $from, $to, $context) {
    return Stockland_HR_Workflow_State_Machine::transition($aggregate, $from, $to, $context);
}

expectDomainCode('actor_missing', function() { go('PAYROLL_RUN','DRAFT','READY',[]); });
expectDomainCode('transition_not_allowed', function() { go('PAYROLL_RUN','DRAFT','PAID',['actor_id'=>'u1']); });
expectDomainCode('payroll_preflight_failed', function() {
    go('PAYROLL_RUN','DRAFT','READY',['actor_id'=>'hr','maker_id'=>'hr','preflight_passed'=>true]);
});
expectDomainCode('self_approval_blocked', function() {
    go('PAYROLL_RUN','SUBMITTED','APPROVED',[
        'actor_id'=>'payroll-maker','maker_id'=>'payroll-maker',
        'rules_approved_and_effective'=>true,'exceptions_resolved'=>true,'totals_reconciled'=>true,
        'calculation_hash'=>str_repeat('a',64)
    ]);
});
expectDomainCode('activation_prerequisite_missing', function() {
    go('CONTRACT','READY_FOR_ACTIVATION','ACTIVE',['actor_id'=>'owner','maker_id'=>'hr','contract_approved'=>true]);
});
expectDomainCode('legal_review_missing', function() {
    go('LEGAL_RULE','LEGAL_REVIEW','APPROVED',[
        'actor_id'=>'owner','maker_id'=>'hr','review_status'=>'PENDING',
        'official_source_ref'=>'source','source_sha256'=>str_repeat('a',64),
        'reviewer_ref'=>'reviewer','test_pack_ref'=>'tests','effective_date'=>'2026-01-01'
    ]);
});
expectDomainCode('settlement_incomplete', function() {
    go('PAYROLL_RUN','SETTLEMENT_PENDING','SETTLED',['actor_id'=>'owner','maker_id'=>'hr','all_recipients_confirmed'=>false]);
});
expectDomainCode('evidence_missing', function() {
    go('PAYOUT_INSTRUCTION','PROCESSING','PAID',['actor_id'=>'finance','maker_id'=>'payroll']);
});
expectDomainCode('payee_tax_review_required', function() {
    go('PAYEE_REQUEST','AUTO_CHECK','APPROVED',['actor_id'=>'finance','maker_id'=>'requester','classification_resolved'=>false,'withholding_reviewed'=>false]);
});

$actorMaker = ['actor_id'=>'hr','maker_id'=>'hr','preflight_passed'=>true,'input_snapshot_frozen'=>true,'input_snapshot_sha256'=>str_repeat('b',64)];
ok(go('PAYROLL_RUN','DRAFT','READY',$actorMaker)['allowed'] === true, 'ready requires frozen preflight inputs');
ok(go('PAYROLL_RUN','READY','CALCULATED',['actor_id'=>'hr','maker_id'=>'hr','rules_approved_and_effective'=>true,'exceptions_resolved'=>true,'calculation_hash'=>str_repeat('c',64)])['allowed'] === true, 'calculation requires rules and exception closure');
ok(go('CONTRACT','READY_FOR_ACTIVATION','ACTIVE',[
    'actor_id'=>'owner','maker_id'=>'hr','contract_approved'=>true,'documents_verified'=>true,
    'onboarding_complete'=>true,'compensation_approved'=>true,'access_approved'=>true
])['allowed'] === true, 'activation has all required prerequisites');
ok(go('LEGAL_RULE','LEGAL_REVIEW','APPROVED',[
    'actor_id'=>'tax-reviewer','maker_id'=>'hr','review_status'=>'LEGAL_REVIEW',
    'official_source_ref'=>'official-source-document','source_sha256'=>str_repeat('d',64),
    'reviewer_ref'=>'reviewer-1','test_pack_ref'=>'rule-tests-v1','effective_date'=>'2026-01-01'
])['allowed'] === true, 'legal rule needs independent review evidence and hash');
ok(go('PAYOUT_INSTRUCTION','PROCESSING','PAID',[
    'actor_id'=>'finance','maker_id'=>'payroll','external_payment_ref'=>'BANK-REFERENCE-001',
    'confirmation_ref'=>'BANK-STATEMENT-ROW-001'
])['allowed'] === true, 'recipient marked paid only with external confirmation');
ok(go('PAYEE_REQUEST','AUTO_CHECK','APPROVED',[
    'actor_id'=>'finance','maker_id'=>'requester','classification_resolved'=>true,
    'withholding_reviewed'=>true,'engagement_ref'=>'contract-001','deliverable_acceptance_ref'=>'deliverable-001'
])['allowed'] === true, 'central payee workflow needs classification and deliverable evidence');

$hash = str_repeat('e',64);
ok(go('PAYROLL_RUN','CALCULATED','SUBMITTED',['actor_id'=>'payroll','maker_id'=>'payroll'])['allowed'] === true, 'processor may submit calculated run');
ok(go('PAYROLL_RUN','SUBMITTED','APPROVED',[
    'actor_id'=>'owner','maker_id'=>'payroll','rules_approved_and_effective'=>true,
    'exceptions_resolved'=>true,'totals_reconciled'=>true,'calculation_hash'=>$hash
])['allowed'] === true, 'independent Owner approval of exact calculation');
ok(go('PAYROLL_RUN','APPROVED','POSTED',[
    'actor_id'=>'finance','maker_id'=>'payroll','approved'=>true,
    'posting_transaction_balanced'=>true,'posting_idempotency_key'=>'payroll-run-2026-06-v1',
    'journal_ref'=>'GL-JOURNAL-001'
])['allowed'] === true, 'posting requires balanced journal and idempotency key');
ok(go('PAYROLL_RUN','SETTLEMENT_PENDING','SETTLED',[
    'actor_id'=>'owner','maker_id'=>'payroll','all_recipients_confirmed'=>true,
    'external_batch_ref'=>'BANK-BATCH-001'
])['allowed'] === true, 'run settles only when every recipient is confirmed');
ok(go('SEPARATION_CASE','REVIEWED','APPROVED_FOR_PAYMENT',[
    'actor_id'=>'owner','maker_id'=>'hr','legal_calculation_reviewed'=>true,
    'clearance_complete'=>true,'totals_reconciled'=>true,'final_calculation_hash'=>$hash
])['allowed'] === true, 'final settlement needs legal review, clearance and reconciled calculation');

echo "PASS synthetic HR/payroll workflow transition contracts; no production writes performed\n";
