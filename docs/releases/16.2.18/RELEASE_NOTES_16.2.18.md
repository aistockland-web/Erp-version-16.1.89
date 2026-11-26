# Stockland ERP 16.2.18 — verified installer (pre-release)

Version series: 16.1.95 → 16.2.0 … 16.2.18 only. Built from the CI-verified delivery head (PR #154, which contains the Integration Hub, controlled import channels and the Windows branch-folder agent) plus PR #155; docs in PRs #156 and this one.

**Installer:** `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` — SHA-256 `aed7090c626029d38e34b0b84c5d06d1b2aa4b48d80786e3bd301bebc0e40f07` (128 files). Install and activate as the WordPress administrator (becomes bootstrap Owner); back up first.

## Included components
1. **Master WordPress installer** — 49-table operational baseline; the 37 HR lifecycle tables only after the Owner's written approval.
2. **Special import jobs (Daily POS and Product Master)**
   - Three Daily POS intake options in one screen: manual upload, chunked resumable import, and the scheduled branch folder pickup; supplier-API option stays disabled until the POS supplier provides official API terms.
   - Exact CNET Cash Sales Voucher "summarized by article" adapter plus mapped CSV/XLSX; embedded business date, duplicate-file detection (same bytes return the existing job), staging then READY/REVIEW.
   - Release is a separate, explicit **Branch Manager-only** action (Sales Employee and Management cannot release); posting creates the sale, stock-ledger movement and a balanced GL journal; replay is idempotent.
   - Product Master workbook import (template in the plugin): staged, committed once, idempotent, duplicate barcodes refused.
3. **POS device connector (Integration Hub)** — register POS sources per branch (unique source-system namespace per device, inline API credentials rejected, per-device PLU mapping), connect a device only through a matching Branch Manager release, branch-scope enforcement.
4. **Windows branch-folder agent** (`STOCKLAND_POS_FOLDER_AGENT_WINDOWS_16.2.18.zip`, SHA-256 `b2dbeef3d4c96e423c77b748392b77bd50d49915522da1d75089068e638e841e`) — PowerShell 7.4+, HTTPS only, dedicated single-branch Sales Employee Application Password (DPAPI-protected), uploads to the preview route only and can never release/post.
5. **Optional GitBook read-only bridge 1.0.0** (separate plugin, PR #150 unmerged): bearer-token, four read-only tools.

## What this release fixes over the previous verified head
- Product Master import preview failed for any workbook with a Barcode/GTIN.
- Product Master import commit failed for every job.
- WCAG AA contrast for small dashboard copy and the notification badge once data exists.

## Verification
CI on merged commit b29f79b: MySQL 8 and MariaDB lanes (PHP 8.3/8.4), security gates, Windows agent contract tests, installer publication — all success. Local MySQL 8: official verifier PASS; 23 suites 637/0 including s12 Daily POS contract, s19 POS Integration Hub (41/41 device and release-authority checks), s22 real multipart POS upload → Branch Manager release → sale/ledger/GL → replay; approval-chain browser buttons PASS; axe 0 violations; 127 route/method combinations refused without authentication. Record: `docs/releases/16.2.18/QUALITY_AND_SECURITY_VERIFICATION_RECORD_2026-11-27.md`.
Not executed here: the PowerShell agent itself (no PowerShell in the verifier sandbox; covered by the CI Windows job) and the GitBook Ask API.

## Boundaries — why this is a pre-release
Not an independent audit or penetration test, and not production go-live authorization. Open Owner decisions: HR lifecycle schema approval, statutory payroll review (shipped ET-2025-AMEND-1395 rules are the Owner's own declaration; no independent opinion recorded), SOP approvals, live-host parity/backup acceptance, the untouched production POS workbook. Switch the pre-release flag off once the Owner closes these gates.
