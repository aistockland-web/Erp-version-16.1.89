# Stockland ERP 16.2.18 — Post-release gap reconciliation and cumulative implementation plan

**Assessment date:** 14 November 2026  
**Purpose:** Reconcile the Owner-supplied 16.2.18 SaaS gap analysis and the completed release evidence against the newer engineering work already present in this repository. Continue the existing ERP as one cumulative WordPress/PWA product and ultimately produce one exact, installable WordPress update ZIP.  
**This document is an engineering reconciliation and work order. It is not a production migration authorization or a claim that every listed feature has already shipped.**

## 1. Correct release identity and decision

The user message once says “16.2.28”; the attached gap analysis, canonical manifest, installer artifact and CI evidence all identify **16.2.18**. This plan therefore treats **16.2.18** as the protected production baseline. Do not invent a 16.2.28 release identity. If a separate .28 artifact exists, it must be supplied by an exact Actions artifact ID and independently verified before it can replace this identity.

The 16.2.18 master installer is the Owner-designated production baseline. Preserve its behavior, data model, branch/location permissions, stock ledger, finance journals, audit history, WordPress/PHP/MySQL architecture and PWA shell. Do not downgrade, replace it with an isolated older candidate, rebuild it in place, or install a feature branch over production.

### Exact package receipt

- Repository: `aistockland-web/Erp-version-16.1.89`
- Canonical delivery branch: `delivery/stockland-full-erp-completion-2026-11-11`
- Reconciliation base commit: `845ea728c52e9111ce8174c76b78ac478d46e1c1`
- Exact approved Actions artifact: run `43570216177`, artifact `14104031868`
- Inner installer: `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`
- Inner SHA-256: `de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08`
- Size: 2,251,794 bytes; 125 ZIP entries; 124 manifest-inventoried application files
- Embedded source commit: `387c193c7999840c731d37acd854ec920f134507`
- Outer Actions artifact SHA-256: `ff58626d39c63980eba7f3de5cf8e2ee7c5788952e746731791fdd20b434d133`

The exact approved artifact is immutable input for comparison and rollback. Every future cumulative candidate must have a new source commit, package, independent SHA-256, manifest, SBOM, build receipt and exact-head evidence. It must never overwrite this baseline artifact.

## 2. What the current evidence proves

The Owner-supplied gap analysis dated 13 November records a full automated 16.2.18 run `43392513255`, with MariaDB 10.11 and MySQL 8.0.46, fresh WordPress installation, 86-table setup, `LIVE_SCHEMA_READY`, deep integrity 52/52, and the listed user/product/procurement/receipt/transfer/retail-sale/return/daily-close/AP/customer-invoice/payroll-vector/schema-governance scenarios. It records zero unbalanced journals, responsive browser smoke at phone/tablet/desktop widths, backup/restore/checksum exercise, and a MariaDB .13→.18 upgrade. The same report explicitly says that the live site itself was not reached.

The subsequent exact-artifact no-rebuild verification for 16.2.18 is recorded in GitHub Actions run `43594028898`: the same SHA-pinned installer was installed in disposable WordPress environments on MariaDB 10.11 and MySQL 8.0.46; the run also exercised the schema-governance, Management Intelligence, variant identity, Daily POS import and dashboard-filter contracts. Related exact-head source-intake and PHP 8.3/8.4 security/runtime checks passed on the canonical delivery head.

**Interpretation:** this is strong, exact-package automated acceptance for the 16.2.18 baseline. It is the correct foundation for continuing engineering. It does not mean every feature in later candidate branches was included in that ZIP, nor does it prove a direct live-host/browser/database inspection from this chat. The Owner's statement that 16.2.18 is live and approved is retained as the production baseline decision; no production access or mutation is claimed here.

## 3. Repository feature archaeology — do not merge by version number

The repository contains later development work that is valuable, but it is not all the same type of release input. The work must be reconciled by exact file, behavior, dependencies, schema impact and tests.

### 3.1 Full HR/payroll candidate from PR #39

