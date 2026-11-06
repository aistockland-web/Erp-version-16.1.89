# Stockland ERP — Complete Source Archaeology and Reconciliation

**Evidence snapshot:** 5 November 2026  
**Repository:** `aistockland-web/Erp-version-16.1.89`  
**PR #55 source branch:** `owner/incremental-target-commits-production-2026-11-05`  
**Disposition:** Source comparison is complete for the accessible GitHub refs and supplied bundle. **The operational baseline is not identified; do not build, migrate, install, or release a replacement yet.** This report is not production acceptance.

## 1. Decision

The reported installed version is 16.2.13, but no exact 16.2.13 source tree, installer, or SHA-256 was present in the accessible refs or supplied bundle. The transcript describing a 16.2.13 working copy is not bound to an exact package or commit. Therefore the currently installed operational state, upgrade delta, rollback point, and safe cumulative base remain unknown.

Keep 16.1.95 as a **protected comparison and rollback reference only**. Do not treat 16.1.95, 16.2.9, 16.3.0, or 16.1.101 as a drop-in replacement for 16.2.13. No cumulative implementation base is selected by this report.

Do not begin application-code implementation or produce a new installer until the exact installed 16.2.13 package and its hash are recovered, or the owner explicitly accepts that evidence gap and approves a separately tested migration plan. This report does not authorize schema changes, live-data tests, or production deployment.

## 2. Scope, evidence and limits

Reviewed:

- The controlling master handover, source-archaeology directive, incremental-target directive, and CI/CLI/build-autonomy addendum on PR #55.
- Reachable refs and commit history in `aistockland-web/Erp-version-16.1.89`, all-state pull-request metadata, the second public `aistockland-web/Erp` repository, and the supplied archive.
- All seven Library ZIPs, the recovered 16.1.95, 16.2.9 and 16.3.0 installer ZIPs and CI artifacts, and the 16.1.101 staging ZIP from its release branch.
- Package manifests, source-file hashes, PHP/JS/CSS inventory, schema and migration declarations, POS/cart flow, PWA shell, HR/payroll and document handling, SOP payload, and available exact-commit CI evidence.

The supplied archive is `STOCKLAND_ERP_CODESPACES_SOURCE_ARCHAEOLOGY_INPUTS_2026-11-05 (9).zip`, SHA-256 `0344a8ae8882b04aa6e7d5486cdcea2b7f5efc1c4def6bea07e517c4ce37832d`. Its 30 listed payload entries matched `SHA256SUMS.txt`; ZIP CRC checks passed for the reviewed packages. No candidate installer had duplicate or unsafe archive paths.

This is a source and artifact audit, not a WordPress/MariaDB business acceptance run. No live business data was accessed. The supplied 16.2.13 transcript is treated as an unverified claim because it does not identify a reproducible package SHA, exact commit, or complete test tree.

## 3. GitHub history, branches and pull requests

### Main repository

At this evidence snapshot:

- `main`: `a46214f6bd5e21a9948d5c896d2136d722d36f16`.
- PR #55 source branch: `5ce52420f076054243c45ba303e7952986638586`; open, based on `main`. The latest commit adds the autonomy/CI/CLI/build-execution directive; it does not identify or supply the missing 16.2.13 package.
- 68 remote branch heads and zero tags were returned by `git ls-remote`.
- 604 commits are reachable from `main`; 1,197 commit objects are reachable from all fetched refs. The earliest reachable `main` commit is dated 2026-10-20. The asserted August–October project history is therefore not fully represented by the accessible repository history.
- 50 all-state pull requests were enumerated. The relevant package lineage is:

| PR | Current state | Head | Archaeology significance |
|---|---|---|---|
| #39 | Merged | `996addd06c635de7deb3738772b1bc3678435b22` | Source lineage for the 16.2.9 full-cycle candidate. |
| #48 | Closed, unmerged | `55e24b735417d3455bf7a591a5344c4ea05354e0` | 16.3.0 HRX candidate; not a cumulative replacement. |
| #49 | Closed, unmerged | `28b9308db879b5b298739170120338f139de7326` | 16.1.101 staging package, HRX DDL replay and SOP-tab candidate. |
| #55 | Open | `5ce52420f076054243c45ba303e7952986638586` | Controlling engineering directives and report destination. |

