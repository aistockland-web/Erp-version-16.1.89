# MES\_SFC\_QUALITY\_WMS\_OMS

## 1. Factory organization

A tenant that enables owned-factory execution can configure:

`Legal Entity → Factory/Site → Building → Floor → Production Area → Line → Work Centre/Operation → Machine/Equipment`.

Each resource has stable ID, purpose/type, calendar, location, active state, capacity attributes, responsible manager, maintenance status and access scope. Display labels are configurable; business rules use stable identifiers, not string matching on names such as “Central Warehouse”.

## 2. Production planning objects

* `ProductionOrder`: buyer/order/style/variant breakdown, site, planned quantity, start/finish dates, approved BOM/routing/pattern revisions, cost snapshot, status and approvals.
* `ProductionOrderLine`: style, colour, size, quantity, buyer order line, planned cut and finished quantity.
* `OperationRouteRevision`: sequence, operation, work area, standard minutes, machine class, required skill, predecessor, subcontract flag, mandatory flag and QC gate.
* `JobCard`: order line/operation/bundle scope, work centre, planned quantity, assigned supervisor/team, due date and active status.
* `WIPContainer`: bundle/panel/trolley/container ID, source cut ticket, variant/size/colour, current operation/location and good/held/rework quantities.
* `ProductionEvent`: immutable start, pause, resume, complete, transfer, output, reject, rework, scrap, downtime, maintenance or exception event with actor/time/correlation/idempotency.

A production order pins the approved revisions. An engineering change creates a new order revision or an explicit change-control decision for affected WIP; it does not silently rewrite the live route or BOM.

## 3. Pre-production and cutting

{% stepper %}
{% step %}
### Release the production order

Planner releases the approved production order only after required materials/capacity are ready or a formally authorized partial-release policy applies.
{% endstep %}

{% step %}
### Confirm material

Material controller confirms accepted fabric roll/lot, shade group, usable width, defect map and available quantity.
{% endstep %}

{% step %}
### Schedule fabric relaxation or conditioning

Fabric relaxation/conditioning is scheduled and timestamped based on material/process requirement. Do not invent one universal relaxation time.
{% endstep %}

{% step %}
### Inspect fabric

Fabric inspection maps defects into roll coordinates and records defect type/severity and permitted concession.
{% endstep %}

{% step %}
### Plan the marker

Marker planning receives exact size/colour ratio, piece quantities, grade, roll width/length/usable width, grainline, nap, print repeat, shade and machine constraints.
{% endstep %}

{% step %}
### Validate and approve the marker

Marker candidate is validated and approved by Pattern Master/Cutting Manager. AI is advisory only.
{% endstep %}

{% step %}
### Record lay and spreading order

Lay/spreading order records marker revision, roll sequence, shade group, ply count, spread direction, fabric consumption and measured lay dimensions.
{% endstep %}

{% step %}
### Validate cutting release

Cutting release validates roll issue/reservation, marker hash, bundle quantity and machine profile.
{% endstep %}

{% step %}
### Record cutting

Cutting records good panels, rejected panels, recut, remnant, fabric waste, bundle identity, size/colour, cutter/line, operator, timestamp and reason.
{% endstep %}

{% step %}
### Create bundle tickets and labels

Bundle tickets/labels encode tenant, production order, style revision, cut ticket, size, colour, quantity, bundle ID and route revision.
{% endstep %}
{% endstepper %}

### Cutting reconciliation

By lay and roll:

`issued usable fabric = cut consumption + approved remnant returned + recorded waste + documented measurement/process variance`.

By style/order/size/colour:

`planned cut quantity = accepted cut quantity + rejected panel quantity + documented recut/shortfall/approved variance`.

No bundle is eligible for sewing until its cut ticket is released and quantity/variant identity is verified.

## 4. Sewing and shop-floor execution (MES/SFC)

### Operation scan

A line operator or supervisor scans the bundle at the assigned operation. The service verifies:

* Tenant/site/factory/line/role access.
* Bundle identity and current WIP operation.
* Route revision and predecessor acceptance.
* Required skill/machine eligibility where enabled.
* Quantity available to process and duplicate/idempotency status.

The scan event records bundle, production order, route operation, worker reference, line/work centre, machine, event type, timestamp, quantity good/reject/rework, downtime reason, correction reference and offline sync identifier.

### Hourly production board

Dimensions: factory/building/line, production date/hour, style, buyer, colour, operation, production order and team. Measures: target, good output, reject, rework, downtime, WIP in/out, expected minutes, earned minutes, variance and approved efficiency formula.

