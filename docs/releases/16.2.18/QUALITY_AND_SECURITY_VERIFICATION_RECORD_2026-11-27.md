# Stockland ERP 16.2.18 — Quality and Security Verification Record

**Date:** 27 November 2026 (Africa/Addis_Ababa)
**Subject:** `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`, SHA-256 `aed7090c626029d38e34b0b84c5d06d1b2aa4b48d80786e3bd301bebc0e40f07` (128 files), merged commit `b29f79bceec6f58236c6122f6e8450ceda6a3026` on `delivery/stockland-full-erp-completion-2026-11-11` (PR #155 on the CI-verified head of PR #154).
**Class:** automated-check verification record. **This is not an independent auditor's certificate, a penetration test, a legal/statutory approval, or permission to deploy to production.**

## 1. Automated CI on the merged commit (GitHub Actions)
| Workflow / run | Result |
|---|---|
| Master installer WordPress verification (run 45912390848): MySQL 8.0 × PHP 8.3 and 8.4, MariaDB 10.11 × PHP 8.3 and 8.4, MariaDB 11.4 × PHP 8.4, Windows branch-folder agent contract, one-pack installer artifact publication | all success |
| Security and runtime gates (run 45912390800): PHP 8.3 and 8.4 | success |
| On the PR head (b200ec5): additionally "fail-closed security gates" and the two "Security gates" jobs | success |

## 2. Independent local execution (MySQL 8.0.46 disposable site, final bytes)
- Official `verify_master_installer.sh`: PASS; HR lifecycle tables stay unapproved/fail-closed.
- `e2e/run_all.sh`: 23 suites, **637 PASS / 0 FAIL** (payroll cycle 262, payroll release rules 16, HR library 29, POS integration hub 41/41, requisition approval chain, real multipart Daily POS upload → Branch Manager-only release → sale + ledger + balanced GL + replay guard).
- Browser: approval-chain buttons PASS across Branch Manager → Management → Warehouse Manager → Warehouse Employee → Branch receipt; `ui_smoke.js` axe WCAG 2.1 A/AA at 390/768/1366 px: 0 violations on fresh and populated sites.
- Product Master import: invalid SKU and negative price rejected; valid style + variants with barcodes stage, commit once, replay idempotent; duplicate barcode refused.
- GitBook read-only bridge (PR #150, not merged): 401 without token; read-only tools only; write-style tool names refused.

## 3. Static and runtime security checks (this record)
- PHP lint of all 86 plugin PHP files: 0 errors.
- Dangerous constructs (`eval`, `exec`, `system`, `shell_exec`, `passthru`, `proc_open`, `popen`, `create_function`, `unserialize`): 0 occurrences.
- Secret patterns (cloud keys, GitHub tokens, GitBook tokens, private keys) in the installer contents: 0.
- Unprepared `$wpdb` calls with variable interpolation (heuristic): 0.
- Runtime authorization sweep: all 103 `stockland/v16` routes, 127 route/method combinations called **without** authentication: 81 × 401, 46 × 403, 0 successful; 2 × 404 were placeholder-parameter mismatches, not open routes.

## 4. Defects found and fixed in this verification cycle
1. Product Master import preview failed for any workbook with a Barcode/GTIN (wrong `prepare()` argument order).
2. Product Master import commit failed for every job (closure variable `$value` overwritten).
3. Contrast below 4.5:1 for small dashboard copy and the notification badge once the site holds data.
CI had not caught these because its fixtures contain no barcode/commit path and an empty dashboard. Regression coverage for 1 and 2 should be added as a follow-up suite.

## 5. Not covered — do not infer a pass
Independent third-party code audit or penetration test; load/performance and disaster-recovery rehearsal; production-host parity, TLS, backup/restore on the live host; the untouched production POS workbook; Docker/devcontainer on the verifier's machine (covered by CI only); GitBook-side registration of the bridge and the GitBook Ask API from this sandbox (network refused); statutory payroll review — the shipped ET-2025-AMEND-1395 rules are the Owner's own declaration (PR #141) with no independent legal opinion recorded; SOP approvals; Owner schema-fingerprint approval for the HR lifecycle tables.

## 6. Conclusion
**Verified:** the named installer's byte identity, its CI results on the exact merged commit, the local MySQL 8 functional, approval-chain, POS, accessibility and authorization results above.
**Not certified:** unrestricted production readiness or go-live. A release decision remains with the Owner after the items in section 5.
