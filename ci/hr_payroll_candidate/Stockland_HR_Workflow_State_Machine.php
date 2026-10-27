<?php
/**
 * Pure candidate state transition guard. Not integrated into the live ERP.
 * Callers must persist allowed transitions transactionally with locks, versions and audit events.
 */
final class Stockland_HR_Workflow_State_Machine {
    private static function deny($code, $message) {
        throw new DomainException($code . ': ' . $message);
    }
    private static function textRequired($c, $key) {
        if (!isset($c[$key]) || !is_string($c[$key]) || trim($c[$key]) === '') {
            self::deny('evidence_missing', 'Missing required evidence: ' . $key);
        }
    }
    public static function transition($aggregate, $from, $to, $context) {
        if (!is_string($aggregate) || !is_string($from) || !is_string($to) || !is_array($context)) {
            self::deny('invalid_input', 'Aggregate, states and context are required');
        }
        $aggregate = strtoupper($aggregate);
        $from = strtoupper($from);
        $to = strtoupper($to);
        $actor = isset($context['actor_id']) ? (string)$context['actor_id'] : '';
        $maker = isset($context['maker_id']) ? (string)$context['maker_id'] : '';
        if ($actor === '') self::deny('actor_missing', 'Authenticated actor is required');

        $map = [
            'WORKFORCE_REQUEST' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED'], 'APPROVED'=>['OPEN'], 'OPEN'=>['FILLED','CANCELLED']],
            'WORKFORCE_PLAN' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED','NEEDS_INFORMATION'], 'NEEDS_INFORMATION'=>['SUBMITTED'], 'REJECTED'=>['DRAFT'], 'APPROVED'=>['CLOSED']],
            'POSITION' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED','NEEDS_INFORMATION'], 'NEEDS_INFORMATION'=>['SUBMITTED'], 'REJECTED'=>['DRAFT'], 'APPROVED'=>['ACTIVE','INACTIVE'], 'ACTIVE'=>['INACTIVE']],
            'REQUISITION' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED','NEEDS_INFORMATION'], 'NEEDS_INFORMATION'=>['SUBMITTED'], 'REJECTED'=>['DRAFT'], 'APPROVED'=>['OPEN','CANCELLED'], 'OPEN'=>['FILLED','CANCELLED']],
            'APPLICATION' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['SCREENING','REJECTED'], 'SCREENING'=>['INTERVIEW','REJECTED'], 'INTERVIEW'=>['RECOMMENDED','REJECTED'], 'RECOMMENDED'=>['SELECTED','REJECTED'], 'SELECTED'=>['OFFERED'], 'OFFERED'=>['ACCEPTED','DECLINED']],
            'CONTRACT' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['SIGNED','REJECTED'], 'SIGNED'=>['APPROVED'], 'APPROVED'=>['ONBOARDING'], 'ONBOARDING'=>['READY_FOR_ACTIVATION'], 'READY_FOR_ACTIVATION'=>['ACTIVE']],
            'ATTENDANCE_EXCEPTION' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED'], 'APPROVED'=>['LOCKED']],
            'LEAVE_REQUEST' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED','NEEDS_INFORMATION'], 'NEEDS_INFORMATION'=>['SUBMITTED'], 'APPROVED'=>['CANCELLED'], 'REJECTED'=>['CANCELLED']],
            'COMPENSATION_CHANGE' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED','NEEDS_INFORMATION'], 'NEEDS_INFORMATION'=>['SUBMITTED'], 'APPROVED'=>['EFFECTIVE'], 'EFFECTIVE'=>['SUPERSEDED']],
            'LEGAL_RULE' => ['DRAFT'=>['LEGAL_REVIEW'], 'LEGAL_REVIEW'=>['APPROVED','REJECTED'], 'APPROVED'=>['EFFECTIVE','RETIRED'], 'EFFECTIVE'=>['SUPERSEDED','RETIRED']],
            'PAYROLL_RUN' => ['DRAFT'=>['PREFLIGHT_BLOCKED','READY'], 'PREFLIGHT_BLOCKED'=>['DRAFT','READY'], 'READY'=>['CALCULATED','PREFLIGHT_BLOCKED'], 'CALCULATED'=>['SUBMITTED','DRAFT'], 'SUBMITTED'=>['APPROVED','REJECTED'], 'REJECTED'=>['DRAFT'], 'APPROVED'=>['POSTED'], 'POSTED'=>['SETTLEMENT_PENDING'], 'SETTLEMENT_PENDING'=>['PARTIALLY_SETTLED','SETTLED'], 'PARTIALLY_SETTLED'=>['PARTIALLY_SETTLED','SETTLED'], 'SETTLED'=>['RECONCILED'], 'RECONCILED'=>['CLOSED']],
            'PAYOUT_INSTRUCTION' => ['PENDING'=>['PROCESSING','CANCELLED'], 'PROCESSING'=>['PAID','FAILED','RETURNED'], 'FAILED'=>['PROCESSING','CANCELLED'], 'RETURNED'=>['PROCESSING','CANCELLED'], 'PAID'=>['RECONCILED'], 'RECONCILED'=>['CLOSED']],
            'REMITTANCE' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['PAID','FAILED'], 'FAILED'=>['SUBMITTED'], 'PAID'=>['RECONCILED'], 'RECONCILED'=>['CLOSED']],
            'SEPARATION_CASE' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['APPROVED','REJECTED'], 'APPROVED'=>['CLEARANCE'], 'CLEARANCE'=>['FINAL_CALCULATION'], 'FINAL_CALCULATION'=>['REVIEWED'], 'REVIEWED'=>['APPROVED_FOR_PAYMENT'], 'APPROVED_FOR_PAYMENT'=>['SETTLEMENT_PENDING'], 'SETTLEMENT_PENDING'=>['SETTLED'], 'SETTLED'=>['CLOSED']],
            'PAYEE_REQUEST' => ['DRAFT'=>['SUBMITTED'], 'SUBMITTED'=>['AUTO_CHECK'], 'AUTO_CHECK'=>['NEEDS_INFORMATION','LEGAL_TAX_REVIEW','APPROVED'], 'NEEDS_INFORMATION'=>['SUBMITTED'], 'LEGAL_TAX_REVIEW'=>['APPROVED','REJECTED'], 'APPROVED'=>['READY_FOR_PAYMENT'], 'READY_FOR_PAYMENT'=>['PAID','PARTIALLY_PAID','PAYMENT_FAILED'], 'PARTIALLY_PAID'=>['PARTIALLY_PAID','PAID','PAYMENT_FAILED'], 'PAYMENT_FAILED'=>['READY_FOR_PAYMENT','CANCELLED'], 'PAID'=>['RECONCILED'], 'RECONCILED'=>['CLOSED']]
        ];
        if (!isset($map[$aggregate][$from]) || !in_array($to, $map[$aggregate][$from], true)) {
            self::deny('transition_not_allowed', 'Transition ' . $aggregate . ' ' . $from . ' -> ' . $to . ' is not permitted');
        }
        $sensitive = ['APPROVED','POSTED','PAID','CLOSED','ACTIVE','SETTLED','RECONCILED','EFFECTIVE','APPROVED_FOR_PAYMENT'];
        if (in_array($to, $sensitive, true) && $maker !== '' && $actor === $maker) {
            self::deny('self_approval_blocked', 'Maker cannot approve or finalize their own high-impact record');
        }
        if ($aggregate === 'WORKFORCE_REQUEST' && $to === 'APPROVED') self::textRequired($context, 'budget_reference');
        if ($aggregate === 'WORKFORCE_PLAN' && $to === 'APPROVED') {
            self::textRequired($context, 'budget_reference');
            if (($context['budget_positive'] ?? false) !== true || ($context['headcount_valid'] ?? false) !== true) self::deny('workforce_plan_approval_blocked', 'Positive budget and valid headcount are required');
        }
        if ($aggregate === 'POSITION' && $to === 'APPROVED') {
            self::textRequired($context, 'position_justification');
            if (($context['plan_budget_approved'] ?? false) !== true) self::deny('position_plan_not_approved', 'An approved workforce plan is required');
        }
        if ($aggregate === 'REQUISITION' && $to === 'APPROVED') {
            self::textRequired($context, 'budget_reference');
            if (($context['headcount_capacity_verified'] ?? false) !== true) self::deny('requisition_capacity_exceeded', 'Approved plan headcount must cover this requisition');
        }
        if ($aggregate === 'APPLICATION' && $to === 'SELECTED') self::textRequired($context, 'human_selection_rationale');
        if ($aggregate === 'CONTRACT' && $to === 'SIGNED') {
            self::textRequired($context, 'private_contract_ref'); self::textRequired($context, 'signed_date');
        }
        if ($aggregate === 'CONTRACT' && $to === 'ACTIVE') {
            foreach (['contract_approved','documents_verified','onboarding_complete','compensation_approved','access_approved'] as $g) {
                if (($context[$g] ?? false) !== true) self::deny('activation_prerequisite_missing', $g);
            }
        }
        if ($aggregate === 'ATTENDANCE_EXCEPTION' && $to === 'APPROVED') {
            self::textRequired($context, 'correction_reason'); self::textRequired($context, 'evidence_ref');
        }
        if ($aggregate === 'COMPENSATION_CHANGE' && $to === 'APPROVED') {
            self::textRequired($context, 'source_approval_ref'); self::textRequired($context, 'effective_date');
        }
        if ($aggregate === 'LEGAL_RULE' && in_array($to, ['APPROVED','EFFECTIVE'], true)) {
            if (strtoupper((string)($context['review_status'] ?? '')) !== 'LEGAL_REVIEW') self::deny('legal_review_missing', 'Independent legal review record is required');
            foreach (['official_source_ref','source_sha256','reviewer_ref','test_pack_ref','effective_date'] as $k) self::textRequired($context, $k);
            if (!preg_match('/^[a-f0-9]{64}$/i', (string)$context['source_sha256'])) self::deny('legal_hash_invalid', 'Legal source SHA-256 is invalid');
        }
        if ($aggregate === 'PAYROLL_RUN' && $to === 'READY') {
            if (($context['preflight_passed'] ?? false) !== true || ($context['input_snapshot_frozen'] ?? false) !== true) self::deny('payroll_preflight_failed', 'Preflight and frozen input snapshot are required');
            self::textRequired($context, 'input_snapshot_sha256');
            if (!preg_match('/^[a-f0-9]{64}$/i', (string)$context['input_snapshot_sha256'])) self::deny('snapshot_hash_invalid', 'Input snapshot SHA-256 is invalid');
        }
        if ($aggregate === 'PAYROLL_RUN' && $to === 'CALCULATED') {
            if (($context['rules_approved_and_effective'] ?? false) !== true || ($context['exceptions_resolved'] ?? false) !== true) self::deny('payroll_calculation_blocked', 'Approved rules and resolved exceptions are required');
            self::textRequired($context, 'calculation_hash');
        }
        if ($aggregate === 'PAYROLL_RUN' && $to === 'APPROVED') {
            foreach (['rules_approved_and_effective','exceptions_resolved','totals_reconciled'] as $g) if (($context[$g] ?? false) !== true) self::deny('payroll_approval_blocked', $g);
            self::textRequired($context, 'calculation_hash');
        }
        if ($aggregate === 'PAYROLL_RUN' && $to === 'POSTED') {
            if (($context['approved'] ?? false) !== true || ($context['posting_transaction_balanced'] ?? false) !== true) self::deny('payroll_posting_blocked', 'Approval and balanced journal are required');
            self::textRequired($context, 'posting_idempotency_key'); self::textRequired($context, 'journal_ref');
        }
        if ($aggregate === 'PAYROLL_RUN' && $to === 'SETTLED') {
            if (($context['all_recipients_confirmed'] ?? false) !== true) self::deny('settlement_incomplete', 'Every recipient requires an independently confirmed payment result');
            self::textRequired($context, 'external_batch_ref');
        }
        if ($aggregate === 'PAYROLL_RUN' && $to === 'RECONCILED') {
            foreach (['ledger_reconciled','payouts_reconciled','remittances_reconciled','period_lock_confirmed'] as $g) if (($context[$g] ?? false) !== true) self::deny('reconciliation_incomplete', $g);
        }
        if ($aggregate === 'PAYOUT_INSTRUCTION' && $to === 'PAID') {
            self::textRequired($context, 'external_payment_ref'); self::textRequired($context, 'confirmation_ref');
        }
        if (in_array($aggregate, ['PAYOUT_INSTRUCTION','REMITTANCE'], true) && $to === 'RECONCILED') self::textRequired($context, 'reconciliation_ref');
        if ($aggregate === 'SEPARATION_CASE' && $to === 'APPROVED_FOR_PAYMENT') {
            foreach (['legal_calculation_reviewed','clearance_complete','totals_reconciled'] as $g) if (($context[$g] ?? false) !== true) self::deny('final_settlement_blocked', $g);
            self::textRequired($context, 'final_calculation_hash');
        }
        if ($aggregate === 'PAYEE_REQUEST' && $to === 'APPROVED') {
            if (($context['classification_resolved'] ?? false) !== true || ($context['withholding_reviewed'] ?? false) !== true) self::deny('payee_tax_review_required', 'Classification and withholding review are required');
            self::textRequired($context, 'engagement_ref'); self::textRequired($context, 'deliverable_acceptance_ref');
        }
        return ['allowed'=>true,'aggregate'=>$aggregate,'from'=>$from,'to'=>$to,'actor_id'=>$actor,'evidence_keys'=>array_keys($context),'persistent_write_performed'=>false];
    }
}
