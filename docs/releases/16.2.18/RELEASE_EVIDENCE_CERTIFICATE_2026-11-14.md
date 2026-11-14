# Stockland Ethiopia ERP 16.2.18 — Release Evidence Certificate

**Record date:** 14 November 2026 (Africa/Addis_Ababa)  
**Certification class:** CI build and installer provenance attestation  
**Status:** **ARTIFACT VERIFIED — PRODUCTION GO-LIVE NOT CERTIFIED**  
**Product scope:** Existing single-company STOCKLAND ETHIOPIA deployment only

> This document certifies the exact installer artifact and automated checks identified below. It is not a digital signature, independent auditor's certificate, legal/statutory approval, production-host acceptance, or permission to deploy. A green CI workflow does not override an explicitly blocked test path or outstanding business-owner acceptance.

## 1. Canonical source and change identity

- Repository: `aistockland-web/Erp-version-16.1.89`
- Canonical delivery branch: `delivery/stockland-full-erp-completion-2026-11-11`
- Exact delivery commit: `387c193c7999840c731d37acd854ec920f134507`
- Integrated change: [PR #121](https://github.com/aistockland-web/Erp-version-16.1.89/pull/121), merged to the delivery branch.
- PR #121 adds validated dashboard period and authorized-location filters, the 13-assertion `s13_dashboard_filters` regression, and an HR & Payroll layout guard. The source change was included in the exact-head artifact below.
- Umbrella PR #84 remains open and non-mergeable against its stacked base. It has not been merged to `main`.

This is a 16.2.18 update on the existing cumulative delivery source. It is not a new product/version line or a return to an older plugin package.

## 2. Exact installer artifact

- Actions workflow: [Run 43570216177 — master-installer WordPress verification](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177)
- Run event: push on the canonical delivery branch at exact commit `387c193c7999840c731d37acd854ec920f134507`
- Artifact name: `stockland-erp-16.2.18-one-pack-installer`
- Artifact ID: `14104031868`
- [Download the existing Actions artifact](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177/artifacts/14104031868)
- Inner installer filename: `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`
- Inner ZIP size: **2,251,794 bytes**
- Inner ZIP entries: **125**
- Inner ZIP SHA-256: `de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08`
- Actions artifact archive size: **2,246,109 bytes**
- Actions artifact archive SHA-256: `ff58626d39c63980eba7f3de5cf8e2ee7c5788952e746731791fdd20b434d133`
- Builder output and `sha256sum -c`: **PASS** (the inner ZIP sidecar matched the inner ZIP bytes).
- Artifact retention expiry shown by GitHub: **14 December 2026, 07:17:33 UTC**.

The two SHA-256 values identify different objects: the inner plugin installer ZIP and the enclosing GitHub Actions artifact ZIP. Do not substitute one for the other. The Actions artifact is temporary; it is not a permanent GitHub Release asset.

## 3. Exact-head automated verification

### Security and runtime

[Run 43570216138 — security and runtime gates](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216138), on exact commit `387c193c7999840c731d37acd854ec920f134507`:

- PHP 8.3 security/runtime job: **PASS**
- PHP 8.4 security/runtime job: **PASS**

### WordPress installer and database matrix

[Run 43570216177](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177), exact post-merge delivery commit:

| Job | Result |
|---|---|
| Fresh WordPress + MySQL 8.0.46 + PHP 8.3 | PASS |
| Fresh WordPress + MySQL 8.0.46 + PHP 8.4 | PASS |
| Fresh WordPress + MariaDB 10.11 + PHP 8.3 | PASS |
| Fresh WordPress + MariaDB 10.11 + PHP 8.4 | PASS |
| Windows branch-folder POS agent contract | PASS |
| One-pack installer publication and checksum | PASS |

Additional observed acceptance in the workflow logs:

- 61-table backup/restore comparison: zero checksum or row-count mismatches.
- Authenticated WordPress administrator login, Stockland launcher, Owner System Control, standalone ERP shell and PWA manifest: PASS.
- Phone (390 px), tablet (768 px) and desktop (1366 px) smoke: PASS with zero horizontal overflow on checked routes.
- Dashboard filter browser interaction and the `s13_dashboard_filters` regression: PASS.
- Management Intelligence route separation, table-holder search/filter behavior, HR schema approval prompt and HR & Payroll layout guard: PASS.
- The existing stock-balance immutable-variant identity migration regression remains in the dual-engine suite.

These are disposable CI environments with synthetic test fixtures. They are not tests against Stockland's live WordPress installation or production database.

## 4. Upgrade compatibility boundary — retain the exact result

| Upgrade/install path | Recorded result |
|---|---|
| Fresh 16.2.18 install on MySQL 8.0.46 | **PASS** |
| Fresh 16.2.18 install on MariaDB 10.11 | **PASS** |
| 16.2.13 → 16.2.18 in-place upgrade on MariaDB 10.11 | **PASS** |
| 16.2.13 → 16.2.18 historical path on MySQL 8.0.46 | **BLOCKED — not a pass** |

MariaDB upgrade evidence: 86 bounded setup batches completed; the synthetic snapshot recorded 86 tables / 160 rows before and 86 tables / 161 rows after (the additional row is the recorded audit append); no tables were added by the upgrade; the 52/52 deep-integrity scan and interrupted/resume, missing-table retry and installer-lock drills passed.

The MySQL 8 historical path is still blocked at baseline wizard batch 25: the unchanged 16.2.13 source declares nullable `stock_balances.variant_id` inside a primary key, which MySQL rejects with “All parts of a PRIMARY KEY must be NOT NULL.” The workflow recognizes that exact historical failure signature and labels this path **BLOCKED**. It does not report a successful 16.2.13-to-16.2.18 MySQL 8 upgrade.

[Issue #76](https://github.com/aistockland-web/Erp-version-16.1.89/issues/76) was closed through the PR #121 delivery update with this compatibility boundary documented. Closure does not make the historical MySQL 8 path pass. The tested alternatives are a fresh 16.2.18 MySQL 8 install or a 16.2.13-to-16.2.18 in-place upgrade on MariaDB 10.11.

## 5. Product, schema and operational acceptance boundaries

- The application scope is the existing single-company **STOCKLAND ETHIOPIA** deployment. Multi-tenant customer provisioning/isolation is not implemented in 16.2.18.
- The 49-key operational baseline (29 Core/Operations, 16 Accounting/Procurement and four legacy HR/payroll keys) is separate from the 37-table HR lifecycle extension.
- HR lifecycle DDL and existing legacy-HR changes remain subject to the Owner's exact schema-fingerprint, backup and written-reason approval. Passing fresh-install CI is not that approval.
- The 24 SOP payloads have integrity checks, but their recorded approval state is `DRAFT_NOT_APPROVED`.
- The original `daily POs.xlsx` source workbook was not available for byte-for-byte acceptance. Daily POS CI used a generated fixture based on the recorded CNET Cash Sales Voucher / Summarized By Article layout. Before the first live import, the Branch Manager must preview the untouched original workbook and reconcile source totals.
- The supplier API intake option remains disabled/unconnected. No provider API or automatic posting is certified.
- Issue [#17](https://github.com/aistockland-web/Erp-version-16.1.89/issues/17) remains open for the wider corrective-action scope, including remaining Management Intelligence lifecycle and operational acceptance work.
- No independent submitted GitHub review is recorded on PR #121; the automated Codex review attempt did not complete because the review usage limit was reached. The green Actions results are not a substitute for independent maintainer/business-owner review.
- Ethiopian statutory payroll/tax/pension rules require qualified, effective-dated approval. This CI record does not provide legal or payroll sign-off.

### Manifest provenance exception

The source `src/stockland-erp-app/RELEASE_MANIFEST.json` still contains `canonical_source_commit = 2b4bcca9bb8205e69224e0886f869a4069424f53` and `github_release_id = null`, rather than the current delivery commit and a release ID. The workflow itself is tied to commit `387c193c7999840c731d37acd854ec920f134507` and the artifact checksum is verified, but the embedded manifest's source-commit field is stale relative to this exact delivery build. This exception is recorded rather than silently edited: changing the embedded manifest would change installer bytes and require another package build, which is outside this record.

## 6. Release object, host acceptance and deployment

At the time this record was prepared:

- GitHub Releases API returned no published Release object.
- Tag `16.2.18` was not found.
- The verified installer exists as the temporary Actions artifact listed in Section 2.
- No authenticated live WordPress host or production database was accessed.
- No production deployment, production migration, live backup/restore, or production parity receipt was performed.
- No live operational sign-off from Stockland Operations, Finance, HR or the qualified payroll/statutory reviewer is evidenced here.

## 7. Certification conclusion

**Certified:** the named 16.2.18 installer ZIP's byte identity and its recorded exact-commit automated CI results, within the environment and compatibility boundaries above.

**Not certified:** unrestricted production readiness, all historical database upgrade paths, statutory payroll correctness, approval of all SOPs/HR lifecycle work, the untouched production POS workbook, deployed-host parity, or a production go-live.

Do not describe this record as a signed certificate or as authorization to install/update the live system. A separate release decision requires the outstanding Issue #17 acceptance, the manifest provenance exception decision, independent review and operational/finance/HR/legal sign-offs, plus an explicit production release/deployment authorization.

## 8. Change-control statement for this record

This certificate is documentation-only. It does not edit the plugin PHP, JavaScript, CSS, SQL, assets or embedded release manifest; does not rebuild or repackage the installer; does not create a tag or GitHub Release; does not merge to `main`; and does not deploy or modify a live site. The exact installer to which it refers is the already-published artifact in Section 2.
