# Stockland ERP 16.2.18 — patch record on the CI-verified delivery head (2026-11-27)

**Base:** `delivery/stockland-full-erp-completion-2026-11-11` @ `03c162a` (PR #154 merge; every check green: PHP 8.3/8.4, MySQL 8 and MariaDB lanes, devcontainer, one-pack publication). Its installer SHA-256 is `96f41b48624c59367a319eba20035a5df68adc680358b1196b97899332409ff0`.
Version stays **16.2.18**. This patch is applied only on that head; nothing from the older `audit` line is carried over.

## Three changes (no schema, REST-contract, ledger, POS, approval or payroll change)
1. `includes/rest/class-rest-import-v16.php` — Product Master **preview** failed for any workbook containing a Barcode or GTIN: `prepare()` arguments were in the wrong order in `product_identifier_owners()`. Corrected.
2. Same file — Product Master **commit** failed for every job: the field loop reassigned the closure `$value` ("Value of type int is not callable"), rolling the job back. Loop variable renamed `$field_value`.
3. CSS (module 14, `app.css`, `app.min.css` regenerated with the repo's semantics-safe minifier so CI parity holds) — WCAG 2.1 AA: small dashboard copy and the notification badge fell below 4.5:1 once the site has data (CI tests an empty site): 5 elements at desktop, the badge at tablet. Colour-only override.

## Verified on MySQL 8.0.46 (disposable site, final bytes)
- Official `verify_master_installer.sh`: PASS; HR lifecycle stays unapproved/fail-closed.
- `e2e/run_all.sh`: **23 suites, 637 PASS / 0 FAIL**, incl. s19 POS hub, s20 payroll release rules (16), s16 payroll cycle (262), s21 requisition approval chain, s22 real multipart Daily POS upload → Branch Manager-only release → sale, ledger, balanced GL, replay-safe. (s22 needs `STOCKLAND_E2E_SITE_URL` and `WP_ENVIRONMENT_TYPE=local` for Application Passwords over HTTP.)
- Product Master import: bad SKU and negative price rejected; STYLE+2 VARIANT with barcodes previews, commits, repeat is idempotent, duplicate barcode refused.
- Browser: `requisition_approval_buttons.js` PASS (Branch Manager → Management → Warehouse Manager → Warehouse Employee → Branch receipt through real buttons). `ui_smoke.js`: fresh site 43 PASS; site with data 0 accessibility violations (remaining failures are the script's HR-pending assumption after suites approved HR, and sandbox-blocked outside resources).
- GitBook read-only bridge 1.0.0 (PR #150, not merged): 401 without token; with token reports ERP 16.2.18 read-only; finds imported SKU; refuses unknown/write tools. GitBook-side registration not performed (needs live HTTPS endpoint + token from the Owner).
- The unpatched CI-verified installer reproduces defect 1 (preview error) on a file with barcodes.

## Not run / not claimed
Docker/devcontainer (no daemon here; CI covers), MariaDB and PHP 8.4 lanes for the patched bytes (CI must run on the PR), live-host parity. Payroll: shipped Owner-declared ET-2025-AMEND-1395 rule set (PR #141) is unchanged; no independent legal opinion is evidenced and nothing was ratified here. Owner, legal, payroll, schema-contract and live-release gates stay with the Owner; no tag or GitHub Release created.

## Installer
`STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` SHA-256 `06c66a0877302c6fa5fffc3263fa1b279e66b729e53deb99b96207ef07e82d23` (128 files).