Other history branches retain useful HR lifecycle, design, CI, report, release, and remediation work. The complete fetched remote branch-name inventory (including `main`) is:

```text
audit/deep-ci-activities
audit/erp-wide-sop-library-2026-10-29
audit/inspect-16-1-91-source
audit/local-consolidation-closure-2026-11-04
audit/release-gates-16.1.89
audit/remediation-approved-2026-10-20
ci/erp-16-1-98-exact-package-gates
ci/hr-payroll-engine-test-workflow
codex/reports-analytics-selector-print-pdf-16-1-93
docs/erp-cross-platform-work-directive-2026-11-04
docs/hr-16-1-97-staging-candidate-register
docs/hr-payroll-full-lifecycle-plan-v0-5
docs/hr-replacement-baseline-2026-10-26
docs/hr-resource-pack-delivery-2026-10-26
docs/hr-route-control-trace-2026-10-26
feat/16.1.93-reports-explorer
feat/16.1.94-premium-workspace-intelligence
feat/erp-16-1-98-full-hr-compensation-mi
feat/reports-analytics-selector-print-permissions
feat/reports-explorer-16-1-93
feat/reports-selector-print-pdf-16.1.92
feat/stockland-erp-16-1-98-hr-payroll-mi
feature/full-hr-payroll-lifecycle-16-1-96
feature/full-hr-payroll-rebuild-16-1-98
feature/full-hr-payroll-replacement-16-2-0
feature/full-hr-payroll-replacement-blueprint-v1
feature/full-hr-payroll-replacement-plan
feature/full-hr-payroll-runtime-16-1-98
feature/hr-payroll-design-reset-v0-6
feature/hr-payroll-engine-domain-core
feature/hr-payroll-full-cycle-16-2-0
feature/hr-payroll-replacement-phase0
feature/hr-payroll-replacement-v0-2
feature/hr-payroll-replacement-workforce-phase1
feature/hr-replacement-full-lifecycle-v1
feature/hr-replacement-stage2-semantic-review
feature/hr-replacement-stage3-rebased-2026-10-28
feature/hr-replacement-stage3-workforce-slice
feature/hr-replacement-stage4-people-lifecycle-2026-10-28
feature/hr-replacement-stage5-time-leave-2026-10-28
feature/hr-resource-download-handler-16-1-98
feature/hris-redesign-blueprint-v0-6
feature/hris-replacement-full-lifecycle-v1
feature/hrx-clean-slate-runtime-16-3-0
feature/management-intelligence-social-planning
feature/stockland-erp-16-2-10-cumulative
fix-hrx-sha256-path
fix/16-2-7-hr-docs-svg-ci
fix/ci-css-profile-semgrep-coverage
fix/ci-hr-batch-boundary-16-1-96
fix/hrx-readiness-accuracy-and-candidate-labels
fix/hrx-unified-table-prefix-and-schema-readiness-2026-10-28
fix/release-ci-baseline-patch-chain-16-1-97
fix/release-ci-python-preflight-16-1-97
main
owner/incremental-target-commits-production-2026-11-05
phase0-live-clone-health-audit
release-tooling/export-16.1.89-artifact
release/16.1.101-hrx-health-sop-tab
release/16.1.90-rest-context-remediation
release/16.1.91-notification-ux
release/16.1.91-publish-installer
release/16.1.92-period-reports
remediation/16.1.89-wpcs-shell-review
retire/hr-16-1-96-ci-keep-baseline
revert-16-feature/full-hr-payroll-lifecycle-16-1-96
revert-5-audit/remediation-approved-2026-10-20
work/hr-replacement-source-export
```

### Separate `aistockland-web/Erp` repository

This repository is a small static responsive UI preview, not a second ERP plugin implementation. Its two branches and five reachable commit objects (including the PR merge ref) do not provide WordPress runtime, database, ledger, finance, payroll, or ERP CI evidence. Its PR #2 (`ui/stockland-command-center-last-pass`, head `154a35378f3b0370ddc1e095dae95466cf9862e7`) is open and leaves authenticated WordPress/browser acceptance pending. Do not merge its preview as a substitute for application code.

