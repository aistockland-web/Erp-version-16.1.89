# Stockland Ethiopia ERP — Finalization Audit
**Audit date:** 3 November 2026  
**Project-wide engineering disposition:** **GO — continue the complete cumulative Stockland ERP through production completion.**  
**Scope of this 3 November assessment:** the specific 16.2.9 and 16.3.0 archive hashes below were not approved as the final cumulative installer at that time. This is an artifact-specific historical assessment, not a stop decision for the ERP as a whole, the protected master, or unaffected live operational modules. It is superseded for project direction by the Owner's 4 November Protected Master decision and 5 November incremental-completion directive.  
**Current main:** `bd6c78f180e3bc6871317aa32c1f11d4fab8a427` (merged PR #39).

This commit is an evidence record only. It does not change plugin code, live data, database schema or release state.

## Exact package identities

| Artifact | SHA-256 | Finding |
|---|---|---|
| 16.2.9 operational installer | `c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01` | 88 files / 55 PHP; 86-table schema provisional; extends legacy HR tables |
| 16.1.95 canonical baseline | `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625` | Frozen baseline |
| 16.3.0 HRX outer GitHub artifact (ID 13190307479) | `0bb3411dc80ea54ccedd3be9253d490e8089fcddd39f30ac0fc9a7c4e716bc10` | Downloaded and re-hashed |
| 16.3.0 HRX inner plugin ZIP | `93b7417f7bb40199b9c5a75cc212e1c5a9ad407f9018e5218e39e42bbf10e200` | 76 files / 49 PHP; receipt says `production_ready=false` |

PR #48 is still open/draft at head `f3fa41c072f7fb9b413eed1a0657f6b7257cb69b`. Exact-package workflow [#41549709592](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/41549709592) passed for that head. This is package/source CI—not production acceptance.

## Why 16.3.0 HRX is not the cumulative release

The builder log and receipt pin the 16.3.0 HRX build to the **16.1.95** installer. A direct package comparison against merged 16.2.9 shows 88 versus 76 files; 73 shared paths, 56 byte-identical and 17 changed; 15 paths removed and 3 added. The 16.3.0 artifact does not contain `includes/rest/class-rest-pos-cart.php` or CSS modules 07–12 present in 16.2.9. It is not a cumulative substitute without a new build from merged main and functional regression evidence.

The 16.3.0 manifest says baseline `16.1.94`, while the receipt's baseline SHA is the 16.1.95 hash above. Its payroll metadata still lists a 7% / 11% pension split and a named PAYE rule while the README says the package does not legally approve PAYE/pension rates. Reconcile these release metadata inconsistencies.

## HR architecture and data-preservation gates

- Notion HR Design Reset v0.5 says no final table count is approved; the early 14–16 table range is a planning estimate, not a frozen schema. A source-mapped ERD, dependencies and shared-service reuse decision must precede installation.
- 16.2.9 proposes 41 HR/payroll tables (including 37 new tables) and alters legacy `employee_compensation`, `payroll_periods` and `payroll_lines`. This conflicts with the instruction to preserve `employees`, `employee_compensation`, `payroll_periods` and `payroll_lines` untouched until migration, reconciliation and rollback are approved.
- 16.3.0 proposes 47 HRX tables but has `SCHEMA_DESIGN_APPROVED=false`; schema installation is blocked. This is a safe guard, but not an operational HR release. The HRX DDL still requires approved table/relationship/orphan design.
- The HRX code includes AES-256-GCM document encryption fixtures and a site-supplied keyring. The installer contains 0 DOCX and 0 PDF files; the approved 24 Amharic-first SOP/resource pack is not delivered in this ZIP.
- One HRX review finding remains open: in-kind payee quantity multiplied by approved unit valuation must reconcile to the request's declared ETB amount. Keep in-kind settlement disabled until proven.

## Exact CI and local static recheck

Run #41549709592 records 49/49 PHP lint on PHP 8.3 and 8.4, JavaScript/CSS checks, encryption fixtures and source-security assertions. PHPCS/WPCS did process all 49 PHP files, but **687 errors and 145 warnings** remain for style triage; the security/database triage reported zero unreviewed findings. A passing source scan does not substitute for live workflow acceptance.

This audit independently rechecked the downloaded inner ZIP: 76 unique members, one safe plugin root, CRC clean, PHP 49/49 on PHP 8.4.24, JavaScript 2/2 and JSON 3/3. The current execution did not have WP-CLI, MySQL/MariaDB or a PHP MySQL driver, so authenticated WordPress/MariaDB tests were not run here.

The separate user-supplied transcript reports a WordPress 7.1.3 / MariaDB 10.11.14 run on a 16.2.13 working copy (86 tables, 147 REST routes, 52/52 relational checks). It does not provide that ZIP's exact SHA/commit, the fixes were applied to a temporary unpacked tree, and the transcript stops before the full HR hiring-to-payroll lifecycle is executed. Its 120 calculator cases used an in-memory rule bundle, not a qualified legal approval; it is not evidence for the 16.3.0 package.

## Open acceptance gates

1. Build from exact merged main, retaining POS/cart, inventory, finance, ledger, reporting and CSS changes.
2. Approve the HR ERD/dependency map; keep all four legacy tables read-only until backup, reconciliation and rollback are proven.
3. Complete all HR lifecycle and role/location REST/UI tests, private-document download authorization and approved resource/SOP delivery.
4. Obtain qualified Ethiopian labour/tax/pension rule approval and contract evidence for the nine employees with missing compensation. Do not invent pay or rates.
5. Run two full payroll periods, recipient-level payments, remittances, balanced GL/PLEDGER and final settlement.
6. Complete POS, receiving, transfer, reservation, count, daily close, VAT/COGS, procurement/AP/AR/GL reconciliation, concurrency and injected-failure rollback.
7. Complete exact-host backup/restore/rollback and installed-source SHA parity.
8. Resolve PHPCS/style diagnostics and metadata inconsistencies; rerun exact-package CI on the final cumulative commit.
9. Obtain Owner, HR, Finance and qualified legal sign-off against the exact final ZIP SHA.

**Decision for the two archive hashes examined in this historical report:** neither archive was accepted as the final cumulative installer on 3 November. Keep that package-specific finding attached to those exact hashes only. **Project-wide engineering remains GO:** continue the complete ERP integration on the protected baseline, selectively integrate the approved HR/payroll source, and close all implementation findings. Existing authorized ERP operations are not globally disabled by a candidate-package review. A release decision applies to the exact final cumulative ZIP and its evidence; it must never be reused as a global operational status. See [16.1.95 Protected Master — HR/Payroll Integration Decision](https://app.notion.com/p/40d6db0f767e8140b744d32a043269f9), [16.3.0 Consolidation Evidence](https://app.notion.com/p/40c6db0f767e8115b6e2fc0b437d4a0a), and [HR Design Reset v0.5](https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d).
