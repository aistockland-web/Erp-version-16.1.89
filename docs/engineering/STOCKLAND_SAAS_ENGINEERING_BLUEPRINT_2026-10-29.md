# Stockland Ethiopia ERP — SaaS Engineering Blueprint and Release Gates
**Date:** 29 October 2026  
**Target:** WordPress-hosted web ERP/PWA; not a separate Node/Python runtime  
**Decision:** engineering candidate / staging only; production remains NO-GO.

## 1. Runtime and hosting contract

The application runtime stays in the WordPress plugin: PHP 8.3+ (validated in CI on 8.3 and 8.4), WordPress REST APIs, and the site's supported MySQL/MariaDB database over HTTPS. Python 3.12 and Node.js 22 are CI/build tools only; neither is required on the shared host. The reported live environment is WordPress 7.1.4, PHP 8.4.27 and MariaDB 11.4.13; those values are user-provided health-screen evidence, not an independently authenticated connection.

Hosting target: HTTPS, PHP 8.3+, MariaDB 10.11+ or MySQL 8+, adequate memory and upload limits, persistent read/write access for the plugin's runtime files, and a database backup/restore path. This aligns with the official WordPress hosting recommendation: https://wordpress.org/about/requirements/.

Do not introduce a separate app server, Node worker, scheduled job, trigger, Cloud AI provider, or hidden runtime dependency to meet the UX goal. Import/reconciliation operations must be chunked and resumable where the hosting request limit requires it.

## 2. UI architecture: local Bootstrap and ordered Stockland modules

**Adopted framework decision:** keep the pinned local Bootstrap **5.3.8** asset. It is the latest stable 5.x patch shown in Bootstrap's version list; Bootstrap 6 is listed as alpha, so it is not the safer release target for this production-oriented WordPress plugin. Do not load Bootstrap from a CDN. Verify the bundled bytes against the SHA-256 in `RELEASE_MANIFEST.json` on every build. Sources: https://getbootstrap.com/docs/versions/ and https://blog.getbootstrap.com/2025/08/25/bootstrap-5-3-8/.

The UI is divided into 12 ordered CSS source modules, compiled in exactly this order:

1. `01-foundation.css` — design tokens, typography, base shell reset, focus treatment.
2. `02-shell-navigation.css` — app shell, top bar, sidebar and navigation.
3. `03-content-data-components.css` — cards, tables, forms, data visualization surfaces.
4. `04-overlays-workflows.css` — dialogs, drawers, tooltips, notifications and action overlays.
5. `05-responsive-layout.css` — breakpoint and mobile-layout contracts.
6. `06-operational-guards.css` — remaining operational and health view protections.
7. `07-dashboard-operations.css` — data-backed dashboard and navigation refinements.
8. `08-reporting-worksheet.css` — spreadsheet-like reports, print states and KPI layout.
9. `09-command-center-theme.css` — command-center visual hierarchy and theme surfaces.
10. `10-bootstrap-desktop-search.css` — Bootstrap bridge, desktop search, toolbar, hit-target and cascade guards.
11. `11-operational-workflows.css` — requisitions, wholesale, POS and operational workflow refinements.
12. `12-hr-payroll-lifecycle.css` — HR lifecycle forms, decision states, payroll run/reconciliation views.

The split is made at existing comment section boundaries and the release builder regenerates `assets/app.css` from the manifest. The generated order is a hard contract; CSS refactors must not reorder modules casually.

The old global regex minifier removed whitespace around punctuation. That is unsafe because whitespace can be meaningful in descendant selectors and values such as `calc()`, and quoted content must remain byte-stable. The 16.2.0 builder now removes comments and collapses whitespace only outside quoted strings. PostCSS must parse source, compiled, minified and Bootstrap CSS; CI checks source/build parity, minifier fixtures, responsive/touch-target selectors, keyboard focus and reduced-motion rules.

**Python does not replace browser CSS.** Python is the deterministic build-time tool that partitions CSS source, assembles the module manifest, emits compiled assets and rejects mismatches in CI. The browser receives CSS and JavaScript only. Never generate runtime CSS by inserting arbitrary `<style>` blocks from app JavaScript.

