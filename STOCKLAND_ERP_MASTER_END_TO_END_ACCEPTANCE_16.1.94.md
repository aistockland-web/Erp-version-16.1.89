# Stockland Ethiopia ERP — Master End-to-End Acceptance & Release Gate

**Prepared:** 23 October 2026  
**Current live version reported by Owner:** Plugin 16.1.94 / DB 16.1.1  
**Current host reported by System Health:** WordPress 7.1.3; PHP 8.4.27; MariaDB 11.4.13; memory 512M; upload 20M / POST 30M  
**Historical review branch:** `feat/reports-explorer-16-1-93`  
**Status:** **NO-GO for a new installation/update until source parity and acceptance blockers are resolved.**  
**Related CAP:** [STOCKLAND_ERP_16.1.93_CAP_REGISTER_AND_ACCEPTANCE_CHECKLIST.md](STOCKLAND_ERP_16.1.93_CAP_REGISTER_AND_ACCEPTANCE_CHECKLIST.md)  
**Related full audit:** [STOCKLAND_ERP_CONSOLIDATED_FULL_SYSTEM_AUDIT_AND_ACCEPTANCE.md](STOCKLAND_ERP_CONSOLIDATED_FULL_SYSTEM_AUDIT_AND_ACCEPTANCE.md)

## 1. The single project target

Deliver one complete, operational Stockland Ethiopia ERP for the existing business—not another sample app, alternative architecture, or documentation-only pass. Match the *control outcomes* expected from mature ERP/SaaS systems (role separation, end-to-end workflows, approvals, traceability, exact-once posting, inventory/accounting reconciliation, safe imports, reliable reporting and responsive work areas) while implementing Stockland's own SOPs and staying compatible with its hosting environment. “100% identical to Odoo/SAP” is not a verifiable or suitable engineering requirement; **100% of Stockland's approved operational requirements and release acceptance controls must pass** before making a production-ready claim.

## 2. Non-negotiable production-preservation boundary

- One existing plugin root: `stockland-erp-app/`. WordPress is the platform; PHP handlers run inside WordPress; MySQL/MariaDB is the existing database. Keep the current same-origin REST authorization model.
- Keep the existing database contract at **49 tables: 29 Core/Operations + 16 Accounting/Procurement + 4 HR/Payroll**, with DB marker 16.1.1 unless a demonstrated requirement has an explicitly reviewed migration plan.
- Preserve Stockland's SOP transitions, role/capability/location scope, source-of-truth identifiers, inventory ledger, audit hash chain, AP/AR, PLEDGER and GL, import resumability, row locks, idempotency, effective dates, historical records and posted accounting. Posted history is corrected through approved reversals/adjustments, never silently overwritten.
- Do not add a separate Node.js/Express/PostgreSQL runtime, required Cron/daemon/worker, demo company/data, mock business API, test routes, QA fixtures or second staging/test app to the installable ZIP. CI scripts may exist in the repository's external `ci/` area only. Existing production PHP, JavaScript, required JSON/PWA manifests and transactional *import staging* must not be deleted indiscriminately.
- Do not run blanket word replacements for “server”, “FL”, “AP”, “AR”, “staging”, or similar tokens. Classify each source occurrence by function; remove only confirmed obsolete hostnames, unsupported runtime assumptions or stale developer-only text. “Staging” can legitimately mean temporary import records or safe transactional table rebuilds.
- No synthetic data may be written to the live database merely to make Health green. No actual employee compensation, stock, payroll, journal or date history may be rewritten to pass tests.

## 3. Version and provenance blocker

The Owner's live System Health screen identifies **Plugin 16.1.94 / DB 16.1.1**. The accessible draft GitHub workstream is **16.1.93**, built from a 16.1.92 ZIP. The accessible historical source also has a different POS renderer from the live screenshots. Therefore the 16.1.93 draft is **not safe to upload as an update to the current live site**.

Before source modifications:
1. Recover the exact 16.1.94 installer/source currently installed and record its checksum and archive inventory.
2. Match source version/cache/schema markers to the Owner's current live screen.
3. Create a file-by-file baseline manifest and hash protected PHP controllers, DB/schema activator, inventory/finance/HR services, REST capability checks, audit and import services.
4. Apply fixes to that precise baseline as one cumulative in-place update. Keep current functionality; increment the release/cache identity only after build.
5. Publish a single ZIP with one plugin root and a verified SHA-256. No old 16.1.93 artifact is an acceptable replacement for the current 16.1.94 installation.

