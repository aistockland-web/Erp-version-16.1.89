# Stockland ERP 16.2.18 — final runtime verification on MySQL 8 (22 Nov 2026)

**Source:** branch `audit/full-2026-11-20` head `4fb3f88` (PR #151). Installer rebuilt with the repository's own `scripts/release/build_master_installer.py --update-manifest`; the in-repo rebuild is byte-identical to the ZIP tested.
**Installer:** `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`, 2,306,000 bytes, 128 files, SHA-256 `b0533135727cd80b633fec50f7e935d404bb94b5366217f9340fd1c6b416d3f0`.
**Correction:** the ZIP previously committed on that branch (`96f41b48…`) was stale. 15 files differed from branch source (HR lifecycle, private vault, payroll release rules, POS integrations, authority/roles, management intelligence, SOP library, assets). A 21 Nov interim verification of that stale ZIP is superseded by this record.
**Environment:** disposable WordPress 7.1.3, PHP 8.3, MySQL 8.0.46, synthetic data, loopback only.

| Check | Result |
|---|---|
| Built ZIP content equals branch source (all files except the manifest) | PASS |
| `verify_master_installer.sh` (repo's official verifier): 49-table baseline, 37 HR lifecycle tables gated, no implicit HR approval, installing admin becomes Owner, live_ready false, mutation routes unregistered before approval | PASS |
| `run_all.sh`: 21 business suites s1 to s20 (users, procurement, transfer, sales/returns/close, AP, customer invoice, payroll vectors, notifications, schema governance, management intelligence, stock-balance identity migration, daily POS import, dashboard filters, reports, HR/payroll rule contract, company profile, **full payroll cycle (262 assertions)**, HR library edit (29), **POS integration hub (41/41)**, **payroll release rules (16)**) | PASS: 576 PASS lines, 0 FAIL |

## Payroll status
The product refuses payroll generation without an independently reviewed, approved, effective-dated rule set, and rejects an Owner declaration as qualified legal review (s20). The suites use synthetic rates only. **No statutory rule was approved or ratified by this work.** Ratification must be done in the application by the Owner with the qualified legal/tax opinion on record.

## Not done here / OWNER INPUT REQUIRED
Real PAYE/pension rule ratification, schema-contract approval, release approval, live-ready authorization, HR/Finance/Operations acceptance, written production release authorization; MariaDB, PHP 8.4, browser/PWA, backup/restore and security-gate lanes are covered by CI (run 44882322999 and siblings), not re-run here. Issue #76 (original 16.2.13 ZIP on MySQL 8) remains open. No tag or GitHub Release created.
