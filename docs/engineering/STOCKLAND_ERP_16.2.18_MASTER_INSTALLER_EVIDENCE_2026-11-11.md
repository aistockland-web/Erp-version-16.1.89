# Stockland ERP 16.2.18 master installer — current exact-head evidence

**Evidence updated:** 13 November 2026  
**Current source commit:** 0bb053f89dccb756068d0a763c3c6dad1692009c  
**Canonical branch:** delivery/stockland-full-erp-completion-2026-11-11  
**Status:** Exact post-merge GitHub Actions acceptance succeeded on MariaDB 10.11 and MySQL 8.0; one historical MySQL 8 upgrade sub-gate remains explicitly BLOCKED. This is not production deployment or a formal GitHub Release.

## 1. Exact source and artifact identity

- Repository: aistockland-web/Erp-version-16.1.89
- Canonical delivery branch: delivery/stockland-full-erp-completion-2026-11-11
- Current commit is the merge commit for PR #108: 0bb053f89dccb756068d0a763c3c6dad1692009c
- PR #108: fix(inventory): preserve immutable variant identity in stock-balance migration.
- PR-head acceptance: [run 43430029439 — SUCCESS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43430029439).
- Post-merge exact-head acceptance: [run 43432115923 — SUCCESS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43432115923).

Post-merge jobs:
- WordPress + MySQL 8: job 131907912257 — SUCCESS.
- WordPress + MariaDB: job 131907912824 — SUCCESS.
- Publish downloadable one-pack installer: job 131909019302 — SUCCESS.

Post-merge one-pack Actions artifact:
- Artifact ID: 14039973805.
- Outer archive SHA-256: 8e9eab68668b0be8c4953c26f44594adcba1016af747c98c1217864d0dede8ab.
- Inner installer: STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip.
- Inner ZIP SHA-256: 85a8221295718d0b4ffa02c153d63b57c09aeba12af199fbebf300ddd0dbda9d.
- Inner ZIP size: 2,239,282 bytes.
- ZIP entries: 124; single Stockland plugin root; ZIP CRC and embedded checksum sidecar PASS.

The Actions artifact is not a formal GitHub Release, signed release certificate, tag or production deployment.

## 2. Current schema-governance acceptance

The current fresh-install business E2E suite includes s9_schema_governance. On both database engines it verifies:

- Exactly the 49-table operational baseline is present: 29 Core/Operations, 16 Accounting/Procurement and four legacy HR/payroll tables.
- Core tables physically complete.
- Finance tables physically complete.
- Four legacy HR tables present.
- The 37 HR lifecycle tables are not created by default.
- No implicit HR approval record is created.
- HR-module readiness remains false while the lifecycle extension is pending.
- Core/Finance operational gate remains true.
- Global application schema gate remains true for the operational baseline.
- Setup status exposes the operational baseline; setup does not auto-advance into unapproved HR lifecycle DDL.
- ERP bootstrap responds while HR lifecycle is pending.

This is fresh-install evidence for the current governed candidate. It does not authorize alteration of existing legacy HR/payroll rows. The 33 recognized HRX keys remain a separate governance inventory under the controlling Notion Target 6 decision.

## 3. Business and module acceptance on both engines

All test databases use disposable WordPress environments and synthetic fixtures only.

### s10 — Management Intelligence persistence

The real REST/database test passes on both engines:
- Management profile/capability permits campaign planning.
- Branch Manager and Auditor are denied.
- Campaign is persisted as DRAFT / planning-only.
- Manual snapshot has timestamp and typed metrics and can be read back.
- Duplicate/retry snapshot is rejected with HTTP conflict.
- Each campaign/snapshot has exactly one immutable audit event.
- Compact audit pointer verifies the SHA-256 of the full Core settings record.
- No sales, stock, expense or GL rows are mutated.

This passes the first persistence/planning slice. It does not claim a verified live provider analytics API, social account OAuth integration or automatic posting.

### s11 — stock-balance immutable variant identity migration

The migration regression passes 16 assertions on each engine:
- A non-NULL variant_id is resolved only by immutable Product Master ID, never by a recycled SKU.
- SKU fallback is allowed only for genuine legacy rows with NULL variant_id.
- New primary key is location_id + variant_id and variant_id is NOT NULL.
- Source rollback snapshot preserves original NULL IDs and source quantities.
- Per-location physical quantities reconcile.
- Migration replay is idempotent and creates no duplicate rollback snapshot.
- Cross-namespace DDL rename is rejected.
- Unknown/orphan identities fail closed before cutover.
- Failed migration preserves original source rows and DDL and does not leave a staging table.

