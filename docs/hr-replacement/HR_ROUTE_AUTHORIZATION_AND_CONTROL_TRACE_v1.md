# Stockland ERP 16.1.95 — HR route authorization and control trace v1

**Date:** 26 October 2026. **Scope:** static source trace of the exact 16.1.95 review artifact; not a live endpoint penetration test or confirmation that this version is installed on production.

## Current control picture

The HR REST class contains **13 route-registration call sites and 15 distinct method/path operations** because the employee and payroll collection registrations each combine GET and POST. All registrations include a literal permission callback. That is only the first layer: effective authority also depends on callback implementation, handler checks, state/record ownership, row locks and side effects.

Controls already present:
- Employee creation is Management-prepared and starts at PENDING. A separate Owner-only decision path locks the record, rejects non-pending records and rejects the creator approving their own employee file. Employee/audit/workflow/notification work is transactional.
- Management prepares and submits its own DRAFT payroll. The Owner approves separately; the submitter cannot approve its own run.
- Payroll-line edits are DRAFT-only, use allowance-component snapshots/concurrent-edit checks and require current rule-version calculations. Exemption classification is Owner-restricted and checks legal basis/evidence against the line total.
- Payroll approval reconciles gross/deductions/net and employer pension, posts through the shared GL journal service and links the journal to the payroll period.
- The current code prevents `RETAINER_SERVICE` employee rows from receiving employee-PAYE/pension compensation through this route.

These are useful foundations to retain and test; they do not make the current four-table subsystem a complete HR replacement.

## High-priority gaps confirmed by source

1. **Statutory basis remains blocked.** `Stockland_Payroll_Statutory::calculate()` subtracts employee pension from the PAYE base. Written Ethiopian tax/pension interpretation is needed before changing or certifying this formula.
2. **A PAID flag is not bank settlement evidence.** `payroll_pay` currently accepts only BANK/CASH, posts a second GL journal, records the actor/time/method, and marks the period PAID. It does not require a bank transaction reference or payment-file identifier, per-employee disbursement result, failed/partial/retry workflow, external reconciliation, or withheld-tax/pension remittance evidence. In a successor, distinguish payment instruction, submitted batch, confirmed/partial/failed payout, statutory remittance, reconciliation and close.
3. **Privacy in audit history.** Employee update auditing serializes the prior employee record and changed fields; those can include phone, email and notes. The hash chain proves tamper evidence but does not replace audit-reader RBAC or data minimization. Preserve essential before/after evidence while avoiding unnecessary PII duplication.
4. **Role model is shallow.** Current HR/payroll checks mainly use Owner and Management. Add separate scoped HR, payroll maker, finance/AP operator, qualified legal/tax reviewer and final approver abilities only where needed. Do not let an owner approval bypass a rule marked LEGAL_REVIEW.
5. **Lifecycle, documents and social reporting are absent.** No route family was found for candidates/recruitment, contracts/onboarding, attendance, leave, grade/promotion, exit/final settlement, individual creator/service-provider AP, secure HR resource downloads or manual social metric snapshots.
6. **Referential integrity must be checked explicitly.** The static 49-table DDL has zero explicit foreign-key constraints. Copy-only migration tests must check orphan/duplicate employee-compensation, payroll-period/line, GL-source, payment-allocation and location/user relationships.

## Per-operation source trace

The CSV beside this document enumerates all 15 method/path operations with registration callback, effective access, observed guard and follow-up gap. Counts are from source, not live request telemetry.

## Acceptance tests before HR/payroll replacement

- Anonymous, wrong-role, revoked/deactivated user, incorrect location/record, and altered object-ID requests are denied for every new/changed route.
- Management cannot approve its own employee or payroll item; Owner-only classification/payment remains protected by both route and handler checks.
- Compensation fields are shown only to roles with an approved need to know; audit fields don't expose unnecessary personal data.
- Payroll transitions reject duplicate submit/approve/pay, stale draft edits, posted line edits and invalid retry; every payment event can reconcile to an external bank/cash reference and to each employee outcome.
- Tax classification records rule ID/version/effective date, source/article, reviewer, evidence, and resolution state. Owner UI cannot convert LEGAL_REVIEW into an automatic exemption.
- Private HR docs reject anonymous, wrong-role and wrong-person access even by direct URL, and each authenticated download is auditable.
- Copy-only migration, orphan checks, backup/restore/rollback and two full parallel payroll periods are signed off before cutover.
