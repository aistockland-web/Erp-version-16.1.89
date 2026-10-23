# Stockland Ethiopia ERP — Local Recheck Receipt

**Date:** 2026-10-23 (Africa/Addis_Ababa)  
**Scope:** Read-only local validation and focused UI regression test for the existing GitHub 16.1.93 candidate.  
**Important limitation:** These checks are **not** run against the live WordPress/MariaDB database, and this package is not the exact installed 16.1.94 source. This receipt does not authorize installing the historical candidate over the live site.

## Source / release identity

- Live Health screen supplied by Owner: Plugin **16.1.94**, DB **16.1.1**.
- Local package tested: historical GitHub Actions artifact `STOCKLAND_ERP_16.1.93_REPORTS_EXPLORER_CANDIDATE.zip`, extracted as `/mnt/data/historical_16_1_93.zip`.
- Historical candidate SHA-256: `a8cb3767f363740387731928211febc315876a7b8c146e65e594930940e64e3b`.
- ZIP CRC: pass; 75 unique file entries, one `stockland-erp-app/` root, no CI/test files embedded.
- This artifact predates the current GitHub head. Current branch head when checked: `5fa1bdc5bc3219e55c97b47774f6957dafacc417`. GitHub returned **zero check runs** attached directly to that commit; related workflow runs were last created 2026-10-20 against an older build/source state.

## Local checks executed

| Check | Result | Scope/meaning |
|---|---|---|
| ZIP CRC, root and inventory | PASS | Historical 16.1.93 candidate only |
| PHP syntax lint | PASS — 48/48 PHP files | Historical 16.1.93 extracted plugin |
| JavaScript syntax | PASS — `assets/app.js`, `assets/pwa.js` | Historical 16.1.93 extracted plugin |
| JSON parse | PASS — release manifest, PWA manifest, CSS module manifest | Historical 16.1.93 extracted plugin |
| CSS ordered source/build/minified parity | PASS | Used the repository's deterministic concatenation/normalization contract locally |
| PostCSS parse | PASS — app.css, app.min.css, bootstrap.min.css | Historical 16.1.93 extracted plugin |
| Responsive CSS coverage | PASS — 60 media-query occurrences | Static coverage only; not complete UX sign-off |
| Literal production table declarations | 49 distinct `stock_*` declarations detected | Static source count; not live DB verification |
| AP / AR / PLEDGER / GL terms | Present | Preserve these real business workflows; no blanket text removal |
| Uppercase standalone `FL` token | Not found in historical PHP-source scan | Not a replacement for the final-package archive-wide scan |

## Focused local browser test

A temporary copy of the historical candidate's frontend JavaScript was given the same narrow change now committed in the GitHub build script: a report-source failure keeps its inline error and Retry action and returns without emitting a second toast over the form. Local Playwright/Chromium used intercepted in-memory HTTP fixtures only.

- Scenario: successful initial load → user-triggered source failure → retry → successful report render.
- API calls: 3 (initial success, one failure, one successful retry).
- Result: PASS.
- Duplicate error toast: absent.
- Error/retry controls remained within a 390 × 844 CSS-pixel viewport; page scroll width was 390, with no horizontal page overflow in this test.
- Report print/CSV remained disabled during the source failure and enabled after successful retry.
- Uncaught browser errors: 0.
- Live database/business data touched: no.

This is an independent local regression check of the intended JavaScript behavior, **not a build of the exact latest branch ZIP**. Exact-head CI/package receipts remain pending.

## Remaining release blockers — not closed by local checks

