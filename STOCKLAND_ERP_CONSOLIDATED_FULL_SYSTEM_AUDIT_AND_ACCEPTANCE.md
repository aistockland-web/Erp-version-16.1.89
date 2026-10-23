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


## 11. Owner-requested final consolidation — no per-defect releases

**Policy:** continue in this existing PR/branch. Do not cut a new plugin version, duplicate PR, or install package for every individual defect. The next publishable package is created only once, after the consolidated change set and acceptance gates pass. Keep all interim work review-only.

### 11.1 Current evidence versus not-yet-proven claims

| Area | Evidence available now | Final disposition |
|---|---|---|
| Exact baseline and package integrity | 16.1.92 checksum lock, 75-file archive inventory, ZIP CRC, PHP/JS/JSON syntax checks | Verified at source/package level |
| Desktop Product Master create action | Playwright reproduces overlapping search-popup click interception; fixed candidate passes 7 desktop widths using mocked REST | Regression fixed in harness; real host/theme still requires staging confirmation |
| Reports & Analytics | Selector, default filters, selected-report print/PDF, CSV, server-side location and Owner-only reorder checks | Static/automated gates pass; all report totals still require independent fixture reconciliation |
| Stock and reorder definitions | Candidate artifact has supplemental logic for legacy SKU-only movements/reservations, sellable availability and over-reservation flag | **Repository build parity is open**; builder must reproduce exact supplemental artifact before release |
| Finance, AP/AR, tax, COGS and general ledger | Business contracts and required fixture suite are documented | **Not certified** until transaction-level reconciliation has expected/observed receipts |
| Front-end final look | Design and viewport acceptance requirements are documented; desktop button has focused browser test | **Whole-app visual/button matrix is open**; do not claim every button/view is verified |
| User profile dropdown | Requirement identified for profile, preferences, notification, session/security, help and sign-out | **Not yet proven** that every menu action is implemented and permission-safe |
| Management intelligence page | Required as a separate management-only WordPress page with read-only decision intelligence and drill-down | **Not yet evidenced as implemented**; requires a dedicated acceptance test and role/access checks |
| Microsoft sign-in / Teams | Entra OIDC is a separate authentication integration; Teams/Graph permissions are separate | **Configuration required / not connected** until owner-provided app registration and authenticated tests |
| Slack | Slack OAuth v2 and minimal approved scopes are required | **Configuration required / not connected** until owner workspace/app approval and callback tests |
| Native chat/collaboration | Needs user identity, room/message authorization, persistence/retention, notifications, audit, retry and revocation | **Not yet certified**; a decorative popup does not count as chat integration |
| Production data and schema | No live deployment or live mutation was performed; schema activator/version marker unchanged in the reviewed package | Preserved by this work; actual installed-site state still requires owner-controlled staging/production verification |

### 11.2 Required implementation sequence within this same PR

1. **P0 — Build reproducibility:** bring the supplemental stock/reorder correction into `ci/patch_reports_explorer.py` and its assertions so the repository rebuild produces the exact approved artifact. Compare all changed file hashes, CSS byte parity, schema activator and database version; no manual post-build patching.
2. **P0 — Accounting/stock correctness:** add deterministic fixtures with independently calculated expected values for receive/sale/return/transfer/reservation/reorder, tax, staged COGS, cash/tender, AP/AR and journal balancing. Test duplicate, retry, concurrent and failed-write behavior. A zero-difference target is mandatory; current status is not certified.
3. **P1 — UI and button chain:** inventory every visible button/link/submit by route, role and viewport. Test hit target, handler, request, authorization, success read-back, validation error, server error, duplicate click and unauthorized attempt. Include dashboard, Product Master, POS, wholesale, receiving, warehouse, requisitions, transfers, returns, adjustments, finance, HR/payroll, reports, users, settings, audit and System Health.
4. **P1 — Shared page/table design:** apply one Stockland-owned shell and page-holder contract; each data grid must have a page title, action toolbar, filter area, table/card holder, loading/empty/error states, totals/pagination where appropriate, focus/keyboard support, responsive overflow and scoped print/export. Do not copy proprietary SAP/Odoo/NetSuite screens or globally replace existing fonts.
5. **P1 — Reports and management intelligence:** reconcile each report to source records; require a report definition, date/time basis, location scope, currency/tax/cost basis, access rule, drill-down and print/PDF check. Implement the separate management page as read-only by default, role-gated, traceable to source records, with explicit uncertainty/exception states and no automatic stock/finance posting.
6. **P1 — Profile menu and integrations:** verify profile/preferences, notifications, session/security, help and sign-out. Add provider adapters only after credentials/configuration are supplied. Microsoft OIDC, Teams/Graph, Slack OAuth and internal chat must be separate capability-tested features; provider outages must not block ERP sales/stock/finance.
7. **Release candidate:** generate one ZIP only after the above code changes and regression suites are in the same builder. Publish one checksum, full changed-file manifest, test receipts and rollback instructions on this PR. No merge/deployment until staging acceptance is signed off.

