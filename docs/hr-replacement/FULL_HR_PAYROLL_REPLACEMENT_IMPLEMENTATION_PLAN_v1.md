# Stockland Ethiopia ERP — Full HR, Payroll & Individual Payments Replacement
**Implementation specification v1.0 • 27 October 2026 • Engineering branch only**

## 1. Decision and safety boundary

This work is for a complete HR/payroll operating system, not a resource-document-only add-on and not an overlay that retains the four legacy HR tables as runtime authorities. The intended target system of record is the new HR domain: canonical person/employment identity, effective-dated contracts and compensation, workforce and recruitment, time/leave, statutory rules, payroll calculations, independent approvals, disbursement/remittance, final settlement, audit, and central individual-payee/AP workflows.

The existing four tables—**employees, employee_compensation, payroll_periods and payroll_lines**—are migration sources only. Do not drop, rename, truncate, change their DDL, rewrite posted history, or populate missing values to force a green health check. Keep them intact and read-only during build and parallel run. The new target must not depend on their IDs or schemas for normal operation after cutover. A temporary migration map may link old IDs to new IDs for reconciliation and rollback.

Source baseline:
- Installer: STOCKLAND_ERP_16.1.95_CANONICAL_WORDPRESS_UPDATE.zip
- SHA-256: 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625
- Size: 1,102,405 bytes; 75 unique members; ZIP CRC validation passed in this session.
- Repository: https://github.com/aistockland-web/Erp-version-16.1.89
- Notion design authority: https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d?pvs=204
- Required product scope: https://app.notion.com/p/4026db0f767e81aaa3ecc4383f5402f9
- HR/SOP resource acceptance register: https://app.notion.com/p/4056db0f767e810d8ca2cbc1fe3e830e?pvs=204
- GitHub tracking: https://github.com/aistockland-web/Erp-version-16.1.89/issues/15 and https://github.com/aistockland-web/Erp-version-16.1.89/issues/23

Earlier fixed 27-table sketches are superseded in the current Notion design. Do not copy that retired list or announce a frozen table count. Build the logical model, reuse shared ERP capabilities only where source-level inspection proves integrity/security guarantees, and freeze physical table count after dependency/data-health review and ERD approval.

## 2. Product outcome

Deliver one native Stockland ERP/PWA experience under HR & People → Workforce → Employee Lifecycle → Time & Leave → Compensation & Benefits → Payroll → Payments & Remittances → Exit & Final Settlement → Reports & Audit → Process Center. Operational users must not need WordPress Dashboard access. WordPress administrators retain normal platform access. The system must work on mobile and desktop and surface real errors instead of substituting zero for query failures or showing decorative non-working controls.

Required outcomes:
1. Recruit and onboard a candidate through approved requisition, human selection, offer/contract, private-document verification, onboarding tasks and separately authorized activation.
2. Maintain a canonical person/employee record, effective-dated contracts, workplace/reporting line, shifts, attendance, leave, learning, performance and lifecycle history.
3. Maintain approved effective-dated compensation components/assignments and benefits without duplicating a base-salary authority.
4. Produce deterministic, explainable payroll calculations from frozen inputs and explicitly approved statutory rules.
5. Enforce preparation, review, Owner approval, payment, statutory remittance and GL/PLEDGER reconciliation as separate stages.
6. Handle increments, retro adjustments, leave of absence, transfer, termination and final settlement through linked corrections, not edits to posted history.
7. Pay creators, influencers, freelancers and other nonemployee service payees through a separate central People & Payments/AP flow, never by fabricating an employee or treating all work as payroll.
8. Provide permission-scoped management reports and timestamped manual social/campaign snapshots where provider metrics are not actually integrated.
9. Supply a versioned HR Process Center with genuine downloadable DOCX/PDF/CSV assets, secure server-side delivery and download auditing.
10. Prove the exact installable ZIP, not just a branch or different test package.

## 3. Domain model — derived from workflows, not from a preset table count

Final normalized physical table count is intentionally **not frozen** by this document. Test whether existing locations, user-location access, workflow/audit, attachment, accounting and payment services can be reused without weakening privacy, append-only history, idempotency or location controls. If a service cannot meet those requirements, add an HR-owned structure. New HR workflows must not use the old four tables as their runtime authority.