* Targets are established by production planner/IE based on approved SMV, working calendar, line capacity and approved assumptions.
* Actual output comes from validated scans/events; the dashboard never allows aggregate actual to be typed in as a replacement for event history.
* Corrections append a correcting event and reference the original. They do not overwrite history.
* Efficiency formula and denominator are versioned and visible. If no defensible available-minute denominator exists, report units/hour and do not invent efficiency.
* Do not use MES output as a direct wage or incentive calculation. HR owns any compensation logic and requires an explicit approved integration rule.

### WIP control

Each operation receives quantity from an accepted predecessor. Good output becomes input to the next operation; reject/rework/hold quantities remain separately accounted. The total of available, held, rework, accepted downstream, rejected/scrapped and documented variance must reconcile to released input.

WIP is a production-state projection with full source events; it is not a second inventory ledger. A bundle cannot be in two active work centres simultaneously unless an explicitly configured split/merge transaction records parent/child identities and quantities.

### Downtime and maintenance

Record machine/line, stop/start, fault code, severity, maintenance request, technician, parts and restored time. Production schedule updates use a new capacity snapshot; no actual output or time log is deleted to improve reported efficiency.

## 5. Special processes and subcontracting

Optional routing operations include embroidery, printing, washing, dyeing, heat transfer, laser, quilting, pleating, garment dye, external finishing and subcontract sewing.

A subcontract issue creates a gate-pass/material-custody record: vendor/site, source bundle/carton IDs, quantities, roll/trim ownership, expected return, service scope/rate, quality specification, expected date and approver. Receipt records processed, good, reject, rework, short, excess and unused material. Vendor-held stock remains visible until reconciled. Supplier invoice and payment remain Finance-owned.

## 6. QAM/SQC — quality assurance and control

### Inspection types

* Incoming fabric/trim/accessory inspection.
* Fabric roll/shade/width/GSM/defect test.
* Pre-production/sample approval.
* Cutting panel inspection.
* Inline sewing inspection.
* End-line inspection.
* Special-process inspection.
* Finishing/final garment inspection.
* Packing/carton audit and pre-shipment inspection.

### Inspection plan

A plan binds to buyer, product/category, material/lot, production stage, market and effective date. It defines characteristics, method, equipment, sample size/selection rule, defect codes, severity, acceptance/rejection rules, measurement tolerances and required evidence. Customer requirements override generic defaults where contractually applicable.

### Sampling standards

The standards registry may map sampling plans to ISO 2859-1, with edition and effective date. ISO 2859-1:2026 is the current third edition published January 2026; it replaces the 1999 version and amendments. The tenant must select the correct inspection level, lot size, AQL/acceptance criteria and switching rules with buyer/quality ownership. Never hard-code one AQL for every buyer or style.

### Defect taxonomy

Defect record contains stable code, type, stage, severity, location on garment/piece, count, affected quantity, inspector, photo/evidence hash, root-cause code, disposition and CAPA link. Separate critical/major/minor categories according to the approved customer plan.

### Disposition state

`INSPECTED → ACCEPTED | HOLD | REWORK | REJECTED | CONCESSION_PENDING`.

A hold can be released only by the configured independent role with evidence/reason. Rework returns to a specified route stage and then requires re-inspection. Rejected/scrapped goods never become sellable stock. CAPA records containment, cause analysis, corrective action, owner, due date, evidence, verification and closure.

### Quality release gates

* QC hold/reject blocks WMS sellable availability, allocation, packing and dispatch.
* A final QC release references style/tech-pack revision, production order, accepted quantity, inspection plan/version and exact lot/bundle/carton.
* Reinspection or concession creates a new inspection/disposition event.

## 7. Finishing

Configurable operations may include thread trimming, pressing/ironing, washing/softening, measurement audit, button/zipper/trim verification, label/care verification, metal detection, needle policy, folding preparation and final appearance review.

Each operation has planned/actual output, reject/rework, operator/team reference, equipment, quality checkpoint and timestamps. Market/buyer/product instructions define which operations are mandatory.

## 8. Packing and cartonization

### Pack specification

A versioned pack spec defines fold/garment orientation, hanger/polybag, tissue, hangtag, barcode/size sticker, care/brand label, inner pack, ratio pack, master carton, dimensions, weight limits, shipping marks and destination requirements.

### Carton content

Each carton records unique carton ID, order, style/variant, colour, size, quantity per SKU, pack ratio, gross/net weight, dimensions, lot/production/quality lineage, label and scan history. Carton contents are built from actual scans, not from an unverified typed total.

### Logistic unit identifier

Where the tenant has the correct GS1 company prefix and license, use SSCC as the unique logistic-unit reference on pallet/carton labels and store the SSCC-to-carton manifest mapping. The prefix is the tenant's own GS1-issued identity; a cloud service must not use its own prefix for a customer's logistics unit. GS1 SSCC is a key for accessing the logistics-unit record and is archived for traceability. Do not invent customer prefixes or issue duplicate SSCCs.

### Packing/FG reconciliation

