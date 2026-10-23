# Stockland Ethiopia ERP 16.1.95 — Full Audit and Release Decision
**Record date:** 24 October 2026  
**Exact source commit tested:** `c80d3efc84d175ec59cb5b8d6992a3ecab744674`  
**Exact-head workflow:** [40036018031 — SUCCESS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40036018031)  
**Artifact:** `STOCKLAND-ERP-16.1.95-Cumulative-WordPress-Update`, ID `12536703884`  
**Installer SHA-256:** `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`  
**Decision:** **The 16.1.95 package, source, code-quality and CI security audit is CLOSED — PASS. Production go-live remains subject to the live-data and owner acceptance gates listed below.**

## Why the earlier status was pending
The earlier 16.1.94 UI workflow passed, but the first security-enriched workflow found two release-check defects. Semgrep had zero targets because the extracted source was underneath an ignored build path. The dependency/license inventory also blocked because the release manifest did not declare the bundled Bootstrap version. The workflow was corrected to scan the exact installer extracted to a temporary path with ignore filtering disabled, require the scanned PHP file count to match the package, and verify Bootstrap version/license/hash against the bundled stylesheet. A later full run passed. The desktop QA receipt was then corrected to derive its release label from the archive manifest, and the resulting exact-head workflow also passed.

## Exact package identity
- Installer size: 1,102,405 bytes; 75 unique files; one safe `stockland-erp-app/` root; 48 PHP files.
- Version markers: plugin header, runtime constant, route/cache marker and release/CSS manifests all say 16.1.95.
- Database marker: 16.1.1; schema contract unchanged at 49 tables: 29 Core/Operations + 16 Accounting/Procurement + 4 HR/Payroll.
- Changed-file allowlist: 14 files; no package paths added or removed.
- The inner ZIP SHA-256 matches the sidecar and release receipt.
- CI artifact bundle SHA-256: `7c9ee6ce4b1969182b20315cb0d68974147100925d84146d87f5fd5ed1005834`; size 1,113,393 bytes.
- No schema migration or business data mutation was performed by package generation.

## Exact-head build, tests and security gates

| Check | Result |
|---|---|
| Locked baseline, build chain, output hash/receipt, ZIP/path/root checks | PASS |
| 49-table schema and release/cache markers | PASS |
| PHP syntax | 48/48 PASS |
| JavaScript, release JSON, CSS modules, PostCSS and source/build parity | PASS |
| Desktop Product Master create flow | PASS, 7 desktop widths, receipt identifies 16.1.95 |
| Reports error/retry/recovery | PASS |
| Requisition approval/history responsive checks | PASS, 7 viewports |
| All work areas and simulated role navigation | PASS, 175 view/viewport pairs, 6 synthetic roles, no measured overflow/console errors |
| Plain/pretty permalink URL regression | PASS, 3/3; query parameters preserved and untrusted origins rejected |
| PHPCS/WPCS | 0 errors, 28 warnings, 1 fixable style/standard item |
| PHPCS security/database source review | PASS |
| Semgrep PHP/security audit | 48/48 packaged PHP targets scanned; 0 findings |
| Dependency/license inventory | PASS; Bootstrap 5.3.8, MIT and hash verified |

The full WordPress Coding Standards report still contains 28 warnings and 1 fixable style/standard item; they have not been described as zero warnings. The targeted security/database review passed. Semgrep's zero findings is meaningful because all 48 PHP files were scanned.

## Corrections included in 16.1.95
1. Quoted MariaDB/MySQL reserved aliases `rows` and `lines` in report queries and all five `row_number` aliases in import SQL.
2. Added the reservations-table binding to the stock/reorder query.
3. Added table/column-existence guards to first activation and deferred role synchronization until the ERP profile table exists.
4. Preserved query parameters for plain-permalink REST routes, retained pretty-permalink behavior and rejected untrusted REST origins.
5. Restricted bootstrap, dashboard insights, SKU/global search and setup-status polling to administrators or an active ERP profile.
6. Required an explicit `business_date` for the daily-close operation; the server no longer silently infers the close date.
7. Mapped a purchase-order over-receipt conflict to HTTP 409 while retaining rollback and HTTP 500 for unexpected errors.
8. Preserved the 49-table contract and historical transaction model; the build does not edit live business records.

