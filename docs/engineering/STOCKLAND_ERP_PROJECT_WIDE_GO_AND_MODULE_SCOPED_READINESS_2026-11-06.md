# Stockland Ethiopia ERP — Project-Wide Engineering GO and Module-Scoped Readiness

**Owner direction:** 6 November 2026  
**Repository:** aistockland-web/Erp-version-16.1.89  
**Applies to:** the entire Stockland ERP, all business modules, the cumulative WordPress application and its release governance  
**Authority:** Owner's 4 November Protected Master decision, 5 November incremental-target-commit directive, and this explicit clarification

## 1. Ratified project status

**PROJECT-WIDE ENGINEERING: GO.**

Proceed with the full end-to-end Stockland ERP implementation, correction, integration, optimization and production completion. The work is not limited to HR/Payroll or any other single section. Do not stop the entire project because one module, one candidate archive or one acceptance item needs further work.

**EXISTING ERP OPERATIONS: CONTINUE.**

An issue specific to a candidate installer or an individual business function does not place every currently released ERP function into a shutdown state. Preserve existing authorized use of working functions. No global operational lock, blanket NO-GO banner or system-wide disablement may be introduced solely because a release artifact, payroll classification or schema-approval item needs review.

**FINAL PRODUCT: ONE CUMULATIVE WORDPRESS ERP.**

The objective remains one integrated Stockland Ethiopia ERP, one WordPress plugin, one final installable ZIP, one canonical source commit and one independently recomputed SHA-256. No partial section-only product, disconnected add-on release, artificial version proliferation or replacement of functioning modules.

## 2. Mandatory status semantics

Do not use a single global status to collapse different kinds of evidence. Every health, dashboard, API and release report must distinguish:

| Status scope | Correct meaning | Effect |
|---|---|---|
| Project engineering | **GO** | Full ERP implementation and corrective work continue. |
| Existing operational module | READY / ACTION REQUIRED / NOT RUN | Only the named module and dependent action are affected. |
| Employee or payroll record | READY / REVIEW REQUIRED / BLOCKED | Only the affected employee, calculation, period or approval is held. |
| Schema/migration | APPROVAL REQUIRED / READY TO MIGRATE | Controls schema-changing actions; does not disable unrelated existing operations. |
| Candidate installer | PACKAGE REVIEW REQUIRED / VERIFIED | Applies only to the exact ZIP SHA under review. |
| Final cumulative release | RELEASE EVIDENCE COMPLETE / RELEASE EVIDENCE INCOMPLETE | Applies to publication/installation of the exact final package; not to the whole ERP's continuing operation. |

A historical candidate's package-specific rejection must not be copied into a current ERP-wide health label. Historical audit records remain intact and are explicitly scoped to their original artifact hash/date.

## 3. Module-isolated gate behavior

Implement and test the following separation in the actual application. A gate is allowed to block only the action whose data or authorization depends on the failed control.

- **Payroll compensation classification:** hold payroll generation/approval for affected active employees and the dependent payroll period until HR/Owner evidence is recorded. Do not disable employee records, recruitment, attendance, leave, POS, inventory, warehouse, finance, reports or unrelated HR activities.
- **Ethiopian PAYE/pension rule approval:** hold the affected statutory calculation or payroll finalization. Do not disable general ERP access or non-payroll accounting/operations.
- **Schema contract approval:** block unapproved schema creation/migration and release activation that depends on it. Do not mutate or freeze existing business tables, and do not turn a migration approval gate into an operational shutdown of existing functions.
- **Release approval:** control publication/installation of the exact candidate package. It is not a runtime switch for the live ERP and must not suppress normal authorized use of existing released operations.
- **Private HR document control:** deny an unauthorized document request; do not disable other employee self-service or HR functions.
- **In-kind valuation mismatch:** hold the specific in-kind payee request/payment until quantity multiplied by independently approved value reconciles. Do not block an unrelated cash path or other ERP modules.
- **Deep integrity scan not run:** display NOT RUN and schedule/execute the scan. Do not translate missing evidence into a false relational FAIL or an ERP-wide stop.
- **One module's data failure:** isolate the affected record/transaction and dependent workflow. Preserve other valid records and functions.

