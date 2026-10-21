# Stockland ERP — Consolidated Full-System Audit & Acceptance Plan
**Target branch:** `feat/reports-explorer-16-1-93`  
**Release candidate under review:** 16.1.93  
**Baseline:** audited canonical 16.1.92 installer  
**Disposition:** consolidated review plan; no live deployment, live-data mutation, schema migration, or release certification.

## 1. Source identity and checks already observed

- Baseline ZIP: `STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip`
- Baseline SHA-256: `adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd`
- Baseline archive: 75 files under one `stockland-erp-app/` root; 48 PHP files.
- Required database contract: 29 Core + 16 Accounting/Procurement + 4 HR/Payroll = 49 tables.
- 16.1.93 candidate remains on the existing draft PR; do not create a separate release for each defect.
- Local inspection of the downloaded candidate: 75 files, 48 PHP files; PHP syntax pass 48/48; `assets/app.js` and `assets/pwa.js` parse with Node; release manifest, PWA manifest, and CSS module manifest parse as JSON.
- Existing GitHub candidate CI, desktop click regression, deep-source/security and enterprise-security gates reported success for the PR head. These are source/package gates, not authenticated live ERP acceptance.

## 2. Binding business specification

Use the existing Stockland workbook/specification as business truth. Do not invent product identities, SKUs, stock, prices, tax rules, branches, or business processes. The single company is STOCKLAND ETHIOPIA with Central Warehouse and Goro, Gerji, Mexico and Megenagna branches. Excel is an import/export/backup/analysis medium, not the runtime inventory database.

Rules to retain and prove:
- Immutable `variant_id` is product/variant identity; SKU is unique and controlled, with history for replacement.
- Immutable stock ledger is authoritative; derived balances must reconcile to it.
- Available stock must account for reservations; do not overwrite physical stock with reserved quantities.
- Product variants support size/color and correct SKU/barcode/location associations.
- Retail sales follow the approved POS source workflow; do not introduce a bypass path.
- Purchase cost/COGS is staged at transaction/import time and must not silently change with current Product Master cost.
- Master selling price is a default; actual negotiated sale price is captured per sale and must not overwrite the master or history.
- VAT is centrally controlled. Sales must clearly distinguish VAT included/without VAT using the approved business rule.
- Requisition/transfer progression is enforced consistently by service and UI, with atomic inventory movements, checked writes and audit records.
- No success response may be returned before the transaction/commit and persisted state are verified.
- Every adjustment, return, damage, void/reversal and approval must preserve reason, source, actor, time and audit chain.
- Management can see authorized company-wide data; operational users are limited by role and assigned location.

## 3. Initial findings / gaps that cannot be called complete yet

### P0 — Live acceptance and accounting truth
1. No authenticated installed-site/production acceptance has been performed in this work. Static code and mocked-browser checks cannot certify 100% operational accuracy.
2. The prior live System Health snapshot reported seven active employees without a current compensation record. This is a real data-integrity gate and must remain visible until records are reviewed/corrected by authorized staff. No salary or employee record may be silently modified.
3. The accounting/stock system must pass transaction-level reconciliation using approved source workbook fixtures and independent expected values: stock movement ledger ↔ balances; sales ↔ tender/receivables; COGS ↔ staged cost; VAT ↔ tax breakdown; AP/AR and payments ↔ control accounts; journal debits = credits; opening/closing ↔ posted transactions.
4. Static absence of SQL foreign-key metadata was reported in the supplied health context. Application-level integrity checks and transactional services must be verified for every relationship; do not claim database-enforced foreign keys unless verified.

