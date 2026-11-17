# REFERENCE\_REPOSITORIES\_AND\_ADOPTION

**Research snapshot:** 15 November 2026. Repository HEADs and selected source files were captured read-only. The four intended contributors are Euro Suits, Production Quality ERP V2.0, Thread ERP and T-VERSE. BengalTex is retained as an additional broad-process cross-check, not a fifth implementation contributor.

These repositories are process/architecture references. Their code, CSS, images, credentials, databases and demo data are not copied. Stockland behavior is independently implemented in the current WordPress/PHP architecture.

## The four contributors

| Contributor                     | Repository / captured `main` HEAD                                                                                                                        | Primary contribution                                                                                                                         | Stockland adoption                                                                                                     |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Euro Suits ERP**              | [ansu9932/euro-suits-erp-full-project](https://github.com/ansu9932/euro-suits-erp-full-project) · `f017b7ffd57e98174b10262390cbef6a982cd183`             | Style/sample lifecycle, pattern/sample stage controls, material references, independent approvals, revision and audit discipline             | PLM, immutable tech-pack revisions, sample fit/review lifecycle, pattern/grade linkage                                 |
| **Production Quality ERP V2.0** | [ratuldev47782/Production-Quality-ERP-V-2.0](https://github.com/ratuldev47782/Production-Quality-ERP-V-2.0) · `8dd6b25d2d6759f5776748fdf664e409476e1c46` | Factory/building/line/style/hour dimensions, SMV, manpower, target/actual, efficiency, WIP and defect capture                                | Optional MES/SFC, hourly operation events, derived WIP and quality/CAPA                                                |
| **Thread ERP / garment-erp**    | [alidevrunner1012/garment-erp](https://github.com/alidevrunner1012/garment-erp) · `6ac233e905bc278dd0a264e7694ec60f241f8597`                             | Transaction-scoped order reservation, picker queue, pick log, explicit short-pick reasons, barcode/gate verification and reservation release | Existing Requisition/Transfer/Reservation/Warehouse flow, scan evidence and frozen dispatch-manifest gate              |
| **T-VERSE**                     | [azad-727/Tverse](https://github.com/azad-727/Tverse) · `e3c245981dc753d90a5a021f26cfe047cf8eb452`                                                       | External marketplace order import, header mapping, channel SKU aliases, bundle decomposition, reservation, pick/pack/dispatch and returns    | Separate OMS/channel-order adapter using existing Stockland product-code maps, import mapping, reservations and ledger |

Commit dates at capture: Euro Suits 12 October 2026; Production Quality ERP 12 September 2026; Thread ERP 13 November 2026; T-VERSE 14 October 2026. A recent commit date does not prove maintenance, production reliability, security, license rights or feature completeness.

## Euro Suits — product lifecycle and sample control

### Inspected sources

* [Repository README](https://github.com/ansu9932/euro-suits-erp-full-project/blob/main/README.md)
* [Architecture](https://github.com/ansu9932/euro-suits-erp-full-project/blob/main/docs/ARCHITECTURE.md)
* [Sample stages](https://github.com/ansu9932/euro-suits-erp-full-project/blob/main/backend/internal/app/sample_stages.go)
* [Publication boundary](https://github.com/ansu9932/euro-suits-erp-full-project/blob/main/docs/PUBLICATION.md)

### Best practices adopted

* Explicit sample stages: prototype, fit, size set and pre-production.
* Style/specification revision linkage, buyer/factory feedback and traceable attachments.
* Independent reviewer controls; no self-approval; reasoned return/rejection.
* Approved technical baselines become immutable; corrections create a new revision.
* Pattern, grading, measurement, material and sample evidence remains linked to the exact style revision.

### Stockland decision

Use existing Product Master `STYLE` as the anchor; the existing Product Master remains the only owner of style/variant/SKU identity. Add separately versioned PLM/tech-pack and sample data only after exact source review and Owner approval. A mutable “latest” style spec must never silently change an order or production order already released against a prior revision.

{% hint style="warning" %}
The inspected repository snapshot does not prove that every public feature is active in its private production deployment. Treat it as a process/architecture reference, not a production certification.
{% endhint %}

## Production Quality ERP V2.0 — factory-floor execution

### Inspected sources

* [Repository README](https://github.com/ratuldev47782/Production-Quality-ERP-V-2.0)
* [Hourly production board](https://github.com/ratuldev47782/Production-Quality-ERP-V-2.0/blob/main/Frontend/app/ProductionComponents/LineDailyWorkingBoard.jsx)
* [Style WIP API](https://github.com/ratuldev47782/Production-Quality-ERP-V-2.0/blob/main/Frontend/app/api/style-wip/route.js)

### Best practices adopted

* Factory/building/line-scoped records connected to style, buyer, colour, SMV, manpower and shift/hour.
* Hourly target and actual, variance, efficiency, style capacity and line WIP.
* Quality defect recording tied to production context.
* Unique production event identity to prevent repeated hour/output entries.

### Stockland decision

MES/SFC is an optional capability for customers operating a factory. Every production event pins the production order, style, tech-pack/routing revision, operation, line and event actor. WIP derives from accepted input/output events; dashboard aggregates are read models. Define one versioned efficiency formula, its exclusions and rounding. Do not accept a user-entered efficiency number as authority and never derive payroll from this board.

{% hint style="warning" %}
The repository is a production/quality monitoring application, not a complete ERP covering PLM, procurement, WMS, retail, Finance or HR. Some quality metrics in its README are future scope, not demonstrated implementation.
{% endhint %}

## Thread ERP — picker and dispatch integrity

### Inspected sources

* [Repository README](https://github.com/alidevrunner1012/garment-erp)
* [Order service](https://github.com/alidevrunner1012/garment-erp/blob/main/backend/src/services/orderService.js)

### Best practices adopted

* Transaction-scoped reservation and picker assignment.
* Per-order/per-line pick logs and explicit short-pick reasons.
* Barcode verification against the intended order/variant.
* Reservation release on allowed cancellation and replay-safe stock mutation.

### Stockland decision

Reuse the current `Stockland_REST_Requisition_V16`, transfer/reservation services, `Stockland_Authority`, `Stockland_Workflow`, `Stockland_Audit` and the one authoritative `Stockland_Ledger`. Add picker/scan evidence and a frozen gate manifest, but do not create a second stock balance or ledger. A scan is evidence, not a movement. Dispatch remains blocked until the scanned manifest matches the expected variant/quantity lines. Stock is posted once through the existing authorized transition.

{% hint style="warning" %}
This repository is a smaller warehouse/order application, not a full garment manufacturing or Finance/HR suite. Do not reuse demo credentials.
{% endhint %}

## T-VERSE — channel OMS and fulfillment

### Inspected sources

* [Repository README](https://github.com/azad-727/Tverse)
* [Manufacturing service](https://github.com/azad-727/Tverse/blob/main/tverse-main/tverse-ims/src/main/java/com/thalasi/tverse/service/ManufacturingService.java)
* [Order flow service](https://github.com/azad-727/Tverse/blob/main/tverse-main/tverse-ims/src/main/java/com/thalasi/tverse/service/OrderFlowService.java)

### Best practices adopted

* External order ingest with source account/order identity and idempotent replay.
* Explicit header mapping and external SKU alias resolution.
* Bundle/component decomposition before availability allocation.
* Reservation, pick, pack, manifest, shipment, cancellation and return/disposition stages.

### Stockland decision

Use existing `product_code_maps` and external product-code resolution, plus the existing header-mapping/profile functionality. Build an OMS adapter that stages untrusted channel orders separately from the Daily POS Import. Unknown or ambiguous SKU mappings stay in a mapping queue. Bundles expand to active Product Master variants before availability checks. Only a separate authorized conversion action may create a Stockland order; intake does not post POS, stock or GL. Actual stock movement remains with the existing ledger-backed shipment path.

{% hint style="warning" %}
The inspected `ManufacturingService.java` is narrow: it handles fabric-in-KG lot/cut quantities/rejections and cancellation restoration. It is not full PLM, graded patterns, marker/lay, sewing, quality, carton traceability or production costing.
{% endhint %}

## Supplemental cross-check — BengalTex ERP

[EnamulHaque68/BengalTex-ERP](https://github.com/EnamulHaque68/BengalTex-ERP) (`main` HEAD `2c8fa04c971f667fbfe11abce57660354575134c`) remains a broad garment ERP process/UAT comparator, not one of the four direct contributors.

Its workflow map and UAT guide provide useful cross-checks for PR → RFQ → approved PO → partial GRN → incoming QC/quarantine → landed cost → supplier invoice match; BOM/MRP → production order/job card/stage QC; and sales → delivery → invoice → receipt reconciliation. Its Bangladesh-specific currency, tax, VAT/Mushak and labour rules are not portable defaults for Ethiopia. It does not authorize copying the .NET/Angular stack into Stockland.

## Current Stockland source alignment

The exact 16.2.18 source already contains useful foundations that should be extended rather than duplicated:

* `includes/rest/class-rest-import-v16.php`: source header alias mapping, confidence/conflict detection and import profile contracts.
* `includes/class-stockland-db.php`: external POS/ERP product-code resolution using `product_code_maps`.
* `includes/class-stockland-requisition-service.php` and `includes/rest/class-rest-requisition-v16.php`: requisition approval, availability, reservation, manager/warehouse actions and receipt flow.
* `includes/rest/class-rest-transfers.php`, reservation records and `includes/class-stockland-ledger.php`: existing stock transfer, idempotent movement and balance derivation.
* `includes/class-stockland-workflow.php`, `includes/class-stockland-audit.php` and `includes/class-stockland-authority.php`: existing transition, audit and role/location boundary services.

{% hint style="info" %}
**Not found in the inspected 16.2.18 source inventory:** full PLM/sample runtime, optional MES/SFC hour-level shop-floor execution, detailed picker scan/gate-manifest service, and external marketplace channel-order staging/OMS conversion. Existing import mapping is not the same as channel-order intake; existing pick/requisition actions are not proof of a full scan/gate workflow.
{% endhint %}

## Shared adoption rules

* Reimplement in Stockland PHP/WordPress REST conventions. Do not port Java/Spring, Next.js/MongoDB, Node/Express/MongoDB or Go/Svelte stacks.
* No copied source, CSS, images, assets, demo data, secrets or credentials. Review license/notice obligations separately before any future direct code reuse.
* Preserve the 49-table operational contract, live business data, Product Master identity, current role boundaries and the existing stock/Finance/HR authorities.
* New domains are additive, versioned and Owner-approved before DDL; ordinary plugin boot cannot install them.
* No duplicate Product Master, stock ledger, payroll engine or general ledger.
* No repository README claim is evidence of successful build, UAT, security test or production behavior.