Use semantic tokens for color, spacing, radius, type, elevation, state and focus. Feature styles belong in their module, not as new tail overrides in an unrelated guard file. Preserve CSS scope under the Stockland app root where possible so WordPress theme/Elementor styles do not override the ERP shell.

## 3. Frontend interaction and accessibility

- Use responsive design as a data contract: 320–390 px phones, tablet, and desktop must not hide actions, clip search results, or make critical forms wider than the viewport.
- Use real button/link semantics, visible keyboard focus, reduced-motion support, input labels, readable error states and announced asynchronous results. Target WCAG 2.2 AA review; this is an acceptance target, not a claim of certification: https://www.w3.org/TR/WCAG22/.
- Keep tables as real tables for desktop/print and use responsive card/row treatment on small screens when appropriate. Do not make spreadsheet reports visually attractive while removing accessible labels/headers.
- Avoid runtime style injection. UI logic must consume API data and use component classes defined in the source modules.
- Search, modals and notifications are bounded to the ERP shell; no duplicate command-center/menu regions or repeated toast copy.

## 4. WordPress/backend boundaries

- WordPress owns identity, sessions, REST routing, capabilities, nonces and lifecycle hooks. The REST API follows WordPress's resource-method patterns: GET for reads, POST for creates/actions, PUT/PATCH for updates where supported, and DELETE only when the domain supports a safe deletion policy. https://developer.wordpress.org/plugins/rest-api/
- A nonce is CSRF protection, not authorization. Every route must have a permission callback plus a callback-level check for capability, active ERP profile, location/branch scope, row ownership/scope, valid state transition, and PII access.
- Controllers validate and authorize input, then call domain services. The browser never posts arbitrary ledger lines or decides final totals.
- Money uses the shared decimal/minor-unit helpers; inventory uses immutable signed movements and explicit reservations; approval/posting flows are idempotent and transactional; failures roll back and must not be converted to zero/empty success.
- Preserve the source-of-truth rules: one Stockland company, Central Warehouse plus Goro, Gerji, Mexico and Megenagna; branch/location permissions are checked server-side. Central Warehouse remains a first-class inventory-control location.
- Use prepared SQL, allowlisted state transitions, immutable posted records, separate correction/return/refund records, and source-backed per-location reports. Follow WordPress PHP/coding/security guidance: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/ and https://developer.wordpress.org/plugins/.

## 5. Data, schema and migration discipline

The live user-provided health screen expected an exact 49-table inventory and reported `schema_contract_exact_49` failed. The full HR/payroll candidate proposes an 86-table inventory (29 core/operations + 16 accounting/procurement + 41 HR/payroll). That is a **provisional candidate model**, not authority to migrate a deployed database to 86 tables.

Before any migration:
1. Recover the exact deployed plugin ZIP and hash every runtime file.
2. Restore a staging clone of the real database and uploads; record table-name and column/index fingerprints without exporting personal data.
3. Produce an ERD and dependency map showing shared users, roles, branches/locations, attachments, workflows, audit, GL/AP and legacy payroll reuse.
4. Keep `employees`, `employee_compensation`, `payroll_periods` and `payroll_lines` intact and read-only for transition until historical reconciliation, two-period parallel payroll, backup/restore, rollback and separate cleanup approval.
5. Require an additive, idempotent migration; no DROP/TRUNCATE/rename/repurpose during the first production migration.
6. Obtain Owner/Architecture approval before treating any table count as a production contract.

## 6. Audit chain, transactions and integrity scan

Audit event hashes are chained in ascending ID order. The synthetic CI regression accepts a valid three-event chain and rejects both a changed event payload and a changed previous-hash link. This proves the verifier behaves correctly against controlled synthetic input only; it does not prove the production audit table is intact.

The comprehensive relational scan remains read-only and fails closed: query errors become UNKNOWN and a scan with unknown checks is not healthy. On shared hosting, a deep scan must report source/schema fingerprint, run ID, started/completed timestamps, total/completed/skipped/error counts, check-level PASS/FAIL/NOT_RUN/ERROR states, cursor/batch progress and a durable final completion receipt. No timeout, interrupted page, missing batch or unknown result may be converted to PASS or zero.