## 4. Package and version inventory

All hashes below are SHA-256 of the ZIP bytes, not of an extracted directory.

| Version/artifact | SHA-256 | Contents and lineage | Disposition |
|---|---|---|---|
| Library 16.1.79 source | `9c0941a3abf253f3e63d0c7b03f2759822848f1a645797bc5e7eec062fcd63ee` | 84 files; ZIP CRC clean. | Historical source only. |
| Library 16.1.80 source audit | `7a94114bcd8649facd83475b1f3a02fabe08749d25644a49caea7c2762b8422c` | 79 files; ZIP CRC clean. | Historical source only. |
| Library 16.1.81 source audit | `d1dffe8eba787e330dd980b9cd93c18d76f4a670a2285d0ce7dbff5f7138dd6c` | 78 files; ZIP CRC clean. | Historical source only. |
| Library 16.1.82 canonical bootstrap | `463a69c240f0f1ce8d926ad50c70505d6938b4e72e32d530a2004bbe25211be7` | 72 files; ZIP CRC clean. | Historical installer/source. |
| Library 16.1.82 source audit | `54798c0897498e2f06b44d6a2b5fe7c5289d79bc9371e909a6d07cb64410ccc8` | 92 files; ZIP CRC clean. | Historical source only. |
| Library 16.1.83 canonical update | `9def43e31f9db0dc85638dc7727c184a3db0c8dec968bd29327a3afca6855f1a` | 72 files; ZIP CRC clean. | Historical installer/source. |
| Library 16.1.84 canonical update | `9f0295af89311e7a1c4f861ec3f7bc54db071fd143390462942d6488e9f464bf` | 72 files; ZIP CRC clean. | Historical installer/source. |
| 16.1.95 canonical installer | `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625` | 75 plugin files; protected 49-table reference. | Comparison/rollback reference only. |
| 16.1.101 staging installer | `2916d9ddd80f0174da8ccb8ab9b57f51050ed5aa3d65d210e6ea97283262b5d1` | 103 file entries plus 9 directory entries; includes 24 valid DOCX files. Derived from a 16.1.99 review ZIP based on 16.1.95, not the reported installed 16.2.13 (nor proof of 16.1.98 parity). | Staging candidate only; SOP content/policy and workflow acceptance remain open. |
| 16.2.9 HR/payroll candidate | `c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01` | 88 plugin files; source from merged PR #39. Candidate manifest proposes 86 tables. | Preserve source changes for reconciliation; not a baseline substitute. |
| 16.2.13 reported installation | **Not recovered** | No exact ZIP/source tree or package SHA in the accessible refs or bundle. A working-copy transcript is not package identity. | **Blocking missing artifact.** |
| 16.2.10 referenced Library source | **Not recovered** | The referenced package was not accessible in the supplied bundle. A branch named `feature/stockland-erp-16-2-10-cumulative` points at the current main SHA; that branch name does not prove a distinct installer. | Lineage unresolved. |
| 16.3.0 HRX candidate | `f48993499e7664c45a079595af8efedbd5881728952cf019428f5db2d1872412` | 76 plugin files; built from 16.1.95, not cumulative 16.2.9. Manifest proposes 92 tables and says schema approval is false. | HRX source candidate only. |
| 16.3.1 conditional verification record | **Not recovered** | No matching package/record in the accessible bundle or refs. | Unverified reference. |

The accessible history includes 16.1.98-side branches and exact-package CI work, but the reviewed bundle does not supply a verified operational 16.1.98 package. No version string or branch name is accepted as proof of the installed state.

## 5. Source-lineage graph

```text
16.1.95 — protected 49-table comparison/rollback reference
  ├── PR #39 → 16.2.9 candidate
  │     + POS/cart, HR/payroll lifecycle and CSS modules 07–12
  │     + proposed schema expansion; approval and contract conflict unresolved
  ├── PR #48 → 16.3.0 HRX candidate
  │     + separate HRX model and encrypted-document implementation
  │     - does not contain the 16.2.9 POS/cart controller or CSS modules 07–12
  └── 16.1.99 review snapshot → PR #49 / 16.1.101 staging candidate
        + 24 DOCX resources and DDL replay/SOP-tab changes
        - not evidence of parity with the installed package

Reported installed 16.2.13 — no exact source/package/hash recovered
```

