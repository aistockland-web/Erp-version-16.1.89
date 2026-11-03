# Full Stockland Ethiopia HR, Payroll & Individual-Payee Replacement — Implementation Blueprint v1

Date: 27 October 2026  
Authority: Notion “HR Design Reset v0.5 — SaaS Engineering, Ethiopian Law-to-SOP Controls & PAYE Calculator” and “Required Scope — Full HR & Payroll Lifecycle and Ethiopian Statutory Rules”.  
Baseline: 16.1.95 installer SHA-256 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625.  
State: Phase 0 package inventory executed; replacement implementation, legal activation, migration and cutover remain open.

## 1. Authority and scope

This is a real replacement of the active legacy HR/payroll authorities, not a document-only screen or another 4-table extension. The target covers workforce planning; recruitment and human selection; contract/probation/onboarding; employee history and movement; attendance, shifts, overtime/rest/holidays; leave/accrual/balances; grades/increments/promotions; compensation and benefits; effective-dated statutory rules; payroll; payslips; payment batches and per-recipient settlement; tax/pension remittance evidence; balanced GL/PLEDGER; termination/final settlement; reports, audit, and a central individual-payee workflow for creators/freelancers/service providers.

Notion v0.5 is the current authority. It supersedes older fixed 27-table, 14–16-table, 59–61-table, 72-table and 76-table proposals. **No replacement table count is approved.** Design the domain model, ERD and reuse/replace matrix after actual source dependencies, schema and data health are evidenced.

The legacy employees, employee_compensation, payroll_periods and payroll_lines tables are transition sources, not target authorities. Do not drop, rename, truncate, repurpose or overwrite them until migration, history reconciliation, backup/restore, rollback, exact-package acceptance and separate cleanup approval all pass. Keep the current 49-table release contract stable during candidate development.

## 2. Phase 0 baseline results already run

The checksum-locked installer was retrieved from the recorded GitHub Actions artifact and independently checked locally:

- SHA-256 matches the baseline above.
- ZIP CRC passes; 75 unique files; expected stockland-erp-app/ archive root; 48 PHP files.
- Static registered table keys and DDL reconcile: 29 core + 16 finance/procurement + 4 legacy HR/payroll = 49; all 49 have DDL declarations.
- The legacy HR REST controller contains 13 route registrations, each with a literal permission callback. This does not prove correct role, row/location scope, state transition or sensitive-field handling.
- Lexical DB::table references to the four old tables were counted as employees 14, employee_compensation 5, payroll_periods 17, payroll_lines 13. These are navigation hints, not a semantic call graph or live database query.

Read-only audit files: hr_replacement_baseline_audit.json, hr_table_dependency_index.csv, hr_baseline_source_fingerprints.csv. Audit code: ci/hr_replacement_audit.py. Tests: ci/qa/test_hr_replacement_audit.py.

Still required before target schema approval: installed-source parity against the exact ZIP; live schema fingerprint on an authorized isolated clone; trace all direct/dynamic SQL, reports, REST routes, Health checks, imports, roles and accounting/ledger dependencies; sanitized duplicate/orphan/date/amount/period/payroll/GL/audit health checks; runtime/host capability report; and evidence for shared DB/authority/workflow/audit/AP/GL service reuse. A version label or screenshot is not a substitute.

## 3. Domain architecture (table count deliberately not frozen)

The ERD must decide normalized table boundaries based on ownership, cardinality, lifecycle, privacy, transaction patterns, uniqueness, audit history, retention and reconciliation. Reuse proven shared services when their guarantees are verified; do not put unrelated sensitive or financial data into a catch-all JSON record just to reduce tables.

