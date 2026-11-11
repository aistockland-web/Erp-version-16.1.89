# FIRST PROMPT — STOCKLAND ERP FINAL FULL-SCOPE DELIVERY

You are continuing the existing Stockland Ethiopia ERP project. The Owner's objective is fixed: deliver one complete, cumulative, secure, production-grade WordPress ERP web application/PWA with all three months of compatible work preserved, one-click installable plugin ZIP, reproducible Codespaces/container build, exact package provenance, and full end-to-end acceptance. Keep this objective as the only project goal. Do not propose a greenfield rewrite, demo, alternate product, lower-scope substitute, or another version-line restart.

## Mandatory reading

1. Read repository-root `AGENTS.md`.
2. Read [the final Codespaces and one-click release runbook](engineering/STOCKLAND_ERP_FINAL_CODESPACES_ONE_CLICK_RELEASE_RUNBOOK_2026-11-11.md) and [the Free-plan single-Codespace/Codex setup](engineering/CODEX_CODESPACES_SETUP_2026-11-11.md).
3. Read [the three-month history handover](engineering/STOCKLAND_ERP_THREE_MONTH_HISTORY_RECONCILIATION_AND_PRE_CODESPACE_HANDOVER_2026-11-05.md) and [incremental delivery directive](engineering/STOCKLAND_ERP_INCREMENTAL_TARGET_COMMITS_AND_PRODUCTION_COMPLETION_2026-11-05.md).
4. Read the latest Notion records linked from AGENTS.md: 9 Nov cumulative deep audit, 11 Nov dual-version Codespaces/E2E, 11 Nov next execution target, and full HR/payroll requirements.
5. Refresh live GitHub branches, ancestry, PRs, exact workflow runs/jobs/artifacts, package locks and branch protections. The last verified successful E2E base is PR #83 head `cc2bde441022a0ee11df76756df22c10bdc6d910`; this is a historical anchor until refreshed.

## One canonical product and source line

- Canonical repository: `aistockland-web/Erp-version-16.1.89`.
- Operational/upgrade baseline: 16.2.13; SHA-256 `74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13`.
- Selected update candidate: 16.2.15; original archive SHA-256 `8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df`.
- PR #83's CI repairs one missing PHP opening tag into a distinct .15 test package. Its repaired package identity is separate; never relabel it as the unchanged original ZIP. Persist the repair in canonical source/build logic, regenerate the release ZIP from the final source commit, then verify a new final hash.
- 16.1.95 is rollback/reference only. 16.2.9 and HRX lines are selective source references, not wholesale replacement packages.
- The actual editable application must be `src/stockland-erp-app/`, materialized from the exact .13 archive and accompanied by `src/.stockland-erp-app.source.json`. Do not code in a sample app, temporary extraction or disposable WordPress copy.

## Your first session actions

Run in the actual Codespace terminal and capture outputs without secrets:

```bash
pwd
git remote -v
git status --short --branch
git rev-parse HEAD
git log -8 --oneline --decorate
git branch -vv
php -v
php -m
composer --version
node --version
python3 --version
docker version
docker compose version
wp --info
```

Preserve all uncommitted/untracked work. Never hard-reset, force-push, run `git clean -fdx`, replace an existing user folder or delete an existing source directory to make setup pass. Fetch the current delivery PR branch and use it as the working branch after preserving existing work. Then run:

```bash
bash .devcontainer/initialize-host.sh
bash .devcontainer/post-create.sh
git status --short --branch
test -f src/.stockland-erp-app.source.json
test -f src/stockland-erp-app/stockland-inventory.php
```

The current post-create flow creates or verifies the separate repaired .15 QA candidate, validates exact archive identity, imports the verified .13 editable source without overwriting existing user edits, and bootstraps isolated MariaDB WordPress sites. Start the optional MySQL 8.0.46 profile and test path when needed:

```bash
bash scripts/codespaces/run_mysql8_parity.sh
```

Database port 3306 stays internal; browser preview ports stay private. Use only disposable synthetic data. Do not load production credentials, backups, employee/customer/supplier records, real payroll, HR attachments or live ledger transactions.

## Continue from the actual state — not from a chat summary

After preflight, inventory the real source tree, all three-month project commits/branches/PRs, current exact CI results, existing tests, migration code, REST routes, app pages, services, schema and source provenance. Build or update the History & Integration Register. Map useful work to files and tests; retain/port/reconcile it with provenance. The standalone `aistockland-web/Erp` UI repository is a static design reference, not an ERP runtime. The older upstream and bot mirror are historical source references. Do not lose useful existing work or merge branches wholesale.

Start with the immediate next target in the Notion execution record: advance the real setup wizard on isolated/restorable .13 baseline and .13→repaired-.15 upgrade databases on MariaDB 10.11 and MySQL 8.0.46. Capture resumable before/after SQL exports, exact table/column/index/FK fingerprints, row counts and migration receipts. Reconcile schema design with the latest explicit Owner decision; do not activate unapproved tables or hard-code a table count as permission. Preserve the legacy HR/payroll source and all posted records until dependency/data-health, migration, reconciliation and rollback evidence is complete.

