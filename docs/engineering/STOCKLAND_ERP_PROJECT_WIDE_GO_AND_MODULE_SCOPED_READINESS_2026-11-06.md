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


## 10. Implemented HR/payroll icon correction — 6 November 2026

The HR/payroll SVG-overflow correction is merged into canonical `main`:

- **PR #58:** [Remove HR/payroll SVG overflow and use accessible text-only cards](https://github.com/aistockland-web/Erp-version-16.1.89/pull/58) — merged.
- **Merge commit:** `476b0b6a819f7fbb63f32282791802b13e14d917`.
- The cumulative HR/payroll builder removes `hrlcSectionIcon()`, `hrlcSocialIcon()`, their wrappers, and HR lifecycle SVG icon CSS/isolation rules.
- HR section accordions retain native `details/summary` behavior, stable IDs, ARIA controls/expanded state, labels, descriptions and action content.
- Social platform cards retain all official platform names/URLs, descriptive content and timestamped manual-export disclosure as text-only cards.
- Global Stockland shell icons and the dashboard chart remain intact; the change is scoped only to HR/payroll.
- Exact-package Node regression verifies rendered HR headings/social cards and CSS selectors. The package-level QA no longer requires the removed icons.

**Exact package evidence from GitHub Actions run 42124893425:**
- Outer CI evidence artifact SHA-256: `710ca92332aa8e420dae23b742762ace180d5e62f76800013ec0dde709503c53`.
- Inner generated 16.2.9 ZIP SHA-256: `f360294feb4aa43f13d43d12a22e55867eda26e0502946dd0d494947277604ca`.
- 88 unique runtime files; ZIP CRC passed.
- PHP 8.3 and PHP 8.4 exact-package build and acceptance jobs passed.
- Protected master validation, WordPress update, CodeQL JavaScript/Actions, secret scanning, dependency/license inventory, dependency review, ShellCheck and actionlint passed.
- Local recheck of the downloaded exact ZIP: 55/55 PHP syntax, all packaged JavaScript syntax, 3/3 JSON manifests, PostCSS for app.css/app.min.css/Bootstrap/HR CSS, and exact 12-module CSS source parity passed. Local text-only HR UI tests passed for accessible accordion output, preserved actions, all four social links, manual-export notice, and preserved non-HR shell/chart assets.

**Scope boundary:** the generated ZIP is still the 16.2.9 selective HR/payroll implementation candidate built from the protected 16.1.95 baseline. Its build receipt continues to state `schema_contract_approved=false`, `production_ready=false`, `new_tables=37`, `production_database_touched=false`, and `wordpress_activated=false`. This PR did not change that schema or approve a release. It did not deploy to the reported 16.2.13 live site or modify production data. The HR icon correction is committed in the canonical builder; exact 16.2.13 installed-source parity and full authenticated WordPress/MariaDB acceptance remain separate work.


## 11. Raw HR overlay source cleanup — 6 November 2026

The follow-up source-hygiene change is merged into canonical `main`:

- **PR #59:** [Remove HR/payroll SVG icons from source overlay and generated ERP](https://github.com/aistockland-web/Erp-version-16.1.89/pull/59) — merged.
- **Merge commit:** `f60b5a48b51c203cb7627e18b74621e98757298e`.
- Raw `ci/hr_payroll_overlay_16_2_0/hr-lifecycle-ui.js` now has no `hrlcSectionIcon()`, no `hrlcSocialIcon()`, no SVG markup, and no old HR icon wrappers. Accessible text-only section summaries and social cards remain.
- Raw `ci/hr_payroll_overlay_16_2_0/hr-lifecycle-ui.css` has no SVG rules, old icon selectors, or page-level SVG isolation overrides. Form/table/accordion/responsive and social-card layout remains.
- The builder is idempotent against already-clean overlay inputs.
- The exact-package Node regression now checks both the raw overlay sources and the generated installer; it also verifies that global ERP shell/chart SVG remains intact.
- **Exact-head CI run:** [42125671298](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42125671298) — PHP 8.3 and PHP 8.4 exact-package builds passed. The text-only UI regression, health scope 10/10, package acceptance, PHP/JS syntax, CSS/PostCSS/module parity, audit chain, payroll calculator fixtures, PHPCS/WPCS source-aware gate, Semgrep (55/55 PHP scanned; zero ERROR/CRITICAL findings), exact receipt/hash, and package integrity all passed.
- **Other checks:** WordPress-update workflow [42125671229](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42125671229), CodeQL/secret/dependency workflows [42125671384](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42125671384), and ShellCheck/actionlint [42125671363](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42125671363) passed.

The tested installer generated from this selective HR source remains the 16.2.9 candidate built from protected 16.1.95; its receipt still declares the 37-table schema expansion unapproved and `production_ready=false`. This merge cleans the source and regression coverage. It does not approve that schema, create the final cumulative product, deploy to the reported 16.2.13 live installation, or change production data.


### Local verification of the same CI-generated package

The PHP 8.3 GitHub Actions artifact was downloaded to the local machine and independently checked:

- Outer CI evidence bundle SHA-256: `1ca58f61649674f012c280b488af0a37c175fbd8b9363bde434a0550407201d3`.
- Inner installer SHA-256: `f360294feb4aa43f13d43d12a22e55867eda26e0502946dd0d494947277604ca`.
- ZIP CRC/integrity: PASS; 88 unique runtime files.
- PHP syntax: 55/55 PASS on local PHP 8.4.24.
- JavaScript syntax: `assets/app.js` and `assets/pwa.js` PASS on Node 22.16.0.
- JSON: 3/3 manifests parse.
- PostCSS: `app.css`, `app.min.css`, Bootstrap and HR lifecycle CSS parse.
- CSS assembly: all 12 ordered source modules reproduce `app.css` exactly.
- HR UI: no HR-specific SVG functions, markup or CSS selectors; text-only HR workflow and social cards are present.
- ERP shell/chart: global navigation icon renderer and dashboard sales chart remain present.
- Module-scoped health classifier: payroll-only, combined payroll/schema/release review, governance-only, and unrelated ledger/deep-integrity failures were exercised locally; scoped review stays isolated and genuine integrity failures remain visible.

The available Chromium headless browser did not complete a viewport render in this environment; no local visual/browser pass is claimed. GitHub Actions exact-package tests did exercise the generated text-only section/social rendering and raw overlay sources on PHP 8.3 and PHP 8.4.

**No live WordPress activation, MariaDB migration, employee/payroll update, inventory adjustment, sale, ledger posting or deployment was performed.** The generated candidate's own receipt still records `production_ready=false`, `schema_contract_approved=false`, `new_tables=37`, and `production_database_touched=false`. These are artifact/schema acceptance facts only; they do not change the project-wide Engineering GO or disable existing authorized ERP operations.