| Domain | Required product capabilities | Key controls |
|---|---|---|
| Workforce/org | Org chart, departments, locations/cost centres, positions/grades/bands, headcount plans and vacancy budget | Effective-dated hierarchy, approved budget and location scope |
| People/worker relationship | Canonical people identity, candidate-to-employee continuity, legal employer, employment category/status/history, manager/workplace | One person identity; employee versus nonemployee is a distinct relationship |
| Recruitment | Requisition, candidate/applications, privacy purpose/retention, screening/interviews, scorecards, human recommendation, approved offer | Restricted access, human decision, no self-approval |
| Contract/onboarding | Contract versions/signatures/terms, effective dates, probation, renewals, amendments, private document references, induction/training/access provisioning | Signed/verified evidence and approval prerequisites before activation |
| Time/attendance | Calendar/shift, time events, absence/late/overtime/rest/holiday exceptions, correction reasons and approvals | Preserve originals; never silently turn unapproved exceptions into paid time |
| Leave | Leave type/policy, eligibility/accrual, balance ledger, requests, approval, supporting evidence, carry-forward and exit balance | Effective-dated policy; approved transactions and history |
| Compensation | Grade/band and compensation assignments; salary, allowance, bonus, commission, reimbursements, benefits, advances and lawful deductions | Effective date, provenance, approval, itemized tax/pension classification |
| Legal rules | Official source/hash/article, scope, publication/effective dates, method/parameters, interpretation, tests, reviewer and approval state | DRAFT / LEGAL_REVIEW / APPROVED / RETIRED; pin approved version to each calculation |
| Payroll | Period/run version, frozen inputs, itemized calculation details, exceptions, variance, approval, locked result and payslip | Decimal money, deterministic/explainable, idempotent, immutable after posting |
| Settlement/remittance | Payment batch and each recipient result, external bank/cash reference, failure/return/retry, tax/pension obligation and receipt | Generated is not paid; posted is not settled; no double payment/posting |
| Accounting | Approved AP/payment/GL/PLEDGER services, linked source IDs, journals, reversals and reconciliation | Debit=credit, transaction safety, idempotency, period-close guards |
| Exit/final pay | Approved exit reason/effective date, notice/clearance/access removal, last work/leave/compensation snapshot, final calculation/statement/payment | Legal rule gate; only lawful deductions; preserve historical ledger |
| Individual payee | Natural-person payee, creator campaign or service engagement, contract/PO/deliverable, cash/in-kind value, tax facts/review, AP/payment/remittance | Not automatically an employee; ambiguous tax is held; central Finance/AP workflow |
| Reports/audit/resource library | Timeline, exception owner, variance, leave/time, payment aging, statutory evidence, restricted export/print and secure templates | Server-side scope, immutable events, private downloads and PII minimization |
| Management/social snapshots | Source/account/period/as-of date, campaign spend/gift stock and attributable ERP sales | Timestamped manual capture first; no passwords, scraping, auto-posting or invented live analytics |

## 4. Lifecycle and state machines

1. Workforce request → budget/headcount approval → position/requisition opened.
2. Candidate privacy notice/purpose → application → screening/interview/panel → documented human recommendation → independent approval → offer accepted/declined.
3. Stable person/employee ID mapping → contract preparation and signing → private document verification → induction/training tasks → approved payroll/tax/pension setup → separately approved access provisioning → activate only after prerequisites pass.
4. Active employee timeline: probation/confirmation, contract renewal/amendment, leave/return, transfer between Central Warehouse/Goro/Gerji/Mexico/Megenagna, manager/role change, grade/increment/promotion, training and approved employee-relations events.
5. Assigned schedule/calendar → time entry/import → exception queue → manager correction with reason and independent approval → leave balance/request decisions → cutoff freeze.
6. Compensation change request → approved instruction/budget → effective-dated change → independent approval → apply only for its effective interval; never overwrite history.
7. Payroll open/preflight → resolve joiner/leaver/contracts/compensation/time/leave/overtime/benefit/rule exceptions → freeze input snapshot → calculate gross-to-net item-by-item → show bands/basis/rounding/version/variance → Management review and submit → independent Owner approval of exact run → payout batch → recipient-level paid/failed/returned confirmation → GL/PLEDGER and tax/pension remittance reconciliation → secure payslip → period close.
8. Late change → linked arrears/correction/reversal run. No edits to posted historical payroll; no repeated post/pay on retry.
9. Exit approved → last day/time/leave snapshot → legally reviewed final pay/leave/notice/severance and permitted deductions → clearance/assets/access removal → independent review and Owner approval → final statement/payslip → payout and remittance evidence → retention/archive.
10. Creator/service payee → factual category and contract/campaign/PO → deliverable acceptance and cash/in-kind valuation → TIN/licence/residency facts as needed → one justified classification/withholding decision or tax review → approval → central AP/payment → receipts and ledger reconciliation. Never classify a genuine employee as a freelancer to avoid employment obligations.

Every transition needs allowed prior/current states, role, actor, separate approver where required, effective date, evidence, version, idempotency key, audit event, failure path and rollback expectation.

