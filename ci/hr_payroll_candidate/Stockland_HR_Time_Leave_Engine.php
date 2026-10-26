<?php
/**
 * Candidate leave-ledger projection core.
 *
 * This is a pure calculation with no database writes. It is not wired into the live ERP.
 * It will not invent leave entitlements: rule versions and the append-only input ledger
 * must supply approved, effective-dated policy and transaction evidence.
 */
final class Stockland_HR_Time_Leave_Engine {
    private static function deny($code, $message) {
        throw new InvalidArgumentException($code . ': ' . $message);
    }
    private static function unitsToMinor($value, $field) {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,4})?$/D', $value)) {
            self::deny('invalid_leave_quantity', $field . ' must be a non-negative decimal string with up to four decimal places');
        }
        $p = explode('.', $value, 2);
        if (strlen($p[0]) > 10) self::deny('leave_quantity_overflow', $field . ' exceeds supported bounds');
        $frac = isset($p[1]) ? str_pad($p[1], 4, '0') : '0000';
        return ((int)$p[0] * 10000) + (int)$frac;
    }
    private static function minorToUnits($minor) {
        if (!is_int($minor)) self::deny('leave_internal_error', 'internal leave quantity must be an integer');
        $sign = $minor < 0 ? '-' : '';
        $n = abs($minor);
        return $sign . intdiv($n, 10000) . '.' . str_pad((string)($n % 10000), 4, '0', STR_PAD_LEFT);
    }
    private static function date($v) {
        if (!is_string($v) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $v)) return false;
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $dt && $dt->format('Y-m-d') === $v;
    }
    private static function validatePolicy($version, $rules, $asOf, $allowSynthetic) {
        if (!is_array($rules) || ($rules['policy_version'] ?? null) !== $version) {
            self::deny('leave_policy_missing', 'a matching, versioned leave policy is required for every ledger transaction');
        }
        $status = $rules['status'] ?? '';
        $synthetic = $status === 'SYNTHETIC_TEST_ONLY';
        if ($synthetic && ($allowSynthetic !== true || ($rules['synthetic_only'] ?? false) !== true)) {
            self::deny('synthetic_policy_blocked', 'synthetic policy is permitted only by an explicit test caller');
        }
        if (!$synthetic && $status !== 'APPROVED') self::deny('leave_policy_not_approved', 'leave policy is not approved');
        foreach (['policy_version','leave_type','unit','effective_from','source_sha256','policy_document_ref','allow_negative_balance'] as $field) {
            if (!array_key_exists($field, $rules) || $rules[$field] === null || $rules[$field] === '') self::deny('leave_policy_incomplete', 'missing policy field: ' . $field);
        }
        if (!in_array($rules['unit'], ['DAYS','HOURS'], true) || !is_bool($rules['allow_negative_balance'])) {
            self::deny('leave_policy_invalid', 'unit or negative-balance flag is invalid');
        }
        if (!self::date($rules['effective_from']) || (isset($rules['effective_to']) && $rules['effective_to'] !== null && !self::date($rules['effective_to']))) {
            self::deny('leave_policy_dates_invalid', 'policy effective dates must be ISO calendar dates');
        }
        if ($asOf < $rules['effective_from'] || (isset($rules['effective_to']) && $rules['effective_to'] !== null && $asOf > $rules['effective_to'])) {
            self::deny('leave_policy_out_of_date', 'no policy is effective for the requested balance date');
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', (string)$rules['source_sha256'])) self::deny('leave_policy_hash_invalid', 'policy source must carry a SHA-256 hash');
        if (!$synthetic && (empty($rules['reviewer_ref']) || empty($rules['approved_at']) || empty($rules['approval_evidence_ref']))) {
            self::deny('leave_policy_approval_evidence_missing', 'approved policy requires a reviewer, approval time and approval evidence reference');
        }
        return $synthetic;
    }

    /**
     * Compute a point-in-time leave balance from approved, append-only transactions.
     * Unapproved transactions are not added to the balance; any effective pending item
     * makes the result NEEDS_REVIEW and blocks payroll use until resolved.
     *
     * Transaction fields: txn_ref, employee_ref, leave_type, type, quantity,
     * direction (ADJUSTMENT only), effective_date, status, policy_version,
     * idempotency_key, source_ref, created_by, approved_by, approval_ref.
     */
    public static function balance($employeeRef, $leaveType, $asOfDate, $transactions, $rulesByVersion, $allowSyntheticTestPolicy = false) {
        if (!is_string($employeeRef) || trim($employeeRef) === '' || !is_string($leaveType) || trim($leaveType) === '' ||
            !self::date($asOfDate) || !is_array($transactions) || !is_array($rulesByVersion)) {
            self::deny('leave_balance_input_invalid', 'employee, leave type, valid as-of date, transactions and rule registry are required');
        }
        $sum = 0;
        $applied = 0;
        $pending = 0;
        $pendingEffective = 0;
        $seenKeys = [];
        $trace = [];
        $unit = null;
        $ruleVersionsUsed = [];
        foreach ($transactions as $i => $tx) {
            if (!is_array($tx) || !isset($tx['employee_ref'],$tx['leave_type'],$tx['type'],$tx['quantity'],$tx['effective_date'],$tx['status'],$tx['policy_version'],$tx['idempotency_key'],$tx['source_ref'])) {
                self::deny('leave_transaction_invalid', 'transaction ' . $i . ' is missing required fields');
            }
            if ((string)$tx['employee_ref'] !== $employeeRef || (string)$tx['leave_type'] !== $leaveType) continue;
            if (!self::date($tx['effective_date'])) self::deny('leave_transaction_date_invalid', 'transaction has invalid effective date');
            if ($tx['effective_date'] > $asOfDate) continue;
            if (isset($seenKeys[$tx['idempotency_key']])) self::deny('duplicate_leave_idempotency_key', 'duplicate leave transaction idempotency key');
            $seenKeys[$tx['idempotency_key']] = true;
            $version = (string)$tx['policy_version'];
            if (!isset($rulesByVersion[$version])) self::deny('leave_policy_missing', 'missing historical rule bundle for version ' . $version);
            $rules = $rulesByVersion[$version];
            self::validatePolicy($version, $rules, (string)$tx['effective_date'], $allowSyntheticTestPolicy);
            if (strtoupper((string)$rules['leave_type']) !== strtoupper($leaveType)) self::deny('leave_policy_type_mismatch', 'policy leave type does not match transaction');
            if ($unit !== null && $unit !== $rules['unit']) self::deny('leave_unit_mismatch', 'balance cannot combine different units');
            $unit = $rules['unit'];
            $ruleVersionsUsed[$version] = true;
            $status = strtoupper((string)$tx['status']);
            if ($status !== 'APPROVED') {
                $pending++;
                if ($tx['effective_date'] <= $asOfDate) $pendingEffective++;
                $trace[] = ['txn_ref'=>$tx['txn_ref'] ?? null,'type'=>$tx['type'],'quantity'=>$tx['quantity'],'status'=>$status,'applied'=>false,'reason'=>'NOT_APPROVED'];
                continue;
            }
            foreach (['txn_ref','created_by','approved_by','approval_ref'] as $field) {
                if (!isset($tx[$field]) || !is_string($tx[$field]) || trim($tx[$field]) === '') self::deny('leave_transaction_evidence_missing', 'approved transaction missing ' . $field);
            }
            if ((string)$tx['created_by'] === (string)$tx['approved_by']) self::deny('leave_self_approval_blocked', 'leave transaction maker cannot approve their own posted balance change');
            $qty = self::unitsToMinor($tx['quantity'], 'transaction quantity');
            $type = strtoupper((string)$tx['type']);
            if (in_array($type, ['ACCRUAL','CARRY_FORWARD','CREDIT'], true)) {
                $delta = $qty;
            } elseif (in_array($type, ['USE','EXPIRY','DEBIT'], true)) {
                $delta = -$qty;
            } elseif ($type === 'ADJUSTMENT') {
                $direction = strtoupper((string)($tx['direction'] ?? ''));
                if (!in_array($direction, ['CREDIT','DEBIT'], true)) self::deny('leave_adjustment_direction_invalid', 'adjustment requires explicit CREDIT or DEBIT direction');
                if (trim((string)($tx['reason'] ?? '')) === '') self::deny('leave_adjustment_reason_missing', 'leave adjustment reason is required');
                $delta = $direction === 'CREDIT' ? $qty : -$qty;
            } else {
                self::deny('leave_transaction_type_invalid', 'unsupported leave transaction type ' . $type);
            }
            $sum += $delta;
            $applied++;
            $trace[] = ['txn_ref'=>$tx['txn_ref'],'type'=>$type,'quantity'=>$tx['quantity'],'delta'=>$delta >= 0 ? self::minorToUnits($delta) : self::minorToUnits($delta),'status'=>'APPROVED','applied'=>true,'running_balance'=>self::minorToUnits($sum),'policy_version'=>$version];
        }
        if ($unit === null) self::deny('leave_policy_missing', 'no approved leave transactions or policy unit was found for this employee and leave type');
        $currentRules = null;
        foreach (array_keys($ruleVersionsUsed) as $v) {
            $candidate = $rulesByVersion[$v];
            if ($candidate['effective_from'] <= $asOfDate && (!isset($candidate['effective_to']) || $candidate['effective_to'] === null || $asOfDate <= $candidate['effective_to'])) {
                if ($currentRules === null || $candidate['effective_from'] > $currentRules['effective_from']) $currentRules = $candidate;
            }
        }
        if ($currentRules === null) self::deny('leave_policy_out_of_date', 'a current policy version for the as-of date is required even when the balance has historical transactions');
        if ($sum < 0 && $currentRules['allow_negative_balance'] !== true) self::deny('leave_balance_negative', 'approved leave ledger would produce a negative balance');
        return [
            'employee_ref'=>$employeeRef,
            'leave_type'=>$leaveType,
            'as_of_date'=>$asOfDate,
            'unit'=>$unit,
            'balance'=>$sum,
            'balance_display'=>self::minorToUnits($sum),
            'applied_transaction_count'=>$applied,
            'pending_transaction_count'=>$pending,
            'pending_effective_transaction_count'=>$pendingEffective,
            'status'=>$pendingEffective > 0 ? 'NEEDS_REVIEW' : 'CALCULATED',
            'payroll_eligible'=>$pendingEffective === 0,
            'policy_versions_used'=>array_values(array_keys($ruleVersionsUsed)),
            'ledger_trace'=>$trace,
            'persistent_write_performed'=>false
        ];
    }
}