### 3.1 Canonical people and workforce demand
- **Person:** one record through candidate-to-employee continuity; person kind/status and employee number; minimized contact/identity fields; lawful-basis/privacy notice, consent where appropriate, retention/deletion review, duplicate-detection keys and restricted-field access.
- **Org position / grade / department / workplace:** use existing ERP location/org identifiers only after mapping and access controls are verified; distinguish legal employer, work location, cost centre, supervisor and payroll company.
- **Workforce plan and plan lines:** year/period, requested/approved headcount, budget/cost estimate, role/grade, department and location, version and approver.
- **Recruitment requisition:** approved position/plan, justification, openings, priority, dates, approval state and accountable manager.
- **Application / interview / assessment:** person + requisition, stage, rubric version, interviewer/panel, evidence, human rationale, disposition, lawful checks, privacy retention.
- **Offer / contract:** employment type and legal scope, template version, effective dates, probation, hours/rest, workplace, manager, notice terms, signature reference/hash, renewal/expiry and approval.
- **Onboarding/access tasks:** versioned checklist, owner, due date, evidence, blockers and completion. Access provisioning/deprovisioning is a separate approval; selection alone never activates an employee.

### 3.2 Employee lifecycle and working time
- **Lifecycle events:** append-only hire, confirmation, probation review, contract extension, location/manager transfer, promotion/demotion, leave of absence/return, compensation change, employee-relations event where lawful, and separation; actor, effective date, reason, evidence and approval chain.
- **Employment documents:** metadata only in ERP (file ID/reference, SHA-256, type/version, verified by/on, expiry, access class, retention). Signed contracts and identity documents must not be served by predictable public media URLs.
- **Shift patterns and assignments:** effective-dated roster, local start/end/break, weekly rest, location scope and reviewed rule references; support overnight shifts without assuming the server timezone.
- **Attendance:** source (manual/device/import), shift/date, punches or bounded duration, exceptions, overtime claim, evidence and approval. Corrections create superseding events rather than silently overwriting source time.
- **Leave policies, requests and leave ledger:** type, eligibility, accrual/carry-over rules and effective dates, opening balance from authorized import, date/time, evidence, coverage, approval/rejection and append-only accrual/use/correction. Balance is calculated/reconciled from the ledger.
- **Learning and performance:** training/certification/expiry, review period/rubric, structured evidence and access class. Avoid unnecessary sensitive notes or unreviewed automatic decisions.

### 3.3 Compensation, benefits and statutory rules
- **Compensation component catalogue:** factual earning/deduction/benefit categories; units and monetary basis. Tax/pension classifications are rule outputs, not user-entered exemptions.
- **Compensation assignment:** effective-dated employee/component amount or formula parameter, currency, frequency, prorating method, evidence, approval, maker/checker and supersession links. Do not infer or copy another person's salary.
- **Benefit enrollment:** benefit, employee, dependent/election if applicable, coverage dates, employee/employer share and approval.
- **Rule sets/components:** jurisdiction, rule kind, source instrument/clause, official URL/file hash, publication/effective dates, scope, calculation basis, parameters/bands, exceptions/evidence, precedence, test vectors, reviewer/approval, date and hash. No arbitrary PHP/expression evaluation. An APPROVED flag alone is insufficient.
- **Pay-item classification:** capture the factual earning first, then apply the approved effective rule. Keep taxability, pensionability and accounting treatment distinct. Unknown/conflicting classification goes to a blocking exception queue.

### 3.4 Payroll calculation and settlement
- **Payroll periods:** local dates, input cutoff, payment date, run kind (regular/off-cycle/correction/final settlement), status/lock, preparer and version. Use unique period/run keys.
- **Payroll runs:** frozen input snapshot/hash, source references, rule IDs/versions, calculator version, calculation timestamp/actor, exception counts/totals/status. A recalculation creates a new immutable run version; it never silently rewrites an approved/posted run.
- **Payroll line details:** employee/component and each statutory step, quantity/rate/base/amount/currency, taxable/pensionable bases, rule version/source, provenance, rounding, before/after values and exception. Reversal/adjustment items link to their original. Money is fixed-point decimal/minor units only.
- **Approvals:** append-only decisions with actor/role, decision/reason, source run hash/version and timestamp; maker cannot approve their own restricted step; duplicate/concurrent approvals reject cleanly.
- **Disbursement batches/results:** bank/cash method, external batch/reference, masked destination only, idempotency key, amount/date, per-person success/failure and recovery/reconciliation references. ERP journal posting is not proof the external bank payment settled.
- **Tax/pension remittances:** liability period, rule/source, due date, return/batch reference, payment evidence, amount and external acknowledgement. Submitted is distinct from paid/reconciled.
- **Ledger integration:** use the verified accounting service or reviewed adapter. Journal must balance; unique source prevents double posting; closed-period guard applies. Map wage expense, employer pension/benefits, PAYE payable, employee pension payable, other approved liabilities and net wages payable only after account mapping review.
- **Final settlement:** separation case, exit date/type/reason/legal review, notice/clearance/handover, final attendance/leave, pay-through date, leave balance/notice/severance only when supported by approved rules/facts, arrears/benefits/deductions, final run, review/Owner approval, payment/remittance, balanced ledger, payslip/statement, service document, access revocation and retention tasks. Uncertain entitlement blocks final posting for review.