The 16.1.95 → 16.2.9 and 16.1.95 → 16.3.0 edges are confirmed by recovered source and build metadata. The 16.1.101 relationship is the candidate's stated/reviewed provenance, not proof it is a safe upgrade. The 16.2.13 node is intentionally disconnected: its lineage cannot be inferred.

## 6. Direct package comparison

An extracted-file SHA comparison of the three recovered installer ZIPs found:

| Comparison | Shared paths | Byte-identical shared files | Changed shared files | Removed from right-hand package | Added to right-hand package |
|---|---:|---:|---:|---:|---:|
| 16.1.95 → 16.2.9 | 75 | 56 | 19 | 0 | 13 |
| 16.1.95 → 16.3.0 | 73 | 62 | 11 | 2 | 3 |
| 16.2.9 → 16.3.0 | 73 | 56 | 17 | 15 | 3 |

Important changed/added 16.2.9 paths include `includes/rest/class-rest-pos-cart.php`, HR lifecycle/payroll calculator/private vault, the schema-contract class, related REST classes, base DB/frontend/setup/HR/payroll files, release metadata, and CSS modules `07-dashboard-operations.css` through `12-hr-payroll-lifecycle.css`. The candidate adds 13 paths without removing 16.1.95 paths, but changes 19 shared files.

The 16.3.0 package changes the release manifest, app assets, admin/frontend/HR/bootstrap sources, and adds three HRX classes. It removes the 16.1.95 statutory payroll class and legacy HR REST class. Relative to 16.2.9 it removes 15 paths, including the transactional POS-cart controller and CSS modules 07–12. Thus 16.3.0 is a separate HRX development line, not a cumulative ERP replacement.

## 7. Business capability and preservation matrix

| Area | Verified source evidence | Reconciliation / acceptance requirement |
|---|---|---|
| Core schema | 16.1.95 declares the protected 49-table contract: 29 core/operations, 16 accounting/procurement, four legacy HR/payroll. The source-mapped HR baseline records zero explicit foreign-key constraints in the reviewed DDL. | Treat these 49 tables and posted histories as preservation constraints; perform relationship/orphan analysis from actual consumers and staging data before migration. |
| POS/cart | 16.2.9 adds a cart controller with capability/authority and branch checks, input validation, branch/date locking, replay detection, SQL transaction, row locking, inventory-ledger posting, journal posting, and exception rollback. | These are source-level controls, not proof of correct persisted stock, cash, tax, journal, replay, concurrency, or rollback behavior. Verify complete cart and posted state in isolated MariaDB/WordPress tests. |
| Inventory/warehouse | The base line has stock and ledger services; 16.2.9 cart flow calls the ledger. The 16.3.0 source does not preserve the 16.2.9 cart extension. | Reconcile Product Master/SKU/barcode/import, central/branch stock, receiving, reservations, requisitions, transfers, returns, damage, counts, daily close, and audit effects against exact source and tests. |
| Finance | The 16.2.9 cart flow posts journals through the shared accounting service and explicitly rolls back on caught failures. | Verify balanced journals, AP/AR, expenses, PLEDGER/GL, closed-period guards, idempotency, ledger history and reconciliation. No recovered CI run proves this end-to-end. |
| HR/payroll | 16.2.9 extends legacy HR/payroll and adds lifecycle, payroll-cycle and calculator classes. 16.3.0 replaces the HR surface with HRX source and requires explicit schema approval. | Keep the four 16.1.95 HR/payroll tables read-only until approved migration, reconciliation and rollback. Validate all lifecycle, role/location, maker-checker and payment/remittance paths. Legal payroll rules are not approved by code or synthetic calculator tests. |
| Private documents | 16.2.9 vault stores outside the web root, restricts upload type/size/source, and computes SHA-256 integrity hashes. Hashing is **not encryption**. 16.3.0 HRX includes AES-256-GCM encrypt/decrypt and fails closed unless OpenSSL/random support and a site-specific versioned keyring in `wp-config.php` are configured. | Do not call 16.2.9 vault encrypted. HRX encryption still needs key generation, custody, backup, rotation/recovery and access-control tests. |
| SOP/resource pack | The 16.1.101 ZIP contains 24 DOCX files. The 16.3.0 ZIP contains no DOCX/PDF resource pack. | The found files' correctness, adoption, authorization and safe download path remain unverified. Do not publish them as approved policy or store signed personnel documents at public predictable URLs. |
| Responsive UI/CSS | 16.1.95 and 16.3.0 contain six numbered CSS modules; 16.2.9 adds modules 07–12 and changes app/minified CSS. | Preserve the strongest 16.2.9 UI work in any future cumulative build; verify desktop/tablet/mobile, keyboard, accessibility and browser acceptance against live-backed isolated data. |
| PWA | `assets/pwa.js` is byte-identical across 16.1.95, 16.2.9 and 16.3.0. The frontend serves a scoped service worker that caches versioned static assets (scripts, styles, images, fonts); it does not provide offline ERP/API transaction capability. | Preserve only with online/authenticated workflow tests, cache-version/update checks and proof that sensitive/API responses are never cached. Do not describe the app as offline-capable. |
| Authority/resources | The existing HR baseline identifies shared authority/role/location services and warns that route registration or UI hiding alone does not prove authorization. No complete accepted 16.3.0 SOP download flow is evidenced by its package. | Reuse existing authority services; verify each route, row, location, state transition, anonymous/direct URL and document-download access. |

