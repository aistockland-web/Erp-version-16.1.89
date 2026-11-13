# Stockland ERP 16.2.18 master installer: evidence record (11 Nov 2026)

Artifact: `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`
SHA-256: `be60d5645a3717494afeb198b65d89dfd33db192f5c0c0c36d10d04f1ede428a` (2,244,730 bytes, 123 files, single root `stockland-erp-app/`).
Reproduce: `python3 scripts/release/build_master_installer.py` (deterministic; identical bytes on repeat builds).
Source lineage: exact 16.2.13 intake commit (113/113 files vs intake manifest), then this change.
Notion execution record: https://app.notion.com/p/4159f20c06128155865bc34a206cac3d

## Environment (disposable, synthetic data only)
WordPress 6.8, PHP 8.3, WP-CLI 2.11. Databases run separately: MariaDB 10.11.14 and MySQL 8.0.46 (strict mode). No production database or live site was accessed.

## Defects fixed
1. `includes/class-stockland-schema-contract.php` lacked `<?php` (present in 16.2.14, 16.2.15 recovery, 16.2.17): source printed as output, activation and REST JSON corrupted.
2. `live_ready` was hard-coded false. Replaced by Owner go-live authorization bound to version + schema fingerprint, written to the audit chain.
3. Health check required `XMLReader::fromStream` (PHP 8.4 only) although a fallback exists.
4. MySQL 8: `SELECT @@session.in_transaction` is MariaDB-only; every ledger/GL posting failed (HTTP 500). Added `Stockland_DB::in_transaction()`.
5. MySQL 8 strict mode: sales and expenses list used `''` for DATE comparisons (HTTP 503 without date filters).
6. `code_map_list` queried unprefixed tables; duplicate customer code returned HTTP 500 (now 409); undefined variable in System Control.

## Results (scripts/release/verify_master_installer.sh, scripts/release/e2e/run_all.sh)
| Check | MariaDB 10.11.14 | MySQL 8.0.46 |
|---|---|---|
| Single-activation install, 86 tables (29/16/41) | PASS | PASS |
| LIVE_SCHEMA_READY, Owner profile, locations, HR approval audit row | PASS | PASS |
| Owner authorization -> live_ready true | PASS | PASS |
| 66 GET routes, no 5xx | PASS | PASS |
| Deep integrity scan 52/52 (also after workflow data) | PASS | PASS |
| Partial DB upgrade (20 -> 45 -> 86 tables, HR approval gate) | PASS | NOT RUN |
| PO -> receipt -> transfer -> POS sale/cart (idempotent replay) -> return -> daily close -> AP invoice/payment -> customer invoice | PASS | PASS |
| Stock balance = transaction sum; GL journals balanced | PASS | PASS |
| PHP debug.log empty | PASS | PASS |

## NOT RUN (no execution evidence)
Two payroll periods with approved Ethiopian statutory rules; role-by-role allow/deny and branch-scope matrix; maker-checker self-approval denial beyond the cases above; private-document encryption and key rotation; concurrency/load; browser, mobile, PWA, print; PHPCS/WPCS, Semgrep, CodeQL; PHP 8.4 matrix; MariaDB 11.4.13 (Owner host); backup/restore/rollback rehearsal on the Owner host.

## Owner input required
Approved effective-dated payroll/tax/pension rule register; real compensation records; process-owner approval of the 24 SOPs (stay DRAFT_NOT_APPROVED); users, roles, branches; opening stock and ledger balances; release authorization. A hash proves bytes, not approval. No tag, release, merge or deployment was performed.

## Addendum (UI verification and Administrator `read` capability fix)

- Defect found by the browser UI smoke (`scripts/release/ui/ui_smoke.js`): `Stockland_Roles::sync_roles()` treated WordPress core capability `read` as part of the Stockland namespace and stripped it from the Administrator role, so the wp-admin "ERP Dashboard" launcher returned HTTP 403. `read` is now excluded from the namespace cleanup.
- Rebuilt ZIP SHA-256: `be60d5645a3717494afeb198b65d89dfd33db192f5c0c0c36d10d04f1ede428a` (123 files, manifest regenerated).
- Re-verified on WordPress + MariaDB 10.11: installer verification PASS (86 tables, deep scan 52/52), UI smoke PASS (login, ERP launcher, System Control, mobile 390 px with 0 px horizontal overflow), business-workflow scripts PASS with a balanced ledger.
- Not re-run after this rebuild: MySQL 8.0.46 and the Docker dev container (no Docker in this session). The change is capability-only and database-neutral.
- Runbook updated with host requirements (upload_max_filesize/post_max_size >= 8 MB); ZIP rebuilt, SHA-256 `be60d5645a3717494afeb198b65d89dfd33db192f5c0c0c36d10d04f1ede428a`; installer verification PASS on MariaDB.

