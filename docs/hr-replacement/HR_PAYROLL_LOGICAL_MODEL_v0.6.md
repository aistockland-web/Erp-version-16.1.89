# Stockland Ethiopia ERP — HR/Payroll Logical Model and Service Decision Matrix v0.6

Date: 27 October 2026  
Authority: Notion HR Design Reset v0.5 and Required Scope — Full HR & Payroll Lifecycle and Ethiopian Statutory Rules.  
Status: Source-led conceptual design for review. No physical table count, DDL, production migration, payroll formula, or cutover is approved by this document.

## 1. Non-negotiable design authority

The owner’s current instruction supersedes previous table-count proposals. The prior 27-table design and any totals derived from it are historical only. The final physical model must follow a source-mapped conceptual ERD, domain/service ownership, privacy/control analysis, row-level dependencies, and migration/rollback evidence. Do not assume 14, 16, 27 or any other physical table count. Logical aggregates below describe business concepts, not a promise that each becomes one SQL table.

The four legacy HR/payroll tables inside the 16.1.95 49-table baseline are transition evidence, not target authority. Keep them unchanged and read-only during migration rehearsal until cutover is separately approved. No code is allowed to drop, rename, truncate, rewrite, or deactivate legacy payroll history as part of a normal install/update.

The 16.1.97 resource-library/document candidate and HR Word/PDF resources are supporting content only. A working download button does not constitute a complete HRIS. No document-only candidate is accepted as the HR/payroll product.

## 2. Exact baseline facts and limits

Locked installer: STOCKLAND_ERP_16.1.95_CANONICAL_WORDPRESS_UPDATE.zip  
SHA-256: 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625  
Package: 75 files, single stockland-erp-app/ root. The source schema contract maps 29 operations + 16 accounting/procurement + four legacy HR/payroll = 49 table keys. Static DDL has no explicit SQL foreign-key constraints. Source contains 116 REST registration call sites, and 13 in the legacy HR controller (15 HTTP method/path operations per route trace). This is source-level inventory; it is not live DB proof, route penetration testing, or evidence of the currently deployed package.

Legacy reference consumers from the source scan:
- employees: 8 source files, including frontend and accounting/requisition API paths.
- employee_compensation: 5 source files, including payroll statutory calculations.
- payroll_periods: 4 source files.
- payroll_lines: 4 source files.
These consumer counts are lexical navigation hints. The dependency owner must trace each query/read/write and tests before replacing it.

## 3. Logical domains and canonical relationships

### A. Person, candidate, and employee identity
A canonical person identity must span candidate → application → selected hire → active employment → former employee without duplicating identity. Store person PII in a restricted domain; candidate consent, retention/deletion and employee privacy purposes differ. A person can have zero or more applications and historical employment relationships, subject to company policy/legal uniqueness. Candidate access is restricted separately from active employee access.

### B. Organization, job and workforce authority
Represent organizational unit, position, grade/pay band, reporting line, legal employer, physical work location, payroll location and cost center as distinct concepts. Reuse the existing location list only for physical locations and branch scope; do not treat store branch as a legal employer or a pay group. A versioned workforce plan owns headcount/cost budget. A position belongs to an approved organizational unit and optional grade; a vacancy/requisition references an approved position and budget authorization.

### C. Recruitment and selection
Applications join a candidate/person to a vacancy. Interview rounds/scorecards and evidence have explicit actor, date, rubric version and stage state. Screening/selection recommendations are inputs to a human decision; the system must not make final automated hiring/rejection decisions. A selection requires rationale, human reviewer and audit event. Offer/contract starts only from the approved decision.

### D. Contract, hiring, onboarding and employee master
An employment relationship references the canonical person plus legal employer, position, grade, reporting manager, workplace and effective start/end/status dates. Contract versions preserve old terms and effective dates. Onboarding tasks reference an employment relationship, have owner/due date/evidence/status, and block activation when marked mandatory. Employee lifecycle events capture transfer, probation, suspension/leave-of-absence/return, promotion, demotion and contract extension as effective-dated events, never overwrite history.

Signed contracts/identity/bank/tax documents are stored only in a verified private document service. The existing media/attachment path must not be assumed private. If hosting cannot enforce private access, sensitive files must not be stored there; keep references and evidence metadata only. General HR resource documents are separate from employee-specific files and need separate access rules/download audits.

