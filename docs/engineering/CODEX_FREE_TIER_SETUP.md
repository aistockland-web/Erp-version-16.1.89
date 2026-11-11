# Stockland ERP — Codex Free-Tier Setup

**Purpose:** Use the official OpenAI Codex IDE extension as the single AI implementation agent in the existing GitHub Codespace. Keep the workflow on ChatGPT Free and GitHub Free allowances only.

## Official links

- [OpenAI Codex extension for VS Code](https://marketplace.visualstudio.com/items?itemName=OpenAI.chatgpt) — extension ID: `openai.chatgpt`.
- [Codex IDE installation guide](https://developers.openai.com/codex/ide).
- [Codex with a ChatGPT plan and usage guidance](https://help.openai.com/en/articles/11369540-using-codex-with-your-chatgpt-plan).
- [ChatGPT/Codex pricing](https://chatgpt.com/codex/pricing/).
- [GitHub Codespaces billing and free quota](https://docs.github.com/en/billing/concepts/product-billing/github-codespaces).
- [GitHub Codespaces: create a Codespace](https://docs.github.com/en/codespaces/developing-in-a-codespace/creating-a-codespace-for-a-repository).

## What is included and what is not

- The OpenAI Help Center says Codex is included on ChatGPT Free, with plan-dependent usage limits. Use the local IDE extension and sign in with the ChatGPT account.
- Free Codex use is limited. The allowance is not an unlimited commitment to finish the entire ERP in one uninterrupted session. Divide work into verified targets, save changes in Git, and wait for the allowance to reset when exhausted.
- Do not use Codex Cloud/delegated cloud tasks on the Free-only workflow. Do not purchase credits, upgrade a plan, add an API key, enable API billing, or use a paid third-party agent.
- Do not paste personal access tokens, API keys, passwords, production credentials or private employee data into Codex prompts, repository files or terminal history.
- This is a local/IDE Codex workflow inside the Codespace. Codex is not being granted permission to deploy or alter the live Stockland website/database.

## Install in this Codespace

The repository's `.devcontainer/devcontainer.json` preconfigures the official extension as `openai.chatgpt`.

1. Open the current Stockland Codespace.
2. Open the Command Palette with `Ctrl+Shift+P`.
3. Run **Codespaces: Rebuild Container** so the updated extension list is applied. Save and commit any existing work before rebuilding.
4. When the editor returns, open the Extensions view and confirm **Codex — OpenAI's coding agent** is installed.
5. Open the Codex icon/sidebar. If it is hidden, open the Command Palette and run **Codex: Open Codex Sidebar**.
6. Choose **Sign in with ChatGPT** and use the existing ChatGPT Free account. Do not choose API-key authentication.
7. Keep the extension's workspace/approval controls enabled. Review requested terminal commands and file changes; never approve a command that targets a production database, production host or external secret store.

If the extension was not installed automatically, open the official Marketplace link above from the Codespaces browser, install **Codex — OpenAI's coding agent**, reload the editor and sign in with ChatGPT. Do not install lookalike extensions.

## Keep GitHub Codespaces free

GitHub currently documents a monthly included allowance for personal Free accounts of **120 core-hours** and **15 GB-month storage**. A 2-core Codespace consumes two core-hours per active clock hour. The quota applies to personal accounts; organization-owned Codespaces may be billed to the organization instead.

- Before creating or resuming a Codespace, check the account's [Billing page](https://github.com/settings/billing).
- Use the smallest suitable machine (2 cores for this workspace).
- Stop the Codespace when finished; closing the browser tab alone does not stop it.
- Do not add a payment method, raise a spending limit, or enable paid usage. If the free quota is exhausted, stop and wait for the next billing-cycle reset.
- Avoid unnecessary Codespaces, custom prebuilds and unused extensions, which consume storage.

## First Codex task — inspect before editing

Paste this into the Codex panel after sign-in:

> You are the sole implementation agent for Stockland Ethiopia ERP in this repository. First read the complete root AGENTS.md and docs/engineering/CODEX_FREE_TIER_SETUP.md. Treat GitHub as authoritative for source and CI, Notion as authoritative for business decisions, and exact artifact hashes as package identity. Do not assume any version label is cumulative.
>
> Before editing, report the current branch, exact HEAD, parent/merge ancestry, git status including untracked files, remotes, open PRs and their exact heads, latest Actions runs, and the current Codespace runtime. Preserve all pre-existing changes.
>
> Recover and compare the reported 16.2.13 source and all relevant branches/artifacts before selecting an integration base. 16.1.95 is a protected rollback/reference, not an assumed replacement. Do not merge divergent ERP releases wholesale. Preserve POS/cart, CSS modules, SOP resources, stock/finance/ledger/roles/location workflows and all historical data. Respect the unresolved MySQL 8 primary-key finding and prove nullable/no-variant SKU identity before changing key design.
>
> Work incrementally toward one cumulative WordPress ERP/PWA for all five Stockland locations. Use only synthetic data in isolated WordPress/MySQL/MariaDB test environments. Never access production credentials or mutate production data. Do not invent statutory payroll rules or historical compensation values.
>
> After source archaeology, choose the next independently testable target and implement it in a focused branch/commit with regression tests. Keep every failed or skipped check visible. Codex may commit/push and open review PRs, but must not merge, release, deploy, install on the live site or claim production readiness. Use only the ChatGPT Free allowance; no paid API, credits, upgrades or Codex Cloud. If the allowance ends, save a clear Git checkpoint and stop until it resets.

## Project authority

The root `AGENTS.md` remains the controlling engineering instruction. The project-wide Engineering GO and module-scoped release/data safeguards are unchanged. This setup document does not approve a schema, installer, production migration or deployment.
