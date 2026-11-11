# Stockland ERP — Owner Master Execution Note: Full-Scale Cumulative Delivery

**Owner direction:** 11 November 2026  
**Purpose:** Continue the existing three-month Stockland ERP project through a complete, tested, cumulative WordPress ERP web application and one controlled production release path.  
**Canonical repository:** `aistockland-web/Erp-version-16.1.89`  
**Current agent branch:** `codex/stockland-agent-workspace-2026-11-11`  
**Operational baseline:** 16.2.13  
**Selected upgrade candidate:** 16.2.15 (review-only until every applicable gate passes)  
**Current release disposition:** Engineering GO; full cumulative ERP acceptance NOT COMPLETE; production release NOT APPROVED.

## 1. Owner direction and agent authority

The Owner explicitly directs the engineering agents to continue the actual Stockland ERP project using all relevant resources and connected tools available to the active session. This is not a new project, a demo, a sample app, a static dashboard, or another planning-only exercise. The mission is one integrated, secure, maintainable, online-first WordPress ERP/PWA with complete business workflows, full-cycle HR/payroll, a reproducible Codespaces/container environment, exact installer provenance and production-grade acceptance evidence.

Use the available GitHub, repository, branch, commit, PR, Actions, artifact, Notion and Codespaces capabilities directly. The connected GitHub identity currently reports admin/maintain/push permissions in `aistockland-web/Erp-version-16.1.89` and `aistockland-web/Erp`. The two historical `stockland-erp-project` repositories are visible as read-only in this connection; inspect their history and source but do not pretend that push permission exists there. A 403 from an earlier chat/session is historical evidence only; test current access afresh and continue using the access actually available.

For normal engineering work, do not ask the Owner to approve every read, file change, test, commit, branch push or review PR. Make coherent, safe changes, commit each completed target promptly to a descriptive working branch, open/update the review PR, run exact-head tests, correct findings and continue. Do not leave completed work only in Codespaces, chat, an uncommitted worktree or an installer ZIP.

The Owner's broad engineering authorization does not remove safeguards around irreversible production operations. Never request or store the Owner's passwords, personal access tokens, device codes, ChatGPT auth files, live database credentials, employee records or private documents in source, artifacts, chat or Notion. Do not modify the live business database, post synthetic transactions, alter real payroll, perform an irreversible production migration, publish a release, or deploy without the required actual Owner-controlled authorization and completed business/legal gates. Ask only when an operation genuinely requires Owner sign-in, an unavailable credential, a legal/business policy decision, a destructive live-data decision, or final production deployment authorization. Do not use “no access” as a generic stopping point when the current connector or Codespace can perform the work.

## 2. Canonical repositories and how to use each

| Repository | Verified role | Required treatment |
|---|---|---|
| `aistockland-web/Erp-version-16.1.89` | Current canonical integration, source, release-input and engineering repository. Current main at the 11 Nov intake is `1c3b743296b7a1f4e3b8a0b481e6883b5fed4154`. | Primary writable repository. Refresh all refs, PRs and checks. Preserve .13/.15 inputs. Implement in its canonical editable source and cumulative integration branch. |
| `stocklandethiopia/stockland-erp-project` | Historical originating ERP repository; last observed main `6bb6288633929f020d1869e54e607ee9f3261886`, version 16.1.78, 16 Oct. | Read-only lineage source. Review commits, branches, PRs and workflows from the August–October period; selectively port useful compatible work with provenance. Do not replace the current .13 base with .78. |
| `salestocklandet-bot/stockland-erp-project` | Older read-only bot mirror; observed history around 16.1.61 and 11 Oct. | Compare for duplicate commits, missing source and history only. Not canonical and not a production source. |
| `aistockland-web/Erp` | Standalone static Stockland UI preview; merged PR #2 at `5385a75c25364da93c8b4fa27585d6ede6438837`. | Use its CSS/layout/search ideas as a design reference only. README confirms no database, authenticated API, authorization, inventory, finance or payroll. Never install, merge as an ERP runtime, or present it as the application. |

