# DOMAIN\_MODEL\_AND\_EVENTS

This is a conceptual domain model for design review and API/schema planning. It is **not approved SQL DDL**. New physical tables, migrations and foreign-key/index decisions require source-level review, exact fingerprints, tenant-scoped tests and the required Owner/Architecture gate. Never alter the protected 49 operational keys to make this model fit.

## 1. Identity and versioning rules

* Every new commercial record belongs to a tenant and the relevant legal entity/site scope.
* Every aggregate has a stable internal ID, business code, created/updated actor/time and lifecycle state.
* Every document or approval-sensitive object has a version/revision and a state transition history.
* A production or buyer order pins exact versions; “latest” is never a valid historical reference.
* External business codes (buyer style code, supplier material code, marketplace SKU) are aliases, not internal identity.
* Every state-changing command is authorized, idempotent, concurrency-safe and audit-linked.
* Deleted/retired master records remain resolvable from historical documents. Prefer inactive/retired states over hard delete after reference.

## 2. Tenant and organization domain

| Entity       | Key attributes                                                          | Relationships                                                            |
| ------------ | ----------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Tenant       | `tenant_id`, legal identity, country, timezone, base currency, status   | Owns legal entities, users/scopes, subscriptions, files and data domains |
| LegalEntity  | `legal_entity_id`, tenant, registration/tax refs, fiscal calendar       | Owns brands, sites, Finance boundary                                     |
| Brand        | `brand_id`, tenant, legal entity, logo/label identity                   | Owns collections/styles/customer-facing specs                            |
| Site         | `site_id`, tenant, legal entity, site purpose                           | Factory/warehouse/branch/office; stable purpose code                     |
| Location     | `location_id`, tenant/site, stable purpose/type, display name           | Warehouse/storage/branch; display name can change without breaking logic |
| WarehouseBin | `bin_id`, warehouse, zone/rack/bin code, capability/state               | Stores item/lot/status locations                                         |
| UserScope    | `user_id`, tenant, legal entity/site/warehouse/line/channel scope, role | Controls access to records and commands                                  |

## 3. Product, design and PLM domain

| Entity              | Key attributes                                                                                  | Relationships                                                                  |
| ------------------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| Style               | `style_id`, Product Master STYLE reference, tenant adapter, brand, season, collection, category | Has variants, design revisions, tech-pack revisions, patterns, BOMs and routes |
| Variant             | Existing Product Master VARIANT/SKU identity reference, size, colour, barcode                   | Used by orders, stock and carton contents; identity not duplicated             |
| Season / Collection | tenant, brand, year/period, state                                                               | Groups styles and design calendar                                              |
| DesignBrief         | style, brief revision, market, price target, delivery                                           | Feeds design assets and tech pack                                              |
| DesignAsset         | type, object key, media type, checksum, source/provenance, revision                             | Linked to brief/style/approval; private access-controlled storage              |
| TechnicalFlat       | style revision, front/back/side drawing, annotations, checksum                                  | Linked to tech pack and pattern pieces                                         |
| MeasurementSpec     | style revision, POM definitions, base size, targets, tolerance                                  | Linked to grade revisions and samples                                          |
| SizeScale           | tenant, size codes/order, market/profile                                                        | Referenced by variants, grade and order breakdown                              |
| GradeRuleRevision   | style, size scale, base size, per-POM increments, generated geometry hash                       | Produces a graded pattern set                                                  |
| PatternSetRevision  | style, tech-pack revision, grade revision, CAD source, canonical geometry snapshot/hash         | Contains pattern pieces and validation report                                  |
| PatternPiece        | piece code, geometry, layer/material, cut quantity, grainline, notch, seam/cut details          | Used by marker plan and cut tickets                                            |
| BOMRevision         | style, tech-pack revision, material lines, alternatives, consumption, wastage                   | Used by costing and MRP                                                        |
| RoutingRevision     | style, tech-pack revision, ordered operation definitions, SMV, skill/machine profile            | Used by capacity and MES/SFC                                                   |
| TechPackRevision    | version, state, author/checker, exact assets/measurement/grade/BOM/route/care/pack snapshots    | Production/buyer orders pin the approved revision                              |
| SampleRequest       | sample type, size/colour, due date, style/tech-pack/pattern revision                            | Contains sample tasks/fit sessions/approvals                                   |
| FitSession          | sample, POM results, comments, photos, correction tasks                                         | Creates pattern/tech-pack change notices                                       |

## 4. Commercial and sourcing domain