### P1 — Front-end polish and all-button integrity
1. The current candidate has a premium shell and responsive layout, but no exhaustive evidence yet that every view is visually consistent or that every visible button works in every role and viewport.
2. Existing desktop Product Master click verification covers seven wide widths and a mocked create/save chain under an injected overlapping-search layer. It does not prove the actual host theme is the source of the production issue.
3. A whole-app button inventory is still required: enumerate buttons/links/submit controls by view and role, map each to its handler/API/permission, then verification success, validation failure, server failure, duplicate click, and unauthorized access. Unbound controls and handlers that report false success are P0/P1 defects.
4. Validate page/container hierarchy across dashboard, Product Master, Inventory, Daily Inventory, POS, Warehouse, Requisitions, Transfers, Returns, Finance, HR/Payroll, Reports, Users, Settings, Audit and System Health. No naked data grids: each grid needs a consistent page title, toolbar, filter row, action placement, table/card holder, loading/empty/error state, pagination where needed, and usable print/export. Preserve the user's existing scoped spreadsheet-style data grids and Times New Roman requirement where already specified; do not globally replace the app font.
5. Validate large desktop widths (1024, 1280, 1366, 1440, 1920, 2560, 3440), normal laptop widths, and mobile; verification browser zoom, keyboard navigation, focus states, menu/modal stacking, overflow, print and responsive headers.

### P1 — Management intelligence and report accessibility
1. Reports Explorer has a selected report type, date range, authorized location filter, optional SKU search for supported report types, selected-report Print/Save PDF, CSV, and server-side permission gates. Validate every report type's data source and totals against the canonical tables before treating results as authoritative.
2. The present source contains dashboard insights, but a dedicated management-intelligence WordPress page with role-gated decision workflows has not been evidenced by this audit.
3. Critical reorder must be Owner-only at both REST permission callback and handler, use location-specific Product Master reorder levels, show physical/reserved/available quantities separately, and be read-only until an explicit authorized replenishment workflow is invoked.
4. Every report needs a named source/definition, date semantics, location scope, currency/tax semantics, totals reconciliation, drill-down, permission contract, and print/PDF layout verification. Reports must not accidentally expose cost/profit, payroll, staff, or cross-branch data.

### P1 — User profile menu and external integrations
1. Appearance preferences currently include browser-local theme handling. Audit the native ERP profile dropdown for profile, preferences, notifications, session/security, help, and sign-out, and verify each action is real and permission-safe.
2. Source scan did not find a working native Microsoft Teams/Slack OAuth integration. Treat this as not implemented until provider connection, token storage, revocation, per-user consent, tenant/admin policy, webhook validation, message permissions and failure behavior exist.
3. A Microsoft email login is not achieved by merely adding a Teams button. It requires a configured Entra ID/OIDC application, redirect URI, tenant policy, secret/certificate management, account-linking rules, CSRF/state/nonce validation, logout semantics and a verified fallback. Slack requires its own OAuth app/scopes. Do not hardcode credentials or claim provider login works without owner-provided provider configuration.
4. Popup chat/collaboration must be a real, role-scoped feature with user identity, authorization, persistence/retention policy, notification rules and audit trail—not a decorative popup or client-only message list.

### P1 — ERP isolation, security and compatibility
- Verify ERP user login and business roles remain distinct from WordPress platform administrator privileges.
- Check every REST route has an explicit permission callback; verify request fields are validated and location/user scope is re-derived server-side.
- Check nonce/authentication, IDOR, stored/reflected XSS, CSRF, SQL injection, mass assignment, output escaping, file upload limits/type handling, path traversal, rate limiting and error disclosure.
- Check idempotency and duplicate submissions for sales imports, receiving, transfers, requisitions, payments, journals, returns, adjustments and closing.
- Verify transaction boundaries/row locks and rollback on audit or ledger write failure; check concurrent requests cannot oversell or over-reserve.
- Preserve Wallet+ and other WordPress plugins: no global login redirect, broad service-worker scope, API caching, global CSS/JS removal or admin takeover.
- Verify PHP 8.3/8.4, MariaDB/MySQL target compatibility, hosting limits, install/upgrade/recovery behavior, PWA static-only caching, no external runtime dependencies unless approved, and backup/restore/rollback.

## 4. Required reconciliation suite

Use deterministic synthetic fixtures only in isolated verification databases; never seed production. Each verification must state its input, expected result, observed result, tolerance/rounding rule and ledger references.

