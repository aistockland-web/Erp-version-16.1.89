# Stockland ERP 16.2.7 — SaaS remediation and commit plan
**Date:** 2026-10-30  
**Status:** isolated candidate / staging only; production is **NO-GO**.

## Source of truth
- Repository: `aistockland-web/Erp-version-16.1.89`
- Current implementation branch: `feature/hr-payroll-full-cycle-16-2-0`
- Candidate version reported by the deterministic builder: `16.2.7`
- Locked package input: 16.1.95 installer SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.
- The current workflow/overlay path retains `16_2_0` for pipeline continuity while the generated candidate version is 16.2.7. This naming mismatch must remain explicit in receipts and should be normalized in a later refactor without hiding the exact input/output lineage.

## Changes committed for this candidate
- Corrected the package test to detect the PHP method `function revise_resource(` rather than Python syntax `def revise_resource`. The failing assertion had blocked both PHP 8.3 and 8.4 jobs before package lint, CSS, POS, audit-chain and payroll tests.
- Replaced social platform text glyphs with local inline SVG geometry for Facebook, Instagram, TikTok and YouTube.
- Added scoped social SVG sizing, fixed box geometry, current-color strokes and no-fill rules to resist WordPress/theme SVG and font overrides.
- Added a package regression assertion so the old glyph implementation cannot silently return; added the SVG contract to the generated release manifest.
- No production deployment, live database operation, employee-data change or schema migration has been performed by these commits.

## Front-end design contract
- Use the local, SHA-256-pinned Bootstrap 5.3.8 asset. No remote CDN dependency.
- Keep the HR workspace two columns on desktop and one column on small screens. Use a concise heading/description per collapsible section; do not duplicate dashboard/navigation cards inside the workspace.
- Use consistent local inline SVGs for controls; preserve visible focus, keyboard operation, reduced-motion behavior, responsive touch targets, and icon labels.
- Social reporting must remain clearly disclosed as timestamped manual/export snapshots unless a separately approved real API integration exists.
- Social Media must ultimately be a distinct ERP navigation/module with its own capability checks. Separate accordions under HR alone do not satisfy this requirement.

## Document-control gap list (do not claim complete yet)
Current candidate source contains private upload/download, role-scoped listing, immutable new-version drafts, file hashes, independent maker-checker approval and atomic supersession. The next implementation slice must complete and test:
1. Document metadata preview with Issue Date, Prepared By, Reviewed By, Approved By, Version and Status. Keep sign-off fields blank until an authorized review/approval exists; do not fabricate reviewer identities.
2. Safe preview of supported PDF/image resources through a permission-checked endpoint; for formats not safely rendered in-browser, show a metadata preview and an authenticated download action.
3. Export the current permission-filtered document register to a formal CSV schema: Document ID, Document Type, Function, Role, Language, Version, Owner, Status, Issue Date, Prepared By, Reviewed By, Approved By, Updated At, Hash / Checksum, File Link. Never create public links to private-vault files.
4. Make “Edit / save new version”, independent approval, audit logging, history download and status visible in the actual document library workflow. A prior approved version must remain active until the new draft is approved.
5. Test private-file access, role scope, invalid IDs, path traversal, tampered hashes, download/preview audit failure, editor lock conflicts and exports.

## Release blockers carried forward
- CAP-03: authenticated target-host acceptance, verified database/uploads backup, restore and rollback receipt.
- CAP-04: nine active employee compensation records require authorized, effective-dated values; never autofill or guess.
- CAP-05/CAP-21: independent review of the current Ethiopian tax/pension rules and calculation basis.
- CAP-07: complete ETB, VAT, COGS, stock, AP/AR and general-ledger reconciliation.
- CAP-12/CAP-13/CAP-14: authorization/IDOR/concurrency, warehouse-to-sale-to-close and import replay/idempotency acceptance.
- CAP-16/CAP-26: complete read-only deep-integrity receipt; `SCAN_INCOMPLETE` is not PASS.
- CAP-22: completed HR-to-final-settlement lifecycle with payout/remittance evidence.
- CAP-28: exact installed artifact/source checksum and source parity.
- CAP-29: signed master SOP and end-to-end acceptance.
- Exact-candidate CI: all required jobs must run; a skipped job is not a pass.
- Architecture: 86-table HRX candidate inventory remains provisional. Do not override the historical 49-table contract or migrate/drop/rename tables before architecture approval and a tested additive migration plan.

