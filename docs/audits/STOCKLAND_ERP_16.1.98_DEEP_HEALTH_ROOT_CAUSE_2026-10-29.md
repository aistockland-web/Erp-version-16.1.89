
# Stockland ERP 16.1.98 — Deep Health Root-Cause Audit

**Date:** 29 October 2026  
**Disposition:** **NO-GO / NOT CERTIFIED** for production or a live update.

## Runtime evidence supplied from the installed WordPress site

- WordPress 7.1.4; PHP 8.4.27; MariaDB 11.4.13; plugin label 16.1.98-HRX-REVIEW-ONLY; DB marker 16.1.1.
- Core schema 29/29; full ERP schema 49/49; REST v16 route count 121.
- Deep checks failed: schema_contract_exact_49, supplemental_integrity_scan and pwa_registration_source.
- The supplemental scan says SCAN_INCOMPLETE and read-only. “Non-zero/unknown checks: 0” is not proof of completion when the scan itself is incomplete.
- Ledger references, balance reconciliation, reservations, AP/AR, GL balance, procurement references, authorization-scope table, import contracts and audit-chain hash report PASS individually. These do not override the failed gates.
- Zero active employees needing compensation review is a current-data preflight result, not end-to-end payroll acceptance.

## Key source/CI findings

### 1. Schema exactness is unresolved

The 49/49 full-schema presence check may mean expected tables are present; it does not prove actual table keys equal the release contract. Extra tables, missing/unexpected keys, prefix parsing, or a different deployed source can still fail exactness.

The HR resource-download branch's 16.1.98 builder changes the health check name from schema_contract_exact_49 to schema_contract_expected and changes its target from 49 to 73. The installed screen still reports schema_contract_exact_49. This is a strong runtime source/package/cache parity warning; the exact cause needs deployed hashes and actual table keys.

**Do not simply change 49 to 73 or 82.** Notion Design Reset v0.5/v0.6 says the final physical HR table count is not approved. The four legacy HR tables (employees, employee_compensation, payroll_periods, payroll_lines) remain transition sources until dependency mapping, ERD, copy-only migration, history/GL reconciliation, two parallel payroll periods, backup/restore and rollback pass.

### 2. Supplemental scan is incomplete

Treat it as UNVERIFIED, not as zero defects. A correct scanner needs a unique scan ID; pinned source hash and DB/schema fingerprint; required check list; total/completed/skipped/error counts; per-check terminal state (PASS, FAIL, NOT_RUN, ERROR); resumable batch cursor; and a server-verified final receipt. PASS is allowed only if every required check finishes PASS. Timeouts, failed queries, missing/replayed batches, expired nonce, stale results and interrupted browser work must remain non-PASS. Keep scanning read-only and bounded for shared hosting.

### 3. PWA registration source gate

The runtime reports pwa_registration_source FAIL. Its exact cause is not visible in the health output. On an isolated staging clone, compare deployed hashes and requests for assets/app.js, assets/pwa.js, assets/manifest.json, service-worker URL/scope and front-end enqueue/bootstrap under /stockland-erp-app/. Possible causes include a mixed/stale deployment, registration-token mismatch, incorrect subdirectory scope, or stale cache. Do not clear the live worker or alter URL paths as a guess.

### 4. CI evidence does not certify a product release

- [Run #40411201808 — 16.1.98 exact-package gates](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40411201808) built a ZIP with SHA-256 f38723acb1ca8bdd55720d8a4edd73fdcd5181012905d729e6aa4b6cfc5967e4, then failed exact-package QA: “candidate must not claim production acceptance.” Later lint, tests and artifact upload were skipped.
- [Run #40395915340 — 16.1.98 exact-package gates](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40395915340) concluded success, but baseline fetch, build, exact-package QA, lint, CSS/PAYE tests and upload were all skipped because the builder/QA suite was absent. That run is not product certification; a missing builder must fail the package gate or explicitly yield a non-release result.
- [Run #40597350077 — 16.2.0 full HR/payroll](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40597350077) failed PHP 8.3 and 8.4 matrix jobs with IndentationError: unexpected indent (build_full_hr_payroll_16_2_0.py, line 305); package/test/artifact steps were skipped.
- [Run #40609855986 — workforce vertical slice](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40609855986) passed a limited PHP 8.3/8.4 service/contract slice. It is not full ERP or payroll acceptance.

## Ordered remediation

### P0 — Read-only identity and schema evidence
1. Back up deployed plugin files, database and uploads; verify restoreability.
2. Capture exact installed ZIP SHA-256, per-file hashes, release manifest, schema markers and code hashes for health endpoint, schema installer, UI, PWA assets and manifest.
3. Use the same prefix/parsing rule as System Health to list exact expected and actual table-key sets, missing/unexpected keys, duplicates and prefix differences. Return sanitized aggregate metadata only.
4. Keep the current baseline at 49 until the Owner-approved ERD/migration design fixes an exact target set. No dropping/truncating/renaming/repurposing legacy HR data.

### P0 — Correct scan completion logic
Persist check-by-check status and an immutable final receipt; make batch resume idempotent; test interruption, reload, expiry, timeout, DB error, empty valid table, malformed cursor, missing batch and duplicate replay. A scan cannot pass if any required check is NOT_RUN, ERROR or UNKNOWN.

### P1 — PWA and durable health alarms
Verify subdirectory-safe service-worker registration in browser/network logs on staging. Create deduplicated server-side health events for schema mismatch, incomplete scan, PWA failure and audit failure, with check code, severity, scan/build fingerprint and timestamps; avoid PII and do not require a scheduler to discover a request-time failure.

### P2 — Rebuild and accept the exact package
Fix the 16.2.0 indentation error; remove false-green CI semantics; then run compile, exact-package tests, PHP lint, JS/CSS, payroll calculation, role/location negative tests, all HR lifecycle flows, payout/remittance/GL, resource-download security, PWA, import/POS/warehouse and archive/hash checks on one commit/package SHA.

Finally complete two parallel payroll periods, historical amount/journal reconciliation, backup/restore/rollback, and Owner + HR + Finance and qualified Ethiopian legal/tax/pension sign-off.

## Design and tracking

- [Notion HR Design Reset v0.5](https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d)
- [Notion full HR/payroll scope v0.6](https://app.notion.com/p/4026db0f767e81aaa3ecc4383f5402f9)
- [Notion ERP-wide Deep Audit](https://app.notion.com/p/4076db0f767e815eba7cc49314197c22)
- [Existing audit/SOP documentation PR #46](https://github.com/aistockland-web/Erp-version-16.1.89/pull/46)
- [New remediation issue #47](https://github.com/aistockland-web/Erp-version-16.1.89/issues/47)

**Final decision: NO-GO.** The supplied runtime evidence has unresolved failed gates. The public site could not be fetched from this session; no authenticated live WordPress/DB run or production data modification is claimed.
