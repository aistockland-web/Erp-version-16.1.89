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
1. No authenticated staging/production acceptance has been performed in this work. Static code and mocked-browser tests cannot certify 100% operational accuracy.
2. The prior live System Health snapshot reported seven active employees without a current compensation record. This is a real data-integrity gate and must remain visible until records are reviewed/corrected by authorized staff. No salary or employee record may be silently modified.
3. The accounting/stock system must pass transaction-level reconciliation using approved source workbook fixtures and independent expected values: stock movement ledger ↔ balances; sales ↔ tender/receivables; COGS ↔ staged cost; VAT ↔ tax breakdown; AP/AR and payments ↔ control accounts; journal debits = credits; opening/closing ↔ posted transactions.
4. Static absence of SQL foreign-key metadata was reported in the supplied health context. Application-level integrity checks and transactional services must be tested for every relationship; do not claim database-enforced foreign keys unless verified.

### P1 — Front-end polish and all-button integrity
1. The current candidate has a premium shell and responsive layout, but no exhaustive evidence yet that every view is visually consistent or that every visible button works in every role and viewport.
2. Existing desktop Product Master click test covers seven wide widths and a mocked create/save chain under an injected overlapping-search layer. It does not prove the actual host theme is the source of the production issue.
3. A whole-app button inventory is still required: enumerate buttons/links/submit controls by view and role, map each to its handler/API/permission, then test success, validation failure, server failure, duplicate click, and unauthorized access. Unbound controls and handlers that report false success are P0/P1 defects.
4. Validate page/container hierarchy across dashboard, Product Master, Inventory, Daily Inventory, POS, Warehouse, Requisitions, Transfers, Returns, Finance, HR/Payroll, Reports, Users, Settings, Audit and System Health. No naked data grids: each grid needs a consistent page title, toolbar, filter row, action placement, table/card holder, loading/empty/error state, pagination where needed, and usable print/export. Preserve the user's existing scoped spreadsheet-style data grids and Times New Roman requirement where already specified; do not globally replace the app font.
5. Validate large desktop widths (1024, 1280, 1366, 1440, 1920, 2560, 3440), normal laptop widths, and mobile; test browser zoom, keyboard navigation, focus states, menu/modal stacking, overflow, print and responsive headers.

### P1 — Management intelligence and report accessibility
1. Reports Explorer has a selected report type, date range, authorized location filter, optional SKU search for supported report types, selected-report Print/Save PDF, CSV, and server-side permission gates. Validate every report type's data source and totals against the canonical tables before treating results as authoritative.
2. The present source contains dashboard insights, but a dedicated management-intelligence WordPress page with role-gated decision workflows has not been evidenced by this audit.
3. Critical reorder must be Owner-only at both REST permission callback and handler, use location-specific Product Master reorder levels, show physical/reserved/available quantities separately, and be read-only until an explicit authorized replenishment workflow is invoked.
4. Every report needs a named source/definition, date semantics, location scope, currency/tax semantics, totals reconciliation, drill-down, permission contract, and print/PDF layout test. Reports must not accidentally expose cost/profit, payroll, staff, or cross-branch data.

### P1 — User profile menu and external integrations
1. Appearance preferences currently include browser-local theme handling. Audit the native ERP profile dropdown for profile, preferences, notifications, session/security, help, and sign-out, and verify each action is real and permission-safe.
2. Source scan did not find a working native Microsoft Teams/Slack OAuth integration. Treat this as not implemented until provider connection, token storage, revocation, per-user consent, tenant/admin policy, webhook validation, message permissions and failure behavior exist.
3. A Microsoft email login is not achieved by merely adding a Teams button. It requires a configured Entra ID/OIDC application, redirect URI, tenant policy, secret/certificate management, account-linking rules, CSRF/state/nonce validation, logout semantics and a tested fallback. Slack requires its own OAuth app/scopes. Do not hardcode credentials or claim provider login works without owner-provided provider configuration.
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

Use deterministic synthetic fixtures only in isolated test databases; never seed production. Each test must state its input, expected result, observed result, tolerance/rounding rule and ledger references.

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
- For each fix, add a regression test and update this same audit document/PR. Do not weaken unrelated tests.
- No schema changes unless a verified P0/P1 finding requires one, with migration, backup, compatibility and rollback proof.
- No CSS changes unless a measured visual/accessibility issue is demonstrated at relevant viewports; when needed, update ordered source modules and compiled CSS together and prove parity.
- Build one candidate ZIP only after the consolidated fixes, with immutable baseline hash, changed-file allowlist, archive inventory, dependency manifest, checksums, QA receipts and a complete change log.
- Require a review of the final diff and the candidate artifact, then staging acceptance with authenticated Owner, Management, Branch Manager, Warehouse Manager and Employee accounts, plus explicit cross-location denial tests.
- Do not merge/deploy until staging reconciliation, UI/button matrix, backup/restore, rollback and owner approval pass.

## 6. Current conclusion

**Status: NOT YET CERTIFIED FOR PRODUCTION.** The 16.1.93 source/package gates and mocked desktop regression are useful evidence. They do not yet prove complete financial/stock accuracy, the actual host's desktop behavior, a dedicated management-intelligence page, or native Teams/Slack integration. The remaining work is consolidated under this single PR and acceptance plan; do not create separate releases for individual defects.