`final QC released quantity = packed quantity + remaining unpacked + approved hold/scrap/variance`.

Carton closure requires that scanned content totals equal carton declarations, buyer size/colour ratio and allocated quantity. Reprint/repack is an audited correction. A sealed carton is eligible for dispatch only if all order, QC, credit and shipping gates pass.

## 9. WMS — warehouse operations

### Inventory dimensions

Tenant, legal entity, owner, site, warehouse, zone, aisle, rack, bin, item/variant, lot/roll, shade, status, UOM, quality state and stock owner.

### Stock states

* `USABLE_ON_HAND`
* `RESERVED`
* `IN_TRANSIT`
* `QC_HOLD`
* `REJECTED`
* `DAMAGED`
* `WIP`
* `FINISHED_GOODS`
* `RETURN_PENDING_INSPECTION`

The exact state model maps to the existing stock ledger via an adapter. No second quantity ledger is added.

### Warehouse processes

* Inbound: ASN, dock/receipt, partial GRN, incoming QC, putaway.
* Raw materials: lot/roll/bin, shade grouping, reservation, material pick/issue, unused return.
* WIP: bundle/cart transfer, accepted operation output, split/merge genealogy and hold area.
* Finished goods: final QC, carton close, putaway, allocation, cycle count and replenishment.
* Outbound: order wave, pick, short pick, scan/verify, pack/carton, dispatch manifest, shipment confirmation.
* Returns: RMA receipt, inspection, restock/repair/hold/scrap/vendor return.

### Stock actions are distinct

| Action               |                                  On-hand |                                   Reserved | Meaning                                              |
| -------------------- | ---------------------------------------: | -----------------------------------------: | ---------------------------------------------------- |
| Reserve              |                                Unchanged |                                   Increase | Promise quantity for order/production                |
| Release reserve      |                                Unchanged |                                   Decrease | Cancel/reallocate valid commitment                   |
| Issue material       |                                 Decrease |                    Clear issue reservation | Physical material consumption/hand-off to production |
| Receive accepted GRN |                          Increase usable |                                  Unchanged | Physically received and quality accepted             |
| Transfer dispatch    |               Source moves to in-transit |                                  Unchanged | Custody change begins                                |
| Transfer receipt     |          In-transit moves to destination |                                  Unchanged | Destination acknowledges actual quantity             |
| Pick                 | Unchanged until posted ship/issue policy | Remains committed or moves to picked state | Operational pick event                               |
| Ship                 |                                 Decrease |                  Clear shipped reservation | Physical dispatch posting                            |
| Physical count       |      No change until approved adjustment |                      Separately reconciled | Count session and approved variance                  |

No negative stock, over-pick, over-issue, duplicate receipt, duplicate shipment or unapproved adjustment. Concurrency uses row/aggregate locks and idempotency; stale updates return conflict.

## 10. OMS, wholesale, retail and channels

Common order states may include:

`DRAFT → QUOTED → CONFIRMED → CREDIT_CHECKED → ALLOCATED → RESERVED → PICK_WAVE → PICKED → VERIFIED → PACKED → DISPATCH_READY → SHIPPED → DELIVERED → INVOICED → SETTLED`.

Actual state machine is domain-specific; do not implement one permissive global status column.

* Wholesale: buyer/customer price list, contract terms, size-colour breakdown, allocation, ATP, dispatch and claims.
* Retail: branch replenishment, transfers, receipt, POS/cart/sale/refund/exchange and daily till close.
* eCommerce/marketplace: authenticated import, SKU alias, idempotency, combo/bundle decomposition, fulfilment update, exception queue and returns.
* B2B portal: catalog/assortment, customer price, order approval, order status, available-to-promise and shipment.
* EDI: partner map, schema version, acknowledgements, retries, duplicate detection, rejection report and secure transfer.

Fulfillment never writes directly to Finance journals. It emits the approved shipment/invoice event to the Finance app.

## 11. Reverse logistics and claims

Every customer/vendor return references the original sale/shipment/GRN/lot/carton. Quality evaluates condition and disposition. Inventory receives a new return movement; Finance creates a credit/debit/refund event where required. The original invoice, shipment and stock movement remain immutable.

## 12. Operational dashboards

* Factory schedule, line load, line/hour actual, output vs target, WIP ageing, constraints, downtime.
* Cutting yield, marker utilization, roll consumption, remnant, scrap/recut and shade issues.
* QC incoming/inline/endline/final holds, defects, rework and CAPA ageing.
* Packing completion, cartons awaiting close, carton mismatch and dispatch readiness.
* Warehouse stock by lot/shade/bin/status, reservations, pick queue, short picks and transfers in transit.
* Orderbook, OTIF, fill rate, backorder, customer claims and returns.
* Finance-owned costing/margin/AP/AR/GL values appear only from approved integration/read models and always link to the Finance source.
