# Stockland ERP 16.2.18 — Cloud AI execution and acceptance

Date: 2026-11-13

## Execution method
The controlling engineering plans remain Target 7 and the resumable setup/E2E target in Notion. Their Codespaces terminal operations are translated into repository commits, disposable GitHub Actions containers, and exact-head artifacts. No Codespace is required for CI-verifiable source/package work.

## Non-negotiable schema boundary
- Operational baseline: 29 Core + 16 Finance/Procurement + 4 legacy HR tables = 49.
- HR lifecycle: 37 source-declared tables, not created or migrated until an active Stockland Owner approves the exact DDL fingerprint and the approval is recorded in the immutable audit chain.
- HRX: 33 recognized keys remain a separate, non-DDL source; no automatic table creation or migration.
- Core, POS, Inventory and Finance can become route-ready while HR lifecycle remains gated.
- A pre-existing set of 37 lifecycle tables is not evidence of approval: live HR readiness still requires an active Owner approval matching the current source fingerprint.
- Payroll finalization remains fail-closed until a qualified effective-dated Ethiopian statutory rule set is approved. No rates are invented here.
- No existing production database is used by CI. Tests use synthetic data and fresh ephemeral MariaDB/MySQL databases.

## Cloud runtime matrix
The workflow provisions disposable WordPress + PHP 8.3 environments against MariaDB 10.11 and MySQL 8.0, builds the installer from the exact branch commit, runs PHP/Bash/Node/Python syntax and guard checks, verifies the 49-key baseline, runs business regression suites, exercises backup/restore into a scratch DB, and runs browser smoke at desktop/tablet/mobile widths plus the wp-admin launcher redirect.

## Evidence discipline
A successful Actions run proves only the exact code, database service and synthetic workflow executed by that run. It does not prove the owner's production host, live credentials, branch data, legal rules, or Owner authorization. Release remains blocked until the Owner validates production backup/restore, opening balances and permissions, approves HR DDL (if HR lifecycle is to be activated), and separately authorizes live operation.

## Remaining owner-controlled items
1. Review and approve (or explicitly defer) the 37-table HR lifecycle DDL after reviewing the exact source fingerprint and physical schema/data map.
2. Supply a qualified approved Ethiopian statutory ruleset before payroll finalization can be enabled.
3. Perform production backup, restore rehearsal, branch count reconciliation and role/access review on the actual Stockland host.
4. Authorize final live operation and deployment. CI and the Cloud AI agent do not self-authorize production.