Also inspect repository forks, branches, tags, PRs, release artifacts, workflow artifacts, reverted/closed/unmerged branches, and the Notion/Library evidence connected to the same work. Preserve origin SHAs and decisions. Do not infer that all historical work is present on current main merely because it appears in a README, UI preview, old ZIP or a branch name.

## 3. Exact source and version decisions

- **16.2.13 is the operational and upgrade baseline.** Historical exact installer SHA-256: `74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13`; 2,188,684 bytes; 113 ZIP entries; 80 PHP files. The committed filename is `STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip`; its sidecar has a filename alias without the `(1)` suffix. Verify the actual ZIP bytes and sidecar; do not change the binary to fix a sidecar name.
- **16.2.15 is the selected review candidate, not yet a production package.** Original archive SHA-256: `8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df`; 2,206,932 bytes. Exact-source CI found a missing PHP opening tag in `includes/class-stockland-schema-contract.php`. The current test workflow makes a separate repaired candidate by adding the missing opening tag only. Repaired test candidate SHA-256: `84f1efa4cc1406384cdf380196f65d5dce503845c2f3929983e9662b45a030df`; 2,211,427 bytes; 117 entries; 80 PHP files. This repaired ZIP is a test artifact, not a signed or approved release. Persist the correction as reviewed canonical source/build logic, rebuild the exact package from the final commit, produce its own manifest/sidecar/receipt/SBOM, and independently verify that final package.
- **16.1.95** is the protected comparison/rollback reference; SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`. Never install it over the later .13 operational baseline.
- **16.2.9** is a selective HR/payroll comparison/source; SHA-256 `c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01`. Port only traceable compatible features and their regression tests.
- **16.3.0/HRX** is a separate non-cumulative lineage, originally based on .95. Do not wholesale overlay it or activate its unapproved schema proposal.
- **16.2.14** has several distinct historical local candidate hashes/receipts. It is not a canonical GitHub release. Compare each candidate's actual bytes and source changes; port proven corrections only. Do not promote any .14 hash merely because it has a successful local receipt.
- Never create arbitrary new version labels to disguise incomplete integration. The final product must be a genuine cumulative update path from the .13 operational installer and an explicitly verified later candidate.

## 4. Current GitHub work to reconcile — do not duplicate or merge blindly

The following exact heads and run outcomes were refreshed on 11 November 2026. Refresh them again before acting; these values are a snapshot, not permanent truth.

| PR | Branch / purpose | Observed head/status | Evidence and next action |
|---|---|---|---|
| #80 | `codex/stockland-agent-workspace-2026-11-11`, agent config/source materializer | `79090074048ae1b3cf7a5e1de014d3f9d8122f0a`, open Draft; targets the integration line | Codex config, roles, skill, importer protections and shell checks passed in run `42954395120`. Continue the master instruction here and retain source importer. |
| #81 | `integration/stockland-erp-16213-to-16215-codespaces-2026-11-11` | `6c7152c361a21e6169fe7f48e3c43f8d63117715`, open | Earlier exact workflow `42951349107` failed WPCS, CSS parity and WordPress lifecycle. Do not treat it as final acceptance. Reconcile it with #82/#83. |
| #82 | `infra/stockland-erp-e2e-codespaces-2026-11-11` | `75d19fec5178f7ee73c8b967726c7b553193da36`, open Draft | Defines PHP 8.3/Node 22/Python 3.12/Composer/WP-CLI, disposable WordPress, MariaDB 10.11 and MySQL 8.0.46. Reuse it; avoid parallel environment scaffolds. |
| #83 | `fix/stockland-e2e-compose-rest-mysql8-2026-11-11`, stacked on #82 | `cc2bde441022a0ee11df76756df22c10bdc6d910`, open Draft | Corrects Compose path handling, REST checks and MySQL 8 .13-to-.15 update path. Environment run `43000093678` PASS; exact-package/E2E run `43000094786` SUCCESS. Review exact workflow/job output and PR ancestry before deciding integration. |

The #83 runs proved the devcontainer image/config validation; PHP 8.3 and 8.4 archive/static syntax checks; package repair test; disposable WordPress baseline installation; .13-to-repaired-.15 plugin update/activation on MariaDB 10.11 and MySQL 8.0.46; route smoke test; synthetic marker preservation and disposable-volume cleanup. These are real positive results for that exact run/head, not full ERP acceptance.

Critical boundary: the MySQL receipt showed only 12 tables before and after because the ERP setup wizard was not advanced. This does not prove the legacy 49-table data contract, migration or business workflows. REST route assumptions changed during earlier attempts; derive the correct namespace/route contracts from the exact package/runtime and keep the check fail-closed. The repaired .15 archive is not yet a canonical built release.

## 5. Three-month project history is mandatory source work

Preserve and reconcile the historical project window 5 August–5 November 2026, plus the current November integration, using the repository graph and all currently accessible sources. Read these repository documents before changing runtime behavior:

- `docs/engineering/STOCKLAND_ERP_THREE_MONTH_HISTORY_RECONCILIATION_AND_PRE_CODESPACE_HANDOVER_2026-11-05.md`
- `docs/engineering/STOCKLAND_ERP_INCREMENTAL_TARGET_COMMITS_AND_PRODUCTION_COMPLETION_2026-11-05.md`

Read the controlling Notion audit and Codespaces handover pages linked by `AGENTS.md`, including the 9 November .13 deep audit and 11 November dual-package E2E records. Search the available Library for retained source ZIPs, patches, reports, receipts, SOPs and plans. Inspect all relevant refs and their real diffs/merge bases; record every material source as merged, unmerged, reverted, superseded, candidate, archived or inaccessible.

Create/update a History & Integration Register with: source repo/branch/PR/commit, date, package and SHA, modules/features, exact changed paths, merge-base/ancestry, tests on exact SHA, decision (retain/port/reconcile/replace/archive/exclude), rationale, schema/API dependencies, regressions and resulting integration commit. Map historical implementation to current files; do not claim full three-month coverage until the register supports it. Agent chat transcript search is not guaranteed; report inaccessible chat/session records honestly and use durable Notion/GitHub/Library records instead.

## 6. Codespaces and container — execute the actual environment

The intended environment is free-tier-conscious and containerized: PHP 8.3+, PHP 8.4 CI, Node 22, Python 3.12, Composer, checksum-verified WP-CLI, GitHub CLI, Docker/Compose, disposable WordPress sites, MariaDB 10.11 and MySQL 8.0.46. Database port 3306 must stay internal; previews 8080/8081/8082 private. Do not add paid services, API-key billing, or paid infrastructure as a workaround.

Before edits, inspect the actual running Codespace: `pwd`, `git remote -v`, `git status --short --branch`, HEAD, upstream, worktrees, recent log, container state, Docker/Compose, PHP/extensions, Node, Composer, WP-CLI, database health, forwarded ports and all repo instructions. Distinguish an actual active Codespace/browser session from an Actions container build. Do not claim it is running simply because a devcontainer file exists.

The verified canonical application source belongs in `src/stockland-erp-app/`, materialized from the pinned exact .13 installer after per-file SHA-256 verification. Confirm `src/.stockland-erp-app.source.json`. If missing, use `bash scripts/codespaces/bootstrap_project_source.sh` after verifying archive intake. Stage the complete source tree and provenance marker in a distinct source-intake commit before functional edits. The importer preserves marked source edits and refuses to overwrite an unmarked directory. If the current Codespace contains a sample/scaffold or other user files, preserve and inspect them first; back up untracked/dirty work and never use `rm -rf`, `git clean -fdx`, hard reset or bulk replacement to force the importer through.

Run the actual Compose/devcontainer lifecycle; bring up the isolated WordPress and database profiles; check HTTP login, plugin activation, routes, PHP runtime, database connectivity and clean teardown. Fix environment failures in source/config, not by weakening validators. Use synthetic data only in isolated containers/Actions. Record commands, image versions, container health, ports, logs and run IDs. GitHub Actions can provide the MySQL/MariaDB matrix; do not leave multiple engines running in a personal Codespace when not needed.

## 7. Full integrated ERP scope — do not stop at activation smoke tests

Implement, preserve and verify the full product as one operational application. Map each workflow from frontend page/form → authenticated REST endpoint → capability/role/location/object ownership → existing domain service → transactional SQL → immutable ledger/audit event → reports/export. Enforce validation, idempotency, concurrency/row locking, atomic posting, safe retries, compensating reversals and failure recovery.

### Identity, company, access and command center
- One company: STOCKLAND ETHIOPIA.
- Five controlled locations: CENTRAL WAREHOUSE, GORO BRANCH, GERJI BRANCH, MEXICO BRANCH and MEGENAGNA BRANCH.
- Frontend-first ERP login and role-aware navigation; WP Administrator retains platform dashboard privileges. Ordinary operators work in the ERP interface, not WP Dashboard/editor.
- Owner/Management approval authority, strict capability/location scope, audit identity, secure sessions/nonces, no cross-branch leakage, deny-by-default tests.
- Command Center, notifications, global search, dashboards, analytics, print-ready reports and module-scoped health.

### Product, stock, warehouse and sales
- Product Master, brand/category/gender/age/department, SKU, size/color/variant/barcode, import and reconciliation.
- Central Warehouse is the controlling inventory point and wholesale source; User Warehouse Management for all authorized branch/warehouse managers.
- Receiving, transfers, requisitions, reservations, stock availability, branch receive/variance, supplier receipt, returns, damage, stock adjustment approval and physical counts uploaded via Excel.
- Retail POS and wholesale: multi-line cart, server-priced products, discounts/approval, reservations, returns/refunds, receipts, checkout, daily closing and stock/accounting posting.
- Ensure insufficient stock blocks safely and offers reservation/requisition alternatives where authorized. Preserve the source example and business rule; never invent inventory.

### Procurement, customers, vendors and financial accounting
- Customer/supplier masters and balances, purchasing/procurement lifecycle, goods receipt/invoice matching, expense capture, AP, AR, payment/receipt, cash/bank accounts, tax/VAT policy as approved, COGS, journal approvals, balanced GL/PLEDGER, reversals and period/report reconciliation.
- Use one ledger/accounting service; no duplicate subsystem or direct edits to posted history.
- Test precision, currency/ETB arithmetic, idempotency, duplicate invoice/payment protection, concurrent transactions, audit sequence and report-to-ledger reconciliation.

### Full-cycle HR, payroll and employee privacy
Deliver the actual full lifecycle, not a payroll calculator demo:
- Workforce/recruitment, position/job description, candidate/selection, contract and document generation, offer/onboarding, employee master and employment history.
- Attendance/time sheets, work schedules, overtime, leave/absence, performance/discipline where specified, benefits and effective-dated compensation changes.
- Payroll preparation for a period; compensation eligibility/classification; draft → review → independent approval → finalization; payslips; payment batch; bank/cash payout recording; tax/pension/statutory remittance; GL/PLEDGER posting; corrections, reversal, reconciliation, month/period closing and offboarding/final settlement.
- Test two consecutive payroll periods including hires, exits, salary changes, leave/absence, overtime and approval separation using synthetic employee fixtures only. Verify historical period snapshots do not change when current employee data is later edited.
- Implement and prove maker/checker: preparer cannot approve own item; no implicit approval on upgrade; retain approver identity/time/reason; null/missing/legacy states fail closed. Test production REST rejects any client flag that attempts to enable a synthetic/test calculator path.
- The private HR file vault must encrypt document bytes at rest (e.g. AES-256-GCM), manage keys outside the repository/web root, validate configuration, support key identifiers/rotation/recovery, enforce document-level role/location/object authorization, log downloads, and backup/restore ciphertext plus key recovery process. Do not enable private HR document operations if encryption/key setup is absent or unsafe.
- Never invent Ethiopian tax, pension, social security, payroll or employment-law rates, thresholds, exemptions, rounding or legal interpretation. Require a qualified current Ethiopian payroll/statutory reviewer and approved effective-dated policy bundle. In the absence of approved rules, calculations/finalization/payment/remittance/posting must fail closed, but the rest of ERP must continue.

### SOPs, resources and presentation
- Preserve all 24 Amharic-first SOP document payloads and hashes. Every item remains `DRAFT_NOT_APPROVED` until process-owner, Owner, HR, Finance and qualified legal/statutory approvals as applicable.
- Preserve private resource, job description, employment contract and policy download/edit/save workflows; access, document version and audit trails.
- Continue the accepted premium SaaS design, responsive mobile/desktop command center, Bootstrap/CSS modules, polished cart/finance/HR/payroll layouts, keyboard accessibility, print/PDF/Excel reports, PWA update behavior. Use the standalone UI repo for design reference only, not runtime implementation.
- No fake products/customers/employees/KPIs/business transactions in real app screens. Synthetic fixtures must clearly be test-only and absent from release data.

## 8. Critical open defects and mandatory closure evidence

Carry forward these findings until exact tests prove each is closed:

1. **P0 compensation migration/approval:** historical .13 source added missing `approval_status` using `DEFAULT 'APPROVED'`, while fresh DDL used DRAFT and selector logic coalesced NULL to APPROVED. Review merged PR #61 (`ad127ae8f672243b4d5bd7bd703e6104beef995a`) and prove the safe DRAFT/NULL behavior exists in the .13→.15 path on both DBs. Preserve approver, timestamp and reason; reject self-approval.
2. **P0 private HR vault:** original .13 attachment payload was plaintext. Verify the exact current candidate's crypto, keyring/site-managed key outside webroot, rotation/recovery, authorization, encrypted backups and no plaintext fallback.
3. **P1 schema governance:** historical source key counts differ (29 core + 16 accounting + 4 legacy HR + 37 HR lifecycle keys); 49 is the protected legacy schema subset, not permission to impose an arbitrary count. Inventory actual DDL/columns/defaults/nullability/indexes/FKs and rows on disposable .13 before/after full setup. Produce an approved additive ERD and rollback. Preserve four legacy HR tables and all posted history. Never hardcode a 49/86/119 count as schema authorization; never DROP/TRUNCATE/RENAME/recreate/reset.
4. **P1 MySQL/MariaDB:** the earlier MySQL 8 failure involved nullable `variant_id` in composite `wp_stock_balances` primary key. Test fresh and upgrade migrations, dbDelta replay, constraints/indexes, variant mapping, legacy balances, duplicate/concurrent writes, idempotency and rollback on MariaDB 10.11 and MySQL 8.0.46.
5. **P1 synthetic payroll:** production must not run fake/sample payroll; fixtures only in test profile, with API negative tests for attempted enablement.
6. **P1 pension defaults:** inspect every call site, migration and UI use of historical 7%/11% pension constants; preserve historical values without treating them as current default law. Use approved, effective-dated rules only.
7. **P1 status isolation:** payroll/schema/release review should block only the affected module/action; do not globally shut down unrelated approved operations. Genuine DB/ledger integrity issues still require a visible system-wide warning.
8. **P1 package/provenance:** final package must include README, exact release manifest, source commit/tree, dependency/license inventory, SBOM, build receipt, workflow evidence, file manifest and independently checked ZIP SHA sidecar. One final source SHA and one exact generated artifact identity must match.
9. **P1 SOP/statutory/acceptance:** all 24 SOPs remain draft; qualified Ethiopian payroll/legal acceptance and business-owner signoff remain mandatory.
10. **P0 complete runtime acceptance:** setup wizard, schema, roles and locations, operational/business transactions, finance reconciliation, full HR/payroll cycles, PWA/browser, backup/restore and rollback are still outstanding beyond the current container/plugin smoke test.

## 9. Incremental implementation and commit mandate

Follow the controlling Notion decision and `AGENTS.md`. Every coherent target must have:
1. A linked requirement/finding, short implementation plan, source files, business invariants, authorization, schema/data impact, failure/retry behavior and rollback.
2. A regression test added before or with the fix.
3. The smallest compatible implementation reusing existing Stockland services.
4. Local targeted tests, then applicable cumulative regression suites.
5. A descriptive commit to a feature/integration review branch; no direct-to-main push.
6. An exact-head PR/CI run; fix failures in source/tests and rerun the failed plus related regression checks.
7. Exact changed files, source lineage, commit and parent SHA, package SHA, logs, test output, schema snapshots, build/manifest/SBOM evidence and current blocker list.
8. A Notion evidence update after the Git commit and exact checks. Do not mark a draft, legal review, acceptance or approval complete without the authorized decision.

Do not use an all-at-once refactor that discards working services. Commit by business target across the Core/access, data/schema, inventory/POS, warehouse, procurement/finance, HR/payroll, private vault/SOP, responsive UI/report/PWA, observability/security, installer and recovery workstreams. The complete delivery should be one cumulative product, with a documented migration guide and backward-compatible upgrade path from .13.

## 10. Required test and release gates

Run against exact source and exact installer, using PHP 8.3/8.4 and both MariaDB 10.11/MySQL 8.0.46 as applicable:
- package identity, checksum sidecar, ZIP CRC, root/path/symlink/duplicate/path-traversal audit, full source manifest;
- all PHP lint, JavaScript/JSON, CSS/PostCSS parse, module order/rebuild and minification equivalence;
- PHPCS/WPCS with full unsuppressed reports, Semgrep/CodeQL/SAST, Gitleaks/secrets, dependency vulnerability/license scan and SBOM;
- actual devcontainer up/post-create/Compose health and WordPress install/activation/repeat activation/update/REST/login/route contracts;
- advance full ERP setup wizard in disposable environments, inspect exact schema and data reconciliation, test migrations/replay/failure/retry/rollback;
- authenticated positive and negative capability/role/location/object tests, including no self-approval and no cross-branch access;
- persisted operational workflows for all modules; assert stock counts, balances, audit/ledger postings and reports reconcile after transactions, retry, concurrency and rollback;
- full HR/payroll lifecycle and two consecutive periods with synthetic employees; independent maker/checker, payslip, payouts, statutory remittance, GL posting, reversal and period close; no legal rules assumed;
- HR encrypted vault and key recovery/rotation/backup restore; PWA/browser/mobile/a11y/performance/print/export tests;
- clean .13 install and true .13→final-candidate upgrade; final exact package re-installed and tested in a fresh isolated site; backup restore and exact rollback rehearsal.

Every result must be classified separately as **PASS**, **FAIL**, **BLOCKED**, **NOT RUN**, or **OWNER INPUT REQUIRED**, with commit SHA, package hash, DB engine/version, workflow run/job IDs and artifact/log references. A green container build, package lint, activation or pre-schema REST test is only a limited result, not ERP production acceptance. Do not suppress a failing check, convert skipped to passed, or claim full readiness from a partial run.

## 11. First actions for the current agent session

1. Read this note, root `AGENTS.md`, the delivery skill, the three-month history reconciliation and the incremental target directive; fetch the latest controlling Notion pages.
2. Refresh all repository heads, branch protection/PR ancestry, current #80–#83 state and exact Actions jobs. Identify the latest PR #83 E2E result and the candidate artifact SHA produced by it; do not use stale PR body text where exact source/run disagrees.
3. Inspect the running Codespace and worktree. Verify the actual container; confirm the canonical editable .13 source, marker and complete manifest. Preserve all sample, dirty and untracked material before importing.
4. Finish the source/history integration register, including read-only historical repos and Library/Notion inputs. Do not start a parallel ERP scaffold.
5. Advance the isolated ERP setup wizard after the currently passing .13→repaired-.15 smoke test; inspect the real table/row schema and safely test the full migrations on both engines.
6. Implement and test the P0 compensation approval and private HR vault defects, then proceed through module workflows and the full-cycle payroll target in incremental commits.
7. Commit each target and its exact evidence to the canonical repository/PR promptly; continue without waiting for the whole project to finish.
8. Update this note/Notion with verified commits, CI and exact package facts. Ask for Owner decisions only at genuinely Owner-controlled/legal/production boundaries.

**Non-negotiable outcome:** one complete Stockland ERP web application that preserves the verified .13 operational source and integrates the compatible .15 and historical project work; real containerized, repeatable install/upgrade and end-to-end tests; complete inventory, retail/wholesale, finance and HR/payroll flows; secured private HR resources; exact reproducible installer and evidence. Do not call it production-ready until every required technical, data, statutory, stakeholder and Owner acceptance gate is actually satisfied.
