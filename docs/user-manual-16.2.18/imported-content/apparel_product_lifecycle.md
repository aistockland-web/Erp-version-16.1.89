# APPAREL\_PRODUCT\_LIFECYCLE

This document defines the domain workflow for apparel, footwear and lifestyle products. It is not a generic bill-of-materials form. A style is a version-controlled product definition that connects design intent, measured fit, approved patterns, materials, routing, cost, production and customer fulfillment.

{% stepper %}
{% step %}
## Season, collection and design brief

**Owner roles:** Brand Manager, Merchandiser, Designer, Product Developer.\
**Output:** a controlled design brief and a draft style identity.

Required brief fields:

* Tenant, legal entity, brand, division, season, collection, target customer/market and channel.
* Buyer/customer or internal brand, target retail/wholesale price, currency and target cost range.
* Garment family/category, intended fit, silhouette, size range, target age/body market where relevant, gender/product classification where the tenant chooses to maintain it.
* Design concept, inspiration/reference media with rights/source notes, key construction details, colour direction, material intent, target launch/ship date and design owner.
* Applicable customer/market rules: labeling, care, packaging, restricted substances/testing evidence, traceability and destination requirements.
* Approval path and due dates.

{% hint style="info" %}
**Gate:** a new style code is unique within the tenant/style numbering policy and is not a stockable SKU by itself. No customer identity, Stockland branch or location default is seeded into a new tenant.
{% endhint %}
{% endstep %}

{% step %}
## Concept sketch and technical flat

A concept image and a technical flat are different artefacts.

* Concept artwork is exploratory: moodboard, colour story, silhouette, styling, visual inspiration, front/back concept sketch.
* Technical flat is a controlled product-development drawing with front/back/side views, seam/stitch callouts, panels, closures, pockets, trims, measurements and notes.
* Detail callouts cover construction-specific features: collar, placket, sleeve/cuff, waistband, pockets, fly, gusset, lining, closures, bartacks, hems, seam finish, logo/embroidery/print placement and labels.
* Each design/flat revision records author, date, review state, media checksum, source file reference and approval comments.

{% hint style="warning" %}
AI concept sketch generation may assist the designer with a draft visual. AI cannot claim the image is an approved tech pack or provide manufacturing geometry. A human designer confirms the technical flat and construction notes.
{% endhint %}
{% endstep %}

{% step %}
## Material and trim development

Material specifications must support procurement, fabric quality, marker/cutting and costing.

### Fabric master attributes

* Material code/type, supplier/mill, composition/fibre blend, yarn/fabric construction, weave/knit, colour/shade, finish, print/dye method, GSM/weight, usable width, nominal width, stretch/recovery, shrinkage/relaxation, hand feel/appearance notes, care constraints, lead time and approved market/test evidence.
* Fabric lot/roll has independent supplier lot, roll number, shade group, width/GSM test, received quantity/UOM, actual measured width, defect map, inspection state, certificate references and storage location.
* Roll and shade identity must flow through marker plan, material issue, cut tickets and bundles. A material master is not a received lot.

### Trim / accessory master attributes

Buttons, zipper, thread, elastic, label, hangtag, interlining, hook/eye, snap, packaging and accessory records contain code, type, specification, dimensions/colour/finish, UOM, supplier, approved sample, compliance/test evidence, consumption, tolerance and approved substitution rules.

### Material approval

A material may have `DEVELOPMENT`, `TEST_REQUIRED`, `APPROVED`, `CONDITIONAL`, `REJECTED` or `INACTIVE` state. Approval is scoped to vendor/material/colour/market/buyer where needed. Conditional materials cannot be silently treated as globally approved.
{% endstep %}

{% step %}
## Pattern block and pattern development

**Owner roles:** Pattern Master, Technical Designer, Pattern Engineer.\
**Output:** versioned 2D pattern pieces and construction geometry.

A pattern revision contains:

* Style ID, tech-pack revision, garment category, block reference, pattern maker, unit, coordinate origin, scale and CAD/interchange format.
* Individual pieces with piece code/name, cut quantity per garment, size/grade reference, mirrored/pair rule, material layer, grainline, fold line, seam line, cut line, seam allowance, notches, drill marks, darts, internal lines, pleats/gathers and labels.
* Closed cut-boundary geometry, piece perimeter/area, edge labels, match points, sewing partner relationships and construction comments.
* Pattern piece geometry version/hash, source file hash and conversion tool/version.
* Validation: closed paths, no self-intersection, valid units, positive area, notch placement, grainline validity, pair/mirror compatibility, required seam allowance and grade consistency.

{% hint style="warning" %}
Pattern geometry is versioned independently from the rendered image. A raster image alone is insufficient to issue a cut ticket. Production markers require validated machine-readable geometry and a supported file dialect.
{% endhint %}
{% endstep %}

{% step %}
## Measurement specification and grading

Each style defines measurement points (POMs) with stable codes, description, measurement method, reference location, base size, unit, target, positive/negative tolerance, rounding policy and diagram callout. Each grade contains values for every mandatory POM across the approved size range.

