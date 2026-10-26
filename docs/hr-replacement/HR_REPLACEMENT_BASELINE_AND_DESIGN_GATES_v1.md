# Stockland ERP — HR/payroll replacement baseline and design gates v1.0

**Date:** 26 October 2026  
**Status:** Reviewable design/audit artifact. This is not an implementation or production migration authorization.  
**Repository main SHA:** `a0f20baaff65c0916e348d926374544b39b631ba`.  
**Reviewed release artifact:** 16.1.95 inner installer SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`; the build receipt reports no schema change and no live business-data change in that build. This does not prove which package is currently installed on the live site.

## 1. Current source facts

- The registered schema contract has **49 tables**: 29 core/operations, 16 accounting/procurement, and four legacy HR/payroll tables.
- Every registered table key maps to one static DDL declaration in the reviewed installer. The static DDL contains **zero explicit foreign-key constraints**. That is not a claim about the live database: it means source relationships must be traced and orphan/reconciliation checks must be explicit.
- HR tables are `employees`, `employee_compensation`, `payroll_periods`, and `payroll_lines`.
- The current `includes/rest/class-rest-hr.php` has 13 route registrations for HR bootstrap, employee read/create/update/decision, compensation, payroll list/detail/create/generate/line update/classification/submit/approve/pay. It has no HR routes for recruitment/candidates, contracts/onboarding, attendance, leave, grades/promotions, exit/final settlement, individual nonemployee payees/AP, HR document downloads or manual social report snapshots.
- A previous owner-provided Health screen reported nine unresolved compensation records, but the 16.1.95 build did not query live data. Verify this through a current authorized read-only export before using as an operational count. Do not infer missing salary data.
- Route registration inventory covers 116 call sites. Each includes a literal `permission_callback`; this only satisfies a registration-level check and does **not** prove that the callback enforces the right role, row scope, location restriction, or state transition.

The accompanying CSV contains one row per registered table with its source DDL file/line, source column and index counts, and lexical source-consumer counts. Those lexical hits are navigation hints, not proof of execution. Follow actual call flow in code.

## 2. Existing building blocks to evaluate—not assume-fit

- **DB, named locks and shared utilities:** `includes/class-stockland-db.php`. Reuse the existing query, schema and locking abstraction where its contracts fit; avoid building a competing DB layer.
- **Role, capability and location authority:** `includes/class-stockland-authority.php`, `includes/class-stockland-roles.php`, and the shared REST base. Reuse and extend narrowly, with route-level tests. UI hiding is not authorization.
- **Workflow events:** `includes/class-stockland-workflow.php` and `workflow_events`. Verify event metadata, actor/location scope, transition validation, retention and append-only behavior before reusing for employee/payee workflows.
- **Audit chain:** `includes/class-stockland-audit.php` and `audit_logs`. Reuse the existing chain verification; make sure HR events can be represented without storing salary/PII in inventory fields such as SKU/quantity. Do not log sensitive document contents or unnecessary personal data.
- **GL posting:** `includes/class-stockland-accounting.php::post_journal()`, `gl_journals`, `gl_lines`. Payroll must post through the accepted journal service with source idempotency, balanced debit/credit validation, closed-period guards and reconciliation.
- **AP/payments:** current `payments` has `supplier_id` and `customer_id`, not a generic individual payee. The `expenses` record is too thin by itself for identity/TIN/licence facts, campaign/deliverable evidence, withholding decision and remittance proof. Do not fake an employee/customer/registered supplier to pay an unregistered creator. Design an individual-payee/AP adapter while preserving existing payment/GL posting controls.
- **Branch/location scope:** `locations`, `user_locations`, authority/service functions. Distinguish employee workplace, legal employer, cost center and payment location as needed; confidential HR data often needs central restricted access, not default branch-wide visibility.
- **Notification and import services:** reuse only after privacy and idempotency tests. HR imports must stay separate from product master and daily POS import, with PII minimization, duplicate detection, review/approval and audit receipts.
- **HR document library:** no suitable secure HR download API/UI is confirmed shipped. Do not publish signed contracts or identity/payroll files in public predictable URLs. Test direct URLs as anonymous and unauthorized; if hosting cannot enforce private storage, do not store sensitive personnel documents there.
- **Social reporting:** direct platform sign-in only in phase 1. No passwords, MFA codes, recovery codes, cookies or tokens in ERP. Management reports use manually captured, timestamped/exported snapshots with platform, account, reporting period, as-of time and staleness; no unsupported “real-time” claim.

## 3. Logical target domains—do not predetermine table count

### Employee and workforce lifecycle
Headcount/vacancy request; candidate/application and controlled privacy retention; employee/person identity; effective-dated employment contract and legal employer; position/grade/pay band/department/workplace/manager; offer and onboarding tasks/documents; probation/review as a lifecycle phase (not tax classification); shift/attendance and correction; leave types, request, accrual/balance and approval; effective-dated compensation and pay-item approvals; transfer/promotion/discipline where applicable; exit notice/clearance/handover; final salary/leave/tax/pension settlement and access removal/retention.

### Payroll and statutory rules
Frozen run-input snapshot and cutoff; individual earning/allowance/benefit/reimbursement/deduction lines with provenance; versioned legal-rule register (jurisdiction, source/article, effective dates, method, exclusions, evidence, reviewer/approval, hash, test fixtures and expiry); per-employee calculation breakdown; maker/checker and variance gates; payslips; payment batch/reference and failed-payment recovery; withheld-tax/pension remittance evidence; linked GL journal. Posted histories are immutable; corrections are linked adjustments/reversals, not edits to historical lines.

### Creator, freelancer and other nonemployee payees
A reusable person/payee profile separate from employee identity and registered business master; deduplication and PII controls; relationship and contract/campaign/service; deliverable URL/date/acceptance; cash and in-kind valuation; identity/TIN/licence/residency facts where relevant; classification decision with source/rule version/reviewer; central request/approval/AP/payment/withholding/remittance/reconciliation queue. A category dropdown never chooses the tax rate by itself. Never automatically route digital-content pay to employee PAYE or apply Article 97 withholding solely because a payee is called “unregistered”; capture facts and require tax review for uncertain law interactions.

### Private HR documents and role resources
Resource metadata: function, role, language, version, effective date, approver, hash, active/retired state and access class. Menu target: HR → HR Process Center → Downloadable Resources → Function → Role → Language → Download. Serve files through an authenticated server-side endpoint with capability and scoped access; log downloads. Existing draft JDs/SOPs are not yet deployed or adopted policy. Do not use public attachments for signed contracts.

## 4. Legal stop gates

1. **PAYE base:** existing `Stockland_Payroll_Statutory::calculate()` subtracts employee pension before PAYE, naming the rule `TAXABLE_GROSS_LESS_EMPLOYEE_PENSION`. The reviewed text did not clearly establish this reduction. Keep `LEGAL_REVIEW / NOT SIGNED OFF`; obtain written qualified Ethiopian tax/pension interpretation before formula changes or legal compliance claims. Preserve old rule versions and posted history.
2. **Allowances and exemptions:** actual reimbursed business expense, flat cash allowance, per diem, benefit-in-kind and statutory exemption are different. Draft candidate exemption items—including transport caps/directive history—remain `LEGAL_REVIEW`. A dropdown cannot turn on a tax exemption without approved source/version/evidence.
3. **Digital creator income:** Proclamation 1395/2025 covers cash and in-kind digital content income. Final public implementing threshold/classification was not verified in this audit; do not hardcode media-reported thresholds.
4. **Withholding:** distinguish eligible goods/service contracts and thresholds, TIN/licence status, Article 97, digital-content/Schedule D and any overlapping rules. Do not stack or choose a rate without a documented rule decision.
5. **Pensionable pay:** keep employee deductions separate from employer cost and sign off the relevant wage basis/effective dates against current pension law/directives.
6. **Privacy:** design purpose limitation, retention, access logging, private document delivery and incident handling for candidate, employee and creator personal data.

## 5. Ordered implementation gates

**Gate 0 — source/runtime parity:** record live installed version/hash, host PHP/DB/WordPress versions and 49/49 health from an approved read-only inspection. Do not trigger setup/repair during audit.

**Gate 1 — dependency tracing:** trace UI → JS → route → capability/profile/location callback → service → SQL/data mutation → workflow/audit/GL side effects for all 13 HR routes and all callers of the four legacy tables, GL and payment/expense tables.

**Gate 2 — data health:** on a protected copy, run employee-to-compensation, payroll period-to-line, user/location, GL source, payment allocation and any other relationship orphan/duplicate checks; reconcile totals and immutable history. Source DDL has no explicit foreign keys, so do not assume integrity.

**Gate 3 — law and target model:** obtain qualified written legal interpretations; define rule registry and golden calculator cases; model lifecycle/state machine, entity cardinalities, RBAC/PII matrix, payment queue, private documents and dashboard source facts. Select actual table boundaries only after the dependency graph proves what existing services can safely own.

**Gate 4 — isolated additive implementation:** branch-only; idempotent and versioned migration; no destructive DDL; no production setup route. Preserve the old HR tables/read path; shadow-test the new modules before changing system-of-record writes.

**Gate 5 — migration and parallel run:** backup and restore proof; copy-only migration rehearsal; roster/compensation/payroll/GL totals reconciliation; legal-rule and missing-compensation exceptions resolved; at least two full payroll periods in parallel with Owner/HR/Finance comparison and signed variances; security/privacy and row-scope tests pass.

**Gate 6 — controlled cutover:** Owner + HR + Finance + qualified tax/legal/pension approval, signed runbook, backup hash, cutover window, idempotent activation, post-cutover acceptance and tested rollback. Keep legacy tables read-only through approved rollback/retention window.

## 6. Non-goals at this stage

No live DDL, payroll mutation, 49-table count change, legacy deletion, guessed tax rates, made-up salaries, fake employee/supplier records, external social posting, or claims that the draft resource pack itself shipped in the ERP.
