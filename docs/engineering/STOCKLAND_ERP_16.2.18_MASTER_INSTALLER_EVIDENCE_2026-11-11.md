# Stockland ERP 16.2.18 — exact-head candidate acceptance and release boundary

**Updated:** 21 November 2026  
**Canonical repository:** `aistockland-web/Erp-version-16.1.89`  
**Delivery branch:** `delivery/stockland-full-erp-completion-2026-11-11`  
**Candidate integration:** PR #151, merged as `27b43a87b7c9bdd423f767499e5b1ccfb0a17e13`  
**Status:** Candidate CI acceptance passed. This is not a formal GitHub Release, signed release certificate, production deployment, or live-host acceptance.

## 1. Source identity and reproducible build

The verified executable change set was PR #151 head `007f4deac253addc3eaf3a00dc915b4f365bc9c1`, merged into delivery commit `27b43a87b7c9bdd423f767499e5b1ccfb0a17e13`. The PR verification workflow used merge checkout `22c68e97bcf8ae797fb2abed12b3986a96a53bf9`, with base `b131d22147346c41ce43063cfac6a87b5fed0f51` and PR head `007f4deac253addc3eaf3a00dc915b4f365bc9c1`.

The installer builder writes the exact checkout SHA into `RELEASE_MANIFEST.json`, regenerates the file inventory, compares each packaged file's SHA-256, and fails if the manifest does not match the checkout. The one-pack builder is deterministic for the same source bytes.

The code-head verification run was [44894812183](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44894812183) — **SUCCESS**. It produced one-pack artifact #14702754382 for that executable source:
- Outer ZIP SHA-256: `1f1a291683fbbbdd2045aebb253ac51df4ec9663e7ac8d99ffc0ff23ea86bc76`.
- Embedded installer SHA-256: `2dcc4c0f3139faa301817b95ce77c75794401ce1a5513aca4441c8596de59414`.
- Outer ZIP size: 2,286,819 bytes; embedded installer size: 2,292,121 bytes.
- Embedded installer: 128 entries under one plugin root; manifest inventories 127 files and excludes itself. The inner checksum sidecar and outer ZIP integrity both verified.

The changelog, README and this evidence note were then corrected in documentation-only follow-up commits; no PHP, JavaScript, schema, or business-logic files were changed by that documentation pass. Those commits trigger a new exact-checkout build. **For the current downloadable package, use the newest successful one-pack artifact on the delivery branch, not the historical artifact ID above.** The embedded manifest is authoritative for that build's exact checkout and file hashes.

## 2. Exact-head candidate CI acceptance

The five WordPress/database lanes passed on run 44894812183:
- MariaDB 10.11 + PHP 8.3.
- MariaDB 11.4 + PHP 8.4.
- MariaDB 10.11 + PHP 8.4.
- MySQL 8 + PHP 8.4.
- MySQL 8 + PHP 8.3.

The lanes completed fresh-install checks, business workflow regressions, scratch-database backup/restore, browser acceptance and the upgrade-path suite assigned to each environment. The Windows branch-folder agent contract test passed. Additional exact-head validations passed:
- PHP 8.3/8.4 runtime and security gates: [run 44894812185](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44894812185).
- Fail-closed security gates on PHP 8.3/8.4: [run 44894812156](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44894812156).
- Codespaces/devcontainer and WordPress service validation: [run 44894812255](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44894812255).
- Local release/installer gates: [run 44894807804](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44894807804).

These are disposable CI tests. They are not production-host acceptance.

## 3. Management Intelligence lifecycle increment

The candidate adds:
- Campaign account-owner, internal-operator and optional influencer assignments.
- Campaign states DRAFT → PLANNED → PUBLISHED or CANCELLED. Planning requires valid dates and active owner/operator profiles. Publication requires an HTTPS post URL and explicit timestamp. Cancellation requires a reason.
- A campaign-linked content calendar with content type, planned time, operator, influencer and brief.
- Content states PLANNED → PUBLISHED or CANCELLED, with HTTPS publication evidence or cancellation reason.
- Deterministic content identifiers to reject identical retried submissions.
- Optimistic row locking and comparison of the expected stored record version before a status update; the immutable audit event records a SHA-256 pointer to the updated Core settings record.