## Owner-supplied System Health — 24 October 2026
The supplied screen reports WordPress 7.1.3, PHP 8.4.27, MariaDB 11.4.13, memory 512M, upload 20M / POST 30M, plugin 16.1.95 / DB 16.1.1, 49/49 tables and 104 REST v16 routes. All listed non-payroll readiness checks show PASS, including SSL, XLSX/XMLReader streaming, audit chain, balance reconciliation, ledger references, no negative stock, reservation integrity, AP/AR, GL, procurement, imports, Product Master, warehouse receiving, frontend route and PWA source. Supplemental integrity shows `CHECKS CLEAN` with zero non-zero/unknown counts.

This health screen is owner-provided evidence, not a live query executed by CI. The screen reports database foreign-key metadata as `NOT_ENFORCED`, with no SQL foreign keys observed. That requires a separate orphan review/schema design decision; it does not negate the screen's zero-count supplemental result.

**One listed production gate fails:** `payroll_compensation_classification`. Nine active employee records have no current compensation row. The check correctly did not fabricate salaries or classifications. HR/Owner must resolve the actual records through the existing authorized process. Do not weaken the gate or seed sample salaries.

The supplemental integrity result and audit-chain PASS do not replace the dedicated “Run Deep Integrity Audit” receipt. That separate action still needs a run ID, timestamps, scope and per-check results.

## Owner-provided disposable-local operation report
The separately supplied test report describes 1,000 auth/nonce requests across 104 REST routes, role/location denial, PO exact-once receipt and replay prevention, rejection of over-receipt, stock transfer and POS sale reconciliation, VAT/COGS/GL arithmetic, close-day protection, and 24 UI views with no browser errors/overflow. These are attributed as owner-supplied test results. Its exact tested ZIP checksum was not supplied, so those transaction results are not claimed as independently repeated against the CI installer SHA above. The CI browser tests use mocked in-memory REST and do not connect to production.

## Cloud AI and Management Intelligence
This plugin version has permission-controlled, source-backed deterministic Management Intelligence. It has **no configured external generative Cloud AI provider and makes no external AI requests**. A real external AI integration is not to be advertised as connected unless Stockland separately approves the provider, credentials, privacy/data handling, least-privilege tools, rate limits, audit trail and human approval of proposed business changes. AI must never directly post ledger, stock or payroll mutations.

## Remaining production/owner acceptance gates
The GitHub package/code/security audit is closed for the exact 16.1.95 candidate. These distinct production gates remain open:
- Nine missing active-employee compensation rows — authorized HR/Owner action.
- Independent current Ethiopian payroll/tax/pension rule-to-law approval.
- Dedicated deep-integrity action receipt with run ID, times, scope and per-check results.
- Exact deployed-directory source/hash parity against installer SHA `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.
- Verified backup, restoration and rollback evidence.
- Daily POS/Product Master import replay/idempotency/resume tests against the exact package.
- Audit/ledger failure rollback in a disposable database.
- Full HR lifecycle through final settlement.
- Signed Owner/SOP acceptance tied to the exact installer hash.
- Exact deployed POS/cart handler parity and one-post stock/VAT/COGS/ledger reconciliation.
- `style-src 'unsafe-inline'` remains accepted security debt while seven runtime-computed inline styles remain.
- SQL foreign keys were not observed; complete orphan/schema review remains separate.

**Do not overwrite a currently deployed folder until its file hashes are compared with this exact artifact.** A matching version label does not establish source parity. The nine compensation records must not be auto-filled to make Health green.
