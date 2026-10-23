# Stockland ERP 16.1.95 — Master End-to-End Acceptance Plan
**Current package:** 16.1.95  
**Exact installer SHA-256:** `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`  
**Exact source commit tested:** `c80d3efc84d175ec59cb5b8d6992a3ecab744674`  
**Exact-head GitHub Actions:** [40036018031 — SUCCESS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40036018031)  
**Use this plan together with:** [Full audit](STOCKLAND_ERP_16.1.95_FULL_AUDIT_AND_RELEASE_DECISION_2026-10-24.md), [CAP register](STOCKLAND_ERP_16.1.95_CAP_REGISTER_AND_ACCEPTANCE_CHECKLIST.md), [release provenance JSON](STOCKLAND_ERP_16.1.95_RELEASE_PROVENANCE_AUDIT_2026-10-24.json).

## 1. Status model
- **CI PASS:** passed the exact candidate's source/package automated checks.
- **Owner-reported PASS:** stated on a supplied health screen or separate local transaction report; not directly repeated by the package builder.
- **PARTIAL:** some contract/source evidence exists, but the complete exact-binary runtime workflow is missing.
- **OPEN / BLOCKER:** requires the named owner, deep-integrity receipt, deployed-source match, independent reviewer or controlled environment.
- **CLOSED:** only when the applicable evidence is attached to the exact installer or the specific accepted business record. A green schema check does not prove all ERP transactions work.

## 2. Release, code and security audit — CLOSED
| Audit type | Result | Receipt |
|---|---|---|
| Locked baseline, deterministic build and exact package attestation | CI PASS | Workflow 40036018031; package hash in provenance |
| ZIP CRC, safe paths, single plugin root, exact inventory | CI PASS | 75 unique files under one `stockland-erp-app/` root |
| Release/cache/version markers | CI PASS | Plugin, route, manifest and CSS marker 16.1.95 |
| Schema/source invariants | CI PASS | 49 tables (29 + 16 + 4); DB marker 16.1.1 |
| PHP syntax | CI PASS | 48/48 |
| JavaScript, JSON, CSS/PostCSS and CSS source/build parity | CI PASS | Artifacts in exact-head workflow |
| WordPress coding standards and reviewed database/security rules | CI PASS with style baseline | 0 errors, 28 warnings, 1 fixable style/standard warning; security/database review PASS |
| Semgrep PHP/security scan | CI PASS | 48/48 packaged PHP files scanned; 0 findings |
| Dependency and bundled Bootstrap license | CI PASS | Bootstrap 5.3.8, MIT, version/hash/source match |
| Desktop Product Master create | CI PASS | 7 desktop widths; release identity derived from tested archive |
| Reports error/retry recovery | CI PASS | Browser receipt |
| Requisition approval history/table responsive | CI PASS | 7 viewport fixtures |
| Work-area and simulated-role responsive smoke | CI PASS | 175 view/viewport pairs, 6 synthetic roles, no measured overflow/console errors |
| Plain and pretty permalink REST URL behavior | CI PASS | 3/3: query preserved; untrusted origin rejected |

A PHPCS warning is not hidden: the raw report contains 28 warnings and 1 fixable style/standard warning. Those counts are not the same as vulnerability counts. Semgrep's zero findings means all 48 PHP files were actually scanned, unlike the earlier zero-target scan.

## 3. System Health baseline — owner-reported 24 October 2026
- Environment shown: WordPress 7.1.3, PHP 8.4.27, MariaDB 11.4.13, 512M memory, upload 20M / POST 30M.
- Plugin/DB: 16.1.95 / 16.1.1.
- Schema: Core 29/29; full schema 49/49.
- REST: 104 v16 routes.
- All displayed non-payroll readiness checks PASS, including db, PHP runtime, SSL, frontend route, XLSX/XMLReader/streaming/upload capacity, accounting/HR upgrade readiness, ledger runtime/audit chain, balances, IDs, ledger references, no negative stock, reservations, AP/AR, GL, procurement, authorization-scope schema, import schema, product master, daily POS, warehouse receiving, sourcing, variant-first identity, PWA and no external-provider/scheduler/database-trigger runtime.
- Supplemental integrity: CHECKS CLEAN with zero non-zero/unknown counts reported; audit chain check passed.
- DB foreign-key metadata: NOT_ENFORCED; no SQL foreign keys were observed. Keep this as an explicit data-integrity/schema design task, not as proof that the supplemental orphan counts are non-zero.
- Only displayed failed production gate: `payroll_compensation_classification`; 9 active employees are missing current compensation records.

