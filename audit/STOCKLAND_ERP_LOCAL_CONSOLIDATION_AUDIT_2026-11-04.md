# Stockland Ethiopia ERP — Local Consolidation Audit and Closure Register

**Audit date:** 4 November 2026  
**Canonical repository:** `aistockland-web/Erp-version-16.1.89`  
**Protected operational baseline:** 16.1.95  
**Selected HR/payroll implementation evidence:** merged 16.2.9 source  
**Disposition:** Engineering evidence consolidated; the final integrated installer is not yet buildable or honestly certifiable from the source and runtime evidence currently available.

## 1. Owner direction and delivery contract

The project Owner's instruction controls the work. The intended delivery is one complete, cumulative, one-click WordPress ERP installer with the agreed operational, finance, warehouse, POS, HR/payroll, SOP, reporting, authorization, audit, responsive-interface and hosting behavior preserved. The work must not be reduced to an HR-only package, a UI-only patch, an add-on chain or a second collection of partial plugin releases.

Engineering/build tools, CI checks, fixtures and synthetic calculations must remain outside the installed operational plugin. The installed plugin must contain real production business logic only; it must not ship sample employees, sample payroll, synthetic tax schedules, training records, debug switches or test-only execution paths. Existing stock, accounting, payroll, approvals and audit history must not be silently rewritten.

The Owner's full engineering authorization permits technical implementation choices. It does not authorize guessing live company payroll data, changing statutory treatment without a valid source and qualified review, or calling an unexecuted host acceptance PASS.

## 2. Exact archives independently recovered and locally checked

The following three exact WordPress installer archives were retrieved from the canonical repository's GitHub Actions artifacts, unpacked locally and audited by their bytes.

| Source | Installer SHA-256 | Files | PHP | PHP syntax | JS | JSON | CSS/PostCSS | ZIP CRC |
|---|---|---:|---:|---|---:|---:|---:|---|
| 16.1.95 protected operational master | `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625` | 75 | 48 | 48/48 PASS | 2/2 PASS | 3/3 PASS | 9/9 PASS | PASS |
| 16.2.9 HR/payroll implementation | `c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01` | 88 | 55 | 55/55 PASS | 2/2 PASS | 3/3 PASS | 15/15 PASS | PASS |
| 16.3.0 HRX integration artifact | `93b7417f7bb40199b9c5a75cc212e1c5a9ad407f9018e5218e39e42bbf10e200` | 76 | 49 | 49/49 PASS | 2/2 PASS | 3/3 PASS | 9/9 PASS | PASS |

All three archives have one `stockland-erp-app/` root and unique file paths. These checks prove archive integrity and language/parser syntax only. They do not prove database migrations, role authorization, payroll, GL posting, stock movement, WordPress activation, browser behavior or rollback.

The exact 16.3.1 consolidated installer referenced in Notion (85 files, 51 PHP, 63 assertions, SHA-256 `36b215da876f297a6d6948eda0b050e4d8b4d9d5ef2c7009e5b5120f3ca2faa5`) was not present in the accessible Library or as a retrievable canonical GitHub Actions artifact in this run. It was therefore not rebuilt, inspected or checksum-verified here. The 16.3.1 Notion record remains documentary evidence, not a substitute for the missing exact ZIP bytes.

## 3. Proven lineage and non-cumulative changes

### 16.1.95

- Protected business schema contract: 49 tables = 29 Core/Operations + 16 Accounting/Procurement + 4 HR/Payroll.
- Legacy HR/payroll tables include `employees`, `employee_compensation`, `payroll_periods` and `payroll_lines`.
- Recorded PHPCS/WPCS result: 0 errors, 28 warnings (27 custom-capability findings and one formatting warning).
- This is the protected rollback/reference master, not proof that it is the currently installed host source.

### 16.2.9

