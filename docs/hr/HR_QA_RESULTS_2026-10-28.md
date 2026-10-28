# Stockland HR/Payroll — Local Engineering Check Results (28 October 2026)

**Disposition: design/source-audit progress; not a production release.**

## Passed locally

1. **ERP 16.1.95 exact archive audit:** PASS. SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`; 1,102,405 bytes; 75 unique plugin files; 48 PHP files; one plugin root; 49 unique static DDL declarations split 29/16/4; four legacy HR tables correctly identified. Python audit script compiles.
2. **ERP 16.1.97 HR Resource Center candidate static audit:** PASS. SHA-256 `7822606ded2883d9d5faf8b58962f058c99320d64fcf2da50c9f140448adee30`; 2,408,034 bytes; 107 unique files; 80 PHP files; 30 resource catalog entries; all 30 embedded DOCX assets match catalog sizes and SHA-256, are ZIP/OOXML containers, and contain `word/document.xml`.
3. **PHP/JavaScript syntax:** PASS on local PHP 8.4.24 and Node 22. Baseline PHP lint 48/48; 16.1.97 candidate PHP lint 80/80; both packages' `assets/app.js` and `assets/pwa.js` parse. Both local Python audit scripts compile.
4. **Candidate source-flow assertions:** PASS for resource library include, HR controller construction, registered catalog/download routes, server-side `can_view_hr` checks, allow-listed asset path/hash validation, audit fail-closed condition, UI catalogue loading, button event binding, fetch-to-Blob download path, and error feedback.
5. **Scope/canonicalization:** PASS — current Notion v0.5 is used as authority; old 27-table and 14–16-table proposals are explicitly not implementation instructions. No target HR table count is frozen.

## Repository CI observed

- Draft review PR #41: https://github.com/aistockland-web/Erp-version-16.1.89/pull/41
- Cumulative baseline release workflow run 40608541509: success for this documentation review commit.
- Exact installer validation workflow run 40608541452: success.
- The independent PHPCS recheck run was skipped; CodeQL/action-extraction and dependency-review steps may be skipped where this documentation-only PR has no runtime/dependency diff. The separate CSS/Semgrep corrective workstream in Issue #17 remains independent. Do not describe this as all repository gates globally green.

## Not tested / pending (do not call pass)

- Live production file hashes and source parity with the 16.1.97 candidate.
- Actual authenticated mobile/desktop browser download click-through and direct REST denial tests against the WordPress host.
- Complete PHP 8.3 runtime-candidate QA and the full runtime/security test suite for a new HR implementation.
- Full HR lifecycle runtime/E2E, target schema and migration, live database metadata/data-health, parallel payroll comparisons, external bank/remittance reconciliation, GL/PLEDGER balance and rollback.
- Current Ethiopian legal-source clause/effective-date review and independent labour/payroll/tax/pension adviser sign-off.
- Verified backup/restore, deployed source parity, Owner + HR + Finance acceptance and written production go/no-go.

## Safety boundary

Only source/archive inspection and documentation changes were made. No production WordPress site, database, employee salary/compensation, payroll history, GL/PLEDGER, or stock/business transactions were changed. The 16.1.97 package is resource-library-only; it is not the full HR/payroll replacement.
