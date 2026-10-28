# Stockland Ethiopia — Full HR, Payroll & Individual-Payee ERP Plan v0.5

**As of:** 28 October 2026  
**Authority:** Notion “HR Design Reset v0.5 — SaaS Engineering, Ethiopian Law-to-SOP Controls & PAYE Calculator” and “Required Scope — Full HR & Payroll Lifecycle and Ethiopian Statutory Rules”.  
**Repository:** aistockland-web/Erp-version-16.1.89  
**Tracking:** GitHub Issues #23 (design reset), #15 (full lifecycle), #17 (independent CI/security remediation).  
**Status:** Blueprint and static source audit only. Not implemented, legally approved, cut over, or production-certified.

## 1. Product decision

Build one integrated Stockland **People & Payments** workspace inside the ERP/PWA. This is a real operating subsystem—not a document-only add-on, mock screen or PAYE calculator alone. It must carry records through workforce planning, recruitment, hire/onboarding, employee lifecycle changes, attendance, shifts/overtime, leave, compensation, rule-based payroll, review/approval, payslips, payment and remittance, accounting reconciliation, exit and final settlement. A related central intake must handle employee benefits, actual expense reimbursements, digital-content creators/influencers, freelancers/service providers and other individual payees without treating them as employees or registered companies.

**The current Notion v0.5 design authority supersedes historical fixed table-count drafts.** Neither the old 27-table proposal nor the 14–16-table estimate is approved. Target tables must emerge from the source/dependency/data audit and approved ERD. The four legacy HR/payroll tables are migration/history sources, not the new system’s target authority. Preserve them unchanged until mapping, reconciliation, backup/restore, rollback and separate retirement approval pass. Do not reuse the retired 16.1.96 overlay as the release design.

Use vendor-neutral enterprise patterns: explicit domain ownership, effective-dated master data, validated state machines, server-side role/location access, maker-checker, private-document handling, PII minimization, immutable financial history, transaction boundaries, concurrency control, idempotency, deterministic calculations, exception queues, responsive UX, backup/restore and exact-package CI.

## 2. Exact-source baseline findings

The checksum-locked 16.1.95 CI installer was retrieved and checked:

- SHA-256: **494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625**
- Size: 1,102,405 bytes; 75 unique package files; 48 PHP files; ZIP CRC passes.
- The package has 49 unique static CREATE TABLE declarations: 29 from the Core/Operations activator, 16 from Accounting/Procurement, four legacy HR/payroll.
- The four legacy tables are stock_employees, stock_employee_compensation, stock_payroll_periods and stock_payroll_lines.
- The examined static DDL has no explicit foreign-key clauses. This is a source finding, not a live database metadata check; application-level orphan, duplicate and reconciliation checks are therefore critical.
- Shared services worth evaluating: Stockland_Authority, Stockland_Roles, Stockland_DB, Stockland_Workflow, Stockland_Audit, Stockland_Accounting, users/locations and notifications. Reuse only where actual permission, transaction, row-scope, history and data guarantees fit.

The 16.1.97 staging candidate archive was also checked:
- SHA-256: **7822606ded2883d9d5faf8b58962f058c99320d64fcf2da50c9f140448adee30**
- 107 package entries, 80 PHP files, 30 embedded DOCX resources.
- It contains the HR resource REST routes, server-side permission callbacks, asset path/hash checks, audit logging and a JS click handler that fetches JSON/base64 and saves a DOCX Blob. Local checks passed archive/hash, embedded DOCX payload, PHP syntax and JavaScript syntax. This is source/package evidence only; it is a document-library feature, not full HR/payroll, and it does not prove the live site has those bytes or that a live browser download works.

The user-reported health snapshot identifies WordPress 7.1.3, PHP 8.4.27, MariaDB 11.4.13, schema 49/49 and plugin 16.1.97; it also reports nine unresolved compensation-classification records. This is user-provided production evidence, not a live read performed here. No salary may be invented or auto-filled. The reported dead download button requires deployed-source parity plus authenticated browser and direct REST tests.

## 3. Required functional scope

### Workforce, recruiting and employment
Organization, department/function, positions, grade/pay band, reporting relationships, location, cost centre and effective-dated workforce plans. Vacancy request and budget/Owner approval. Recruitment captures sourcing, candidate/person identity, privacy notice/retention, application stages, interviews/rubrics, evidence, human selection rationale and offer acceptance/decline. No automated final hiring or rejection decision.