## Ordered commit plan
1. **CI and responsive SVG correction** — the present focused source change; require exact-head PHP 8.3/8.4 package tests to continue beyond the former assertion.
2. **Document workflow** — preview, metadata, CSV export and fail-closed audit/download behavior, with backend capability tests and version-chain tests.
3. **SaaS navigation separation** — separate Social Media page/module, independent capability boundary, responsive cards, timestamped export reporting.
4. **Runtime QA** — install the exact artifact into a restored isolated WordPress/MariaDB clone; test actual user roles, mobile/keyboard/print behavior, POS/stock/ledger and HR operations.
5. **Release decision** — verify exact ZIP/hash/manifest/receipt and backup/restore; obtain Owner, HR, Finance and qualified local statutory sign-off before production promotion.

## Evidence links
- PR #39: https://github.com/aistockland-web/Erp-version-16.1.89/pull/39
- SaaS design and gates: https://github.com/aistockland-web/Erp-version-16.1.89/blob/feature/hr-payroll-full-cycle-16-2-0/docs/engineering/STOCKLAND_SAAS_ENGINEERING_BLUEPRINT_2026-10-29.md
- Notion ERP-wide audit: https://app.notion.com/p/4076db0f767e815eba7cc49314197c22
- Notion canonical release gates: https://app.notion.com/p/3fc6db0f767e81f2a3bbe644048054f9

## Verified exact-candidate rerun — 30 October 2026

- Workflow: [run 40962728289](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40962728289) — **SUCCESS**, commit `3548981c39dbbd23f1e7b74bc90517f43dac54c1`.
- PHP 8.3 job: success. PHP 8.4 job: success. The only intentionally skipped step was artifact publishing in the PHP 8.4 matrix job; the PHP 8.3 job published the candidate artifact.
- Candidate Actions artifact: `STOCKLAND-ERP-16.2.7-HR-PAYROLL-CANDIDATE`, artifact ID `12935933940`, Actions archive digest SHA-256 `17bce474e57c1cbd8b84114f2b6d895c6e78e318090ab9c1621ccc3fbff9c743`.
- Inner installable plugin ZIP: `STOCKLAND_ERP_16.2.7_HR_PAYROLL_CANDIDATE.zip`, SHA-256 `063d87578adc29308e0bd77611359ddd4a9a6bac0c1600daf29f1fc019bec4ea`; matches the included receipt's `installer_sha256`.
- Verified local downloaded artifact ZIP CRC and inner plugin ZIP CRC; both passed. Independent local `node --check` also passed for packaged `assets/app.js` and `assets/pwa.js`.
- CI passed package-level acceptance, PHP syntax across the installer, Node syntax, synthetic POS/VAT/COGS fixtures, PostCSS parsing of `app.css`, `app.min.css`, and `bootstrap.min.css`, pinned Bootstrap 5.3.8 hash, CSS-module/build parity, the synthetic audit-chain regression, the synthetic payroll-calculator contract, and the fail-closed/no-production-write receipt assertions.
- Candidate receipt explicitly says `schema_contract_approved=false` and `production_ready=false`. It records 37 new proposed tables and an 86-table inventory; that remains provisional and must not be applied as a production migration.

## Immediate next slice

The engineering build gate is now green for this exact candidate. Continue with authenticated, isolated WordPress/MariaDB staging acceptance, actual visual verification at desktop/mobile widths, private HR vault access/restore tests, complete document preview/metadata/CSV export, standalone Social Media navigation/capability separation, POS concurrency/retry/rollback tests, deep-inventory/accounting parity and the external Owner/HR/Finance/legal approvals. Do not activate this candidate on production based solely on CI.

## Exact project continuation — current source is 16.2.8 (30 October 2026)

### Current source identity and run results