### 3.5 Central nonemployee People & Payments workflow
- **Payee profile:** separate creator/contractor/service-payee identity with deduplication and privacy controls; do not classify by name or registration status alone.
- **Engagement:** campaign/service, contract, dates, requester/location, expected cash plus in-kind consideration, approvals and budget.
- **Deliverable/acceptance:** evidence reference, submitted/due/accepted dates, acceptance actor and disputes.
- **Tax decision:** employment-vs-nonemployment facts, digital creator vs ordinary service, TIN/licence/residency where relevant, rule version, reviewer, withholding rationale and exceptions. Never stack withholding by assumption.
- **Payment/AP:** central Finance queue, supporting documents, payee balance, withholding/remittance/payment references, unique idempotency, AP/GL reconciliation. Do not fake an employee, supplier or customer to fit a limited legacy schema.
- **Social/campaign reporting:** platform/account, creator/campaign, inventory gift references, manual Insights snapshot with source/time/period, attribution only where POS/order data exists, freshness and missing-permission flags. No platform passwords/MFA/recovery codes, scraping, unsupported real-time claims or auto-posting.

### 3.6 Process Center/document library
Resource record: stable ID, function, role, language, type, version, state (DRAFT/LEGAL_REVIEW/APPROVED/RETIRED), owner, effective date, superseded version, hash, size/type, access class and reviewed source. Downloads use authenticated server responses with capability/scope checks, nonce, hash/MIME/filename integrity and audit before bytes return. Draft legal resources show a warning. Signed personnel files use a private vault/approved controlled storage, not public template storage.

## 4. Required state machines

All transitions are server-authoritative, actor/scope checked, validated against current state/version, audited and idempotent where a retry is possible.

### 4.1 Recruitment and hire
WORKFORCE_DRAFT → WORKFORCE_SUBMITTED → WORKFORCE_APPROVED/REJECTED → REQUISITION_DRAFT → PENDING_APPROVAL → OPEN → SCREENING → INTERVIEW → SELECTED/REJECTED/HOLD → OFFER_DRAFT → OFFER_APPROVED → CONTRACT_PENDING_SIGNATURE → CONTRACT_SIGNED → ONBOARDING → READY_FOR_ACTIVATION → ACTIVE.
- Requisition points to approved headcount/budget.
- Candidate selection requires documented human rationale and reviewer.
- Activation requires contract/effective dates, mandatory document checks, approved compensation instruction, onboarding completion and access approval.
- Rejection/withdrawal/retention follows the privacy policy.

### 4.2 Attendance and leave
Attendance: CAPTURED → SUBMITTED → REVIEWED/EXCEPTION; corrections create superseding rows. Leave: DRAFT → SUBMITTED → APPROVED/REJECTED/CANCEL_REQUESTED → CANCELLED. Ledger entries post once only after an approved event. Conflicting dates, insufficient balance and unauthorized location block.

### 4.3 Compensation and legal rules
Compensation: DRAFT → SUBMITTED → INDEPENDENTLY_APPROVED → EFFECTIVE → SUPERSEDED. Rule set: DRAFT → LEGAL_REVIEW → TECHNICAL_TESTED → OWNER_APPROVED → EFFECTIVE, and also requires independent professional legal/tax/pension sign-off before statutory use. Expired/unresolved rule or missing test-vector hash fails closed.

### 4.4 Payroll
DRAFT → INPUTS_FROZEN → CALCULATED → MANAGEMENT_REVIEW → SUBMITTED → OWNER_APPROVED → POSTED → DISBURSEMENT_PENDING → PARTIALLY_SETTLED/SETTLED → REMITTANCE_PENDING/RECONCILED → LOCKED.
- Journal post and bank settlement are separate.
- Unknown statutory items, unresolved compensation or unreconciled totals block approval.
- Frozen run uses snapshot/hash; locked period forbids edits. Corrections create an approved later run or reversal.
- Employee breakdown shows gross, taxable base, each allowance/deduction, PAYE, employee/employer pension, net pay, employer cost, rule ID/version, formula trace and rounding.
- Failed bank payments remain distinguishable from successful payments; retry cannot double-pay or double-post.

