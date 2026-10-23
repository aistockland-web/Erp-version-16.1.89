# Stockland Ethiopia ERP — Final Local Recheck

**Date:** 23 October 2026 (Africa/Addis_Ababa)  
**Disposition:** **NO-GO for production update.** These tests ran against a locally reconstructed, historical 16.1.93 review fixture—not the live 16.1.94 source or database.

## Exact fixture identity
- File: `STOCKLAND_ERP_16.1.93_LOCAL_CUMULATIVE_REVIEW_FIXTURE_ONLY.zip`
- SHA-256: `0d2632ad75d6df81c5047b0937b9b551d7b9ee7d03104c8c91458682116e5883`
- Size: 1,101,969 bytes
- Release: 16.1.93
- ZIP: 75 unique files under one safe `stockland-erp-app/` root; CRC passed
- Test hooks are injected only into temporary extracted files, never into this archive.

## Static/package QA — PASS on review fixture
- PHP syntax: 48/48 files.
- JavaScript syntax: `assets/app.js` and `assets/pwa.js`.
- JSON: RELEASE_MANIFEST, PWA manifest and CSS modules manifest parse.
- CSS: six ordered modules reproduce `app.css`; deterministic normalized CSS matches `app.min.css`; PostCSS passes for app.css, app.min.css and Bootstrap.
- Runtime/host hygiene: PHP 8.3 floor and “WordPress hosting compatibility” diagnostics are present; old InfinityFree wording is removed.
- Report guards: ten `report_source_query_failed` guards plus location-label failure guard are present; five Command Center data-source failure guards are present.
- Source cleanup: archive-wide URL scan found zero unapproved URLs. The existing approved logo and required SVG/OpenXML namespace identifiers remain; Bootstrap 5.3.8 attribution and full MIT notice remain.
- Existing contract markers: plugin/root/cache metadata 16.1.93 and DB marker 16.1.1; 49-table contract is retained. No schema/activator or business data was changed by the local check.

These are static/source checks. They do not prove the actual SQL queries succeed or the live installation’s records reconcile.

## Browser smoke — PASS on mocked API responses
A local Playwright/Chromium run executed against the cumulative review fixture. API responses were intercepted and synthesized; no live WordPress REST endpoint or database was called.

- 175 view/viewport combinations = 25 existing work areas × 7 widths (320, 360, 390, 430, 768, 1024 and 1440 CSS px).
- Six synthetic role profiles (Sales Employee, Branch Manager, Warehouse Manager, Management, Employee, Owner); the tested navigation capability assertions passed.
- Management Intelligence separately tested for Management, Owner and Sales Employee: visible for the two authorized roles; restricted message shown to Sales Employee.
- Requisition approval drawer: seven widths; no approver/decision/status overlap, page-wide overflow or supply-table wrapper outside the drawer.
- Reports: successful initial render → induced source error → single inline error/Retry (no duplicate toast) → successful retry. Print/CSV disabled during error and re-enabled after retry. At 390px the document/body width stayed 390px.
- This run recorded zero blank views, overflow failures, page errors or console errors.

Machine-readable receipt: `STOCKLAND_ERP_LOCAL_CUMULATIVE_BROWSER_RESULTS_2026-10-23.json`.

## Production release blockers — not passed
1. The live screen says Plugin 16.1.94 / DB 16.1.1. The accessible source/build base is 16.1.92 and review candidate 16.1.93. The exact installed 16.1.94 ZIP/source/checksum was not available in this repository/library; do not upload or downgrade with this fixture.
2. No exact-head GitHub Actions receipt has been generated for the latest branch head. Existing green runs refer to older commits; the new workflow steps still require an exact-head run.
3. Live System Health reports nine active employees missing current compensation. Authorized HR/Owner must correct actual records against contracts/effective dates—no synthetic salaries or autofill.
4. Complete HR lifecycle from workforce planning through final settlement is not evidenced as implemented and accepted end-to-end.
5. Deep audit receipt remains unresolved: a screenshot shows UNVERIFIED while other lightweight/supplemental checks show PASS. Need one run ID, timestamp and per-check completion record.
6. The live report source-read failure is not diagnosed by a mocked browser test; the database/source query still needs investigation.
7. The actual live 16.1.94 Cart/POS renderer and multi-line handlers have not been matched to the historical source.
8. Full end-to-end stock movement, reservations/transfers/receipts, sales, ETB values, VAT/COGS, AP/AR/PLEDGER/GL, role/location denial, imports/replay, audit rollback and backup/restore were not validated against an authorized database or host.
9. Ethiopian statutory payroll rules still require the effective-date/legal rule matrix and approval by the responsible payroll/tax adviser.

## Final conclusion
Local package/static/browser checks are clean **for this review fixture**. That is not a production certification. Keep CAP-01/02/03/04/05/07/08/09/10/12/13/14/15/16/19/21/22/28/29/30 open pending their objective evidence; report UI portions of CAP-24/25/27 and source cleanup have local candidate evidence but are not fully closed for the installed site. Do not create a WordPress update from this older lineage and label it certified.