## Full product delivery scope

Complete the integrated application across authentication, frontend-first Command Center, role/capability/location authorization, Product Master/SKU/brand/size/color, Central Warehouse and four branches, User Warehouse Management, receiving, requisitions, reservations, transfers, branch receipt/variance, POS/cart/checkout/refund, wholesale, returns, damage, adjustments, physical counts, daily close, Excel import/export, supplier/customer, procurement, AP, AR, expenses, cash/bank, VAT/COGS, journals, GL/PLEDGER, reporting/printing, notifications, audit/history, SaaS-responsive UI, accessibility, PWA and secure operations.

Complete full HR lifecycle: workforce planning, recruitment, selection, contract, onboarding, employee history, attendance/shift/overtime, leave, promotion/grade, effective-dated compensation and benefits, payroll period/input/calc, review, independent maker-checker approval, payslips, payment batch, remittance, GL/PLEDGER posting, corrections/reversals, reconciliation, period close, offboarding and final settlement. Test two consecutive payroll periods with synthetic fixtures only. Preserve legacy posted payroll and finance history. Do not invent Ethiopian law/tax/pension rates; use an approved effective-dated rule register with qualified Ethiopian reviewer and Owner/HR/Finance approval. Keep production finalization/posting fail-closed until authorized rules exist.

Secure the HR vault with authenticated encryption at rest, site-managed key material outside repository/web root, key identifiers/rotation/recovery, document-level permissions, access/download audit and encrypted backup/restore. Preserve the 24 Amharic-first SOPs and their actual approval state; do not relabel drafts as approved.

## Problem-solving instruction

When any defect appears, keep the project goal unchanged. Reproduce the exact failure, identify the exact commit/file/runtime/database/route, read the job log and source, fix the root cause in the application, migration, test, or container configuration, add a focused regression, run local checks, then rerun the exact affected CI jobs and cumulative regressions. Commit the fix. Do not only describe the problem, bypass the test, suppress the warning, weaken assertions, switch products or advise restarting the project.

Use WordPress Coding Standards, secure PHP/SQL patterns, OWASP-aligned access/session/data handling, WCAG accessibility, responsive/browser interoperability, transactional/idempotent data operations, privacy by default, immutable audit/history, reproducible builds, dependency/license inventories and SBOM. These are engineering quality requirements, not claims of external certification.

## Incremental commit and review loop

For each coherent target: link the Notion requirement; write a short plan; name source files, data/API/authorization impact, migration/rollback and tests; implement; run targeted and cumulative checks; commit to a descriptive branch; push; open/update a focused PR; attach exact SHA, package hashes, reports and workflow IDs; update Notion; continue to the next target. Never push directly to main or rewrite protected history. Use the connected repository permissions directly for normal engineering work; do not ask the Owner to approve routine reads, edits, tests, commits or review PRs. Ask only for genuinely Owner-controlled access, business/legal decisions, destructive live-data changes or final production release/deployment authorization.

## Final one-click installer outcome

Build one complete WordPress plugin ZIP from the final canonical commit. It must have one plugin root, valid plugin header, all runtime assets/templates/migrations/resources, correct activation/update path, no development dependencies or credentials, no sample records, no duplicate plugin/engine and no missing required file. Generate release manifest, file inventory, dependency/license inventory, SBOM, build receipt, source commit/tree, workflow IDs and independent SHA-256/sidecar. Upload it as a downloadable CI artifact for review; after full exact-package and business acceptance, obtain Owner publication/deployment authorization. A normal WordPress user should be able to install the final ZIP through Plugins → Add Plugin → Upload Plugin → Install → Activate, then complete the documented first-run setup.

Do not declare production readiness until the exact final source/package, full database and business workflows, security, payroll/legal, backup/restore/rollback, browser/PWA, stakeholder and Owner acceptance are evidenced. Continue engineering GO throughout; keep each technical/legal gate visible and close it with an actual fix or required approval.

**This is the sole delivery objective. Start from the current repository/Codespace state, fix forward, commit incrementally and finish the complete Stockland ERP.**


## Current one-Codespace create link and schema control

Create only one Codespace from the branch linked in README.md after checking remaining Free quota. Follow docs/engineering/CODEX_CODESPACES_SETUP_2026-11-11.md. Recommended minimum is 2 cores / 8 GB / 32 GB, with one local MariaDB profile and MySQL 8.0.46 in GitHub Actions. Do not enable paid overage, Codex Cloud, API keys, marketplace plugins or duplicate Codespaces.

Notion Target 6 is the current Owner schema boundary: protect the 49-key legacy operational baseline; keep 37 HR lifecycle tables and 33 HRX keys separate and unapproved; no automatic lifecycle DDL or existing legacy HR ALTER without exact written Owner approval; do not globally block non-HR operational routes; payroll finalization remains fail-closed without effective-dated qualified review.
