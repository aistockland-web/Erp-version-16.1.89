# Stockland ERP 16.2.18 — accessibility rebuild record (2026-11-24)

Version stays **16.2.18** (series 16.1.95 → 16.2.0 … 16.2.18 only; no other version mixed in).

## Change
Full browser test (repo `ui_smoke.js` with axe-core, WCAG 2.1 A/AA) found 5 dashboard text elements at desktop width below 4.5:1 contrast (category row labels, low-stock small copy, first activity row; ratios 2.85–4.43). Fix is presentation-only: one override rule appended to `assets/css/14-table-holders-legacy-colors.css` (colour #536981), `app.css` regenerated from the ordered modules, same rule appended to `app.min.css`. No PHP, schema, REST or business-logic change.

## Result on MySQL 8.0.46 (disposable site)
- Official `verify_master_installer.sh`: PASS (fresh install, HR lifecycle tables still unapproved/fail-closed, live_ready false).
- `e2e/run_all.sh`: 21 suites, 576 PASS / 0 FAIL.
- `ui_smoke.js` on a fresh HR-pending site: axe 0 violations at 390/768/1366 px for both ERP routes; no horizontal overflow; POS Integration Hub, HR forms, table holders pass. Only 5 console errors remain: sandbox-blocked outside resource loads (ERR_TUNNEL_CONNECTION_FAILED), not app defects.
- NOT RUN: container/devcontainer test (no Docker daemon here), MariaDB/PHP 8.4 lanes (CI), click-through of approval chain/POS import by browser automation.

## Installer
`STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` SHA-256 `39e0a91049790c6d6a365c3766108b68fe169102158b40142eb9c7640747d165` (supersedes b0533135…).
Owner, legal, payroll ratification, schema-contract and live-release gates remain closed; no tag or GitHub Release created.
