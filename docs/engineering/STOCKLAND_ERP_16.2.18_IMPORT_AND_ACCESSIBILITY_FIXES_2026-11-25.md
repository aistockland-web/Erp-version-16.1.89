# Stockland ERP 16.2.18 — Product Master import fixes and accessibility rebuild (2026-11-25)

Version stays **16.2.18** (series 16.1.95 → 16.2.0 … 16.2.18 only; nothing from another version mixed in). Supersedes the 2026-11-24 rebuild record.

## Defects found by driving the master-file import end to end (both were present in 16.2.18 as sourced)
1. **Preview failed for any workbook containing a Barcode or GTIN.** `class-rest-import-v16.php` `product_identifier_owners()` passed prepare() arguments in the wrong order (column name placed before the values), so MySQL received a query on a column named after the barcode ("Could not verify Product Master barcode uniqueness"). Fixed by correcting the argument order.
2. **Commit failed for every Product Master import.** In `product_commit()` the field loop reassigned the closure variable `$value` (money / int / text), so the next `$value(...)` call raised "Value of type int is not callable" and the whole job rolled back. Fixed by renaming the loop variable to `$field_value`.
No schema, REST contract, ledger, POS or payroll change.

## Accessibility (presentation only)
Two override blocks appended to `assets/css/14-table-holders-legacy-colors.css` (colour #536981 for small grey dashboard copy on white surfaces); `app.css` regenerated from the ordered modules; same rules appended to `app.min.css`. The second block covers data-dependent elements (activity timestamps, branch rows, KPI meta, block heads) that only render once the site has activity.

## Verified on MySQL 8.0.46 (disposable site, rebuilt ZIP)
- Official `verify_master_installer.sh`: PASS (fresh install; HR lifecycle tables unapproved/fail-closed; live_ready false).
- Product Master import via REST: bad SKU prefix rejected; negative price rejected; valid STYLE+2 VARIANT workbook previews (staged, nothing live), commits (POSTED, 3 rows), repeat commit idempotent, same file re-preview returns existing job, duplicate-barcode workbook refused at commit, no stock created by the import.
- `e2e/run_all.sh`: 21 suites, 576 PASS / 0 FAIL (includes s19 POS Integration Hub: Daily POS upload, Branch Manager-only release, device registration/connection, 41/41).
- GitBook read-only bridge 1.0.0 on the same site: tool calls refused without a valid bearer token (401); with token `stockland_system_status` reports ERP 16.2.18 read-only; `stockland_product_search` and `stockland_sku_lookup` return the just-imported STK-IMP products; unknown/write tool names are refused.
- `ui_smoke.js` (axe WCAG 2.1 A/AA at 390/768/1366 px): fresh HR-pending site 43 PASS, no real failures; site with data and HR approved: no accessibility violations. The only other failures are sandbox-blocked outside resource loads and, on the HR-approved site, the script's own assumption that HR is still pending.

## Not run / not claimed
Container/devcontainer test (no Docker daemon here); MariaDB and PHP 8.4 lanes (CI); browser click-through of the approval chain; GitBook-side registration (needs a live HTTPS endpoint and token from the Owner). Owner, legal, payroll-ratification, schema-contract and live-release gates remain closed; no tag or GitHub Release was created.

## Installer
`STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` SHA-256 `48ea0b80ac33589cef3e29864ab470e7e531ad2932256f8c60e05196c42f292b`.