A canonical person is distinct from an employment relationship. Employment records include employee code, legal employer, contract type, job/grade, manager, schedule, work location, cost centre, effective dates, probation and status. Contract versions store signed-document metadata/hash and private vault reference; signed copies are immutable. Onboarding covers contract, required records, policy acknowledgment, induction/training, approved compensation/payroll setup and separately approved system access. Handle transfers across Central Warehouse, Goro, Gerji, Mexico and Megenagna; promotion/demotion, renewal, probation confirmation, absence/return and status/manager/grade changes all retain effective dates and approval history.

### Attendance, overtime and leave
Maintain rosters, attendance in/out, lateness/absence, manual corrections with reasons/evidence/approval, overtime requests, rest-day/holiday/night-work eligibility and payroll cutoff snapshots. Store time facts separately from legal entitlement/rate calculations. Leave covers policy, eligibility, accrual, opening balance, usage, adjustments, carry-forward, request, coverage decision, evidence, paid/unpaid treatment and leaver balance. Negative balances and retroactive corrections need explicit policy and audit evidence.

### Compensation, benefits and statutory rules
Use effective-dated compensation assignments and components with reason, approval, start/end dates and evidence. Categories include base salary/wage, overtime/differential, commission/bonus, allowances, actual expense reimbursement, in-kind benefit, arrears/correction, advance/loan recovery, lawful deduction and final-settlement items. Factual pay item is separate from taxable/pensionable classification; a dropdown label cannot make a payment tax-exempt.

Each statutory rule stores jurisdiction/population, source instrument/article, official URL and file hash, publication/effective dates, scope/exclusions, method, rounding order, test vectors, interpretation, reviewer/approver, version, expiry/review trigger and state. Rule states progress through Draft → Evidence review → Legal/payroll review → Approved → Effective → Superseded/Expired. Final payroll fails closed if a relevant legal basis is missing, conflicting or unapproved. Each run/line retains its rule version and calculation trace.

PAYE base/pension interaction, transport allowance treatment after the amendment, current pensionable-pay basis, digital-content creator classification/withholding, and relevant labour/overtime/leave/notice/severance terms remain blocked until written independent review. Never select a historical formula merely because its example balances.

### Payroll, payout, remittance and accounting
Create a period/cutoff and freeze approved employee, compensation, time, leave, overtime, benefits, deductions and rule versions. Calculate itemized gross, taxable base, PAYE, employee/employer pension where applicable, lawful deductions, net pay, employer cost and allocations. Support joiners/leavers, partial periods, arrears, effective-date changes, correction and controlled reruns.

State flow: Draft → Inputs frozen → Calculated → Management review → Submitted → Independent Owner approval → Approved/Locked → Payment batch prepared → Sent / Partially settled / Settled / Failed → Reconciled / Closed. Incomplete compensation, unresolved classification, duplicate line, invalid date or missing evidence blocks transition with an actionable reason.

A PAID flag does not prove bank settlement. Record external bank/cash batch reference, per-person payout result/reference, amount, timestamp, partial/failure state, retries and reconciliation. Track tax/pension remittance liability, authority, period, return/reference, submitted/paid/accepted status and receipt. Generate secured payslips. Post GL/PLEDGER through the approved accounting service only after approval; require source idempotency, balanced debit/credit, open-period guard and transaction atomicity. Failed posting must not leave a half-posted or falsely settled record. Correct posted history by linked adjustment/reversal, never by editing it.

### Exit and final settlement
Record exit request/type/reason/effective date, required notice/evidence, human approval, handover, asset clearance, outstanding advance/loan and expenses, private records, access revocation and retention. Freeze final attendance/overtime, prorated salary, approved allowances/bonus/arrears, leave balance and legally applicable notice/severance. Each component shows calculation rule/evidence. Missing data or unapproved rules block settlement. Management prepares; independent Owner approves; Finance records payout/remittance; GL/PLEDGER balances; final statement/payslip and applicable service document are issued; employment and financial history remain immutable.

### Central individual-payee intake
One wizard begins with five types: (1) employee payroll/benefit, (2) actual expense reimbursement, (3) digital-content creator/influencer, (4) freelancer/service provider, or (5) other individual/exception. Capture person/payee identity, engagement/campaign, deliverable acceptance, dates, cash and in-kind value, residency/TIN/licence facts where relevant, classification source/rule and reviewer, payable, payment evidence and remittance. Branches submit evidence; central Finance/AP controls payout. Never fabricate a supplier/employee or assume unregistered means tax-free. Never stack unapproved withholding treatments.