## Addendum: independent CI verification on GitHub (run 43176369919, workflow "Stockland 16.2.18 master installer WordPress verification")

Ubuntu runner, PHP 8.3.36, upload_max_filesize 16M / post_max_size 20M, ZIP SHA-256 `be60d5645a3717494afeb198b65d89dfd33db192f5c0c0c36d10d04f1ede428a`, ZIP content equals the 123 source files.

| Database | Result |
|---|---|
| MySQL 8.0.46 (strict sql_mode, ONLY_FULL_GROUP_BY) | PASS: 86 tables, LIVE_SCHEMA_READY, deep scan 52/52, live_ready after Owner authorization, workflow regression with 0 unbalanced journals |
| MariaDB 10.11.19 | PASS: same checks |

The Codespaces environment validation workflow (devcontainer JSON/shell validation, Compose config, PHP 8.3/Node 22 image build) also passed on this branch. A byte-for-byte ZIP rebuild is not reproducible across Python/zlib versions, so CI compares ZIP contents file by file against the source tree.

## Addendum 2026-11-13: upgrade path, resumable setup and resilience (runbook section 4)

`scripts/release/upgrade_e2e.sh` (disposable site; also run in CI on MariaDB 10.11 and MySQL 8.0.46):
1. Installs the pinned 16.2.13 operational baseline (SHA-256 `74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13`) and completes its setup wizard in bounded batches (86 batches, one table per batch, state and physical table count recorded after every batch, failure on a stalled batch): PASS, 86 tables, `LIVE_SCHEMA_READY`.
2. Runs the synthetic business workflows on the baseline (products, procurement, transfer, sales/return/close, AP, invoicing).
3. Snapshots every application table (columns, indexes, keys, row counts, per-column value digests), upgrades in place to the master installer, and verifies the HR default migration is blocked until the Owner gives a written reason, then completes after it (three payroll-line rate defaults become neutral NULL; existing rows untouched).
4. Compares the snapshots: no table, column or index lost, no type change, no row lost or changed, no table added: PASS.
5. Resilience drills: repeat setup is a no-op, a lost table is recreated alone on retry, an interrupted run is reported incomplete and resumes, a concurrent installer is refused while locked, deep integrity scan 52/52 before and after: PASS.
Baseline defect confirmed and fixed in 16.2.18: duplicate customer code returned HTTP 500 on 16.2.13 and returns HTTP 409 on 16.2.18.

Upgrade path on MySQL 8.0.46 (CI run 43387907392): BLOCKED by a baseline defect, not by 16.2.18. The 16.2.13 baseline's `wp_stock_balances` DDL has `PRIMARY KEY (location_id,variant_id)` with nullable `variant_id`; MySQL 8 rejects it ("All parts of a PRIMARY KEY must be NOT NULL", wizard batch 25 HTTP 500). No 16.2.13 site can be completed on MySQL 8, so there is nothing to upgrade there (issue #76). Fresh 16.2.18 install on MySQL 8 is PASS. Upgrade path on MariaDB 10.11 (CI) is PASS. CI asserts the exact blocked signature on MySQL 8 so any change is noticed.
## Addendum 2026-11-12 (late): transfer notifications, interactive browser setup, backup/restore

- Defect fixed: stock transfers between locations notified only on receipt. They now notify the right audiences when requested, approved, reserved, picked, in transit, rejected and cancelled (sent after the stock transaction commits; failures are logged and never roll back stock). Regression: `scripts/release/e2e/s8_transfer_notifications.php` (9 checks, PASS).
- Installer ZIP rebuilt: SHA-256 `aee5c551bd3367717091de066bfd5f86d8298dda1f6361d7534813d8087cd54e`, 123 files (supersedes `be60d564…`).
- Interactive browser acceptance (`scripts/release/ui/interactive_setup.js`) on a fresh disposable WordPress 6.8 + MariaDB 10.11.14 site: upload ZIP in wp-admin, activate, System Control shows all 29/16/41 tables present and "Installation complete", Owner authorization recorded through the form, ERP app shell renders: PASS (7/7).
- Backup/restore drill (`scripts/release/backup_restore_check.sh`): logical dump, restore into a scratch database, CHECKSUM TABLE and row count per table, 98 tables compared (86 application tables), 0 mismatches: PASS.
- Workflow regression `run_all.sh` (8 suites incl. payroll calculator vectors and transfer notifications): PASS locally on MariaDB. MySQL 8.0.46 result comes from the CI workflow run on the pull request.
