# Stockland ERP 16.1.99 HRX Health-Fix Review Audit — 30 October 2026

**Disposition: review/staging only; production NO-GO.** This audit uses the owner's supplied live System Health output and a local candidate built from the locked 16.1.95 source archive. The exact installed 16.1.98 source/ZIP was not available for hash comparison, so this is not represented as a cumulative production update.

## Owner-supplied live health evidence

The owner reports WordPress 7.1.4, plugin `16.1.98-HRX-REVIEW-ONLY`, PHP 8.4.27, MariaDB 11.4.13, DB marker 16.1.1, Core 29/29, baseline ERP schema 49/49, and 121 REST v16 routes. `schema_contract_exact_49` fails; `supplemental_integrity_scan` fails with `SCAN_INCOMPLETE` and “Non-zero/unknown checks: 0”.

That zero is **not a clean result**. An incomplete or unexecuted scan has not established a count, so the result must remain unknown/null with an explicit reason. The exact schema failure cause remains **unconfirmed** because the supplied screen does not include the actual `schema_contract_details` list of missing, unexpected, and HRX extension table keys. A strict exact-49 count that does not separate registered HRX extensions is a plausible failure class, not proven live root cause.

## Locked baseline and reproducibility

- Source baseline 16.1.95 SHA-256: `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.
- Baseline archive: 75 unique files, 48 PHP files, 49 registered/static DDL table keys (29 Core/Operations + 16 Finance/Procurement + 4 legacy HR/payroll).
- Four legacy HR/payroll tables: `employees`, `employee_compensation`, `payroll_periods`, `payroll_lines`.
- The inspected main workflow `.github/workflows/erp-16-1-98-production-build.yml` references `ci/build_release_16_1_98.py` and `ci/qa/test_release_16_1_98.py`; direct file lookups for these paths returned 404 on the inspected main revision. The 16.1.98 release is therefore not reproducible from the paths the workflow currently names.
- The local candidate is built from 16.1.95 and must **not** be uploaded over the reported live 16.1.98. It does not prove preservation of all prior Process Center assets. Recover the exact installed package and merge existing HR resources rather than replacing them.

## Local review candidate

- ZIP: `STOCKLAND_ERP_16.1.99_HRX_HEALTH_FIX_REVIEW_ONLY.zip`
- SHA-256: `e6a647e9def4a33bb75b7ae05078945803454a91691426beab64d648bffbbdae`
- Size: 2,085,551 bytes; 103 unique members; one `stockland-erp-app/` root; 51 packaged PHP files; 24 bundled SOP DOCX files; ZIP CRC passes.
- It preserves the four legacy HR tables as transition sources; legacy HR mutation endpoints are disabled in this review candidate.
- It has 33 proposed HRX logical tables; that is a candidate model count, not an approved final production schema count.

## Local code changes in the candidate

1. The immutable 49-table base contract is checked separately from a registered, allow-listed `stock_hrx_` extension. Missing baseline tables, missing extension tables/version marker, and unknown Stockland-owned table names are exposed separately. The helper does not force a PASS.
2. Read-only baseline integrity joins can run if DB access and all required baseline tables exist, even while an unregistered extra table separately fails the contract.
3. The explicit deep scan runs up to eight SELECT-only integrity checks per HTTP request, using a user-scoped WordPress transient cursor with 15-minute expiry and strict sequential offsets.
4. Partial, incomplete or not-run scans return unknown/null counts and reasons. Zero is valid only after the complete query catalogue executes with known-zero results.
5. Aggregation keeps earlier nonzero and SQL-unknown results through later batches.
6. Explicit terminal health failures and captured PHP fatal errors can create deduplicated internal Owner/Management notifications if the existing notifications table is available. No external email/SMS/hosting monitor is configured; these notifications cannot fire if the platform is not executing PHP.
7. The ERP-wide SOP catalogue is a separate section after existing HR resources; private contracts, IDs, bank details and payslips remain blocked until a verified private vault exists.

## Local tests (not live WordPress E2E)

- HRX domain regression: 727 assertions, 0 failures.
- Schema-contract extension: 12 assertions, 0 failures.
- Internal alarm deduplication: 13 assertions, 0 failures.
- Health/UI contract: 21 assertions, 0 failures.
- Resumable integrity batches: 37 assertions, 0 failures across seven batches, including persistence of an earlier nonzero and earlier SQL-unknown result.
- PHP syntax: 56 files checked (51 packaged application files + five independent QA harnesses).
- `assets/app.js` and `assets/pwa.js` Node syntax: PASS.
- Release/PWA/SOP JSON manifests: PASS.
- SOP files: 24/24 SHA-256 values match manifest.
- Candidate archive: 103 unique members, CRC PASS; QA harnesses are not packaged with the installer.

These checks validate local source/static logic and the selected simulation harnesses only. They do not establish WordPress activation, real MariaDB query compatibility, live transient persistence, REST permissions, downloads, or financial workflows.

## Hosting test status

Target: https://slerp.xo.je/stockland-erp-app/

A direct request in the current environment failed DNS resolution; web fetch returned a cache-miss. No WP CLI, Docker, or MySQL/MariaDB runtime/driver is attached. **Live WordPress/MariaDB E2E is NOT RUN**, not PASS. The owner-supplied screen is evidence input, not independently authenticated output. This audit did not change the live site, schema, payroll, stock, or ledger.

## ERP-wide SOP library

The separate local library has 24 Amharic-first Word SOP drafts with IDs, v1.0-DRAFT status, issue date, owner/approver placeholders, effective-date placeholder, review cadence, steps, controls, evidence, measures and source-table mapping. The map covers 49/49 legacy table names and 33/33 HRX logical table names. This is documentation coverage, not proof of runtime capability. Documents still need native Ethiopian process-owner proofread and operational walkthrough; labour/tax/pension clauses require qualified Ethiopian review. No draft is effective policy until approved.

## Required release gates

1. Recover exact installed 16.1.98 package and source hashes; capture actual `schema_contract_details`.
2. Take verified DB/uploads backup and restore an isolated WordPress/MariaDB clone.
3. Port fixes onto the exact deployed source; preserve all current HR Process Center resources and add SOPs alongside them.
4. Run browser/REST tests for quick/deep health, interrupted/expired cursors, query errors, role/location denials, nonce, invalid paths/IDs, hash tampering, audit-write failure and all 24 downloads.
5. Review complete scan output against stock, reservations, AP/AR, GL/PLEDGER, procurement and payroll history.
6. Complete HRX migration/history reconciliation, attendance/leave, statutory rule approval, payroll, recipient payouts/remittances, final settlement, two parallel payroll periods, backup/restore/rollback and Owner/HR/Finance plus qualified Ethiopian statutory sign-off.

**Final status: local review candidate only; NO-GO for production.**