### Reporting and Process Center
Provide headcount/vacancy, contract/probation expiry, onboarding completion, attendance/overtime/leave exceptions, compensation approvals, payroll variance, payment failures, remittance ageing, final settlement, payee ageing and audit reports. Each report carries period/location, as-of time, source and row-level scope. Social metrics phase 1 are manually captured timestamped Insights; do not claim real-time APIs or auto-posting without verified provider scopes.

Resource downloads belong inside HR → Process Center, not an orphan/mid-dashboard control. Use genuine DOCX/PDF, authorized allow-listed handler, correct MIME/Content-Disposition, private no-store response, integrity check, audit before bytes are sent, loading/success/failure feedback and retry. Test 320/360/390 px and desktop, plus keyboard/touch. A wrong extension or source-only mock is not accepted.

## 4. Provisional domain model (no table count fixed)

The ERD/data dictionary decides which become owned tables, subentities, views or shared-service references after dependency audit:

- Organization/position/grade/workforce plan/vacancy.
- Person/candidate/application/interview/offer/employment/contract version/onboarding task.
- Shift/roster/attendance exception/overtime approval/leave policy/balance/accrual/request.
- Effective-dated job assignment/performance/training/compensation assignment/component.
- Statutory instrument/rule version/test vector/approval.
- Payroll period/run/version/frozen input/per-person result/payroll detail/payment batch/per-person payout/remittance receipt/reconciliation.
- Exit/final settlement/settlement item/access-clearance reference.
- Individual payee/engagement or campaign/deliverable/classification decision/payable/payment/remittance.
- Secure document metadata/private-storage locator/approval timeline/audit event/idempotency/source link/exception queue.

Invariants: candidate-to-hire continuity; effective-dated employment/compensation; every payroll item traces to a frozen input and approved rule; payment traces to payable and external reference; every journal is balanced/idempotent; final settlement traces to locked final inputs; each state transition records actor/time/from/to/reason; no sensitive document bytes in public URLs or ordinary audit payloads.

## 5. Roles and access
Owner approves workforce/budget-sensitive decisions, compensation/rule activation, payroll and final settlement. HR/Management prepares recruitment, contracts, onboarding and employment changes; cannot self-approve sensitive decisions. Payroll preparer/reviewer is separate from Owner approval. Finance/AP runs central payee payments/remittance/reconciliation with minimum necessary employee detail. Branch managers see only assigned-location team attendance/leave and requests by default. Auditors are read-only and scoped. WordPress Administrator/IT retains platform administration but gains no business approval authority solely from that role.

Test role × operation × state × location × record owner × sensitive field for every route. UI hiding is not authorization. Minimize and scope audit-reader access, including sensitive reads/downloads.

## 6. Migration and release sequence

1. Match installed plugin hashes/version; capture current DDL/columns/indexes, route/dependency map, hosting settings and sanitized data-health profile.
2. Profile duplicate identities, orphan compensation, invalid dates, payroll period/line mismatches, inconsistent totals, duplicate payment references and orphan journals. No PII in GitHub.
3. Approve domain dictionary, ERD, legacy ID crosswalk, conflict dispositions, history semantics, reporting parity, retention, migration and rollback plan.
4. Use disposable staging from verified backup. Keep old HR tables intact/read-only during coexistence.
5. Reconcile by period/person: gross/items, net, PAYE, pension, payments, remittance and GL/PLEDGER. Differences need signed disposition.
6. Compare old/new payroll for selected periods with authorized inputs and professionally approved vectors; explain every variance.
7. Prove restore and rollback after simulated partial cutover; capture receipts and exact SHA.
8. Pilot a restricted population/location/period with Owner, HR and Finance sign-off. Retire legacy tables only in a separate approved change after the rollback horizon.

