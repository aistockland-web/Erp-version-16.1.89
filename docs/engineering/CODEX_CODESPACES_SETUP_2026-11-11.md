# Stockland ERP — one Free Codespace and optional local Codex

Repository: aistockland-web/Erp-version-16.1.89  
Working branch: feat/stockland-free-codespaces-codex-2026-11-11  
Product: the existing cumulative Stockland Ethiopia WordPress ERP/PWA. This is not a greenfield app or a production release.

## 1. One Codespace, not another environment

Create a single Codespace from this repository branch after repository checks pass:

https://github.com/codespaces/new?repo=aistockland-web%2FErp-version-16.1.89&ref=feat%2Fstockland-free-codespaces-codex-2026-11-11

The Dev Container declares a minimum machine profile of 2 cores, 8 GB RAM and 32 GB storage where available. This is the recommended Free-plan target. A 4-core/16-GB machine uses twice as many core-hours; do not select it for this free-only workflow unless the shorter allowance is acceptable.

GitHub's published personal Free Codespaces allowance is 120 core-hours and 15 GB-month storage. At 2 cores, compute quota permits up to 60 active hours for the billing month; at 4 cores it permits up to 30. All Codespaces billed to the account use this quota. When quota is exhausted and no payment method exists, usage is blocked until the next billing cycle. This configuration does not add a payment method or request paid overage.

Before creating the one Codespace, open GitHub Settings, Billing/Usage, Codespaces and check remaining compute/storage allowance, then confirm the create page offers the 2-core machine. The connected API cannot read the Owner's remaining quota or available machine list. Stop the Codespace when finished; the default idle timeout is 30 minutes, but verify the account setting. Avoid prebuilds and duplicate Codespaces.

## 2. Environment and free services

The existing repository Dev Container remains authoritative:
- PHP 8.3 and required WordPress extensions, Composer and WP-CLI (WP-CLI is verified against its published SHA-256 before installation).
- Node 22, Python 3.12, GitHub CLI, Docker/Compose and locked QA tools.
- MariaDB 10.11 for the default private local WordPress test sites.
- Private browser previews on ports 8080/8081; database port 3306 remains internal.
- MySQL 8.0.46 remains optional. Prefer the GitHub Actions database matrix for repeatable MariaDB/MySQL coverage rather than keeping extra services running in the interactive Codespace.
- Credentials are random, local-only, generated into ignored .env files; never commit or print them.

Do not load a live database, business backup, customer/supplier/employee records, payroll, HR files or production credentials.

## 3. Optional Codex CLI on ChatGPT Free

Codex is available on ChatGPT Free with plan-specific usage limits. Codex Cloud is not included with Free. The local CLI runs inside this one GitHub Codespace and uses GitHub Codespaces compute while active. It is optional: the ERP build/test environment does not require Codex.

Install the pinned CLI only when needed:
bash scripts/codespaces/bootstrap_codex.sh
codex login --device-auth

Complete ChatGPT sign-in using the one-time device flow in the Owner's browser. Do not use an OpenAI API key. API usage is separately billed and is outside this free-only plan. Never paste device codes/tokens, ~/.codex/auth.json, GitHub tokens or credentials into Git, issue comments, Notion, chat or screenshots.

Codex project configuration is loaded only after the repository is trusted:
- .codex/config.toml limits concurrent agent threads to one.
- Four project-scoped agents provide read-only exploration, workspace-write implementation, read-only review and isolated QA.
- Minimal configuration enables no third-party plugins, no external MCP endpoints, no paid services and no cloud agent.
- Project auth/session files are ignored by Git.

Use /status in Codex CLI to inspect usage. Usage limits vary by plan and may stop local Codex work until reset. Do not add credits, use an API key or upgrade to continue under the free-only constraint.

## 4. Current ERP/schema rule before tests

The latest Owner decision is Notion Target 6, 11 November 2026. The protected operational inventory is 49 legacy keys (29 Core/Operations + 16 Accounting/Procurement + four legacy HR/payroll). Keep the 37 HR lifecycle extension tables and 33 recognized HRX keys in separate, unapproved inventories. Do not auto-create lifecycle tables or alter existing legacy HR/payroll tables without written Owner approval tied to an exact reviewed DDL fingerprint.

Core + Accounting operational readiness must not be suppressed by HR lifecycle review. Keep POS, stock, warehouse, finance and reports available when their own schema checks pass. Report HR lifecycle separately as APPROVAL_REQUIRED/NOT_INSTALLED; keep HR/payroll mutation and finalization fail-closed on their own schema, authorization, vault/key and effective-dated statutory prerequisites. Do not let a QA loop continue to global ready if that creates unapproved HR DDL.

The divergent schema-test scripts are prototypes; adapt them to the latest approval boundary before use. Do not merge the branch wholesale.

## 5. Execution sequence

1. Finish repository-side config/agent/tests and obtain exact-head GitHub checks.
2. Create or re-use exactly one Codespace from this branch.
3. Confirm branch/HEAD/worktree, package hashes, source provenance, PHP/Node/Python/Composer/WP-CLI and internal DB health.
4. Commit the verified full .13 source intake separately before functional edits.
5. Implement module-scoped schema/readiness changes and dual-database regression on the exact source.
6. Continue POS/inventory/warehouse, finance/procurement, full HR lifecycle/payroll, vault, reports/PWA, backup/restore and rollback end-to-end. Every test uses disposable synthetic data.
7. Build one final WordPress plugin ZIP from the final canonical commit, generate manifest/SBOM/build receipt and independent SHA-256, run exact-package install/update and business acceptance, then request the required Owner release decision.

Free-tier limitation: GitHub Free includes a finite monthly quota and Codex Free usage is also limited. This configuration reduces cost and avoids paid dependencies, but it cannot guarantee unlimited compute or that the full ERP can be completed within one billing month's free allocation. Preserve every commit and stop before any paid overage.