- Current feature branch head before this handover update: `a4ca90a6e7790779aafbd2da026d41fc0c7c17e2`, commit message: “Fix cross-realm error assertion in synthetic POS cart test”.
- Current candidate workflow: [16.2.8 exact package run #40968188861](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40968188861) — success on PHP 8.3 and PHP 8.4.
- Cumulative baseline workflow: [16.1.95 gates #40968188860](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40968188860) — success including browser-based desktop Product Master, Reports retry, requisition approval and all-workarea responsive checks; CSS/PostCSS, package integrity, PHP/JS syntax, WPCS/security invariants, Semgrep and dependency/license checks passed.
- Enterprise security: [run #40968189006](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40968189006) — success for secret scanning, CodeQL JavaScript/TypeScript, CodeQL Actions and runtime dependency/license inventory.
- Local release-gate workflow: [run #40968188938](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/40968188938) — success. PR #39 remains open; no merge or production deployment has been performed.

### Exact candidate checksum and independent local verification

- GitHub Actions artifact `STOCKLAND-ERP-16.2.8-HR-PAYROLL-CANDIDATE`, artifact ID `12937788838`; outer artifact ZIP SHA-256 `18c2df52052040c4cd874cfa66883452a6858aeb9dce6081675061520dcfd2ed`.
- Installable inner plugin: `STOCKLAND_ERP_16.2.8_HR_PAYROLL_CANDIDATE.zip`; SHA-256 `3068747abced50000f593a7d5c8e86a55a1acdff6af75f54036badd71fa0b1f8`; 1,204,715 bytes.
- Receipt hash equals the independently calculated inner ZIP hash; both outer and inner ZIP CRC checks pass. The installer has 87 archive members under one `stockland-erp-app/` root, including 54 PHP files.
- Independent local checks: PHP 8.4 syntax 54/54 pass; Node syntax `assets/app.js` and `assets/pwa.js` pass; all 3 JSON files parse; 12 ordered CSS modules concatenate to `assets/app.css` according to the locked QA algorithm; Bootstrap 5.3.8 version/hash and CSS/PostCSS checks pass in exact-head CI.
- Release receipt values are authoritative: `production_ready=false`, `schema_contract_approved=false`, `new_tables=37`, `production_database_touched=false`, `wordpress_activated=false`.
- Artifact source baseline remains 16.1.95 SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`. The new candidate is therefore not proof of binary parity with the user's installed 16.2.6/site ZIP; exact installed-file parity remains CAP-28.

### Front-end / HR gaps verified directly in the 16.2.8 package

The present package improves the HR desktop/mobile layout, two-column desktop sections, mobile stacking, section search/accordion behavior, and inline SVG icon rendering. The package also includes private HR document upload, approval, a new-version editor, same-origin REST-nonce-authenticated downloads, and manual timestamped social campaign snapshots. The following user-requested items are **not yet complete** and must not be inferred from the passing static tests:

1. **Separate Social Media module:** 16.2.8 renders campaign/snapshot controls and official platform links as HR sections (“Influencer/social campaign planning & snapshots”, “Official social account access”). They still share the HR workspace/search and are not a distinct sidebar/navigation module with an independent capability surface.
2. **Document workflow:** there is no complete authorized PDF/image preview, no formal metadata footer/view with Issue Date / Prepared By / Reviewed By / Approved By / Version / Status, and no permission-filtered CSV register export using the agreed headers.
3. **Private-document audit:** verify the live download endpoint's audit-write failure behavior and role/approval matrix with a staging database and injected failures. Static route permission callbacks alone do not prove data-scope or audit behavior.
4. **Staging/runtime:** no authenticated target-host run, real private-vault mount, backup/restore/rollback drill, payroll payout/remittance E2E, live inventory/accounting reconciliation or browser/device acceptance against the deployed site was performed by these build jobs.

### CAP and statutory status

No CAP is closed solely because a candidate build is green. CAP-03 backup/restore/rollback; CAP-04 authorized values for the nine active employee compensation records; CAP-05/CAP-21 qualified current Ethiopian tax/pension review; CAP-07 accounting/inventory reconciliation; CAP-12/CAP-13/CAP-14 authorization/concurrency and business lifecycle; CAP-16/CAP-26 complete deep-integrity scan; CAP-22 HR/final-settlement evidence; CAP-28 exact deployed-source parity; and CAP-29 formal SOP/Owner acceptance remain release gates.

The receipt's 86-table model (29 core/operations + 16 accounting/procurement + 41 HR/payroll, including 37 proposed new tables) is provisional and not architecturally approved. Do not migrate or deploy it into production until the schema contract has a signed decision, an additive migration plan, historical-data reconciliation, tested rollback and independent stakeholder acceptance.

### Local handover files generated for this run

- Installable candidate ZIP: `STOCKLAND_ERP_16.2.8_HR_PAYROLL_CANDIDATE.zip`
- Checksum sidecar: `STOCKLAND_ERP_16.2.8_HR_PAYROLL_CANDIDATE.zip.sha256`
- Detailed validation handover: `STOCKLAND_ERP_16.2.8_VALIDATION_HANDOVER.md`

**Disposition:** the exact 16.2.8 candidate CI is green, but production is still **NO-GO**. Keep working on the feature branch and re-run exact-head CI after each implementation commit; do not merge or activate solely from static CI evidence.

