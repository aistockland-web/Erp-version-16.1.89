# Stockland ERP 16.1.101 — QA report (STAGING candidate, NOT production-ready)
Date 2026-10-30 · Package STOCKLAND_ERP_16.1.101_HRX_HEALTH_FIX_STAGING.zip · sha256 2916d9ddd80f0174da8ccb8ab9b57f51050ed5aa3d65d210e6ea97283262b5d1 · 112 files · CRC pass
Base: 16.1.99 review ZIP (e6a647e9…bbdae) + two fixes below. Derived from 16.1.95, NOT from the deployed 16.1.98.

## Changes vs 16.1.99
1. HRX DDL replay fix: table definitions are emitted one column/key per line with MariaDB-reported integer widths. 16.1.99 failed any second HRX install/upgrade on MariaDB 10.11 (`Invalid default value for 'plan_year'`). Now replay = 0 ALTER statements.
2. ERP SOP Library is a top-level tab on HR & Payroll (was a hidden dropdown option). Works with or without the HRX schema installed. Duplicate list in the "schema not installed" view removed.

## Evidence (sandbox: WordPress 6.8.3, MariaDB 10.11.14, PHP 8.3.6, Chromium)
- Clean-DB flow from the final ZIP: plugin install/activate, setup 49/49, HRX 33/33 (82 tables), exact-49 contract PASS.
- Deep integrity scan: 7 resumable batches, complete, healthy, 0 unknown, empty ledger.
- 24/24 SOP DOCX downloads (valid ZIP/DOCX container) and audit-logged.
- Browser (desktop 1280 and mobile 390): SOP tab present, 24 items, download triggers, no JS errors, both with and without HRX schema.
- PHP lint all files, node --check app.js/pwa.js, manifest JSON valid.
- Quick-health items failing in the sandbox are host-specific (HTTP not SSL, PHP upload 2M, XML stream probe).

## Not done
- Exact deployed 16.1.98 package not available anywhere (repo has only 16.1.89–16.1.92 installers): this is not a cumulative update. Do not install over live 16.1.98 until merged onto the recovered package.
- Live `schema_contract_exact_49 = FAIL` root cause unconfirmed (need `schema_contract_details`).
- Not run: business transaction flows, role/permission/penetration tests, restore/rollback, two parallel payroll periods, Ethiopian labour/tax/pension legal sign-off. Statutory rules intentionally unseeded.
- CI: PR-run secret-scan (gitleaks) failure on the earlier PR is unexplained; logs not retrievable.
