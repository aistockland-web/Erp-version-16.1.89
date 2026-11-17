# FOUR\_REFERENCE\_CONTRIBUTION\_EXECUTION

**Baseline:** Stockland ERP 16.2.18, canonical source merge `fe937ea97123a243c410696e4007c48d3332803d`\
**Installer SHA-256:** `4a6bb09b4f5819873fff7f1417de20494c7d791003faf9a55649458458977021`\
**Research snapshot:** 15 November 2026\
**Disposition:** local implementation work package; no canonical push, production install or live database change.

This plan turns the four researched repositories into four specific Stockland contributions. Their source code, frameworks, UI assets, credentials and data are not copied. The contribution is the independently reimplemented business behavior, expressed in Stockland's current PHP/WordPress REST, authorization, audit, workflow and ledger conventions.

## Executive decision

| Contributor                     | Adopted best practice                                                                                                           | Stockland target                             | Existing foundation                                                                                                           | Remaining implementation                                                                                                                           |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Euro Suits ERP**              | Version-controlled tech pack, sample stage gates, independent review, pattern/grade/material references                         | PLM, style revision and sample workflow      | Product Master `STYLE` identity; local PLM/tech-pack candidate code below                                                     | Fit-session persistence, sample attachments/measurement observations, pattern revision binding and full UI/E2E acceptance                          |
| **Production Quality ERP V2.0** | Factory/line/style/hour context, SMV, target vs actual, manpower, WIP and defect capture                                        | Optional MES/SFC + QAM                       | Existing Product Master and proposed tech-pack routing/standard minutes                                                       | Production-order execution, immutable operation events, hourly board, derived WIP and quality/CAPA transitions                                     |
| **Thread ERP / garment-erp**    | Transaction-scoped picker task, pick logs, explicit short-pick reason, barcode gate and exact-once stock posting                | Existing Warehouse/Requisition/Transfer flow | `Stockland_REST_Requisition_V16`, transfer/reservation services, `Stockland_Ledger`, `Stockland_Audit`                        | Line-level scan/pick history and manifest gate verification; extend current flows rather than create another inventory engine                      |
| **T-VERSE**                     | External order intake, source-header mapping, channel SKU aliases, bundle decomposition, reserve/pick/pack/dispatch and returns | Optional OMS/channel adapters                | `product_code_maps`, `Stockland_DB` external-code resolution, import profile/header mapping, existing reservations and ledger | Separate channel-order staging/validation and conversion into an approved Stockland order; channel imports must not masquerade as Daily POS Import |

BengalTex remains a fifth broad process cross-check only; it is not part of the requested four-contributor implementation set.

## 1. Euro Suits — PLM and sample engineering

### Adopt

* Every style revision is immutable once approved and carries a content checksum.
* A tech pack binds measurement points, size grading, colorways, BOM materials, operation routing, care labels and packaging notes.
* Sample stages are explicit: prototype → fit → size set → pre-production.
* Fit observations and buyer/factory comments point to the exact style and tech-pack revision.
* Submission and approval are separate commands. The submitter cannot approve their own revision; return/reject requires a reason.
* Approved snapshots cannot be reopened or overwritten. Corrections create a new revision.

### Stockland implementation boundary

The stable Product Master `STYLE` product remains the identity anchor. Variant/SKU generation remains owned by the existing Product Master. PLM must not create a second product catalogue or change active variant IDs. The draft PLM schema is independently versioned and additive; installation is Owner-controlled, backup-confirmed and requires verified style references. It is never installed during plugin boot or ordinary activation.

### Local code progress

The code-only Euro Suits adaptation was applied to a disposable copy of the exact 16.2.18 installer. Its 88 PLM/module contract assertions passed, 39 module-catalog assertions passed after updating one stale expected runtime-status string, and all 87 PHP files linted. The patch deliberately excludes conflicting `GO_LIVE_RUNBOOK.md`, `README.md` and `RELEASE_MANIFEST.json` hunks. This is a locally verified source overlay, not a release or a merged PR.

## 2. Production Quality ERP — factory-floor measurement and WIP

### Adopt

* Every operation is contextualized by factory, line, production order, style, color/size, operation and routing revision.
* Hourly records store target, input, good output, reject, rework, headcount and available minutes with event actor/time.
* SMV is pinned to the approved routing revision. The board derives target/efficiency; users do not submit a free-form efficiency percentage.
* WIP is derived from accepted operation input/output events. Summary tiles are read models, not the authoritative quantity ledger.
* Defects and rework are linked to quality inspection, disposition and CAPA evidence.

### Stockland implementation boundary

MES/SFC is optional and enabled only for a customer operating a factory. It consumes an approved production order and frozen tech-pack/routing revision. It emits immutable production events; it does not write HR salary/payroll, calculate worker wages, or post GL journals. The proposed efficiency measure is `earned_minutes / available_minutes × 100`, where earned minutes are based on accepted good units multiplied by the frozen standard minutes for the operation. The exact formula, break exclusions and rounding must be versioned and approved before dashboards are enabled.

## 3. Thread ERP — warehouse picking and gate verification

### Adopt

* Assign a picker to a specific approved transfer/requisition line set.
* Record per-line scans and picked quantities; a short pick must include a controlled reason.
* Revalidate location, role scope, product variant and remaining allocatable quantity inside the transaction.
* Gate verification compares scanned carton/order contents with the frozen dispatch manifest, not merely whether a barcode exists.
* Replayed scan/dispatch requests are idempotent. Stock is posted once, at the existing authorized dispatch/receipt transition.

