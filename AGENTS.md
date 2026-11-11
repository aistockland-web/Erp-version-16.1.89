# Stockland Ethiopia ERP — Master Agent Engineering Order

**Repository:** https://github.com/aistockland-web/Erp-version-16.1.89  
**Current base:** 1c3b743296b7a1f4e3b8a0b481e6883b5fed4154 (10 November 2026)  
**Working branch:** integration/stockland-erp-16213-to-16215-codespaces-2026-11-11  
**Operational baseline:** 16.2.13  
**Selected review candidate:** 16.2.15  
**Production disposition:** NOT APPROVED pending exact-package gates and Owner acceptance.

## 1. Authority and mandatory project rules

Notion is authoritative for business requirements, architecture decisions, approval authority, findings and acceptance. GitHub is authoritative for repository source, commit ancestry, pull requests, workflows, artifacts and exact package bytes. A chat summary, version number, ZIP filename, green workflow or SHA-256 alone is not approval.

Read and reconcile these populated controlling records before each implementation target or release decision:

- [16.2.13 Cumulative Deep Audit & Release Reconciliation — 9 Nov 2026](https://app.notion.com/p/4136db0f767e81ee87cec44eac9c4340)
- [Codespaces Recovery & Cumulative ERP Execution Plan — 7 Nov 2026](https://app.notion.com/p/4106db0f767e8119a322ff39cf928763)
- [Cross-Chat GitHub/Notion Release & CI Reconciliation — 7 Nov 2026](https://app.notion.com/p/4106db0f767e8182a599e5ea5104c3f4)
- [Canonical Release Audit & Acceptance Gates](https://app.notion.com/p/3fc6db0f767e81f2a3bbe644048054f9)
- [Incremental Target Commits and Full Production Completion Directive](docs/engineering/STOCKLAND_ERP_INCREMENTAL_TARGET_COMMITS_AND_PRODUCTION_COMPLETION_2026-11-05.md)
- [Three-month history and pre-Codespaces handover](docs/engineering/STOCKLAND_ERP_THREE_MONTH_HISTORY_RECONCILIATION_AND_PRE_CODESPACE_HANDOVER_2026-11-05.md)

The separately supplied Notion URL ending 4146db0f767e807da536de70a3471ca0 resolved to a blank, unverified page. Do not treat it as the strict work rules page. Use the populated records above. Notion agent-session search is unavailable to this connector; do not claim that every private chat transcript has been recovered.

## 2. Current source-line decision — version numbers do not prove ancestry

| Source | Identity / role | Treatment |
|---|---|---|
| 16.2.13 | SHA-256 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13; 2,188,684 bytes; 113 ZIP entries; 80 PHP files | Designated operational/upgrade baseline. Preserve cumulative POS, inventory, finance, CSS 07–13, templates, SOP payloads and uninstall data-preservation behavior. |
| 16.2.15 | SHA-256 8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df; archive and sidecar added by 6dd5263865cf2d6e4f64c133b9a3ff7212e60f16 | Selected review candidate only. The archive appeared after the 9 Nov Notion audit, which had said no .15 ZIP was available. That historical statement is superseded by the 10 Nov Git commit. Exact ZIP and update path still require fresh validation. |
| 16.1.95 | SHA-256 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625 | Protected rollback/comparison reference. Never install over a later operational baseline. |
| 16.2.9 | SHA-256 c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01 | Selective HR/payroll source only; do not wholesale overlay onto .13. |
| 16.3.0 HRX | SHA-256 f48993499e7664c45a079595af8efedbd5881728952cf019428f5db2d1872412 | Non-cumulative side line from .95. Selective comparison only; proposed schema is not approved. |

Relevant GitHub history:
- 1c3b743296b7a1f4e3b8a0b481e6883b5fed4154 adds the .13 intake QC receipt, package file manifest, archive and checksum.
- 6dd5263865cf2d6e4f64c133b9a3ff7212e60f16 adds the .15 review-candidate archive and checksum.
- ad127ae8f672243b4d5bd7bd703e6104beef995a is merged PR #61: DRAFT-safe compensation approval and NULL/unassumed pension-rate protections. Prove that exact correction exists in .13/.15 before claiming it is retained.
- c1fdf5bf3ece55b3810ec3bfc847a502ce68d006 merges HRX source/docs, not an approved HRX release or schema.
- PR #77 is an open, documentation-only Owner correction confirming .13 as operational baseline.
- PR #67 is the earlier Codespaces configuration branch. Its successful workflow validated JSON/shell/Compose/PHP image only; it did not start WordPress or exercise an ERP update.

The .13 archive is tracked as STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip while its sidecar retains the original logical filename without the Git (1) suffix. Treat this as a repository filename alias only. Pin and verify the bytes; do not silently rebuild or rename the archive.

## 3. Deep audit findings that remain release blockers

Carry these forward; do not erase them because a newer ZIP exists:

- **P0 compensation approval migration:** .13 was found to add a missing approval_status with DEFAULT 'APPROVED' on upgrade while fresh-install DDL used DRAFT; a selector also treated NULL as approved. Inspect .15 and prove the PR #61 correction on fresh install and upgrade, retaining approver/time/reason and rejecting self-approval.
- **P0 HR private vault:** .13 stored HR attachment bytes in plaintext. Later source-only remediation reported AES-256-GCM, but the actual candidate must prove keys, rotation, recovery, authorization and encrypted backup. Never enable sensitive document flows without E2E evidence.
- **P1 schema governance:** source enumerators, schema comments and historical counts conflict (49 legacy contract vs 86 keys and optional HRX proposals). Do not create/drop/truncate/rename/reset or migrate tables from a hard-coded count. Require exact DDL mapping, sanitized staging schema inventory, approved ERD, row reconciliation, migration and rollback.
- **P1 MySQL compatibility:** a prior disposable MySQL 8.0.46 test failed on nullable variant_id in a composite primary key in wp_stock_balances. MariaDB passing did not close it. Test clean install, upgrade, dbDelta replay, FK/index, variant identity, legacy no-variant rows and rollback on both DB engines.
- **P1 synthetic payroll:** keep synthetic fixtures test-only; production callers must fail closed and must not accept client switches that enable synthetic calculations.
- **P1 statutory payroll and SOPs:** do not invent tax/pension rates, employee amounts or legal interpretation. The 24 Amharic-first SOP payloads are DRAFT_NOT_APPROVED and are not legal authority. Obtain Owner, Operations, HR, Finance and qualified Ethiopian legal/statutory review.
- **P0/P1 operational acceptance:** role/location allow-deny, POS/cart concurrency/retry, inventory and ledger reconciliation, AP/AR/GL/PLEDGER, payouts/remittance, two payroll periods, encrypted backup/restore, rollback, browser/PWA and stakeholder acceptance remain open until exact evidence exists.

## 4. Stockland product invariants

Build one integrated WordPress ERP/PWA for STOCKLAND ETHIOPIA. Preserve frontend-first operations and the five controlled stock locations:

- CENTRAL WAREHOUSE
- GORO BRANCH
- GERJI BRANCH
- MEXICO BRANCH
- MEGENAGNA BRANCH

Preserve Command Center; retail/wholesale POS and cart; Product Master/SKU/brand/size/color; central and user warehouse management; receiving; requisitions; reservations; transfers; branch variance; returns; damage; adjustment approvals; physical counts; daily close; Excel import/export; reporting and print; user/role/location access; supplier/customer balances; procurement; AP/AR; expenses; cash/bank; GL/PLEDGER; HR/payroll; private resources; SOP/job-description/contracts; immutable audit/ledger/history; health and online-first PWA.

Server-side capability, role and location authorization is mandatory. Keep one Stockland company/data boundary. Central Warehouse is first-class inventory control and wholesale source. Do not introduce a competing inventory, accounting, POS or authorization engine.

## 5. Every target follows the strict incremental commit rule

For every plan, task, change and commit:

1. Refresh main, working branch, PRs, ancestry, Actions and package identities before editing. Preserve existing work; never force-push or rewrite history.
2. Link the target to the controlling Notion requirement/finding/decision. Record a focused plan, affected files, invariants, schema/data risk and tests before changing runtime behavior.
3. Recover and compare exact source packages. For each changed module record origin commit/path, dependency, business behavior, schema impact, tests and decision (retain/port/reconcile/reject).
4. Implement one coherent target with tests and migration/rollback where applicable. Do not leave code only in Codespaces, a ZIP, chat or local worktree.
5. Commit to a descriptive branch and open/update a focused PR. Never push directly to main. Preserve exact parent/head SHAs.
6. Run local checks first and exact-head GitHub Actions. Fix reproducible findings in source/tests, commit corrections separately and rerun affected plus cumulative regressions.
7. Attach logs, reports, ZIP inventory, SHA-256, build receipt, dependency/SBOM evidence, schema fingerprints and statuses to PR/Notion. Update Notion only with verified facts; never change an approval state without authorized Owner action.
8. Continue the next target. Do not wait to commit completed targets until the entire ERP is finished.

Required sequence: source archaeology; baseline recovery; schema/data map; MySQL/MariaDB migrations; POS/inventory/warehouse; finance/procurement; HR/payroll/private vault; SOP/resource acceptance; SaaS UI/reports/PWA; security/performance/observability; build/installer; backup/restore/rollback; final Owner acceptance.

## 6. Codespaces and test safety

The Codespace is isolated engineering/QA only. Use synthetic WordPress data. Never load production credentials, backups, employee/customer/supplier records, payroll data or business documents.

The devcontainer includes PHP 8.3, Node 22, Python 3.12, Composer, WP-CLI, locked PostCSS/PHPCS tooling, WordPress Apache/PHP and separate MariaDB 10.11/MySQL 8.0.46 services. Ports 8080/8081 are private previews. Database 3306 must not be published or forwarded. Test credentials are disposable and Git-ignored.

The bootstrap installs .13 first and exercises the .15 package update on both database engines. This is a candidate test, not approval for live installation. Run further targeted tests before claiming functional acceptance.

Useful commands:

~~~bash
git status --short --branch
git rev-parse HEAD
python scripts/codespaces/verify_candidate_archives.py --report artifacts/codespaces/package-intake.json --extract-root /tmp/stockland-candidates
bash scripts/codespaces/bootstrap_wordpress.sh
docker compose -f .devcontainer/docker-compose.yml config --quiet
~~~

Never copy production data into test volumes. Do not run destructive migration, synthetic payroll, failure injection or fake business transactions against the live company database.

## 7. Status and release contract

Report each check as exactly one of:

- **PASS:** completed against the exact named commit/package.
- **FAIL:** completed and failed.
- **BLOCKED:** prerequisite or permission prevented execution.
- **NOT RUN:** no evidence it executed.
- **OWNER INPUT REQUIRED:** business policy, source archive, legal interpretation, key, destructive decision or authorized approval is missing.

Every change report states branch, exact HEAD and parent SHAs, changed files, commit/PR links, test commands/results, CI run IDs, package SHA/size, open findings and next target.

SHA-256 is byte integrity, not signature, approval or deployment proof. A green workflow proves only the checks it ran. No production tag/release, signed certificate, deployment or production-ready claim until applicable exact-package gates pass, legal/business approvals exist and Owner explicitly authorizes publication/deployment.
