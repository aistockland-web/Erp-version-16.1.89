# Legacy HR dependency and cutover map v1.0

Date: 27 October 2026  
Source package: 16.1.95, SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.  
Authority: Notion HR Design Reset v0.5.  
Status: static package inspection; not a live data-health report or migration authorization.

## What the 16.1.95 source actually does

The current package has four HR-owned table keys in `includes/class-stockland-hr.php::table_keys()`: `employees`, `employee_compensation`, `payroll_periods`, and `payroll_lines`. The package DDL defines them directly in `Stockland_HR::install()`. The audited static table registry is 29 core + 16 finance/procurement + four HR/payroll = 49 tables. None of the static DDL declarations declares an explicit SQL FOREIGN KEY constraint; applications must therefore prove referential integrity through explicit validations and controlled migrations. This source finding is not a query against the live database.

### Table → current source behavior → replacement treatment

| Legacy source | Current behavior found in source | Target authority / migration treatment |
|---|---|---|
| `employees` | Read by HR bootstrap/list; employee code duplicate check; owner-only direct create; decision/approval; update; compensation entry; active-employee payroll generation; system-health checks | Target must use one canonical People identity with a separate employment relationship/assignment. Preserve legacy ID and employee-code crosswalk. No employee becomes active until the approved recruitment, contract, onboarding and payroll setup flow completes. Never auto-fill salary or tax data. |
| `employee_compensation` | Effective-dated rows are created by HR; current row is selected by employee and date; payroll generation resolves compensation segments for a period; classification notes/status and allowance breakdown feed preflight/approval | Target compensation assignments and individual pay-item decisions must be effective-dated, approval-gated and explainable. Migrate old rows as unapproved evidence where classification cannot be proven. Do not infer legal treatment from a dropdown name or legacy 7%/11% defaults. |
| `payroll_periods` | Period creation and unique month guard; status lifecycle; period row is locked during generation/edit/submit/approve/pay; totals are recomputed from lines; approver/time/journal ID stored; payment user/time/method stored | New payroll run must freeze input/rule versions, store versioned approvals and linked ledger entries. Payroll-period identity cannot silently overlap or double-post. Legacy period ID/key/journal links must be mapped and reconciled before read-only transition. |
| `payroll_lines` | One row per period and employee; stores employee/code/name/location snapshots and aggregate salary/allowance/deduction/tax/pension/net values plus tax-rule version/status. Draft edits/classification are gated, submit and approval require calculated lines, approval reconciles totals and posts a journal | Target needs immutable per-run, per-worker calculation evidence and itemized details/rule basis; a correction must link to the original instead of rewriting posted lines. Preserve snapshots/history and reconcile every line to period totals and GL/PLEDGER. |

## Current call graph / touchpoints (static source interpretation)

- `includes/rest/class-rest-hr.php::bootstrap()` returns employee list, payroll periods and a hardcoded/source-defined statutory-rule display catalogue.
- `employee_rows()` joins employees to locations; when payroll visibility is enabled it selects the most recent compensation row whose effective date is not after today.
- `employee_create()`, `employee_decision()`, and `employee_update()` create, approve/update legacy employee masters. The replacement must retire direct creation as the normal hiring path, while preserving controlled migration/import where explicitly approved.
- `compensation_create()`, `compensation_segments()`, and `compensation_notes()` create/read effective-dated compensation and feed payroll generation / classification review.
- `payroll_create()`, `payroll_rows()`, `payroll_totals()`, `payroll_generate()`, `payroll_line_update()`, `payroll_line_classification_update()`, `payroll_detail()`, and `payroll_submit()` manage period, snapshot, draft, and submission.
- `payroll_approve()` locks the period and lines, verifies calculated status/rule version and allowance-exemption evidence, reconciles totals, posts through `Stockland_Accounting::post_journal()`, stores approval/journal metadata and writes an audit event.
- `payroll_pay()` currently requires Owner, posts a `PAYROLL_PAYMENT` journal to debit salary payable and credit bank/cash, then marks the entire legacy period `PAID` with payment method and actor/time. It does **not** persist a required external batch/bank/cash reference, per-worker paid/failed/returned results, partial settlement, external confirmation/reconciliation or tax/pension remittance receipt. The replacement must separate these states: `GL_POSTED` is not `PAYMENT_SENT`, `SETTLED`, or `RECONCILED`.
- `includes/rest/class-rest-app.php::health()` includes legacy HR schema and compensation readiness diagnostics. New Health logic must audit the new authoritative model and retain legacy evidence during transition; it must not make a payroll line or compensation “pass” by editing it.
- `includes/class-stockland-accounting.php::post_journal()` is the existing journal service and is used by other ERP transactions. The new payroll/settlement flow must reuse it through the reviewed contract rather than writing GL rows directly.

