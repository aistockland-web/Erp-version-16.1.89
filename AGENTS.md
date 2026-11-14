# Stockland Ethiopia ERP — Master Engineering Order

**Current engineering record:** [Dual-Version Codespaces and WordPress E2E Execution — 11 November 2026](https://app.notion.com/p/4156db0f767e81458a0edb560a998e53)

**Canonical GitHub repository:** https://github.com/aistockland-web/Erp-version-16.1.89

**Controlling Notion decisions:**
- [Stockland ERP — 16.2.13 Cumulative Deep Audit & Release Reconciliation — 9 Nov 2026](https://app.notion.com/p/4136db0f767e81ee87cec44eac9c4340)
- [Codespaces Recovery & Cumulative ERP Execution Plan — 7 Nov 2026](https://app.notion.com/p/4106db0f767e8119a322ff39cf928763)
- [Cross-Chat GitHub/Notion Release & CI Reconciliation — 7 Nov 2026](https://app.notion.com/p/4106db0f767e8182a599e5ea5104c3f4)
- [Canonical Release Audit & Acceptance Gates](https://app.notion.com/p/3fc6db0f767e81f2a3bbe644048054f9)
- [Full-System Deep Audit & Corrective Action Plan — 24 Oct 2026](https://app.notion.com/p/4036db0f767e8189ac2ed3f0548bbe02)
- [ERP-wide Deep Audit & 24 Amharic SOP Library — 29 Oct 2026](https://app.notion.com/p/4076db0f767e815eba7cc49314197c22)
- [Project-wide Engineering GO and Module-scoped Readiness — 6 Nov 2026](https://github.com/aistockland-web/Erp-version-16.1.89/blob/main/docs/engineering/STOCKLAND_ERP_PROJECT_WIDE_GO_AND_MODULE_SCOPED_READINESS_2026-11-06.md)

This is a cumulative ERP recovery, integration and production-completion assignment. Do not restart the product, treat the latest version number as the whole truth, or replace working subsystems with an unverified candidate. The objective is one coherent Stockland Ethiopia WordPress ERP/PWA that preserves the best compatible source, UI, workflows, business rules, finance/inventory ledgers, HR resources, SOPs, tests, build tools and security controls from the project's full history.

## 1. Mandatory Notion work rule — every plan and target commit

Notion is authoritative for business requirements, architecture decisions, Owner direction, audit findings and acceptance. GitHub is authoritative for source code, commit ancestry, branch/PR state, Actions, artifacts and exact file bytes.

Before every new implementation target:
1. Fetch the current controlling Notion release decision, the 9 November 16.2.13 cumulative audit, the 7 November Codespaces plan and cross-chat reconciliation, and any module-specific decision related to the target.
2. Search the Notion workspace for newer corrections that supersede older decisions. A page's edit date or historical wording does not by itself change the approved baseline; read its actual decision and evidence.
3. Create or update a durable Notion execution record before the target commit. Record target scope, exact source/base SHA, applicable decision links, files expected to change, acceptance commands and safety boundaries.
4. Implement one coherent target. Commit it on a dedicated review branch, never on the live installation or by force-updating protected history.
5. Update the same Notion record immediately after the commit with the exact commit SHA, PR link, Actions run IDs, artifacts/hashes and PASS/FAIL/BLOCKED/NOT RUN/OWNER INPUT states.
6. If a new finding changes scope, update the plan before the next commit. Never report planned work as implemented, or a green environment workflow as production certification.

The Notion agent-session search capability is not available under the current workspace entitlement. Use accessible Notion pages, repository history, saved context, source files and Library artifacts. Explicitly record unavailable transcripts or packages; never claim every past private chat has been recovered.

## 2. Required three-month project continuity

The historical project window is 5 August–5 November 2026. The primary repository handover is:

- docs/engineering/STOCKLAND_ERP_THREE_MONTH_HISTORY_RECONCILIATION_AND_PRE_CODESPACE_HANDOVER_2026-11-05.md
- docs/engineering/STOCKLAND_ERP_PREIMPLEMENTATION_VERSION_ARCHAEOLOGY_AND_SOURCE_RECONCILIATION_2026-11-05.md
- docs/engineering/STOCKLAND_ERP_INCREMENTAL_TARGET_COMMITS_AND_PRODUCTION_COMPLETION_2026-11-05.md
- docs/engineering/STOCKLAND_ERP_AGENT_AUTONOMY_CI_CLI_AND_BUILD_EXECUTION_2026-11-05.md
- docs/engineering/STOCKLAND_ERP_PROJECT_WIDE_GO_AND_MODULE_SCOPED_READINESS_2026-11-06.md
- docs/engineering/STOCKLAND_SAAS_ENGINEERING_BLUEPRINT_2026-10-29.md

Read these before product edits. Refresh main, all useful branches, merge ancestry, PR discussions/reviews, reverts, current workflow runs/jobs/logs, artifacts and Notion decisions. Inspect code and package bytes; never infer implementation from a PR title, branch name or version label.

For the current STOCKLAND ETHIOPIA runtime, preserve the full product scope: frontend-first premium SaaS web ERP; one existing Stockland company; its Stockland-specific Central Warehouse and Goro, Gerji, Mexico and Megenagna branches; Product Master/SKU/brand/size/colour; POS/cart, retail and wholesale; reservations, availability and approval; receiving, requisitions, transfers, returns, damage, adjustments, physical counts and daily closing; procurement, supplier/customer masters, AP, AR, expenses, cash/bank, journals, GL/PLEDGER; role/capability/location enforcement; reports, print/Excel/PDF; notifications, audit, diagnostics and PWA; recruitment, employee lifecycle, time/leave, compensation, payroll, payout/remittance, private HR documents and controlled SOP/JD/contract resources.

The five seeded inventory locations in the current Stockland instance are CENTRAL WAREHOUSE, GORO BRANCH, GERJI BRANCH, MEXICO BRANCH and MEGENAGNA BRANCH. These are Stockland-specific deployment defaults, not new-customer defaults. Central Warehouse is a first-class inventory control point and wholesale source. Keep this instance's single-company stock/money ledgers, maker-checker authority, branch scoping, historical prices, audit history and all existing posted records.

The intended commercial product is a customer-neutral, multi-tenant Apparel Supply Chain platform. Separate customer-company identity, tenant isolation/provisioning, customer sites/factories/warehouses/branches and customer-specific users are future capabilities and are **not implemented** in the current 16.2.18 runtime. Do not describe this single-company Stockland installation as an already multi-tenant platform. Any future multi-tenant implementation requires its own explicit architecture, data-isolation, security and acceptance work.

## 3. Version-line and exact-package roles

Do not assume a linear version sequence. Compare actual packages and source ancestry.

| Version | Role | Required treatment |
|---|---|---|
| 16.2.13 | Owner-designated operational/upgrade baseline | SHA-256 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13; 2,188,684 bytes; 113 files; 80 PHP. Retain all later 16.2.x operational work, POS/cart, CSS modules 07–13, SOP payloads and uninstall data-preservation behavior. |
| 16.2.15 | Selected local review/update candidate | SHA-256 8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df; 2,206,932 bytes. Candidate only until its archive hash, contents, version header, WordPress activation, upgrade behavior and database compatibility pass exact tests. |
| 16.1.95 | Protected comparison/rollback reference | SHA-256 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625. Do not replace the later operational baseline with it. |
| 16.2.9 | Selective HR/payroll implementation ancestor | Use only after file-level comparison and tests; do not wholesale replace current operational services. |
| 16.3.0 HRX and 16.3.1 records | Divergent HR replacement/design/candidate work | No automatic schema approval or cumulative replacement. Selectively preserve compatible implementation after source/data/ERD review. |

Current GitHub source evidence includes:
- [16.2.13 intake and package evidence commit](https://github.com/aistockland-web/Erp-version-16.1.89/commit/1c3b743296b7a1f4e3b8a0b481e6883b5fed4154)
- [16.2.15 selected candidate package and SHA sidecar commit](https://github.com/aistockland-web/Erp-version-16.1.89/commit/6dd5263865cf2d6e4f64c133b9a3ff7212e60f16)
- [PR #77 — Owner correction establishing 16.2.13 operational baseline](https://github.com/aistockland-web/Erp-version-16.1.89/pull/77)
- [PR #67 — first Codespaces setup branch](https://github.com/aistockland-web/Erp-version-16.1.89/pull/67)

Those two commits add package/evidence records; they are not themselves application-code fixes. PR #77 is documentation-only; it confirms source roles but does not certify either candidate. PR #67 establishes a PHP/MariaDB workspace foundation but does not prove a running WordPress installation. Build on latest main, preserve those commits and PRs, and do not merge or overwrite their history.

The 16.2.13 archive is stored in main as STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip. Its historic SHA sidecar had a filename mismatch; the sidecar reference is corrected on the Codespaces target branch without changing package bytes. The package lock under ci/codespaces/candidate-packages.json is the exact source for automated integrity checks. Do not change a locked digest merely to make a job pass.

## 4. Codespaces and web application development environment

The Dev Container is Stockland-specific:
- PHP 8.3, Node.js 22, Python 3.12, Composer, GitHub CLI, WP-CLI (only after upstream SHA-256 verification), Docker CLI support and the repository's locked QA dependencies.
- MariaDB 10.11 isolated service and two disposable WordPress sites: baseline install of 16.2.13 and a separate 16.2.13 → 16.2.15 update/activation site.
- Optional MySQL 8.0.46 WordPress site that installs 16.2.13 and executes the same exact 16.2.13 → 16.2.15 upgrade path, including a disposable pre-update SQL snapshot and marker-preservation check.
- Private forwarded ports: 8080 baseline, 8081 upgrade, 8082 optional MySQL 8.
- Generated random credentials only in ignored local .env. Never add environment credentials or production tokens to Git.

Use the repository workflows and commands under .devcontainer/, ci/codespaces/, scripts/codespaces/ and .github/workflows/. Keep all DB ports internal. No production domain, DB, credentials, employee files, customer records, payroll, real ledger transactions or live plugin belongs in this Codespace.

The GitHub repo is the PHP WordPress ERP. The referenced github/dotnet-codespaces repository is only a general configuration reference. Never copy its .NET sample app, C# packages, sample database, or unrelated demo content into Stockland.

The `.13/.15` Codespaces candidate smoke installs the exact locked 16.2.13 baseline and repaired 16.2.15 candidate. Its observed route contract is `stockland/v16` with `/stockland/v16/setup/status` (GET), `/stockland/v16/setup/advance` (POST), and `/stockland/v16/app/access/bootstrap` (GET). The 12 November 2026 disposable WordPress run confirmed these routes on the baseline and upgrade sites; do not substitute unverified `stockland/v1/app/bootstrap` or `stockland/v16/system/ping` expectations. A WordPress HTTP 200 or a generic `namespaces` array is not proof of Stockland REST registration.

## 5. Non-negotiable business, schema and security invariants

- Current Stockland runtime: one company and one source of truth; enforce server-side branch/location/record/object scope on every REST action. Multi-tenant provisioning and tenant isolation are future work, not implemented in 16.2.18.
- Maker-checker approval; no self-approval; auditable approver identity, timestamp and reason.
- Fail closed for missing stock, unknown schema, failed queries, missing approval, invalid amounts, duplicate requests or incomplete evidence. Never substitute zero/empty success.
- Atomic/idempotent POS/cart, receiving, stock movements, reservation, transfer, payout and journal writes. Retry must not duplicate business effects.
- Use common money/decimal helpers and balanced accounting. Do not rewrite posted transactions, historical payroll, stock movements, journals or immutable audit records; use authorized compensating events.
- Preserve the protected 49-table legacy contract and all legacy HR/payroll data until the source-mapped ERD, dependency/data health, additive migration, backup/restore, rollback and Owner approval exist. Source code declarations or 73/86 table candidate counts are not schema approval.
- HR attachments must be private, encrypted at rest, key-managed, role/location/object-scoped and download-audited before enabling production use. Do not expose PII in logs or artifacts.
- No synthetic employee, salary, stock, sale, customer, payment or payroll transaction in live production. Synthetic fixtures only in isolated QA.
- Never invent Ethiopian PAYE, pension, exemption, overtime, leave or final-settlement policy. Require official effective-dated sources and qualified Ethiopian legal/payroll review plus Owner/HR/Finance approval.
- Preserve all 24 Amharic-first SOP payloads and their draft/approval state. A draft SOP is not legal authority. Do not fabricate a missing signed SOP, JD, contract, employee record or statutory source.
- Never add WooCommerce, third-party POS/inventory, duplicate auth/roles, paid AI extensions, paid subscription agents or overlapping systems without a documented approved gap and compatibility tests.
- Never disable/suppress PHPCS/WPCS, Semgrep, CodeQL, dependency/license/secret scanning, syntax tests or workflow jobs to obtain green CI.

A payroll-only, schema-only or release-only review finding must gate only the affected action. It must not globally disable unrelated POS, inventory, warehouse, finance, reporting or HR operations. Keep project engineering GO while reporting each candidate and module status separately.

## 6. Required target-by-target engineering workflow

For every target:
1. Read the latest Notion decisions and relevant source/audit documents.
2. Refresh GitHub main/branch/PR refs. Record base SHA, parent, worktree state and uncommitted files.
3. Write or update the Notion plan/target record before implementation.
4. Inspect the smallest relevant source slice and all dependencies. Preserve stronger prior code. No arbitrary rewrite.
5. Make one coherent application/build/CI target with a corresponding regression test.
6. Run local syntax, package and focused behavior checks. Then run exact-head CI and affected cumulative regressions.
7. Read every failed workflow job and log. Fix the root cause in code/test/environment; rerun affected suites. Do not hide failures or downgrade checks.
8. Commit on a dedicated branch with a descriptive conventional commit. Do not reset/force push protected history.
9. Open/update a focused review PR. Record exact files, SHA, CI IDs, artifact/hash and all open findings in the same Notion record.
10. Continue with the next target; do not stop at an audit or plan when safe implementation work is available.

Use evidence statuses exactly:
- PASS — the named check ran and succeeded on the exact source/package.
- FAIL — it ran and failed.
- BLOCKED — permission or prerequisite prevented execution.
- NOT RUN — no evidence it executed.
- OWNER INPUT — requires unavailable source/business resource, unresolved policy/legal interpretation, paid cost, production credential, destructive decision or release/deployment authorization.

## 7. Release, production and owner boundaries

A SHA-256 is an integrity identifier, not a signature or release approval. A passing CI job certifies only its exact named checks on its exact commit/package. Codespaces boot, WordPress test activation or a passing package smoke check is not full ERP production acceptance.

Do not merge a production release, tag/publish a GitHub Release, sign a certificate, install an update or deploy to the live site until the final cumulative installer passes all applicable gates and the Owner explicitly authorizes release/deployment. No production DB or live WordPress mutation from this agent.

The final product remains one cumulative Stockland WordPress ERP, one canonical source commit, one installable ZIP and one independently recomputed package SHA-256 with a full evidence bundle.


## 8. Owner's single final-delivery objective — mandatory for every agent/chat

Read `docs/AGENT_FIRST_PROMPT.md` first, then `docs/engineering/STOCKLAND_ERP_FINAL_CODESPACES_ONE_CLICK_RELEASE_RUNBOOK_2026-11-11.md`. These files make the Owner's fixed product goal and execution sequence explicit for every Codespaces, GitHub, Notion and other ChatGPT agent session.

The sole goal is one complete cumulative Stockland Ethiopia WordPress ERP/PWA, preserving the compatible work of the full 5 August–5 November 2026 project history and the November integration; one reproducible containerized development/QA environment; full authenticated end-to-end business workflows including the full HR/payroll lifecycle; one exact-source, one-click installable WordPress plugin ZIP; and one complete acceptance evidence chain. Do not start a greenfield/sample app, change framework/product, fragment the solution into competing plugins, reduce the agreed business scope, or restart from 16.1.95. Treat defects as root-cause engineering work: reproduce the concrete scenario, fix source/config/migration, add regression coverage, rerun exact CI and commit the repair.

The Owner authorizes routine in-scope repository investigation, code/config edits, isolated tests, focused commits, branch pushes and review PRs using current connected access. Do not ask for individual permission for ordinary file changes or commits. Never push directly to main, force-push, rewrite history or merge/release/deploy to production without the relevant explicit release authorization. Never put secrets or real business/employee data in code, logs, artifacts or chat.

## 9. Canonical delivery branch and Codespaces source intake — 11 November 2026

This delivery branch is based on the latest previously verified PR #83 exact head `cc2bde441022a0ee11df76756df22c10bdc6d910`. Refresh current refs before use. It carries the corrected container/E2E path plus:
- `docs/AGENT_FIRST_PROMPT.md` — copy-ready instruction for the next agent session.
- `docs/engineering/STOCKLAND_ERP_FINAL_CODESPACES_ONE_CLICK_RELEASE_RUNBOOK_2026-11-11.md` — detailed single-goal project and release runbook.
- `scripts/codespaces/verify_candidate_archives.py` and the safe .13 source materializer under `scripts/codespaces/bootstrap_project_source.*`.
- `src/README.md` describing the canonical editable application folder.
- `.github/workflows/stockland-final-delivery-preflight.yml` validating the source importer, exact archive manifest and rebuild-preservation behavior.

The Codespaces `.devcontainer/post-create.sh` now creates or reuses the separate repaired .15 QA archive/lock, validates the exact candidate, verifies the original .13 archive and materializes the editable .13 application at `src/stockland-erp-app/` with `src/.stockland-erp-app.source.json`. The repair changes only the audited PHP opening-tag defect in a separate test artifact; the original .15 ZIP remains byte-identical. A partial generated-artifact set is reported without deleting any files. A pre-existing unmarked source directory is never overwritten.

First boot and rebuild must preserve existing marked source edits and all unrelated user files. Confirm `git status` before/after post-create. The source tree is the canonical runtime edit target; do not edit the temporary extraction or installed WordPress QA copy as the source of truth. Make a separate source-intake commit from the exact manifest before the first runtime behavior fix.

## 10. Required next execution sequence

After Codespaces preflight and source intake, proceed to the full resumable Stockland setup wizard and physical schema/data verification on disposable MariaDB 10.11 and MySQL 8.0.46 databases. The successful PR #83 smoke run did not advance the ERP setup wizard; its 12-table receipt is not full ERP schema acceptance. Continue with role/location allow-deny, inventory/warehouse, POS/cart, wholesale, transfers/receiving, procurement, AP/AR, expenses, cash/bank, GL/PLEDGER, reports/closing, HR lifecycle, two-period payroll, private vault/key lifecycle, browser/PWA, deep security, backup/restore and rollback.

Use the latest explicit Owner schema/HR design decisions; resolve historical table-plan conflicts by exact decision/source/ERD review. Preserve legacy rows, four legacy HR/payroll source tables and all posted history until a reviewed migration, reconciliation, backup/restore, rollback and approval are evidenced. Do not hard-code table counts. Do not invent Ethiopian statutory policy or mark the 24 Amharic-first SOP drafts approved.

Build the final one-click plugin ZIP from the final canonical source commit, not from an unreviewed local folder or test artifact. Include one plugin root, release manifest, full per-file hashes, source commit/tree, dependency/license inventory, SBOM, build receipt, CI evidence, package size and independently calculated SHA-256 sidecar. Upload as a downloadable CI artifact for review. Formal publication, live installation and deployment require the applicable final Owner release decision.

Keep engineering GO and the single product objective throughout. Report exact PASS, FAIL, BLOCKED, NOT RUN and OWNER INPUT REQUIRED states; never convert skipped or partial tests into acceptance.


## 12. One free-tier Codespace and optional local Codex

The only current prepared create link is the one in README.md, on the canonical delivery branch delivery/stockland-full-erp-completion-2026-11-11. Follow docs/engineering/CODEX_CODESPACES_SETUP_2026-11-11.md. Use one Codespace with the requested 4-core/16-GB/64-GB profile only when GitHub shows it as available; confirm quota, with no paid overage. Keep private ports, default MariaDB and CI-first MySQL. Codex CLI is optional on ChatGPT Free; sign in with device auth only, no API key or Codex Cloud. Copilot Free is optional with limited chat/agent use and 2,000 monthly completions; it does not include cloud coding-agent access. No paid marketplace plugin or external MCP is configured.

The most recent schema/HR governance is Notion Target 6 (11 Nov): 49 operational legacy keys are kept separate from the unapproved 37 HR lifecycle extension and 33 recognized HRX keys. Do not auto-create lifecycle DDL or alter existing legacy HR tables without exact written Owner approval. Do not let HR lifecycle readiness block unrelated POS, inventory, warehouse, finance and reporting routes. Keep payroll finalization fail-closed without approved effective-dated rules and qualified Ethiopian review. See docs/engineering/CODEX_CODESPACES_SETUP_2026-11-11.md and the current Notion execution record.