[PR #39 — Feature/hr payroll full cycle 16 2 0](https://github.com/aistockland-web/Erp-version-16.1.89/pull/39) is merged into repository history at `bd6c78f180e3bc6871317aa32c1f11d4fab8a427`. Its source builder and overlay live under `ci/`; it is not evidence that the overlay is already in the 16.2.18 runtime source or installer.

The overlay contains substantial proposed workforce lifecycle, payroll-cycle, statutory-rule, private-vault, POS-cart and UI work. However, its own builder identifies a 16.1.95 source input and a 16.2.9 candidate. Its schema code proposes 37 HR lifecycle tables and changes existing legacy HR/payroll tables. Its calculator explicitly documents that it is not wired into the production ERP and accepts only synthetic test rules for test execution. The candidate also needs review for legacy route compatibility, explicit relationships/FKs or equivalent integrity, private-document encryption, code-standard debt, full WordPress runtime behavior and payroll settlement reconciliation.

**Disposition:** preserve PR #39 as a valuable engineering source and test corpus; do not run its whole-package overlay on 16.2.18. Port compatible features into the current source only after each change has a file-level diff, a protected-schema review and focused tests. Do not silently import its unrelated POS/cart, stock, sales, database or global CSS changes as part of HR.

### 3.2 HRX clean-slate work

[PR #48 — HRX integration candidate 16.3.0](https://github.com/aistockland-web/Erp-version-16.1.89/pull/48) was built from 16.1.95, not from the 16.2.18 production baseline. Its PR description explicitly says it is not a cumulative ERP update. The 47-table HRX DDL is a proposal and the schema installer remains disabled. It requires a final model, data/dependency map, shared-service reuse review, legal acceptance, role/location and private-document tests, payroll/payout/remittance/GL reconciliation, and migration/restore/rollback evidence.

[PR #44](https://github.com/aistockland-web/Erp-version-16.1.89/pull/44) and [PR #45](https://github.com/aistockland-web/Erp-version-16.1.89/pull/45) provide useful Stage 4 people-lifecycle and shared table-prefix corrections, but their PR records identify them as candidate/CI-oriented work, not accepted full WordPress production runtime. [PR #49](https://github.com/aistockland-web/Erp-version-16.1.89/pull/49) records a staging SOP Library tab and 24 downloads, but it was not merged as a production package.

**Disposition:** HRX is a design and source reference, not a package to install. Do not assume 37, 47, 14–16 or any other proposed HR table count is approved. Determine the smallest sound model only after auditing the exact 16.2.18 DDL and dependencies. Preserve the existing four legacy HR/payroll tables and their history until an additive migration and rollback are proven and approved.

### 3.3 Existing 16.2.18 controls and Management Intelligence

The 16.2.18 manifest already states:
- 29 Core + 16 Accounting/Procurement + 4 legacy HR keys are preserved as the legacy subset.
- HR lifecycle keys are additive source declarations; exact schema/data mapping and Owner approval remain separate.
- HRX is recognized for preservation only and is not activated.
- Destructive schema operations are prohibited; a table count is not approval.
- Statutory defaults are absent; an approved effective-dated rule bundle is required.
- Private HR vault uses versioned AES-256-GCM with a site-managed secret; actual live-host key configuration is not tested.
- The 24 SOP resources are payload-integrity PASS but `DRAFT_NOT_APPROVED`.

[PR #106 — Management Intelligence decoupled from HR](https://github.com/aistockland-web/Erp-version-16.1.89/pull/106) is merged into the canonical delivery branch. It adds a distinct Owner/Management route and manual social campaign/export snapshots in existing Core settings. It is deliberately read-only for ERP financial/stock records and does not claim provider APIs, automatic posting or automatic analytics.

**Disposition:** retain the current 16.2.18 module boundaries and PR #106 behavior. Complete the missing UI/report controls without allowing Management Intelligence to become a POS import or posting surface.

## 4. Reconcile the uploaded SaaS gap analysis into a cumulative roadmap

The attached gap analysis is the owner-facing product backlog. “SaaS-like” means a polished, responsive, data-dense, accessible, secure application experience with complete workflows; it does not authorize a rewrite into another framework or automatically require multi-tenant billing. Stockland remains a single-company ERP unless the Owner separately approves tenant provisioning.

### P0 — Reporting, tables, search and design-system consistency

1. Audit every dashboard KPI/widget and every report explorer/list. Each displayed report must have a real handler, correct date range, branch/location scope, filter dropdowns, search, refresh, print/export and visible source/as-of timestamp.
2. Build reusable table holders for HR/Payroll and other dense modules: search suggestions, column filters, date filters, sort, pagination, empty/loading/error states, saved filter state where safe, and accessible keyboard/touch operation.
3. Trace the HR/Payroll navigation and accordion flow. Reorganize the dense horizontal row into a responsive two-column section layout with nested subsection holders; preserve existing actions and server authorization.
4. Fix the search-suggestion behavior on focus/input and ensure every menu/dropdown/button has an actual handler or is clearly disabled with a reason.
5. Keep Bootstrap 5.3.8 and the existing ordered Stockland CSS modules. Inherit the server-selected theme tokens; add tested fallbacks for older browsers and reduced-motion/accessibility preferences. Do not introduce a second competing style system.
6. Test 320/360/390/768/1024/1366 widths, keyboard focus, screen-reader names, no horizontal overflow, console/network failures and theme fallback behavior.

### P0 — Full HR/payroll lifecycle, with governed activation

Implement one integrated HR workspace, not a detached HRX plugin:
- Workforce plan, position/grade, approved vacancy, recruitment/application/interview/offer.
- Employee identity, contract versions, probation/onboarding, branch transfers and effective-dated employee events.
- Attendance, shifts, overtime/holiday exceptions, leave policy/balance/request/approval, with every statutory formula tied to an approved effective-dated rule.
- Compensation components, maker-checker approval, payroll periods, frozen input snapshots, line-by-line calculation trace, exceptions, payslips and correction/re-run controls.
- Payment batches with per-employee success/failure, bank/cash reference, idempotent retry and independent settlement reconciliation.
- PAYE/pension/statutory remittance registers, evidence, due dates and balanced GL/PLEDGER posting through the existing approved accounting service.
- Exit, clearance, final settlement, lawful leave balance and immutable historical records.
- Private HR document storage, authenticated object/role/location access, encryption at rest, site-managed keyring, rotation/recovery and encrypted backup/restore.
- Employee self-service for payslips, leave requests and authorized personal information, without exposing other employees or sensitive records.

**Rule authority:** the attached gap-analysis statement that an approved rule set exists must be reconciled to the current repository/Notion evidence before activating live payroll. GitHub Issue #23 and the current 16.2.18 manifest still describe statutory rule activation as requiring an approved effective-dated bundle and qualified Ethiopian review. Historical PAYE constants, SmartERP examples, 7%/11% pension defaults, a rule version label or a located official PDF are not by themselves legal approval. Until the signed approval record and clause/effective-date mapping are found, test the calculation engine with clearly synthetic vectors and keep only the affected statutory finalization blocked. Do not block unrelated HR or ERP operations.

### P1 — Wholesale order-to-invoice

- Customer-specific price tiers and discount authorization.
- Quotations, versioned quote acceptance, sales orders, reservation/availability check, picking, delivery notes, partial fulfilment, invoice and payment allocation.
- Credit limits/holds and audit trail.
- End-to-end test from quote through stock reservation, dispatch, invoice, AR and GL; reject duplicate submissions and over-fulfilment.

### P1 — Accounting and statutory business controls

- Bank/cash statement import, matching, reconciliation and approved adjustment workflow.
- Fiscal-period close/reopen with independent authority, checklist, lock and immutable close evidence.
- Credit/debit notes linked to original invoices and tax treatment.
- Ethiopian VAT/withholding return preparation and reconciliation; do not claim electronic filing without a verified official integration.
- Fixed assets, depreciation, budgets and cost centers as separate reviewed modules, reusing the existing accounting/GL services.

### P1 — Inventory costing and procurement

- FIFO/weighted-average costing only after Owner/Finance selects the policy and approves its accounting consequences.
- Variant-aware lot/batch traceability where operationally needed; do not conflate size/color/variant identity with lot identity.
- Landed cost allocation and purchase returns with auditable supplier/stock/GL effects.
- RFQ, supplier quote comparison, approval, PO, receiving, supplier invoice and payment workflow.
- Reorder-to-purchase suggestions with explicit review, not automatic PO creation.

### P2 — Retail/customer, integrations and local-language capability

- CRM/customer pipeline, loyalty/gift-card rules, promotions and price lists.
- Offline POS queue only with conflict resolution, idempotency, device identity and explicit reconciliation; no offline stock/GL posting that bypasses server authority.
- Fiscal printer/e-invoicing integration only after provider/API, certification, local legal requirements and sandbox credentials are verified.
- Email/SMS/Telegram notifications through documented providers and explicit opt-in/configuration; keep in-app notifications as the source-of-truth audit record.
- Amharic Unicode UI, Amharic-first SOP/JD content, Ethiopian calendar display/input and tested Gregorian conversion. Do not alter stored canonical timestamps or tax effective dates.
- In-app SOP/JD editing and saving with version history, permissions, approval state and audit; downloads remain available as a convenience, not the only workflow.

## 5. Data, migration and release invariants

- Keep one WordPress plugin and one cumulative source tree under `src/stockland-erp-app/`.
- Preserve the exact 16.2.18 installer and its SHA as the protected baseline/rollback input.
- Never DROP, TRUNCATE, reset, recreate, or rewrite the original 49 legacy tables or posted business/payroll/GL/stock history to satisfy a new schema count.
- New tables must be justified by the data model, have unique keys and explicit relationships or equivalent tested integrity, audit fields, retention/security classification and reversible migration.
- No synthetic records, real employee data, production credentials, tax IDs, salary, bank details or customer data in GitHub/CI artifacts.
- Preserve role/capability, branch/location scope, Owner approval, maker-checker, ledger posting and transaction idempotency.
- A missing or unapproved rule blocks only the affected calculation/approval/posting; it must not disable POS, inventory, procurement, finance, reports or unrelated HR.
- Keep all SOP/JD/contract content labelled DRAFT until the designated process owner and any required legal reviewer approves the exact version.

## 6. Free engineering and hosting topology

Cloudflare is not required to create a Docker/Codespaces environment. Do not provision Cloudflare Containers, D1, KV, R2, Supabase or another database just to imitate Codespaces. Use the free/available repository toolchain first:

1. **GitHub Actions:** repeatable PHP 8.3/8.4, Node 22, Python, ZIP/manifest/SHA, security, WordPress and database test matrix. Use synthetic data only.
2. **GitHub Codespaces + Dev Container:** one interactive Linux environment from the canonical delivery branch; use the repository's Docker Compose WordPress + MariaDB 10.11 + MySQL 8.0.46 services, WP-CLI, Composer and browser test harness. Check the account's remaining included quota before long runs; stop the Codespace when idle.
3. **Docker Engine / Compose:** use the container engine supplied by Codespaces or GitHub-hosted runners. Do not require a paid remote container host or Docker Desktop subscription for CI.
4. **Existing WordPress hosting:** remains the production PHP/MySQL origin and system of record. The Owner says the company controls its domain and SSL certificate at a separate host; do not change DNS/nameservers or TLS settings without a specific need and owner-controlled staging test.
5. **Cloudflare Free (optional):** use only if it solves a real edge requirement for the existing domain. The connected account inventory currently shows no DNS zones and the `erp` Worker is static-assets-only with no PHP/WordPress origin bindings. Keep its query-string log redaction enabled. Do not describe it as the ERP runtime or provision a paid tier. If the current host already provides the required SSL/CDN/security, Cloudflare may be omitted.
6. **Other connected apps:** use GitHub for source/PR/Actions; Notion for decision and evidence records; AI2SQL only for SQL reasoning (no Stockland DB is connected); AnythingMCP/Supabase only if a real approved data source is connected. Never migrate the ERP system of record away from its existing MySQL/MariaDB host.

## 7. Execution and acceptance sequence

Every workstream is implemented on an isolated branch from the current canonical delivery HEAD, reviewed as a focused PR, and merged only after exact-head checks pass. No direct production deployment from a feature branch.

| Gate | Required evidence |
|---|---|
| Source intake | Exact parent SHA, clean provenance, file inventory, no silent overwrite |
| Code quality | PHP lint 8.3/8.4, WPCS/PHPCS, JS syntax, CSS/PostCSS, dependency/license and SAST/secret scans |
| Schema | Exact before/after DDL fingerprint; additive-only migration; idempotency; FK/orphan/duplicate checks; rollback snapshot |
| Authorization | Positive and negative role, Owner/Management, branch/location, object and self-approval tests |
| Business E2E | UI → JS → REST → DB → stock/finance effect → audit event; expected HTTP/state/quantity/ETB assertions |
| Payroll | Two synthetic complete periods, rule version/effective-date boundary tests, payslips, payment failure/retry, remittance, GL/PLEDGER reconciliation; legal approval record before live activation |
| UI | Real browser tests on phone/tablet/desktop, search/filter/autocomplete, button handlers, accessibility and theme fallback |
| Recovery | Backup/restore of DB, plugin files and encrypted vault/keyring; restore verification and rehearsed rollback |
| Package | One WordPress ZIP from one canonical commit; manifest, SBOM, build receipt, exact file inventory, independent SHA-256 |
| Release | One evidence matrix bound to the exact final ZIP SHA; Owner/process-owner approval; WordPress in-place update instructions |

A CI pass, candidate merge, code file presence or version number alone does not close a feature. Record PASS, FAIL, BLOCKED and NOT RUN separately.

## 8. Immediate implementation order

1. Reconcile the uploaded gap analysis against the exact 16.2.18 source and manifest; trace every UI control and report handler before coding.
2. Start with P0 dashboard/report filters and reusable HR/Payroll table/search components, preserving all existing routes and permissions.
3. In parallel, inventory the full HR/payroll candidate PR #39 and HRX work by file/behavior/schema/test; choose the safe additive architecture and map the exact UI/API/data flows.
4. Complete the payroll full-cycle service and interface using the approved rule register only when its signed source/approval evidence is found; keep test vectors synthetic and finalization fail-closed until then.
5. Implement wholesale, accounting, inventory costing and procurement in focused vertical slices with balanced stock/ETB/GL assertions.
6. Finish Amharic/Ethiopian calendar, document editing, external notification and other integrations only with verified requirements/provider support.
7. Produce one final cumulative WordPress ZIP, test the exact bytes on MariaDB and MySQL, attach the checksum and install/rollback instructions, then seek the Owner's explicit install authorization.

## 9. Current open tracking

- [Issue #17](https://github.com/aistockland-web/Erp-version-16.1.89/issues/17): distinct Daily POS/Product Master/Receiving import paths, Management Intelligence boundaries and HR/schema governance.
- [Issue #23](https://github.com/aistockland-web/Erp-version-16.1.89/issues/23): HR/payroll lifecycle, data model, statutory source/effective-date/legal acceptance.
- [PR #39](https://github.com/aistockland-web/Erp-version-16.1.89/pull/39): selected 16.2.9 full HR/payroll engineering source, not a cumulative runtime package.
- [PR #48](https://github.com/aistockland-web/Erp-version-16.1.89/pull/48): 16.3.0 HRX design/candidate, not a cumulative 16.2.18 replacement.
- [PR #106](https://github.com/aistockland-web/Erp-version-16.1.89/pull/106): Management Intelligence route separation and manual planning/report snapshots, merged.
- [PR #124](https://github.com/aistockland-web/Erp-version-16.1.89/pull/124): Cloudflare Free inventory and exact 16.2.18 Docker verification, merged; infrastructure/docs only.

**Final product target:** one cumulative, professional, responsive WordPress ERP/PWA update ZIP, derived from the approved 16.2.18 production baseline, with selected later features actually integrated and fully tested—not a Cloudflare demo, not a separate HRX plugin, and not a collection of competing release ZIPs.
