# Stockland HR/Payroll Replacement — Semantic Dependency Trace v0.1

**Authority:** Notion HR Design Reset v0.5 and Required Scope — Full HR & Payroll Lifecycle and Ethiopian Statutory Rules.  
**Baseline:** 16.1.95 installer SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.  
**Status:** reproducible static source trace and design input; not a live DB audit, physical-schema approval, migration, or release certification.

## Governing decision

This is the full HR/payroll replacement—not an extension treating the four legacy tables as target authorities and not the 16.1.97 resource-only candidate. Keep the four legacy tables read-only during migration rehearsal until reconciliation, backup/restore, rollback, cutover approval and later cleanup approval pass. The former fixed 27-table and 14–16-table proposals are superseded. No target physical table count is frozen here.

## Exact source results

| Test | Result |
|---|---|
| SHA-256 / size | Exact lock: `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`, 1,102,405 bytes |
| ZIP structure | CRC passes, 75 unique members, one stockland-erp-app root |
| PHP | 48 packaged files |
| Static schema contract | 29 core/operations + 16 accounting/procurement + 4 legacy HR/payroll = 49 |
| Legacy HR DDL | One source declaration per table key; zero explicit foreign-key constraints |
| Direct legacy table helper calls | 49 total: employees 14; employee_compensation 5; payroll_periods 17; payroll_lines 13 |
| HR REST inventory | 13 register_rest_route call sites and 15 permission_callback entries |

The reproducible scanner generates call-site CSV, cross-module reference CSV and a JSON receipt tied to the baseline SHA. Its tests verify the exact hash, counts, declarations, output completeness, and fail-closed behavior for a modified ZIP.

**Limit:** these are source-navigation hints, not AST, runtime data-flow, live DB results, or proof that permission callbacks enforce the right scope. Full-method/manual review remains required.

## Legacy data consumers and disposition

| Legacy table | Source method groups | Required replacement |
|---|---|---|
| employees | employee list/bootstrap, create/decision/update, compensation create, payroll generation, System Health | Canonical person identity + employment relationship/assignment, legacy-ID crosswalk, human recruitment/contract/onboarding prerequisites, location/field scope |
| employee_compensation | employee list, compensation create, effective-dated segments, notes, health | Effective-dated approved compensation assignments with itemized components; unresolved legal classification remains blocked; no guessed salary |
| payroll_periods | create, totals, generate, draft edits/classification, detail, submit, Owner approval, payment | Frozen payroll run, versioned inputs/rules, approval history and journals; immutable final state and controlled adjustments |
| payroll_lines | list/totals/generate, draft edits/classification, detail, submit/approval reconciliation | Per-employment payroll line + itemized detail, exact legacy mapping, immutable posted results and GL reconciliation |

The CSV records package-relative file/line, enclosing method guess, lexical operation hints, source excerpt and manual review flag. Review each complete method, SQL/query args, permission, state/lock/transaction, audit/event, report/health and accounting side effects.

## Cross-module contracts

- Schema Health/Admin checks include the 49-table baseline and must be updated coherently in a reviewed candidate with versioned migration metadata. Normal activation/update must not perform destructive legacy retirement.
- REST/UI/report/bootstrap/export/Health callers require compatibility during migration. Replacing one REST controller is not enough.
- Use the current Stockland identity/role system with additional server-side person/candidate/document/period/field/location checks; test guessed IDs, wrong role/location, stale state and self-approval.
- The generic workflow event helper is not a full transaction/state/authorization service. Lock and verify state/version/actor, mutate and append the event in a coherent transaction.
- The audit hash chain is not access control or retention. Minimize PII copied to generic before/after payloads; store evidence references and hashes, not document bodies or bank/identity fields.
- Reuse the approved balanced/idempotent GL posting service only after concurrency, replay, closed-period, mismatch, inactive-account, audit-failure and rollback tests. Never write journals directly from HR handlers.
- Legacy payroll payment records method/actor/time and posts a liability-clearing journal, then marks PAID; it does not prove bank settlement. New model needs batches, recipient instructions/results, external reference, partial/failed/returned retry, statutory remittance evidence and reconciliation.
- An individual creator/freelancer is not automatically an employee or registered supplier. Build central Finance/AP requests with engagement, deliverable, cash plus in-kind value, tax facts and one reviewed classification.
- Private contract, identity, bank, tax, payslip and exit records require a private-vault adapter and server-side download controls. Public media URLs are not acceptable.
- PAYE tax base, pensionable pay, transport, creator classification/threshold, overtime, leave and severance rules remain blocked pending exact current official provision/effective-date and independent qualified Ethiopian reviewer sign-off.

## Physical-model options requiring review—not a fixed table count

Decide normalized storage boundaries for: canonical person and candidate privacy; workforce plan/budget/position/requisition; applications/interviews/human decisions; employment relationship/assignment/contract versions/onboarding/events; private document metadata; calendars/shifts/time events/corrections; leave policy and append-only balance ledger; compensation assignments/items; legal rule sets/components; frozen payroll input snapshots/runs/lines/items/adjustments; payout batches/instructions/attempts; statutory obligations/remittance receipts; separation/clearance/final settlement; individual payees/creator-service engagement/payment requests; and restricted reporting/audit/social snapshots.

For each boundary record ownership, cardinality, uniqueness/concurrency, privacy/retention, effective-dated vs append-only history, transaction/idempotency, queries/indexes, shared-service reuse evidence, migration mapping, rollback and test IDs. Do not collapse queryable payroll facts into an undifferentiated JSON bag.

## Migration gate

1. Verify installed-source parity and schema fingerprint on an authorized restored clone; ZIP identity is not live parity.
2. Run the WP-CLI read-only diagnostic only on the disposable restored WordPress/MariaDB clone. It has not been run against production.
3. Finish direct/dynamic SQL, route, UI, Health, report, role and accounting dependency review.
4. Review and approve logical-to-physical boundaries and migration mapping before migrations/runtime.
5. Copy-only rehearsal: map IDs, historical compensation, periods/lines, approvals/audit and journals; report aggregate counts/totals, duplicates/orphans, unresolved rows and reconciliation differences.
6. Prove restore/rollback before switching authority; keep legacy tables read-only through the approved transition window.

## Next gate

Review this trace, physical boundary rationale, RACI/field privacy and migration map. Then build the first persisted vertical slice on an isolated branch, with schema versioning and runtime activation disabled by default: workforce request → approval → candidate/application → human selection → pending hire → contract/onboarding prerequisites. Require audit/state events, transaction rollback, idempotency and role/location denial tests before attendance/leave and payroll.

**Production disposition: NO-GO** for HR replacement, statutory activation, migration or legacy-table retirement. Package/source checks are not live WordPress/MariaDB acceptance, legal approval, backup/restore, source parity or Owner/HR/Finance sign-off.