The Health text was provided by the owner and is not a direct live database query made by CI. The separate “Run Deep Integrity Audit” button must produce a run ID, times, full scope and per-check receipt before deep integrity is closed; a clean supplemental scan alone is insufficient.

## 4. Master operational acceptance matrix

| Workflow / audit family | Expected source-of-truth behavior | Evidence status | Exact closure evidence still needed |
|---|---|---|---|
| Product Master / variants / SKU | Variant-first identity, stable SKU, no physical stock from product creation, audit history for SKU changes | CI contract + owner Health PASS | Exact-binary authenticated Product Master transaction, including edit/import behavior |
| Product & master Excel import | Preview validates new/existing SKU, prices, stock impacts, missing fields and duplicates; commit is atomic and replay-safe | PARTIAL | Daily POS and Product Master preview/commit/replay/resume tests against exact ZIP with row/job IDs and before/after ledger counts |
| Central Warehouse receiving / purchase orders | Receipt updates stock/ledger once; over-receipt rejected; VAT/AP uses approved amounts | Owner local report; source fixes in CI | Exact-binary receipt with one-post check, replay check, over-receipt 409 and voucher reconciliation |
| Reservations / requisitions | Available = physical − active reservations; request/approve/reserve/pick/dispatch/in-transit/receive/completed never double counts | Source/Health checks PASS; owner local report partial | Exact-binary full transition receipt across warehouse and a permitted branch; concurrent reservation test |
| Transfers and branch receiving | Transfer is not a sale; dispatch/in-transit/receipt changes only canonical states; duplicate receive prevented | Owner local test report partial | Exact-binary transfer receipt including replay, variance and immutable workflow history |
| POS / sale / tax / COGS | One accepted sale, one stock decrement, one VAT amount, one COGS/ledger set; no sale after close | Owner local report; exact-package UI CI is mocked | Exact package runtime transaction and DB voucher/stock reconciliation, with request ID and source hash |
| Day open/close | Opening/closing totals are ledger-driven; close locks a specified business date; corrections go through reversal/adjustment | Source hardened; owner local test reported close guard | Verify UI sends an explicit date, 400 on missing date, closed-day 409 and authorized reversal path in exact binary |
| Returns / damage / adjustment | Approved stock adjustment is transactional, audited and reversible; no destructive editing of posted history | Source/Health contracts partial | Controlled transaction matrix: success, permission denial, audit-write failure rollback, before/after balances |
| Reports / printing/export | Read-only query; report source errors fail closed; scope/cost/location permissions apply; CSV/print output matches ledger | Report UI CI PASS; Health references PASS | Authenticated exact-binary report totals compared with sales, inventory, returns, AP/AR, GL; test print/CSV/PDF where enabled |
| Financial ledger / AP / AR / GL | Posted amounts reconcile by source reference; trial balance balances; VAT and COGS have consistent source transactions | Owner Health PASS; local example 6,740 = 6,740 | Exact-binary voucher-level evidence and month/day roll-up tie-out; no historical rewrite |
| Payroll compensation | Each active employee has approved effective-dated comp, taxable/exempt allowance evidence and pensionable salary consistency | **OPEN / BLOCKER** | HR/Owner resolves the 9 real missing rows; rerun payroll readiness and preview with audit actor/time/approval |
| Ethiopian payroll/tax/pension | Effective-dated legal rules, tax base, pension and allowance classification independently reviewed | **OPEN / BLOCKER** | Written independent current Ethiopian payroll/tax adviser approval plus independently calculated boundary cases |
| HR lifecycle | Hire, compensation change, leave/termination, final settlement and journals reconcile | **OPEN** | Full authorized lifecycle acceptance using approved test records; no synthetic changes to live employees |
| REST access / CSRF / IDOR / roles | Nonce, capability and active ERP profile checks; location boundary enforced server-side; no role privilege inflation | Source review CI PASS; separate local 1,000-request test reported | Repeat named route matrix against exact ZIP on isolated WordPress; retain anonymous/no-nonce/role/location test receipt |
| Setup/first activation | No role sync or column DDL before required tables/columns exist; protected setup remains authorized and resumable | Source fix + CI contract PASS | Clean WordPress 6.8+ activation/setup cycle, repeated setup poll under supported permalink modes, no DB errors |
| Database integrity / deep audit | Audit chain, no orphans, reservation/ledger/AP/AR/GL references; report unknown/deferred checks explicitly | Owner supplemental result CLEAN; deep action not identified by run ID | Run full read-only Deep Integrity Audit, attach run ID/start-end/scope/every check, classify FK NOT_ENFORCED and complete orphan review |
| Concurrency / idempotency / rollback | Locks prevent overselling/double reservation; duplicate request safe; failed business/audit post rolls back all related writes | Not covered by UI mocked REST | Isolated DB concurrency, request replay and audit/ledger failure injection with DB assertions |
| Global search / setup status | WordPress administrator or valid active ERP profile only; no disclosure to profile-less logged-in WP account | 16.1.95 source guard PASS | Repeat profile-less, inactive profile, wrong location and administrator route matrix against exact binary |
| UI / PWA / host theme | Responsive phone/tablet/desktop, route/cache markers refresh; offline limitations not overstated | CI fixtures PASS, owner local 24 views / 390px reported | Exact installed browser matrix, service-worker reload check, production theme and PHP-FPM checks |
| Cloud AI / Management Intelligence | Source-backed permission-controlled insights; no provider claims without configuration; proposals must require human approval | Status reviewed and documented | No external provider is configured. If requested, require a separate approved integration and privacy/security audit before transmitting ERP data |
| External integration connectors | No false “connected” label for Teams/Slack/chat | Policy requirement | Verify UI marks unavailable unless credentials/provider connection verified |
| Deployment, backup, rollback | Deployed code matches approved hash; rollback files and database backup restorable | **OPEN / BLOCKER** | Verify current deployed files and SHA parity; test restoration/rollback before any replacement |
| Owner SOP acceptance | Exact release SHA, business workflow, roles, exceptions and rollback accepted by Owner | **OPEN** | Signed/evidenced owner acceptance tied to installer SHA |

