# Stockland ERP — one Free Codespace and optional local Codex

Repository: aistockland-web/Erp-version-16.1.89  
Working branch: delivery/stockland-full-erp-completion-2026-11-11  
Product: the existing cumulative Stockland Ethiopia WordPress ERP/PWA. This is not a greenfield app or a production release.

## 1. One Codespace, not another environment

Create a single Codespace from this repository branch after repository checks pass:

https://github.com/codespaces/new?repo=aistockland-web%2FErp-version-16.1.89&ref=delivery%2Fstockland-full-erp-completion-2026-11-11

The Dev Container requests 4 cores and 16 GB RAM, matching the Owner's selected interactive workspace profile. Disk size is intentionally left to the GitHub create dialog; select the smallest supported storage size that accommodates the WordPress/PHP source, Docker images and QA artifacts. GitHub only offers machine sizes supported by the repository, personal account and region, so confirm the 4-core / 16-GB profile before creating the single Codespace. The machine size does not turn Codex model inference into a local 32-GB requirement; the RAM is for the local PHP/WordPress, database, browser and QA toolchain.

GitHub's published personal Free Codespaces allowance is 120 core-hours and 15 GB-month storage. At 4 cores, compute quota permits up to 30 active hours for the billing month. All Codespaces billed to the account use this quota. Storage is metered separately by GB-month; choose the smallest storage option offered and check the remaining 15 GB-month allowance before creation. When quota is exhausted and no payment method exists, usage is blocked until the next billing cycle. This configuration does not add a payment method or request paid overage.

Before creating the one Codespace, open GitHub Settings, Billing/Usage, Codespaces and check remaining compute/storage allowance, then confirm the create page offers the 4-core / 16-GB machine and choose the smallest available disk option. The connected GitHub API cannot read the Owner's remaining quota or available machine list. Stop the Codespace when finished; the default idle timeout is 30 minutes, but verify the account setting. Avoid prebuilds and duplicate Codespaces.

## 2. Environment and free services

The existing repository Dev Container remains authoritative:
- PHP 8.3 and required WordPress extensions, Composer and WP-CLI (WP-CLI is verified against its published SHA-256 before installation).
- Node 22, Python 3.12, GitHub CLI, Docker/Compose and locked QA tools.
- MariaDB 10.11 for the default private local WordPress test sites.
- Private browser previews on ports 8080/8081; database port 3306 remains internal.
- VS Code includes GitHub Copilot and Copilot Chat extensions. Sign in to GitHub and select the free Copilot tier; the free plan has limited chat/agent usage and 2,000 monthly completions, and does not include the cloud coding agent. Copilot is optional, not a dependency of the ERP.
- MySQL 8.0.46 remains optional. Prefer the GitHub Actions database matrix for repeatable MariaDB/MySQL coverage rather than keeping extra services running in the interactive Codespace.
- Credentials are random, local-only, generated into ignored .env files; never commit or print them.

Do not load a live database, business backup, customer/supplier/employee records, payroll, HR files or production credentials.

## 3. Optional Codex CLI on ChatGPT Free

Codex is available on ChatGPT Free with plan-specific usage limits. Codex Cloud is not included with Free. The local CLI runs inside this one GitHub Codespace and uses GitHub Codespaces compute while active. It is optional: the ERP build/test environment does not require Codex.

The Codespaces post-create step prepares the pinned CLI automatically when npm access is available. To retry or install manually:

bash scripts/codespaces/bootstrap_codex.sh

Then sign in once using:

codex login --device-auth

Complete ChatGPT sign-in using the one-time device flow in the Owner's browser. Do not use an OpenAI API key. API usage is separately billed and is outside this free-only plan. Never paste device codes/tokens, ~/.codex/auth.json, GitHub tokens or credentials into Git, issue comments, Notion, chat or screenshots.

### One-time optional Superpowers plugin installation

After device sign-in, from the repository root run:

```bash
codex plugin marketplace add .
codex plugin marketplace list
codex
```

In the Codex terminal, open `/plugins`, select **Superpowers** under **Stockland ERP Engineering**, and choose **Install Plugin**. Start a new Codex session to load it. The marketplace pins upstream commit `22f0b463e7a5e579904dd6211a520321035526f7`; the upstream project identifies its license as MIT. This is a free, optional workflow aid for planning, test-driven development, debugging and review. The ERP instructions remain authoritative when they differ from a generic plugin suggestion. No paid model, API key or cloud agent is used.

The bootstrap sets `SUPERPOWERS_DISABLE_TELEMETRY=true` in the local shell profile to disable the plugin's optional visual-companion telemetry.

Codex CLI is pinned to version 0.167.1 in the local installer. Codex project configuration is loaded only after the repository is trusted. GitHub Copilot uses the root .github/copilot-instructions.md, which carries the same Stockland master scope and schema approval rules:
- .codex/config.toml limits concurrent agent threads to one.
- Four project-scoped agents provide read-only exploration, workspace-write implementation, read-only review and isolated QA.
- The optional Superpowers plugin is pinned to an immutable upstream commit from its MIT-licensed repository. It is installed only after the Owner chooses Install in Codex; no plugin, paid service or cloud agent is required for the ERP build. External MCP servers remain disabled in the minimal Free configuration.
- Project auth/session files are ignored by Git.
- Copilot Free is an optional assistant with monthly usage limits. Cloud agent runs and paid AI credits are not part of this workflow.

Use /status in Codex CLI to inspect usage. In GitHub Copilot, check Settings → Copilot → Usage for remaining free allowances. Usage limits vary by plan and may stop local Codex work until reset. Do not add credits, use an API key or upgrade to continue under the free-only constraint.

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
