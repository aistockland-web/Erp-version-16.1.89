# Stockland ERP — Free-plan Codex and one-Codespace setup

**Purpose:** one integrated Stockland ERP, one repository, one interactive Codespace, no paid agent or API dependency.

## 1. Canonical source and project objective

The canonical repository is aistockland-web/Erp-version-16.1.89. The only product objective is the complete cumulative Stockland Ethiopia WordPress ERP/PWA, with all compatible three-month work retained, full retail/wholesale/inventory/warehouse/procurement/finance/HR/payroll workflows, and one final reproducible installable plugin ZIP plus exact acceptance evidence. The standalone aistockland-web/Erp repository is UI preview material only.

Read AGENTS.md, docs/AGENT_FIRST_PROMPT.md, the final one-click release runbook and the current Notion execution record before implementation. Notion is authoritative for business decisions; GitHub is authoritative for source, branch, PR, CI and artifacts.

## 2. GitHub Free Codespaces limits

GitHub Free personal accounts currently include 120 Codespaces core-hours and 15 GB-month storage per billing month. The 4-core machine consumes four core-hours per active hour, giving a maximum of about 30 active hours if the complete compute allowance is used only on that machine.

Storage is a separate quota measured in GB-hours while the Codespace exists. It includes repository files, generated data, extensions, and custom dev-container base image contents. The current Stockland environment uses a custom PHP 8.3 base image, so it is NOT storage-free. A machine with large local databases, Docker images, or snapshots can consume the storage allowance even while stopped.

GitHub says usage is blocked when a personal account without a valid payment method exhausts its included quota. Do not add a payment method, enable paid overage, or change budget settings to continue this project. If the quota is exhausted, preserve and push the work, then wait for the next included-use reset.

## 3. Exact machine selection

The repository devcontainer sets hostRequirements to a minimum of 4 CPUs and 16 GB RAM. GitHub documents this as a minimum specification, not a maximum. It can still show larger machine types.

At Codespaces creation, manually choose the machine row that explicitly states 4 cores and 16 GB RAM. Do not choose 8 cores / 32 GB or a higher tier. If the 4-core / 16-GB row is not available for this repository/account, stop; do not substitute a paid or larger machine. The connected GitHub API cannot see the account's current machine picker or remaining Free quota, so check both in the GitHub UI before creation.

Use one Codespace from the canonical delivery branch after this preparation PR is reconciled. Do not create separate Codespaces for 16.2.13, 16.2.15, Codex or another chat. Keep prebuilds disabled. Use the default 30-minute idle timeout or a shorter personal setting, stop the Codespace when not using it, and avoid keeping unnecessary SQL snapshots, generated archives, browser caches, and Docker images.

Stopping a Codespace stops compute use but not storage accrual while the Codespace still exists. Deleting it stops future storage accrual but does not reverse storage already accumulated in the current billing period. Track both counters in GitHub Settings → Billing → Codespaces.

## 4. Codex — local CLI/IDE only

ChatGPT Free provides limited Codex CLI/IDE usage. Codex Cloud is not included on Free. This repository therefore configures only optional local Codex CLI profiles. The project defaults to one concurrent agent thread and medium reasoning effort to keep usage controlled.

Install the pinned CLI only when needed:

    bash scripts/codespaces/bootstrap_codex.sh

Then sign in interactively with your ChatGPT account:

    codex login --device-auth

Choose ChatGPT sign-in, not API-key authentication. Do not paste Codex auth files, device codes, tokens, GitHub credentials or passwords into the repo, chat, Notion, or logs. The bootstrap does not sign in and does not create any credential.

No third-party marketplace plugin, paid MCP, paid agent, OpenAI API key, or Codex Cloud dependency is configured. The ERP must build and run even if Codex is not installed or signed in.

## 5. Project agent roles

- stockland_explorer: read-only architecture, history and workflow tracing.
- stockland_implementer: workspace edits for one authorized target with focused regressions.
- stockland_reviewer: independent read-only correctness/security/data-integrity review.
- stockland_qa: disposable synthetic QA only, never production.

All roles are subordinate to AGENTS.md and the same latest Notion decisions. They must not merge, publish, deploy, alter production data, or invent legal/statutory rules.

## 6. Current schema safety — Owner Target 6

Do not run the old global setup ready loop unchanged. Target 6 separates the 49 legacy/operational schema inventory (29 Core + 16 Accounting/Procurement + four legacy HR/payroll tables) from the 37 HR lifecycle extension keys and 33 HRX known keys. These counts are not schema approval.

Before advancing setup, implement and test module-scoped readiness: approved Core + Accounting can become operational independently, while the HR lifecycle extension remains approval-required and not installed. No automatic creation of the 37 lifecycle tables; no automatic ALTER of existing legacy HR/payroll tables without exact Owner-approved migration; no assumed statutory rates or 7%/11% pension defaults. Preserve historical values and posted records.

## 7. Cross-chat continuity

Private ChatGPT conversation histories cannot be edited or merged by a repository integration. Notion custom-agent/session search is unavailable under this workspace entitlement. The durable handoff is the root AGENTS.md, this document, docs/AGENT_FIRST_PROMPT.md, current Notion execution records, source files, PRs and exact Actions receipts. Future chats must refresh those records; never claim inaccessible sessions were inspected.

## 8. Final release boundary

The current PR #84 / delivery line has passed package, source-intake and bounded WordPress update checks. The 12-table smoke receipt does not prove the ERP setup schema or business workflows. Full business acceptance, physical schema/data reconciliation, security, payroll/legal review, vault key lifecycle, browser/PWA, backup/restore/rollback and Owner approvals remain open. The final one-click plugin ZIP is not yet production-approved. Do not merge to main, tag, publish, install live or deploy without the required final Owner release authorization.

## Sources for quota and machine behavior

- GitHub Codespaces billing: https://docs.github.com/en/billing/concepts/product-billing/github-codespaces
- Included usage guidance: https://docs.github.com/en/codespaces/troubleshooting/troubleshooting-included-usage
- Minimum machine requirements: https://docs.github.com/en/codespaces/setting-up-your-project-for-codespaces/configuring-dev-containers/setting-a-minimum-specification-for-codespace-machines
- Creating a Codespace and choosing a machine: https://docs.github.com/en/codespaces/developing-in-a-codespace/creating-a-codespace-for-a-repository
- Codex with ChatGPT plans: https://help.openai.com/en/articles/11369540-using-codex-with-your-chatgpt-plan