### Schema and migration conflict

The schema evidence is not internally consistent or approved:

- Protected 16.1.95 contract: 49 tables (29 + 16 + 4).
- 16.2.9 manifest: 86 total tables and 37 new tables; its schema-contract source also describes an 86-table base plus a known 33-table HRX extension, which does not reconcile with the manifest's 86 total.
- 16.2.9 source/audit describes changes to legacy `employee_compensation`, `payroll_periods` and `payroll_lines`; the existing HR gate requires preserving all four legacy HR/payroll tables until migration/reconciliation/rollback approval.
- 16.1.101 material reports 49 + 33 = 82 tables.
- 16.3.0 manifest proposes 92 total tables (45 non-HR + 47 HRX) and sets schema design approval to false.

These are different models, not interchangeable schema counts. Reconcile every table, column, index, relation, migration, consumer, legacy write path, orphan case and rollback effect with an owner-approved ERD before any install or migration. No data migration may overwrite/delete posted transactions, ledger history, payroll history, or audit evidence.

## 8. CI, build and test evidence

| Candidate | Exact CI evidence | What it establishes—and does not |
|---|---|---|
| 16.1.95 | Run `40036018031`, commit `c80d3efc84d175ec59cb5b8d6992a3ecab744674`, artifact `12536703884`; recorded as passed. | Validates that run's artifact/checks, not the installed package or present-day workflow acceptance. |
| 16.2.9 | Run `41146685514`, commit `996addd06c635de7deb3738772b1bc3678435b22`, artifact `13020807863`; PHP 8.3 and 8.4 jobs passed. | Candidate-specific CI; not proof of schema approval, full persisted business behavior, or compatibility with 16.2.13. |
| 16.3.0 | Run `41734244180` passed on commit `1fbf761632e5e568331b5ea1167c643c0d72db46`; final HRX branch/PR #48 head is `55e24b735417d3455bf7a591a5344c4ea05354e0`. | Passing run is not on final head. Its outer artifact digest `60b877fcda546283eaa390b12b78cbb28664f31a7bb8c80fb9a0aa8f0589a5a5` (1,137,021 bytes) differs from the bundled CI artifact hash `215bd114c96ce21c3e9fc521172878a7a19c4fdb34b5a7cc842ff179c9927953` (1,137,024 bytes); provenance must be reconciled. |
| 16.3.0 earlier review run | Run `41549709592` is recorded for an earlier PR #48 commit, not the current head. Its report records 49/49 PHP lint on PHP 8.3/8.4 and JS/CSS/security checks, but PHPCS/WPCS reported 687 errors and 145 warnings. | Static checks are not a clean style result, current-head CI, or end-to-end business acceptance. |
| 16.1.101 | PR #49 QA identifies a `dbDelta` replay fix and resource tab; the package has 24 DOCX files. | QA leaves business flows, full role tests, restore/rollback, legal payroll review and installed-version compatibility unresolved. |