Health alarms are a separate requirement: persist a deduplicated server-side event when schema parity fails, deep scan is incomplete, the PWA source/scope check fails, audit-chain verification fails or a posting reconciliation fails. Include severity, code, time, deployment/source fingerprint and resolution state without writing salary/TIN/employee PII into logs. Do not depend on WP-Cron or a background worker; trigger recording from explicit health/operational actions and use existing audited storage only after its integrity has been verified. A broken audit chain must not be silently “repaired” by logging into the same chain.

## 7. PWA and caching contract

- Keep the PWA online-first for all transactional ERP actions. Do not cache REST business data, salaries, identity documents or private resources in the service worker.
- Register only under the verified ERP page path with a matching `Service-Worker-Allowed` header and same-origin asset URLs. The actual site path is `/stockland-erp-app/`; other supported path variants require explicit tests.
- Cache version is derived from per-file asset fingerprints. Update/deactivation must remove only Stockland-owned caches and must not control the rest of the WordPress site.
- In staging, test manifest response, worker response MIME, registration scope, script URL/query parameters, cache version transition and update/unregister behavior across a real browser, including subdirectory permalinks and HTTPS.
- The source-level `pwa_registration_source` check cannot prove runtime registration. The live health screen reported FAIL; source parity and browser evidence remain required.

## 8. CI and release gate policy

Every candidate package must pass all of the following on the exact output ZIP, not just repository source:

1. Locked baseline SHA, ZIP CRC, safe archive root, unique file inventory and deterministic receipt.
2. PHP lint on every packaged PHP file; JavaScript syntax check.
3. Bootstrap version/hash, ordered CSS manifest, `app.css` source parity, safe minifier fixture, PostCSS parse for all CSS assets and CSS module responsive/accessibility guards.
4. REST contract/security checks, payroll fail-closed synthetic rules, private-vault path/type/size/hash behavior, no hard-coded tax rates or exemption caps, no unexpected external AI/CDN runtime.
5. Synthetic audit-chain tamper/continuity tests; deep integrity results never mislabeled if incomplete.
6. Schema-change disclosure, migration approval state and `production_ready=false` until external cutover evidence is attached.
7. An isolated WordPress/MariaDB end-to-end test with role/location denial, inventory receiving/transfer/requisition, POS sale/refund/close, imports, AP/AR, GL/PLEDGER reconciliation, HR lifecycle, payroll run, payout, remittance and rollback.
8. Backup → restore → repeat functional tests; Owner/HR/Finance and qualified Ethiopian statutory sign-off.

Static CI does not replace staging E2E. A skipped gate is NOT PASS. The optional PHPCS recheck was skipped in the latest successful candidate run and remains a known CI gap.

## 9. Current result and open blockers

The 16.2.0 package passed CI on PHP 8.3 and 8.4, including exact-package acceptance, all packaged PHP syntax checks, CSS/PostCSS checks, Bootstrap SHA verification, synthetic payroll-calculator tests, and the synthetic audit-chain regression. The CSS module build now emits 12 ordered CSS modules and an 86-file plugin archive. Candidate ZIP SHA-256 is recorded in the CI artifact/receipt.

Production remains NO-GO because the latest live health evidence still reports the 49-table schema-contract failure, `supplemental_integrity_scan=SCAN_INCOMPLETE`, and `pwa_registration_source=FAIL`. Source hashes of the installed build were not available through an authenticated live session. The candidate's proposed 86-table schema is unapproved. No live WordPress activation, production database mutation, actual live audit-chain check, production order/stock/AP/AR/PLEDGER reconciliation, browser PWA scope check, two-period payroll comparison, backup/restore/rollback drill, or stakeholder acceptance was performed by these builds.

## 10. Engineering standards used

- WordPress hosting requirements: https://wordpress.org/about/requirements/
- WordPress Plugin Developer Handbook: https://developer.wordpress.org/plugins/
- WordPress REST API: https://developer.wordpress.org/plugins/rest-api/
- WordPress coding standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/
- Bootstrap versions / v5.3.8 announcement: https://getbootstrap.com/docs/versions/ and https://blog.getbootstrap.com/2025/08/25/bootstrap-5-3-8/
- OWASP ASVS 5.0.0: https://owasp.org/www-project-application-security-verification-standard/
- WCAG 2.2: https://www.w3.org/TR/WCAG22/