Route registration finding: the legacy HR controller has 13 route registrations and each has a literal `permission_callback`. That syntactic fact does not prove correct role, object ownership, field-level privacy, location scope, transition or maker-checker enforcement. Test each actual endpoint positively and negatively.

Detailed function/line navigation is emitted by the Phase 0 audit artifact `hr_legacy_dependency_context.csv` when the updated Phase 0 workflow completes. All lexical/function-context mappings are investigation leads—not proof that a path is executed in production.

## Current legacy field inventory (static DDL)

### employees
`id`, `employee_id`, `wp_user_id`, `full_name`, `branch_location_id`, `position_title`, `employment_type`, `hire_date`, `termination_date`, `status`, `phone`, `email`, `notes`, `created_by`, `updated_by`, `created_at`, `updated_at`.

### employee_compensation
`id`, `employee_id`, `effective_from`, `base_salary`, `allowance_total`, `standard_deduction_total`, `currency`, `notes`, `taxable_allowance_total`, `exempt_allowance_total`, `pensionable_salary`, `statutory_classification_status`, `statutory_classification_note`, `allowance_breakdown_json`, `created_by`, `created_at`.

### payroll_periods
`id`, `period_key`, `start_date`, `end_date`, `status`, `employee_count`, `gross_total`, `deduction_total`, `income_tax_total`, `employee_pension_total`, `employer_pension_total`, `other_deduction_total`, `net_total`, `journal_id`, `approved_by`, `approved_at`, `paid_by`, `paid_at`, `payment_method`, `notes`, `created_by`, `created_at`, `updated_at`.

### payroll_lines
`id`, `period_id`, `employee_id`, `employee_code`, `employee_name`, `branch_location_id`, `base_salary`, `allowance_total`, `deduction_total`, `taxable_allowance_total`, `exempt_allowance_total`, `other_deduction_total`, `gross_pay`, `taxable_income`, `income_tax`, `income_tax_rate`, `pensionable_salary`, `employee_pension_rate`, `employer_pension_rate`, `employee_pension`, `employer_pension`, `tax_rule_version`, `statutory_status`, `employer_cost_total`, `net_pay`, `allowance_breakdown_json`, `notes`, `status`, `created_at`, `updated_at`.

## Required migration/cutover map

1. Freeze a source snapshot in a disposable staging clone; record package hash, schema fingerprint, row counts, column hashes, row-level checksums using protected tooling, and backup/restore receipt. Do not put PII, amounts, bank details or employee records into GitHub issues or CI logs.
2. Trace every reader/writer/export/report/health check/import/setup path to all four legacy keys. Add test coverage for every discovered dependency. Static search is a starting inventory, not closure.
3. Map each existing employee to exactly one target People identity and employment relationship; preserve both legacy IDs. Any duplicate/missing/conflicting identity must be reviewed, not auto-merged.
4. Map every compensation row including effective dates and classification status, keeping unapproved tax/pension facts explicitly unresolved. Never synthesize salary values.
5. Map each payroll period, line, approval, journal, payment reference and audit event. Reconcile worker count, aggregate totals, line totals, net pay, tax/pension payable, employer cost and source journal/PLEDGER amounts.
6. Run old and new calculation in parallel only using synthetic fixtures first, then approved sanitized staging data with rule versions and expected results signed by the qualified adviser. Differences require disposition; do not force the values to match by mutation.
7. Rehearse rollback: restore legacy state, verify all source IDs/journals and audit chain, prove no duplicate journal or payout if the candidate is interrupted/retried.
8. Keep the four legacy tables intact and read-only during the approved transition; disable them as active authorities only behind an explicit approved feature-flag/cutover, after all current consumers are migrated and acceptance is signed. Physical table deletion is a later, separate Owner-approved action.

## Concrete replacement gates derived from this source

- Direct employee creation is replaced by approved requisition → human selection → contract → onboarding → activate.
- Compensation is not a set of free-text totals only; pay items, effective date, basis/evidence, legal treatment status and independent approval are first-class.
- Payroll needs frozen input/rule snapshots and itemized auditable lines, not only aggregates or mutable draft fields.
- Payment/settlement needs a recipient-level state and external evidence separate from journal posting. Failed/returned payment retries must be idempotent and never duplicate the payroll expense or payable.
- Statutory remittance is a tracked obligation with amount, period, due date under approved rules, submission/receipt, payment confirmation, actual date, status, variance and GL reference.
- All personal/financial data exports, downloads and audit readers require field-level least privilege and retention.
- Current PAYE tax base, post-amendment transport exclusion, digital creator thresholds/classification and pensionable-pay basis remain `LEGAL_REVIEW` until the exact current authoritative instrument/effective dates and qualified Ethiopian reviewer approval are attached.
- No production installation, live schema change, migration, payroll mutation or legal activation is authorized by this audit.