Existing free/pinned tooling was inspected: `ci/phpcs/composer.json`/lock and `ci/cssqa/package.json`/lock. No dependency was installed and no duplicate test infrastructure was created. The prior finalization audit reports that WP-CLI, MySQL/MariaDB and a PHP MySQL driver were unavailable in that execution, so it did not run authenticated WordPress/MariaDB tests. This archaeology turn performed archive/hash/source comparisons only; it did not rerun CI or business tests. No result here is evidence of live-site or staging acceptance.

## 9. Gaps and release blockers

1. **Baseline identity:** exact installed 16.2.13 source/ZIP and SHA-256 are missing. No candidate can be safely called a replacement.
2. **History/source completeness:** accessible `main` history starts 2026-10-20, not the stated three-month period; the local 16.2.13 package and referenced 16.2.10/16.3.1 source are absent.
3. **Schema authority:** 49/82/86/92-table evidence conflicts; 16.2.9 has an additional internal count inconsistency; HRX design is explicitly unapproved.
4. **Cumulative preservation:** 16.3.0 drops 16.2.9 POS/cart and CSS modules 07–12. No verified cumulative installer combines the stronger operational and HRX work.
5. **Data safety:** no exact-host backup, restore, rollback, migration replay, posted-history preservation, or in-place upgrade test is evidenced for the unknown installed package.
6. **Business acceptance:** POS through close, warehouse, procurement/AP/AR/GL/PLEDGER, payroll-to-payment/remittance, permissions/location scope, and persisted audit/inventory/ledger state need isolated end-to-end tests.
7. **HR/legal:** payroll/PAYE/pension rules and employment policy require qualified review and owner/management approval. Calculator tests or metadata do not authorize a rate. Missing compensation records must not be fabricated.
8. **Document security:** HRX key management/rotation/recovery and SOP authorization/download audit are not accepted; SOP content has not been approved.
9. **CI provenance:** HRX checks do not cover the current branch head; artifact digest mismatch and PHPCS findings remain unresolved.
10. **Release gates:** management, HR, Finance, security, legal/payroll, backup/restore/rollback and owner sign-off against the exact final commit and installer SHA remain mandatory.

## 10. Cumulative implementation sequence — blocked pending baseline decision

This is a gated sequence, not authorization to start code changes:

1. Recover and hash the exact installed 16.2.13 ZIP/source and record its manifest, file inventory, schema version and installed environment facts. If unavailable, obtain the owner's explicit acceptance of that gap and a documented staging-only migration strategy.
2. Reconcile the exact baseline with 16.1.95, 16.2.9, 16.3.0, 16.1.101 and relevant history. Produce file/table/route/data-lineage maps; approve the physical HR/schema model and preservation rules before migrations.
3. Select a cumulative source base only after steps 1–2. Preserve 16.2.9 POS/cart, inventory/finance and CSS improvements where validated; evaluate HRX encryption/key-management and lifecycle work as a separately gated integration. Keep unapproved HR/schema and payroll rules disabled.
4. Implement one coherent target at a time with source-level unit/contract tests and exact-commit CI; test migrations and repeated activation in isolated WordPress/MariaDB with synthetic data.
5. Run cumulative persisted-state regressions: POS/cart, branch inventory, receiving/transfers/counts/close, finance/AP/AR/PLEDGER/GL, HR/payroll maker-checker, private docs/SOP access, role/location isolation, responsive/PWA behavior, audit trail and injected-failure rollback.
6. Build one installer from the exact passing commit; align all version markers, manifest, build receipt and SHA-256. Verify isolated backup/restore/rollback and package/source parity.
7. Obtain the technical, operational, security, payroll/legal, management and owner release evidence against that exact artifact. No production deployment until the owner explicitly approves.

## 11. Requested next source item

Please provide the **exact 16.2.13 installer/source package as installed**, preserving its original filename and archive structure, together with its original SHA-256 if known. Do not substitute 16.1.95, 16.2.9, 16.3.0 or 16.1.101. This is the only next artifact needed to resolve the primary baseline question; no business requirements need to be restated.
