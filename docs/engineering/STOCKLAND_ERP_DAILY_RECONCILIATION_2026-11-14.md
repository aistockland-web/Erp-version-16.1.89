# Stockland ERP — Daily Repository Reconciliation — 14 November 2026

**Reporting date:** 14 November 2026, Africa/Addis_Ababa (UTC+03:00)  
**GitHub commit window used:** 13 November 2026 21:00:00 UTC through 14 November 2026 20:59:59 UTC. This UTC interval corresponds to the Addis Ababa calendar day.  
**Canonical repository:** `aistockland-web/Erp-version-16.1.89`  
**Canonical cumulative branch at reconciliation start:** `delivery/stockland-full-erp-completion-2026-11-11`  
**Exact branch HEAD at reconciliation start:** `47227362a6a9c7b502740870e1db61812b619b12`

This report records only changes committed/merged during the 14 November Addis Ababa day and tests run against the exact delivery head. It is not a retrospective restatement of older project work.

## 1. Work merged into the canonical delivery line today

| PR | Result | What entered the canonical line |
|---|---|---|
| [#112](https://github.com/aistockland-web/Erp-version-16.1.89/pull/112) | MERGED; merge commit `891706c201620c49d8f1d1441f731205c6f83ad7` | CAP-09/CAP-13 security and runtime gates: PHP lint/compatibility across PHP 8.3 and 8.4, secret scanning, Semgrep, WordPress security sniffs, and a planted-secret negative test. The merge timestamp is 23:50 UTC on 13 November, which is 02:50 on 14 November in Addis Ababa. |
| [#113](https://github.com/aistockland-web/Erp-version-16.1.89/pull/113) | MERGED; merge commit `d081ce8af156206cbd9401e873971b58e598ef73` | Table holders with search, up to three dropdown filters, row count and Clear for longer and dynamically rendered tables; Sapphire colour literals and inline-block fallbacks for older browsers; transfer notification outcome handling; deliberate, unprefilled HR schema approval prompt; source/manifest/installer provenance and CSS bundle gates. |
| [#115](https://github.com/aistockland-web/Erp-version-16.1.89/pull/115) | MERGED; merge commit `18161fc1392d121f9473f0c1f54236690919be7e` | Daily POS intake integrated into Branch Daily Sale: CNET Cash Sales Voucher / Summarized By Article source layout; article-code alignment; source-layout validation, staging and source-total reconciliation; separate manual workbook upload, Windows branch-PC folder pickup and disabled supplier-API placeholder; Sales Employee staging and Branch Manager review/edit/release; folder agent cannot commit; s12 contract tests and Windows agent tests. Also includes the 14-module CSS cascade, PostCSS semantic parity, minified asset consistency and Bootstrap 5.3.8 local asset provenance. |
| [#116](https://github.com/aistockland-web/Erp-version-16.1.89/pull/116) | MERGED; merge commit `47227362a6a9c7b502740870e1db61812b619b12` | Corrected release README, GO_LIVE runbook and CAP-03 wording: 49 operational baseline tables (29 Core/Operations, 16 Accounting/Procurement, four legacy HR/payroll); separate 37-table HR lifecycle extension remains Owner-approval-gated by exact schema fingerprint, backup confirmation and written reason; 86 is the combined total only after the separately approved extension. CAP-03 remains IN PROGRESS pending System Control/UI and full cross-file alignment. |

### Daily POS source-evidence boundary

The original `daily POs.xlsx` export binary was not available in the active attachment/Library set during the POS integration. CI used a generated fixture based on the recorded CNET Cash Sales Voucher / Summarized By Article layout. The available Product Master workbook contains all nine fixture article codes. This is **not** byte-for-byte verification of the original third-party export. Before the first production upload, a Branch Manager must preview and verify the untouched source workbook and reconcile its totals. The POS supplier API option remains a disabled/unconnected placeholder; no live provider connection is claimed.

## 2. Other 14 November source work included in the merged line

The canonical delivery history for the Addis Ababa date includes the POS article-summary staging/reconciliation and three-option intake commits; Windows folder-agent runner, installer, guide and contract tests; responsive Daily POS layout; CSS cascade/minifier correction and regeneration; PostCSS semantic parity tests; local Bootstrap 5.3.8 provenance; and installer manifest binding to the exact source checkout. These changes were integrated through PRs #113 and #115 and are covered by the final exact-head workflows below.

## 3. Exact final delivery-head verification

**Exact tested source:** `47227362a6a9c7b502740870e1db61812b619b12`.

| Workflow / gate | Result | Evidence |
|---|---|---|
| Security/runtime gates, PHP 8.3 and PHP 8.4 | PASS | [Run 43539097638](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43539097638) |
| WordPress master-installer verification and publication | PASS | [Run 43539096998](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43539096998) |
| Fresh MySQL 8.0.46 install with PHP 8.3 and 8.4 | PASS | Included in run 43539096998 |
| Fresh MariaDB 10.11 install with PHP 8.3 and 8.4 | PASS | Included in run 43539096998 |
| Business workflow regression, backup/restore checksum drill and browser/PWA smoke | PASS | Included in run 43539096998 |
| Windows branch-folder agent contract | PASS | Included in run 43539096998 |
| MariaDB 10.11 in-place upgrade from 16.2.13 | PASS | 86 bounded wizard batches; 86 tables / 160 rows before and 86 tables / 161 rows after (the additional row is the expected audit append); 52/52 deep integrity checks; Owner HR approval gate; interrupted/resume, missing-table retry and installer-lock drills passed. |
| MySQL 8.0.46 in-place upgrade from 16.2.13 | **BLOCKED — known historical baseline defect** | The unchanged 16.2.13 setup fails at batch 25 because nullable `stock_balances.variant_id` is part of a primary key. The workflow recognizes only this exact failure signature and reports the historical upgrade as BLOCKED; fresh 16.2.18 MySQL 8 installation is PASS. See [issue #76](https://github.com/aistockland-web/Erp-version-16.1.89/issues/76). |

The browser smoke includes authenticated ERP bootstrap, PWA manifest, responsive checks at 390px / 768px / 1366px with zero horizontal overflow, HR lifecycle visibility gated while pending, deliberate unprefilled HR approval prompt, Management Intelligence route separation, table-holder interaction and native WordPress Dashboard access.

## 4. Final-branch installer artifact

- **Artifact:** `stockland-erp-16.2.18-one-pack-installer`
- **Actions artifact ID:** `14090764366`
- **Workflow:** [43539096998](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43539096998)
- **Artifact wrapper SHA-256:** `e6ecbbc50ece62905c2ab402eef8f15281a51cf28208e61f35416652a7239573`
- **Inner ZIP:** `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`
- **Inner ZIP:** 2,249,589 bytes; 125 files
- **Inner ZIP SHA-256:** `6034d79ac95bc9fcf7f9b431cb2ef0da49eadf76c48e2ffbf792c2e4bb53cb4a`
- **Build/checksum:** builder PASS; `sha256sum -c` returned OK.
- **Expiry shown by GitHub:** 14 December 2026 (Actions artifact retention).

The downloadable artifact is a verified CI artifact for the exact delivery HEAD. It is not itself a permanent GitHub Release, signed release, production installation or deployment receipt.

## 5. PR/issue reconciliation — what is and is not part of today's delivery

- PRs #112, #113, #115 and #116 are merged into the canonical delivery branch.
- PRs #110 and #111 were closed without merge; the selected Daily POS implementation was integrated through #115. Do not count their unmerged branches as separately shipped.
- PR #114 was closed without merge to `main`; its selected delivery work was integrated through #115.
- PR #92 remains open against `main`, outside this canonical delivery branch. Do not treat its full diff as part of 16.2.18.
- PR #84 remains the open umbrella delivery PR from the canonical delivery branch to its stacked base. It is not a merge-to-main or production deployment.
- [Issue #17](https://github.com/aistockland-web/Erp-version-16.1.89/issues/17) remains OPEN: today's POS implementation is integrated, but remaining Management Intelligence campaign/content lifecycle and other stated scope still require evidence.
- [Issue #76](https://github.com/aistockland-web/Erp-version-16.1.89/issues/76) remains OPEN: the historical 16.2.13/MySQL 8 upgrade is still blocked, despite fresh MySQL 8 installation and the candidate's stock-identity migration tests passing.

## 6. Product-boundary decision recorded today

The current 16.2.18 runtime and this go-live runbook apply to the existing **single-company STOCKLAND ETHIOPIA deployment**. The intended commercial product is a customer-neutral, multi-tenant Apparel Supply Chain platform, but separate customer identity, tenant isolation/provisioning, sites, factories, warehouses, branches and users are **future capabilities, not implemented in 16.2.18**. The current Central Warehouse and Goro/Gerji/Mexico/Megenagna seeds are Stockland-specific defaults, not defaults for new customers. The release README, runbook and root engineering instructions are being clarified to prevent a false multi-tenant claim.

## 7. Release and production status at reconciliation

- GitHub Actions installer artifact: **PUBLISHED as an Actions artifact**.
- GitHub Releases endpoint: **no published release found** at reconciliation time.
- Git tags: **no release tag found** at reconciliation time.
- PR #84: **OPEN**, not merged to its base.
- Live WordPress/database: **not accessed or modified** by these tasks.
- Production deployment: **NOT PERFORMED**.
- No production stock rows, customer/supplier/employee data or payroll records were used in QA.

## 8. Remaining acceptance work — not silently closed by today's green CI

1. Resolve or provide an approved, data-preserving path for the historical 16.2.13 → 16.2.18 MySQL 8 upgrade, preserving nullable legacy semantics, immutable variant identities, quantities, row lineage and rollback.
2. Complete CAP-03 System Control/UI and remaining documentation/constants cross-file audit.
3. Complete full HR lifecycle and two synthetic payroll periods only after qualified Ethiopian statutory-rule review and separate Owner approval; 16.2.18 CI does not certify statutory calculations.
4. Complete the remaining Management Intelligence campaign/content lifecycle in issue #17.
5. Complete any outstanding role-by-role denial, print, device, load/concurrency and host-specific compatibility acceptance not covered by the named CI checks.
6. Obtain a separate release/deployment decision before publishing a permanent GitHub Release/tag or touching the live WordPress/database.

This report is a factual record of today's repository work, not a declaration that every project-wide acceptance item or the production GO_LIVE decision is complete.