## 4. Live System Health evidence captured from the Owner's screen

### Reported host and schema

| Indicator | Owner-provided live value | Interpretation |
|---|---|---|
| WordPress | 7.1.3 | Live Health display only; no independent host query was made |
| PHP | 8.4.27 | Target hosting runtime reported by Health |
| Database | MariaDB 11.4.13 | Target database engine/version reported by Health |
| PHP memory | 512M | Reported limit |
| Upload / POST | 20M / 30M | Reported limits; file workflow must respect effective smaller limit |
| Installed plugin / DB marker | 16.1.94 / 16.1.1 | Mandatory baseline identity for next update |
| Core schema | 29 / 29 | Presence check, not full transactional acceptance |
| Full schema | 49 / 49 | Presence check, not proof that every workflow works |
| REST v16 | 104 routes | Count only; route permission and operation contracts still need coverage |
| Foreign-key metadata | NOT_ENFORCED | Do not assume database FKs exist; preserve application integrity checks and review orphan scans separately |

### Payroll classification gate

The pasted live Health output identifies **9 active employee records with missing current compensation**. It says 0 standard Management-prepared compensation records were accepted and 0 RETAINER_SERVICE contracts were excluded. The failed gate is `payroll_compensation_classification`.

Required handling:
- HR/Owner opens authorized employee/compensation records and verifies actual contracts, effective dates, base salary, each allowance and deduction, taxable/exempt classification, evidence and pensionable salary.
- Do not invent salary values, apply a generic classification to force a green result, or automatically change data during Health checks.
- Retainer/service-provider status must be a fact evidenced by actual contract and approved policy, not a way to bypass employee payroll controls.
- Re-run the specific readiness gate after authorized corrections and verify audit actor/time/reason/approval. Keep unresolved records visible and block only the unsafe dependent operation according to SOP, not unrelated verified ERP workflows.

### Integrity results: reconcile apparently different screen states

The latest pasted text lists PASS labels for audit-chain, ledger references, negative stock, balance reconciliation, reservation integrity, AP/AR, GL balance, procurement references and identifier integrity. It separately reports “supplemental integrity scan: CHECKS CLEAN” and “Audit chain integrity check passed.”

However, the earlier screenshot simultaneously displayed **“Deep integrity checks pending — status UNVERIFIED”** and explicitly listed audit-chain hash, ledger/balance, reservation, AP/AR, GL/orphan, procurement/PLEDGER, duplicate identifier, payroll classification and import/workflow contract gates as not yet checked. The available evidence does not reliably tie both screen states to the same audit run ID/time. Thus:
- Treat the individually listed PASS values as reported Health results.
- Treat the supplemental scan as a limited bounded check, not as proof that the complete deep audit finished.
- Treat the complete deep-integrity result as **UNVERIFIED until a run ID, start/end timestamp, scope and per-check completion receipt prove that every required deep check was actually executed**.
- A query error, timeout, interrupted chunk or deferred scan is UNKNOWN/NOT_RUN, never a zero count or PASS.
- All scans remain read-only. No auto-repair or schema mutation is allowed during the audit.

## 5. Unified Stockland end-to-end workflow map

Every stage must use the existing canonical record, authorized REST path, approval/state machine and durable audit evidence. UI visibility alone is not completion.

### A. Master data and purchasing
Product/variant creation and controlled XLSX import → approved supplier/vendor and purchasing terms → PO request/review/Owner approval → accepted GRN/receipt → supplier invoice and matching → AP liability → payment/clearing → balanced journal. Test partial receipt, rejected variance, duplicate invoice/payment, interruption and rollback.

### B. Inventory and warehouse
Variant-first Product Master identity → immutable receipt/movement ledger → derived location balance → active reservations → sellable availability → requisition/approval → reservation → pick/dispatch/in-transit transfer → destination receiving → variance workflow. Returns/damage/adjustments/physical counts require the proper authority, reason, source and audit. Do not make inbound stock physically available before receipt. Verify ledger-to-balance and variant identity by location.

### C. Retail and wholesale order-to-cash
Authorized SKU/barcode resolution → cart line add/update/remove → exact decimal line totals → permitted discount/price override → applicable VAT mode/rate → validated stock and business date → one idempotent sale commit → stock movements/COGS → cash tender or AR → tax/journal → receipt/invoice → returns/refund/reversal → daily close. Multi-line quantities, discounts, tax, roundings and grand totals are recomputed independently. Double click/retry must not double-post. Existing live cart handlers/endpoints must be preserved.

