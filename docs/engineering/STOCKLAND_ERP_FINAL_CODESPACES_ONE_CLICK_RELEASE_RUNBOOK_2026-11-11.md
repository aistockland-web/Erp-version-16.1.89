# Stockland ERP — Final Codespaces, Full ERP and One-Click Installer Runbook

**Owner objective:** one complete Stockland Ethiopia WordPress ERP/PWA, preserving the compatible work from 5 August–5 November 2026 and the November integration, delivered as one reproducible cumulative source line and one installable plugin ZIP.

**Canonical repository:** `aistockland-web/Erp-version-16.1.89`  
**Operational baseline:** 16.2.13  
**Selected review/update candidate:** 16.2.15  
**Working delivery branch:** `delivery/stockland-full-erp-completion-2026-11-11`

## 1. Single objective and decision hierarchy

The product objective is fixed. Keep one Stockland ERP. Do not create a demo, rewrite into a different framework, restart from an older release, split modules into competing plugins, or reduce the agreed business scope.

Use this decision order when historical records differ:
1. Latest explicit Owner decision in the controlling Notion record for that subject.
2. Exact current GitHub source, merged PR ancestry and current tests.
3. Earlier design/audit/branch records, retained as history and reconciled.
4. Chat summaries only as navigation aids; never as proof of code, package or approval.

Resolve conflicts by inspecting exact source and decision dates, writing the comparison, and implementing the authorized outcome. Do not hard-code disputed table counts or statutory rules.

## 2. Canonical sources and lineage

| Source | Role |
|---|---|
| `aistockland-web/Erp-version-16.1.89` | Canonical writable integration/source/release repository. |
| `stocklandethiopia/stockland-erp-project` | Historical upstream source, last observed at 16.1.78. Selective source/history reference; current connector permission may be read-only. |
| `salestocklandet-bot/stockland-erp-project` | Older bot mirror for history comparison only. |
| `aistockland-web/Erp` | Static UI preview only; design/CSS reference, not an ERP runtime. |