- Derived from 16.1.95; selected as the HR/payroll implementation source, not selected as a wholesale replacement for the operational ERP.
- Declares 86 tables (29 + 16 + 41), adding 37 HR tables and changing existing legacy HR definitions/defaults.
- Compared with 16.1.95: 75 common files, 56 byte-identical, 19 changed, 13 added; no removed paths.
- Adds useful HR lifecycle/payroll and POS cart code, plus six CSS modules. However, existing HR REST behavior and legacy schema compatibility need careful mapped integration.
- PHPCS/WPCS: 16,364 errors and 690 warnings. All PHP files still pass syntax; this coding-standard result is not acceptable for final release.
- `includes/class-stockland-hr-payroll-calculator.php` contains a guarded `SYNTHETIC_TEST_ONLY` execution path, an explicit synthetic switch, and synthetic result labels. It is disabled by default but is still shipped in the product runtime. This path and any associated fixture behavior must be removed from the installed plugin; tests belong in external QA tooling only.
- The receipt states `schema_contract_approved=false` and `production_ready=false`; it explicitly lists external acceptance work not yet complete.

### 16.3.0 HRX

- Built from 16.1.95, not cumulatively from the merged 16.2.9 source.
- Declares 92 logical table entries: 29 Core/Operations + 16 Accounting/Procurement + 47 HRX replacement entries; legacy HR tables are marked inactive in that model. Its source explicitly sets `SCHEMA_DESIGN_APPROVED=false` and blocks HRX schema creation.
- Compared with 16.2.9: 73 common paths, 56 byte-identical, 17 changed, 15 removed and 3 added. Removed items include the POS cart controller, six CSS modules, legacy HR lifecycle/payroll services and the legacy HR REST path. This is a functional regression risk if adopted wholesale.
- Compared with 16.1.95: 73 common paths, 60 byte-identical, 13 changed, two removed (`class-stockland-payroll-statutory.php` and legacy HR REST) and three HRX classes added.
- The package receipt says `production_ready=false`, `schema_changes_in_package=true`, status `PRODUCTION_ACCEPTANCE_PENDING`.
- Recorded review PHPCS/WPCS result: 687 errors and 145 warnings. The PHPCS recheck workflow was skipped; no clean coding-standard result was established.

**Integration decision:** do not use 16.2.9 or 16.3.0 as a wholesale replacement. Preserve the protected stock, POS, finance, approvals, ledger, roles, audit, schema and UI contracts. Selectively carry forward verified features and resolve the HR schema/migration design before activation.

## 4. Verified implementation blockers

