# README

No commits have been created in this session. This file describes the intended small, reviewable commit sequence for a user-authorized canonical feature branch.

**Proposed branch:** `feat/apparel-supply-chain-foundation-2026-11-15`\
**Base:** current canonical delivery branch at `fe937ea97123a243c410696e4007c48d3332803d`.\
**Excluded:** unmerged PR #134 head `676d34b420b51fa386b10b36242bb8def3f3bb6f`; all external repository source/CSS; plugin runtime; migrations; production data.

{% stepper %}
{% step %}
## `docs(apparel): define commercial product and lifecycle contract`

**Paths:** `product/apparel-supply-chain/README.md`, `docs/APPAREL_PRODUCT_LIFECYCLE.md`, `docs/REFERENCE_REPOSITORIES_AND_ADOPTION.md`.

**Done:** product profiles, application ownership, outsourced vs owned manufacturing flows, full lifecycle, source caveats and current Stockland-vs-commercial capability distinction are documented.

**Checks:** Markdown lint, internal link/path check, source SHA references and wording check for no false implementation claims.
{% endstep %}

{% step %}
## `docs(apparel): specify design pattern and sample engineering`

**Paths:** design/pattern/sample docs and JSON schemas.

**Done:** design brief, technical flats, material development, pattern geometry, grade rules, measurement/fit, sample room, buyer approvals and design asset control documented.

**Checks:** JSON parse and 2020-12 metaschema; schema examples/negative fixtures; review by apparel design/pattern specialist before normative/production use.
{% endstep %}

{% step %}
## `docs(apparel): specify pre-cut marker copilot safety contract`

**Paths:** `docs/CUTTING_MARKER_AI_ASSISTANT.md`, `docs/AI_MODEL_EVALUATION.md`, `docs/PATTERN_GEOMETRY_AND_CAD_INTERCHANGE.md`.

**Done:** AI tool allowlist, deterministic marker validator, vector preview, evaluation dataset, fallback, privacy and human release gates documented.

**Checks:** threat-model review, prompt-injection cases, authority deny matrix, geometry fixtures and licensed CAD dialect review.
{% endstep %}

{% step %}
## `docs(apparel): define production quality and carton workflows`

**Paths:** MES/SFC/QAM/WMS/OMS docs and schemas.

**Done:** production order, cutting, bundle, sewing operation, WIP, rework/scrap, quality gates, special process, finishing, packing/cartonization, SSCC, warehouse and fulfillment contracts documented.

**Checks:** quantity reconciliation test cases, state-machine and traceability review, warehouse/quality role denial cases.
{% endstep %}

{% step %}
## `docs(apparel): define domain model and event contracts`

**Paths:** `docs/DOMAIN_MODEL_AND_EVENTS.md`, `docs/API_AND_COMMAND_CONTRACTS.md`, all integration schema files.

**Done:** domain ownership, unique source of truth, event envelope, idempotency, outbox/inbox, aggregate versioning, data keys and failure behavior documented.

**Checks:** schema compatibility/version tests, duplicate event and stale aggregate examples, Finance/HR decoupling review.
{% endstep %}

{% step %}
## `docs(apparel): define tenant, security and localization model`

**Paths:** architecture and security docs, standards registry.

**Done:** isolated tenant model, site/location-purpose structure, PII/privacy classes, localization and country packs, standards evidence registry and HR/Finance boundaries documented.

**Checks:** threat model, cross-tenant read/write/export/file tests designed, Ethiopian statutory review boundary.
{% endstep %}

{% step %}
## `test(apparel): add schema contract fixtures and static QA`

**Paths:** schema test harness and synthetic fixtures only.

**Done:** machine-readable schema fixtures test positive/negative paths; no database or WordPress runtime mutation.

**Checks:** all schemas parse and metaschema validate; resolver check; fixture validation and invalid-case assertions.
{% endstep %}

{% step %}
## `docs(apparel): add acceptance roadmap and release evidence`

**Paths:** acceptance matrix, CAP work packages, Codespaces/release docs, manifest templates.

**Done:** each work package has source, roles, states, data, dependencies, acceptance, rollback and evidence expectations.

**Checks:** dependency graph order, source/target coverage, release status truthfulness and file hash receipt.
{% endstep %}

{% step %}
## `docs(apparel): reconcile source provenance and publish review PR`

**Paths:** Notion/GitHub references and aggregate docs as needed.

**Done:** exact current source base and any intervening PR merge state reconciled; reviewer request and all required checks attached.

**Checks:** exact branch/base comparison, diff scope limited to `product/apparel-supply-chain/`, CODEOWNERS/review, CI. Do not merge until required reviews and checks pass.
{% endstep %}
{% endstepper %}

## Follow-on runtime increments

Runtime implementation begins only after AP-00 exact source/data/schema and Product Master linkage review. Implement domain services incrementally:

1. Design/PLM
2. Sample room
3. Sourcing
4. Costing/orders
5. MRP
6. Material warehouse
7. Cutting
8. Sewing MES/SFC
9. QAM/SQC
10. Finishing/packing
11. WMS/OMS

Each runtime increment has its own additive schema fingerprint, API permission matrix, transactional/idempotent tests, cross-tenant tests where applicable, and a full regression on the current ERP.

The commit list is proposed, not an assertion that GitHub commits exist. Do not generate fake commit hashes in manifests or handoffs.
