# Stockland ERP — GitHub Copilot repository instructions

You are working on the existing Stockland Ethiopia PHP/WordPress ERP repository. Read the root AGENTS.md and the current Notion execution record before making a plan or commit.

## Required context

- Notion controls business requirements, source selection, Owner decisions and acceptance. GitHub controls exact source, commits, branches, PRs and Actions.
- Current operational baseline: 16.2.13, SHA-256 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13.
- Selected 16.2.15 update candidate: SHA-256 8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df. Candidate until exact package and WordPress update tests pass.
- Protected comparison/rollback reference: 16.1.95, SHA-256 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625.
- Owner correction: PR #77. Existing Codespaces foundation: PR #67. Do not merge either merely to start work.

## Mandatory workflow

1. Fetch current Notion decisions and newer corrections before every target.
2. Update the Notion execution record before making the target commit; append exact commit/PR/CI/test evidence immediately afterwards.
3. Inspect current source, history, PRs and package files. Do not infer code behavior from version numbers.
4. Implement one coherent target with regression tests; preserve the best compatible implementation from all project history.
5. Run local checks, exact commit CI and exact installer checks. Read logs and fix real failures; never suppress or skip gates.
6. Commit to a dedicated branch and open a review PR. Do not reset, force-push, mutate production or merge/deploy without Owner authorization.

## Product invariants

Stockland ERP is a single-company WordPress web ERP/PWA: central warehouse plus Goro, Gerji, Mexico and Megenagna branches; Product Master/SKU; retail and wholesale POS/cart; inventory, receiving, requisitions, reservations, transfers, returns, damage, adjustments, physical counts and daily close; procurement, suppliers/customers, AP/AR, expenses, cash/bank, GL/PLEDGER; role/branch authorization; reports/exports; HR/payroll and private SOP/resources.

Preserve immutable ledger/audit history, maker-checker approval, exact money arithmetic, fail-closed availability, idempotent transactions, branch/location scope and privacy. Keep the protected 49-table contract/legacy HR records until approved ERD, source mapping, backup and rollback evidence exists. Never invent Ethiopian statutory rules or approved SOP content.

## Dev environment

Use .devcontainer/ and scripts/codespaces/. Baseline WordPress QA is private port 8080; .13 → .15 upgrade QA is private port 8081; optional MySQL 8.0.46 QA is private port 8082. All databases are synthetic, isolated and internal. Local credentials are generated in ignored .env and must never be committed.

The dotnet-codespaces repository is reference material only. Do not introduce .NET, sample/demo app code, WooCommerce, third-party POS/inventory or duplicate authentication systems.

## Reporting

Use PASS, FAIL, BLOCKED, NOT RUN and OWNER INPUT distinctly. Name the exact branch/SHA, files, tests, workflow run and artifact hash. Never call a candidate production-ready based only on static syntax, successful environment creation or a green CI workflow.


## Free-only, one-Codespace and schema Target 6 safeguards

- The only allowed interactive workspace is one Codespace with a manually selected 4-core / 16-GB RAM machine. The devcontainer sets 4 CPUs / 16 GB as a minimum only; it cannot cap the machine at 16 GB. Never select 8-core / 32-GB or enable paid overage.
- GitHub Free personal quota is 120 core-hours and 15 GB-month storage. Four cores consume four core-hours per active hour. The current custom PHP base image counts toward measured storage; monitor the actual quota and do not claim this environment is storage-free.
- Local Codex CLI/IDE only, with ChatGPT sign-in and Free usage limits. Do not require Codex Cloud, API keys, third-party paid plugins, or marketplace agents. No API-key fallback.
- Follow latest Owner HR/schema Target 6. Keep the four legacy HR/payroll tables separate from the 37 lifecycle extension and 33 HRX keys. Do not auto-create lifecycle tables, auto-alter legacy HR/payroll columns, or infer approval from counts. Core + Accounting readiness must not be globally blocked by pending HR extension approval. New pension DDL must not silently default to 7%/11%; never rewrite historic values.
- Do not run the divergent QA branch global-ready setup loop or the 16.2.15 wizard unchanged. First implement and test module-scoped schema gating, then test permitted baseline setup on disposable databases.
- The standalone aistockland-web/Erp UI repository is a design-only reference, not the ERP runtime. Preserve the canonical authenticated WordPress app and API contracts.
