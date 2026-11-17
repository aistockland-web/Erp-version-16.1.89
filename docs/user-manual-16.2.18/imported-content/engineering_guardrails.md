# ENGINEERING\_GUARDRAILS

**State:** governing target for future implementation branches. It does not authorize production deployment or live data changes.

## 1. Source ownership and update policy

* Canonical source repository: `aistockland-web/Erp-version-16.1.89`.
* Current implementation base for this workspace: delivery branch commit `fe937ea97123a243c410696e4007c48d3332803d` (PR #133 merged).
* PR #134 (`676d34b420b51fa386b10b36242bb8def3f3bb6f`) is an unmerged accessibility-only candidate and is not included in this workspace's base or proposed changes.
* The uploaded 16.2.18 ZIP SHA-256 is `aee5c551bd3367717091de066bfd5f86d8298dda1f6361d7534813d8087cd54e`; its embedded manifest did not bind that ZIP to a canonical Git commit. Do not use its filename/version as commit identity.
* The current canonical repository connection is read-only (`push:false`). The workspace therefore contains local, review-ready documentation and contracts. It is not represented as a remote GitHub repository, commit, PR or release.

### Before a future source edit

{% stepper %}
{% step %}
## Refresh the canonical repository and delivery branch

Fetch current PRs, reviews, issues, Actions jobs/logs and artifacts.
{% endstep %}

{% step %}
## Confirm the implementation base

Confirm whether PR #134 or another newer PR has merged. Select the exact resulting base SHA; do not cherry-pick around an unreviewed UI/style PR without comparing its behavior and asset manifests.
{% endstep %}

{% step %}
## Read required documentation and records

Read root `AGENTS.md`, current `src/README.md`, plugin README, release manifest, Codespaces docs, CAP, audit and Notion Chief Architect Hub.
{% endstep %}

{% step %}
## Verify source provenance

Verify source provenance before extracting an installer or overlaying files. Compare the exact per-file SHA-256 inventory; preserve source archive bytes.
{% endstep %}

{% step %}
## Create an isolated implementation copy

Make an isolated copy/feature branch and document the target file set, ownership, routes, data, authorization, migrations and rollback before changing code.
{% endstep %}
{% endstepper %}

## 2. CSS and frontend protection

The present ERP has 14 ordered Stockland CSS modules, an assembled `assets/app.css`, minified `app.min.css`, and a locally pinned Bootstrap 5.3.8 asset. They form an existing UI contract.

* This workspace adds **no CSS files, no JavaScript UI bundle, no Bootstrap, no copied icons, no theme package and no remote style dependency**.
* Future apparel screens must first reuse the existing Stockland shell, tokens, spacing, typography, components and responsive breakpoints.
* A feature may propose a visual delta only after it identifies the owning existing CSS module and provides a screenshot/DOM/test reason. New selector rules must be scoped, semantic, minimal and approved through the source PR. Do not paste arbitrary generated CSS into a new tail file.
* Never load CSS/JS from a public CDN or another ERP website. Use the repository's pinned local assets.
* Build `app.css`/`app.min.css` only through the existing module builder and compare their SHA-256 against the release manifest. No CSS regeneration or theme modification was performed while preparing this workspace.
* Accessibility requirements are additive acceptance tests, not permission to recolor every muted element. Test computed contrast and visible labels before making the smallest owning-module edit.

## 3. Code provenance and SHA policy

SHA-256 is used to identify exact bytes, not to prove safety, authorship, license, correctness or production readiness.

For every file imported or referenced:

* Record repository URL, branch/tag, exact commit, path, blob SHA (Git SHA-1 where supplied), byte length and SHA-256 of the downloaded file.
* Keep external repository files in an evidence register only. Do not copy or vendor their source, CSS, images, credentials, generated ZIPs, SQL dumps or demo records.
* If a later release updates a reference, compare old/new source bytes and document the behavior changed. A URL or branch name alone is not version pinning.
* Pin third-party runtime dependencies by exact version and lockfile hash; include license and vulnerability review before inclusion.
* Generate SHA-256 manifests for release artifacts and all files. The manifest must include source commit/tree, build workflow, artifact digest, inner ZIP hash, package file hashes and test receipt.
* A checksum match is package integrity only. It is not a code review, E2E pass, live certification or Owner release authorization.

## 4. Data and schema invariants

* Protect the 49 operational schema keys (29 Core/Operations +16 Finance/Procurement +4 legacy HR/payroll).
* The separate 37-table HR lifecycle extension remains Owner-approved and exact-fingerprint-controlled; HRX 33 recognition/preservation keys are not automatic DDL.
* Do not infer DDL approval from a total table count, health percentage, README statement or a schema-ready label.
* Apparel schemas are separate, additive, versioned, fingerprinted, reviewed, backed up and physically verified. They reference existing Product Master STYLE/VARIANT identities; they do not clone Product Master.
* Inventory mutations use the established stock ledger and movement services. No new authoritative stock-balance table or direct balance write.
* Preserve historical company, branch, warehouse, product, variant, ledger, journal, payroll, user, permission and audit IDs/records.
* No destructive migration, live schema write, real financial transaction, payroll run/disbursement or production deployment in a coding task without the separately required Owner decision.

## 5. Business authority and state machines

* Every material command checks tenant, legal entity, site, location, capability, role, object scope and current document state on the server.
* UI visibility is not authorization. A nonce is CSRF protection, not authority.
* All writes are idempotent and concurrency guarded; duplicate retries must not double-create events, reserve stock, issue material, complete an operation or post a journal.
* Posted records are immutable. Corrections use explicit reversal, return, rework, credit/debit or compensating documents.
* Maker/checker separation applies to tech pack, pattern, marker, quality disposition and release where configured. The creator cannot approve their own material decision.
* Reservation is not physical stock deduction. Issue and ship are separate ledger movements. Quarantined/rejected stock is not usable supply.
* A production operation cannot consume more accepted WIP than it has received; a downstream operation cannot complete without a valid predecessor handoff.
* Final QC release is a hard gate for sellable FG, carton sealing and dispatch.

## 6. Pattern, CAD and AI boundaries

* Preserve original CAD file bytes and SHA-256. Parsing/conversion produces a separate canonical geometry object and conversion report; never overwrite the original.
* Canonical geometry declares units, coordinate origin, scale, winding, holes, cut/seam boundaries, grainline, notches, drill marks, fold lines, pair/mirror, piece count and transformation history.
* A CAD adapter is enabled only after format rights, parser security, round-trip tolerance and actual customer file tests are approved.
* AI is advisory and may read only minimum authorized tenant/order/style/roll context. It can draft, explain, compare and request review; it cannot approve patterns, alter approved tech packs, reserve/issue stock, release cut tickets, post inventory or control a machine.
* Pattern geometry validation, marker constraints and material consumption are deterministic. AI output must pass those validators before a preview is displayed.
* An SVG/PDF is a preview. It is not a CAD/CAM machine file or cut command unless an approved, tested adapter and human release workflow exist.

## 7. Modular architecture and tests

* Continue the existing WordPress/PHP modular monolith for the current tenant. Node 22, Python and Composer are engineering tools; do not add a second app server because an external repository uses another language.
* Apparel contexts use deep modules with small, documented interfaces: Design/PLM, Pattern, Samples, Costing/Merchandising, Sourcing, Planning/MRP, Production, Cutting, MES/SFC, Quality, WMS, Packing, OMS/Fulfillment and Traceability.
* HR and Finance remain independently owned target applications. No direct table writes between apps; use versioned API/event adapters and an outbox/deduplication contract.
* Each command must have positive, permission-deny, wrong-tenant/site, invalid-state, stale-version, duplicate/idempotency, concurrency, partial-write rollback, audit failure and retry tests.
* The package runs the pre-existing full regression suite plus targeted apparel acceptance. No test is marked PASS if it did not run.

## 8. Release labels

Use the exact status ladder in `release/README.md`:

1. workspace prepared
2. feature branch
3. reviewed PR
4. CI accepted
5. package built
6. disposable installer verified
7. Owner-approved
8. published
9. deployed

No skipped state. Do not use the word “certified” for a checksum or source-level test receipt.