These are not shortcuts to green health checks. Failed controls remain visible and enforceable at the correct layer. Never change a health predicate, force live_ready=true, suppress an exception or invent approval evidence merely to present a successful status.

## 4. Full ERP scope remains mandatory

The completion scope is the complete existing ERP, not a narrow HR release:

1. Login, roles, capabilities, Owner authority, branch/location scope, maker-checker and audit history.
2. Command Center, dashboard, workspace navigation and responsive application shell.
3. Product Master, SKU/variant identity, brand/size/colour, Excel import and product-data integrity.
4. Central Warehouse, branch stock, receiving, requisitions, reservations, transfers, dispatch, returns, damage, adjustments and physical counts.
5. POS/cart, discounts, tax, payment, receipts, sales, daily close and transaction replay/rollback.
6. Wholesale orders, reservations, availability checks, approval and fulfilment.
7. Procurement, suppliers, purchase orders, receiving, accounts payable and supplier settlement.
8. Customers, accounts receivable, expenses, cash/bank movements, GL/PLEDGER and reconciliation.
9. Reports, analytics, export, printing and per-location scopes.
10. Full HR lifecycle: workforce planning, recruitment/selection, employee/contracts/probation/onboarding, attendance/shifts, leave, grading/increments/promotions, compensation, payroll, review/approval/payment, payouts, statutory remittances, offboarding, exit clearance and final settlement.
11. HR Process Center: approved Amharic-first SOPs, job descriptions, employment resources, secure authenticated downloads, edit/version/approval where authorized.
12. Notifications, operational health, system diagnostics, imports, PWA/device support and security controls.

The HR design must follow Notion HR Design Reset v0.5. Do not reintroduce superseded fixed table-count plans. Preserve the four legacy HR/payroll tables as migration sources until dependency mapping, reconciliation, backup/restore and rollback are proven and the final data model is approved.

## 5. Protected source lineage and data invariants

- Preserve the 16.1.95 protected master ZIP SHA-256 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625 as the exact rollback reference.
- Use merged PR #39 / its exact 16.2.9 HR/Payroll artifact as a selective implementation source only. Do not copy its unrelated POS, sales, shared CSS, database or legacy-table alterations wholesale.
- Keep every non-HR protected service compatible: existing roles, permissions, locations, stock ledger, reservation model, POS, warehouse, reports, accounting, AP/AR, GL/PLEDGER, notifications and immutable audit history.
- Do not rewrite historical stock movements, sales, posted payroll, GL journals or audit events.
- New schema must be justified by the source-mapped ERD and actual dependency/data-health evidence; no predetermined 14–16, 37 or 47 HR-table count is approved by this directive.
- Every migration requires a restorable pre-migration backup, row/reference reconciliation, idempotent retry and tested rollback on an isolated copy before production execution.
- No synthetic employee, salary, sale, stock movement, payment or payroll event may be inserted into the live production ledger. Synthetic fixtures belong only in isolated tests.
- Never invent Ethiopian PAYE, pension, allowance exemption or final-settlement rules. Activate only rules tied to verified official text/effective dates and qualified independent review.

## 6. Acceptance and release evidence

The one final installer is evaluated against its exact SHA-256. Acceptance must verify, at minimum:

- PHP 8.3/8.4 syntax and pinned PHPCS/WPCS; JavaScript/Node; CSS/PostCSS/module-build parity; JSON and ZIP/file inventory.
- Static security, dependency/license/secret checks and authorization review.
- WordPress clean install, upgrade from protected master, recovery and idempotent activation on a host representative of the planned WordPress/PHP/MySQL/MariaDB environment.
- Every UI action through REST, capability/role and branch/location enforcement, database writes, business side effects, audit events, errors and retries.
- Genuine end-to-end workflows across all modules listed above, including positive and negative authorization paths.
- Quantity/ETB reconciliation for stock, POS, wholesale, procurement, AP/AR, GL/PLEDGER and payroll.
- Two complete payroll periods, employee-level payout results, failed-payment recovery, remittance evidence and final settlement; qualified Ethiopian review of rules.
- Private HR document encryption at rest, key management, role/location/object-scoped downloads and encrypted backup/restore.
- Dedicated deep-integrity evidence, backup checksums, restore into an isolated environment and rollback rehearsal.
- Owner, HR, Finance and relevant process-owner acceptance tied to the same final package hash.

