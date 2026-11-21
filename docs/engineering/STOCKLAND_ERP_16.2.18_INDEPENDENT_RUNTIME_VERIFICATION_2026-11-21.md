# Stockland ERP 16.2.18 — independent runtime verification (21 Nov 2026)

**Artifact under test:** `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` committed on branch `audit/full-2026-11-20` at `4fb3f88`.
**SHA-256:** `96f41b48624c59367a319eba20035a5df68adc680358b1196b97899332409ff0` (matches the committed `.sha256` sidecar).
**CI for the same commit (run 44882322999):** all jobs success: MariaDB 10.11 PHP 8.3/8.4, MariaDB 11.4 PHP 8.4, MySQL 8 PHP 8.3/8.4, Windows agent tests, one-pack artifact publish.
**Decision:** this is not a production release. Owner/HR/Finance/legal sign-off and explicit release authorization remain required, and no tag or GitHub Release was created.

## Environment
Disposable WordPress 7.1.3, PHP 8.3, MySQL 8.0.46 (separate datadir), synthetic data only, loopback only.

## Results (PASS = executed against the exact ZIP above)
| Check | Result |
|---|---|
| ZIP checksum vs sidecar | PASS |
| 127/127 manifest file hashes; 0 PHP files without opening tag; 0 lint failures | PASS |
| Fresh install on MySQL 8.0.46, clean activation (no stray output) | PASS |
| Setup stops at 49-table operational baseline; HR lifecycle gated on Owner approval + written reason | PASS (as designed) |
| After Owner approval of HR schema: 86 tables, state LIVE | PASS |
| Deep integrity scan after business flows | PASS 52/52, failed=0 |
| Supplier/PO: Management prepares, a different Owner approves; Management self-approval denied | PASS |
| Goods receipt: only Warehouse Manager; Warehouse Employee and Management denied; value 30,000.00; GL journal posted; replay of same request key idempotent | PASS |
| Transfer Central→Gerji: Approved, Reserved, Picked, In Transit, Received; Warehouse Employee denied as receiver; Branch Manager receives | PASS |
| POS sale 2 × 600 VAT-included: total 1,200.00, VAT 156.52; stock 28; oversell of 500 refused (409); sale at a non-authorized branch denied (403) | PASS |
| Customer return (reason mandatory): stock 29; balances 70 (warehouse) and 29 (Gerji) | PASS |
| Two deactivate/reactivate cycles: data and HR schema marker preserved, integrity 52/52 | PASS |
| REST authorization sweep, 186 route-methods: unauthenticated and subscriber denied except the intentional self-identity bootstrap route | PASS |

## Findings against earlier candidates (for the record)
Runtime installation of earlier candidates found: (a) MariaDB-only `@@session.in_transaction` breaking every ledger posting on MySQL 8; (b) a schema-contract file missing `<?php`; (c) HR marker, XLSX gate and reactivation diagnostics issues; (d) a 2M PHP upload limit failing the `xlsx_upload_capacity` host gate. (a), (b) and (c) are already corrected in 16.2.18. (d) is a host requirement: set `upload_max_filesize` and `post_max_size` above the plugin XLSX limit.

## Still NOT RUN here (CI or owner evidence needed)
Daily-close endpoint walk-through, wholesale, requisition flow, finance AP payment and PLEDGER reconciliation beyond the integrity checks, full HR lifecycle and two-period payroll, payment/remittance, encryption key rotation, PHPCS/Semgrep/CodeQL here, browser/PWA/print here, load testing, exact live-host file parity.
## OWNER INPUT REQUIRED
Schema-contract approval, release approval and live-ready authorization (gates intentionally closed); qualified Ethiopian payroll/tax/pension rule approval; HR/Finance/Operations acceptance; written release authorization.
## Known boundary
Issue #76: the original 16.2.13 ZIP cannot complete setup on MySQL 8 (nullable column in a primary key). It is not edited. Use MariaDB or the 16.2.18 fresh-install path.
