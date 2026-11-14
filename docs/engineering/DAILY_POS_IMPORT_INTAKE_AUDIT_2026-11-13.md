# Stockland Daily POS Import — implementation and acceptance record

**Date:** 14 November 2026  
**Integrated candidate branch:** `delivery/stockland-pos-reconciled-2026-11-14`  
**Canonical integration checkpoint:** `d081ce8af156206cbd9401e873971b58e598ef73` on `delivery/stockland-full-erp-completion-2026-11-11`; POS integration is selectively applied to that source on the feature branch.  
**Release protection:** no production database, live installation or release tag was changed. Changes are confined to the candidate feature branch; the existing certified base installer was not overwritten.

## 1. Scope and SOP boundary

The existing ERP Daily POS importer is extended in place. No second importer, duplicate sales ledger, schema migration, or separate management-intelligence workflow is introduced.

- Sales Employee: upload and stage source evidence for the assigned branch only.
- Branch Manager: review, correct eligible staged rows, reconcile, and explicitly release.
- Management/Owner: retains the existing product-code and configuration responsibilities.
- Management Intelligence remains read-only. Daily POS is located in Sales & POS → Daily POS.
- Product Master imports remain a separate screen and job type. A Product Master import must not create sales or change stock quantities.

## 2. Target POS source format recorded in the preparation specification

The preparation record describes `daily POs.xlsx` as a **Cash Sales Voucher / Summarized By Article** report, not a receipt-level transaction export. The original `daily POs.xlsx` binary was not available in the active attachment or Library set during this integration, so the column map below is treated as a recorded source specification and generated-fixture contract; it is not claimed as a fresh byte-for-byte inspection of that workbook.

| Source element | Actual source layout | Import contract |
|---|---|---|
| Report date | J3: Saturday, November 7, 2026 | Detect the business date from the original workbook; reject a conflicting manual date |
| Report identity | “Cash Sales Voucher” and “Summarized By Article” | Recognize this exact summarized-report profile |
| Article code header | A8:B8 is merged; visible heading is Article Code | For this positively identified profile only, read each actual article code from column B |
| Article description | Column C, Article Name | Source evidence only; SKU resolution uses Article Code |
| Quantity | Column E, Quantity | Must be numeric and positive |
| Average selling amount | Column I, “Avg.  Amount” | Map to Daily POS selling price |
| Article line total | Column L, “Total  Amount” | Validate against quantity × average selling price with a one-cent-per-line rounding allowance |
| Control totals | Column K, Sub Total, Add. Charge, Discount, Total Tax, Grand Total | Retain the original precision and reconcile against ERP net, VAT and gross before release |

The header alignment is conditional on the source markers, merged Article Code heading, blank A data cell, SKU in B, description in C and numeric quantity in E. It does not shift columns in generic POS exports or Product Master workbooks.

## 3. Product Master code cross-check and generated XLSX fixture

The nine Article Code values used by the deterministic E2E fixture were checked against the available Stockland Product Master upload workbook and all nine codes were found there. Because the original `daily POs.xlsx` binary was unavailable in this integration workspace, this confirms the fixture-to-Product-Master mapping only; it does not prove that those are the complete rows or totals in the original POS export. The live ERP still checks that each corresponding Product Master SKU is active and has a valid cost basis before posting.

| Measure | Recorded source specification used by the fixture | ERP rule calculation | Difference |
|---|---:|---:|---:|
| Article rows | 9 | 9 | 0 |
| Sold units | 13 | 13 | 0 |
| Net/subtotal | ETB 14,956.607 | ETB 14,956.64 | ETB 0.033 |
| VAT | ETB 2,243.480 | ETB 2,243.51 | ETB 0.030 |
| Gross/grand total | ETB 17,200.087 | ETB 17,200.15 | ETB 0.063 |

The displayed three-decimal values in the recorded fixture specification and ERP's cents-based line rounding are not silently rewritten. The generated fixture validates reconciliation within at most ETB 0.01 per article row (ETB 0.09 for this nine-row report). A discrepancy outside that bound or a missing source control total moves the job to REVIEW and blocks release.

## 4. One screen, three intake methods