The original balance source remains available as the rollback snapshot. See PR #108 and Issue #76 for the remaining distinction about the old 16.2.13 bootstrap.

### Backup/restore

Both engines:
- Logical dump and restore to isolated scratch database: PASS.
- 61 tables compared, zero checksum or row-count mismatches.
- MariaDB backup SQL SHA-256: ea958728fe03cf3d136b39f4d4ce78e49948dc5013d9fe8409dd366338a6f932.
- MySQL 8 backup SQL SHA-256: e55ed0c8259c4be4f8604724a103d602c37933d60357647ee6c2311c25f4559b.

### Browser and administrator UI

Both engines pass:
- Disposable administrator login.
- WordPress native Dashboard remains reachable.
- Stockland ERP launcher menu entry and Owner System Control page.
- Published standalone route and direct ERP route.
- Authenticated application bootstrap and PWA manifest.
- 390 px phone, 768 px tablet and 1366 px desktop layouts.
- Zero horizontal overflow on each checked route/viewport.
- Command palette and dashboard quick actions keep unapproved HR lifecycle hidden.
- Management Intelligence links to official platforms but makes no unverified provider/API claim.

Admin launcher smoke confirms redirect happens before admin output and the native WordPress Dashboard is not redirected.

## 4. Historical upgrade path — results must be distinguished

### MariaDB 10.11: 16.2.13 → 16.2.18 — PASS

- The pinned .13 baseline completed setup in 86 bounded batches.
- Pre-upgrade snapshot: 86 tables / 160 rows.
- Post-upgrade snapshot: 86 tables / 161 rows.
- No tables added by the upgrade; no existing table/column/index dropped.
- Only an allowed append to the audit log and approved neutral-default normalization of selected payroll-line rates occurred.
- HR default migration remained blocked until a written Owner reason was supplied and recorded against the exact DDL signature; non-Owner/short-reason requests were rejected.
- Repeated setup preserved approval without duplicate records.
- Deep integrity 52/52 before and after resilience drills.
- Repeat setup no-op, lost-table detection/retry, interrupted-run resume and concurrent-installer lock behavior passed.
- Final MariaDB upgrade E2E result: PASS.

### MySQL 8: original 16.2.13 baseline setup / upgrade — BLOCKED

The original 16.2.13 archive remains unchanged. Its fresh setup fails at wizard batch 25 because the historical DDL declares wp_stock_balances.variant_id as nullable while including it in PRIMARY KEY(location_id, variant_id). MySQL 8 returns “All parts of a PRIMARY KEY must be NOT NULL”.

The workflow recognizes only that exact failure signature and reports the historical .13→.18 MySQL 8 path as BLOCKED, not PASS. It does not generate a completed .13 baseline snapshot for that lane. This is not a failure of the fresh 16.2.18 MySQL 8 install or of the candidate s11 migration fixture, both of which pass. Do not edit or relabel the pinned original .13 ZIP to hide the distinction. Issue #76 remains OPEN.

## 5. Source corrections in PR #108

PR #108 changed:
- scripts/release/e2e/run_all.sh
- scripts/release/e2e/s11_stock_balance_variant_identity_migration.php
- src/stockland-erp-app/includes/class-stockland-activator.php
- src/stockland-erp-app/includes/class-stockland-db.php

The migration no longer maps any non-NULL variant_id by SKU. It uses SKU only for NULL-ID legacy rows; unknown/orphan and ambiguous rows fail closed. It checks location/variant group count, global quantities, per-location totals and per-location/variant totals before atomic cutover. The DB schema-query gateway validates both source and destination identifiers in multi-pair RENAME TABLE statements.

## 6. Remaining open work and production boundary

- Issue #76: provide a safe supported MySQL 8 historical .13 restore/upgrade path or document the exact compatibility boundary with a tested alternative. Original .13 package remains unchanged.
- PR #84 remains open, stacked and unmerged. It has not been merged to main.
- PR #103 is a stale evidence-refresh branch based on an older delivery head; do not merge it wholesale. Its useful docs-trigger idea is ported in this focused follow-up.
- Issue #17: complete POS import routing and broader Management Intelligence scope.
- Issues #15/#23: complete HR lifecycle/payroll, statutory and process-owner approvals.
- Issue #47: schema-contract / complete supplemental integrity / PWA registration source.
- Full code/security review, dependency/license/SBOM, exact release source parity, final host acceptance, Owner/Operations/Finance/HR/legal sign-off and explicit production release/deployment authorization remain required.

No production WordPress, business database, posted payroll, stock ledger, employee/customer data or live transaction was changed. No formal GitHub Release, tag, main merge or production deployment is authorized by this evidence record.