1. **Version/source mismatch:** live is reported as 16.1.94, while the accessible review source is baseline 16.1.92 → historical candidate 16.1.93. The live Cart/POS renderer is not verified from this older ZIP.
2. **Cart/POS mobile layout:** live screenshots show serious defects. The historical candidate contains an atomic single-SKU `posForm`, not a multi-line `cartItems`/`cartForm` implementation; this old code cannot safely be claimed as the live cart fix.
3. **Payroll:** live Health reports 9 active employee records missing current compensation. Authorized HR/Owner review against real contracts/effective dates is required; no synthetic salaries or classifications.
4. **HR lifecycle:** workforce planning through final settlement still needs approved-SOP traceability and real workflow acceptance.
5. **Deep integrity:** one screenshot says the deep audit is UNVERIFIED while the pasted Health dump shows separate PASS and bounded-supplemental results. A run ID, timestamp and per-check completion receipt are required; no automatic repair during the scan.
6. **Reports:** the live report source-read error must be diagnosed against the correct installed build/database. The report-error UI change is committed to the 16.1.93 builder, but it does not resolve an unknown live database query failure by itself.
7. **Hosting/source cleanup:** the inspected historical package still contains the “Server environment” heading, “plugin requires 8.4+” wording and older Node.js provenance text in its release manifest. The current hardening builder contains cleanup for these, but the latest exact-head package has not been built and validated here.
8. **Exact-head CI / target acceptance:** no current-head CI receipt; no live WordPress install, DB reconciliation, backup restore or rollback test was performed.

## Code changes committed in GitHub

- `ci/patch_reports_explorer.py`: report-source failures remain inline with Retry rather than also throwing into the same UI submit handler and producing a duplicate toast.
- `ci/qa/test_reports_explorer.py`: assertions enforce the single inline recoverable-error path.
- `ci/qa/test_reports_loading.py`: regression test simulates a user-triggered source failure, checks no duplicate toast, checks mobile bounds/no page overflow at 390px, then proves a successful retry.

Updates remain in the existing draft PR/workstream. No alternative plugin or architecture was created. **Overall disposition remains DRAFT / NO-GO for installing over 16.1.94** until exact source is recovered and critical master acceptance gates pass.


## Second local regression pass — 23 October 2026

This second pass tested a **review-only fixture derived from the previously extracted historical 16.1.93 plugin**, after the report error presentation and responsive requisition approval CSS edits. This is not a verified WordPress installer and is not the exact live 16.1.94 release.

- Review-fixture SHA-256: `767fb1f3703c179f5cce0bfc0391c69220963723c4290b807032e30ed57c08ed`.
- Package: ZIP CRC PASS; 75 unique files; one `stockland-erp-app/` root.
- PHP lint: 48/48; JavaScript syntax: `assets/app.js` and `assets/pwa.js`; JSON parse: release/PWA/CSS module manifests.
- CSS: all six ordered source modules rebuild `app.css` exactly; deterministic normalization matches `app.min.css`; PostCSS parses `app.css`, `app.min.css` and `bootstrap.min.css`; 61 `@media` occurrences.
- Requisition/approval layout: tested 320, 360, 390, 430, 768, 1024 and 1440 CSS px. Fourteen approval rows exercised; zero row-child overflow, zero approver-text/status overlap, and zero browser errors. Desktop/tablet keep two approval cards per row; phones use one card per row. Long labels, notes and decision badges wrap inside the card.
- Whole-workspace smoke: 25 views × 7 viewports = 175 combinations; six synthetic role/capability profiles; zero blank-view failures, page-level horizontal overflow, browser errors or console errors.
- Report recovery: successful initial read → user-triggered report source failure → Retry → success. Three in-memory API responses; no duplicate toast; Print/CSV disabled during the failure and enabled after success; 390px page scroll width remained 390; no browser errors.
- No live WordPress request, live database query, employee or salary review, stock or finance posting was made during these tests.

A first intermediate CSS attempt moved approval status content outside the row at 320px because of a flex-basis interaction. It was corrected, and only the final rerun above is counted as PASS.

### Release status remains NO-GO

These local results demonstrate the targeted front-end behavior in the historical candidate fixture. They do **not** close live CAP-25: the actual 16.1.94 source was not present. They also do not resolve the real report database/source-read failure, nine active employees without current compensation, full HR-to-final-settlement lifecycle, discrepancy between the separate PASS/supplemental health output and the earlier UNVERIFIED deep-audit panel, exact-head GitHub CI, independent stock/ETB reconciliation, or target-host backup/restore/rollback. Do not upload the review fixture as a production plugin.