### 11.3 Management intelligence and sensitive decision safety

Management intelligence should provide explainable answers rather than an unconstrained chatbot that can post transactions. Every result should state:
- the date range, authorized locations and canonical source records used;
- calculation/metric definition, cost/tax basis and refresh timestamp;
- whether the figure is posted, derived, estimated or incomplete;
- exceptions such as missing compensation, over-reservation, unknown SKU, stale data or failed integrations;
- a drill-down path and an audit entry for sensitive report access where appropriate.

AI or narrative analysis may summarize and compare verified data but must not invent missing amounts, silently correct payroll, write ledger entries, approve adjustments, change reorder thresholds or post stock movements. Any action must go through the same authorized workflow, confirmation and server-side transaction services as a human operator.

### 11.4 Final acceptance rule

**Historical disposition for Sections 1–11:** the package build and live-environment gates described above were open at the time those sections were written. See Section 12 for the latest WordPress installer source and package receipts. A successful package check is not proof that the file has already been uploaded or activated on the authenticated InfinityFree site.


## 12. Consolidated WordPress update — latest artifact and source alignment

**Artifact:** `STOCKLAND_ERP_16.1.93_WORDPRESS_UPDATE.zip`  
**SHA-256:** `0e1f21ec02d33710921249f06e61aba4d4cbc24dae26d50c2513f19fe72442ba`  
**Size:** 1,096,594 bytes  
**Package:** 75 uniquely named files, single `stockland-erp-app/` plugin root  
**Disposition:** installable update for the existing WordPress plugin folder; no new database schema or live record changes are packaged.

### 12.1 Locally verified package facts

- ZIP CRC, unique archive inventory and safe single plugin root passed.
- PHP syntax: 48/48 files passed the installed PHP linter.
- JavaScript syntax: `assets/app.js` and `assets/pwa.js` passed.
- JSON parse: release manifest, PWA manifest and CSS module manifest passed.
- Nine CSS files are byte-identical to the checked-out source tree.
- Table contract remains 49 tables: 29 Core/Operations, 16 Accounting/Procurement, 4 HR/Payroll. Database schema version stays at 16.1.1; the schema activator is unchanged.
- Release metadata and README identify this as a Stockland WordPress update and do not label the plugin update as a trial installation.
- The source contains the dedicated Owner/Management Management Intelligence workspace and report query failures return explicit errors instead of valid-looking zero totals.
- WordPress/PHP runtime checks, plug-in header and hosting notes align to PHP 8.3+. XLSX streaming health accepts the disk-backed reader fallback when supported XMLReader and ZipArchive are available.

### 12.2 Stock / reorder audit findings incorporated

The Reports Explorer, dashboard low-stock calculation, inventory list, live reorder endpoint and Daily Inventory now use consistent definitions. Posted stock ledger movements remain the physical-stock authority. Active reservations are resolved both by canonical variant and by legacy SKU-only rows where a valid Product Master mapping exists. Physical on-hand, reserved, sellable available, inbound transfer, reorder position and reorder threshold are separate. Sellable availability is floored at zero while `OVER-RESERVED` remains visible as an integrity warning. Unconfigured reorder thresholds do not create a false replenishment alert. Inbound quantity includes only eligible transfers whose state is explicitly `In Transit`; `Picked` alone is not assumed to mean a transfer has been dispatched. The proposed quantity is advisory and does not mutate stock or auto-create a purchase order.

