# Stockland ERP 16.2.18 master installer — current cloud evidence

**Evidence updated:** 13 November 2026  
**Status:** Delivery-branch package verified by exact-head GitHub Actions. This is not production deployment or a public GitHub Release.

> This current record supersedes the 11 and 12 November test summaries embedded in earlier versions of this file. In particular, the earlier 86-application-table / 41-HR schema count and 98-table backup comparison are historical results from a prior schema policy and must not be used as the current release gate. The approved operational baseline is now 49 keys; the 37-table HR lifecycle extension remains separately Owner-controlled.

## Canonical source and release artifact

- Repository: `aistockland-web/Erp-version-16.1.89`
- Canonical delivery branch: `delivery/stockland-full-erp-completion-2026-11-11`
- Merged feature PR: [#102](https://github.com/aistockland-web/Erp-version-16.1.89/pull/102)
- Merge commit: `3e9f349cf6636ccfbe7cc125eb7ea7b49e766623`
- PR exact-head verification: [Actions run 43338041006](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43338041006)
- Post-merge exact-head verification: [Actions run 43338530647](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43338530647) — SUCCESS for MariaDB 10.11, MySQL 8.0 and one-pack artifact publication.
- Post-merge one-pack Actions artifact: `stockland-erp-16.2.18-one-pack-installer`, artifact ID `13998975712`.

Inner WordPress plugin ZIP:
- Filename: `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`
- Size: `2,229,903` bytes
- Entries: `123`, single plugin root `stockland-erp-app/`
- Main plugin: `stockland-erp-app/stockland-inventory.php`
- SHA-256: `5644b90a8b4c048a01158095f8bfd3453579cc742f2992faae89c729a1f46786`
- Embedded SHA-256 file: PASS
- Independent ZIP integrity check: PASS

The outer one-pack archive also contains the inner installer ZIP, its checksum file, and this evidence record. The inner plugin ZIP is the file intended for WordPress plugin installation.

## Current schema-governance contract

- **49-key operational baseline:** 29 Core + 16 Finance/Procurement + 4 legacy HR tables.
- **37 HR lifecycle tables:** not automatically created or altered. Requires approval by the active Stockland Owner bound to the exact source DDL fingerprint, followed by physical schema verification.
- **33 HRX recognized keys:** remain a separate contract and are not automatic DDL.
- Core, POS, Inventory and Finance may be operational while HR lifecycle is pending; the application boot and frontend use a separate operational-ready signal.
- HR lifecycle routes/navigation stay disabled until the HR module's own readiness check passes.
- Live release authorization remains fail-closed until full Owner-approved schema readiness is established.
- Payroll finalization remains disabled until qualified, approved, effective-dated Ethiopian statutory rules are supplied. No statutory rates are inferred or invented.

## Exact-head synthetic acceptance

All tests use disposable GitHub-hosted WordPress environments with synthetic data. No Stockland production database or live hosting environment was accessed.

| Area | MariaDB 10.11 | MySQL 8.0 |
|---|---|---|
| Fresh WordPress / plugin install and installer verification | PASS | PASS |
| Core + Finance + legacy HR 49-key baseline | PASS | PASS |
| HR lifecycle approval gate / no implicit DDL | PASS | PASS |
| Authenticated setup-status and operational application bootstrap | PASS | PASS |
| Business workflow and transfer-notification regressions | PASS | PASS |
| Backup/restore to an isolated scratch database | PASS | PASS |
| Responsive browser smoke: desktop, tablet and phone | PASS | PASS |
| ERP launcher and native wp-admin Dashboard behavior | PASS | PASS |
| Exact source/package parity and one-pack publication | PASS | PASS |

Additional verified backup controls:
- 61 tables compared after restore, zero checksum or row-count mismatches.
- Wrong disposable DB name rejected before database access.
- Remote WordPress DB host rejected.
- Remote administrative MySQL host rejected.
- Untrusted inherited MySQL option file rejected.
- Backup command uses strict pipeline failure handling and requires explicit `RESULT: PASS`.

## Cumulative changes in this verified candidate

- Integrated the transfer lifecycle notification flow and its synthetic E2E regression checks.
- Separated operational Core/Finance readiness from HR lifecycle readiness so the 37-table HR extension does not prevent daily operational modules from loading.
- Removed the fresh-install implicit HR approval path and bound approval to the active Owner and exact DDL fingerprint.
- Kept HR bootstrap/mutation routes fail-closed until physical schema verification.
- Added the explicit HR-pending UI state and prevented HR lifecycle navigation/command-palette visibility before approval.
- Added a focused WordPress admin ERP launcher redirect while preserving the native Dashboard.
- Rebuilt the one-pack installer from the exact verified source commit and published it as a GitHub Actions artifact.

## Historical baseline and limitations

The 16.2.13 baseline archive and its 113-entry package file manifest were located in Git history at intake commit `1c3b743296b7a1f4e3b8a0b481e6883b5fed4154`. The archived ZIP Git blob is `908bbff20399438c76a15233ecd1b4bc5957fe58`, recorded size `2,188,684` bytes. The historical SHA-256 recorded by the prior intake is `74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13`. The binary bytes were not independently rehashed in this Cloud AI run, so the digest remains historical provenance, not a newly recomputed value. The original 16.2.13 live installed plugin-directory parity remains unverified.

Fresh MySQL 8 installation and current E2E pass do not yet replace a dedicated populated-old-schema migration simulation for the previously reported `stock_balances.variant_id` issue. That migration test remains open and must prove legacy balances and variant identity are preserved.

Other open work remains separately tracked:
- Owner's HR lifecycle DDL decision and approval (#15).
- Management Intelligence social/campaign/manual-performance scope (#17).
- Qualified Ethiopian payroll-law/statutory-to-SOP approval (#23).
- HRX contract drift and deployed PWA/source parity (#47).
- Outstanding code/security-review finding (#53).
- Independent 16.2.13 archive rehash and deployed source parity (#71).
- Populated legacy MySQL stock-balance upgrade simulation (#76).

The cumulative delivery PR #84 remains open and GitHub reports it non-mergeable; it must not be force-merged or redirected to `main` merely because PR #102 passed. Older Codespaces/feature branches are not bulk-merged.

## Production boundary and Owner-controlled gates

This evidence certifies the disposable cloud test matrix and the delivery-branch package only. It does not certify the Owner's live WordPress host, live credentials, production database, branch inventory, customer/supplier balances, statutory payroll rules or production source-file parity.

Before go-live, the Owner-controlled acceptance remains:
1. Production backup and restore rehearsal on the actual Stockland host.
2. Installed source/PWA file parity and environment compatibility.
3. Opening stock, branch counts, supplier/customer balances and ledger reconciliation.
4. Final role/location permission and security review, including the unresolved security finding.
5. Explicit HR lifecycle DDL decision/approval if HR lifecycle activation is required.
6. Qualified, approved effective-dated Ethiopian payroll statutory rules before payroll finalization.
7. Explicit authorization for production deployment and live operation.

No production database was touched. No public GitHub Release, production deployment, or live-operation authorization was performed by these CI runs.
