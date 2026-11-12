# Stockland ERP — Local Evidence Report, 8 November 2026

Continues the existing programme on the **verified 16.2.13 operational baseline**. Nothing was pushed to GitHub. No production site or data was touched. All databases were disposable and local.

## 1. Source identity
| Item | Value | Status |
|---|---|---|
| 16.2.13 installer (attached by Owner) | SHA-256 `74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13` — equals the recorded value; ZIP CRC OK; 1 plugin root; 113 files; 80 PHP | **PASS** |
| Output: 16.2.14 installer | SHA-256 `9419267a28c423062a121c45bc31c1a5444863184ed49f5eff13e25c4617d902` ; 122 entries; 80 PHP | built from 16.2.13 + 4 fixes below |
| Live-host parity of 16.2.13 | not compared | **OWNER INPUT REQUIRED** (file/hash export of the installed plugin directory) |

## 2. Changes versus 16.2.13 (full diff: 16.2.13_to_16.2.14.patch)
1. **MySQL 8 blocker fixed.** `stock_balances.variant_id` was declared `NULL` yet used in `PRIMARY KEY(location_id,variant_id)`. MySQL 8.0.46 rejects this (error 1171) so fresh install stopped at 24/29 core tables. Evidence for the fix: MariaDB already silently stores the column as NOT NULL; the plugin's own legacy-migration staging table already declares NOT NULL; the migration refuses rows without a Product Master variant. So no NULL balance rows can exist and no data semantics change. Other `variant_id NULL` columns are non-key ledger columns for SKU-only history and are untouched.
2. **HR schema marker.** Diagnostics read a key nothing writes (`stockland_hrx_schema_version`) → always "NOT_RECORDED". Now falls back to `stockland_hr_schema_version`.
3. **HR marker lost after deactivate/reactivate.** Activation zeroes the marker and setup never restored it though the schema was verified complete. Setup now records it only when ready and not read-only.
4. **XLSX readiness gate stricter than the code.** Gate demanded PHP 8.4-only `XMLReader::fromStream`; the importer has a working fallback and the plugin header supports PHP 8.3. Gate now requires ZipArchive + XMLReader.
5. Version stamps 16.2.13 → 16.2.14 in 8 places (header, constant, route/asset/SOP manifests, CSS banners).

## 3. Test results (exact artifact above, WordPress 7.1.3, PHP 8.3)
| Test | Result |
|---|---|
| PHP lint 80/80, JS (2), JSON | **PASS** |
| Reproduce blocker: pristine 16.2.13 on **MySQL 8.0.46** fresh install | **FAIL (reproduced)** at table 24/29: "All parts of a PRIMARY KEY must be NOT NULL" |
| 16.2.14 fresh install on **MySQL 8.0.46** → 86 tables, READY/LIVE | **PASS** |
| 16.2.14 fresh install on **MariaDB** → 86 tables, READY/LIVE | **PASS** |
| Deep relational integrity scan, both engines | **PASS** 52/52, 0 failed |
| Upgrade 16.2.13 → 16.2.14 in place on MariaDB with seeded data: 4 table checksums identical before/after, 86 tables, plugin stays active, READY in 1 step | **PASS** |
| Backup → drop 3 tables → restore (86 tables, row counts intact) | **PASS** |
| Deactivate/reactivate ×2 idempotent, data intact, HR marker restored | **PASS** (after fix 3) |
| Payroll arithmetic: 120 cases across all bracket boundaries vs independent integer-cent implementation, in-memory rules | **PASS** 0 mismatches (arithmetic only, **not** legal accuracy) |
| Setup is fail-closed: payroll refuses to compute without an approved effective-dated rule set | **PASS** (by design) |
| Remaining deep-health gates `schema_contract_approved`, `release_approval`, `live_ready` | **OWNER INPUT REQUIRED** — intentionally closed; not forced |
| Note: `balance_reconciled` failed once in the upgrade DB because my seed script inserted balance rows without ledger movements; the plugin correctly flagged it. Not a product defect. | explained |

## 4. NOT RUN (no execution evidence — do not read as passing)
- Full HR lifecycle through final settlement (a delegated run was cut off by a usage limit before it passed recruitment). **NOT RUN**
- Role / branch-location allow-and-deny matrix on REST routes; maker-checker and self-approval denial. **NOT RUN**
- POS cart, wholesale, receiving, requisition/reservation/transfer, returns, damage, physical count, daily close. **NOT RUN**
- Finance: PO→GRN→AP→payment→GL, reversals, PLEDGER reconciliation. **NOT RUN**
- Two-period payroll, payment batches, statutory remittance, GL reconciliation. **NOT RUN**
- Private-document encryption/key rotation/restore, SOP authenticated download audit. **NOT RUN**
- Concurrency/idempotency/replay tests. **NOT RUN**
- PHPCS/WPCS (16.2.9 line carried 16,364 errors), Semgrep, CodeQL, dependency/licence scans. **NOT RUN**
- Browser/device/PWA checks at 320–1440 px, print/export. **NOT RUN**
- PHP 8.4 matrix; MariaDB 10.11 exact version (local MariaDB is the distro build); MySQL→ upgrade path from a populated MySQL install. **NOT RUN**
- Performance/load. **NOT RUN**

## 5. Open blockers needing the Owner (not engineering)
- Qualified Ethiopian payroll/tax/pension review and approved, effective-dated rule register. The 7%/11% pension values and the PAYE bands in code are **unverified** and are not approved by this report.
- The nine active employees with missing compensation: real contract evidence only; nothing was auto-filled.
- Schema-contract approval (86 tables) and release approval — Owner/HR/Finance/Operations sign-off.
- Actual 24 Amharic-first SOP/JD/contract files and 16.3.1 package if still held (the 16.2.13 package already contains an SOP payload library; its completeness vs the 24 files was **not** checked).
- Live-host plugin-directory hash export for parity with 16.2.13.

## 6. Integrity statement
This is a **tested build, not a production approval**. Only the checks marked PASS were executed, against the artifact named above. Cloudflare: no account tool was connected, so nothing was configured. GitHub and Notion were not modified; the Notion plan page you linked is not visible to this connection (404).