### E. Shift, attendance, overtime and exceptions
Published rosters/shift assignments, time records, daily exceptions, lateness/absence, worked hours, breaks, overtime, night/rest-day/public-holiday segments and manager corrections. Every correction has old/new values or a minimal change hash, reason, actor, reviewer and timestamps. Approved time inputs freeze at payroll cutoff; later corrections become linked adjustment records rather than retroactive silent edits. Legal/company rules are effective-dated and approved; unknown categories or rules block calculation.

### F. Leave and balances
Leave calendars, policy entitlements, eligible leave types, accrual methods, opening balances, earned/taken/adjusted/carry-forward/expired movements and period cutoffs. Employee request → manager/HR decision → schedule/coverage update → approved payroll consequence. Leave balance is movement-based or provided by a reconciled balance service, never manually overwritten. Final settlement computes only the legally/contractually due balance after rule approval.

### G. Grade, compensation and benefits
Effective-dated compensation assignment per employment relationship with pay frequency, base amount, currency, grade/band, start/end dates, reason, contract/approval reference and status. Component-level earnings, allowances, benefits, deductions, reimbursements, bonus/commission, shift premiums, overtime, arrears and in-kind benefits. Each is a factual pay item; its label does not determine PAYE or pension treatment. Distinguish employee pay from one-off reimbursement, advance/loan movement, deduction, payroll correction and nonemployee service payment. No salary may be fabricated or copied from another employee.

### H. Ethiopian legal rules and calculator
Build a versioned effective-dated legal-rule register with jurisdiction, instrument/title and catalogue alias, article/clause, official URL/file and SHA-256, publication date, provision-specific effective dates, scope, taxable/pensionable base, bands/rates, exceptions, rounding, interpretation, qualified reviewer, approval identity/date, test vectors, status and supersession. Rule lifecycle DRAFT → LEGAL_REVIEW → APPROVED → RETIRED; REJECTED/BLOCKED are explicit outcomes. LEGAL_REVIEW rules cannot finalize/post payroll. Owner business approval cannot substitute for qualified legal/payroll sign-off.

Use exact decimal/minor-unit arithmetic, deterministic rounding, immutable rule snapshots on every payroll run and line, and explanatory itemized calculations. The rule is selected by earning/pay-period date, not current date alone. PAYE and pensionable pay are separate. Do not automatically subtract employee pension from the PAYE base until a qualified Ethiopian adviser has signed the rule-specific interpretation. Notion’s progressive-band numbers are test fixtures pending Gazette clause mapping, not active legal rules.

### I. Payroll run, approval, payment and remittance
A payroll run has period/cutoff, eligible population, frozen input snapshot, rule-set references, calculation hash, status, totals and audit trace. Per-employee detail records amount, taxable/exempt/pensionable allocation, quantity/rate, source record, rule/version, rounding and calculation trace. Corrections are separate linked adjustment/reversal records. A finalized run and its lines are immutable.

Separate preparer, reviewer and final approver. Management may prepare/submit; a different authorized Owner/Finance approver performs approval according to SOP. No self-approval. A PAID flag is not bank evidence: model payment batch/instructions, per-employee outcome, external reference, partial/failed/retry status, cash voucher or bank response, remittance evidence, and reconciliation. Retries are idempotent and never create a second expense. Payroll payment clears payroll liabilities, not salary expense a second time. Post only through the existing approved GL/PLEDGER API after verifying its transaction/idempotency contracts.

PAYE and pension remittance have separate liabilities, return/report periods, submission/receipt references, due dates, paid amounts, status, journal and reconciliation. Do not claim government e-filing or API success without a verified official service and actual response.

### J. Separation and final settlement
A separation case records lawful category/reason, notice/effective/last-worked dates, evidence reference, dispute/hold status, required clearance checklist and independent approval. Freeze the last approved attendance, compensation and leave inputs; calculate due regular/partial pay, approved arrears/benefits/bonus, unused leave if legally/contractually due, notice/severance only if applicable under an approved rule, and lawful recoveries/deductions. Show all items and sources on settlement worksheet; compare gross, tax/pension, deductions and net. Require preparer/reviewer/Owner approval, payment reference/outcome, asset/access clearance, service documentation and Finance reconciliation. No settlement overrides an active dispute hold without an explicitly approved resolution. Preserve employee, payroll, GL and audit history after exit.