### 4.5 Exit/final settlement
NOTICE_DRAFT → PENDING_REVIEW → APPROVED_TO_EXIT → CLEARANCE_IN_PROGRESS → READY_FOR_FINAL_CALCULATION → FINAL_RUN_CALCULATED → INDEPENDENTLY_REVIEWED → OWNER_APPROVED → POSTED → PAYMENT_PENDING → SETTLED → ACCESS_REVOKED → CLOSED.
Final run links to separation case and frozen inputs. Only supported entitlements are calculated; uncertain legal outcomes block. Preserve historical employment and payroll.

### 4.6 Individual payees
REQUESTED → CONTRACTED → DELIVERABLE_PENDING → ACCEPTED → TAX_REVIEW → FINANCE_APPROVED → PAYMENT_APPROVED → PAYMENT_SUBMITTED → PARTIALLY_PAID/PAID → WITHHOLDING_REMITTED → RECONCILED/CLOSED.
Unaccepted deliverable, unsupported tax classification or unresolved noncash valuation blocks approval. One central Finance queue applies to all locations.

## 5. Role/access matrix

| Role | Authorized scope | Controls |
|---|---|---|
| Owner | Approved HR/payroll/finance summaries and sensitive records as operationally necessary | Independent approvals for requisition/contract activation/compensation/rule/payroll/final settlement/payment |
| Management / HR | Assigned people, recruitment and employee records | Prepare workforce, recruitment, contract, attendance, leave, compensation proposals, payroll draft; never self-approve restricted actions |
| Finance / Payroll | Payroll, AP, remittance and journals needed for role | Prepare/freeze and reconcile; restricted release approval independent from preparer |
| Line/branch manager | Limited assigned roster, time and leave | Submit/correct/approve time within delegation; no peer salary/candidate/bank/identity detail |
| Auditor | Time-bounded read-only evidence | No mutations |
| Employee (later portal) | Own profile/payslip/leave only | Own leave request and allowed profile correction; no peer reads |
| System administrator | Platform support according to policy | WP admin status is not payroll/legal business approval |

Server-side permissions must check capability, record, location, sensitivity, workflow state, and actor distinction. UI hiding alone is not authorization.

## 6. Ethiopian legal correctness is a hard release gate

No tax band or pension rate may be silently hard-coded into a generic formula. Build a deterministic versioned calculator from reviewed rules; no arbitrary dynamic PHP/expression execution. Rule records carry source instrument/clause, official checksum, publication/effective dates, applicability, taxable/pensionable base, bands/rates/rounding, exemptions/evidence, reviewer approvals and boundary fixtures.

Unresolved questions kept open:
- PAYE base treatment relative to employee pension deduction needs written Ethiopian tax/pension interpretation. Do not silently carry forward the legacy TAXABLE_GROSS_LESS_EMPLOYEE_PENSION assumption.
- Reconcile current official status/effective date of Income Tax Proclamation 979/2016 and Amendment Proclamation 1395/2025 against Gazette/Ministry metadata before activating bands/exemptions. The Ministry of Finance register includes an income-tax regulation amendment draft dated 26 February 2026; draft text is not a final rule.
- Transport cap, medical treatment, per diem, hardship, free meals, employer retirement contributions and historical exemptions remain LEGAL_REVIEW until confirmed for the exact period.
- Digital-content creator income must follow verified Article 22/Schedule D and applicable implementing text. Unregistered does not automatically mean tax-free or employee payroll.
- Ordinary goods/service withholding under amended Article 97 applies only when facts/statutory conditions match. Do not automatically stack creator and ordinary-service withholding.
- Current pension basis/rates and Directive 1087/2025 must be checked against official text for the period.
- Labour terms, working time, leave, termination and notice/severance require clause-to-SOP mapping to Proclamation 1156/2019/current amendments.
- Privacy must map to Personal Data Protection Proclamation 1321/2024 and applicable implementing text.