### D. Finance and period close
Customer/supplier master/balances; AP, AR, payments, receipts, expenses, tax, staged COGS and General Ledger/PLEDGER; balanced journals; period/day locks; approved reversals. Require debits = credits and independent reconciliation from source transactions to subledger and GL. No unexplained account imbalance, orphan transaction, duplicate identifier or backdated silent rewrite.

### E. Full HR lifecycle and payroll
Workforce/headcount plan and budget → approved requisition → recruitment/offer where required by Stockland SOP → onboarding and employment contract → department/role/location/probation and effective-dated changes → attendance/shifts/overtime/leave and other SOP-required employee records → compensation/allowances/benefits/loans/advances/reimbursements → payroll cutoff/input reconciliation → statutory calculation → review/approval → payslip/remittance/accounting posting → correction/reversal/period lock → resignation/termination/retirement → clearance/assets/leave/loans/advances → final settlement calculation, approval, payment, certificates and archival.

The current historical candidate's employee/compensation/monthly-payroll controls do not prove this whole lifecycle. CAP requires signed Stockland SOP traceability and independent legal/payroll review; schema gaps must be explicitly assessed before any approved migration. Never assume a formula fixture means full HR is implemented.

### F. Reports and management control
Every report declares source data, metric definition, site-local date basis, authorized location scope, filters, precision/tax/cost basis, allowed roles, empty state, error state, export fields and drill-down. Validate Command Center, sales/product/SKU, stock/reorder, receiving/transfers, requisitions, returns/adjustments, count variances, AP/AR, PLEDGER/GL and payroll reports against independent source records. Failed database reads must show explicit error/retry; never fabricate a zero. Print/PDF prints only the selected report. CSV is safely quoted, formula-injection-resistant, correctly scoped and reconciles to on-screen data.

### G. Imports
Daily POS CSV/XLSX and Product Master XLSX must have clear separate contracts. Validate format/size/encoding/headers/date/location/SKU/variant and duplicates; preview/stage before commit; retain source fingerprint, row counters and cursor; resume after network interruption; protect day close and approvals. Replaying an entire file, row or chunk must be idempotent. Product Master import cannot create physical stock. No summary totals may be turned into fake transactions.

### H. Permissions and auditability
Test Owner, Management, Branch Manager, Warehouse Manager, Sales/Cashier, Finance, HR/Payroll and Employee roles at both UI and direct REST levels. Every consequential mutation checks server-side capability, object ownership and location scope, validates input, uses transaction/locking/idempotency as needed, records actor/timestamp/action/object/location/reason/outcome/correlation ID, and rolls back if a required audit/ledger/GL write fails. A hidden UI control is not authorization.

## 6. Current visible UX defects and required correction

The Owner's mobile screenshots evidence overlapping labels and statuses in “Approval Routing / Decision history”, a cramped request drawer, horizontally clipped warehouse supply lines, and a red report error toast covering the inline report error and content. The Owner also reports the live Cart page is unusable.

The accessible 16.1.93 branch's historical renderer is not the exact live 16.1.94 cart implementation. Do not patch a guessed older component or install a 16.1.93 downgrade. Once the exact live source is recovered:
- Cart: present each item in a clean mobile card with variant/SKU, quantity stepper, permitted price/discount, line totals, remove action, subtotal, VAT and total; keep scan/search and primary checkout visible. Desktop may use a spreadsheet-style table.
- Approval routing: place approver, decision, status, timestamp, notes and badge into a wrapping grid; never overlap text; keep each action accessible.
- Supply lines: keep the page width within the viewport. If a table is necessarily wide, horizontal scrolling stays inside the table wrapper and not across the page.
- Reports: retain fail-closed server behavior; display a single clear, accessible message with Retry and System Health guidance; avoid duplicate intrusive toast; never hide a real source/database error.
- Shell: preserve Stockland brand, search/menu/profile/notification behavior, Bootstrap 5.3.8 and current visual system. Remove duplicate workspace/command sections only where actual DOM evidence proves duplication. Keep page/table headings from covering content.

## 7. Master acceptance evidence matrix

Each item is PASS only when the named test ran against the **same exact built ZIP** and recorded its source commit plus package SHA-256. Local unit/static checks are necessary but are not proof of live database or mobile operation.

