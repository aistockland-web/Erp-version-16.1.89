# ERP-wide audit refresh and release gates — 29 October 2026

**Disposition: NO-GO for production update.** This refresh adds source-level table traceability and individual document-controlled SOP sources. It is an audit record, not a deployment instruction, release certificate or legal opinion.

## 1. Exact baseline evidence

Recovered and independently re-hashed GitHub Actions artifact ID 12536703884. The inner canonical 16.1.95 WordPress installer is **1,102,405 bytes**, SHA-256 **`494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`**; archive CRC passed; it has 75 unique paths and 48 PHP files. Independently extracted source shows **49 registered logical table keys matching 49 static DDL declarations**: 29 Core/Operations, 16 Accounting/Procurement and four legacy HR/Payroll. The exact DDL source path/line and mapped SOP IDs are in the adjacent CSV/JSON.

The previously recorded installed 16.1.97 candidate SHA-256 is `7822606ded2883d9d5faf8b58962f058c99320d64fcf2da50c9f140448adee30`, but its binary is unavailable here and was not re-hashed in this session. Do not claim current deployment/source parity until the exact installed ZIP and deployed source hashes are recovered.

## 2. Live host and CI truth

The target `https://slerp.xo.je/stockland-erp-app/` failed DNS resolution from this execution environment (temporary resolver failure). Therefore live WordPress, REST, downloads, role-denials, MariaDB writes, payroll transactions and visual/browser E2E were **NOT RUN**. This is neither a runtime pass nor sufficient evidence that the site itself is down.

PR #46 remains open and documentation/QA oriented. Its prior run passed listed code/package security gates such as secret scanning, exact baseline installer validation, CodeQL JS/TS and Actions, ShellCheck/actionlint and dependency/license inventory. Some dependency/PHPCS/candidate build test jobs were skipped or not applicable. A skipped job is **not a pass**, and static HRX assertions, syntax checks, mocked browser tests, and ZIP CRC checks do not establish live WordPress behavior.

The 16.1.98 HRX/SOP review ZIP was based on 16.1.95 and must not be installed over the reported 16.1.97 because the exact 16.1.97 binary/resource set was not available to prove cumulative preservation. No production code, database or live rows were modified in this audit.

## 3. All-ERP data table source coverage

All 49 logical table keys from the locked 16.1.95 source have a DDL file/line and at least one SOP mapping. This proves documentation mapping only—not live schema parity, record integrity, complete AST/data-flow analysis or correct runtime authorization. Runtime physical names are generated from the WordPress database prefix; never hard-code `wp_`.

The four legacy HR tables remain `employees`, `employee_compensation`, `payroll_periods`, and `payroll_lines`. Keep them intact as historical migration sources until an approved additive migration has passed full identity/history/journal reconciliation and rollback. HRX proposed logical table counts in various isolated design slices are not an approved physical schema target.

## 4. Required whole-ERP staging acceptance

| Area | Required proof before release |
|---|---|
| Identity, roles, branch/location boundaries | Authenticated allow/deny matrix, direct-ID denial, revoked-user denial, private-document authorization and export audit |
| Product Master and import | SKU/variant identity, duplicate barcode/SKU, validate-preview-approve-commit, replay/idempotency/resume, and proof catalog imports never create stock |
| Receiving, inventory, transfers, reservations, counts | Separate physical/available/reserved/in-transit; shortage-aware approval; duplicate receive/dispatch rejection; count variance approval; ledger/balance reconciliation |
| POS, wholesale, daily close | Sale retry/duplicate prevention; void/refund authorization; tender/cash/bank reconciliation; explicit business date; correction by linked reversal |
| Procurement, AP/AR, GL/PLEDGER | PO/receipt/invoice match; invoice/payment replay control; AP/AR allocation and aging; balanced journals; period lock; rollback on posting/audit failure in disposable DB |
| HR and payroll | Hire/change/exit; time/leave cutoff; effective-dated compensation; missing-data holds; two parallel periods; gross-to-net vectors; payment outcome/remittance evidence; balanced GL |
| Operations and reports | Source/date/location/provenance flags; SSL/runtime/DB checks; exact schema fingerprint; backup/restore/rollback proof; storage/upload limits; alert delivery tests |

## 5. HR/payroll blockers retained