Exact package roles:
- 16.2.13 operational installer SHA-256: `74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13`.
- 16.2.15 original candidate SHA-256: `8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df`.
- 16.2.15 repair: one missing PHP opening tag is added by `ci/codespaces/repair_candidate_php_entrypoint.py` to a separate ZIP. The repaired artifact has a different SHA and must never be confused with the untouched original.
- 16.1.95 protected rollback/reference SHA-256: `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.
- 16.2.9 and HRX packages are selective source inputs only; never overlay the whole package without file-level review.

The latest previously checked PR #83 head was `cc2bde441022a0ee11df76756df22c10bdc6d910`, with environment workflow run `43000093678` and exact-package/WordPress E2E run `43000094786` successful. Refresh these remote refs and runs before relying on them.

## 3. Codespaces initial setup and verification

Preserve all current Codespace work before switching branches. Do not hard-reset, force-push, delete untracked work, or overwrite unmarked directories.

The container toolchain is PHP 8.3, PHP 8.3/8.4 CI, Node 22, Python 3.12, Composer, checksum-verified WP-CLI, GitHub CLI and Docker/Compose. The isolated DB matrix is MariaDB 10.11 and MySQL 8.0.46. WordPress preview ports 8080/8081/8082 are private; database port 3306 remains internal.

Run:

```bash
pwd
git remote -v
git status --short --branch
git rev-parse HEAD
git log -8 --oneline --decorate
php -v
php -m
composer --version
node --version
python3 --version
docker version
docker compose version
wp --info
```

Then:

```bash
bash .devcontainer/initialize-host.sh
bash .devcontainer/post-create.sh
git status --short --branch
```

The post-create procedure must:
1. Set up only local random credentials and isolated service directories.
2. Install locked CSS/PHPCS tools and verify WP-CLI checksum.
3. Generate or reuse the separately repaired .15 QA package and its own sidecar/lock; never modify the original candidate.
4. Validate package SHA, size, root, CRC, paths, version and PHP opening tags.
5. Extract and verify exact .13 files, then materialize the real editable app at `src/stockland-erp-app/` with provenance `src/.stockland-erp-app.source.json`.
6. Preserve existing marked source edits and refuse to replace unmarked pre-existing directories.
7. Bootstrap isolated .13 baseline and .13→repaired-.15 MariaDB WordPress sites.

The optional MySQL 8.0.46 route is:

```bash
bash scripts/codespaces/run_mysql8_parity.sh
```

Keep all test data synthetic, database ports private, and production credentials/backups out of the Codespace.

## 4. Immediate next technical target: setup wizard and schema E2E

The current successful PR #83 smoke target covers plugin install/activation/update and pre-schema REST route visibility. It does not prove the ERP setup wizard, business schema or workflow acceptance.

Proceed on isolated databases:
1. Install exact .13 baseline and complete the actual setup wizard in bounded/resumable API batches.
2. Record setup state before/after every batch; fail on stalled or incomplete physical work.
3. Capture SQL snapshot, physical tables, columns, defaults, nullability, indexes, unique keys, foreign keys, constraints and selected row counts.
4. On separate upgrade instances, complete .13 setup, snapshot first, update to the separately repaired .15 QA candidate, and verify row/reference continuity.
5. Repeat on MariaDB 10.11 and MySQL 8.0.46. Test fresh install, upgrade, repeat setup, dbDelta replay, idempotency, failure/retry, concurrency and rollback.
6. Reconcile the resulting physical schema with the latest explicit Owner decision and the current HR design/legacy-data plan. Preserve legacy rows and posted ledger history. No hard-coded 49/86/119 table count is migration authorization.
7. Keep exact SQL dumps, schema fingerprints, logs, hashes and non-secret receipts as ignored QA artifacts.

The current historical receipt showing 12 WordPress tables is only pre-schema bootstrap evidence. Do not treat it as ERP schema PASS.

## 5. Full integrated business acceptance matrix

Use the actual registered WordPress REST routes and existing Stockland domain services. Each workflow must be tested from UI/API through server authorization, database transaction, ledger/audit record and reports.

| Workstream | Required end-to-end behavior |
|---|---|
| Identity and shell | Login/logout/session, frontend-first ERP interface, Owner/admin boundaries, notifications, global search, navigation, module health. |
| Authorization | Roles, capabilities, object ownership, location/branch allow/deny, CSRF/nonces, sensitive field permissions, maker-checker and no self-approval. |
| Product master | Product/brand/category/gender/age/SKU/size/color/variant/barcode, uniqueness, import validation, edits and audit. |
| Warehouses | Central Warehouse plus Goro, Gerji, Mexico and Megenagna; User Warehouse Management, receiving, reservation, requisition, transfer dispatch/receipt, variance, adjustment, return, damage, count upload and reconciliation. |
| POS and wholesale | Multi-line cart, server-authoritative prices/stock/discounts, order/receipt, duplicate/retry, checkout, return/refund, wholesale direct from Central Warehouse, daily closing. |
| Procurement | Supplier/customer master, purchase request/order, receipt, invoice, approval, balance and document history. |
| Finance | Expenses, AP, AR, cash/bank, VAT/COGS, journal approval, balanced GL/PLEDGER, reconciliation, reversal/compensating events, period close and reports. |
| HR lifecycle | Workforce plan, recruitment/selection, contract/probation/onboarding, employee record/history, attendance/shift/overtime, leave, promotion/grade, effective-dated compensation/benefits, offboarding/final settlement. |
| Payroll | Period inputs, draft calculations, exception review, independent approval, payslip, payment batch, remittance, balanced GL posting, correction/reversal, reconciliation and period lock. Test two consecutive synthetic periods; preserve historic posted values. |
| HR privacy | Authenticated encryption at rest, external key configuration, key ID/rotation/recovery, document role/location/object scope, download audit, encrypted backup/restore. |
| SOP/resources | 24 Amharic-first SOPs, JD/contracts/policies, versioning, controlled upload/edit/save/preview/download and approvals. Preserve current draft/approved state. |
| Reports/PWA/UI | Reconciled dashboards, print/PDF/Excel, mobile/desktop/browser, keyboard/accessibility, responsive cart and finance/HR layouts, service-worker updates, offline-safe private data behavior. |

For every workflow include positive and negative permission cases, invalid data, insufficient stock/funds, duplicate request, replay, concurrency, partial failure, transaction rollback, recovery and immutable history checks. Assert that report values reconcile to the underlying ledger and stock balances.

## 6. HR and statutory payroll requirements

Implement the full lifecycle using the latest Owner-approved HR design. Earlier table-count drafts conflict; the agent must reconcile the newest explicit design with the source dependency map and current Notion HR decision before building or migrating schema.

The four legacy HR/payroll tables and all linked history must be protected until the approved design, source-to-table mapping, sanitized data health, backup/restore, migration, reconciliation and rollback are proven. Do not enable an unapproved HRX schema. Do not invent any employee or compensation record.

Statutory rules must be stored in a versioned, effective-dated rule registry with source instrument, article, official URL, document checksum, publication/effective dates, jurisdiction, calculation basis, exceptions, tests and reviewer/approval evidence. Require a qualified Ethiopian labour/payroll/tax reviewer and Owner/HR/Finance sign-off. If exact legal applicability is unresolved, allow draft preparation and exception review as designed, but do not finalize, pay, remit or post unsupported calculations. Synthetic test employees only.

## 7. Security, international engineering quality and operations

Apply WordPress coding standards and platform APIs; secure PHP, prepared SQL, capability and nonce checks; secure file uploads and authenticated encryption; least privilege; OWASP-aligned authorization/session/input/output controls; WCAG accessibility; internationalization; supported browser behavior; robust decimal/money calculations; transactional/idempotent operations; append-only audit and ledger history; secrets outside source; dependency locking and license review; SBOM; repeatable builds; observability without PII leakage; tested backup/restore and recovery.

Run:
- PHP lint on all packaged PHP with PHP 8.3 and 8.4.
- PHPCS/WPCS full report; JavaScript syntax; JSON; PostCSS/CSS-module order and minification equivalence.
- Semgrep/CodeQL or equivalent SAST, Gitleaks/secret scan, dependency vulnerability and license scan, SBOM.
- Archive SHA/sidecar/CRC/path/traversal/symlink/duplicate/root and manifest checks.
- WordPress clean install, activate, repeat activate, upgrade, REST/login/route registration and setup/migration E2E.
- MariaDB/MySQL matrix, role/location allow-deny, persisted transaction reconciliation, two payroll periods, vault encryption/key recovery, PWA/browser/device/accessibility/performance.
- Backup restore and exact rollback rehearsal.

No skipped or partial job becomes PASS. Link every result to exact commit, package hash and run/job IDs.

## 8. Incremental source and commit policy

The repository remains the durable engineering record. For each coherent target:
1. Link the Notion goal/finding.
2. Identify source files, dependencies, business rules, API/data permissions, schema impact, audit behavior, migration/rollback and tests.
3. Implement and add regression.
4. Run targeted and cumulative local tests.
5. Commit, push to a descriptive branch and open/update a focused PR.
6. Run exact-head CI, diagnose failures, fix root causes and rerun.
7. Update Notion with exact commit, PR, package/workflow evidence and open findings.
8. Continue.

Use actual connected GitHub write permission in the canonical repository; do not ask the Owner to approve every normal file edit or routine commit. Do not push directly to main or rewrite history.

## 9. One-click installer and final release package

The final deliverable is one self-contained WordPress plugin ZIP with one root folder, valid entry point/header, complete runtime source/assets/templates/resources, tested activation and in-place update path, no test dependencies, secrets, fixture records or unrelated plugins. It must install through the standard WordPress Plugins → Add New → Upload Plugin flow.

Build only from the final canonical source commit. Generate:
- release README and upgrade/migration guide;
- exact source commit/tree ID;
- RELEASE_MANIFEST.json and per-file SHA-256 inventory;
- dependency/license inventory and SBOM;
- build receipt with toolchain versions and build commands;
- exact workflow run/job IDs and full reports;
- ZIP byte size, CRC and independent SHA-256 sidecar;
- install/upgrade/rollback and acceptance evidence.

Publish the installer as a downloadable CI artifact for review. Create a formal GitHub Release/tag only after all technical and business acceptance gates pass and the Owner explicitly authorizes publication/deployment. SHA-256 is not a digital signature; sign only when a real authorized signing identity/key and verifiable chain exist.

## 10. Final definition of done

One canonical source line includes the best compatible implementation from the full available three-month history. One cumulative installer upgrades from the .13 operational baseline. The container creates the expected environment repeatably. All product modules function end-to-end with secure role/location controls and reconciled ledgers. Full-cycle HR/payroll and private resources are tested with approved statutory rules. All quality/security/database/browser/backup gates have exact evidence. One final ZIP, manifest, SBOM, build receipt and independent checksum are tied to the same source commit. Owner, Operations, Finance, HR and qualified legal/statutory acceptance are recorded. The standard WordPress upload/install procedure succeeds in a clean isolated staging site.

The delivery objective remains fixed. Problems are engineering findings to reproduce, fix, test and commit—not reasons to change the product scope.