The real WordPress REST/database E2E test passed on all five matrix lanes. It checks assignments, lifecycle transitions, duplicate retries, invalid HTTP publication URL rejection, HTTPS evidence/timestamp requirements, cancellation, content listing, audit-pointer integrity and no changes to sales/stock/expense/GL rows.

This feature is for planning and manual evidence capture only. No provider OAuth, credential/token storage, live provider analytics API, auto-posting, ERP ledger posting or inventory mutation is enabled.

## 4. MySQL 8 historical 16.2.13 boundary — Issue #76 remains OPEN / P0

Fresh 16.2.18 MySQL 8 installation passes. That is separate from the original historical baseline problem.

The pinned original 16.2.13 installer contains legacy DDL that declares `stock_balances.variant_id` nullable while including it in primary key `(location_id, variant_id)`. A literal fresh creation of that DDL on MySQL 8 fails with “All parts of a PRIMARY KEY must be NOT NULL” at setup batch 25.

To test the later in-place upgrade steps on disposable MySQL 8, `scripts/release/upgrade_e2e.sh` pre-creates `stock_balances` with valid physical MySQL primary-key semantics, verifies `variant_id IS NOT NULL` and the exact two-column primary key, and then proceeds with the historical plugin/upgrade test. This fixture tests the post-baseline upgrade and preservation path; it does **not** prove that an untouched 16.2.13 archive can create its original baseline on MySQL 8.

Accordingly:
- The fixture-based upgrade-path E2E passed in the matrix.
- The original 16.2.13 MySQL 8 bootstrap incompatibility remains an open P0.
- Do not report the original 16.2.13 fresh-bootstrap path as supported on MySQL 8, close Issue #76, or claim the historical issue solved from the fixture-based E2E.
- The pinned 16.2.13 archive and production stock data were not modified.

## 5. Payroll, statutory rules and SOP approval boundary

The production payroll release-rules class ships **no** current Ethiopian PAYE, employee-pension or allowance defaults. Its compatibility `ensure()` hook returns false and does not create or approve a rule. Payroll calculation, finalization and posting remain fail-closed until exact official instruments/effective dates, a qualified independent Ethiopian legal/payroll opinion and separate Owner ratification are attached to the same rule version. An Owner declaration alone is not an independent legal opinion.

The 24 embedded Amharic-first SOP resources passed byte/hash/container-integrity checks, but the controlled resource manifest marks them `DRAFT_NOT_APPROVED`. Owner, process-owner and applicable professional review/approval are still required. Packaging, download availability, editability and file integrity do not constitute approval.

The 49-table operational baseline is distinct from the separately governed 37-table HR lifecycle extension. HR lifecycle DDL and legacy-HR column changes remain subject to exact Owner approval, schema fingerprint, backup confirmation and written reason. No production schema migration was run.

## 6. Daily POS connector status

- Excel/CSV POS intake is available through the controlled import flow.
- The Windows folder agent is branch-scoped and stage-only; it does not auto-release or auto-post.
- A supplier/vendor POS API is **NOT CONNECTED**. Do not claim live API integration until the real provider protocol, credentials, authentication design, replay/idempotency and reconciliation tests are supplied and accepted.
- Branch Manager remains the final and sole business approval for staged POS daily-import release; upload itself is not approval.

## 7. Production boundary and remaining acceptance

No production WordPress host, Stockland business database, stock ledger, payroll, employee/customer records or live transactions were changed. No production install, activation, migration, data backfill or deployment occurred. CI environments were disposable.

Before production release, the organisation still needs the Issue #76 MySQL 8 legacy-baseline resolution, qualified statutory payroll review and Owner ratification, SOP approvals, HR lifecycle acceptance, real connector onboarding where required, and final host-specific backup/restore, keyring, access-control, browser/PWA/print and operational sign-off. A green CI candidate is not a substitute for those approvals or explicit production deployment authorization.