## 5. Stockland SOPs and traceability

Use the 12 SOPs in Notion v0.5, all remaining DRAFT until accepted:
1. HR-SOP-01 Workforce plan and hiring authorization.
2. HR-SOP-02 Recruitment, selection and candidate privacy.
3. HR-SOP-03 Employment contract and lifecycle.
4. HR-SOP-04 Attendance, shifts, overtime, night work, weekly rest and holidays.
5. HR-SOP-05 Leave and protected absence.
6. HR-SOP-06 Grade, increment, promotion and compensation change.
7. PAY-SOP-07 Monthly payroll and PAYE.
8. PAY-SOP-08 Pension, remittance and reconciliation.
9. HR-SOP-09 Separation and final settlement.
10. DATA-SOP-10 Employee data, access, retention and incidents.
11. FIN-SOP-11 Payroll payment, GL, correction and audit.
12. GOV-SOP-12 Law/policy change control.

For each SOP capture owner, RACI/separation of duties, internal-policy choice, legal instrument/article and exact obligation, worker/category scope, dates, forms, exception path, retention/access class, test vectors, audit events, reviewer, approval date, source hash and superseding version. Crosswalk each requirement ID to SOP clause → UI → REST method/permission → service/transaction → persisted record → audit event → test → acceptance receipt. A route's permission_callback existing is not sufficient evidence.

## 6. Security and statutory rules

- Enforce role/location permissions server-side for every read/list/export/download/change/decision. Test wrong role, wrong branch, object-ID guessing, sensitive field masks, direct download URLs, mass assignment, nonce/session and self-approval. UI hiding is not security.
- Reuse Stockland_DB, Authority/Roles, workflow/audit and AP/accounting/GL services only after their actual transaction, location, history and security guarantees pass review.
- Versioned migrations with pre/postconditions, dry-run, idempotency, row/advisory lock where available, reconciliation, backup restore and rollback. Do not mutate live data during audit or build.
- Private contracts/ID/bank/payslip documents require private storage, authorization, audit, content hash/version and retention. No public predictable URL or public media exposure.
- PAYE must use approved legal input and progressive, decimal, explainable calculations; ordinary PAYE is based on the applicable taxable employment income, not take-home pay. Employee deductions and employer costs are separate. Rule version/effective dates and rounding are explicit.
- Qualified Ethiopian reviewers must approve current official texts/amendments for Labour Proclamation 1156/2019, Income Tax Proclamation 979/2016 as amended and Gazette No. 64 / 1395/2025, current pension law/directives including 1085/2025 and 1087/2025, Personal Data Protection Proclamation 1321/2024 and applicable holiday instruments/contracts.
- The Notion review flags the current PAYE pension-base method for written legal interpretation, transport exemption/cap after amendment, and digital creator threshold/classification as unresolved. The engine can be built and tested, but these rules stay LEGAL_REVIEW and block dependent final calculations until signed. Never alter posted payroll/GL based on research notes or sample arithmetic.
- Social-account reporting starts with dated manual Insights snapshots. No credential/MFA/session storage, scraping, auto-posting or fictional live metrics.

## 7. Build phases

**Phase 0 — Baseline/data discovery (in progress):** exact installer/source parity; live schema fingerprint on restored clone; complete runtime dependency map; sanitized data-health; host capability; verified reuse contracts; legal source register and risk list. Gate: Owner/HR/Finance accept evidence method/findings.

**Phase 1 — Domain design:** workflow interviews/walkthroughs; approved RACI/permissions; state diagrams; PII classification; ERD/data dictionary; rationale for each table boundary; old-to-new dependency matrix; migration, feature flags, rollback. Gate: design approval; no production DDL.

**Phase 2 — Technical foundation and vertical slice:** versioned candidate migrations, services/controllers, permission and location scopes, audit/workflow contracts, private document adapter, observability; a fully tested synthetic workforce request→approved recruitment→pending hire→contract/onboarding slice. Gate: lint/security/auth/state/idempotency and migration rollback pass.

**Phase 3 — People lifecycle:** recruitment, contracts, onboarding, employee history, transfers, confirmation, promotion and access events. Gate: positive, unauthorized, concurrent, missing-evidence, private-document and rollback tests.

