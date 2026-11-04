# Stockland Ethiopia ERP — Final Consolidation Evidence and Owner Instruction

**Evidence date:** 4 November 2026  
**Decision authority:** Stockland Ethiopia Project Owner  
**Repository:** `aistockland-web/Erp-version-16.1.89`  
**Purpose:** final, cumulative engineering consolidation toward one integrated WordPress ERP installer—not a new series of partial releases.

## 1. Owner instruction to engineering

The Owner’s approved business plan and decisions across the last two ERP versions and the intended final plugin remain controlling. The engineering team has authority to choose implementation details, tools, branches, CI, local environments, source-reconciliation methods and technical corrections across chats, repositories and machines, provided that the agreed end-to-end scope is preserved.

The product outcome is **one complete, integrated, one-click WordPress plugin installer** for Stockland Ethiopia. It must contain the agreed operational ERP, POS and wholesale workflows; Product Master and SKU variants; branch and Central Warehouse operations; stock receiving, requisitions, reservations, transfers, returns, damages, adjustments, physical counts and daily close; reporting; procurement, suppliers/customers, AP/AR, expenses, GL/PLEDGER and finance controls; the complete HR and payroll lifecycle; individual payee/creator/freelancer workflows; private HR resources; SOP/resource delivery; and the approved responsive SaaS-style ERP interface.

No function may be intentionally omitted to make a smaller package appear complete. No synthetic payroll rules, fabricated staff compensation, sample business transactions, test switches, demonstration records, staging-only features or QA artefacts may be shipped in the product runtime. Build and verification tooling belongs outside the installed plugin. Legitimate operational workflows such as staged import review, reservation states, draft approvals and safe temporary migration tables are not to be removed merely because their internal names contain “stage”; their business purpose must be preserved and their data boundaries tested.

The final installer itself must not be identified as a test/staging/candidate/review artifact. It must have one canonical version, source commit, manifest, inventory, reproducible installer hash, installation/upgrade instructions and acceptance receipt. “Production-ready” is a claim earned by evidence; the Owner’s ambition is the target, not permission to manufacture a PASS result.

## 2. Authoritative source and decision record