1. Money arithmetic: decimal-safe rounding; VAT included/excluded; discounts; price overrides; line vs invoice rounding; refunds/reversals; ETB display and storage units.
2. Stock: receive → reserve → pick → in-transit → branch receive → sell/return/damage/adjust; replay/duplicate event; partial receipt; interrupted transaction; negative stock and over-reservation guards.
3. Procurement and finance: PO → GRN → supplier invoice → payment; customer invoice/payment; expenses; AP/AR aging; account mapping; balanced journal and control-account reconciliation.
4. Daily POS import: duplicate file, duplicate source line, unknown SKU, branch/date mismatch, repeated chunk, resume, rollback, staged cost and approved commit.
5. Closing: opening balance + posted ins/outs = closing balance; block/flag unposted, duplicate or out-of-scope transactions.
6. Payroll: statutory formula regression and compensation classification; missing compensation remains a visible error, never silently repaired.
7. Reorder: branch-specific threshold, reserved stock, transfer in transit, central availability, duplicate alert suppression, owner-only view, no implicit purchase-order/stock mutation.
8. Reporting: branch performance, daily/monthly/yearly sales, net sales, VAT, gross profit, inventory valuation, transfers, requests, reservations, returns, discounts/price overrides, reorder, audit history; report totals reconcile to source ledgers.

## 5. One consolidated release workflow

- Keep work in the existing 16.1.93 draft PR/branch; do not mint a version for each defect.
- First finish the audit matrix and reproduce findings; prioritize P0 data integrity/security, P1 broken workflows/accessibility, then visual polish and integrations.
- For each fix, add a regression verification and update this same audit document/PR. Do not weaken unrelated checks.
- No schema changes unless a verified P0/P1 finding requires one, with migration, backup, compatibility and rollback proof.
- No CSS changes unless a measured visual/accessibility issue is demonstrated at relevant viewports; when needed, update ordered source modules and compiled CSS together and prove parity.
- Build one candidate ZIP only after the consolidated fixes, with immutable baseline hash, changed-file allowlist, archive inventory, dependency manifest, checksums, QA receipts and a complete change log.
- Require a review of the final diff and the candidate artifact, then installed-site acceptance with authenticated Owner, Management, Branch Manager, Warehouse Manager and Employee accounts, plus explicit cross-location denial checks.
- Do not merge/deploy until installed-site reconciliation, UI/button matrix, backup/restore, rollback and owner approval pass.

## 6. Current conclusion

**Status: NOT YET CERTIFIED FOR PRODUCTION.** The 16.1.93 source/package gates and mocked desktop regression are useful evidence. They do not yet prove complete financial/stock accuracy, the actual host's desktop behavior, a dedicated management-intelligence page, or native Teams/Slack integration. The remaining work is consolidated under this single PR and acceptance plan; do not create separate releases for individual defects.


## 7. Added requirement — Stockland-owned ERP design system and integrated operations

### Design direction and originality

Build one consistent Stockland Ethiopia ERP experience informed by proven enterprise interaction patterns: SAP Fiori's role-based, adaptive, task-focused approach; Odoo's modular work-area model; and the dense financial/workbook workflows commonly found in enterprise suites such as Oracle NetSuite. These are references for usability patterns only. Do **not** copy vendor logos, branded assets, proprietary page layouts, names, or exact styling. The visual identity, table behavior, page hierarchy, states, and interaction details must be Stockland-owned.

Acceptance characteristics:
- One shared application shell, navigation model, global search/command entry, page-title/action area, responsive layout, and consistent permission-aware status vocabulary.
- Role-based workspaces for Owner/Management, branch operations, Central Warehouse, Finance, HR/Payroll, and Audit. Hide or disable an action according to permission, but always enforce permission and location scope on the server.
- A reusable table system with an explicit page container, title, toolbar, filters, column/row density controls where appropriate, sortable headings, pagination, totals, empty/loading/error states, keyboard/focus states, responsive overflow, and print/export where permitted. Do not render naked tables without a context holder. Preserve useful spreadsheet-style grids for Product Master, Inventory, Daily Inventory, and reports.
- Clear distinctions between editable draft, pending approval, posted/immutable, reversed, failed, and syncing states. Show when data was last refreshed and whether a figure is live, derived, or a saved snapshot.
- Responsive desktop/tablet/mobile behavior, no blocked controls beneath popovers, no sticky table/header layers that obstruct mobile content, and no global CSS/JS behavior that damages WordPress or Wallet+.
- Build with Stockland's existing visual identity and accessible contrast. No broad restyling, typography replacement, or compiled-CSS edits without measured evidence and source/compiled parity checks.

