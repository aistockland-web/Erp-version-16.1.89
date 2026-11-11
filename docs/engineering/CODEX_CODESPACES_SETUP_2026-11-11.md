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

### Superpowers — install once

The repository exposes upstream Superpowers v7.0.2 at an immutable Git commit through `.agents/plugins/marketplace.json`.

From the repo root, after Codex is signed in:

```bash
codex plugin marketplace add .
codex plugin add superpowers@stockland-codex
codex plugin marketplace list
```

Then start a new Codex session. Superpowers provides planning, TDD, debugging, and review workflows. Its upstream source is MIT-licensed; the repository marketplace is pinned so a moving upstream branch cannot silently change the installed source.

If the plugin command is unavailable in the installed Codex build, use Codex's Plugins UI to add the repository marketplace and install Superpowers. Do not install duplicate copies from multiple marketplaces.

### Context7 — preconfigured documentation MCP

Context7 is configured at `https://mcp.context7.com/mcp`. It can resolve current public library/framework docs, including WordPress, PHP, JavaScript, and tooling. It does not require an API key for basic use; unauthenticated requests have lower rate limits. A free Context7 account can sign in through the first-use OAuth flow for the free-plan quota. No paid plan or key is required for the project bootstrap.

### OpenAI developer docs — preconfigured, read-only

The public `https://developers.openai.com/mcp` endpoint is configured for read-only OpenAI product/API documentation. It does not call the OpenAI API and does not need an API key.

## GitHub Codespaces free usage

GitHub personal Free accounts include 120 compute hours and 15 GB-month storage per billing month. Codespaces usage stops when the included quota is exhausted if no payment method/spending allowance is configured. Stop Codespaces when not in use and remove unused Codespaces/volumes. Do not enable paid overage for this project without explicit approval.

The Codespaces environment uses the existing disposable WordPress/MariaDB 10.11/MySQL 8.0.46 design. Keep database port 3306 internal and preview ports private.

## Safety and release boundary

Codex may independently inspect, edit, test, commit to working branches, and open PRs within repository scope. It must never:
- push directly to `main`, force-push, rewrite protected history, merge, tag, publish, deploy, or install into production without explicit Owner authorization;
- run synthetic transactions, test payroll, destructive migrations, or failure-injection tests against live business data;
- put API keys, GitHub personal access tokens, Codex auth files, or production credentials in git, logs, artifacts, chat, or Notion;
- invent statutory rules, employee values, business records, or SOP approval.

Owner involvement remains necessary only for account sign-in/installation steps, missing business/legal decisions, paid capacity, production credentials, destructive or irreversible data changes, and final live release/deployment authorization.
