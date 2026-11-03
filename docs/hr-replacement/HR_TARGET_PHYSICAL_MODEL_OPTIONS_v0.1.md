# HR / Payroll Replacement — Physical Model Boundary Review v0.1

**DRAFT FOR DESIGN REVIEW.** No executable DDL and no fixed table count. The current Notion reset supersedes older fixed-count proposals.

## Logical aggregates and boundaries

| Aggregate | Candidate boundary | Existing surface to assess | Key invariant |
|---|---|---|---|
| Person identity | Canonical person + alias/crosswalk if justified | WordPress/Stockland users | Candidate-to-employee continuity; person is not a login or job |
| Org / job context | Employer, org unit, position/grade, cost centre/payroll group only if needed | stock_locations and user-location scope | A physical store is not automatically employer or cost centre |
| Workforce plan | Versioned plan, budget/headcount lines | Finance approval service if proven fit | Budget/headcount approval before requisition |
| Requisition | Vacancy request, approval and outcome | Shared workflow only after transaction review | Unique requisition, versioned approvals |
| Recruitment | Application per person/requisition + interview/assessment decision history | User IDs as actors only | Human selection, restricted applicant access, retention |
| Employment | Employment relationship, effective-dated assignment/status and lifecycle history | Legacy employees as migration source | A person can have multiple historical relationships/assignments |
| Contract | Immutable signed contract versions and effective dates | Private vault adapter TBD | Correct signed version gates activation; template updates never overwrite |
| Onboarding | Checklist tasks and evidence references | Notification/workflow only if suitable | Required prerequisites before activation |
| Document metadata | Private vault reference, SHA-256, type/version, expiry, access/retention | Media only after direct-URL denial proof | No public URL for personnel documents |
| Calendar/shift | Effective-dated rules and employee assignment | Business calendar only if semantically fit | Retail business-day ≠ employment schedule |
| Time/attendance | Append-only events + corrections that supersede originals | No full source proven | Original retained; correction reason/evidence/approval |
| Leave | Versioned policy, request decision and append-only accrual/usage/carry-forward ledger | No existing complete leave module proven | Balance is derived from the approved ledger |
| Compensation | Effective-dated assignment and itemized pay components | Legacy employee_compensation as migration source | No guessed pay; component provenance and approval |
| Legal rules | Versioned rule sets/components, source hash/article, effective dates, review and tests | Do not reuse constants as source law | Unknown/stale rules block final payroll |
| Payroll | Frozen input snapshot/run, per-employment line, itemized detail and exception/variance | Legacy periods/lines as migration sources | Immutable finalized history; reproducible calculation |
| Approvals/audit | Exact-run-hash review and Owner decision | Workflow/audit only after contract review | Event alone is not authorization/transaction lock |
| Corrections | Linked arrears/adjustment/reversal runs | GL reversal path only after review | No silent edits or repeated posting |
| Disbursement | Batch, recipient instruction, attempts/results/external reference | Existing payments/journals if semantic fit passes | Posting is not settlement; retries idempotent |
| Remittance | Obligation/return/receipt and reconciliation | Existing accounting if proven | PAYE/pension remittance separate from net pay |
| Exit | Separation, hold, clearance, frozen inputs, final settlement items and approvals | Audit/GL after review | Applicable legal/contract basis; controlled access removal |
| Individual payee | Natural-person payee, creator/service engagement, request and withholding review | Supplier/customer/AP only if fit proven | Do not fabricate employee/supplier; central Finance/AP queue |
| Reporting | Source-backed restricted reporting and dated manual social snapshots | Existing report patterns | Source, as-of timestamp and stale data shown explicitly |

## Evidence needed before turning an aggregate into a physical table

For each boundary document owner/source of truth; cardinality/keys; privacy/retention; effective-dated vs append-only history; transaction/idempotency; query/index needs; reuse/extend/replace disposition; legacy mapping; rollback/test IDs; and a reason to combine or split. Avoid catch-all JSON that hides queryable financial facts.

## Boundary decisions

- **Identity:** decide whether a shared people master covers candidates and employees while permissions differ; keep WP user ID separate and do not automatically grant access when a candidate is selected.
- **Employment:** separate person from employment relationship, assignment history, contract versions and compensation assignment.
- **Time and leave:** retain originals/corrections; derive balance from approved policy-versioned movements.
- **Payroll:** pin every input and legal-rule version; store explainable line items; separate run posting, recipient settlement and remittance.
- **Individual payees:** separate relationship, engagement type and tax result; use central Finance/AP; do not make branch-specific payment desks.
- **Shared services:** use Stockland_DB with a reviewed schema/migration allowlist; extend the current authority model for fine-grained scope; reuse balanced idempotent GL posting; reuse workflow/audit only after lock, transaction, privacy and reader permissions pass tests.

## Candidate migration contract (not executable)

- Versioned/idempotent migration with pre/postconditions and migration ledger.
- No destructive operations on legacy tables in ordinary activation/update.
- Copy-only rehearsal, deterministic crosswalks and sanitized before/after summaries; unresolved rows stop as exceptions.
- Reconcile person/compensation/payroll/approval/journal/payment/audit links.
- Prove backup restore and rollback on a disposable clone.
- Retain legacy tables read-only through the approved transition window; cleanup requires separate approval.

**Gate:** review these boundaries before migration/runtime controllers. This design review is not statutory activation or production authorization.