| Phase | Scope | Exit evidence |
|---|---|---|
| 0 | RACI, company SOP decisions, legal source register | Signed Stockland policy decisions; unresolved law blocked |
| 1 | Installed source hash, 49-table fingerprints, route/table dependencies, host/data audit | Reproducible source report and sanitized findings |
| 2 | Process maps, state machines, role matrix, ERD and migration plan | Owner/HR/Finance design approval; no arbitrary count |
| 3 | Secure service contracts, migrations, documents, audit, approvals, locks/idempotency | Repeat/rollback/authorization/atomicity tests |
| 4 | Workforce, recruitment, contracts, onboarding, employment history | Synthetic hire/renewal/transfer/probation E2E |
| 5 | Shifts, attendance, overtime and leave | Entitlement/cutoff tests; duplicate and unauthorized edits blocked |
| 6 | Compensation, rule register and PAYE engine | Qualified legal review plus exact approved vector suite |
| 7 | Payroll, payslips, payment batches, remittance, GL/PLEDGER | Balancing, retries, no self-approval, failure rollback |
| 8 | Individual payee queue and AP/withholding workflows | Five intake categories; classification errors fail closed |
| 9 | Exit and final settlement | Full hire-to-exit E2E with financial history intact |
| 10 | Reporting and Process Center | Source-backed reports and responsive private download E2E |
| 11 | Exact-package CI, parallel run, restore/rollback, cutover | Exact SHA, required sign-offs and written go/no-go |

## 7. Minimum acceptance tests

- Package: exact SHA/size/ZIP/root/path uniqueness/version parity; complete PHP lint on PHP 8.3/8.4; JS parse; CSS module parity/PostCSS; WPCS/security and exact Semgrep target coverage. Skipped gates stay pending.
- Lifecycle: all positive/negative state transitions, no self-approval, stale version and replay, locks/concurrency, role/location denial, private documents and audit failure rollback.
- Payroll: adviser-approved PAYE/pension vectors for every band boundary, rounding, effective-date and month boundaries, joiner/leaver/partial period, arrears, allowance/in-kind and rule supersession; line/period totals reconcile.
- Accounting: duplicate generation/payment callbacks do not double post; closed period guard; every journal balances; linked corrections/reversals; bank settlement distinct from payroll posting; remittance evidenced.
- End-to-end: workforce plan → vacancy → candidate/selection → contract/onboard → employee → attendance/leave/overtime → increment/promotion → payroll prepare/review/Owner approval → payslip/payment/remittance → balanced GL/PLEDGER → exit/final settlement/access removal.
- UX/operations: 320/360/390 px, tablet and desktop; keyboard/touch; visible errors; no clipped controls; authorized download has correct MIME/name/size/hash and audit; restore/rollback and deployed-source parity proven.

## 8. Legal sources (starting points, not certification)

- Labour Proclamation No. 1156/2019: https://justice.gov.et/en/law/labour-proclamation/
- Income Tax Amendment Proclamation No. 1395/2025, Federal Negarit Gazette No. 64: https://www.ffic.gov.et/Portals/0/1395-2017.pdf
- Ministry of Finance tax directive register (draft entries are not law): https://www.mofed.gov.et/mof-directive/tax-directive/
- Pension contribution Directive No. 1087/2025: https://justice.gov.et/en/directives/%E1%8B%A8%E1%8C%8D%E1%88%8D-%E1%8B%B5%E1%88%AD%E1%8C%85%E1%89%B5-%E1%88%A0%E1%88%AB%E1%89%B0%E1%8A%9E%E1%89%BD-%E1%8C%A1%E1%88%A8%E1%89%B3-%E1%88%98%E1%8B%8B%E1%8C%AE-%E1%8C%88%E1%89%A2-%E1%8B%95-3/
- Pension implementation Directive No. 1085/2025: https://justice.gov.et/en/directives/%E1%88%88%E1%8C%8D%E1%88%8D-%E1%8B%B5%E1%88%AD%E1%8C%85%E1%89%B5-%E1%88%A0%E1%88%AB%E1%89%B0%E1%8A%9E%E1%89%BD-%E1%8C%A1%E1%88%A8%E1%89%B3-%E1%88%A5%E1%88%AB-%E1%8A%A0%E1%8D%88%E1%8C%BB%E1%8C%B8/
- Personal Data Protection Proclamation No. 1321/2024: https://justice.gov.et/en/law/personal-data-protection-proclamation/

Legal source pages do not constitute Stockland’s legal opinion. Verify full text, amendments, clause and effective date with a qualified Ethiopian reviewer. Do not claim full legal compliance until rule versions and tests have written sign-off.

## 9. No-go criteria

No live release until exact installed source identity, audited dependencies/data health, approved SOP/RACI and ERD, qualified legal sign-off, full synthetic lifecycle/security tests, working private downloads, payroll/payment/remittance and GL/PLEDGER reconciliation, exact-package CI/security, backup restore and rollback, Owner/HR/Finance acceptance, and written go-live decision. No live employee pay, schema, payroll history or GL/PLEDGER data was changed in preparing this plan.
