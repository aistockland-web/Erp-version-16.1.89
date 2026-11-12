# Stockland ERP — 16.2.13 Operational Baseline and 16.2.9 Builder Correction

**Owner evidence update:** 8 November 2026  
**Repository main checked at:** 339d519f5597c5c9b1efd45c5eb376216d976703  
**Purpose:** Correct prior handover wording that treated 16.1.95 as the final operational starting point. The designated operational upgrade baseline is 16.2.13; 16.1.95 remains the protected rollback/reference package.

## 1. Corrected baseline decision

### Operational / upgrade baseline: 16.2.13

The 6 November source-archaeology Addendum B records owner-supplied 16.2.10–16.2.13 archives as re-hashed and ZIP-integrity checked, with one stockland-erp-app/ root.

| Package | SHA-256 | Files | PHP |
|---|---|---:|---:|
| 16.2.10 MASTER_FULL_PACK | 2ea7832efb6a42decfd53496aaaf309eb068228c54507a3b8022776860a1601f | 88 | 55 |
| 16.2.11 MASTER_FULL_PACK | 27c66b65e999f5609d070c105517ecc491fb705a887bbdefb11851454750dee8 | 88 | 55 |
| 16.2.12 WORDPRESS_INSTALLER | db82f3e8ec565539c0341d47d39e0382b4ba04e370464f59a742c2941d96ca75 | 112 | 80 |
| 16.2.13 WORDPRESS_INSTALLER | 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13 | 113 | 80 |

The exact original 16.2.13 ZIP is not available in this current workspace for a fresh re-hash. Its identity above was historically recomputed from the owner-supplied archive on 6 November and recorded in Addendum B. Whether those exact bytes are currently installed on the live host remains owner-reported; verify live-directory parity with a read-only fingerprint before planning an update.

### Protected rollback/reference: 16.1.95

SHA-256: 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625

Keep the 16.1.95 package unchanged as the rollback/reference source. Do not treat it as the automatic final operational starting point merely because later builders download it.

