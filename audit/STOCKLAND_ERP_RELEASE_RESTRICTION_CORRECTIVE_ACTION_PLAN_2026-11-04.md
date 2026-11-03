# Stockland ERP — Release Restriction and Readiness Corrective Action Plan
**Audit date:** 4 November 2026  
**Scope:** GitHub main release/audit language and the exact 16.3.0 HRX integration-candidate artifact.  
**Current reported installed plugin:** 16.2.13 (user-supplied System Health evidence; installed ZIP/source SHA not independently obtained).  
**Candidate source baseline:** 16.1.95, SHA-256 \`494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625\`.  
**Review PR:** #48, \`feature/hrx-clean-slate-runtime-16-3-0\`; draft only.  
**No production WordPress or MariaDB mutation was performed.**

## Executive finding

The audit found **no plugin-wide runtime flag that shuts down the ERP because a release receipt says \`production_ready=false\` or because a document says NO-GO**. The words \`NO-GO\`, \`staging only\`, and \`NOT LIVE READY\` are found in dated audit/blueprint records and the System Health UI. Their scope must be stated accurately. The actual runtime restriction identified in the 16.3.0 candidate is narrower: \`SCHEMA_DESIGN_APPROVED=false\` blocks installation of the proposed HRX schema. That is a real, deliberate HR/payroll data-model gate; it does not block plugin activation or the core/accounting runtime.

Two source-level readiness inaccuracies were corrected in the candidate builder: static System Health no longer marks employee compensation classification as PASS when no employee-level preflight was run, and setup health now reads the HRX schema status instead of inferring readiness from the retired legacy HR shim. Candidate metadata and health presentation were corrected so the 16.3.0 artifact cannot be mistaken for a cumulative update to 16.2.13 or for a whole-product shutdown.

The 16.3.0 artifact remains an HRX integration candidate—not a safe replacement for the reported 16.2.13 installation. It is built from 16.1.95 and retires the legacy HR runtime while the proposed HRX DDL remains unavailable. That would leave the HR/payroll domain unavailable if installed before approved HRX schema and lifecycle acceptance. Do not install it as the cumulative ERP update.

## Evidence and provenance

- Latest exact-package workflow: [Run 41733684055](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/41733684055), exact PR head \`64a5609e5e65ca32c9fadfd10166212422e9c8eb\`, passed.
- Exact candidate artifact: [Actions artifact 13276687926](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/41733684055/artifacts/13276687926).
- Outer evidence archive SHA-256: \`215bd114c96ce21c3e9fc521172878a7a19c4fdb34b5a7cc842ff179c9927953\`.
- Inner plugin installer SHA-256: \`f48993499e7664c45a079595af8efedbd5881728952cf019428f5db2d1872412\`.
- Receipt confirms \`artifact_class=INTEGRATION_CANDIDATE\`, \`baseline_version=16.1.95\`, \`cumulative_core_update=false\`, 76 package members, 49 PHP files, 47 proposed HRX tables, \`production_ready=false\`, \`business_data_changed_by_builder=false\`.
- PHP syntax: 49/49 on PHP 8.3 and 49/49 on PHP 8.4.
- Candidate exact-package contract tests, JavaScript syntax, CSS/PostCSS, private-document encryption fixtures, and source-security assertions passed.
- Semgrep: 25 rules, 49 PHP files, 0 findings.
- PHPCS/WPCS processed all 49 PHP files: 687 errors and 145 warnings remain for style/standards triage. The associated security/database triage reported 0 unreviewed security/database findings. These PHPCS counts are not a clean style pass and must remain visible.
- The separate legacy 16.1.89 candidate PHPCS recheck workflow was skipped; this does not erase the PHPCS run performed by the HRX exact-package workflow.
- No isolated WordPress/MariaDB end-to-end lifecycle, live role/location acceptance, independent Ethiopian legal review, production backup/restore/rollback, installed-source SHA parity or Owner/HR/Finance acceptance was performed.

## Restriction classification

| Restriction or wording | Source layer | What it actually does | Correct disposition |
|---|---|---|---|
| “NO-GO” in 23 Oct / 29 Oct audit records | Audit documentation | Records a dated decision against the exact artifact/evidence set then reviewed | Keep as historical audit evidence; do not carry forward as a timeless product flag |
| “engineering candidate / staging only” in the 29 Oct blueprint | Architecture/release documentation | Overbroad wording for a dated 16.2.0 candidate | Corrected to a historical, candidate-scoped decision; no runtime staging-only mode exists |
| “DRAFT — NOT FOR PRODUCTION INSTALL” in PR #48 | GitHub pull-request review metadata | Prevents treating the HRX integration candidate as approved/merged | Retain draft review state for this candidate; it does not disable the ERP |
| \`production_ready=false\` / \`PRODUCTION_ACCEPTANCE_PENDING\` in build receipt and manifest | Builder/manifest/receipt | Describes the evidence state of this 16.3.0 candidate; not consumed as a runtime boot flag | Retain for this artifact because required acceptance evidence is genuinely absent; future promotion requires a separate evidence-backed release record, not a permanent product restriction |
| \`SCHEMA_DESIGN_APPROVED=false\` | HRX runtime schema installer | Rejects only HRX DDL creation (HTTP 409) until design approval | Retain. It protects the 47-table proposal from being installed before dependency/ERD/SOP/rollback review and written Owner approval |
| Aggregate \`live_ready=false\` | System Health API/UI | Reports that the complete suite of schema, accounting, ledger, payroll, authorization and integrity checks is not all healthy | It is not a global plugin boot/activation kill switch in this package. Present module-level results separately; do not turn incomplete checks into PASS |
| \`NOT LIVE READY\` | System Health UI | Text label derived from aggregate \`checks.live_ready\` | Corrected for the HRX-only-pending case to “HRX SETUP PENDING • CORE CHECKS SEPARATE”; no full-suite readiness is implied |
| \`live_ready=false\` in setup response | Setup API | Setup mode explicitly prevents business endpoints from registering while required core/accounting schema is incomplete | Keep. This is a true setup integrity guard, not a restriction on an already healthy running core |
| “staging” in POS/import code | Business workflow | Rows are validated/staged before manager-authorized commit/posting | Keep. This is transactional data staging, not deployment staging |
| Legacy reset wording in original PR body | PR description | Claimed an optional non-production reset workflow | Corrected: the current candidate contains no legacy HR reset/drop/truncate endpoint; old tables are preserved as read-only history |

## Source-level defects and corrections

### CAP-RR-01 — Employee compensation check could false-PASS
**Original source:** \`ci/build_hrx_clean_slate_16_3_0.py\` HR health builder block. It reported \`current_period_preflight=NOT_RUN_BY_STATIC_SYSTEM_HEALTH\` but assigned \`payroll_compensation_classification = payroll_statutory_model\`.  
**Risk:** a working calculator/schema could make an employee-level eligibility check appear to pass without examining employee compensation, attendance, leave or rules for a selected period.  
**Correction:** report status \`NOT_RUN\`, set the boolean check false, and publish \`check_statuses.payroll_compensation_classification=NOT_RUN\`. The UI explains that the selected-period preflight is required.  
**Regression evidence:** exact-package QA now asserts that the check cannot PASS without a scan. Run 41733684055 passed.

### CAP-RR-02 — Setup health used the retired legacy shim as HR readiness
**Original source:** setup response derived \`hr_schema_upgrade_ready\` from the legacy \`snapshot.hr.upgrade_gaps\`. The compatibility shim intentionally reports no legacy runtime tables/gaps, so this did not prove the HRX schema was ready.  
**Risk:** setup health could report HR ready while HRX was absent or unapproved.  
**Correction:** derive HR readiness from \`snapshot.hrx_replacement.ready\` AND \`design_approved\`; expose independent HRX status/missing/drift diagnostics. Rename the setup base schema check to \`schema_contract_core_finance_exact\` for the 29+16=45 core/accounting scope; HRX is reported separately.  
**Regression evidence:** exact-package QA asserts these source contracts. Run 41733684055 passed.

### CAP-RR-03 — Candidate identity was misleading/inconsistent
**Original source:** 16.3.0 release label described a “full-cycle release”; manifest baseline was 16.1.94 while the receipt pinned the 16.1.95 SHA. The outer PR description also claimed a reset path not present in the actual package.  
**Risk:** a reader could mistake the HRX overlay for the cumulative 16.2.x product or rely on a nonexistent reset feature.  
**Correction:** manifest, README, receipt and Actions artifact now identify an HRX integration candidate derived from exact 16.1.95, explicitly not a cumulative 16.2.x update; receipt records baseline version/hash and \`cumulative_core_update=false\`. PR body now states no legacy reset/drop/truncate endpoint is included.  
**Regression evidence:** exact-package QA validates candidate identity and baseline; CI passed.

### CAP-RR-04 — Health wording implied the whole ERP was unavailable for an HRX-only pending condition
**Original source:** System Health derived “NOT LIVE READY” from aggregate full-suite checks. HRX schema approval failure was mixed into the same user-facing label as a core runtime/setup failure.  
**Correction:** where core schema and accounting checks pass and the only outstanding failure group is explicitly HRX/schema-dependent, the UI labels “HRX SETUP PENDING • CORE CHECKS SEPARATE” and explains that this is module-scoped, not an application-wide runtime lock. Aggregate \`live_ready\` remains false; the complete ERP suite is not falsely declared ready.  
**Regression evidence:** exact-package QA checks this label and explanatory wording; CI passed.

### CAP-RR-05 — Dated architecture note used evergreen “staging only / production NO-GO”
**Original source:** \`docs/engineering/STOCKLAND_SAAS_ENGINEERING_BLUEPRINT_2026-10-29.md\`.  
**Correction:** the disposition is now explicitly historical and scoped to the 16.2.0 candidate and the evidence as of 29 October. The document states it is not a runtime mode, not a blanket status for every Stockland ERP installation and must not carry forward automatically.

### CAP-RR-06 — HRX candidate is not a full ERP replacement
**Source facts:** 16.3.0 is built from 16.1.95, while the latest user-supplied installed version is 16.2.13. The candidate removes the old HR REST/statutory runtime classes and replaces the HR screen with HRX, while HRX DDL is still blocked by \`SCHEMA_DESIGN_APPROVED=false\`.  
**Impact:** installing this package before HRX schema approval/installation and workflow migration acceptance would leave the HR/payroll module unavailable. This is a genuine module-level availability gap, not a global ERP kill switch.  
**Disposition:** do not install 16.3.0 as a cumulative replacement for 16.2.13. The correct next implementation is a cumulative build from the exact installed 16.2.13 source, retaining the current operational core and delivering HRX only through an approved, tested transition. Do not activate the retired legacy statutory calculator simply to bypass legal/data safeguards.

## Corrective action register

| ID | Severity | Corrective action | Layer | Status |
|---|---|---|---|---|
| CAP-RR-01 | High | Static payroll status must remain NOT_RUN until selected-period preflight executes | Runtime health + UI + CI | Implemented; exact-package CI passed |
| CAP-RR-02 | High | Setup health must read HRX readiness, not legacy shim | Setup API + CI | Implemented; exact-package CI passed |
| CAP-RR-03 | High | Pin baseline/version; label 16.3.0 as integration candidate; fix PR/README/receipt claims | Builder + manifest + PR + CI | Implemented; exact-package CI passed |
| CAP-RR-04 | Medium | Distinguish HRX-pending from core ERP failure without asserting full-suite readiness | UI + health diagnostics + CI | Implemented; exact-package CI passed |
| CAP-RR-05 | Medium | Scope 29 Oct “staging only / NO-GO” to its dated 16.2.0 assessment | Architecture documentation | Implemented in PR branch |
| CAP-RR-06 | Critical | Do not install the HRX-only candidate as a full cumulative ERP replacement; obtain exact 16.2.13 source and build cumulative package | Release architecture | Open; exact source/hash unavailable in this audit |
| CAP-RR-07 | High | Approve/reduce 47-table HRX design against SOP, shared ERP services, dependencies and source ERD before schema creation | Data model + migration | Open; guard intentionally retained |
| CAP-RR-08 | High | Complete HR lifecycle E2E, branch/location authorization, private-doc access, two-period payroll, payment/remittance/GL and final settlement | Runtime + isolated WP/MariaDB E2E | Open |
| CAP-RR-09 | High | Complete qualified Ethiopian labour/tax/pension review and current effective-dated rule evidence | Legal/policy data | Open |
| CAP-RR-10 | High | Complete backup/restore/rollback and exact live-source parity; obtain Owner/HR/Finance acceptance | Operations/release | Open |
| CAP-RR-11 | Medium | Triage 687 PHPCS errors and 145 warnings; preserve separate finding counts and severity | CI/engineering quality | Open; not a security pass, not a runtime blocker by itself |
| CAP-RR-12 | High | Verify PWA browser scope, actual hosted environment, full financial/stock reconciliation and deep relational scan against authorized live/staging clone | Runtime/host acceptance | Open |

## Final decision

1. **Do not describe the entire Stockland ERP as “staging only” or “not product ready” based on the HRX candidate's draft status.** There is no global runtime kill switch matching those phrases in the inspected 16.3.0 ZIP.
2. **Do not remove the HRX schema-design, payroll-preflight, role/location, private-document, legal or migration safeguards.** They are scoped to real unverified HR/payroll risks.
3. The 16.3.0 candidate's package CI is green, but its HR/payroll replacement is not ready for installation as the full ERP because the baseline is 16.1.95, HRX schema remains unapproved, and the legacy HR runtime has been retired in that candidate.
4. The current 16.2.13 product cannot be source-audited or patched in this work until its exact source package/hash is available. The 16.3.0 corrections must not be represented as 16.2.13 fixes.
5. No PR merge, plugin deployment, production DB migration, employee-pay change, inventory posting, ledger mutation or audit-chain modification was performed.

The governing rule is applied throughout: no invented restrictions, no concealed failures and no false readiness claims.


## Addendum — runtime guard and scan-state source trace (4 November 2026)

A second pass inspected the WordPress bootstrap, activation, uninstall and full health implementation in the exact downloaded candidate ZIP.

| Source | Executable behavior | Classification |
|---|---|---|
| \`stockland-inventory.php\` activation hook | Calls only \`Stockland_Activator::begin_activation()\`. If that throws, it records/logs the exception, deactivates this plugin and shows the activation error so WordPress itself remains usable. | Legitimate activation-failure containment; not tied to NO-GO or candidate receipt. Retain and test. |
| \`stockland-inventory.php\` boot path | Builds a missing core/accounting/legacy-shim table list. If core/accounting schema is incomplete or upgrade inspection fails, it leaves frontend/setup available, marks operational schema unavailable and does not register business REST/App controllers. | Legitimate fail-closed setup guard for incomplete core/accounting schema. It is not triggered by the HRX design flag alone in this candidate. |
| HRX registration | HRX REST controller registration occurs only after the core/accounting application gate; individual HRX operations rely on HRX tables and readiness. | Module dependency; an uninstalled HRX model means HRX operations are unavailable, not that unrelated core routes are stopped. |
| \`uninstall.php\` | Explicitly preserves \`wp_stock_*\` tables and options. | Data-preservation safeguard; keep. |
| \`class-stockland-hr-replacement.php\` | The only HRX-design hard gate is \`SCHEMA_DESIGN_APPROVED=false\`, returning HTTP 409 from HRX schema installation. | Scoped HRX DDL gate; retain until model approval. |
| Health \`live_ready\` | Computed as an aggregate of all critical core, accounting, HRX, payroll, ledger, reconciliation and integrity checks; used for health response/UI, not consulted as a global plugin bootstrap/activation switch. | Full-suite status indicator. It must remain false when any mandatory suite check is unresolved, but the UI must show module-specific state. |

The \`DROP TABLE IF EXISTS\` occurrences seen in the candidate's core activator are bounded to internal disposable import/balance staging-table migrations (not the four legacy HR tables). The candidate's HRX plugin contains no legacy-HR reset/drop/truncate endpoint. Its uninstall file intentionally preserves all ERP tables/options.

### CAP-RR-13 — skipped relational scan displayed as a generic failure

**Original behavior:** when the database or exact HRX schema contract was not ready, the supplemental read-only integrity joins were deliberately skipped and the diagnostic returned \`SCAN_INCOMPLETE\`, but the boolean check was false without a corresponding tri-state status. The UI could therefore display FAIL even though the scan had not executed.  
**Correction:** the builder now maps a skipped scan to \`check_statuses.supplemental_integrity_scan=NOT_RUN\`; when executed, it reports PASS or FAIL from the actual scanner result. The underlying aggregate \`live_ready\` remains false and \`SCAN_INCOMPLETE\` remains visible; this is not a readiness bypass.  
**Regression:** the exact-package QA now asserts both the skipped and executed status paths. The 4 November exact-package workflow for the updated head must pass before this CAP is marked CI-verified.



## Addendum — final verification boundary for the current PR head

The package-level CI pass at [run 41733684055](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/41733684055) applies to code head \`64a5609e5e65ca32c9fadfd10166212422e9c8eb\`. A subsequent successful run [41734244180](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/41734244180) applies to head \`1fbf761632e5e568331b5ea1167c643c0d72db46\`. The latter included the candidate-identity, payroll NOT_RUN, setup-health, scoped UI, and historical-blueprint corrections then present.

After that run, two additional changes were committed:
- \`0ef39203ccfa45db1b26c523ce9f95f3ccae1e7c\` and \`ab74bc74be6b330b16fe7d6147bffa92777305d4\`: mark a skipped supplemental integrity scan \`NOT_RUN\` (while preserving aggregate full-suite false) and add exact-package assertions for skipped/executed scan states.
- \`a6930357fe45a70a0164784fe237ae00cf52e55e\`: add tests proving that release/HRX readiness flags do not become a global plugin boot switch, that incomplete core/accounting schema still uses the intended setup gate, and that uninstall preserves Stockland business tables/options.

No GitHub Actions run is currently associated with the latest source head \`a6930357fe45a70a0164784fe237ae00cf52e55e\`. Those final tri-state and bootstrap/uninstall assertions are therefore **implemented but not yet CI-verified**. Do not call them passed. The earlier successful runs remain valid only for their recorded exact commit SHAs. The PR is kept open and draft; no merge/deployment is authorized by this audit.