### 12.3 Payroll reference and arithmetic disposition

The supplied standalone example was not installed as a second Node.js/Express service or PostgreSQL schema, and its sample names, TINs and salary values were not imported. Payroll remains in the existing WordPress/PHP/MySQL service using the canonical decimal-safe money engine, versioned rule `ET-PAYE-1395-2025-v3-PENSION-BASE`, effective-dated compensation, Owner-approved classifications, period locking and accounting posting.

The installed PHP statutory calculator was invoked with synthetic fixture values. Six PAYE bracket boundaries and a complete payroll line reconcile: taxable income ETB 10,300.00; income tax ETB 1,740.00; employee pension ETB 700.00 (7%); employer pension ETB 1,100.00 (11%); gross pay ETB 11,000.00; net pay ETB 8,560.00; employer cost ETB 12,100.00. Five independent reorder boundary cases also match the stated threshold and minimum-one-unit suggestion policy. These are formula-level receipts, not a claim that any employee’s actual pay was changed or independently audited.

The reported seven active employees without current compensation remain visible for authorized HR review. No salary, employee identity or effective-dated compensation record was invented, auto-filled or rewritten.

### 12.4 Repository generation and installed-site boundary

The existing PR #12 build workflow now invokes the ordered Reports Explorer, stock/reorder reconciliation and shared-host/report-protection steps. The stock query parameter-order assertions were corrected to match the expanded canonical-variant/reservation/transfer joins, and the regression contract explicitly checks that only `In Transit` contributes inbound stock. The production hardening source includes fail-closed guards for both report-source queries and Command Center sales/inventory measures.

At the time this update was written, the GitHub connector had not returned a workflow run for the newest branch head; therefore the repository-generated artifact hash and current workflow status are **not represented as CI-verified**. The manually prepared installer above has the local receipts listed in Section 12.1. A later green repository run should be compared against the same package hash before treating CI reproducibility as closed.

No authenticated control channel to the owner’s InfinityFree WordPress installation was available in this session. The ZIP was not uploaded, activated, or run against live tables, and no production data was modified. That boundary is not an open code change inside the installer; it is an external access/action limit. Do not represent live-site activation, all-button coverage, every finance/POS transaction path, or complete AP/AR/GL reconciliation as performed from package-level receipts alone.


## 11. Domain portability and removal of fixed external website links

The installable plugin must not rely on the previous test/redirect host, an InfinityFree subdomain, an old GitHub project URL, or a hard-coded external website for ordinary ERP operation. The ERP page/REST/assets use WordPress URL functions and the running site's origin. The existing bundled Stockland logo asset remains local to the plugin; no additional remote logo/image request is required.

The package-build routine now removes:
- fixed Ethiopian legal-source hyperlinks and their hard-coded fallback domains from the payroll interface/API (the legal labels remain visible, without a remote click-through);
- old GitHub Actions workflow URLs from the embedded release manifest;
- website/license URL text from the bundled Bootstrap stylesheet's header comment only (the CSS rules and framework version/MIT attribution text remain intact).

The package QA contract scans all packaged file bytes for web URLs. Only the required SVG namespace `http://www.w3.org/2000/svg`, the OpenXML relationships namespace `http://schemas.openxmlformats.org/officeDocument/2006/relationships`, and the exact official Stockland logo URL if one is intentionally used are allowlisted. Namespace URIs are technical identifiers, not browser requests. Bootstrap CSS changes are restricted to its header-comment URL text; its styling rules must remain identical. The archive file list/root and schema activator remain unchanged.

This is a source/build contract in the existing PR, not proof that the host redirected or that the installed WordPress files have already been replaced. The generated ZIP's checksum and build receipt must be checked before describing it as the package for manual update. No remote files, credentials, database rows, stock or financial records are changed by the cleanup itself.

## 13. Supplemental Owner findings — mandatory before installation