| Gate | Required evidence | Current state |
|---|---|---|
| Source/deployed version parity | Exact 16.1.94 installer/source, checksum and protected-file baseline | **FAIL / MISSING** — branch is historical 16.1.93 |
| Package shape/integrity | ZIP CRC, one plugin root, inventory, JSON, PHP lint, JS syntax, CSS module/compiled parity, manifest/version/cache match | OPEN on current 16.1.94 baseline |
| Schema/setup | 49-table schema, interrupted setup recovery, unchanged schema markers unless approved migration | Reported 49/49; full install/recovery still needs evidence |
| Health/deep integrity | Each check has completed status/time/run ID; errors UNKNOWN; read-only semantics | Some PASS reported; full deep result needs final receipt due earlier UNVERIFIED screen |
| Payroll readiness | 9 actual compensation exceptions lawfully resolved; no synthetic value; signed rule matrix | **FAIL** — 9 records need review |
| HR lifecycle | SOP coverage from workforce planning through final settlement | **OPEN / substantive feature gap** |
| Report loading | Current report error → Retry → success; no fake zero; all types reconcile | **FAIL observed on live screen / regression CI not yet proven on current branch head** |
| Cart/requisition mobile UI | Exact 16.1.94 source and viewport/handler regression | **FAIL observed / source parity missing** |
| Stock/warehouse SOP | Variant-first stock, reservation, transfer, receipt, variance, return/damage/adjustment reconciliation | Reported invariants PASS in Health; end-to-end operational evidence still open |
| Finance | AP/AR, taxes, payments, COGS, PLEDGER/GL source-to-journal reconciliation | Health reports several PASS; independent end-to-end acceptance open |
| Security/authorization | Negative permissions, location isolation, protected REST routes, concurrency/idempotency | OPEN pending exact-source route matrix |
| Import/recovery | Preview/stage/commit, interrupted resume, replay, rollback, closed-day and audit evidence | Contracts reported PASS; full operational edge matrix open |
| Mobile/desktop accessibility | 320–430 and desktop viewports, keyboard/focus, no overlap/hidden action, print/CSV | **FAIL observed in supplied screens** |
| Backup/rollback | Backup identity, restore proof, rollback checklist, post-update integrity | **NOT PROVIDED / OPEN** |
| Target installation acceptance | Authorized exact-host update smoke tests and installed-version/hash receipt | **NOT RUN** |

## 8. Safe execution sequence

1. Secure the current 16.1.94 plugin ZIP/source and verify its checksum; do not install the historical 16.1.93 candidate over it.
2. Confirm a restorable backup of the current WordPress files and MariaDB data. Any validation copy remains outside the source tree and installable ZIP; no test runtime is shipped.
3. Build a source-to-SOP route/handler manifest for every ERP module and role. Include each one of the 49 tables and all current REST v16 routes; resolve authorization and persisted payload contracts.
4. Implement the narrowly reviewed cart/approval/report UI fixes on the exact 16.1.94 source; preserve protected business code and existing state transitions. Implement the missing HR lifecycle rather than describing it as done.
5. Run exact-head tests: PHP and JavaScript syntax, archive inventory/hash, CSS source/compiled parity, security/static analysis, browser failure/retry flows, full mobile/desktop UI matrix, import replay/recovery, stock concurrency, finance balance and role/location denials.
6. Run the read-only deep audit on the authorized installed/validation database; attach per-check results with no FAIL/UNKNOWN remaining. Treat nine payroll classifications as a separate authorized HR task.
7. Verify full workflow outputs and independent quantity/ETB reconciliations. Obtain Stockland process-owner sign-off for Warehouse, POS/Wholesale, Finance, HR and reporting.
8. Only then create one cumulative WordPress installer from the same exact source head, publish its checksum and audit receipt, and get Owner approval for an in-place update. After installation, re-check the installed version/hash, quick/deep Health, report loading, cart, core workflows, history and role access.

## 9. Local recheck receipt and exact-head automation state

The [Local Recheck Receipt](STOCKLAND_ERP_LOCAL_RECHECK_2026-10-23.md) records what was actually run locally against a historical 16.1.93 candidate (SHA-256 `a8cb3767f363740387731928211febc315876a7b8c146e65e594930940e64e3b`). It passed ZIP structure/integrity (75 unique files, one plugin root), PHP syntax 48/48, JavaScript syntax, JSON parse, PostCSS parsing and ordered CSS source/build/minified parity. A temporary local Playwright/Chromium copy with the proposed report-error behavior passed a request sequence of initial success → explicit report-source failure → retry success; the error remained inline, no duplicate toast covered it, the 390px viewport had no page-level overflow, print/CSV controls were disabled on failure and re-enabled after success, and no browser exceptions occurred.