Official starting references (source discovery, not legal certification):
- MoJ Labour Proclamation 1156/2019: https://justice.gov.et/en/law/labour-proclamation/
- MoJ Income Tax Amendment listing: https://justice.gov.et/en/law/proclamation-to-amend-federal-income-tax-proclamation-no-1395-2024/
- MoF tax directive register: https://www.mofed.gov.et/mof-directive/tax-directive/
- Private Organization Employees’ Social Security Administration Directive No. 1087/2025: https://justice.gov.et/am/entity/private-organizatio-employees-social-security-administration/
- Personal Data Protection Proclamation 1321/2024: https://pdp.eca.et/pdp-proclamation

Software can automate arithmetic and evidence after rule approval; it cannot itself certify legal interpretation. Unknown classification blocks final approval/posting.

## 7. ERP integration and UX
- Use Stockland_DB only after validating its table prefix convention; logical hr_x should resolve to a single intended stock_hr_x name, never a doubled prefix.
- Extend existing authority/roles with a least-privilege policy and route-level negative tests.
- Reuse location IDs and user-location mapping only after verification. Distinguish employer, workplace, cost centre and payment location.
- Reuse audit/workflow only if append-only, actor/scope and PII behavior meet this design. Do not put salary/identity values in generic serialized audit payloads.
- Reuse accounting service only after proving balanced entry, closed-period guards, source idempotency, transaction/rollback and reversal. Otherwise block posting pending a reviewed adapter.
- ERP must not fail at startup because HR replacement tables await migration; show a controlled schema-pending state for HR while core ERP continues.
- Responsive workspace with real list/detail/forms, accessible loading/empty/validation/permission/stale/error states. Mobile tables become readable cards or scoped scrolling; desktop search remains visible.
- Every action is a real server call; duplicate submits are disabled while pending; success/failure is explicit and server state is refreshed.
- Reports filter by dates/location/status/role. Query failure never appears as zero.

## 8. Ordered implementation gates

### Gate 0 — Source baseline and test environment
Verify source ZIP, file map, source code, DDL, route hooks, authority, ledger services, installed version/hash via approved read-only evidence, host limits and restoration access.
**Exit evidence:** manifest, exact package SHA, source-to-table dependency map, test inventory, no live data exported.

### Gate 1 — Data health and dependencies
On a protected staging copy, export table names, engines, schema fingerprints, keys/indexes, row counts and the four old tables' actual columns; trace all references through PHP/REST/UI/SQL/report/health/import/export/GL. Profile duplicate IDs, orphan lines, unresolved compensation, invalid dates, period overlaps, double postings, payroll-to-GL variances and missing audit. Only masked aggregates go to tickets.
**Exit evidence:** exact schema contract, dependency map, sanitized quality report, host report, backup hash and tested restore.
**Stop:** no destructive DDL or cutover if dependencies or restore are unknown.

### Gate 2 — SOP/RACI and law register
Turn supplied 20 functional SOPs and bilingual role resources into an Owner-approved versioned register. Map each requirement to screen, API, permission, entity, event, report and test. Obtain written Ethiopian legal/tax/pension interpretation and golden test fixtures.
**Stop:** statutory calculation rules remain disabled while material legal questions are open.

### Gate 3 — Target architecture and mapping
Review ERD, data ownership, role/location/PII matrix, migration mapping from legacy four tables and payroll/GL references, transaction/idempotency and private-storage choice. Freeze table count only now.
**Exit evidence:** approved ERD/field dictionary, migration plan, no duplicate source of truth, privacy/retention map, architecture decision record.

### Gate 4 — Build full HR in isolated branch
1. Versioned migration + schema contract/tests + health panel.
2. Person identity, plans, positions, requisitions and approvals.
3. Applications/interviews/offers/contracts/onboarding/activation.
4. Employee lifecycle, documents, learning/performance/access controls.
5. Shifts, attendance/corrections, payroll cutoff, leave policy/request/ledger.
6. Effective-dated compensation/benefits and maker-checker.
7. Legal rules register + deterministic calculation engine + explainable trace.
8. Payroll periods/runs/frozen snapshots/exceptions/approval/rejection/lock.
9. Payslips, payment batches/results/failure recovery, remittances and GL adapter.
10. Separation/final settlement linked to final run, clearance, payments and access revocation.
11. Central individual payee/creator/service request, contract, deliverable, tax review, AP/payment/remittance/GL.
12. Process Center downloads, management reports, print/CSV and audit.