These findings were raised after the earlier consolidated audit and supersede no existing acceptance gate. They are additional blockers and must be resolved in the same 16.1.93 workstream; documentation alone does not close them.

### P0 — HR must be a complete Stockland SOP lifecycle, not a statutory calculator

The prior payroll arithmetic fixtures only establish limited formula behavior. They do not certify that Stockland has a complete HR system. Before release acceptance, map the approved SOP through workforce/headcount planning and budget; requisition and approval; recruitment/offer where required; onboarding; employee master, contracts, positions, locations and probation; attendance, shifts, overtime, holidays and leave; performance/training/discipline where specified; compensation and changes with effective dates; allowances, deductions, loans/advances, benefits and reimbursements; payroll cut-off/input reconciliation/review/approval/payslip/remittance/accounting; correction/reversal; resignation/termination/retirement; clearance and asset return; leave/loan/advance reconciliation; final earnings/deductions and settlement calculation, approval, payment, certificates and archival.

For every step, identify the canonical record, actual persisted request/response payload, required fields, permission, approver, state transition, date semantics, exception/failure behavior, accounting effect and audit event. Do not invent missing employee/compensation values. If the current four HR/Payroll tables or services cannot represent a required SOP step, record the exact gap and propose a reviewed, versioned migration with backup/rollback proof; do not claim the step exists or add shadow records. Closure requires signed HR/Owner SOP traceability and independent settlement/payroll expected-value reconciliation.

### HR/Payroll scope finding — source evidence, not just a statutory formula gap

The inspected historical 16.1.93 candidate exposed employee list/create/decision/update, compensation create, payroll list/detail/create/generate, line update/classification, submit, Owner approval and payment. Its UI described a four-step monthly payroll run and employee rows included hire/termination dates. The inspected archive did **not** evidence a complete workforce plan → requisition/recruitment/onboarding → time/attendance/leave → employee development/performance (where the SOP requires it) → termination clearance → final settlement workflow. This is a historical candidate inspection only; the current exact-head ZIP still must be built and inspected.

The release must not equate a functioning PAYE/pension calculator or approved monthly payroll with a full HR ERP. CAP-22 requires a Stockland SOP-to-feature matrix, complete persisted payload/field review, effective-date and historical-record protection, independent final-settlement math, authorization, and audit evidence. Any new schema requirement must be separately justified with migration/backup/rollback proof; do not fabricate employee or compensation values.

### Specific source issue found and narrow build correction

In the inspected historical candidate ZIP, the WordPress admin diagnostics panel in `includes/class-stockland-admin.php` displayed a “Server environment” heading and the text “plugin requires 8.4+”. This was inconsistent with the intended PHP 8.3+ hosting floor. The consolidated build patch corrects the PHP floor and now renames that heading to “WordPress hosting compatibility”, while tests require the live PHP version, MySQL/MariaDB version, memory limit and execution-time diagnostics to remain present. No diagnostic data is removed.

The historical bundled README included old test/staging/mock-CI commentary, and the release JSON carried a large accumulated candidate-history list and old CI/toolchain metadata. The build patch now replaces the README with a current host/operator guide and whitelists production operational contracts plus current release notes in `RELEASE_MANIFEST.json`. A static gate checks that old workflow/source-gate history and known test/runtime wording are not copied into those installed documentation/metadata files. The external audit and Actions history remain in GitHub. Exact-head build and regression execution are still required before closure.

The inspected historical ZIP contains legitimate import-staging/temporary-table workflows (e.g., import preview/stage/approve/commit and safe temporary-table rebuilds). These are business/data-integrity controls, not a “staging copy” of the application. They must remain. The inspection found no standalone uppercase whole-word `FL` token in that historical artifact; AP/AR/PLEDGER/GL references correspond to existing Finance/Procurement operations and must be preserved. This classification must be repeated against the exact final built archive, with every matched occurrence logged and dispositioned before approval.

### P0 — Hosting/source cleanup must be precise and non-destructive