Reference: SAP describes Fiori as a consistent, role-based, adaptive, task-focused design system, not a single copied screen: https://experience.sap.com/fiori-design-web/explore_category/sap-fiori/ . The Stockland implementation must remain an original design.

### Native module integration matrix

“Integrated” means modules share canonical records and verified business events, not merely that they link to one another or display similar dashboards. Reuse current tables/services where they are authoritative; do not create competing writable copies of inventory or ledger state.

| Business event / module | Required downstream consistency | Required evidence |
|---|---|---|
| Product Master / variant creation | One canonical variant identity; unique SKU; controlled size, color, barcode and status; available in authorized catalog/search flows | Create/edit/disable checks, duplicate-SKU rejection, catalog refresh and audit event |
| Purchase / receiving / GRN | Supplier and receipt references; posted stock ledger movement; branch/warehouse balance; staged cost; payable impact only at the correct approved invoice/posting step | Partial/full receipt, duplicate receipt, rollback and supplier/AP reconciliation |
| POS retail / wholesale sale | Sale lines, actual negotiated price, tender/receivable, tax split, stock ledger movement, staged COGS and journal mapping stay linked | Cash/card/credit cases, discounts, override, refund, idempotency and sales-to-stock-to-GL reconciliation |
| Reservations / requisitions | Reserved quantity separate from physical on-hand; available-to-pick calculation; approval limits and branch scope maintained | Competing requests, over-reservation rejection, cancellation/release and unauthorized-location checks |
| Stock transfer | Source dispatch, in-transit quantity, destination receipt/variance, ledger events and audit history are one traceable workflow | Partial dispatch/receipt, loss/variance, duplicate receive, cancellation rules and location totals |
| Returns / damage / adjustments | Reference original transaction where applicable; approval and reason; reversal or compensating movement; no destructive edit to posted history | Return limit, damaged stock, reversal, duplicate action, permissions and ledger replay checks |
| Supplier invoices / AP / payments | Approved invoice and payment references; open balance changes once; cash/bank/AP ledgers and control accounts reconcile | Partial payment, duplicate payment prevention, supplier balance aging and balanced journals |
| Customer credit / AR / receipts | Customer invoice/credit, collection, outstanding amount and control-account totals reconcile | Partial receipt, overpayment/credit handling, reversal and AR aging |
| Expenses / tax / general ledger | Central accounting mappings and effective-dated tax settings; posted journals immutable; every journal balances | VAT included/excluded scenarios using configured rate, rounding boundaries, corrections by reversal and trial balance |
| Daily closing / imports | Opening + posted movements = closing; duplicate files/rows blocked or idempotently identified; unresolved exceptions visible | Repeat import, resume/chunk, unknown SKU, branch mismatch, interrupted commit and close-blocking checks |
| Critical reorder / replenishment | Location-specific reorder point; show on-hand, reserved, available, inbound, demand/backorders, and proposed quantity separately; no hidden stock mutation or auto-order | Boundary values at/below/above threshold, in-transit stock, zero/negative anomalies, duplicate alert control and Owner-only authorization |
| HR / payroll | Approved compensation source, period, calculations, deductions and journals are traceable; missing compensation is a blocking visible exception | Deterministic payroll fixtures, effective-date and rounding checks; never silently invent missing salaries |
| Reports / management intelligence | Named definition, date basis, location scope, currency/tax/cost semantics and source references; drill-down totals match canonical posting sources | Report-by-report expected totals, role/location disclosure checks, print/PDF and CSV comparison |
| Audit / identity / notifications | Actor, source event, time, location, correlation/idempotency key, approval/reversal, and integration outcome are attributable | Tamper/permission checks, revoked identity, failed delivery, retry without duplicate posting |

### Canonical calculation contracts

Do not use binary floating-point for money or hide rounding policy in the UI. Use decimal-safe server-side arithmetic and an explicit, documented currency precision and invoice/line rounding policy. Store the policy/configuration version with posted tax/accounting records when required for reproducibility. The front end may preview calculations, but the server is authoritative.

