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

The Dev Container provisions PHP 8.3, Node 22, Python 3.12, Composer, checksum-verified WP-CLI, MariaDB 10.11 and private WordPress QA sites. The repository requires a minimum of 4 CPU cores and 16 GB RAM; this is a minimum, not a maximum.

- Port 8080: 16.2.13 operational baseline
- Port 8081: disposable 16.2.13 → 16.2.15 update path
- Port 8082: optional MySQL 8.0.46 clean-install test

Use private port visibility. Do not enter production credentials or upload real company/employee/customer/supplier data.

### Free plan — read before creation

GitHub Free personal accounts include 120 Codespaces core-hours and 15 GB-month storage. A 4-core machine uses 4 core-hours per running hour, so the theoretical compute allowance is about 30 active hours per billing month. Storage accumulates while a Codespace exists, and this repository currently uses a custom PHP base image whose contents count toward measured storage. This is not an unlimited or storage-free environment. Select the machine row explicitly labeled 4 cores / 16 GB RAM in GitHub's creation UI; do not select 8 cores / 32 GB or enable paid overage. The devcontainer minimum cannot enforce a maximum. Check your actual remaining quota and machine list before creation. Keep only one Codespace and no prebuilds.

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