| ID | Area | Finding | Closure requirement |
|---|---|---|---|
| CONS-01 | HR schema/data | The 16.2.9 86-table schema and 16.3.0 HRX 47-table proposal are not approved final schemas. Existing four HR tables must remain protected until source mapping, ERD, relation/FK design, orphan checks, copy-only migration, reconciliation and rollback are proven. | Publish one source-mapped logical/physical model; reuse shared services; preserve legacy history; complete Owner/HR/Finance design approval and migration/rollback evidence. |
| CONS-02 | Synthetic payroll logic | 16.2.9 ships `SYNTHETIC_TEST_ONLY` path and synthetic result labels in PHP runtime. | Move every fixture/synthetic switch and data generator to external QA; remove runtime entry points and labels from the installed plugin; prove production calculator rejects unapproved rules. |
| CONS-03 | Payroll time and leave | HRX preflight blocks approved overtime/night/rest-day/holiday time and approved unpaid/part-paid leave because calculation does not consume those inputs. The separate `overtime_amount()` helper is disconnected from payroll-line construction. | Implement only when an approved effective-dated legal/payroll rule exists; otherwise preserve explicit blocking. Add calculation and reversal/reconciliation tests. |
| CONS-04 | Compensation frequency | HRX calculator supports only `MONTHLY` items and rejects `ONE_TIME`, `HOURLY` and `DAILY`. | Implement frequency-aware period allocation and exact-once treatment, or keep the unsupported frequency blocked until an approved rule exists. |
| CONS-05 | Statutory remittance concurrency | `verify_remittance()` reads remittance, obligation and outstanding total before starting its transaction; no obligation-level row lock protects the remaining liability. Concurrent verifications could over-post the same obligation. | Begin transaction before row reads; lock remittance and obligation with `FOR UPDATE`; serialize all remittance creation/verification on the same obligation; recalculate sums under lock; add unique/idempotency protection and concurrent database tests. |
| CONS-06 | In-kind payee verification | Current check binds a stock reference to the request and requires an approved valuation document, but does not independently reconcile item/product, quantity and approved unit valuation to the exact posted stock issue. | Reconcile each stock-issue line and approved valuation line; require exact ETB totals and independent review; add end-to-end movement/rollback tests. |
| CONS-07 | PAYE/legal rules | The PAYE taxable base, pension treatment, leave/overtime rates, allowances and exemptions require source-identified, effective-dated legal rules and qualified Ethiopian tax/pension review. | Do not guess or seed statutory rules. Preserve legal-review blocks until authoritative source and written qualified opinion are attached and independently approved. |
| CONS-08 | HR private documents | HRX document storage/access and key lifecycle require actual host validation. Hashing alone is not encryption. | Prove authenticated role/location access, encryption at rest, key rotation/recovery, download authorization, backup encryption, audit access minimization and restore. |
| CONS-09 | Finance integration | Final HR must reuse Stockland GL/AP/AR/PLEDGER/accounting configuration and preserve transactional/idempotent posting; it must not introduce a parallel ledger. | Demonstrate payout, partial/failed payment recovery, statutory remittance, payee/creator AP, in-kind issue and period close with balanced journals and reconciliation. |
| CONS-10 | Current host identity | Project records disagree about the last installed version and branch/package lineage; no authenticated host manifest or deployed file hashes were available in this run. | Record exact installed ZIP, file hashes, plugin header, database/schema versions and cache identity from the intended WordPress host before choosing an in-place upgrade path. |
| CONS-11 | PWA / System Health | Issue #47 documents exact-schema, incomplete supplemental-scan and PWA registration failures for an earlier installed source. This session checked source syntax only, not deployed `/stockland-erp-app/` HTTP responses or worker scope. | Verify deployed source hashes, server-generated complete scan receipt, 49-table/source-mapped contract and exact subdirectory service-worker registration on the intended host. |
| CONS-12 | HR/SOP library | The three recovered installers contain 0 DOCX and 0 PDF resources. The PR #49 records a 24-document pack, but the actual 24 files were not retrieved into this session and the PR/package is closed unmerged. | Retrieve and hash the actual 24 approved documents; verify each file's content, version, permissions, links and downloads in the HR Process Center. Do not claim the documents are shipped from a description alone. |
| CONS-13 | PHP standards/security | 16.2.9 and 16.3.0 coding-standard totals fail the project quality gate. The baseline's 28 warnings also require a deliberate disposition. | Run PHPCS/WPCS over the exact consolidated package on the exact source commit; fix errors and review every warning; run Semgrep/CodeQL/dependency checks and publish reports. |
| CONS-14 | Runtime acceptance | This local environment has PHP 8.4.24 and Node 22.16.0, but no `mysqli`/`pdo_mysql`, WordPress installation, WP-CLI, MySQL/MariaDB server, Composer or Docker. | Run clean activation, upgrade, role matrix, full workflow, concurrency, backup/restore and rollback against the approved WordPress/PHP/MariaDB environment. |
| CONS-15 | CI governance | CI records for some older candidate runs report skipped build/PHPCS/package gates. A workflow conclusion `success` with mandatory build/tests skipped is not exact-package certification. | Fail closed if required builders, reports or tests are absent; bind all checks and artifacts to one immutable source commit and installer SHA. |

## 5. PR #48 source-level review disposition

The current HRX source review found 40 GitHub review threads and 0 resolved threads on PR #48. A later static source read found source mitigations for a number of concerns: single-record/list location mapping, HR-only salary redaction, legal-review transaction/audit handling, frozen payroll input revalidation, foreign-currency rejection, open-exit preflight, partial remittance arithmetic, payout-batch close checks, cash-only zero in-kind handling, approved engagement enforcement, physical schema type/index comparison, accepted-offer duplicate checks, entity-specific writer metadata and several UI request/action mappings.

Those are **source-level mitigations only**. Runtime role/row-scope, transaction rollback, concurrency, browser/API and accounting acceptance remain necessary. The following remain materially open: payroll time/leave and non-monthly frequency handling, exact in-kind inventory valuation, incomplete branch-specific social snapshot mapping, and obligation-level remittance locking. The original PR is closed without merge; its source is not the final installed plugin. No review thread is represented as closed merely because a similar branch implementation exists.

