# Stockland Ethiopia ERP — HR/Payroll Replacement Plan v0.6

This repository plan corresponds to the working implementation plan and requirements trace maintained for this engineering work. It is design/engineering planning evidence, not a production release.

## Authority and baseline

The current authority is Notion HR Design Reset v0.5 and Required Scope — Full HR & Payroll Lifecycle and Ethiopian Statutory Rules. These supersede fixed table-count proposals including the earlier 27-table draft. Do not freeze physical table count before the source-to-query/route/report dependency inventory, logical ERD, shared-service audit, access/privacy model, and migration/rollback design are reviewed.

Locked 16.1.95 baseline ZIP SHA-256: 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625. It contains 75 files and a 49-table source contract: 29 Core/Operations + 16 Accounting/Procurement + four legacy HR/payroll tables. Static DDL has no explicit foreign-key constraints. Legacy tables are not target authorities but remain immutable transition sources until a separate approved cutover/retirement gate passes.

## Product scope

1. Workforce plans, departments, positions, grades/pay bands, approved requisitions and budgets.
2. Candidate/person identity, applications, interview evidence, human selection, offers, contract versions and onboarding.
3. Employee history, probation, transfers, promotions, leave-of-absence/return, reporting lines and private document references.
4. Rosters, attendance/time entries, exceptions, corrected values with approval, overtime/rest/holiday segments and cutoff handling.
5. Leave policies, eligibility, accrual and movement balances, request/approval/cancellation and leaver balance.
6. Effective-dated compensation/pay-item catalog: salary, allowances, benefits, overtime, commission/bonus, reimbursements, advances/loans and deductions.
7. Versioned statutory rule registry keyed to official instrument/clause/source hash/publication and provision-specific effective dates; draft calculator preview separated from final approval; unapproved/conflicting rules fail closed.
8. Frozen payroll run, input snapshot, itemized line calculation trace, PAYE/pension/other deductions/net/employer cost, variance queue, independent Owner approval and immutable finalized history.
9. Payslips, payout batch and per-person evidence, partial/failed/retry idempotency, statutory remittance receipts and reconciliation.
10. Balanced GL/PLEDGER integration through the approved accounting service; liability clearing at payout, period locking, reversals/corrections, deep reconciliation and audit.
11. Employee changes, exit/clearance, last-worked/time/leave snapshot, final-pay items, approved settlement, payout evidence, access removal and preserved history.
12. Separate creators/influencers/freelancers/service-provider payee classification/AP workflow; central Finance queue, deliverable evidence, cash plus in-kind valuation and withholding review.
13. Management reports and secure resource center; source-backed HR/Finance filters, timestamped manual social metrics, private versioned documents, accessible responsive UI and audited downloads.

## Engineering controls

Server-enforced capabilities and record/location/field scope, maker-checker, versioned state transitions, concurrency/locking/idempotency, exact decimal arithmetic, change lineage, privacy minimization, secure document storage, balanced ledgers, orphan checks, audit-reader access, versioned/resumable migration and rollback. UI hiding never substitutes for server authorization. A PAID flag without external payout evidence is not settlement.

## Source audit status

See HR_PAYROLL_LOGICAL_MODEL_v0.6.md, the requirements trace and exact baseline audit scripts. Source audit checks checksum/root/ZIP, 49-key-to-DDL match, legacy consumer scan and regression tests. Recorded baseline evidence: PHP lint 48/48, assets/app.js and assets/pwa.js JS syntax pass, 116 REST registration calls, and lexical source consumer counts 8/5/4/4 for the four legacy HR tables. These are baseline source checks, not new-product tests.

## Twelve Stockland SOPs

1. Workforce plan, position and vacancy approval.
2. Candidate privacy, screening, interview and human selection.
3. Employment terms, contract, onboarding and lifecycle changes.
4. Shift, attendance, absence, overtime, rest and holiday exceptions.
5. Leave, entitlement, accrual, carry-forward and final balance.
6. Grade, increment, promotion, allowances and compensation evidence.
7. Monthly payroll preparation, PAYE base, variance review and approval.
8. Pension, withholding, declaration/remittance and reconciliation.
9. Payroll payout batches, partial/failed payments, retries and GL close.
10. Separation, clearance and final settlement.
11. Personnel data access, document security, retention and audit privacy.
12. Legal/company policy change control and rule-version regression.

## Implementation gates

A. Finish query/consumer audit and host capability evidence; finalize logical ERD and approved physical schema after reuse decisions.  
B. Build canonical person, organization/position, recruitment and employment workflows in a feature branch with migration disabled until approved.  
C. Build onboarding/document metadata and private document service; verify anonymous/wrong-role/wrong-person denial.  
D. Build time/attendance/leave movements, approvals and cutoff snapshots.  
E. Build compensation/pay-item/rule registry and synthetic preview; legal-review rules remain non-posting.  
F. Build payroll run snapshot, line calculation trace, independent approval and immutable period.  
G. Build payout/remittance outcomes and GL/PLEDGER accrual/clearing with idempotency and rollback.  
H. Build employee changes, exit/clearance/final settlement and central nonemployee AP/payee path.  
I. Build reports/resources, responsive UI, role/location tests, migration parity and source trace.  
J. Run isolated WordPress/MariaDB E2E; two parallel payroll periods; archive/hash identity; backup restore/rollback; deployed parity; Owner/HR/Finance and qualified Ethiopian legal/payroll approvals. Only then consider cutover and separately authorized legacy retirement.

## Legal gate

Starting sources: Labour Proclamation 1156/2019, Income Tax Amendment Proclamation 1395/2025/Gazette No.64, current pension materials/directives including 1085/2025 and 1087/2025, and Personal Data Protection Proclamation 1321/2024. Article-level treatment, effective dates, PAYE base/pension interaction, allowance treatment, pensionable pay, overtime, leave and final settlement remain subject to qualified Ethiopian legal/payroll review. Do not activate a rule from historical examples alone.

## Release decision

NO-GO to replace the live module or retire old tables at this stage. The audit and design artifacts do not claim a full runtime implementation, live-site access, legal certification or production acceptance. No live database, employee pay or payroll history may be mutated by this workstream.