The installable product remains one Stockland WordPress plugin running on the existing PHP/WordPress and MySQL/MariaDB hosting model. It must not gain a separate Node.js/Express/PostgreSQL service, daemon, unsupported required worker, hard-coded host/redirect, or test/staging application tree. Do not embed test staff/pay, fixtures, mock APIs, staging configuration, CI helpers or demonstration business data in the ZIP.

Audit the actual final archive and build source for the reported “FL/AR/AP/server environment” wording one occurrence at a time. Classify each item before changing it. Remove stale hostnames, misleading environment claims, hard-coded external workflow/legal URLs, unsupported runtime dependencies and developer-only wording only where confirmed. Preserve real Stockland ERP processes and all legitimate AP (Accounts Payable), AR (Accounts Receivable), GL, REST route, permission, ledger, audit, import, resume, transaction/row-lock and schema/version identifiers. Required PHP handlers invoked by WordPress REST remain part of the hosted application; removing them or moving privileged writes into browser code would weaken authorization and record integrity. Required JavaScript/PWA assets and JSON manifests are not test copies and must not be stripped indiscriminately. Preserve existing business dates/history, all approved SOP transitions and immutable posted records.

Closure requires an archive-wide inventory/phrase/URL/dependency scan, exact changed-file allowlist, PHP/route/capability checks, schema/version/date invariants, CSS source/compiled parity, and proof that the build produces the same inspected ZIP with one `stockland-erp-app/` root and the approved runtime inventory.

### P0 — Re-establish the previously corrected Reports loading behavior

The Owner reports that a later downloaded version reintroduced a report-loading error after multiple prior correction iterations. The precise previously-good implementation and its regression proof must be traced through the repository/package history before changing the code. Do not hide the error, fake zero totals, force an empty-state result on a failed query, change report scope, or remove report handlers as a shortcut.

The final exact-head package must exercise the previously affected report as well as every report type: initial load, filter/date/location changes, navigation away/back, switching report types, no results, API/query failure, retry, and repeated selection. A real successful response renders persisted-source data; a genuine empty result renders the designated empty state; a failure displays a clear error and retry path without an infinite spinner or fabricated zero. The external CI-only browser test `ci/qa/test_reports_loading.py` is now wired into the existing Reports Explorer workflow to check failure → retry → success recovery; the static regression test also guards the loading/error/retry markers. Neither test is considered passed until an exact-head workflow receipt is returned. Capture the historical fixing commit/version when identifiable, the reproducing case and expected behavior, exact package hash and passing regression receipt. Until that evidence exists, the report-loading CAP remains OPEN.

### Packaging and release non-negotiables

- One cumulative plugin update, same established release branch and plugin root; no parallel “test” or “staging” plugin.
- QA scripts/fixtures stay outside the installable ZIP, in the review/CI area only; no demo employee identity/TIN/salary or mock transaction data enters the package or live database.
- No new runtime service or hosting dependency. CI may use test tooling externally, but Node.js or similar developer tooling must not be required to operate the installed ERP.
- Preserve approved source fixes and the entire prior change history. Every patch must have a file allowlist and regression contract so it cannot reintroduce a previously closed loading/UI/payroll/stock bug.
- No source-wide blanket replacements for terms such as “server,” “AP,” or “AR.” Review each occurrence and prove its role before editing.
- Do not call any audit finding closed solely because a document was added. The package must be rebuilt, tested at the exact final source head, reviewed, then accepted on the authenticated target host with a verified backup/restore and rollback plan.


## Latest Owner-provided live 16.1.94 Health and mobile UI evidence — 23 October 2026

The Owner supplied a current System Health text dump and phone screenshots from `stocklanderp.ct.ws`. This is reported live evidence, not an authenticated session performed by the audit runner.

### Runtime identity / source mismatch

- Live screen reports Plugin **16.1.94**, DB **16.1.1**, PHP **8.4.27**, WordPress **7.1.3**, MariaDB **11.4.13**, memory limit **512M**, upload/POST limits **20M/30M**.
- It reports Core **29/29**, Full ERP **49/49**, REST v16 **104 routes**. These are useful schema/route-presence checks; they do not independently prove full business transaction coverage.
- The connected review branch being audited remains a 16.1.93 candidate built from a 16.1.92 package. No 16.1.94 plugin ZIP/source is present in this branch. Therefore, do not upload this branch's ZIP as an update over live 16.1.94 and do not modify its older POS/cart markup as though it were the exact installed page. Obtain and checksum the actual deployed 16.1.94 package first.