### K. Nonemployee creators, influencers, freelancers and service providers
Keep individuals paid for services distinct from employees and from registered supplier/customer identities unless source evidence proves an appropriate existing AP profile model. Record agreement/campaign, deliverable/evidence, date accepted, cash and non-cash value, payee identity, TIN/licence/residency facts where relevant, classification/withholding decision and rule source/reviewer. Route through one central Finance/AP queue and approved payment/GL controls. Branches may submit requests/evidence, not operate payment desks. Lack of registration alone neither proves tax-free nor independently chooses withholding. No fictitious supplier or employee records.

### L. Reporting, audit and resources
Report staffing/vacancy, recruitment stage, turnover, attendance, leave, overtime, compensation change, payroll totals/variance, statutory remittance, payout status, final settlement and audit activity with date/location/permission filters. Sensitive fields remain permission scoped. Social account data phase 1 is manually captured/timestamped, with stale/missing-data flags and source; no passwords, MFA/recovery codes, session scraping, auto-posting or invented live metrics. HR resource downloads are versioned, approved, language/role tagged, authenticated, server-side authorized, private when sensitive and audit-logged.

## 4. Service reuse / retention decision matrix

| Existing surface | Proposed disposition | Required proof before reuse or retirement |
|---|---|---|
| stock_locations and user-location scope | Reuse for location identity/scope; add legal-employer/cost-center/payroll-group only if source audit requires them | Exact DDL/query/authority trace and cross-location negative tests |
| stock_users, role/capability logic, stock_user_locations | Reuse identity/access source; do not duplicate login/role tables without proof | Capability grants/revocation, Owner/Management separation and API tests |
| stock_workflow_events | Reuse only if actor/state/object/version/immutability behavior fits; otherwise create HR domain events and link them | Append-only and actor/transition tests |
| stock_audit_logs | Reuse only after PII minimization, role-scoped audit-reader access and retention are proven | Sensitive read/change events, hash-chain and audit-reader tests |
| stock_gl_accounts, stock_gl_journals, stock_gl_lines | Reuse accepted accounting post path; no ad-hoc journal writes | Balanced entries, source idempotency, open-period, rollback and replay tests |
| stock_payments, allocations, expenses | Reuse where semantics fit; payroll payout outcomes/remittances remain explicit | Verify payment direction/allocations, liability clearing, no double expense |
| Existing supplier/customer masters | Do not force an employee/creator into these; separate payee/AP identity only if required after audit | Identity/deduplication, AP aging, withholding, privacy and GL tests |
| employees | Retain as read-only transition source after migration rehearsal; replace target authority only at cutover | Full row/identity/relationship mapping and legacy consumers bridged |
| employee_compensation | Retain history; new effective-dated assignments/components own target | Effective dates, approval, totals; missing values remain exceptions |
| payroll_periods | Retain immutable historical runs; new run/approval model target after reconciliation | Every period/status/date and journal reference reconciled |
| payroll_lines | Retain immutable legacy detail; new line detail target after row/money reconciliation | Every line mapped or exception documented; no silent recomputation |
| WordPress media/attachments | Not assumed suitable for private HR docs | Anonymous/wrong-role/wrong-person direct-access tests; otherwise metadata only |
| Current HR routes/UI/reports/health | Keep compatibility facade while replacing authority behind controlled adapters | Every route, report, bootstrap, health check and consumer mapped/tested |

## 5. Workflow and state invariants

- Workforce plan: DRAFT → SUBMITTED → APPROVED/REJECTED; amendments versioned.
- Recruitment: OPEN → SCREENING → INTERVIEW → RECOMMENDED → SELECTED/REJECTED/WITHDRAWN; final decision human, reasoned, audited.
- Hire: OFFER_DRAFT → OFFER_APPROVED → CONTRACT_PENDING → SIGNED/VERIFIED → ONBOARDING → PAYROLL_SETUP_APPROVED → PENDING_ACTIVATION → ACTIVE.
- Leave/time: DRAFT → SUBMITTED → APPROVED/REJECTED/CANCELLED; corrections linked and approved.
- Compensation/rules: DRAFT → SUBMITTED → APPROVED/REJECTED → SUPERSEDED; legal rules require qualified LEGAL_REVIEW before business activation.
- Payroll: DRAFT → CALCULATED → EXCEPTIONS_PENDING or READY_FOR_REVIEW → SUBMITTED → OWNER_APPROVED → POSTED → PAYMENT_IN_PROGRESS → SETTLED/PARTIALLY_FAILED/FAILED → CLOSED. Posting, payout and remittance may use distinct sub-states; one paid flag cannot mean all obligations settled.
- Separation: DRAFT → REVIEW → APPROVED or HELD → CALCULATED → APPROVED_SETTLEMENT → PAYMENT_IN_PROGRESS → SETTLED → CLOSED.
- Each transition checks server-side role, object scope, expected current version, maker/checker, idempotency key and transaction outcome. Failed audit/GL posting must roll back or create a proven recoverable exception. Finalized rows are immutable; corrections are linked adjustments/reversals.

