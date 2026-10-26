# Stockland ERP — Full HR, Payroll, People & Individual-Payee Replacement Plan v1.0

**Date:** 27 October 2026  
**Status:** implementation authority draft for engineering; not a legal approval, schema-freeze record, or production release.  
**Repository:** `aistockland-web/Erp-version-16.1.89`  
**Verified source package:** Stockland ERP 16.1.95 canonical installer, SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`, 1,102,405 bytes, 75 unique package files, ZIP CRC verified. This proves the retrieved build artifact only—not the installed site version.

## 1. Product decision and source-of-truth hierarchy

Build one first-class **People & Payroll** workspace inside the existing Stockland ERP/PWA. The HR replacement is a domain system, not a DOCX library, not a form pasted onto the existing payroll page, and not an extension of the current four tables as permanent authorities.

The four legacy tables `employees`, `employee_compensation`, `payroll_periods`, and `payroll_lines` become **migration sources only** for the new target domain. Do not drop, rename, truncate, rewrite, or disable them during discovery or initial rollout. Keep them unchanged and queryable as controlled read-only transition sources until source-to-target mappings, data-health findings, full reconciliation, backup/restore, rollback, parallel payroll and explicit cutover approval pass.

The newest Notion design reset says not to reuse the earlier fixed 27-table proposal and not to lock the physical count before the actual schema/dependency/data-health audit. Some older paragraphs in the long Notion page contain a historical “27 new tables” direction. This plan resolves that conflict by preserving the *functional intent*—replace the four legacy HR/payroll authorities—but **does not blindly copy the retired 27-table inventory or pre-commit to a physical table count**. The target entities below are a logical model; physical tables and service reuse will be decided by evidence from the exact source.

Authority order for this work:
1. Latest explicit owner direction in Notion “Required Scope — Full HR & Payroll Lifecycle and Ethiopian Statutory Rules” and “HR Design Reset v0.5”.
2. Approved Stockland SOPs and role-specific policy decisions after Owner/HR/Finance review.
3. Actual 16.1.95 source/package, live-safe DB fingerprints and dependency maps.
4. Official current Ethiopian instruments and written independent legal/payroll/tax/pension review.
5. Secondary software examples (including Odoo-style UX) are interaction references only, not Stockland policy or legal authority.

## 2. Outcome: one end-to-end operating system

The target is one company-wide workflow with a Central People & Payments queue. Employee work location can be Central Warehouse, Goro, Gerji, Mexico, Megenagna, or another approved location; that location is an attribute and access scope, not a separate HR/payroll department. The Owner remains the final authorized approver for the designated high-impact steps; Management/HR prepares and documents the evidence; Finance controls payables, disbursement, remittance and ledger reconciliation; an independent qualified Ethiopian reviewer approves statutory interpretation.

The home screen must give each role a clear next action, actionable exception and current state. Most ordinary work should be a guided form/wizard with prefilled facts, controlled dropdowns, evidence upload/reference, suggested notes, automatic calculations only where a rule is approved, and one submission path. The system must not invent data or tax conclusions to move a workflow forward.

### Entry point: “What are you recording?”
1. **Employee work / benefit** → employment lifecycle and employee payroll.
2. **Employee expense reimbursement** → approved expense process, with factual receipts; not automatically exempt from tax.
3. **Digital-content creator / influencer** → an individual payee and creator-specific tax-review path.
4. **Freelancer / service supplier** → individual payee/service payable and legally reviewed withholding path.
5. **Other individual payment / exception** → no default classification; Finance/legal review before approval.

Relationship/work type, contract/work classification, and tax classification are separate fields. No employee is reclassified as a supplier just to bypass payroll; no natural-person payee is fabricated as a registered company or employee merely to enable payment.

## 3. Full lifecycle map and stage gates

### A. Workforce planning and positions
- Effective-dated organizational units, department, position, grade/pay band, manager, cost centre, planned headcount, work location and budget.
- Workforce plan with fiscal period, justification, affordability/budget reference, headcount request and independent authorization.
- Position/requisition creation only under an approved plan or documented exception; vacancy state, openings, approved range, recruiter, due date and restrictions.

### B. Recruitment and selection
- Candidate/person identity is canonical and deduplicated; an applicant who becomes an employee keeps the same person identity.
- Vacancy publication/source, application, required data notice/purpose, screening, interview panel, rubric/version, evidence, reference/checks where lawful, decision rationale and human decision.
- Restrict access to applicant records; define retention/deletion and lawful exceptions before collecting unnecessary sensitive data.
- Offer requires authorized approval and cannot create an active employee before contract/onboarding gates.

### C. Hire, contract and onboarding
- Contract/terms: legally supported contract type, start/end, role/grade, legal employer, location, manager, probation dates and amendments; never treat probation as a tax category.
- Signed contract stored in an approved private repository; ERP stores a protected reference, checksum, type, version and approval state—not a public attachment URL.
- Conditional onboarding tasks: identity/work-eligibility verification, contract, policy acknowledgements, role induction, safety/training, equipment, secure payroll/payment setup and scoped access request.
- Candidate-to-employee conversion is transactional/idempotent and cannot create duplicate employees or activate without required independent decisions.

### D. Employee master and lifecycle events
- Person identity, employee number, contacts, legal employer, current status, primary location, department, position/grade, manager, work schedule, cost centre and effective-dated employment history.
- Events: onboarding, probation checkpoints/confirmation, renewal, transfer (including the five Stockland locations), promotion/demotion, increment, role change, approved absence/return, disciplinary/employee-relations case where lawfully required, separation and archive.
- All material changes record actor, effective date, reason, approval, evidence reference, previous/current version and workflow event. Avoid unnecessary personal data in audit payloads.

### E. Attendance, shifts, overtime, rest days and holidays
- Effective-dated shift/roster and calendar; scheduled versus actual time; absence/lateness; regular/night/rest-day/public-holiday/overtime buckets; cutoff snapshots.
- Correction flow requires reason, evidence when required, and an approver independent of the original entry; never silently overwrite the original time record.
- Any hour limits, overtime conditions/rates, public-holiday rules and night/rest-day premium are sourced to the exact approved law/contract/policy version. Unknown calendars/rules trigger a blocking exception.
- Managers submit location records; HR/payroll consolidates company-wide records. Duplicate punches, overlaps, negative durations, missing clock-outs, boundary-crossing shifts and approved correction history are tested.

### F. Leave and absence
- Leave catalogue separates statutory minimum, collective agreement/contract and Stockland's more generous internal policy.
- Eligibility, accrual, grant, carry-forward, expiry, use, unpaid conversion and final balance are separate audited transactions; never store only a mutable balance.
- Employee request → manager coverage check → authorized decision → balance reservation/posting → payroll/attendance effect → cancellation/correction.
- Backdated requests, overlaps, leave across calendar/payroll boundaries, partial days, leaver balances, policy changes and negative balance prevention require tests. Medical/private attachments receive restricted permissions and retention treatment.

### G. Grades, compensation and benefits
- Effective-dated salary/grade/pay band; promotion/increment proposals; contract basis; budget and authority; compensation components for base pay, overtime, shift premiums, commissions/bonuses, allowances, benefits-in-kind, reimbursement, arrears, advances/loans, approved deductions and final-settlement items.
- A component is factual data, not a legal tax classification. Each component has a linked approved rule version, taxable/pensionable treatment, evidence requirements, contract basis and review status.
- No free-form toggle that makes an item exempt. An unknown classification stays `LEGAL_REVIEW` and blocks a finalized run/payment if material.
- No salaries or statutory classifications can be guessed from peer employees, historical examples or missing records. The latest live health report showing nine unresolved compensation records is an exception to resolve from actual contracts by authorized people—not a seed-data instruction.

### H. Monthly payroll, payslips and accounting
1. Open a full valid payroll period; snapshot the applicable employee/contract/compensation/calendar and approved statutory rule versions.
2. Preflight joiners/leavers, contract dates, compensation, time/leave/overtime approvals, payment data, changed rules, arrears and exceptions.
3. Freeze input snapshot at cutoff. Corrections after freeze use controlled reopen/adjustment with reason and authority.
4. Calculate itemized gross earnings → approved taxable base → PAYE → employee pension → other legally authorized deductions → net pay; calculate employer pension and other employer cost separately.
5. Provide explainable per-item/employee payslip preview, exact rule/effective date, rounding, variance from prior periods and blocking exceptions.
6. Management submits; an independent authorized Owner/reviewer approves the exact run version. No self-approval; no rule with status `DRAFT`, `LEGAL_REVIEW`, `EXPIRED`, conflicting dates or missing approval can finalize.
7. Create a unique disbursement batch and per-employee payment instruction. “Batch created” is not “paid.”
8. Store bank/payment provider reference, each payment outcome (pending, confirmed, failed, returned, reversed), timestamps, retry/idempotency key and external reconciliation. Partial failure must not duplicate the successful rows.
9. Post through the existing approved shared GL/PLEDGER service after checking the mapping and posting contract. Payroll liability/expense journal must balance; posting and status change are atomic or have a verified compensating recovery path.
10. Track PAYE and pension remittance separately from employee payment, including due period, declaration/export, payable amount, payment reference/receipt, submission/accepted/rejected/pending state and reconciliation.
11. Issue payslips, reports and exception register; lock and close the period. Corrections are linked adjustment/reversal runs, never edits to posted histories.

Payroll calculation formulas use decimal-safe money arithmetic, not binary float. Rule and input snapshots are immutable/versioned, with reproducible results. The current legacy PAYE base subtracts employee pension; that disputed basis is a legal blocker and must not be treated as approved from code or old sample arithmetic.

### I. Employee change, exit and final settlement
- Exit request/reason and lawful category, approvals, notice dates, last day worked, attendance/time cutoff, accrued leave, salary/arrears, earned allowances/bonus, authorized deductions/advances, notice/severance and other items *only where the approved legal/contract rule applies*.
- Clearance checklist: stock/equipment, accounts/access, outstanding advances/loans, company property, handover and required documents; each action assigned and time-stamped.
- Settlement calculation stores every input/rule/source, gross amount, each deduction, PAYE/pension treatment, net amount, reviewer, Owner approval and payslip/final statement.
- Payment uses the same evidence-driven batch workflow; do not mark settled until actual confirmation and reconciliation. Revoke access only through the separately approved identity/access action.
- Preserve historical contract, time, payroll, GL and audit evidence according to an approved retention policy.

### J. Individual creators, influencers and service providers
- One restricted natural-person payee profile shared across later payments, deduplicated against existing people without turning the individual into an employee or invented registered business.
- Captures relationship, TIN/licence/residence facts where applicable, agreement/PO, scope, campaign/service category, deliverable/date/acceptance evidence, cash plus in-kind value, payment account ownership verification, reviewer, classification/rule, withholding decision, payment proof and remittance evidence.
- Digital-content activities (sponsorship, promotion, affiliate activity, subscriptions, platform monetization and product/service in exchange for promotion) are separate from ordinary services (design/editing/software/translation/consultancy/etc.). Employee work remains payroll.
- One central Finance/AP queue: `Draft → Submitted → Auto-check → Needs information / Legal-tax review → Approved → Ready for payment → Paid / Failed → Reconciled → Closed`. No branch payment desks.
- Use existing AP/GL/payment services only after confirming they can represent a natural-person payee and required withholding/remittance evidence. Do not fake a supplier/customer to fit a schema.
- Until written official/legal interpretation resolves creator versus Article 97 withholding overlaps, affected payments remain `TAX_CLASSIFICATION_REVIEW`; no invented 15%/3%/30% stacking or unverified threshold.

### K. HR Process Center and management reporting
- HR → HR Process Center → Downloadable resources → Function → Role → Language → Download. Only approved, versioned templates and SOPs; each file includes owner/version/effective date/approval status/hash. Genuine DOCX/PDF MIME and file extension must match. Download path is authenticated, role-scoped, no-store, audited and tested against anonymous/unauthorized direct access.
- Reports: headcount/vacancy funnel, contract/probation expiry, attendance/leave/shift exceptions, overtime, grade/increment, payroll variance, missing compensation/legal-classification tasks, payment failure/return, statutory remittance, final settlements, audit and role-scope exception. Include company/date/location filters and print/export, but never leak salary/identity data to ordinary branch roles.
- Social accounts: phase 1 manually captured timestamped platform insights/campaign metrics with source/as-of time/staleness; direct sign-in does not mean automatic analytics. No password/MFA/recovery-code or session-cookie storage, no scraping or auto-posting. Read-only API/OAuth may be added only after official scope/security review.

## 4. Target architecture and ownership

Build on verified existing services, not a competing ERP:
- **Database/money/transactions:** existing DB and decimal-money service after row-lock/transaction behavior is verified for current MariaDB/PHP.
- **Authority and location:** existing role/capability/location service, with specific server-side capabilities for HR master, recruiter, timekeeper, manager approvals, payroll maker, Finance/AP, Owner approver, legal-rule reviewer and read-only auditor. Exact capability names to be added only after role mapping.
- **Workflow/audit:** reuse existing workflow event and audit chain only after confirming immutability, sensitive-read logging and PII minimization. Approval decisions are separate events from field edits.
- **Accounting:** reuse shared GL posting service only after proving journals are atomic, balanced, idempotently linked to source runs and block closed periods. Do not create a parallel ledger.
- **Documents:** private vault adapter, opaque reference and hash; the public WordPress uploads directory is not a secure personnel-document vault by default.
- **Notifications/reports:** reuse only after privacy and idempotency checks. Each high-impact action surfaces a clear status and recoverable error.
- **REST/API:** every operation has explicit permission callback plus handler-side authority/state/record/location checks; use nonces/auth as appropriate, validate IDs/dates/amounts, bounded pagination and concurrency tokens. UI hiding is never authorization.
- **UI:** one responsive workspace with navigation by lifecycle, list/detail/form, saved filters, search, status/next-action, timeline, accessible keyboard focus, clear loading/error/empty states, printable payslips and downloadable reports. Test 320/360/390 px and desktop.

## 5. Logical domain model — entities before physical table count

The following are logical entities/aggregates, not a frozen physical-table checklist. During implementation the ERD must identify which require independent persistence versus an existing proven service, and which are child detail relations. The final schema must be normalized enough for constraints/idempotency but small enough to avoid duplicate master data.

1. Person (candidate/employee/individual payee identity continuity)
2. Employment relationship and effective-dated employee status
3. Workforce plan / headcount budget
4. Position, grade/pay band and org/location assignment
5. Vacancy/requisition and candidate application/interview/selection
6. Contract and employment amendment
7. Secure document metadata/reference and acknowledgement
8. Onboarding task and access-provision request
9. Shift/roster and work calendar
10. Attendance/time entry plus correction history
11. Leave policy, entitlement/accrual ledger, request and approval
12. Compensation assignment and component item(s)
13. Versioned statutory/policy rule set and normalized rule clauses/test vectors
14. Payroll run/input snapshot and per-person calculated lines/detail items
15. Payroll payment batch and per-recipient settlement rows
16. PAYE/pension/statutory remittance and receipt reconciliation
17. Exit case, clearance task and final settlement detail
18. Natural-person payee relationship and individual payable/campaign/service evidence
19. Audit/workflow events where existing services are proven suitable
20. Document/resource catalogue and download audit
21. Performance/training/review event records as approved by SOP and data-minimization review
22. Reporting snapshots and exception queue where material persisted state is required

Candidate identities are canonical; multiple relationships can link to one person. Monetary history is append-only after posting. Balances (leave, payable, advances) should be explainable from transactions, not only mutable totals. Foreign-key metadata constraints are absent in the reviewed baseline, so each relationship needs an explicit validator/orphan query and only safe indexes/unique keys that are compatible with actual data.

## 6. Legal rules register and Ethiopian payroll control

Every active rule must store:
- exact jurisdiction and affected person/relationship/payment type;
- official legal instrument, article/clause, full-text source location and SHA-256;
- publication date, provision-specific effective-from/to, and applicability logic;
- rule method/parameters, exceptions, proof required, rounding and test vectors;
- reviewer identity/qualification, review decision and signed approval reference/date;
- change ticket, regression test result and supersession link.

Never hard-code statutory tax bands, pension rates/basis, transport exemption, leave accrual, overtime premium, public holiday, notice, severance or creator threshold directly in business logic. A rule can be observed, drafted or under legal review without being executable. Calculation finalization must fail closed for missing, conflicting, draft, expired or unsigned rule coverage.

Current open legal topics requiring written independent Ethiopian review include: the exact current gazetted PAYE instrument and effective dates; pensionable salary and whether employee pension reduces taxable PAYE base; continuing transport/allowance exemptions after the 2025 amendments; tax treatment of reimbursement versus flat allowance; overtime/rest/holiday rules; leave and final settlement; digital creator Article 22 versus Article 97 withholding; TIN/licence scope and thresholds; required tax/pension return formats and deadlines. No 100% legal-compliance claim can be made until this is resolved.

Official starting links (source discovery, not legal sign-off):
- [Labour Proclamation No. 1156/2019 — Ministry of Justice](https://justice.gov.et/en/law/labour-proclamation/)
- [Income Tax (Amendment) Proclamation No. 1395/2025 — official publication copy](https://www.ffic.gov.et/Portals/0/1395-2017.pdf)
- [Ministry of Finance — Tax Directives](https://www.mofed.gov.et/mof-directive/tax-directive/)
- [Private Organization Employees’ Pension and current directives — Ministry of Justice](https://justice.gov.et/am/sector/social-security-pensions/)
- [Private Organization Employees’ Social Security Administration register including Directive No. 1087/2025](https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/)
- [Personal Data Protection Proclamation No. 1321/2024 — Ministry of Justice](https://justice.gov.et/en/law/personal-data-protection-proclamation/)
- [National Bank of Ethiopia — notice on personal/third-party bank-account use](https://nbe.gov.et/nbe_news/press-release-notice-on-use-of-personal-or-third-party-bank-accounts/)

## 7. Migration and retirement plan for legacy HR tables

**Do not run against production during this engineering stage.**
1. Lock the exact baseline artifact hash; extract source; hash all package files; fingerprint all 49 registered DDLs and physical column/index metadata from a sanitized read-only database export.
2. Produce a field-level dependency map for the four legacy tables across REST, JS, reports, health checks, SQL, payroll calculator, GL source references, workflow/audit and setup migrations. Static grep is only a navigation hint; trace execution paths.
3. Create a sanitized data-health report: duplicate identity keys, orphan IDs, invalid dates, inconsistent status, missing compensation, duplicate payroll line/period references, totals and journal mismatches. Never export PII/salary/bank/tax values to GitHub.
4. Specify one-to-one or one-to-many mappings into the target. Map legacy employee IDs and old payroll-period/line IDs to new canonical IDs; retain source table/key/hash in the migration ledger.
5. Back up staging; rehearse an idempotent versioned migration on a disposable copy. Do not alter legacy tables. Verify row counts, null/missing fields, totals by period/status, per-person totals and exact GL references.
6. Run old/new payroll in parallel using synthetic fixtures and authorized sanitized staging data for at least two complete payroll periods; document every difference and approved disposition.
7. Cutover in a short, reversible release: stop writes to legacy HR, snapshot data and mapping receipt, route reads/writes to new module, verify deep integrity and accounting, keep legacy tables read-only through the agreed retention window.
8. Retire legacy tables only in a separate, explicitly approved later release after restore and rollback have been demonstrated. No implicit cleanup in plugin activation/uninstall.

## 8. Delivery phases and working artifacts

- **Phase 0 — specification freeze:** this plan, requirement/route/permission/test traceability, RACI, approved SOP inventory and legal-review register.
- **Phase 1 — baseline/source audit:** exact package manifest/hash, route/schema/service map, sanitized data-health, host capability and source-to-table dependencies.
- **Phase 2 — domain model/API/UI prototypes:** canonical person, state machines, permissions, errors, reporting and responsive click-through review.
- **Phase 3 — employee lifecycle:** workforce plan → vacancy → applicant → selection → contract/onboarding → employee activation → changes → exit.
- **Phase 4 — time/leave/compensation:** shifts, attendance corrections, time rules, leave ledger, compensation components and approvals.
- **Phase 5 — legal/statutory engine and payroll:** effective-dated rules, calculation breakdown, exception queue, maker-checker, payslips and period locks. Legal calculation remains disabled while required sign-offs are unresolved.
- **Phase 6 — payments/remittance/accounting:** confirmed per-person payouts, failure/retry, remittance receipts, balanced GL/PLEDGER, idempotent source references.
- **Phase 7 — individual payees and central AP:** creators/freelancers, classification review, evidence, withholding and settlement.
- **Phase 8 — reporting/resources/performance:** role-scoped dashboards, downloads, SOP/resource versioning and manually sourced social reporting.
- **Phase 9 — exact-package and staging acceptance:** syntax/static/security/dependency checks; isolated WP/MariaDB integration; E2E and negative tests; backup/restore/rollback; exact file parity.
- **Phase 10 — controlled pilot/cutover:** written go/no-go, Owners, HR and Finance sign-off, qualified legal review, rollback operator, monitoring and post-release reconciliation.

Every phase ends in a reviewed PR, tests and documented acceptance. Do not merge scaffold-only code as a product release.

## 9. Required test matrix (minimum)

- **Identity/lifecycle:** deduplication; candidate-to-employee continuity; conflicting identities; duplicate submissions; unauthorized transitions; self-approval; rejected candidate; offer expiry; probation/contract effective boundaries; transfer history.
- **Time/leave:** DST/time-zone not assumed (timezone stored explicitly); shift crosses midnight/month; missing punch; overlap; approved correction; overtime boundary; holiday-calendar version; leave accrual and carry-forward; overlapping requests; negative balance; leaver balance; policy effective-date boundary.
- **Compensation:** effective-dated increment; conflicting components; taxable/pensionable status unresolved; allowance evidence absent; retro change; mid-period joiner/leaver; advances/deductions cannot exceed permitted basis without reviewed rule.
- **Statutory rules/PAYE:** progressive bracket boundaries and each lower/upper edge; zero/negative/huge values; decimal half-cent rounding; leap/month boundaries; rule effective date within period; expired/overlapping/missing rules must block; no unapproved pension-base/tax-base branch; independently signed golden fixtures.
- **Payroll integrity:** frozen input snapshot reproducibility; changed input after snapshot; duplicate generation; concurrent approval; stale version token; failed GL post rollback; unbalanced journal; closed period; duplicate payment; partial/failed/returned payment; retry after timeout; correction/reversal with original run linkage; no historical line edit.
- **Payees/AP:** employee cannot use supplier path; creator and normal service differ; TIN/licence facts; cash plus in-kind; duplicate payee; no invented company; unresolved creator/Article 97 overlap blocks; attachment privacy; payment account ownership; unique payment/ref; source evidence and remittance.
- **Security/privacy:** anonymous download denied; invalid/expired nonce; route capability denial; location row-scope denial; salary redaction; direct document URL denial; audit-reader scope; PII minimized in logs; formula injection in exports; CSRF/XSS/SQLi; access removal.
- **Reports/UI:** every role's permitted field/row scope; all five locations; search and date filters; print/export; empty/error/loading states; keyboard and focus; viewport widths 320/360/390/768/1280/desktop; long labels and error messages; no duplicate or mid-dashboard floating control.
- **Migration/operations:** exact source SHA, ZIP CRC/root/inventory, schema fingerprints, no legacy DDL mutation, row mappings, totals and journal reconciliation, failed migration rollback, backup/restore, post-restore integrity and release SHA parity.

## 10. Definition of done / release gate

An implementation is **not** complete because routes exist or a page renders. A release candidate needs:
- approved SOP/RACI and requirement-to-screen/API/database/permission/audit/test traceability;
- actual replacement code for the full lifecycle and central individual-payee workflow;
- approved legal rule pack and independent written Ethiopian reviewer sign-off for activated calculations;
- all applicable synthetic E2E, boundary, negative, authorization and security tests pass;
- isolated WordPress/PHP 8.3 and 8.4 + supported MariaDB integration tests pass;
- exact final ZIP SHA, archive/content inventory, PHP/JS/CSS gates and source parity;
- migration rehearsal and target reconciliation, backup/restore/rollback receipts;
- test download actually opens as a genuine OOXML DOCX/PDF and unauthorized direct requests fail;
- Owner + HR + Finance acceptance and a named rollback operator;
- no hidden skipped checks counted as passes.

## 11. Known facts and current blockers recorded before implementation

- The downloaded 16.1.95 ZIP is valid, exact SHA-256 and 75 unique files.
- Static DDL defines 49 existing tables and no explicit FK constraints; application orphan/reconciliation checks therefore matter.
- The current HR REST controller has 13 route registration call sites representing 15 method/path operations. Existing maker-checker behavior is a useful baseline but not a complete HR lifecycle.
- Existing payroll “PAID” lacks external per-person payout confirmation/partial failure recovery and separate statutory-remittance evidence.
- The current PAYE base deducts employee pension before PAYE. This stays blocked pending written legal interpretation; do not change posted payroll or pretend the rule is approved.
- A previous live Health screen reported nine employee records with missing compensation and zero management-prepared records accepted; each must be resolved from approved original records, not filled by code or sample data.
- The separate 16.1.98 scaffold-only resource-download branch is not the full replacement and must not be merged/released as one.

**Current disposition: build from the new target domain on a dedicated branch; preserve legacy data; legal/statutory activation and production cutover remain gated.**
