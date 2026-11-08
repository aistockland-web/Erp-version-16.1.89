# Cumulative ERP Execution Plan — update 8 Nov 2026 (local-first)

**Decision:** continue the existing programme. Operational base = verified 16.2.13. 16.1.95 stays frozen as rollback. No new parallel candidate.

## Done locally
- 16.2.13 identity verified (SHA matches 74e3205d…).
- MySQL 8.0.46 blocker reproduced and fixed (stock_balances PK). Fresh install passes on MySQL 8 and MariaDB (86 tables, 52/52 integrity).
- In-place upgrade 16.2.13→16.2.14 preserves data (table checksums identical). Backup/restore and re-activation pass.
- Three diagnostics/readiness defects fixed (HR marker x2, XLSX gate).

## Next engineering targets (in order, each = commit + tests)
1. Role/location allow-and-deny REST matrix; maker-checker.
2. Operations E2E: Product Master/import → stock → POS/wholesale → receiving/requisition/transfer → returns/damage/count/close.
3. Finance E2E and GL/PLEDGER reconciliation against independent figures.
4. HR lifecycle E2E → two payroll periods → payout/remittance → final settlement.
5. Private-document encryption + SOP authenticated download; verify 24 SOP files.
6. PHPCS/WPCS debt burn-down, Semgrep, CodeQL; PHP 8.3/8.4 matrix; exact MariaDB 10.11.
7. Browser/device/PWA acceptance; print/export.
8. Cumulative regression on exact commit → exact ZIP → backup/restore/rollback rehearsal.

## Owner-required inputs
Payroll legal review & approved rules · real compensation for 9 employees · schema + release approvals · SOP files · live plugin-dir hashes.

## Status vocabulary
PASS / FAIL / BLOCKED / NOT RUN / OWNER INPUT REQUIRED. Production: **NO-GO** until all gates evidenced on one exact ZIP.