| Entity              | Key attributes                                                                        | Relationships                                 |
| ------------------- | ------------------------------------------------------------------------------------- | --------------------------------------------- |
| Buyer / Customer    | tenant, terms, channel, ship-to, credit reference                                     | Owns enquiries/orders/shipments               |
| Enquiry             | buyer, requested styles/qty/dates/market                                              | Produces costing and quotation                |
| CostSheetRevision   | style/order, pinned BOM/route, material/labour/overhead/other inputs, formula version | Approved quote and production cost comparison |
| QuotationRevision   | buyer, style/variant breakdown, price/currency/validity                               | May be accepted into SalesOrder               |
| SalesOrder          | customer, ship-to, terms, requested dates, state                                      | Contains order lines and delivery schedule    |
| SalesOrderLine      | style/variant breakdown, quantity, tech-pack revision, delivery                       | Feeds demand/MRP/allocations                  |
| Supplier            | tenant/partner identity, category, terms, currency, capabilities                      | Owns RFQ/quotes/PO/ASNs                       |
| SupplierMaterial    | external material code, spec, approved supplier relationship                          | Maps to Stockland material master             |
| RFQ / SupplierQuote | spec snapshot, qty/UOM, price, currency, lead time, validity                          | Comparison/award decision                     |
| PurchaseRequisition | requested item/qty/site/need-by/reason/budget ref                                     | Approved requisition may create PO            |
| PurchaseOrder       | supplier, approved lines, price, delivery/tax/terms                                   | Receipts and Finance match events             |
| SupplierASN         | PO/shipment, roll/lot/carton details, expected date                                   | Inbound receipt preparation                   |
| GoodsReceipt        | PO, received/accepted/rejected/held qty, receipt event                                | Material lot and AP 3-way match               |
| LandedCostVoucher   | charges, allocation basis, currency/date                                              | Finance event; affects material valuation     |

## 5. Planning and production domain

| Entity              | Key attributes                                                                                | Relationships                              |
| ------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------ |
| DemandPlanRevision  | orders/forecast snapshot, horizon, planner                                                    | Feeds MPS                                  |
| CapacityCalendar    | site/line/workcentre, shifts/hours, unavailable intervals                                     | Feeds capacity plan                        |
| CapacityPlan        | style/order/operation, SMV, quantity, available minutes, efficiency assumption                | Feeds schedule decisions                   |
| MRPRun              | input hashes, BOM versions, on-hand/committed/inbound snapshot, calculation version           | Produces shortage proposals/exceptions     |
| ShortageException   | item/site/date/quantity/reason/source MRP run                                                 | May create draft PR/expedite action        |
| ProductionOrder     | sales demand, style/tech-pack/BOM/route/pattern revision, site/date/qty/state                 | Contains production lines/jobs/cut tickets |
| ProductionOrderLine | variant/size/colour qty, cut/FG status                                                        | Feeds MRP and operation output             |
| JobCard             | order line, operation, work centre, planned qty/due date                                      | WIP/work execution                         |
| LayRequest          | production order, size/colour ratio, roll constraints, lay qty                                | Feeds marker candidate                     |
| MarkerCandidate     | input snapshot hashes, geometry/placements, solver/config version, metrics, validation report | Human approval may create cut release      |
| MarkerApproval      | candidate ID/hash, reviewer, manager, decision/reason/time                                    | Authorizes exact marker only               |
| CutTicket           | marker, roll IDs, lay, ply, cut order, bundle quantities                                      | Produces bundle records                    |
| Bundle              | order/style/variant/size/colour, cut ticket, qty, route, current op                           | Travels through factory operations         |
| OperationEvent      | bundle, operation, line, worker ref, machine, time, good/reject/rework/downtime qty           | Immutable WIP transition evidence          |
| WIPTransfer         | source operation, destination operation, bundle(s), accepted qty                              | Reconciles inter-operation movement        |
| SpecialProcessOrder | process/vendor, gatepass, bundle/material custody                                             | Subcontract/special-process lineage        |

## 6. Quality and warehouse domain

| Entity                   | Key attributes                                                            | Relationships                                  |
| ------------------------ | ------------------------------------------------------------------------- | ---------------------------------------------- |
| InspectionPlanRevision   | buyer/category/material/stage, test/POM, sampling scheme, criteria        | Used by inspections                            |
| Inspection               | source lot/bundle/carton, plan version, sample size, inspector/result     | Creates defects/disposition                    |
| Defect                   | code/severity/location/qty/evidence                                       | Linked to inspection/CAPA                      |
| QualityHold              | source aggregate, qty, reason, owner/status                               | Blocks usable stock/next stage                 |
| ReworkOrder              | source defect/bundle/qty, route and reinspect requirement                 | Produces reinspection evidence                 |
| CAPA                     | source defect, containment, root cause, action, verification              | Tracks systemic corrective action              |
| QualityRelease           | source aggregate, approved qty, plan/version, inspector/approver          | Required for sellable FG                       |
| MaterialLot / FabricRoll | SKU/material, supplier lot, roll, shade, width, GSM, length/qty, QC state | Traceable from GRN through cutting             |
| StockMovement            | Existing authoritative ledger ID or event reference                       | Remains single source of quantity changes      |
| Reservation              | order/production, item/lot/location, qty/state                            | Commitments distinct from on-hand              |
| WarehouseTask            | task type, source/destination, reference, assigned role/user              | Inbound/pick/move/count work                   |
| PickWave / PickLine      | order, bin, variant/lot, expected/picked qty                              | Gate scan/dispatch                             |
| Carton                   | carton ID/SSCC, order, pack spec, weight/dimensions, status               | Contains carton lines and shipment association |
| CartonLine               | variant/size/colour/lot qty                                               | Exact scanned carton contents                  |
| Shipment                 | order, carton manifest, carrier/route, dates/status                       | Finance invoice event and delivery proof       |
| Return/RMA               | original order/shipment/carton, inspected qty/disposition                 | New stock return and Finance credit event      |