## 6. Authorization and segregation of duties

Target abilities need to distinguish HR field-limited view/maker/approver, hiring panel for assigned candidates only, payroll preparer, payroll reviewer, independent Owner payroll approver, Finance payout operator, Finance reconciliation, qualified legal/tax reviewer, read-only auditor and platform administrator. Exact capability names wait for the source authority audit. PHP handlers enforce role, record/person/field/location/period/state scope; UI hiding is not security. Revoked accounts or altered IDs must fail closed.

## 7. Migration and release gates

1. Pin artifact, DDL, manifest and versions; separately verify live file/database parity.
2. Trace every SQL/query consumer, REST route, UI, report, System Health, import/export and GL path referencing old HR tables.
3. Review conceptual ERD/service ownership; freeze physical schema after merge/split/reuse decisions, indexes, uniqueness, concurrency and privacy review.
4. Create copy-only migration mapping; keep real PII/salary out of GitHub/artifacts; use synthetic test data in CI.
5. Run key/DDL inventory, migration idempotency/resume, orphan/duplicate/fingerprint checks.
6. Test isolated WordPress/MariaDB including all screens/routes, PHP 8.3/8.4, JS/CSS/REST/mobile/accessibility and route-level negative authorization.
7. Run payroll boundaries, decimals, effective dates, unknown classifications, joiners/leavers, overtime/leave, retro corrections, retries/concurrency, and no duplicate posting.
8. Run two complete parallel payroll periods and explain every delta with independent Finance/legal disposition.
9. Prove backup restore, rollback, deployed source parity, deep integrity, and Owner + HR + Finance plus qualified legal/payroll approvals.
10. Separate cutover approval. Old tables remain read-only through agreed retention; only a separately approved action may retire them after all consumers and parity checks are resolved.

## 8. Legal sources and gating

Starting official sources:
- Labour Proclamation No. 1156/2019: https://justice.gov.et/en/law/labour-proclamation/
- Income Tax Amendment Proclamation No. 1395/2025 / Gazette No. 64: https://www.ffic.gov.et/Portals/0/1395-2017.pdf
- Ministry of Finance Tax Directive register: https://www.mofed.gov.et/mof-directive/tax-directive/
- Private Organization Employees’ Pension Proclamation and current directives (including 1085/2025 and 1087/2025): https://justice.gov.et/am/sector/social-security-pensions/
- Personal Data Protection Proclamation No. 1321/2024: https://justice.gov.et/en/law/personal-data-protection-proclamation/

Exact source identity/title/year aliases, clause mapping, provision-specific effective dates, PAYE base/pension interaction, transport treatment, pensionable salary, overtime, leave and final settlement require qualified Ethiopian review. Legal discovery and arithmetic examples are not certification. Unknown or conflicting rules cannot be activated.

## 9. Definition of done

Production-ready requires approved legal rules and Stockland SOPs; exact runtime code/schema; requirement-to-route/data/test trace; complete synthetic HR-to-settlement E2E; authorization/privacy negative tests; balanced idempotent GL and payout/remittance evidence; two parallel payroll cycles; migration/orphan/historical-money reconciliation; backup/restore/rollback and deployed source parity; and recorded Owner/HR/Finance plus qualified legal/payroll sign-off. A ZIP, lint suite, resource download, or successful CI alone is not complete acceptance.

## 10. Project references

- Notion design reset: https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d
- Notion required scope: https://app.notion.com/p/4026db0f767e81aaa3ecc4383f5402f9
- Notion resource register: https://app.notion.com/p/4056db0f767e810d8ca2cbc1fe3e830e
- GitHub lifecycle issue #15: https://github.com/aistockland-web/Erp-version-16.1.89/issues/15
- GitHub design reset issue #23: https://github.com/aistockland-web/Erp-version-16.1.89/issues/23
- GitHub CI issue #17: https://github.com/aistockland-web/Erp-version-16.1.89/issues/17
- Baseline audit scripts: ci/qa/audit_hr_replacement_baseline.py and ci/qa/test_hr_replacement_baseline.py