At minimum, display and reconcile these stock measures independently:
- **Physical on hand:** net of posted stock-ledger movements only.
- **Reserved:** active reservations, released/cancelled reservations excluded.
- **Available:** sellable available stock follows the operational rule max(0, physical on-hand less active reservations and other explicitly modeled commitments). If active reservations exceed physical on-hand, flag an explicit `OVER-RESERVED` integrity exception instead of hiding the discrepancy.
- **Inbound / in transit:** shown separately and never treated as pickable physical stock before destination receipt is posted.
- **Reorder position / proposed replenishment:** calculated from the configured location-specific policy and clearly show every included input (on hand, reservations, eligible confirmed inbound, open demand/backorders, reorder point/target, pack/MOQ if configured). Do not silently treat a transfer or purchase as received. The precise reorder formula and treatment of inbound/backorders must be approved in the business specification and locked by boundary checks.

For every posted sale, calculate sales/tax/tender/receivable/COGS using the transaction's approved price, tax configuration and staged cost; a later Product Master edit must not rewrite historical results. Refunds, voids, returns and corrections must be traceable compensating entries. Every posted general-ledger journal must balance within the defined minor-unit policy; stock subledger, inventory valuation, COGS, VAT, cash/bank, AP and AR must reconcile to their control accounts.

### Shared service and integration boundaries

- All modules call shared server-side domain services for product identity, stock posting, reservation, transfer, sale posting, money/tax calculations, journal posting, and audit. UI endpoints must not independently reproduce financial or stock formulas.
- Use atomic transactions, idempotency keys/source IDs and duplicate protection for externally imported or retried events. If any essential ledger/audit write fails, roll back the business transaction or leave a clearly recoverable, non-posted state—never claim success.
- Derived balances/reports are rebuildable projections of authoritative posted records; identify the source query and reconcile projections. Do not allow an editable summary table to drift from its ledger.
- Define a consistent integration registry for each connector: provider, purpose, status (**Available**, **Configuration required**, **Planned**, **Failed/Disconnected**), credentials location, scopes, last-success time, last error, retry/duplicate semantics, health verification and revoke/disconnect action. Never represent a planned connector as active.
- Built-in import/export, printing, barcode, POS import and internal ERP module integration each need their own verified status. External API/SSO/chat integrations must remain isolated adapters so a missing provider secret does not disable core sales, stock, finance or ERP login.

### Microsoft / Slack / collaboration boundary

Microsoft sign-in must use a configured and validated Microsoft Entra ID OpenID Connect/OAuth flow; Teams notifications/chat are separate Graph/Teams permissions and functionality. Slack requires its own OAuth install/consent flow and minimal approved scopes. Tokens/secrets belong server-side, are encrypted/protected, never included in a browser bundle or logs, and must support revoke/disconnect, state/nonce validation, tenant restrictions, scope review, permission checks, retries and audit. Owner-provided app registration details, client IDs, redirect URIs and secret/certificate configuration are required before an external tenant integration can be truthfully marked connected. A decorative login button, link to the provider, or browser-only message panel is not an integration.

References: Microsoft identity platform OIDC protocol: https://learn.microsoft.com/en-us/entra/identity-platform/v2-protocols-oidc ; Slack OAuth v2: https://docs.slack.dev/authentication/installing-with-oauth/ .

### Production accuracy exit criteria (no “100%” claim without receipts)

For each finance, stock, reorder and ledger scenario, maintain a versioned fixture with source/input data, expected result calculated independently, observed result, acceptable rounding rule, source-ledger/reference IDs, permission context, and pass/fail. Include boundary, duplicate, retry, concurrency, cancellation, reversal, partial completion, and failed-write cases. Reconcile a representative end-to-end flow from source action through stock ledger, balances, customer/supplier subledgers, tax, cash/receivable/payable and general ledger. Compare both totals and transaction-level references.

Release gate: **zero unexplained reconciliation differences, zero unhandled critical transaction failures, zero unauthorized cross-location/financial disclosures, no false-success response, and all verification failures documented/resolved or explicitly accepted by the Owner.** This is an acceptance target, not a claim that the current candidate has already passed. If source evidence, provider credentials, or target-host access is absent, record the gate as **Blocked / Not verified**, not Pass.



## 8. Consolidated stock / reorder correctness correction

The installable WordPress update archive is corrected across the Reports Explorer, dashboard stock summary, live stock list and live reorder-status endpoint so these views use consistent operational stock definitions:

