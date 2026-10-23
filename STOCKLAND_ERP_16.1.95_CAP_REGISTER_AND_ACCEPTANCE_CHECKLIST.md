# Stockland ERP 16.1.95 — CAP Register and Acceptance Checklist
**Record date:** 24 October 2026  
**Exact installer SHA-256:** `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`  
**GitHub exact-head run:** [40036018031 — SUCCESS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40036018031)  
**Closure rule:** close only what has reproducible evidence. Code/package/CI closure is separate from live operational and Owner gates.

| CAP | Status | Evidence and remaining action |
|---|---|---|
| CAP-01 — Build, package provenance and checksum | **CLOSED — PASS** | Exact 16.1.95 artifact ID 12536703884; 75 unique files, single plugin root, 48 PHP files; SHA, sidecar and release receipt match. |
| CAP-02 — Exact-head CI and security gates | **CLOSED — PASS** | Run 40036018031 passes. WPCS has 0 errors, 28 warnings and 1 fixable style/standard item; reviewed security/database checks pass. Semgrep scanned 48/48 PHP files with zero findings. Dependency/license inventory passed. |
| CAP-03 — Host acceptance, backup/restore/rollback | **OPEN** | Owner screen reports WP 7.1.3, PHP 8.4.27, MariaDB 11.4.13 and SSL PASS; no verified restoration or rollback receipt was supplied. |
| CAP-04 — Missing current compensation for 9 employees | **OPEN — OWNER ACTION / BLOCKER** | HR/Owner must inspect actual contracts and enter approved effective-dated compensation. Do not invent or auto-fill salary values. |
| CAP-05 — Current Ethiopian payroll/tax/pension sign-off | **OPEN — OWNER ACTION / BLOCKER** | System Health payroll-statute model reports PASS, but independent current rule-to-law approval remains necessary. |
| CAP-06 — Stock/reservation/reorder consistency | **CLOSED for code and reported health contracts** | The missing reservations binding is fixed; owner Health reports reservation integrity, balance reconciliation and no-negative-stock PASS. Live transaction acceptance is CAP-13. |
| CAP-07 — Sales/VAT/COGS reconciliation | **PARTIAL** | Owner-supplied local report gives ETB 690 = 600 revenue + 90 VAT and COGS 300. The exact tested ZIP hash was not attached. |
| CAP-08 — Procurement/AP/AR | **PARTIAL** | Health reports AP/AR and procurement reference PASS; local report gives PO 50 × 100 plus 15% VAT = 5,750 and receipt replay protection. Tie transaction receipt to exact artifact. |
| CAP-09 — General ledger/trial balance | **PARTIAL** | Health reports GL balanced; local report says 6,740 = 6,740. Keep voucher-level evidence tied to the deployed SHA. |
| CAP-10 — Report SQL aliases and retry UI | **CLOSED — PASS** | Reserved `rows` / `lines` aliases quoted; reports error/retry/recovery browser test passed. |
| CAP-11 — XLSX/XMLReader/stream/upload diagnostics | **CLOSED for owner-reported Health checks** | The supplied Health screen reports all XLSX, XMLReader, streaming and upload-capacity checks PASS; host-specific upload transaction remains environment-dependent. |
| CAP-12 — REST authentication, nonce and role/location isolation | **PARTIAL** | Owner describes 1,000 auth/nonce requests and cross-location denial in a disposable test. The exact 16.1.95 CI workflow does not execute that full runtime matrix against a live WordPress database. |
| CAP-13 — Receiving, transfer, POS sale and daily close | **PARTIAL** | Owner local report describes stock movement and financial values. 16.1.95 additionally requires explicit `business_date` for close. Tie an end-to-end transaction receipt to the exact ZIP. |
| CAP-14 — Import idempotency, replay and resume | **OPEN** | PO receipt replay is not the same as Daily POS and Product Master import preview/commit/replay/resume. Run that matrix and preserve before/after ledger evidence. |
| CAP-15 — Plain/pretty permalink query preservation | **CLOSED — PASS** | Exact-package Node tests 3/3: query parameters preserved and untrusted REST origins rejected. |
| CAP-16 — Dedicated deep-integrity run receipt | **OPEN — OWNER ACTION** | Supplemental scan reports CHECKS CLEAN and audit chain PASS. The separate deep-audit action needs run ID, timestamps, full scope and per-check results. |
| CAP-17 — Stale host/domain and dependency provenance | **CLOSED at package scope** | Exact package gate passes; Bootstrap version/license/hash match the bundled asset and manifest. |
| CAP-18 — First activation/setup ordering | **PARTIAL** | Source guardrails defer role sync until the profiles table exists and gate expense DDL on table/column existence. Clean activation on a WordPress 6.8+ test host has not been tied to this exact SHA. |
| CAP-19 — Audit/ledger failed-post rollback | **OPEN** | Inject a controlled posting/audit failure only in a disposable database and show both mutations roll back. Never run failure injection on production. |
| CAP-20 — Optional chat connectors | **NON-BLOCKING only if visibly unconfigured** | Teams/Slack/chat must not be represented as connected unless configured. This is separate from Cloud AI. |
| CAP-21 — Independent statutory payroll review | **OPEN — OWNER ACTION / BLOCKER** | Same rule-to-law sign-off requirement as CAP-05. |
| CAP-22 — Complete HR lifecycle through final settlement | **OPEN** | Run authorized hire/change/termination/final-settlement and reconciliation tests with approved test records. |
| CAP-23 — Product Master / no-stock-on-product-import / variant identity | **CLOSED for package/health contracts** | Exact-package contract tests and owner Health results support these checks. Deployed-source parity remains CAP-28. |
| CAP-24 — Setup-status and profile-less search access | **PARTIAL** | Source-level access guards are in 16.1.95. Full authenticated/anonymous request matrix must be tied to exact deployed-source identity before runtime authorization is closed. |
| CAP-25 — Cloud AI / Management Intelligence | **CLOSED as accurately classified; external AI not configured** | Management Intelligence is authorized deterministic ERP analytics; the plugin does not call an external generative provider. A real provider integration would require separate owner approval, data policy, credentials and security review. |
| CAP-26 — Supplemental versus complete deep integrity | **OPEN** | Zero-count supplemental scan is not a substitute for the dedicated deep-integrity run receipt. |
| CAP-27 — Responsive UI and work-area navigation | **CLOSED — PASS for exact-package browser fixtures** | Product create at 7 desktop widths; requisition at 7 widths; 175 view/viewport pairs, 6 synthetic roles, no measured overflow or browser-console failures. |
| CAP-28 — Exact deployed-source parity | **OPEN — BLOCKER** | The Health label 16.1.95 is not a file hash. Compare the deployed plugin directory against SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`. |
| CAP-29 — Signed Owner/SOP acceptance | **OPEN — OWNER ACTION** | Sign acceptance tied to exact SHA after reviewing payroll exceptions, source parity, backup/rollback and operational SOP matrix. |
| CAP-30 — Deployed Cart/POS handler parity | **PARTIAL / OPEN** | Owner-supplied local report describes sale/VAT/COGS/GL success, but did not include the tested package hash. Verify deployed handler parity and one-post transaction behavior against the exact SHA. |

## Owner-provided System Health summary — 24 October 2026
The supplied screen reports all listed non-payroll checks PASS, including 29/29 core tables, 49/49 ERP tables, 104 REST v16 routes, SSL, XSLX/XMLReader streaming, audit-chain integrity, ledger/balance/AP/AR/GL/procurement references, no negative stock, reservation integrity, import and Product Master contracts, warehouse receiving, sourcing and supplemental integrity `CHECKS CLEAN`. It lists only `payroll_compensation_classification` as a failed production gate: nine active employees have missing current compensation.

The database reports `NOT_ENFORCED` foreign-key metadata because no SQL FK constraints were observed. A full orphan/schema review remains a separate design requirement even though supplemental checks report zero non-zero/unknown counts.

## Final release decision
**GitHub package/source/code/security audit: CLOSED — PASS for exact 16.1.95 SHA above.**  
**Production readiness: NOT fully certified until the OPEN owner/live CAPs are supported by evidence.** The payroll gate must remain visible until the nine real records are corrected through authorized HR/Owner workflow. No sample compensation, historical posting overwrite, or live data mutation is allowed as a release workaround.