- Earlier owner-supplied health evidence found nine active employees without a current compensation row. Re-check on the real database and obtain approved contract/compensation evidence; never seed guessed salaries.
- Current PAYE/pension tax-base interpretation, rates and statutory effective dates need qualified Ethiopian tax/pension review. The official income-tax amendment label/year differs between official listing and Gazette reference; reconcile the controlling Gazette citation and effective date before enabling calculations.
- An ERP payroll record marked PAID is not evidence of external settlement. Require a payment batch/reference, per-person success/failure/partial outcome, bank/cash reconciliation, and remittance receipt.
- Preserve old payroll periods, lines and journals until history reconciles. HRX tables are proposed, not proven deployed.
- Personal data must be minimized in audit events. Employee/candidate files remain blocked from public storage until a private vault and record-level access controls pass tests.

## 6. Host-aware server-side monitoring and alert design

Keep the runtime compatible with verified shared WordPress/PHP hosting. Use PHP/WordPress-native health probes and a scheduled task; prefer real system cron only if provided by the hosting plan and report WP-Cron delays/low-traffic risk. Node.js belongs in CI/build/test unless persistent runtime support is explicitly verified. Do not require a resident Node daemon, Redis, queue service or cloud vendor without actual entitlement and operational proof.

Monitor: HTTPS/certificate expiry, PHP fatal errors, DB connection failures, schema key/count drift, REST 401/403/404/409/5xx patterns, nonce failures, audit-write failure, cron heartbeat age, backup age/size/hash and latest restore test, upload/storage headroom, stock-ledger-vs-balance mismatches, unbalanced journals, missing compensation and payroll exceptions. Each alert needs a unique incident/run ID, timestamp, severity, signal/source, deduplication/rate limit, owner, lifecycle status and delivery receipt. Redact passwords, tokens, session IDs, bank credentials and unnecessary PII. Dashboard notifications do not prove email/cloud alert delivery. No external monitoring, AI provider or cloud-alert provider is considered connected until configured and E2E tested.

## 7. Ethiopian statutory / privacy references

- Labour Proclamation 1156/2019: https://justice.gov.et/en/law/labour-proclamation/
- Personal Data Protection Proclamation 1321/2024: https://justice.gov.et/en/law/personal-data-protection-proclamation/
- Ministry of Finance tax-directive register: https://www.mofed.gov.et/mof-directive/tax-directive/
- Income-tax amendment reference requiring Gazette-citation reconciliation: https://www.ffic.gov.et/Portals/0/1395-2017.pdf
- Private organization pension/social-security materials: https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/

These links are starting references, not legal approval. No statutory rate, PAYE/pension formula, leave, withholding, final settlement or retention rule is marked effective by this audit.

## 8. Exact release gates

1. Recover/hash the exact installed 16.1.97 ZIP and deployed source; make and verify a database/uploads backup; restore an isolated staging clone.
2. Fingerprint the actual host/runtime/database schema and document any approved additive tables. Keep source and live schema separate in the receipt.
3. Run exact-head CI against executable changes. Re-run skipped code-quality/build/test gates; show target counts and hashes.
4. Build a cumulative installer that preserves all 16.1.97 resources. Test every SOP download plus anonymous/wrong-role denial, invalid ID, path traversal, tampered/missing hash/file, audit-write failure, filename/MIME, mobile/keyboard and retry behavior.
5. Run synthetic full ERP positive/negative tests, import retry/resume, stock/GL rollback injection, two parallel payroll periods, historical reconciliation, payout/remittance and exit final settlement.
6. Prove restore/rollback and post-restore integrity, reconcile reports/subledgers and resolve actual missing compensation with authorized HR/Owner evidence.
7. Obtain Owner + HR + Finance acceptance and independent qualified Ethiopian legal/tax/pension review. Only then may production be reconsidered.

**Final disposition: source traceability and controlled procedure documentation improved; live E2E NOT RUN; production NO-GO.**

## 11. Offline SOP-pack attestation (current 29 Oct build)

The current offline controlled SOP ZIP includes 24 editable DOCX, 24 rendered PDF, 24 Markdown copies, the 49-key CSV/JSON map, audit/release gate notes and SHA-256 manifest. ZIP contains 77 members and passes CRC; all 76 non-manifest payload files pass their individual SHA-256 entries. Final ZIP size: 4,426,124 bytes. **Current pack SHA-256: `22a48035e7cb70635f2fc01dd508c33db1da5f8363cacc35a1aca981a3c33f66`.** Older recorded SOP ZIP hashes refer to previous packaging revisions and are superseded by this one. The pack is an offline draft/document set, not a WordPress plugin package and not proof that SOP downloads are exposed in the live Process Center.