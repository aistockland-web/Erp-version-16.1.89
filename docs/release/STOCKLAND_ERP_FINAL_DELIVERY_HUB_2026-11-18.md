# Stockland Ethiopia ERP — Final Delivery Hub
**Evidence date:** 18 November 2026  
**Repository:** [aistockland-web/Erp-version-16.1.89](https://github.com/aistockland-web/Erp-version-16.1.89)  
**Canonical cumulative source:** `delivery/stockland-full-erp-completion-2026-11-11`  
**Latest inspected delivery commit:** `4965aef44d56fc7f235544227ab0b79874d27105`  
**Status:** consolidated engineering handoff; NOT a new production release or deployment approval.

## 1. Which installer is which

### Protected production baseline — keep unchanged
- Application: Stockland ERP 16.2.18
- Source commit: `387c193c7999840c731d37acd854ec920f134507`
- GitHub Actions run: [43570216177](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177)
- Artifact ID: `14104031868`
- Inner installer SHA-256: `de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08`
- This package remains the certified rollback/production baseline. Do not overwrite it, rebuild it, or reuse its version label for changed code.

### Latest cumulative delivery candidate — test/review only
- Source commit: `4965aef44d56fc7f235544227ab0b79874d27105` (merged PR #142)
- Installer verification: [run 44035644180](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44035644180) — SUCCESS
- Security/runtime: [run 44035644136](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44035644136) — SUCCESS
- Workflow artifact ID: `14307272333`, `stockland-erp-16.2.18-one-pack-installer`
- Outer one-pack artifact SHA-256: `11307e165c06f93e1190b39c6152c190ede2042bcac3bc75733451c4c330ace2`
- Inner installer SHA-256: `8fad9ce6758d8bedadae7b245cbd1af3bafdc7e7b8b250bf7f805c9ee93b32a4`
- Inner ZIP: 128 entries; ZIP CRC validation passes; all 127 manifest-inventoried files match the embedded manifest's sizes and hashes.
- The candidate still declares application version **16.2.18** and its manifest says `github_release_id: null`. It is not a separately versioned production release and must not replace the protected installer.

**Packaging evidence discrepancy:** the outer one-pack currently bundles an evidence Markdown file whose embedded build date/commit and artifact details describe an earlier package, not commit `4965aef`. The inner manifest also retains `created_at: 2026-11-11`. Use the hashes and CI run recorded in this hub as the latest inspected provenance; correct the generated evidence/manifest metadata in the next versioned build.

## 2. Exact latest workflow results

| Gate | Run / result | What it proves |
|---|---|---|
| Installer + WordPress matrix | [44035644180 — PASS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44035644180) | Windows branch-folder agent contract tests; disposable WordPress with MariaDB/PHP 8.3; MySQL 8/PHP 8.3 and 8.4; MariaDB/PHP 8.4; MariaDB 11.4.13/PHP 8.4.27; downloadable installer publication |
| Security/runtime | [44035644136 — PASS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44035644136) | PHP 8.3 and PHP 8.4 security/runtime gates |
| Codespaces/devcontainer | [43946333233 — PASS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43946333233) | Devcontainer and WordPress services validation at earlier commit `85f017314ba7be8a5766182271c719983b8f871e`, not the latest `4965aef` head |

The latest candidate has PASS on installer and PHP security workflows. An exact-`4965aef` devcontainer run was not found in the inspected Actions records. A GitHub-hosted CI job is not the same as an interactive developer Codespace, a production-host test, or a deployment.

## 3. Cumulative changes included on the delivery branch

- PR #135: HR library editing/versioning and share controls; vacancy sharing; company/branch profile editing; payroll-rule form.
- PR #138: vendor-neutral POS integration hub, branch device registry, external PLU-to-SKU mapping, daily POS staging/reconciliation/release controls, Windows folder-agent framework and contract tests.
- PR #139 and #140: WCAG/accessibility naming and contrast fixes, plus browser accessibility gate.
- PR #141: Owner-declared `ET-2025-AMEND-1395` payroll rule bundle. This is an Owner declaration, not independent Ethiopian payroll/tax adviser sign-off. See the open legal/source acceptance requirements below.
- PR #142: Product Master create flow, STK- SKU prefix/validation, dashboard Add Product shortcut, and links to the populated 16.2.18 GitBook manual. The PR's disposable browser test verifies product create/read-back and manual navigation.
- PR #144 is a separate documentation-branch merge: it adds 32 synthetic-data interface renderings to the manual. These are not approved-production-account captures; the capture checklist remains open.

## 4. Application component map

The 16.2.18 single-company Stockland application covers Command Center, Retail POS/cart, wholesale, Product Master/SKU variants, Inventory, Central Warehouse, branch operations, Receiving, requisitions/reservations/transfers, returns/damage/adjustments, physical counts, procurement, supplier/customer masters, AP/AR, expenses, GL/PLEDGER, daily closing, reports, authorization/audit, user/location access, HR/payroll, HR document library and PWA/mobile-responsive UI.

This is a single-company Stockland deployment with the Central Warehouse plus Goro, Gerji, Mexico and Megenagna branches. It is not a generalized multi-tenant SaaS provisioning platform.

## 5. Items still required before a truly end-to-end production-ready release

1. **Create a distinct next application version.** The current delivery artifact is still labelled 16.2.18 although its source is newer than the protected production commit. Build a distinct version from the cumulative source; regenerate manifest, CHANGELOG, evidence note and sidecars from the exact build; publish a formal GitHub Release only after all acceptance gates pass.
2. **Fix and retest System Health result rendering.** The current 16.2.18 front-end has a confirmed `quickOnly is not defined` reference in `runHealth()`: `quickOnly` is declared inside `classifyERPHealthScope()` but read in the separate health-rendering function. PR #144's manual checklist also records S29 as not captured because of this defect. Add a regression test and rerun the full matrix.
3. **Re-run container/environment on the exact release commit.** Run the Codespaces/devcontainer validation at the final candidate SHA, then perform fresh install and upgrade rehearsal from the protected 16.2.18 package on both MariaDB and MySQL. Retain restore evidence.
4. **Complete all authorized import channels.** Excel upload exists; the folder/POS agent and vendor-neutral integration framework have tests. Issue #136 remains open for production-grade device/folder allowlists, secure credentials, authentication/signatures, replay protection, idempotency, retry/resume, row-level quarantine/reconciliation, immutable provenance, and verified supplier/POS vendor API contracts. No live connector should be represented as enabled until its actual vendor protocol and credentials have been tested.
5. **Finish HR/payroll acceptance.** Issue #15 remains open. The additive 37-table HR lifecycle extension is Owner-gated and is not created by normal activation. The 24 packaged SOP resources have payload integrity PASS but approval state `DRAFT_NOT_APPROVED`. Complete the HR workflow, job descriptions/contracts/SOP approvals, data protection and vault-key setup, role/location tests, independent statutory review by a qualified Ethiopian payroll/tax adviser, and finance/Owner acceptance. The payroll rule bundle in PR #141 remains an Owner-declared rule set, not adviser certification.
6. **Complete Management Intelligence and cross-module acceptance.** Resolve issue #17; prove POS import routing, analytics/Management Intelligence scope, HR schema freeze, and the cross-module stock/finance/ledger/reporting invariants.
7. **Perform site-specific preparation.** Back up and restore the real site; configure HTTPS/PHP/extensions/upload limits and vault secrets; assign users/locations; validate opening stock and financial balances; run the operational System Control and deep integrity checks; only then perform separately authorized live operation.

## 6. Release and deployment facts

- GitHub's Releases endpoint returned an empty list at the time of this audit: there is no published GitHub Release object for this candidate.
- `github_release_id` is null in the installer manifest.
- No production WordPress/cPanel/SSH/SFTP or database connection was available for this inspection; no production deployment or production data change was performed.
- The connected Cloudflare account showed no zones and only an asset-oriented Worker, not a PHP/MySQL WordPress host.
- The repository's documented Codespaces environment is disposable QA infrastructure. Keep real employee/payroll/customer, payment and stock-ledger data out of it.

## 7. One-click installation versus full operational readiness

The plugin package's installer flow is one WordPress ZIP upload and activation. Activation provisions/verifies the 49-table operational baseline (29 Core/Operations + 16 Accounting/Procurement + four legacy HR/payroll tables) and Stockland's locations. The additional 37-table HR lifecycle extension is a separate Owner-approved schema action requiring schema fingerprint review, a restorable backup and written reason. The Owner must also complete System Control/live-operation authorization, user assignments, opening balances/stock, SOP review, and legal/payroll/vault approvals. Therefore **one-click plugin installation is implemented; one-click fully configured production operation is not yet evidenced**.

## 8. Source links

- [Canonical cumulative delivery branch](https://github.com/aistockland-web/Erp-version-16.1.89/tree/delivery/stockland-full-erp-completion-2026-11-11)
- [Protected baseline run](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177)
- [Latest installer matrix + one-pack artifact](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44035644180)
- [Latest PHP security/runtime gates](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44035644136)
- [Earlier passing devcontainer validation](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43946333233)
- [Controlled import authorization and acceptance](https://github.com/aistockland-web/Erp-version-16.1.89/issues/136)
- [Full HR/payroll lifecycle acceptance](https://github.com/aistockland-web/Erp-version-16.1.89/issues/15)
- [HR/payroll design and legal-source register](https://github.com/aistockland-web/Erp-version-16.1.89/issues/23)
- [Cross-module corrective action](https://github.com/aistockland-web/Erp-version-16.1.89/issues/17)
- [16.2.18 user manual](https://stockland-one-member-plc.gitbook.io/docs-user-manual-16.2.18/)

**Decision rule:** preserve the certified 16.2.18 installer exactly. Treat the `4965aef` package as the latest cumulative QA candidate only. Do not deploy it under the 16.2.18 identity.