A test that was not run must be marked NOT RUN. A failing module test remains visible for the affected module. A green CI job does not by itself certify live WordPress/MariaDB operation.

## 7. Repository correction required

This directive supersedes the project-wide reading of the 3 November historical finalization audit. That audit assessed specific 16.2.9 and 16.3.0 archive hashes; its package-specific findings remain valid historical evidence but must not be interpreted as a global project or operational NO-GO.

Update release summaries, audit indexes, dashboards and health-status descriptions to apply the scope rules above. Do not erase historical test results or falsify an old audit. Remove only the incorrect project-wide implication; keep every artifact-specific finding and every valid safeguard.

## 8. Immediate implementation targets

1. Review the actual full-system status model and remove any code path that turns a payroll/schema/release-only gate into a global ERP outage or blanket readiness banner.
2. Add regression assertions proving that a payroll-only classification failure does not disable POS, inventory, warehouse, procurement, finance, reports, or unrelated HR actions; prove that the affected payroll action remains safely blocked.
3. Verify the schema_contract_approved and release_approval gates protect only schema/release mutation and do not disable existing runtime functions.
4. Reconcile and complete the HR lifecycle against HR Design Reset v0.5 while preserving the protected 49-table baseline and all existing historical data.
5. Carry every remaining audit finding through a committed fix and exact-head retest. Deliver one cumulative installer and one release receipt only after all applicable evidence is complete.

**This directive is an engineering GO for the whole Stockland ERP. It does not certify an untested installer or authorize a destructive production migration. It expressly prohibits using an isolated module failure as a reason to label or disable the entire ERP.**


## 9. Implemented source correction — 6 November 2026

The first module-scoped health-status correction is merged into canonical `main`.

- **Merge commit:** `30752e6ece6345fcbbf2d15ef3e348cf87beeb85`.
- **Review:** [PR #56 — Fix health status module scope](https://github.com/aistockland-web/Erp-version-16.1.89/pull/56), merged.
- **Builder:** `ci/build_full_hr_payroll_16_2_0.py` now adds `classifyERPHealthScope()` to the generated application UI.
- **Exact checks recognized as scoped review actions:** `payroll_compensation_classification`, `schema_contract_approved`, `release_approval`.
- For any combination made up solely of those checks, the generated UI says **OPERATIONAL • TARGETED REVIEW REQUIRED**, names the affected actions, and states that existing authorized POS, inventory, warehouse, finance and other ERP workflows remain available.
- Payroll-only keeps its existing payroll-specific warning.
- Unknown/deep-integrity/ledger failures, mixed review-plus-integrity failures, setup mode and quick-only health are deliberately not disguised as targeted approval reviews.
- **Tests:** new Node test `ci/qa/test_health_scope.js` exercises 10 state assertions. Exact-package QA also asserts the targeted label and unaffected-module wording. The exact-package workflow runs the Node test against the generated plugin.
- **Exact-head CI:** 16.2.9 builder/package workflow [#42120740245](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42120740245) — SUCCESS on PHP 8.3 and PHP 8.4; protected 16.1.95 baseline workflow [#42120740236](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42120740236) — SUCCESS; enterprise security workflow [#42120740057](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42120740057) — SUCCESS.
- **Local source replay:** the same transformation was applied to extracted 16.1.95 and 16.2.9 source trees; each passed 10/10 health-scope assertions and JavaScript syntax checking.

This merge changed the build source, QA and workflow only. It did **not** change a production database, schema, payroll value, stock/sale/ledger row, live WordPress installation or deployed installer. It does not itself install the fix on the reported 16.2.13 site; the exact 16.2.13 source/hash has not been independently matched. The correction is now part of the canonical build source for the cumulative product.