### Payroll data gate

The latest copied Health text reports **9 active employee records** with the reason “Current compensation record is missing”; `payroll_compensation_classification` is the failed production gate. The check expressly states it does not change salaries/classifications. This count supersedes older reports of seven affected employees. HR/Owner must review actual contracts and effective compensation through authorized workflows. No values can be synthesized or auto-filled to obtain PASS.

### Deep-integrity state must be tied to one completed run

The latest pasted text reports PASS labels for audit-chain, ledger references, no-negative-stock, balance reconciliation, reservation integrity, AP/AR, GL balance, procurement references and identifier integrity, and says the supplemental bounded scan is clean. It also reports that database foreign-key metadata is **NOT_ENFORCED**. In a separate supplied screenshot, the red panel explicitly labels the broader deep audit **UNVERIFIED** and lists audit-chain hash, ledger/PLEDGER/AP/AR/GL/orphan, reservation, duplicate identifier, payroll and import/workflow checks as pending. Without a shared run ID, timestamps and per-check completion receipt, these panels cannot prove that the complete deep scan finished. Treat the full deep result as UNKNOWN/UNVERIFIED until a completed read-only receipt demonstrates every required check; keep supplemental bounded results separate. Never infer SQL foreign keys from application orphan checks.

### Reporting failure and mobile layout

- Reports screenshot: the live report call returns “The report could not read every required source. No partial totals were returned.” This fail-closed behavior is safer than fabricated totals, but the source/database read failure is still an operational blocker. Diagnose the failing report source using sanitized diagnostics and preserve the explicit error/retry behavior; do not widen access or coerce the response to zero.
- The same screen shows an inline error plus a second large red toast obscuring the report. Remove duplicate/intrusive presentation only after exact 16.1.94 source is recovered; keep one accessible message and working retry/Health path.
- Requisition/warehouse screenshot shows the approver/decision/status/timestamp text colliding and the supply-line table occupying a horizontally clipped area. The Owner separately reports the Cart page is visually unusable. Fix the actual 16.1.94 renderer and its CSS without altering protected handlers, approval states, stock/GL/audit effects or idempotency.
- CAP register has been extended with CAP-25 (mobile Cart/requisition/approval layout), CAP-26 (deep Health completion semantics), CAP-27 (report error presentation/source failure), CAP-28 (16.1.94 source parity), CAP-29 (single master end-to-end acceptance) and CAP-30 (Cart/POS correction on exact current source).

### Local recheck and code-level report recovery change — 23 October 2026

A [Local Recheck Receipt](STOCKLAND_ERP_LOCAL_RECHECK_2026-10-23.md) was added with a reproducible local test record. The historical 16.1.93 artifact SHA-256 `a8cb3767f363740387731928211febc315876a7b8c146e65e594930940e64e3b` passed ZIP CRC/root/inventory (75 unique files, one plugin root), PHP lint 48/48, JavaScript syntax for app.js/pwa.js, JSON parsing, ordered CSS source/build/minified parity, PostCSS parsing and responsive media coverage (60 @media occurrences). Source scanning found 49 distinct literal stock-table declarations, preserved AP/AR/PLEDGER/GL terms and no case-sensitive standalone uppercase `FL` token in the historical PHP tree.

A narrow report UI correction is committed in `ci/patch_reports_explorer.py`: when a report API call fails, the screen keeps the explicit inline error and Retry button, clears stale report state and returns rather than throwing into the same click handler and creating a second intrusive toast. The static regression contract was updated, and `ci/qa/test_reports_loading.py` now checks a user-triggered failure followed by retry, single-error presentation and 390px mobile bounds. A separately run local Playwright/Chromium test applied this exact replacement to a temporary copy of the historical candidate and passed: initial report success → user-triggered source failure → inline error/no duplicate toast → retry success; print/CSV disabled during error and re-enabled on success; no page-level horizontal overflow at 390px; no uncaught JS errors; no live data touched.