## 7. Application ownership and data boundaries

### Apparel owns

Product/style adapter, design, patterns, samples, orders, sourcing workflow, MRP, production, cutting, WIP, quality, WMS, packing, shipments, operational traceability and factory analytics.

### HR owns

Person/employee identity, employment, private documents, compensation, attendance, leave, payroll inputs/calculation, payslips, payment batches, remittance and statutory rule packs.

### Finance owns

COA, accounting periods, GL, AP/AR subledgers, invoices, payment allocations, bank, tax, FX, budgets, assets, valuation/COGM posting and close.

## 8. Event envelope

```json
{
  "event_id": "uuid",
  "tenant_id": "uuid",
  "source_application": "APPAREL",
  "aggregate_type": "Carton",
  "aggregate_id": "uuid",
  "aggregate_version": 12,
  "event_type": "CartonSealed.v1",
  "schema_version": 1,
  "occurred_at": "2026-11-15T00:00:00Z",
  "correlation_id": "uuid",
  "causation_id": "uuid",
  "idempotency_key": "opaque-stable-key",
  "payload": {}
}
```

The example is synthetic schema documentation, not an issued event. Event consumer records the event ID/idempotency result and rejects a duplicate side effect. Unknown future schema versions are quarantined with an actionable compatibility error; never interpret unknown fields as permission to post.

## 9. Event catalog

| Event                         | Producer            | Consumer / next process         | Key data                                         |
| ----------------------------- | ------------------- | ------------------------------- | ------------------------------------------------ |
| `StyleCreated.v1`             | Product/Design      | PLM                             | Style ID, tenant, season/collection              |
| `TechPackApproved.v1`         | PLM                 | Costing/MRP                     | Exact revision/checksum, BOM/route/pattern refs  |
| `PatternRevisionApproved.v1`  | Pattern Engineering | Marker planning                 | Geometry hash, grade version, approval           |
| `SampleApproved.v1`           | Sample Room         | Buyer order/production baseline | Sample/tech pack/pattern revisions               |
| `BuyerOrderConfirmed.v1`      | OMS/Commercial      | Demand plan                     | Order lines, size-colour ratios, delivery dates  |
| `MRPShortageProposed.v1`      | MRP                 | Procurement planner             | Item, shortage qty/date, input run hash          |
| `PurchaseOrderApproved.v1`    | Procurement         | Supplier/WMS/Finance            | PO reference, approved lines/terms               |
| `GoodsReceiptAccepted.v1`     | WMS/Quality         | MRP/Finance                     | GRN, lot/roll, accepted qty, QC reference        |
| `MaterialReserved.v1`         | WMS                 | Production control              | Order/lot/location/quantity                      |
| `MaterialIssued.v1`           | WMS                 | Cutting/MES/Finance read model  | Lot/roll/qty/order/ticket                        |
| `MarkerCandidateValidated.v1` | Cutting             | Pattern/Cutting approval        | Candidate/input/result hashes, metrics           |
| `CutTicketReleased.v1`        | Cutting             | MES/SFC                         | Pattern/marker/roll hashes, bundles/qty          |
| `OperationOutputAccepted.v1`  | MES/SFC             | Next operation/HR adapter       | Bundle, operation, accepted/reject/rework qty    |
| `QualityHoldCreated.v1`       | QAM/SQC             | WMS/Production                  | Source ID, held qty, reason                      |
| `QualityReleased.v1`          | QAM/SQC             | WMS/OMS                         | Released qty/source/inspection version           |
| `CartonSealed.v1`             | Packing             | WMS/OMS/Traceability            | Carton/SSCC/line content/manifest hash           |
| `ShipmentConfirmed.v1`        | OMS/WMS             | Finance                         | Carton manifest, buyer order, delivery reference |
| `ProductionCostFinalized.v1`  | Apparel Costing     | Finance                         | Cost snapshot, production order, categories      |
| `ProductionOutputAccepted.v1` | MES/SFC             | HR (if connected)               | Minimal worker ref, operation, accepted quantity |
| `ReturnReceived.v1`           | OMS/WMS             | Quality/Finance                 | Original shipment, returned qty/condition        |

## 10. Event ordering and reconciliation

* Events are at-least-once delivered unless an adapter contract explicitly guarantees stronger semantics; consumers must be idempotent.
* Aggregate version/sequence detects stale and out-of-order events. A missing predecessor event creates a pending/reconciliation exception.
* Events contain identifiers and minimal domain facts, not copied full records or PII.
* Corrections are new events with causation reference, not mutation of historical payload.
* Cross-app delivery status is visible as `PENDING`, `ACKNOWLEDGED`, `REJECTED`, `RETRYING` or `RECONCILIATION_REQUIRED`.
* A transactional outbox is preferred to “write business row then best-effort publish”.