**Phase 4 — Time/leave:** calendars, shifts, entries, corrections, overtime/rest/holiday exceptions, leave requests/accrual/balance and cutoff freeze. Gate: boundary/concurrency tests against signed-off rule fixtures; unapproved rule remains blocked.

**Phase 5 — Compensation/rules:** effective-dated pay assignments, item dictionary, changes, benefits/deductions, legal rule register/versioning and calculator harness. Gate: no self-approval, immutable versions, expiry/effective intervals and fail-closed uncertainty.

**Phase 6 — Payroll/payments/remittances:** preflight and frozen run, itemized calculator, review/Owner approval, lock, payslip, recipient payout results, failure/return/retry, external reconciliation and statutory receipts; integrate through approved AP/GL/PLEDGER service. Gate: full synthetic run, cent-level arithmetic, no double post/pay, balanced ledger, rollback and approved rule fixture.

**Phase 7 — Exit and individual payees:** final settlements, access clearance, final statements; creator/service intake, evidence and classification, central AP/payment and remittance. Gate: ambiguous rule held, employee/payee separation, exit/payment failures and reconciliation tests.

**Phase 8 — Reporting/resource/mobile quality:** exception queues, variance/aging, audit and restricted reports/exports, private document/resource downloads, 320/360/390 px and desktop, keyboard/accessibility/error-state checks; manual dated social snapshots.

**Phase 9 — Migration rehearsal and cutover:** explicit ID/date/history map; clean disposable WordPress/MariaDB plus sanitized restore clone; legacy-to-target reconciliation for employees, compensation, payroll, payments, GL and audit; parallel payroll and documented differences; restore/rollback; exact ZIP SHA and PHP 8.3/8.4, JS/CSS, security, auth, E2E, integrity and source-parity receipts. Obtain qualified legal/tax/pension review, Owner + HR + Finance acceptance, backup restore, go/no-go and rollback owner. Keep legacy four tables read-only during transition; cleanup needs separate approval.

## 8. Required acceptance tests

- Installer identity/ZIP CRC/member/root/version/manifest/file hashes.
- Schema, migration idempotency, concurrency, unique constraints, backfill completeness, orphan/duplicate scan, dry-run and reversal.
- Every REST route: permissions, branch/location isolation, sensitive fields, direct download denial, mass-assignment, nonce and self-approval.
- State machine, repeated request, race, stale version, failed audit, missing document/evidence and transaction rollback.
- Effective dates, month/year boundaries, joiner/leaver, leave accrual/carry-forward, overtime/rest/holiday, allowance categories, marginal PAYE boundaries and rounding from approved fixtures.
- No unknown rule activation; rule version/source hash pinned.
- Duplicate payroll period/run/payment prevention, payout failure/return/retry, recipient-level external confirmation, remittance receipts and GL debits=credits; paid not inferred from file creation.
- Exit/final pay boundary tests and no historic overwrite.
- Creator/service distinction, cash/in-kind consideration, TIN/licence facts and review, no duplicate withholding, AP/GL reconciliation.
- Private document MIME/content-disposition/integrity/cache/download audit/success-error feedback, browser download and mobile layout; no public access.
- Deep integrity, audit chain, backup restore, rollback, deployed-source parity and exact package SHA. Skipped or unknown tests are not passes.

## 9. Go/no-go

Production replacement stays NO-GO until the exact candidate hash has: approved dependency/data-health and ERD; 12 SOP requirement-to-code/test mappings; qualified legal source/effective-date sign-off; full synthetic lifecycle E2E; role/location denial; no double payment/posting; accounting/remittance reconciliation; migration+restore+rollback evidence; exact PHP/JS/CSS/security test outputs; Owner/HR/Finance acceptance; and deployed source parity. No real employee pay data is used in tests; no live production table or payroll is mutated by Phase 0 tooling.

Current result is a verified package/source inventory and an executable audit gate—not the finished ERP replacement. The next coding gate is the actual dependency/data-health report and approved ERD, then the tested vertical slice and phased implementation.

Official review starting points:
- https://justice.gov.et/en/law/labour-proclamation/
- https://www.ffic.gov.et/Portals/0/1395-2017.pdf
- https://www.mofed.gov.et/mof-directive/tax-directive/
- https://justice.gov.et/am/directives/private-organizations-employees-pension-implementation-directive-2/
- https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/
- https://justice.gov.et/en/law/personal-data-protection-proclamation/