This is a **partial code/QA improvement**, not closure of CAP-24/27. GitHub currently reports zero check runs directly attached to the latest source commit `5fa1bdc5bc3219e55c97b47774f6957dafacc417`. Available related Actions receipts were created on 20 October and reference older build state. The exact latest source/package has not been built and tested in Actions, and the live 16.1.94 database source error is not diagnosed by a mocked local test.

The same local source audit found that the historical 16.1.93 package still shows “Server environment” and “plugin requires 8.4+” in the admin panel, and the release manifest includes older Node.js provenance. The current production-hardening build script contains corrections, but no exact-head built ZIP was available to prove their final output. It also exposes a single-SKU atomic POS form, not the precise live Cart source in the Owner's screenshots. This confirms the baseline mismatch blocker and must not be treated as a live Cart fix.

### Final cumulative local regression recheck — 23 October 2026

The final available **local review fixture** is `STOCKLAND_ERP_16.1.93_LOCAL_CUMULATIVE_REVIEW_FIXTURE_ONLY.zip`, SHA-256 `0d2632ad75d6df81c5047b0937b9b551d7b9ee7d03104c8c91458682116e5883`, 1,101,969 bytes, manifest version 16.1.93. This is not an approved production update and must not be uploaded over live 16.1.94.

**Static/package PASS on the review fixture:** ZIP CRC/path/root/inventory (75 unique files / one plugin root); PHP syntax 48/48; app.js and pwa.js syntax; JSON parsing; PostCSS for app.css/app.min.css/Bootstrap; six-module source → app.css exact rebuild; normalized source → app.min.css parity; ten fail-closed report source query guards plus location lookup failure; five dashboard KPI query-failure guards; Management Intelligence view/capability gate; PHP 8.3+ diagnostic normalization; archive-wide unapproved outbound URL scan zero; Bootstrap MIT attribution preserved. Static guards establish code presence, not the live queries' successful operation.

**Browser smoke PASS using synthetic intercepted API responses:**
- 175 view/viewport pairs across 25 work areas × 7 CSS widths (320, 360, 390, 430, 768, 1024, 1440).
- Six role profiles: Sales Employee, Branch Manager, Warehouse Manager, Management, Employee, Owner; all tested navigation-capability assertions passed.
- Management Intelligence separately checked once for Management, Owner and Sales Employee; workspace visible for the two authorized profiles and restriction message shown for Sales Employee.
- Requisition approval history/supply table: 7 widths; no approver/decision/status overlap or page overflow, no table wrapper outside the drawer.
- Reports: initial success → induced API failure → single inline error/no duplicate toast → successful retry; 3 requests in the focused failure/retry sequence, no overflow at 390px, print/CSV appropriately disabled then enabled.
- Zero blank views, measured overflow failures, page errors or console errors in the final recorded browser run.

Detailed report: [STOCKLAND_ERP_LOCAL_ACCEPTANCE_RECHECK_2026-10-23.md](STOCKLAND_ERP_LOCAL_ACCEPTANCE_RECHECK_2026-10-23.md); machine receipt is available locally as `STOCKLAND_ERP_LOCAL_CUMULATIVE_BROWSER_RESULTS_2026-10-23.json`.

**Not proved by these runs:** live DB/REST read success, actual SQL source of the report error, real server-side role/location permission denials, stock and ETB transactions/reconciliation, import idempotency, statutory-law signoff, real payroll corrections, the complete HR lifecycle, deep audit completion, backup/restore/rollback, or live 16.1.94 Cart/POS source parity. Tests ran against synthetic fixture responses in a temporary browser page and did not access or mutate production records.

The integrated GitHub workflow has source steps for the new report-loading, requisition layout, all-work-area/role and CSS/PostCSS checks. **Exact-head CI remains pending**; the last visible green Actions receipts target previous commits from 20 October. A missing exact-head receipt is not inferred as PASS.