Evidence sources:
- [6 November Addendum B commit](https://github.com/aistockland-web/Erp-version-16.1.89/commit/1383885d43b95c934fe1298bd9153f92d666d4df)
- [7 November Cumulative Execution Plan](https://app.notion.com/p/4106db0f767e8119a322ff39cf928763)
- [7 November Cross-Chat Reconciliation](https://app.notion.com/p/4106db0f767e8182a599e5ea5104c3f4)

## 2. Confirmed cumulative 16.2.x lineage

The 16.2.13 package is a cumulative descendant of 16.2.9:

- 16.2.9 → 16.2.10: 13 changed files, no additions/removals.
- 16.2.10 → 16.2.11: 7 changed files.
- 16.2.11 → 16.2.12: 26 additions (SOP library route, manifest and 24 payloads), 2 removals (README and RELEASE_MANIFEST), 17 changed.
- 16.2.12 → 16.2.13: 1 addition (assets/css/13-enterprise-saas-system.css), 7 changed.
- 16.2.13 versus 16.2.9: 27 added, 2 removed and about 16 changed. The core DB, sales REST and POS-cart REST files are byte-identical to 16.2.9. POS/cart, CSS modules 07–12 and non-HR hardening survive into .13.
- 16.2.13 versus 16.1.95: 40 added, 2 removed and about 19 changed.
- 16.2.13 versus 16.3.0: 42 files exist only in .13, 5 only in .3.0 and 17 differ. The .3.0 HRX line omits .13's SOP library, CSS 13, templates and uninstall policy.

**Engineering consequence:** build the final cumulative successor from recovered 16.2.13 operational source. Do not rebuild the operational product from 16.1.95 or from the original .95-pinned 16.2.9 builder. Reuse approved corrections and stronger code from later sources only after diffing them against .13 and proving compatibility.

## 3. The 16.2.9 builder is explicitly pinned to 16.1.95

Current main source file: [ci/build_full_hr_payroll_16_2_0.py](https://github.com/aistockland-web/Erp-version-16.1.89/blob/main/ci/build_full_hr_payroll_16_2_0.py), fetched blob SHA 38a4e1f79ab5494b22e2174b8b2e487ec9b6350b.

Builder constants:

    BASE_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
    RELEASE = "16.2.9"

It downloads and verifies the exact 16.1.95 artifact before building 16.2.9. Its receipt states baseline_release: 16.1.95 and baseline_sha256 equal to the protected .95 hash. This confirms .9 is an extension of .95, not an unrelated rebuild. It does not mean .13 should be rebuilt from .95; .13 contains later .10–.13 work.

Original 16.2.9 workflow: [#42125671298](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42125671298), head c66a8c6b99f3d6dfe4579775090e9ee9518d765b; PHP 8.3 and PHP 8.4 builder jobs succeeded.

## 4. PR #61 correction and corrected exact 16.2.9 candidate

[PR #61](https://github.com/aistockland-web/Erp-version-16.1.89/pull/61) is merged as commit ad127ae8f672243b4d5bd7bd703e6104beef995a.

It corrects two unsafe defaults:

- Existing-table employee compensation approval_status now defaults to DRAFT, not APPROVED. Existing rows do not become approved without a real approval event.
- Historical and fresh-install employee/employer pension-rate columns remain nullable/NULL; the migration no longer assumes or backfills 7%/11%.

Corrected workflow: [#42258772425](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/42258772425), head e6cf4d70b211edfe3308a5fe3672dff8afd0c2ec. Both exact-candidate build jobs completed successfully on PHP 8.3 and PHP 8.4.

Artifact #13523269084:
- Outer Actions artifact SHA-256: cece30278781cef05da195e31bb1a30c74b7849c617e92ac7cd5ceba4dea6643.
- Inner installer SHA-256: fc22346f6e1fafc9e47a50eba76a2c0846fb9f09407168564e1f295ce69290d8.
- 88 runtime files; ZIP CRC PASS; one plugin root.

The corrected receipt still says schema approval false, production_ready false, 86 candidate tables (29 Core/Operations + 16 Accounting/Procurement + 41 HR/Payroll), 37 new tables, no production database touched and WordPress not activated. Outstanding acceptance includes Ethiopian statutory/legal review, private document vault/access, isolated WordPress/MariaDB lifecycle E2E, backup/restore/rollback, source parity, POS/cart concurrency/retry/rollback, shared UOM precision and AP/AR/PLEDGER/GL reconciliation.

The corrected artifact's PHPCS/WPCS report records **16,364 errors, 690 warnings and 16,683 fixable findings** across 37 report files. Its Semgrep report records 0 findings and 0 scanner errors. Workflow success is not a WPCS-clean result or full production acceptance. Fix and report the coding-standard debt; do not hide it or weaken checks.

PR #61 fixes the implicit approval/statutory default defect only. Its own scope leaves larger schema design, encryption, role/location, legal, resource pack, full WPCS and WordPress/MariaDB live-runtime acceptance open.

## 5. 16.1.95 quality reference

Workflow [#40036018031](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40036018031), source SHA c80d3efc84d175ec59cb5b8d6992a3ecab744674.

Downloaded Actions artifact #12536703884 was independently checked:
- Outer artifact SHA-256: 7c9ee6ce4b1969182b20315cb0d68974147100925d84146d87f5fd5ed1005834.
- Inner .95 installer SHA-256: 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625.
- 75 files; ZIP CRC PASS; single plugin root.
- WPCS: 0 errors, 28 warnings, 1 fixable finding.
- Semgrep: 0 findings and 0 scanner errors; focused security review covered 48 PHP targets.
- Receipt status is local package pass only; target-host and data acceptance remain separate.

Preserve .95's engineering quality practices while retaining the larger operational feature set in .13.

## 6. 16.3.0 HRX and other source lines

16.3.0 is a side HRX replacement candidate built from .95, not the cumulative successor. Candidate workflow #41733684055 / artifact #13276687926. Its receipt labels it INTEGRATION_CANDIDATE, cumulative_core_update=false, production_ready=false, with 47 proposed HRX tables, schema changes and legacy HR runtime disabled. It omits .9 POS/cart and CSS 07–12; .13 contains additional later-only resources and templates.

PR #48 is now merged into main as source/docs/HRX changes (merge commit c1fdf5bf3ece55b3810ec3bfc847a502ce68d006); that merge does not certify the .3.0 candidate ZIP as a production release or approve the HRX schema.

PR #49 (16.1.101) remains closed/unmerged. Addendum B says the 16.2.13 SOP library contains 24 PHP payload files, manifest status DRAFT_NOT_APPROVED, version 1.0-DRAFT, language am-ET; payload SHA values match the DOCX hashes in the 16.1.101 pack. The content exists in two encodings but is draft and owner-approval-only, not statutory/legal authority.

## 7. 16.2.13 source safeguards and missing release metadata

The 16.2.13 archaeology recorded:

- uninstall.php preserves wp_stock_* tables and options. Keep this safeguard.
- Stockland_Master_Import_Template.xlsx and Stockland_Physical_Count_Template.xlsx are bundled; preserve and test them.
- .12/.13 do not contain README or RELEASE_MANIFEST; release identity is only in the plugin header/runtime constant. Add a proper readme, release manifest, builder and build receipt to the cumulative successor.
- Synthetic-payroll path is still referenced 15 times under includes/*.php. Isolate/remove synthetic behavior from production runtime; keep synthetic fixtures in test tooling only.
- class-stockland-schema-contract.php describes a fixed 86-table base (49 + 37 HR/payroll), while the .9 receipt says the candidate schema change is not approved. Read the actual live schema/table inventory in read-only mode; do not infer live table count from source code or activate HRX. An approved source-mapped HR ERD, data reconciliation and migration/rollback evidence are required before schema changes.

## 8. Corrected Codespaces instructions

1. Treat 16.2.13 as the designated operational upgrade baseline; recover exact source/ZIP from Owner/Codespaces/local archive and compare to the historical hash. If unavailable, request it before attempting to reproduce the exact baseline. The historical hash is valid evidence from 6 November, but this review cannot rehash unavailable bytes.
2. Keep 16.1.95 unchanged as rollback/reference and quality benchmark.
3. Verify .9→.13 cumulative lineage; preserve .13 POS/cart, CSS 07–13, SOP payloads, import/count templates and uninstall data-preservation logic.
4. Compare PR #61 corrections to .13. Prove whether .13 already contains DRAFT approval/NULL pension protections; port only if absent and compatible, then test fresh install and upgrade.
5. Do not use the .95-pinned .9 builder to generate the final operational replacement. Adapt a reproducible build path from exact .13 source plus selected fixes.
6. Keep HRX schema unapproved pending actual schema inventory, approved ERD, migration/rollback and Owner/HR/Finance acceptance.
7. Review SOP content and owner approvals; do not delete the actual .13/16.1.101 resources.
8. Resolve synthetic payroll runtime references, add missing release manifest/readme/build provenance, and ensure no test fixtures or fake payroll can affect production.
9. Commit each coherent workstream incrementally; run exact-SHA CI and isolated WordPress/MariaDB functional/regression tests; fix findings and rerun.
10. Test migrations, POS/inventory/finance/HR/payroll, restore and rollback only in isolated sanitized environments, never against live business data.
11. Verify live installed file parity and actual DB table inventory read-only. Prepare an in-place update package only after complete acceptance and explicit Owner approval.

## 9. Repository status checked for this correction

- main: 339d519f5597c5c9b1efd45c5eb376216d976703.
- PR #39 merged (16.2.9 HR/payroll source), merge bd6c78f180e3bc6871317aa32c1f11d4fab8a427.
- PR #48 merged (HRX source/docs), merge c1fdf5bf3ece55b3810ec3bfc847a502ce68d006.
- PR #49 closed, unmerged (16.1.101 staging/SOP line).
- PR #55 merged (owner incremental-commit/history reconciliation directives), merge 91a8399c79746622cb836f6fc301d05b2b0655db.
- PR #61 merged (implicit approval/pension-default fix), merge ad127ae8f672243b4d5bd7bd703e6104beef995a.

**Final decision:** 16.2.13 is the designated operational upgrade base. 16.1.95 is the protected rollback reference. 16.2.9 is the confirmed feature ancestor and source of HR/payroll work. PR #61 supplies important corrective behavior that must be checked against .13 and ported only if missing. 16.3.0 remains a selective HRX source, not a wholesale replacement. Build one cumulative production successor from exact .13 source and preserve its later work.
