# Stockland ERP — Codex in GitHub Codespaces

**Date:** 11 November 2026  
**Working branch for this setup:** `codex/stockland-agent-workspace-2026-11-11`  
**Parent integration line:** `integration/stockland-erp-16213-to-16215-codespaces-2026-11-11`

## What this adds

- Pinned Codex CLI bootstrap for the Codespaces devcontainer; no API key is added.
- Project instructions from the repository-root `AGENTS.md`.
- Four project-scoped Codex agents in `.codex/agents/`: read-only explorer, cumulative implementer, read-only reviewer, and isolated WordPress/database QA agent.
- Project-scoped `.codex/config.toml` with a three-agent concurrency default.
- Read-only OpenAI developer documentation MCP and Context7 documentation MCP.
- A repo plugin marketplace exposing Superpowers from a pinned upstream commit.
- A reusable `stockland-erp-delivery` skill under `.agents/skills/`.
- A TOML/JSON configuration validator and CI coverage.

## Codespaces startup

The devcontainer post-create process installs the Codex CLI version pinned by `scripts/codespaces/bootstrap_codex.sh`. The command is:

```bash
bash scripts/codespaces/bootstrap_codex.sh
```

The CLI is installed under `~/.local/bin` when it is not already present. The script does not sign in, create credentials, or use an OpenAI API key.

## One required account action: sign in with ChatGPT

From the Codespaces terminal, run:

```bash
codex login --device-auth
```

Open the one-time sign-in link in your own browser and finish the ChatGPT login. Use **Sign in with ChatGPT**, not an API key. Never paste `~/.codex/auth.json`, device codes, tokens, or secrets into this repository, Notion, chat, terminal logs, or screenshots.

Codex access on ChatGPT Free is limited. Codex Cloud is not included on Free; the CLI running inside your GitHub Codespace is the intended route here. Do not configure API-key billing or paid services as a workaround.

## Trust the project and start Codex

From the repository root:

```bash
git status --short --branch
git rev-parse HEAD
codex
```

Trust this repository when Codex asks. Project-scoped configuration is intentionally ignored until the project is trusted. Codex reads the root `AGENTS.md`, the `.codex/config.toml`, and the agent profiles under `.codex/agents/`.

Suggested first prompt:

> Read AGENTS.md, the Stockland ERP delivery skill, the controlling Notion pages, and the latest GitHub PR/Actions evidence. Inspect the full repository and Codespaces state. Do not start a greenfield rewrite. Produce a ranked target plan, then implement the highest-priority safe code defect, add its regression test, run the exact affected gates, commit it on a descriptive branch, and continue to the next coherent target. Ask me only for a truly Owner-controlled input or an operation that cannot safely proceed without my sign-in/authorization.

## Optional Codex plugins and documentation

### Superpowers — install once inside Codex

The repository exposes upstream Superpowers v7.0.2 at an immutable Git commit through `.agents/plugins/marketplace.json`. The source is MIT-licensed.

From the repository root, after signing into Codex:

```bash
codex plugin marketplace add .
codex plugin marketplace list
codex
```

Inside the Codex terminal session, open the plugin browser:

```text
/plugins
```

Search for **Superpowers**, choose the entry from **Stockland ERP Engineering**, and select **Install Plugin**. Then start a new Codex session. This uses the Codex plugin flow; no separate ChatGPT app plugin or API key is required. The Codex plugin directory documents `/plugins` as the CLI install path and `codex plugin marketplace add` as the marketplace registration command.

### Context7 — preconfigured documentation MCP

Context7 is already configured at `https://mcp.context7.com/mcp` in project Codex settings. It can resolve current public framework/library documentation, including WordPress, PHP, JavaScript, and tooling. No separate ChatGPT Context7 app installation is needed for this Codespace. Complete Context7's free OAuth flow only if the MCP server prompts for it; do not add a paid plan or API key.

### OpenAI developer docs — preconfigured, read-only

The public `https://developers.openai.com/mcp` endpoint is configured for read-only OpenAI product/API documentation. It does not call the OpenAI API and does not need an API key.

## GitHub Codespaces free usage

GitHub personal Free accounts include 120 core-hours and 15 GB-month storage per billing month. A 2-core Codespace therefore gives up to 60 active hours/month; a 4-core Codespace gives about 30 hours. Use the 2-core option for this free-tier project, keep only one database profile running at a time in the interactive Codespace, and stop the Codespace when idle (the default idle timeout is 30 minutes). Run the MariaDB/MySQL matrix in the public GitHub Actions workflows rather than keeping both engines active in the personal Codespace. If the included quota is exhausted and no payment method/spending limit is set, usage is blocked. Do not enable paid overage for this project.

The Codespaces environment uses the existing disposable WordPress/MariaDB 10.11/MySQL 8.0.46 design. Keep database port 3306 internal and preview ports private.

## Safety and release boundary

Codex may independently inspect, edit, test, commit to working branches, and open PRs within repository scope. It must never:
- push directly to `main`, force-push, rewrite protected history, merge, tag, publish, deploy, or install into production without explicit Owner authorization;
- run synthetic transactions, test payroll, destructive migrations, or failure-injection tests against live business data;
- put API keys, GitHub personal access tokens, Codex auth files, or production credentials in git, logs, artifacts, chat, or Notion;
- invent statutory rules, employee values, business records, or SOP approval.

Owner involvement remains necessary only for account sign-in/installation steps, missing business/legal decisions, paid capacity, production credentials, destructive or irreversible data changes, and final live release/deployment authorization.


## Repository handover status — 11 November 2026

- Codex workspace branch: `codex/stockland-agent-workspace-2026-11-11`.
- Draft handover PR to the active integration branch: [PR #80](https://github.com/aistockland-web/Erp-version-16.1.89/pull/80).
- Exact PR head: `33108758d367fab4eca90d7c3c62f614b0dd35f3`; base integration head at PR opening: `6c7152c361a21e6169fe7f48e3c43f8d63117715`.
- Focused Codex config validation passed on push [run 42949157594](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42949157594) and on PR [run 42949299469](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42949299469). Both runs tested the exact same head SHA and completed successfully.
- This is tooling/agent setup only. The live Codespace was not remotely edited or authenticated from this chat. On Codespace rebuild/post-create, the pinned Codex CLI is installed if absent; the Owner must complete ChatGPT device sign-in and install Superpowers once.
- The full .13-to-.15 WordPress/MariaDB/MySQL runtime acceptance remains a separate ERP acceptance gate. This Codex PR does not certify the ERP or approve production release.