* Separate body measurement definitions from finished garment measurement specifications.
* Each size scale belongs to a tenant/brand/market and may contain alpha, numeric, waist/inseam or custom sizes.
* Grade rules record increments by POM and size step; the grade nest is versioned and previewable.
* Validate expected direction of growth, symmetry constraints, monotonicity where appropriate and measurement tolerances. Do not infer grading increments from a size label alone.
* Keep body-measurement standards, the tenant's target population, ease allowance and garment measurement targets as distinct records.
* Fit sessions capture actual measurements, fit comments/photos, problem area, severity, assigned correction and retest state.

The standards registry may map measurement terminology to ISO 8559 family references, but the tenant's approved size chart and buyer fit spec are still explicit. The standard does not automatically create a customer's size table.
{% endstep %}

{% step %}
## Sample room lifecycle

Sample stages are configurable by garment type/buyer but use an auditable transition model. Typical flow:

`REQUESTED → DESIGN_REVIEW → PATTERN_DRAFT → MATERIAL_ALLOCATED → CUT → SEWING → FINISHING → QA_REVIEW → FIT_REVIEW → BUYER_REVIEW → APPROVED / REWORK / REJECTED → HANDOVER → CLOSED`.

Typical sample variants:

* Proto / first pattern sample.
* Fit sample for fit comments and measurement validation.
* Revised fit / second sample.
* Size-set sample across the required size range.
* Pre-production sample (PPS) using production-intent material, trims, construction, labels and packaging.
* Sales/photography/showroom sample, where the company uses them.

Each sample records request, sample type, style/tech-pack/pattern/grade revision, size/colour, materials, assigned maker/room, due date, actual stage timestamps, consumed material, defects, measured values, photos/files, comments and sign-off. Rework creates a new task/revision linked to the previous sample; it does not erase prior evidence.

{% hint style="info" %}
**Gate:** only approved sample evidence and the approved technical revision can release a production baseline, unless a buyer-controlled exception is recorded with owner and expiry.
{% endhint %}
{% endstep %}

{% step %}
## Tech pack release

One approved tech pack is a versioned bundle of:

1. Design brief and approved technical flats.
2. Construction and workmanship specification.
3. Measurement spec, POM diagram, grade rules and size table.
4. Pattern-piece set, CAD files and pattern/grade hashes.
5. Colourways, material/BOM and approved alternatives.
6. Operation routing, work area, standard minutes, skill/machine requirements and subcontract steps.
7. Label/care instructions, packaging/carton instructions and market requirements.
8. Quality plan and buyer-specific inspection criteria.
9. Sample history and approvals.
10. Target cost and costing assumptions/version.

Any change to a released item creates a revision/change notice and impact analysis: open buyer orders, MRP, POs, markers, cut bundles, WIP and finished goods that still reference the previous revision. The system may require a stop/hold or exception by affected order, not mutate their pinned revisions silently.
{% endstep %}

{% step %}
## Enquiry, costing and buyer order

### Enquiry / request for quotation

Capture customer/buyer, delivery window, destination, style(s), quantity by size/colour, packaging, compliance, sample request, target price, currency, payment/credit terms and requested incoterm/shipping information where used.

### Cost sheet

Cost is a versioned computation from:

* Fabric consumption by marker/yield/size ratio, fabric price, usable width, shrinkage, wastage and landed-cost assumption.
* Trim/accessory consumption and approved alternates.
* Operation SMV, agreed labour/overhead basis, line efficiency assumption, special processes and subcontract charges.
* Testing, finishing, labeling, packaging, freight/duty/insurance and other allocated cost components.
* Commercial overhead, margin, commission, currency/date/exchange-rate source and buyer allowances.

The cost sheet stores inputs and formula version; it cannot silently update when supplier prices or grade data change. A new costing revision captures the change.

### Quotation / buyer order

Accepted quote creates demand lines with style/variant and size/colour ratio, quantity, ship/delivery window, ship-to, customer price/terms and pinned tech-pack revision. Any order change that changes quantity, delivery or approved product revision is a controlled amendment with planning/MRP impact.
{% endstep %}

{% step %}
## Demand planning, capacity and MRP

* Demand may originate from confirmed buyer orders, approved forecast, replenishment plan or internal store/warehouse replenishment.
* Aggregate by tenant, legal entity, factory/site, style, colour, size and period.
* Capacity model uses factory/building/floor/line/work centre, operation routing, standard minutes, working calendar, planned availability, changeover, efficiency assumptions and optional skill/machine constraints.
* Material MRP uses approved BOM revision and gross required quantity, usable on-hand, valid reservations/commitments, accepted inbound supply by due date, supplier lead time and approved wastage/scrap factors.
* Exclude QC-held, rejected, quarantined, expired, unowned or unconfirmed supply from usable availability.
* Run and store the input snapshot and calculation version. Output is a proposed shortage/expedite/defer/capacity exception, draft PR and draft production plan.

