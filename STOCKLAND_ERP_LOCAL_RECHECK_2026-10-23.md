# Stockland Ethiopia ERP — Final Local Recheck and Artifact Provenance

**Date:** 23 October 2026 (Africa/Addis_Ababa)  
**Current source branch:** feat/reports-explorer-16-1-93, head `06511a1e28380d13c6fc3b096ab171bbac511f18`  
**Live version reported by Owner:** Plugin 16.1.94 / DB 16.1.1  
**Decision:** **NO-GO for installation/certification until the exact current head produces a coherent final package and the live backend/data gates pass.**

## 1. Strongest reproducible canonical baseline from Library/GitHub

The latest locked canonical installer in the accessible repository is **16.1.92**, SHA-256 `adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd`, 1,113,446 bytes, 75 files. The PHP 8.3 and PHP 8.4 GitHub Actions artifacts contain this same inner installer checksum. The saved 16.1.92 QA receipt reports the 49-table contract (29 Core/Operations + 16 Accounting/Procurement + 4 HR/Payroll) and no live deployment.

Local recheck of those exact 16.1.92 baseline bytes:
- ZIP CRC/path/root and 75-file inventory: PASS.
- PHP syntax: 48/48 files.
- JavaScript syntax: assets/app.js and assets/pwa.js.
- RELEASE_MANIFEST.json, PWA manifest and CSS module manifest parse.
- Six ordered CSS modules rebuild assets/app.css exactly; deterministic normalized output matches assets/app.min.css.
- The saved exact-package PHPCS result reports 0 errors / 0 warnings. The saved Semgrep 1.139.0 result contains 0 findings and 0 errors. Those are historic 16.1.92 source results, not new scans of the current 16.1.93 builder output.
- Live install, real database reconciliation, backup/restore and rollout were not run.

**Version boundary:** 16.1.92 is the best reproducible baseline to rebuild from, but it is older than the supplied live 16.1.94 identity. It must not be installed over live 16.1.94 because later changes may be lost.

## 2. Actual downloadable 16.1.93 GitHub Actions artifact inspected

Actions run [39471331354 — Reports Explorer 16.1.93 exact-package gates](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/39471331354) used source commit `1f96da520602b992bec449ebf2ab45b9469f107e` from 20 October, not the current remediation head.

| Artifact evidence | SHA-256 | Bytes | Interpretation |
|---|---|---:|---|
| Outer Actions artifact ZIP | `42e638b77db9edc1b6179962051ff42b848c5f4d4ebd50d10087c8cd6fc37140` | 1,115,505 | Contains installer plus QA receipts |
| Inner Reports Explorer plugin ZIP | `a8cb3767f363740387731928211febc315876a7b8c146e65e594930940e64e3b` | 1,118,326 | Actual plugin ZIP in that artifact |
| Inner ZIP .sha256 sidecar | `a8cb3767f363740387731928211febc315876a7b8c146e65e594930940e64e3b` | 1,118,326 | Matches the inner ZIP bytes |
| BUILD_RECEIPT.json / LOCAL_QA_RESULTS.txt | `1cfab0a8221802aa2a5633e92d65ff76f146ea6152aae7068e6d82b384f2dceb` | 1,117,980 | Intermediate Reports Explorer ZIP before the stock patch |
| STOCK_RECONCILIATION_RECEIPT.json | `a8cb3767f363740387731928211febc315876a7b8c146e65e594930940e64e3b` | 1,118,326 | Correctly describes the later stock-patched ZIP |

The inner ZIP and its sidecar agree. The defect is that the overall build/QA receipt points at an intermediate artifact, while the downloadable package is a later modified artifact. It is not a single final-package certification tied to the final bytes.

Local inspection of the actual inner ZIP also confirms it predates the latest cumulative source patches: the admin panel still contains “Server environment” and “plugin requires 8.4+”; the XLSX reader contains “InfinityFree currently provides”; the payroll UI/API contain fixed `justice.gov.et` URLs; the manifest retains build/candidate metadata; report-source fail-closed guards are absent; Management Intelligence and responsive approval-history styles are absent; and the report retry path still creates a duplicate toast. Its 75-file inventory, PHP syntax, JS syntax and original CSS parity pass, but those passes do not make it current.

## 3. Current source changes added after discovering the stale artifact

Current GitHub head is `06511a1e28380d13c6fc3b096ab171bbac511f18`. The source branch now includes:
- a final-package attestation generated after all source patches;
- `ci/qa/verify_final_package.py`, which requires final ZIP SHA/size to equal both sidecar and build receipt, validates the same 75-file inventory and allowed changed-file set, verifies schema activator preservation, version/schema markers, report/dashboard error guards, Management Intelligence gating, CSS module/build/minified parity and hosting/domain cleanup;
- regression tests that run the final-package verifier and prove that an intentionally stale intermediate hash is rejected;
- a workflow step to publish the final package receipt with the ZIP and checksum.

This is a source-side corrective control. **It has not yet been run by GitHub Actions on the current head.** The most recent matching Actions runs are still from 20 October at commit `1f96da520602b992bec449ebf2ab45b9469f107e`; I found no run/status for current head `06511a1e28380d13c6fc3b096ab171bbac511f18`.

## 4. Reconstructed local UI fixture — separate from the actual Actions ZIP

An earlier local Playwright test ran on a reconstructed review-only fixture with SHA-256 `0d2632ad75d6df81c5047b0937b9b551d7b9ee7d03104c8c91458682116e5883`. That fixture is not the actual inner Actions ZIP identified in section 2 and is not an installer.

The recorded synthetic-browser results covered 175 view/viewport combinations (25 work areas × 7 widths), six synthetic role profiles, Management Intelligence visibility, requisition approval/supply-table geometry at seven widths, and report failure → one inline error → retry success. No browser/console errors or page overflow were recorded in that test. These results only apply to the reconstructed test fixture with intercepted/mock REST responses; they do not validate the actual inner Actions ZIP, the latest source head, live SQL, or server-side authorization.

## 5. Live and end-to-end blockers

The supplied live System Health reports nine active employees with missing current compensation. No salary/classification was filled automatically. The following remain open:
1. Exact current-head CI and a single final ZIP/build receipt whose hashes all match.
2. Exact installed 16.1.94 package/source parity; the current repository base is 16.1.92 and does not expose a genuine 16.1.94 installer.
3. Owner/HR correction or documented lawful exclusion of the nine compensation exceptions, plus effective-dated Ethiopian payroll rule matrix and independent local adviser approval.
4. Complete HR workflow from workforce planning through final settlement.
5. One completed, read-only deep integrity receipt with run ID/timestamp and explicit PASS/FAIL/UNKNOWN/NOT_RUN per required check.
6. Actual live report query failure diagnosis, not just UI error handling.
7. Actual live Cart/POS markup and handlers; the accessible historical candidate does not establish that the 16.1.94 cart is fixed.
8. Real database tests of stock/transfer/reservation, AP/AR/PLEDGER/GL, role/location authorization, import idempotency, audit-chain rollback, and verified backup/restore/rollback.

## 6. Release disposition

**Do not upload the historical 16.1.93 Actions artifact or the reconstructed fixture over live 16.1.94.** The 16.1.92 package is the strongest reproducible canonical baseline currently available from Library/GitHub, but not a safe direct replacement for .94. The builder now has the right fail-closed package-integrity gate; exact-head CI and the separate live-data/operational gates still must pass before a canonical update can be called production-ready.