- Physical stock sums posted ledger movements linked directly to a variant and legacy SKU-only movements that can be resolved to the canonical Product Master variant.
- Active reservation totals include both variant-linked and legacy SKU-only reservation rows. Released/cancelled reservations are excluded.
- Physical, reserved and sellable available quantities remain distinct. Sellable availability is `max(0, physical - active reservations)`; when reservations exceed physical stock, the result also emits `OVER-RESERVED` so the integrity exception is visible instead of silently displaying a negative available figure.
- Critical reorder applies the branch/warehouse-specific reorder level to sellable available stock, not merely physical stock. A zero or missing threshold is shown as `NOT CONFIGURED` and does not generate a false reorder alert.
- Inactive locations and inactive/non-stockable Product Master records are excluded from relevant stock/reorder summaries.
- Dashboard inventory valuation maps resolvable legacy SKU-only ledger entries to their Product Master variant while using the current Product Master cost basis supported by the existing schema. This is not a substitute for historic transaction-staged COGS.
- The package correction changes existing query logic and release metadata only. It adds no table, schema migration, live-data mutation or new product mode.

## 9. Gap addressed in the delivered WordPress package

The prior report/dashboard correction left the general stock-list and reorder-status REST handlers using only variant-linked records. Those endpoints could therefore disagree with reports when legacy SKU-only ledger movements or reservations existed, and could assess reorder using physical stock instead of sellable availability.

The delivered `STOCKLAND_ERP_16.1.93_WORDPRESS_UPDATE.zip` includes the correction in `includes/rest/class-rest-stock.php`, `includes/rest/class-rest-reports.php` and `includes/rest/class-rest-app.php`. It keeps physical/reserved/available figures distinct, flags over-reservation, excludes disabled reorder thresholds, and uses sellable availability for critical-reorder calculations.

**Repository parity is still open:** the supplemental source correction has been applied to the delivered ZIP, but the current GitHub build workflow and source-level assertions have not yet been updated to regenerate this exact artifact hash. This is explicitly tracked in Section 10; it must be reconciled within PR #12 before relying on a repository-generated package. Do not treat the current source/build pipeline as fully synchronized.

This package-level correction is not evidence that the installed WordPress database or all finance, POS, identity and ledger workflows have been verified. It does not alter live data or the schema.



## 10. Installable WordPress update artifact and current disposition

The following WordPress plugin archive has been prepared from the existing 16.1.93 Reports Explorer candidate with the consolidated stock/reorder corrections incorporated; no separate release has been created for an individual defect.

- Installer filename: `STOCKLAND_ERP_16.1.93_WORDPRESS_UPDATE.zip`
- SHA-256: `414298ea395ad9aafaf1c973a93f7f9f2a4b0179e83df4ceca86ec5f5e1208b2`
- Size: 1,119,689 bytes; 75 files; single `stockland-erp-app/` plugin root.
- Source files changed by the supplemental correction: `includes/rest/class-rest-stock.php`, `includes/rest/class-rest-reports.php`, and `includes/rest/class-rest-app.php`, with the release manifest and README updated to describe the operational corrections.
- Package verification results: ZIP CRC passed; 48/48 PHP files passed `php -l`; `assets/app.js` and `assets/pwa.js` passed Node syntax parsing; the release/PWA/CSS module JSON manifests parsed; all nine CSS files are byte-identical to the pre-correction 16.1.93 package; and `includes/class-stockland-activator.php` is unchanged. The database version marker remains `16.1.1`; no schema migration or new table was introduced.

**Installed-site boundary:** this session does not have an authenticated control channel to the user's InfinityFree WordPress installation, so it did not upload, activate, or modify that live installation. The archive is a WordPress update package ready for an owner-controlled plugin update. The README was updated to present it as an installed-site update package rather than a separate trial package. Installed records, financial balances, branch permissions, every POS path and the complete ledger must not be represented as live-verified based only on package checks.

**Repository reproducibility gap:** the supplemental correction was applied to the delivered archive; the current GitHub CI builder/QA allowlist has not yet been synchronized to reproduce this exact hash. Keep PR #12 open and do not merge/release through its current automated build until the stock correction is brought into the same builder and its existing regression gates. This remains part of the same consolidated release—not a request to create another version or one PR per issue.