The controlling Owner decision is in Notion: [16.1.95 Protected Master — HR/Payroll Integration Decision](https://app.notion.com/p/40d6db0f767e8140b744d32a043269f9). The release gates are in [Notion Canonical Release Audit & Acceptance Gates](https://app.notion.com/p/3fc6db0f767e81f2a3bbe644048054f9).

- **Protected operational baseline:** 16.1.95 installer SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625` (75 runtime files; 49-table contract). This is the preservation baseline, not a claim that all later plans are already integrated.
- **Selected HR/payroll implementation lineage:** PR #39 merged at `bd6c78f180e3bc6871317aa32c1f11d4fab8a427`; its 16.2.9 exact installer SHA-256 is `c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01` (88 files). Use its workflow and controls as mapped engineering source, not as a wholesale production overlay: it adds 37 HR tables, changes legacy schema behavior, ships a guarded synthetic-only calculator path, and has major PHPCS/WPCS findings.
- **HRX integration line:** PR #48 remains open/unmerged, head `1fbf761632e5e568331b5ea1167c643c0d72db46`. The latest retrieved 16.3.0 review installer SHA-256 is `0bb23267d00d31125f49552b61be25756eacbf5261d6a14b5a88cd3010450cbe` (76 files). Its own manifest says `cumulative_core_update=false`; its 47-table replacement schema is not approved and installation is disabled pending design/Owner approval.
- **16.3.1 local receipt:** Notion records SHA-256 `36b215da876f297a6d6948eda0b050e4d8b4d9d5ef2c7009e5b5120f3ca2faa5`, 85 files/51 PHP, 63 assertions passed, zero failed, five findings, status `CONDITIONAL_PASS`. The 16.3.1 ZIP and exact source tree were not retrievable from the Library or repository in this execution, so this hash and receipt cannot be independently recomputed here. The Notion record itself lists live WordPress/MariaDB, legal approval, 24 SOP files, HRX schema approval, restore/rollback, two-period payroll and stakeholder acceptance as pending.
- **16.1.98 side line:** exact installer SHA-256 `1997b33326b45c7d0a32ba283dc7fb6ea8bd4a61d1392990944065add5c81f8b` (79 files) is pinned to 16.1.95 and is not proven cumulative from the intervening 16.1.97 work; it is not a final foundation.

The repository `main` at `a46214f6bd5e21a9948d5c896d2136d722d36f16` contains governance/CI/audit sources but no committed `stockland-erp-app/` runtime root. Installer packages are assembled by workflow builders from pinned binary installers plus overlays. A final auditable release therefore requires that the exact runtime source/build inputs and the generated installer be tied to one commit and reproducible receipt; an Actions artifact alone is not a complete source-of-truth repository.

## 3. Exact packages independently recovered and checked locally

The following exact inner installer archives were downloaded from their GitHub Actions artifacts and independently hashed. ZIP inventory, CRC, file syntax and assets were checked in this environment.

| Package | Inner installer SHA-256 | Files | PHP lint | JavaScript syntax | JSON | CSS/PostCSS | ZIP CRC | Manifest version |
|---|---|---:|---:|---:|---:|---:|---|---:|
| 16.1.95 | `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625` | 75 | 48/48 | 2/2 | 3/3 | 9 | PASS | 16.1.95 |
| 16.2.9 | `c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01` | 88 | 55/55 | 2/2 | 3/3 | 15 | PASS | 16.2.9 |
| 16.1.98 | `1997b33326b45c7d0a32ba283dc7fb6ea8bd4a61d1392990944065add5c81f8b` | 79 | 52/52 | 2/2 | 3/3 | 9 | PASS | 16.1.98 |
| 16.3.0-release | `66998733c2d7ed2f8d1c2c1ad45178dc718ed1d316fb0f17a0ff240513097d6f` | 76 | 49/49 | 2/2 | 3/3 | 9 | PASS | 16.3.0 |
| 16.3.0-current-review | `0bb23267d00d31125f49552b61be25756eacbf5261d6a14b5a88cd3010450cbe` | 76 | 49/49 | 2/2 | 3/3 | 9 | PASS | 16.3.0 |

Additional confirmed results:

- All recovered installers have one `stockland-erp-app/` root, unique archive paths and clean CRC. CSS module manifests are internally unique and the ordered module sources reconstruct `assets/app.css` (whitespace-normalized) in every recovered package.
- Source route inventories (lexical counts, not security proof): 16.1.95 = 116 REST registrations / 132 `permission_callback` keys; 16.2.9 = 157 / 178; 16.1.98 = 130 / 146; 16.3.0 = 137 / 154. These counts do not prove correct role, record, employee, branch, location, field or workflow-state authorization.
- The protected 16.1.95 source declares 29 Core/Operations + 16 Accounting/Procurement + four legacy HR/Payroll tables = 49. 16.2.9 declares 86 (29 + 16 + 41 HR); 16.1.98 declares 73 (29 + 16 + 28 HR); 16.3.0 describes 45 active core/finance tables plus 47 proposed replacement HRX tables (92 inventory total), with the replacement schema explicitly unapproved/disabled.
- The baseline and HR-related source DDL contain no explicit relational `FOREIGN KEY` declarations. Referential integrity must therefore be demonstrated through approved unique constraints, transaction/service invariants and orphan/duplicate reconciliation; table count alone is not proof.

## 4. Direct cumulative regression found: 16.3.0 is not a 16.2.9 superset

A byte-level file map of the newest recovered 16.3.0 review package against 16.2.9 produced:

- 16.2.9: 88 files; 16.3.0: 76 files.
- 73 common paths; 56 byte-identical and 17 changed.
- 15 paths removed; three added.

**Removed paths (must be accounted for, retained or intentionally replaced with tested equivalent behavior):**
- `assets/css/07-dashboard-operations.css`
- `assets/css/08-reporting-worksheet.css`
- `assets/css/09-command-center-theme.css`
- `assets/css/10-bootstrap-desktop-search.css`
- `assets/css/11-operational-workflows.css`
- `assets/css/12-hr-payroll-lifecycle.css`
- `includes/class-stockland-hr-lifecycle.php`
- `includes/class-stockland-hr-payroll-calculator.php`
- `includes/class-stockland-hr-private-vault.php`
- `includes/class-stockland-payroll-statutory.php`
- `includes/class-stockland-schema-contract.php`
- `includes/rest/class-rest-hr-lifecycle.php`
- `includes/rest/class-rest-hr-payroll-cycle.php`
- `includes/rest/class-rest-hr.php`
- `includes/rest/class-rest-pos-cart.php`

**Added paths:**
- `includes/class-stockland-hr-replacement-calculator.php`
- `includes/class-stockland-hr-replacement.php`
- `includes/rest/class-rest-hr-replacement.php`

**Changed shared paths:**
- `README.md`
- `RELEASE_MANIFEST.json`
- `assets/app.css`
- `assets/app.js`
- `assets/app.min.css`
- `assets/css/06-operational-guards.css`
- `assets/css/modules.json`
- `assets/manifest.json`
- `includes/class-stockland-admin.php`
- `includes/class-stockland-business-calendar.php`
- `includes/class-stockland-db.php`
- `includes/class-stockland-frontend.php`
- `includes/class-stockland-hr.php`
- `includes/rest/class-rest-app.php`
- `includes/rest/class-rest-sales.php`
- `includes/rest/class-rest-setup.php`
- `stockland-inventory.php`

The removed `includes/rest/class-rest-pos-cart.php` is a direct POS regression risk. Removal of the six CSS modules 07–12 also discards the 16.2.9 dashboard, reporting worksheet, command-center theme, desktop/search, operational workflow and HR/payroll lifecycle styles. HR lifecycle/payroll/vault/statutory/REST controllers are replaced by three HRX classes while the old HRX schema remains disabled. This may be a deliberate architecture change, but the package comparison proves it is not a complete cumulative integration. Each replacement requires explicit capability-equivalent routes, preserved state/ledger behavior and regression evidence.

## 5. Material implementation risks requiring correction before the final package

### HR/payroll and legal data

1. The 16.2.9 runtime contains a `SYNTHETIC_TEST_ONLY` rule path and an explicit opt-in synthetic test switch. It defaults off and is guarded, but it is still code inside the plugin ZIP. The Owner’s release rule requires that this path and test fixtures be moved to external QA tooling and excluded from the final product runtime.
2. The 16.2.9 payroll model contains default pension percentages of 7% employee and 11% employer in its statutory class/schema defaults. Existing compensation migration/default approval semantics are also inconsistent (`APPROVED` in one migration default versus `DRAFT` for a fresh definition). Do not infer that these rates, taxable bases, allowances, exemptions or status defaults are current Ethiopian law or approved Stockland policy. Keep statutory rules unavailable for posting until a qualified Ethiopian reviewer signs the exact source, effective dates and calculation vectors.
3. The 16.2.9 HR private-vault implementation enforces outside-webroot storage, MIME checks, random references and SHA-256 integrity, but the file bytes are not encrypted at rest. The HRX line declares AES-256-GCM with a site-supplied keyring; that is a better design direction, but key availability, key rotation, restore recovery, authenticated download and host-level controls still require live verification.
4. The 16.3.0 HRX 47-table schema is unapproved, disabled and lacks explicit foreign-key definitions in the inspected HR source. Its health/setup endpoints deliberately remain pending/blocked (HTTP 409) until schema approval. That is a guard, not a completed operating HR/Payroll module.
5. Existing employee compensation gaps must be obtained from a current authorized read-only source. No salary, PAYE, pension, allowance, tax exemption, creator classification or payment facts may be fabricated to make a demo payroll run green.
6. The full lifecycle still requires traceable hiring and screening, offer/contract/onboarding, time/shift/overtime, leave/accrual, compensation changes and approvals, legal rule registry, frozen payroll inputs, maker/checker, payslips, recipient-level payment attempts/retries, tax/pension remittance, AP/GL/PLEDGER reconciliation, nonemployee payees, exits/final settlement and retention/privacy.

### Operational, finance and interface preservation

7. The 16.3.0 package comparison shows the POS cart controller and six CSS modules removed from the 16.2.9 package. Reconcile the cart, barcode, retail/wholesale, report/print and responsive UI behavior before acceptance.
8. PR #48 has 40 inline review threads and none is marked resolved in GitHub at this read. Author replies exist on some threads, but they are not the same as independent code verification or thread resolution. Latest HRX workflow run #96 passed; a separate ERP PHPCS recheck was skipped. PR #52 remains open/unmerged and its 16.3.0 HRX workflow attempts #90/#91 failed; the PHPCS recheck was skipped.
9. The prior PHPCS/WPCS evidence for 16.2.9 records 16,364 errors and 690 warnings. A successful PHP lint or security scan does not clear coding-standard findings. The final cumulative package needs a complete PHPCS/WPCS run with a reviewed, justified disposition and no unexplained suppressed diagnostics.
10. The 16.3.0 HRX installer contains no DOCX or PDF documents. The controlling Notion plan requires the 24 Amharic-first SOP/resource pack. The Library currently lists no corresponding 24-file DOCX/PDF bundle, and the 16.3.1 receipt also lists SOP delivery pending. Verify/recover the approved source documents and include the authorized final resource pack in the one installer or an explicitly approved private-resource provision process.
11. The repository main branch currently has no branch-protection rule enabled. PR review and CI are therefore not server-enforced; enforce the project’s review protocol and required exact-package checks before merge.

### Latest PR #48 HRX source-level finding reconciliation

I separately re-opened the exact latest recovered PR #48 review installer (`0bb23267d00d31125f49552b61be25756eacbf5261d6a14b5a88cd3010450cbe`) and checked the source behind the inline review comments. This is a static source review only, not a runtime or independent regression-test pass. The GitHub API still reports 40 review threads and **0 resolved threads**; author replies are not changed to “resolved” by this report.

| Review concern | Current source observation | Disposition |
|---|---|---|
| Single-record and list location scope, including people without a direct `location_id` | `resolve_related_locations()` and `location_filter_for_entity()` now use approved employment/assignment or recruitment relationships; unknown/unmapped entities fail closed. | **Mitigation verified in source**; role/record matrix still needs runtime tests. |
| HR-only user can read component amounts | Both list and single-record read paths redact the generic `amount` field as well as salary/payroll totals when payroll-view capability is absent. | **Mitigation verified in source**; runtime authorization tests remain. |
| Legal-rule reviewer qualification/evidence | Dedicated review endpoint validates named qualification, source/test hashes and approved private opinion, then updates rule, workflow event and audit inside a transaction. | **Mitigation verified in source**; actual legal review and runtime rollback test remain. |
| Stale payroll employee/compensation/rule set | `frozen_payroll_sources_current()` compares current eligible employee IDs, compensation IDs/versions/items and approved rule-bundle hash against the frozen run before Owner approval. | **Mitigation verified in source**; concurrency and DB behavior still need integration tests. |
| Foreign-currency payroll/payee values | Both pathways reject non-ETB until a separately approved conversion/accounting policy exists. | **Safe rejection verified**; foreign currency is not implemented. |
| Open employee exits included in ordinary payroll | Preflight and calculation recheck reject unresolved exits effective by the period end. | **Mitigation verified in source**; runtime test remains. |
| Remittance partial-payment double count | Verification computes the prior reconciled sum before changing the current remittance to `RECONCILED`, then recomputes the total once after the update. | **Original arithmetic defect appears corrected**; see separate concurrency blocker below. |
| Payroll close before payout batch reconciliation | Close requires every batch to be `RECONCILED`, a linked accounting journal, every recipient settled, and statutory obligations reconciled or not required. | **Mitigation verified in source**; live accounting reconciliation remains. |
| Cash-only request with `0.00` in-kind value | Verification is required only when the numeric in-kind amount is positive; zero-value cash-only requests do not enter the in-kind verification path. | **Mitigation verified in source**; end-to-end payee testing remains. |
| Unrelated stock movement used for in-kind verification | Stock reference must identify this exact payee request and at least one negative ledger movement; an independently approved valuation document must also be linked. | **Partial mitigation verified**; exact product/quantity-to-valuation reconciliation still requires stronger evidence and integration tests. |
| Approved engagement required for payee processing | Calculation loads an existing `APPROVED` engagement and checks that the service period is inside its dates. | **Mitigation verified in source**; approval/payment parity must remain tested. |
| HRX schema checked only by column names | `schema_status()` compares expected columns, normalized SQL types, required indexes and unexpected columns. | **Mitigation verified in source**; actual host schema remains untested. |
| Accepted offer creates duplicate employment | The endpoint locks the offer and checks for an existing employment before creating employment, contract and onboarding tasks in one transaction. | **Mitigation verified in source**; concurrent DB test remains. |
| Table-specific creator/approval fields and optional timestamps | The current writer maps known actor fields by entity and updates timestamps only when present in the row schema. | **Mitigation verified in source**; full CRUD-by-entity tests remain. |
| UI submit key, application stage, deliverable transition and exit-clearance evidence | The current UI sends idempotency keys, renders applications from `stage`, exposes PENDING→SUBMITTED deliverables, and includes approved evidence references for clearance actions. | **Mitigation verified in source**; actual browser/API flow remains untested. |

The source review also confirms these are **not yet completed features**, even though the implementation avoids silent underpayment:

1. Approved overtime/night/rest-day/holiday time causes payroll preflight to block with `APPROVED_TIME_PAYROLL_RULE_UNSUPPORTED`. Approved unpaid/partially paid leave likewise blocks because the payroll calculator does not consume it. The separate `overtime_amount()` helper is not wired into `payroll_line()` or the run builder. The planned attendance/overtime/leave-to-payroll lifecycle is therefore still incomplete.
2. The calculator accepts only `MONTHLY` compensation items and returns an explicit error for `ONE_TIME`, `HOURLY` or `DAILY`. This is safer than repeatedly or incorrectly paying them, but those supported compensation frequencies are not yet implemented.
3. Branch-scoped `social-snapshots` and any other entity with no approved row-to-location mapping fail closed to company-scope roles. They are not available as branch-scoped views until a verified relationship model is added.

**Additional concurrency finding from source review:** `verify_remittance()` reads the remittance and the prior reconciled total before beginning its transaction. It does not lock the statutory-obligation row while checking the remaining balance. Two distinct remittances against the same obligation could therefore both observe the same outstanding amount before either is posted. This needs an obligation-level row lock/serialized transaction, duplicate/idempotency protection and a concurrent integration test before remittance posting can be accepted. It was not one of the author-replied fixes I could safely mark closed.

The latest HRX artifact also still identifies itself as an integration candidate in its README and release manifest, and its schema remains explicitly unapproved/disabled. No synthetic payroll marker or sample payroll transaction was found by a text scan of this 16.3.0 installed plugin tree; the guarded synthetic calculator path is present in the separate 16.2.9 source and must remain external to the eventual plugin runtime. The final consolidated installer must carry its one canonical release identity and must not inherit the current candidate label.

## 6. Work actually performed in this execution

- Retrieved exact Actions installers for 16.1.95, 16.2.9, 16.1.98 and two 16.3.0 HRX builds.
- Independently recalculated their installer SHA-256 hashes and archive inventories.
- Ran local `php -l` on every packaged PHP file (the counts above), Node syntax on `assets/app.js` and `assets/pwa.js`, parsed each JSON file, parsed each CSS file through PostCSS, checked CSS-module reconstruction, scanned REST callback declarations and inspected key HR/payroll source.
- Produced a complete file-level SHA delta between 16.2.9 and the newest recovered 16.3.0 review installer.
- Reviewed controlling Notion HR design, implementation plan, shared-service contract, 16.3.1 receipt, GitHub PR/CI state, and finalization audit.

The available local container has PHP 8.4.24, Node 22.16.0, Python 3.13.5, PostCSS, Playwright 1.57.0 and Chromium 144. It does **not** have `mysqli`, `pdo_mysql`, PHP ZIP support, Composer, WP-CLI, MySQL/MariaDB CLI or Docker. Shell network access to GitHub is unavailable. There is no authenticated Stockland WordPress host or database in this session. Playwright/Chromium being installed does not make the actual WordPress application or database available; no real authenticated ERP browser workflow could be exercised. PHP 8.3/8.4 Actions checks exist for selected historical artifacts; they do not turn this local environment into a live WP/MariaDB test host.

Therefore this execution did **not** perform: WordPress activation/upgrade on the target hosting plan; REST calls as Owner/Management/HR/Finance/Branch Manager; real database migration/rollback; PWA browser/service-worker checks; race/concurrency/failure injection; POS/stock/GL/AP/AR/payroll end-to-end posting; private document upload/download against the host; backup restore; two full payroll periods; legal review; Owner/HR/Finance final acceptance.

## 7. Final disposition

**No recovered package can honestly be released as the final production installer today.** This is not a scope reduction and not a decision to create another partial product. It is the evidence-based conclusion that none of the current exact source/package combinations satisfies the Owner’s cumulative end-to-end definition:

- 16.1.95 is the protected 49-table operational baseline, not the full planned final HR/payroll system.
- 16.2.9 is an HR implementation source, not a production replacement: schema drift, synthetic-only runtime path, unapproved/statutory defaults, PHPCS backlog and missing lifecycle/host acceptance remain.
- 16.1.98 is a side line built from 16.1.95, not a proven carry-forward of 16.1.97 and all later work.
- 16.3.0 replaces/drops 16.2.9 paths and is explicitly a non-cumulative HRX integration artifact with unapproved/disabled schema.
- 16.3.1 is only a conditional Notion receipt in this environment; its exact ZIP/source cannot be independently verified.

No new installer ZIP has been produced, because producing one from these incomplete lineages would either discard agreed functionality, retain disallowed synthetic/runtime behavior, or silently enable an unapproved HR data model. No GitHub review thread has been marked resolved and no Notion audit finding has been closed merely to create a zero-finding appearance.

## 8. Required completion order for one final installer

1. Recover the exact 16.3.1 ZIP and all 85 source members—or reproduce that exact output from committed, immutable source. Commit the builder, overlays, source ZIP identity, dependencies, generated installer, manifest and receipt to one canonical release commit.
2. Use the Owner-approved source lineage. Keep the protected 16.1.95 operations/finance behavior and all accepted later cumulative functions. Selectively integrate the approved parts of PR #39/16.2.9 and HRX only after mapping every changed/removed route, class, asset, table, report, permission, audit and GL consumer. No wholesale overlay.
3. Finalize a source-backed ERD and reuse/replace matrix for the HR/payroll/payee model. Preserve the existing four legacy HR tables and all 49 baseline contracts until an additive mapping, orphan checks, copy-only migration, backup/restore and rollback are proven. Obtain the required Owner schema approval and qualified Ethiopian statutory review. Keep no hard-coded unapproved rules.
4. Move every synthetic payroll fixture and test-only calculator path outside the installed plugin. Keep realistic, sanitized QA fixtures in the external CI suite only. Make production payroll fail closed when approved rule bundles or verified employee compensation are absent.
5. Implement and independently verify all 40 PR #48 review threads, including authorization/location scope, employee and compensation privacy, approved overtime/leave integration, compensation frequency, legal approval atomicity, payroll frozen-set parity, remittance/payout reconciliation, cash/zero values, in-kind valuation, currency matching, idempotency, exit cutoff, state transitions, schema type/index contracts and UI evidence submission. Resolve the GitHub threads only after the exact resulting code passes regression tests.
6. Preserve and retest the full POS cart, Product Master, inventory/warehouse, receiving, requisition/reservation, transfers, returns, adjustments, counts, daily close, procurement, AP/AR, expenses, PLEDGER/GL, reports/print, notifications, responsive UI and PWA cache behavior.
7. Deliver and verify the approved 24 Amharic-first SOP/process-resource documents, with secure role/location-scoped downloads and immutable version/hash metadata.
8. Run full PHPCS/WPCS, PHP 8.3 and 8.4, Node syntax/behavioral suites, CSS module build/parity, Semgrep/security, schema/route/dependency contracts and exact-package receipt checks on the one final release commit. Ensure every workflow actually runs; no green job with skipped build/review can count as PASS.
9. Run an isolated copy of the intended WordPress/PHP/MariaDB host and complete fresh install, upgrade, full role/branch matrix, all REST/UI workflows, real transaction/ledger reconciliation, duplicate/retry/concurrency/failure rollback, document privacy, PWA/browser matrix and system health.
10. Prove backup and restore of files, database and private document keyring; verify rollback and source/installed file hashes; complete two payroll periods using reviewed legal rules and approved real compensation data; obtain signed Owner, HR, Finance and qualified legal acceptance.
11. Create one consolidated WordPress plugin ZIP with no test/demo/staging runtime or records, no dropped accepted workflows, no misleading artifact classification, no external Node production service, and one reproducible SHA-256. Close each exact finding against evidence in GitHub and Notion, then request the independent final production audit against that exact ZIP and SHA.

**Acceptance rule:** only findings with exact source changes, repeatable passing evidence and a verified final package hash may be closed. All other items remain visible and assigned. The result must be one complete installer—not another partial release.

## 9. Evidence attachments

- Machine-readable audit: `STOCKLAND_ERP_CONSOLIDATION_EVIDENCE_2026-11-04.json`
- File-level 16.2.9 → 16.3.0 path delta: `STOCKLAND_ERP_16_2_9_TO_16_3_0_FILE_DELTA_2026-11-04.csv`
- Evidence hash sidecar: `STOCKLAND_ERP_CONSOLIDATION_EVIDENCE_2026-11-04.sha256`