# COMMIT\_RELEASE\_PLAN

**Proposed work branch:** `feat/apparel-supply-chain-foundation-2026-11-15`\
**Proposed merge base:** `fe937ea97123a243c410696e4007c48d3332803d`\
**Branch/commits/releases created:** No — canonical repo connection has `push:false`; no repository-create action is available. This is a commit-ready plan, not a false commit receipt.

## 1. Commit stack

| Commit | Intended subject                                              | Included scope                                                                                                      | Required acceptance before next commit                                                    |
| -----: | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
|      1 | `docs(apparel): define product scope and repository evidence` | Main README, first-four repository evidence, baseline/gap decisions, protected source invariants                    | Source links/HEAD pins, no copied code/CSS, decision consistency and owner requirements   |
|      2 | `docs(apparel): specify enterprise garment lifecycle`         | Design brief, technical flats, pattern, grading, tech pack, samples, buyer order, costing, sourcing and procurement | Every workflow stage has owner/input/gate/output/failure states                           |
|      3 | `docs(apparel): specify cutting marker and AI copilot`        | CAD geometry, marker constraints, deterministic solver boundary, AI tools, human approvals, safety/evaluation       | AI forbidden-action tests and geometry rule contracts reviewed by pattern/cutting experts |
|      4 | `docs(apparel): define MES quality warehouse and fulfillment` | Cutting tickets, sewing/WIP, special processes, QAM/SQC, finishing, packing/cartons, WMS/OMS/returns                | Quantity reconciliation and hold/release gates cover failure/partial/rework paths         |
|      5 | `feat(apparel): add versioned domain event and JSON schemas`  | JSON Schema/OpenAPI/event contracts, validation fixtures and contract tests                                         | Schema parsing, compatibility checks, examples, version rules and tenant scope            |
|      6 | `docs(apparel): define tenant security and app boundaries`    | Tenant isolation, HR/Finance separation, permissions, data classification, migration gates                          | Threat model, role deny matrix and cross-app data minimization reviewed                   |
|      7 | `test(apparel): add synthetic end-to-end acceptance plan`     | Scenario matrix, reconciliation formulae, regression gate mapping                                                   | All target profiles and negative-path tests specified; no production fixtures             |
|      8 | `chore(codespaces): document apparel workspace handoff`       | Reuse existing `.devcontainer`, toolchain lock, preflight, patch/release instructions                               | No duplicate container/config, no secret, no live service command in the prep target      |
|      9 | `build(apparel): prepare review bundle and provenance`        | Release folder, workspace file manifest, patch hash and review-only ZIP                                             | Exact SHA-256 and ZIP CRC verified; no release/certification claim                        |

## 2. Runtime implementation sequence after design approval

| Stage | Domain increment                                           | Protected dependency                                                  |
| ----- | ---------------------------------------------------------- | --------------------------------------------------------------------- |
| A0    | Exact source provenance / tree comparison / schema map     | Current delivery branch and 49-key boundary                           |
| A1    | Tenant/location purpose and company config adapter         | Preserve current Stockland IDs/names/history                          |
| A2    | Style/season/collection/garment metadata                   | Existing Product Master STYLE/VARIANT remains source of truth         |
| A3    | Design Studio / tech-pack / sample workflow                | Approved style and sample revisions                                   |
| A4    | Pattern drafting/grade/CAD adapters                        | Canonical geometry and file safety                                    |
| A5    | Deterministic marker planner and Cutting Copilot interface | Approved pattern, fabric roll/QC, order ratio and human release gates |
| A6    | Costing / buyer order / demand / MRP                       | Approved BOM/routing snapshots                                        |
| A7    | Sourcing / material procurement / inbound QC               | Existing PO/GRN/AP interfaces and single inventory ledger             |
| A8    | Raw material WMS / reservations / issue                    | Existing ledger, no duplicate balance                                 |
| A9    | Cutting/lay/bundle execution                               | Marker approval, material issue and cut reconciliation                |
| A10   | Sewing MES/SFC / WIP                                       | Routing, operation event, skills and capacity                         |
| A11   | QAM/SQC / CAPA / quality release                           | All held/rejected stock blocked from sale/dispatch                    |
| A12   | Finishing/packing/cartonization/FG WMS                     | QC release and carton content reconciliation                          |
| A13   | OMS/B2B/retail/eCommerce/dispatch                          | Existing POS/wholesale compatibility and Finance events               |
| A14   | Independent Finance deployment extraction                  | Finance authority over GL/AP/AR/COGM                                  |
| A15   | Independent HR & Workforce extraction                      | HR authority over employee/payroll/legal packs                        |
| A16   | Tenant platform/entitlements/operations                    | Complete tenant/data/file/cache/queue isolation                       |

## 3. Branch/review rules

* Use one feature branch per coherent target. Never commit to `main` or mutate a production branch directly.
* Before creating the branch, refresh PR #134 and the delivery branch. PR #134 is currently open on `fe937ea…`; avoid basing work on its unmerged head until it is accepted or explicitly stack/rebase with its owner.
* Commit complete source files and tests; never commit a ZIP extracted from another repository as application code.
* Use the existing repository's `.devcontainer`, `scripts/codespaces`, `ci/release` and release builder. Do not invent a second Codespace or new build pipeline.
* Each commit must have target files, acceptance command(s), source/base commit and test status written in Notion/GitHub.
* A failed required check stops release; no weakening assertions, changing expected outputs or replacing source checks with a success string.

## 4. Release progression

{% stepper %}
{% step %}
## WORKSPACE\_REVIEW\_ONLY

Current folder/package; docs/contracts prepared, no runtime execution.
{% endstep %}

{% step %}
## FEATURE\_BRANCH

Source changes are committed but not merged.
{% endstep %}

{% step %}
## PR\_REVIEWED

Exact source diff and human/Codex review completed; unresolved findings closed or accepted with reason.
{% endstep %}

{% step %}
## CI\_ACCEPTED

Targeted and cumulative required workflows pass on exact candidate commit.
{% endstep %}

{% step %}
## PACKAGE\_BUILT

Reproducible one-pack with exact commit, manifest, SBOM, artifact digest and sidecar.
{% endstep %}

{% step %}
## INSTALLER\_VERIFIED

Exact ZIP installed/updated in disposable WP with all required DB engine/runtime combinations and complete E2E/backup/restore.
{% endstep %}

{% step %}
## RELEASE\_APPROVED

Authorized Owner/business/legal decision, required statutory and operational evidence.
{% endstep %}

{% step %}
## PUBLISHED

GitHub Release/tag or accepted distribution artifact.
{% endstep %}

{% step %}
## DEPLOYED

Separately approved live deployment, backup and rollback receipts.
{% endstep %}
{% endstepper %}

Never skip states or report a later state based on earlier evidence. “SHA checksum” is provenance/integrity; it is not the same as source review, successful tests, statutory approval or certification.

## 5. Current access boundary

* Canonical repository `aistockland-web/Erp-version-16.1.89` is readable but current connector permissions report `push:false`.
* No `create_repository` action is exposed by the available GitHub connector. Therefore a new GitHub repository, remote branch, commit, PR or release cannot be created in this session.
* `stocklandethiopia/stockland-erp-project` is writable but is an older 16.1.78-era release-package repository explicitly reserved for verified releases; it is not a safe workspace for this new source line. Do not use it as a code fork.
* Current workspace files are prepared locally and can be imported into the canonical repository once a user with write permission creates the feature branch or updates the GitHub connection. No code is pushed to the wrong repo.