**Evidence boundary:** this was not a build or test of the exact current GitHub branch source, the exact live 16.1.94 ZIP, or live WordPress/MariaDB. GitHub reports zero check runs directly on commit `5fa1bdc5bc3219e55c97b47774f6957dafacc417`; the related Actions runs are from 20 October and reference older source/build state. The current source contains the narrow report-error fix in the build script and regression assertions, but exact-head package/build/CI acceptance is still open.

The historical candidate scan also confirms that the reviewed source exposes an atomic single-SKU POS form rather than the live multi-line Cart implementation shown in the Owner's screenshots; it still has stale host diagnostics / PHP 8.4+ wording and older manifest test-tool metadata before the current source-cleanup builder is applied. Those are reasons to require exact-head packaging and current-source parity, not to upload the old archive.

## 10. Latest local recheck evidence (23 October 2026)

The [Local Recheck Receipt](STOCKLAND_ERP_LOCAL_RECHECK_2026-10-23.md) and [local acceptance decision](STOCKLAND_ERP_LOCAL_ACCEPTANCE_RECHECK_2026-10-23.md) record the available candidate-level results.

**Review fixture identity:** `STOCKLAND_ERP_16.1.93_LOCAL_REVIEW_FIXTURE.zip`; SHA-256 `784bea5f7db6cee28c131d8ca4b9dea2edfea3d38115b4bead580e2344f614f5`; 1,119,373 bytes; release `16.1.93`. The archive has 75 unique plugin files, one `stockland-erp-app/` root and valid ZIP CRC. It is a review fixture—not a WordPress update approved for live 16.1.94.

Local checks pass: PHP lint **48/48**; app.js/pwa.js syntax; release/PWA/CSS JSON parsing; PostCSS parse for app.css/app.min.css/Bootstrap; deterministic CSS source-module/build/minified parity; six CSS modules and 61 responsive media-query occurrences.

Playwright/Chromium browser checks pass on the temporary fixture:
- **175 view/viewport combinations** (25 work areas × 7 CSS widths: 320, 360, 390, 430, 768, 1024, 1440) across **6 role profiles**; zero blank views, horizontal page overflow, browser exceptions or console errors in the captured run.
- Report flow: initial success → induced source failure → inline recovery with no duplicate toast → successful retry (3 mocked requests); report print/CSV actions disabled on failure and enabled after recovery; 390px viewport has no page-level overflow.
- Requisition approval history/supply-table geometry: 7 widths; no text/status-pill overlap, page overflow or table wrapper outside its drawer; zero page errors.

These are **local frontend regression results against synthetic API responses**. They do not test live database source queries, stock/finance transaction posting, exact server permissions, real employee records, complete HR lifecycle, deep integrity, restore or rollback.

The draft CI workflow now includes dedicated test steps for report recovery, desktop Product Master create, requisition responsiveness, all 25 work areas/6 role profiles, and modal-specific CSS/PostCSS parity. The latest GitHub source commit is not accompanied by a new exact-head workflow/artifact receipt yet; older green runs do not certify the newest commits.

The blockers remain:
1. Exact installed 16.1.94 package/source and checksum are unavailable in the accessible repo; the reviewed source lineage is 16.1.92 → 16.1.93. Do not upload it over live 16.1.94.
2. The true live Cart/POS source and its multi-line behaviors are still unverified; the older candidate renderer is not proof of a live fix.
3. Live payroll readiness fails for **nine active employee records** with no current compensation; HR/Owner must verify true data and classification.
4. The full workforce-to-final-settlement HR lifecycle needs approved-SOP traceability and operational acceptance.
5. The deep-integrity panel's UNVERIFIED state and separate PASS/supplemental results need a single run ID/timestamp and per-check completion receipt.
6. The live report source-read/database failure remains undiagnosed even though the UI error recovery is improved in the review candidate.
7. Exact-head CI, end-to-end stock/ETB reconciliation, server-side role/location denial tests, import replay/idempotency, backup/restore/rollback and Owner sign-off remain open.

## 11. Release decision

**Current decision: NO-GO.** This is not a statement that the live ERP is wholly down; it is a statement that the evidence supplied contains a failing payroll readiness gate, a report source-read error and serious mobile UI defects, while the branch package under review is older than the installed plugin. The next approved installer must correct these issues on the true 16.1.94 baseline and prove the complete Stockland SOP end-to-end. Do not claim “100% operational” until every critical row in the master matrix has current evidence and Owner sign-off.