### Gate 5 — Exact-package, security and performance tests
- Deterministic builder; baseline SHA/member identity; ZIP path traversal/duplicate member checks; build receipt.
- PHP lint for every packaged PHP file, WPCS/PHPCS, Semgrep; JavaScript syntax/static tests; CSS build/minified parity/mobile/search checks.
- Every route: unauthenticated, no capability, wrong location, cross-location IDOR, self-approval, invalid state, stale version, malformed payload, CSRF and over-posting tests.
- Migration: idempotent twice, failure/resume, no changed legacy DDL, safe table prefix, backup restore.
- Payroll fixtures: band boundaries ±0.01, rounding, zero/negative inputs, month cutoff, joiner/leaver/partial period/leap day, retro change, leave/overtime, allowance, missing/expired/ambiguous rules, replay and concurrent calculation.
- Full E2E: plan→requisition→application/interview→offer/contract/onboarding→activation→attendance/leave→increment→payroll preview/review/Owner approval→payslip/disbursement/remittance→balanced GL→exit/clearance/final settlement/payment/GL/access revoke.
- Separate creator/service payee E2E, noncash valuation, unresolved classification hold, rule decision trace, central queue, no auto-employee-PAYE.
- Financial: balanced journal, unique source IDs, duplicate payout rejection, rollback on journal/audit failure, closed period, historical immutability.
- Privacy: anonymous/unauthorized file denial, no public private files, download audit, redacted logs, retention/deletion, no real PII in artifacts.
- UI screenshots/click tests at 320/360/390/768/1024/1440 px, keyboard/focus/error tests, actual file click+MIME+Content-Disposition+hash+DOCX/PDF parser test.

### Gate 6 — Staging migration and parallel payroll
1. Restore a production copy into isolated staging; hash backup and record counts.
2. Add replacement tables; keep old HR tables unchanged/read-only.
3. Map legacy IDs; reject ambiguous matches/missing pay; do not upload personal rows to GitHub.
4. Reconcile counts/totals per period and payroll/payment/GL/PLEDGER; sign each variance.
5. Execute synthetic E2E, historical replay under approved period rules, security and restore tests.
6. Run at least two full payroll periods in parallel; Owner, HR, Finance and qualified reviewer sign variances.
7. Restore backup and complete rollback rehearsal with checksum and financial totals.
**Stop:** any unexplained variance, data exposure, duplicate posting, unproven remittance, or untested rollback is NO-GO.

### Gate 7 — Cutover and legacy retirement
Owner + HR + Finance + qualified Ethiopian adviser approve exact source, rules register, reconciliation, restore/rollback and ZIP hash. Freeze writes, take final backup/hash, activate new writes behind a reversible feature flag, and verify smoke tests. Keep old tables read-only and physically present for the agreed rollback/retention period. Delete legacy tables only after separate written authorization and proof that reports, bank/remittance, audit and ledger no longer depend on them. Monitor first two periods and execute rollback if financial/security gates fail.

## 9. Current observed state
- 16.1.95 exact package hash verified locally.
- 16.1.95 schema version 16.1.3 declares the old four HR tables, which do not provide full lifecycle.
- 16.1.97 ZIP in the working directory is explicitly a staging resource-library candidate; its own README says it does not complete the lifecycle.
- Owner's production health screen previously showed a payroll compensation-classification blocker and nine records requiring review. Re-verify by authorized live read-only query before using that count; never invent values.
- The static DDL reviewed so far has no explicit DB foreign-key constraints; application-level orphan/reconciliation checks are required.
- The current 16.1.98 workflow is a fail-closed scaffold until the actual product builder/test suite exist. A workflow scaffold is not an installer.
- An earlier feature branch tried a narrow HR download handler. It is not the full product and must not be merged as completion.

## 10. Acceptance register

Each check is PASS, FAIL or NOT RUN; skipped is never pass:
1. Requirements/SOP/RACI approval.
2. Exact source contract and dependency trace.
3. Sanitized data health and restore receipt.
4. Approved ERD/migration mapping.
5. Legal source/effective-date register and written professional sign-off.
6. All workflows and API/permission matrix implemented.
7. Full HR lifecycle E2E.
8. Payroll golden/boundary/correction tests.
9. Creator/service payee/AP/withholding tests.
10. GL/PLEDGER/remittance reconciliation tests.
11. Secure resource downloads and UI acceptance.
12. Exact installer checksum/source-package parity.
13. PHP/JS/CSS/security checks all run/pass.
14. Staging migration, two-period parallel payroll, restore and rollback pass.
15. Deployed-file parity and Owner + HR + Finance sign-off.
16. Separate legacy retention/retirement approval.

**Current state: NO-GO for production.** This document authorizes engineering in the feature branch, not live schema migration or payroll posting.
