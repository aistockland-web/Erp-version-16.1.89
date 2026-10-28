# HRIS Replacement — implementation plan v0.6

**Status: architecture/source-audit tranche; implementation and production cutover are not certified.**

## Design authority and conflict resolution

The latest authority is the Notion page [HR Design Reset v0.5](https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d?pvs=204) and parent scope [Required Scope — Full HR & Payroll Lifecycle](https://app.notion.com/p/4026db0f767e81aaa3ecc4383f5402f9). They supersede historical 27-table / 14–16 / 72 / 76-table proposals. Do not lock a physical target table count until the complete dependency graph and sanitized data-health report support the ERD. The four current tables — \`employees\`, \`employee_compensation\`, \`payroll_periods\`, \`payroll_lines\` — become legacy migration sources, not target authorities. Do not drop, rename, truncate, disable or change their data before approved migration, reconciliation, backup/restore and rollback gates pass.

## Baseline established

The exact canonical 16.1.95 installer is checksum-locked to SHA-256 \`494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625\`, 1,102,405 bytes, 75 unique ZIP members and 48 PHP files. It contains 13 HR REST route-registration calls. A source-text reference scan identified ten files that mention one or more of the four old table names, including the HR data layer, HR REST, frontend, health checks, admin schema inventory, statutory helper, accounting/requisition callers and settings. Static DDL has no explicit foreign keys. These facts mean this replacement must update health/schema contracts and downstream readers deliberately; merely creating new HR tables will not complete a cutover.

Shared source services found: \`Stockland_DB\` table/query/insert/update helpers; \`Stockland_Authority\`; \`Stockland_Workflow::event/transition\`; \`Stockland_Audit::log\` chained audit; \`Stockland_Money\` decimal arithmetic; and \`Stockland_Accounting::post_journal\` with \`source_type/source_id\` idempotency. Reuse only after method-level tests verify scope, locking, append-only behavior and failure handling. Never write directly to GL/PLEDGER.

## Full workflow to implement

1. **Workforce & positions:** approved headcount plan, budget, cost centre, grade/band, position, manager, workplace and effective period. A vacancy cannot proceed without the approval.
2. **Recruitment:** candidate privacy/purpose, duplicate detection, requisition, human screening, interview panel/rubric, evidence, documented selection rationale, approved offer. No automated final hire/rejection.
3. **Contract, hire and onboarding:** contract particulars and versions, start/end/probation dates, private document references, checklist/tasks, training/acknowledgements and payroll setup. Activation requires signed/approved contract, verified prerequisites and approved compensation.
4. **Employee master and changes:** canonical person continuity from candidate to employee; employment/contract history, manager/location/grade/position transfer, promotion, increment, probation, return from leave and status changes with effective dates and audit evidence.
5. **Attendance and shifts:** schedule/roster, source attendance events, explicit missed-event exceptions, corrected records linked to originals, manager review, payroll cutoff. Overtime/rest/holiday/night premiums require approved effective-dated rules.
6. **Leave:** categories, eligibility, versioned rules, accrual/allocation ledger, request/evidence/coverage/review, approval, paid/unpaid treatment, carry-forward where approved, reversals and exit balance.
7. **Compensation & benefits:** effective-dated base pay and itemized allowance/benefit/bonus/commission/overtime/reimbursement/advance/deduction; evidence, contract/policy basis, approval and legal taxable/pensionable classification. Never infer a salary or exemption.
8. **Law/rule register:** immutable versioned sources, exact clause, original document URL/hash, publication/effective dates, jurisdiction and worker/payee scope, formula/basis/rounding, evidence, reviewer, separate approval and golden test vectors. States: DRAFT, LEGAL_REVIEW, APPROVED, RETIRED. Unapproved/expired/conflicting rule blocks affected payroll/settlement.
9. **Payroll:** monthly period/cutoff, frozen input snapshot, effective-dated compensation/time/leave, itemized calculation trace, PAYE, pension, approved deductions, net pay and employer costs, variance/exceptions, rerun protection. Missing compensation stays blocked; do not auto-fill to pass Health.
10. **Review/posting/payment/remittance:** Management prepares and submits; independent Owner/authorized approver reviews the exact frozen version. Post one balanced journal via the existing GL service with idempotency; transaction rollback on failure. Separate accounting posting from external settlement: batch, unique bank/cash/cheque reference, per-person outcome, partial/fail/return/recovery, remittance due/submitted/paid receipts and GL/PLEDGER reconciliation.
11. **Exit & final settlement:** lawful case category, approved effective date, notice/clearance, final time/leave/compensation/arrears, legally due leave/notice/severance where applicable, permitted deductions, itemized settlement and final payslip, actual payment/remittance evidence, assets/access closure and Owner/HR/Finance approval. Posted history remains immutable; corrections are linked adjustments/reversals.
12. **Central People & Payments:** one intake routes employee payroll/benefits, actual expense reimbursement, digital-content creator/influencer, freelancer/service provider and other individual exceptions. Separate work relationship from tax classification. Capture contract/PO, deliverable, cash + in-kind value, TIN/licence/residency facts as relevant, classification decision/version, approval, payment and remittance. No fake supplier or employee; no branch payment desks; uncertain overlaps go to Finance + qualified tax review.
13. **Resource Center & reporting:** HR → Process Center → Function/Role/Language → Download. Genuine DOCX/PDF; controlled version/effective date/hash/status/access class; authenticated server delivery, no predictable public private-file URLs, download audit, actual browser feedback. Management dashboard only uses sourced, timestamped ERP data. Social metrics are manually captured platform snapshots in phase 1; no passwords, MFA, cookies, scraping or auto-posting.

## Non-negotiable invariants

- WordPress Administrator retains platform access; HR business permission is a separate capability and audited.
- Enforce role, record, branch/location and sensitive-field scope server-side on every route. UI hiding is not authorization.
- No self-approval for hires, compensation, tax-rule approval, payroll, settlement and high-risk payment.
- Use exact decimals; freeze inputs; prevent duplicate request, concurrent approvals, repeated journal/payment and reruns.
- Audit only the minimum necessary PII; never log document contents or publish personal/signed contracts.
- \`MISSING\`, \`FAILED\`, \`STALE\` and \`UNAVAILABLE\` are not zero or PASS.
- Existing data and posted payroll/ledger remain unchanged until copy-only rehearsal, reconciliations, backup/restore, and separate cutover authority.

## Ethiopian legal gate

Official discovery sources include [Labour Proclamation 1156/2019 — MoJ](https://justice.gov.et/en/law/labour-proclamation/), [Personal Data Protection Proclamation 1321/2024 — MoJ](https://justice.gov.et/en/law/personal-data-protection-proclamation/), [Pension Directive 1087/2025 — MoJ](https://justice.gov.et/en/directives/), and [MoF tax-directive register](https://www.mofed.gov.et/mof-directive/tax-directive/). A [tax-summary page reviewed 15 July 2026](https://taxsummaries.pwc.com/ethiopia/individual/significant-developments) reports that publication of an income-tax change approved in July 2025 was still pending; the public MoJ/MoF registers refer to related instruments. The sources reviewed do not settle every applicable provision/effective date. Do not hard-code or activate PAYE bands/base, pensionable pay, allowance/transport exemption, overtime/leave/severance, digital creator classification/threshold or Article 22/97 overlap until exact primary texts and effective dates are confirmed in writing by a qualified Ethiopian adviser. Do not change historical payroll or current formula to clear a Health failure.

## Ordered implementation gates

**G0 — exact baseline and host:** verify archive/hash, installed files if authorized, PHP/WordPress/MariaDB/transactions/permissions/storage and restore capability. No setup/repair invocation against production during audit.

**G1 — complete source dependency/data health:** finish call graph from all 13 old HR routes/table callers through UI, REST, permissions, SQL, workflow/audit, payroll/GL/reporting/System Health. On an authorized restored clone, sanitize duplicate/orphan/missing/effective-date/status/period/ledger findings; do not put real rows or salary values in GitHub. Map every old-table dependency and produce target ERD + retain/reuse/replace map.

**G2 — SOP and target design:** link the 12 SOPs to role/RACI, screens, route/capability/location, schema, workflow events, reports, exception routes, retention and tests. Approve target ERD/physical schema only after G1 and legal interpretations.

**G3 — isolated implementation:** complete one domain end-to-end at a time with reversible versioned migration, feature flags, validators, server authorization, responsive screens, help/error/retry handling, tests and auditable state transitions. Keep legacy schema and writes intact in parallel/shadow mode.

**G4 — time/leave/compensation and statutory engine:** effective dates and locked snapshots; exact-decimal calculation; golden and boundary vectors; fail-closed unresolved legal classifications; separate reviewer/approval.

**G5 — payroll/payment/settlement:** frozen run, independent approval, balanced idempotent GL, external per-beneficiary settlement result, tax/pension remittance tracking and reconciliations; exit/final-settlement scenarios.

**G6 — People & Payments, resources and reports:** centrally routed five-category intake, private resources, manual social metrics, source-backed location/date-period reporting.

**G7 — security, data migration and two parallel periods:** role/location negative tests, direct-file denial, privacy/retention audit, all legacy-to-new maps, source/history/GL totals reconciliation, two complete parallel payroll periods and signed exception dispositions. Backup/restore and rollback drill on staging.

**G8 — exact release:** exact ZIP hash tied to commit/build receipt; archive inventory/path/hash, PHP 8.3/8.4 syntax, JS syntax, CSS/PostCSS/mobile/search, REST/permalink, schema/migration, payroll & withholding regressions, authorization, no-self-approval, concurrency/idempotency/rollback, document MIME/download and source preservation. No skipped check counts as a pass.

**G9 — controlled cutover/retirement:** Owner + HR + Finance + qualified Ethiopian labour/tax/pension review sign-offs, runbook, protected backup hash, activation approval, post-cutover reconciliation. Retain old four tables read-only until separate cleanup approval and complete rollback window pass.

## Acceptance evidence required before calling the HRIS production-ready

- requirement-to-screen/route/data/state/audit/test traceability;
- source-to-table and route authorization matrices;
- reproducible migration + copy-only rehearsal with before/after hashes;
- synthetic full E2E from approved workforce plan to final settlement/remittance;
- PAYE and pension golden vectors signed against exact primary rule/version;
- hire/leave/transfer/increment/joiner/leaver/retro-change/exits, mobile 320/360/390/desktop/keyboard and failure/retry tests;
- no duplicate journal, payment or remittance; no unauthorized cross-location/PII reads; no self-approval;
- balanced GL/PLEDGER and external payment/remittance reconciliation;
- backup/restore/rollback receipts and exact deployed ZIP/file parity;
- Owner + HR + Finance + qualified legal/tax/pension sign-off.

**Current disposition:** NO-GO to production replacement. The checked package/source and design documents are available; full target runtime is not yet implemented, installed, legally approved, or accepted. The next correct step is completing the complete dependency/data-health matrix against the exact baseline and protected staging copy before approving the physical target model.