### Stockland implementation boundary

Keep the current reservation, transfer and requisition authorities, and use the existing `Stockland_Ledger` as the sole stock ledger. Do not create Thread ERP's MongoDB/session architecture or duplicate physical/reserved balance tables. A scan event is operational evidence, not a stock movement. Dispatch remains blocked if the manifest does not reconcile. Any new durable pick-event table must be proposed as a separately reviewed additive schema; it is not part of the 49-table core and is not created by this work package.

## 4. T-VERSE — channel order intake and fulfillment

### Adopt

* External source account + external order ID + stable idempotency key identify an import.
* External headers are mapped explicitly; ambiguous mappings are blocked.
* External SKU aliases resolve only through the authorized existing product-code map to an active Product Master variant.
* Bundles are decomposed to validated component variants and quantities before availability checks.
* Imported channel orders remain staged until mapping, customer/location, currency, price and quantity checks pass.
* Accepted orders then use Stockland's existing order/reservation/pick/pack/dispatch path. Returns reference the original shipment and require inspection/disposition.

### Stockland implementation boundary

This is a separate OMS/channel adapter, not a replacement for Daily POS Import. Importing an external order does not create a POS sale, post revenue, deduct inventory or write the GL. The adapter only stages and validates. A separately authorized conversion command creates a Stockland order. Inventory is committed only through existing ledger-backed shipment/dispatch commands. No Bangladesh, foreign-tax or marketplace fee default is inferred.

## 5. Common engineering contract

{% stepper %}
{% step %}
## Keep the canonical source base

Keep the canonical 16.2.18 package as the source base. Reconcile any candidate against the exact commit and ZIP hash before changing source.
{% endstep %}

{% step %}
## Protect existing operational records and authorities

Keep the existing 49 operational table keys, live records, role scopes, identifiers, Product Master, reservations, stock ledger, posted sales, finance and audit history byte-for-byte protected unless a separately approved migration explicitly changes an additive domain.
{% endstep %}

{% step %}
## Enforce command controls

Every state-changing command checks `Stockland_Authority` server-side, asserts location/company scope, uses `Stockland_DB`, records `Stockland_Workflow`/`Stockland_Audit` evidence and uses an idempotency key.
{% endstep %}

{% step %}
## Avoid duplicate core engines

No duplicate inventory ledger, Product Master, HR payroll engine, Finance journal or tax calculation.
{% endstep %}

{% step %}
## Govern optional schemas

New optional domains use namespaced schemas and explicit Owner approval, exact DDL signature, backup confirmation and rollback plan before schema creation.
{% endstep %}

{% step %}
## Bind UI to real commands

UI buttons are bound to real REST commands, with pending/error/success states and a refresh from persisted records. No demo/static actions.
{% endstep %}

{% step %}
## Build incrementally

Build and test one small changeset at a time. Run the full existing exact-source regression suite after every code increment.
{% endstep %}
{% endstepper %}

## 6. Build order

| Order | Changeset          | Deliverable                                                     | Exit gate                                                                                                           |
| ----: | ------------------ | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
|     1 | Euro Suits         | PLM tech-pack revision + sample review/fit contracts            | immutable revision, independent approval, no automatic DDL, Product Master identity preserved                       |
|     2 | Production Quality | MES/SFC hourly events + derived WIP/quality                     | event idempotency, frozen SMV/routing, output reconciliation, no payroll/GL writes                                  |
|     3 | Thread ERP         | picker sessions, short-pick reasons, scan log and gate manifest | role/location scope, scan replay protection, manifest equality and exactly-once existing ledger posting             |
|     4 | T-VERSE            | channel order staging, SKU aliases, bundles and OMS handoff     | duplicate import rejection, unknown SKU quarantine, bundle math, no POS/GL/stock write before authorized conversion |

The sequence is dependency-aware: a production order must pin an approved style/tech-pack/routing revision; warehouse fulfillment must consume authorized finished-goods quantities; channel orders must resolve to Product Master variants before allocation.

## 7. Verification and current gaps

* 16.2.18 source base ZIP checksum verified locally: `4a6bb09b4f5819873fff7f1417de20494c7d791003faf9a55649458458977021`.
* Euro Suits PLM code-only overlay: 88 contract assertions PASS; 39 module catalog assertions PASS; PHP syntax 87/87 PASS.
* The broader four-source work remains a development work package. The new JSON schemas test contract shape only; they do not make production features live.
* Production Quality MES, Thread scan/gate enhancement and T-VERSE channel OMS are not yet PHP runtime implementations.
* No source was pushed to canonical GitHub; no PR, migration, production install, live transaction or release certificate was created.

## 8. Exact next implementation tasks

* Resolve and rewrite the three PLM documentation conflicts against the current 16.2.18 runbook, README and release manifest; preserve all unrelated newer content.
* Add sample-fit session persistence only after the fit/sample lifecycle contract and schema ownership are reviewed.
* Implement MES/SFC operation event service and hourly read model behind a factory capability gate.
* Extend current warehouse action flow with line-level scan event and a frozen gate manifest; reuse current reservation/ledger semantics.
* Add external channel order staging as a new OMS adapter using existing `product_code_maps`; do not alter Daily POS Import semantics.