## 5. Cloud AI classification
There is no configured external generative Cloud AI provider in this installer. “Management Intelligence” is deterministic analytics sourced from authorized ERP data, not proof of an external model connection.

The prior Management Intelligence add-on QA record (0.1.2) reports 11/11 focused assertions but explicitly omits activation, real provider credentials/payload, live role E2E and restoration tests. Its QA-reported SHA `9914fbd06c03a7caf207de87d4643ff786072515b742ca171a2ca746a865e272` conflicts with the available checksum sidecar value `8c24751b105407d5125db175f8fffc29f81c51270c7e042bc8612d226bad1e4d`. A 0.1.4 sidecar is present in the library but its matching ZIP/source was not found in the current workspace. Neither add-on archive is part of the exact 16.1.95 core package. Therefore the core release audit passes, but the separate Cloud AI/add-on inclusion requirement remains open until the intended exact artifact is recovered, verified and integrated/tested. The `no_external_provider_runtime` PASS is a security boundary check, not evidence that AI is connected.

No external model should receive employee, financial, customer or stock data unless Stockland has approved the service, data policy, credentials and controls. AI must never write stock, payroll or ledger records directly.

## 6. Final acceptance rule
The 16.1.95 **package/code/security** audit is closed by exact-head CI run 40036018031. A full production certification is **not** closed: the nine missing compensation rows, independent statutory sign-off, deep-integrity receipt, exact installed-source hash, verified backup/restore/rollback and owner/live workflow acceptance remain evidence-gated. Version markers, source-level predicates and mocked UI calls cannot substitute for those controls.