{% hint style="info" %}
**Gate:** MRP does not automatically order, issue, reserve or start production. Planner approves the proposals and the exact plan revision.
{% endhint %}
{% endstep %}

{% step %}
## Sourcing and supplier collaboration

* Supplier onboarding: legal identity, category (mill, trims, packaging, external manufacturer, freight, lab), contacts, capability, currency, terms, bank details in Finance-owned secure master where appropriate, compliance evidence, rating and blocked status.
* RFQ line pins specification, colour/shade, quantity, UOM, destination, due date, quality evidence, quote validity and expected lead time.
* Quote comparison evaluates specification match, total cost/landed cost, lead time, capacity, quality history, compliance and approved commercial preference—not lowest price only.
* Purchase requisition and PO remain separate from supplier invoice/payment. Approval thresholds and maker/checker are tenant-configured.
* Supplier ASN/pre-advice, shipment, partial receipt, shortage, overage/tolerance, QC and supplier return are separately recorded.
* External factory order records who owns fabric/trim, who pays freight, approved process, capacity/milestone, wastage, quality disposition and who owns rejected goods.
{% endstep %}

{% step %}
## Incoming material quality and warehouse

Each received roll/lot is a separate traceable stock object. The GRN is not a QC pass. Incoming quality may inspect composition, width, GSM, colour/shade, shrinkage, defects, test certificate, trims and packaging. A material may be:

* `ACCEPTED_USABLE` — eligible for MRP and reservation.
* `QC_HOLD` — physically stored in a quarantine location; unavailable.
* `REJECTED` — return/scrap/concession disposition required.
* `CONDITIONAL_ACCEPTANCE` — allowed only within a written, scope-limited exception.

Putaway assigns warehouse/bin, roll, shade and material ownership. Movement and reservation are different: reservation does not physically consume stock. Issue to production does. Return-to-store records original roll/lot and usable remainder. Physical stock count is a separate inventory count/adjustment workflow—not a transfer or ordinary movement.
{% endstep %}

{% step %}
## Cutting, sewing, finishing and packing

The cutting and factory execution sequence is defined in `CUTTING_MARKER_AI_ASSISTANT.md` and `MES_SFC_QUALITY_WMS_OMS.md`. Briefly:

* Roll inspection/relaxation → shade grouping → lay/spreading plan → marker candidate → independent validation → Pattern Master approval → Cutting Manager release → roll issue → cut execution → size/colour bundle labels → waste/recut reconciliation.
* Bundle dispatch to sewing line → operation scan/time/output → accepted WIP transfer → rework/downtime/reject → optional embroidery/printing/washing/subcontract.
* Inline/end-line inspection → finishing/pressing/trim/label/measurement/metal detection as required → final inspection → QC release.
* Pack spec → size/colour ratio → carton plan → scan contents → weigh/dimension → carton ID/SSCC → sealed manifest → finished-goods putaway.
{% endstep %}

{% step %}
## Channels, dispatch and returns

All channels consume the same Product Master and warehouse/availability truth.

* Wholesale/B2B: buyer price/contract, order breakdown, credit check, allocation, reserve, pick wave, carton verification, shipment, invoice event and receipt status.
* Retail: purchase/transfer from distribution centre, branch receipt, POS sale/refund/exchange, cashier close and branch reconciliation.
* eCommerce/marketplace: authenticated order intake, external SKU mapping, idempotent imports, bundle decomposition, availability, allocation, pick/pack/ship and status callback.
* Dispatch: only sealed cartons, released quality, verified order allocation, route and shipping documents.
* Returns: reference original order/shipment/carton, inspect condition, disposition to restock/repair/hold/scrap/vendor claim and send required Finance event. Original shipment and journal are never deleted.
{% endstep %}

{% step %}
## Analytics and traceability

Required metrics:

* Product development: sample cycle days, fit iterations, approval lead time, revision changes.
* Materials: supplier OTIF, roll defects, shade rejects, material yield, actual vs planned consumption, fabric inventory age.
* Planning: order book, MRP shortage days, supplier confirmation coverage, line capacity load.
* Factory: line hourly target/actual, earned minutes, efficiency, WIP aging, bottleneck, downtime, rework/scrap.
* Quality: inspected/accepted/rejected/rework qty, defect rates and buyer-specific acceptance status.
* Fulfillment: cartonization accuracy, pick/pack accuracy, OTIF, fill rate, backorders and return rate.
* Commercial/Finance: approved standard/actual cost variance, margin, AP/AR status, aged receivable and WIP/COGM (Finance app owns the posted numbers).

Trace from customer order line → style and approved technical revisions → MRP inputs → material supplier lot/roll → GRN/incoming QC → material issue → marker/lay → cut tickets/bundles → sewing operation logs → quality results → carton → shipment → customer return/claim. The system must support forward and reverse lookups for affected-lot containment and customer claims.
{% endstep %}
{% endstepper %}
