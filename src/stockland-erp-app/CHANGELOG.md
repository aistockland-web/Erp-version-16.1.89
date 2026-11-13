# Changelog

## 16.2.18 — Master installer

- Stock transfers between locations now notify the right audiences at every stage (requested, approved, reserved, picked, in transit, rejected, cancelled) in addition to the existing receipt notice. Notifications are sent after the stock transaction commits and can never roll it back.
- Fixed missing `<?php` open tag in the schema-contract class (source leaked into output; activation and REST responses failed).
- Added `Stockland_Installer`: one activation builds the 49-key operational baseline (29 Core + 16 Finance/Procurement + 4 legacy HR tables) with physical verification; the 37-table HR lifecycle extension and legacy HR column migrations remain gated behind active-Owner fingerprint approval.
- Added Owner go-live authorization bound to version + schema fingerprint, recorded in the audit chain; health `live_ready` now reflects it.
- Health: XMLReader check accepts the supported PHP 8.3 streaming fallback.
- MySQL 8: stock-ledger and GL posting used MariaDB-only `@@session.in_transaction` (every inventory posting failed with HTTP 500 on MySQL 8). Added portable `Stockland_DB::in_transaction()` (MariaDB variable, or tracked state on MySQL).
- MySQL 8 strict mode: sales/expenses list endpoints used '' as a DATE placeholder (HTTP 503 when no date filter); now a valid neutral date.
- Fixed product code-map query (unprefixed tables), duplicate customer code (500 -> 409) and an undefined variable in System Control.

## 16.2.17 — Owner System Control access correction

- Changed the System Control submenu and page gate to the dedicated `stockland_install_schema` capability; Stockland Owner can reach setup without receiving WordPress `manage_options`.
- HR status card now distinguishes missing tables from pending structural column migrations.


## 16.2.16 — WordPress setup and HR access repair

- Added a protected HR/Payroll schema continuation action to WordPress System Control; it is available only after Core and Accounting prerequisites are physically complete.
- Added bounded, physically verified HR setup progress reporting and persisted error diagnostics.
- Fixed browser setup progress detection to include HR structural-column migrations, not only table-count and finance progress.
- Raised the bounded browser-run ceiling to cover the source-declared module inventory while preserving one bounded server-side mutation per request.
- Added a complete-schema guard before mounting the operational dashboard.
- Restored Owner/Management HR entry visibility in the sidebar and command palette while retaining server-side permission checks.
- Removed hardcoded diagnostic table-count fallbacks; schema counts remain source diagnostics, not approval.
- Corrected the Core admin action to match its one-table-per-request label.


## 16.2.15 — cumulative local hardening candidate

- Retains the full 16.2.13 application source tree and existing REST/module boundaries.
- Closes implicit compensation approval defaults and enforces explicit independent approval metadata in the legacy payroll/hire readers.
- Removes production synthetic payroll calculation mode.
- Removes implicit PAYE/pension defaults and historical statutory-date fallbacks; calculation requires an approved, effective-dated rule set.
- Hardens the private HR vault with versioned authenticated encryption, externally managed keyring, key rotation and hash-gated legacy conversion.
- Replaces hardcoded schema-count assertions with source-declared table-key inventory comparisons; preserves the legacy subset and HRX recognition policy.
- Rewrites release, architecture, migration and production acceptance documentation.

## 16.2.14 — earlier local hardening candidate

- Introduced compensation Maker/Checker protections, encrypted vault, health status and HR-card accessibility fixes. Several different 16.2.14 archives were generated during the local process; none is treated as this package's release identity.

### 16.2.18 — Management Intelligence, social analysis and theme fixes
- Management Intelligence: the "Refresh intelligence" button had no click handler; it now reloads authorized insights and the social workspace.
- Social media analysis (official platform sign-in, campaigns, export snapshots) moved out of HR & Payroll into Management Intelligence; HR & Payroll keeps only a link. Credentials, tokens and posting APIs are still never stored.
- Theme colour is now set by the server (`data-sl-theme="sapphire"` on the standalone body; manifest and meta `theme-color` unified to #295ff3), so the Sapphire colours no longer depend on JavaScript running first.
- Manual `watch_time_minutes` snapshots are validated and stored as decimal minutes, not passed through ETB monetary normalization; the snapshot table labels the value in minutes.