## 6. Local environment and checks actually executed

- PHP 8.4.24; 152 PHP files across the three exact packages passed `php -l` (48 + 55 + 49).
- Node 22.16.0; both JavaScript assets in each package passed `node --check`.
- All 9 + 15 + 9 CSS files parsed with PostCSS.
- All three JSON files in each package parsed successfully.
- All three installer archives passed ZIP integrity/CRC and single-root/inventory checks.
- Static file comparison established the additions/removals/change counts in section 3.
- Direct source scan identified the 16.2.9 synthetic payroll code; earlier generic marker scans were insufficient and are not used as a clean-product claim.

Not run: WordPress installation/activation, MariaDB migrations, authenticated roles, location denial tests, POS transactions, warehouse/physical count, GL/AP/AR/PLEDGER posting, full HR lifecycle, payroll run/approval/payout/remittance, concurrent remittance, secure file upload/download, PWA worker scope, complete health scan, backup restore, rollback, legal review or stakeholder sign-off.

## 7. Consolidated engineering route

1. Lock the actual target host's deployed source/package fingerprint and read-only data/schema inventory. Reconcile it against 16.1.95 and subsequent approved changes; do not infer the live release from inconsistent historic notes.
2. Use 16.1.95 as the protected reference for business services and the exact selected 16.2.9 commit as an HR implementation source. Reconstruct the complete cumulative file inventory; retain the POS/cart controller and all six later CSS modules unless a verified successor supersedes them.
3. Reconcile every changed file against protected operations/finance/warehouse/authority/ledger/POS/HR code. Preserve the 49 legacy table contract until the final HR model and copy-only migration have evidence and required written approvals.
4. Complete the HR lifecycle, using existing Stockland role, location, audit, workflow, money, notifications, calendar, accounting and document services. Do not ship parallel ledgers or synthetic runtime behavior.
5. Fix remittance serialization, payroll time/leave/frequency, exact in-kind stock valuation, PII and vault security, legacy REST compatibility, UI/API idempotency and all other review findings. Every code fix needs focused regression and integration coverage.
6. Integrate the 24 actual HR resource documents, SOPs, job descriptions and employment documents after retrieval, review and permission mapping.
7. Run exact-source PHPCS/WPCS with zero errors and reviewed warnings; PHP/JS/CSS/JSON checks; full test suite; Semgrep/CodeQL/dependency review; package inventory, one-root and reproducible build/checksum verification.
8. Install the exact one-click ZIP in a cloned copy of the intended WordPress/PHP/MariaDB environment. Execute all Owner, Management, Finance, HR/Payroll, Warehouse, Branch and Sales workflows, run at least two complete payroll periods in parallel, reconcile stock and GL/PLEDGER/AP/AR and statutory liabilities, prove recovery and rollback, then collect the Owner/HR/Finance and qualified legal/tax/pension acceptance required by the approved plan.
9. Publish one canonical source commit, exact immutable installer ZIP, manifest/file inventory/build receipt, independently recomputed checksum, audit bundle and linked Notion closure matrix. The build/QA harness stays outside the installed plugin ZIP.

## 8. Repository and decision-record state

- Canonical `main` observed at `a46214f6bd5e21a9948d5c896d2136d722d36f16` during the audit.
- PR #48 (HRX) and PR #49 (resource library) are closed without merge; PR #54 is also closed without merge and contains documentation, not a plugin release.
- Issue #47 remains open and documents unresolved schema/scan/PWA/runtime acceptance gates.
- The Notion protected-master decision and HR Design Reset v0.5 remain controlling. The 16.3.0/16.3.1 schema count is not an approved physical design.

**Final disposition:** Do not publish a fabricated all-green report or a ZIP assembled from incompatible version lines. The static archive/syntax checks listed above are closed with exact evidence. The remaining integration, payroll, security, statutory, runtime, data-migration, restore and Owner/HR/Finance acceptance findings must be fixed and proven before the single consolidated WordPress installer can honestly be called the completed production ERP.