**Release boundary:** live System Health reports plugin **16.1.94 / DB 16.1.1**, whereas this source lineage is 16.1.92 → review candidate 16.1.93. Exact installed 16.1.94 installer/source/checksum remains unavailable. Nine live employee compensation exceptions remain an Owner/HR data gate; no fake salary values can be inserted. The live report-source error and full HR-to-final-settlement workflow remain unresolved. Local fixture PASS therefore does not certify the WordPress update.

### Verified release-artifact provenance and CAP-01 closure gate — 23 October 2026

The actual Actions artifact was downloaded and inspected instead of assuming that a package filename or green earlier workflow proved final-source parity.

- Actions run: [39471331354 — Reports Explorer 16.1.93 exact-package gates](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/39471331354)
- Run source SHA: `1f96da520602b992bec449ebf2ab45b9469f107e` (20 October); current branch source SHA after cumulative gate changes: `7d99ff8dba2f1fe0eea3eb572872a55576af1589`.
- Locked canonical 16.1.92 installer: `adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd`, 1,113,446 bytes. PHP 8.3 and PHP 8.4 action artifacts embed the same baseline bytes. Local validation passes ZIP CRC / 75-file one-root inventory, PHP syntax 48/48, JS syntax, JSON parsing and CSS six-module/app.css/app.min.css parity. The saved 16.1.92 PHPCS JSON reports 0 errors / 0 warnings and the saved Semgrep 1.139.0 JSON reports 0 results / 0 errors for that package/revision. Live deployment was not performed.
- The 16.1.93 build and local QA receipts inside the downloaded artifact identify an intermediate package at SHA `1cfab0a8221802aa2a5633e92d65ff76f146ea6152aae7068e6d82b384f2dceb`, 1,117,980 bytes.
- The actual embedded 16.1.93 ZIP and its sidecar identify SHA `a8cb3767f363740387731928211febc315876a7b8c146e65e594930940e64e3b`, 1,118,326 bytes; the stock reconciliation receipt agrees with this latter identity.

The downloadable candidate's SHA sidecar matches its own bytes, but its top-level build/QA receipt is not an attestation of those final bytes. The artifact also predates the latest source cleanup and UI/backend hardening now present in the branch: it retains the 8.4+/server-environment wording and fixed legal-domain links, and lacks the report-source query-failure guards, Management Intelligence renderer, responsive approval-history CSS, and single-inline-error retry correction. So the older Actions “success” is not proof that the current cumulative release package exists.

A final package receipt gate is now in the source pipeline: builder emits final_package SHA/size/inventory/changed-file evidence after all patches; `ci/qa/verify_final_package.py` checks sidecar, build receipt, 75 files/one plugin root, release/schema markers, changed-file allowlist, PHP 8.3/domain/report/management/approval markers, and CSS source/compiled parity. The reports regression test deliberately tampers with the receipt to prove a stale/intermediate SHA fails. The workflow publishes the final receipt alongside the ZIP and checksum.

**Status remains OPEN / BLOCKER for CAP-01/02:** the latest GitHub head currently has no matching Actions run/status. The source-side guard is implemented but has not been executed on that head, and no new final artifact with a matching receipt has been produced. **Do not upload the historical 16.1.93 artifact over live 16.1.94.** Other live-data and operational blockers (nine missing compensation rows, full HR lifecycle, full deep-audit receipt, report SQL failure, and Cart/POS version parity) remain separate and open.

### Updated disposition

**NO-GO for installation/update.** Current evidence contains a real payroll gate failure, a failed live report source read and mobile UI defects, and the review package is older than the installed release. The master acceptance plan is linked in `STOCKLAND_ERP_MASTER_END_TO_END_ACCEPTANCE_16.1.94.md`. Corrective documentation does not close the code, data or host acceptance gates. No deployment or live data mutation was performed by this review.

**Updated disposition: NOT APPROVED FOR WORDPRESS INSTALLATION until the exact current artifact has been built and verified, the HR-to-final-settlement SOP lifecycle is evidenced, host/source cleanup is complete without deleting legitimate ERP services, the earlier report-loading fix is proven retained, all existing CAP blockers are closed, and the Owner approves the evidence.**

