# Stockland Ethiopia ERP

This repository contains the cumulative Stockland Ethiopia WordPress ERP/PWA. It is not a sample application or a .NET project.

## Source roles

- 16.2.13 is the Owner-designated operational/upgrade baseline.
- 16.2.15 is the selected local review/update candidate and must pass exact package, clean-install, update and database compatibility tests before release approval.
- 16.1.95 remains the protected comparison/rollback reference.

The authoritative source and acceptance decisions live in the linked Notion engineering records and root AGENTS.md. GitHub history, package hashes, CI runs and PRs provide implementation evidence.

## Start the Stockland Codespace

Create a Codespace from the active engineering branch:

https://github.com/codespaces/new?repo=aistockland-web%2FErp-version-16.1.89&ref=delivery%2Fstockland-full-erp-completion-2026-11-11

The Dev Container requests 4 cores / 16 GB RAM / 64 GB storage for the single interactive workspace, subject to GitHub account/repository/region availability. It provisions PHP 8.3, Node 22, Python 3.12, Composer, checksum-verified WP-CLI, MariaDB 10.11 and private WordPress QA instances. MySQL 8.0.46 remains CI-first to control storage and compute.

- Port 8080: 16.2.13 operational baseline
- Port 8081: disposable 16.2.13 → 16.2.15 update path
- Port 8082: optional MySQL 8.0.46 clean-install test

Use private port visibility. Do not enter production credentials or upload real company/employee/customer/supplier data.

## Local QA

~~~bash
make codespaces-validate
make packages-static
bash scripts/codespaces/smoke_test_sites.sh
bash scripts/codespaces/run_mysql8_parity.sh
~~~

The exact archive lock, SHA verification, package extraction, PHP/JavaScript/JSON/CSS checks and disposable WordPress E2E workflow are under ci/codespaces/, .devcontainer/ and .github/workflows/.

## Production boundary

This repository's test environments are synthetic and isolated. A successful Codespaces setup or package workflow is not production approval. Full ERP acceptance, schema/ledger reconciliation, WPCS/security scans, backup/restore/rollback, qualified Ethiopian statutory review and Owner/HR/Finance approvals remain separate release gates.


The only prepared Free-tier Codespace handover and optional local Codex instructions are in docs/engineering/CODEX_CODESPACES_SETUP_2026-11-11.md. Use this one branch and create one Codespace only after checking remaining GitHub quota.


## Free coding assistance

The Codespace includes optional GitHub Copilot and Copilot Chat extensions. Copilot Free has limited chat/agent usage and 2,000 monthly inline completions; cloud coding-agent access is not included. The optional local Codex CLI uses ChatGPT sign-in on the Free plan, with its included limits; Codex Cloud is not included. Neither assistant is a build dependency, and no paid credits or API key are configured. See docs/engineering/CODEX_CODESPACES_SETUP_2026-11-11.md.
