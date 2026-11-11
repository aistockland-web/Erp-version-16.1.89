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