1. **Original Excel/CSV upload:** the branch selects the file exactly as exported. The ERP detects this report profile, branch scope, date, SKU, quantity, price, source totals and duplicate hash. Preview creates a staged job only.
2. **Automatic folder pickup:** an optional Windows PowerShell 7.4+ agent watches the branch PC's Incoming folder and submits the unchanged file over HTTPS to the same Daily POS preview/staging endpoint. It uses a dedicated Sales Employee account scoped to one branch, DPAPI-protected application-password storage, a five-second file-write settling period, retry retention on transient errors and separate Submitted / NeedsReview / NeedsAttention folders. The Windows task requires the branch PC user to be signed in and the PC online. The agent has no release call.
3. **Future supplier POS API:** displayed as NOT CONNECTED / AWAITING VENDOR. It is not represented as live. Implementation requires the supplier's official API documentation, authentication method, test credentials, supported date/branch semantics, paging or cursor rules, stable transaction IDs, correction/void semantics, retry/idempotency contract and owner approval. When those exist, the adapter must feed the same source validation and staging contract; it must not write directly to sales or the ledger.

## 5. Posting and replay controls

- Original file SHA-256 and branch/date form the import idempotency key. Re-uploading the same file opens its existing job instead of producing a duplicate.
- Reusing the same source hash against another branch/date is rejected.
- The report date is checked against the selected date and business calendar; closed days remain blocked.
- Summary exports contain no receipt numbers. The importer does not fabricate row receipts. It groups the summary rows under one internal, receipt-free daily batch; `external_receipt_no` remains NULL and `receipt_count` is zero.
- Only one Daily POS import may begin posting for a given branch/date. A second changed export is blocked once another job for that branch/date is PARTIAL or POSTED.
- The established day lock, locked job re-read, bounded posting chunks, immutable sale rows, stock ledger, finance posting and audit trail remain the commit path.
- Product Master stays independent.

## 6. Verification added

- `scripts/release/e2e/s12_daily_pos_import_contract.php`: creates a disposable XLSX fixture matching the supplied report layout and exercises the actual streaming parser, merged heading handling, date extraction, field mapping, source-total capture, VAT calculation, rounding and mismatch-to-REVIEW rules. It is added to the existing disposable WordPress E2E sequence.
- `scripts/release/tests/daily_pos_ui_contract.js`: checks the three blocks, role/capability gate, common preview endpoint, reconciliation UI, Manager-only release status, Product Master separation and folder-agent no-commit contract.
- `tools/stockland-pos-folder-agent/tests/Test-StocklandPosFolderAgent.ps1`: Windows contract tests for settling, normal staging, REVIEW routing, transient retry, permanent authorization error, no branch/date override and no sales release.
- `.github/workflows/master-installer-wordpress-verify.yml`: adds the Node contract to the Linux MariaDB/MySQL acceptance job and runs the folder-agent tests on Windows. The installer artifact job waits for both verification jobs.

The tests are now included in the reconciled candidate branch; the exact-head GitHub Actions results are the release gate and are not represented as passing until observed. A green syntax/static contract alone is not proof of live branch operation or real supplier API integration.

## 7. Explicitly unverified items

- No production Stockland site, branch workstation, or production database was accessed or changed.
- No production System Health screen or database deep-integrity scan was run during this feature work. The existing CI health/deep-integrity checks run in disposable environments and must not be described as live production certification.
- The historical ~315 MB memory figure was not found as a reproducible benchmark in the inspected source and Notion records. The importer retains the established streaming XLSX reader and row safety cap, but peak memory under a large real branch workbook must be measured in the target PHP/hosting environment before making a memory-optimization claim.
- The folder agent and UI tests are synthetic contract tests until GitHub CI completes and a branch owner installs it on one controlled branch PC.
- Supplier API remains unavailable until the supplier supplies the required interface.

**Source evidence limitation:** before using this profile for the first production branch upload, a Branch Manager must upload the untouched original POS export through the normal preview path and confirm the detected date, rows, quantities and totals against the source report. The importer does not post automatically; unresolved or out-of-tolerance totals remain `REVIEW`.

## 8. Acceptance gate

Do not merge, tag, rebuild/publish the certified release, or deploy until:
1. GitHub's Linux MariaDB and MySQL acceptance jobs pass;
2. the Windows folder-agent contract passes;
3. the draft PR is reviewed for source diff and generated installer integrity;
4. a controlled branch-owner UAT verifies upload → staged review → manager release → sales/stock/ledger/audit reconciliation on a disposable or approved pilot branch;
5. production System Health is separately verified against the live database without weakening existing health checks.
