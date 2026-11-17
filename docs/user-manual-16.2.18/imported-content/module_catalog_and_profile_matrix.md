# MODULE\_CATALOG\_AND\_PROFILE\_MATRIX

**Status:** target catalog. Capability names below do not assert current runtime implementation.

## Product navigation/domain catalog

| Domain                            | Main responsibilities                                                                           | Required master data                                            | Primary actors                                   | Key outputs                                            |
| --------------------------------- | ----------------------------------------------------------------------------------------------- | --------------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------ |
| Tenant & Organization             | Customer account, legal entities, sites, brands, factories, warehouses, branches, subscriptions | Country, currency, site/location purpose, users, role templates | Tenant Owner, Admin                              | Tenant configuration, site scope                       |
| Apparel Product Master            | Style, colour, size, fit, material, variant/SKU, barcode                                        | Taxonomy, size scale, colour library, UOM                       | Product Manager, Merchandiser                    | Controlled style/variant identity                      |
| Design Studio                     | Design briefs, concept assets, technical flats, review                                          | Season, collection, category, design asset types                | Designer, Design Lead                            | Design revision, review pack                           |
| Pattern Engineering               | Blocks, pattern pieces, geometry, seam allowance, notches, grade rules                          | CAD dialect, units, POM, size scale, material direction         | Pattern Maker, Pattern Master                    | Approved pattern set and grade nest                    |
| PLM / Tech Pack                   | Measurements, BOM, routing, labels, care, packaging, change control                             | Material/component masters, operation library, standards refs   | Technical Designer, PLM Reviewer                 | Immutable approved tech-pack revision                  |
| Sample Room                       | Proto, fit, size set, PP, photo/sales sample, comments and approval                             | Sample types, fit model reference, POM, workflow                | Sample Coordinator, Pattern, QA, Buyer           | Approved sample and measured fit evidence              |
| Costing & Merchandising           | Enquiry, quotation, cost sheet, margin, buyer booking                                           | Material price, labour/SMV, overhead, FX, Incoterms/terms       | Merchandiser, Costing, Commercial Approver       | Approved cost snapshot and buyer order                 |
| Sourcing & Supplier Collaboration | RFQ, quote, supplier capacity, mill, subcontract factory, ASN                                   | Supplier qualification, lead time, material spec                | Sourcing, Procurement, Supplier                  | Supplier award, PO, ASN and milestone evidence         |
| Demand & Assortment Planning      | Forecast, order book, assortment, allocation, delivery calendar                                 | Sales history, customer plan, seasonal calendar                 | Planner, Merchandising                           | Demand plan and allocation proposal                    |
| Capacity Planning / MPS           | Factory calendar, line capacity, SMV, skills and bottlenecks                                    | Factory, building, line, work centre, machine, shift calendar   | Production Planner, IE                           | MPS and overload/shortage exceptions                   |
| MRP                               | BOM explosion, gross/net requirements, supply-date and shortages                                | Approved BOM, inventory states, lead times, reservations        | Material Planner, Procurement                    | Material shortage and draft PR proposals               |
| Raw Material WMS                  | Fabric rolls, trims, lot/shade/bin, reservations, putaway and issue                             | Material/roll/lot, warehouse, bin, QC status                    | Warehouse Manager, Storekeeper                   | Accepted usable stock, issue/return ledger events      |
| Cutting Room                      | Relaxation, inspection, lay, marker, spreading, tickets, bundle/panel                           | Pattern/grade, roll, defect map, marker constraints             | Cutting Planner, Pattern Master, Cutting Manager | Validated marker, approved lay/cut tickets, bundle IDs |
| MES / SFC                         | Line assignment, operation scan, hourly output, WIP, downtime, rework                           | Routing, work centre, machine, skill reference                  | Factory Manager, Line Supervisor, Operator       | Immutable operation events and WIP state               |
| Special Processes / Subcontract   | Print, embroidery, wash, dye, heat transfer, quilting and external steps                        | Process recipe, supplier/vendor, material custody               | Production Planner, Subcontract Manager          | Process batch and vendor WIP reconciliation            |
| QAM / SQC                         | Incoming, inline, end-line, final inspection, defect, quarantine, CAPA                          | Inspection plans, sampling standard, defect dictionary          | QC Inspector, QA Manager                         | Quality disposition and release evidence               |
| Finishing                         | Thread trim, pressing, label verification, measurement and metal detection if required          | Approved routing/pack spec, finishing checklist                 | Finishing Supervisor, QA                         | Finished quantity pending final inspection             |
| Packing & Cartonization           | Pack ratio, polybag/hangtag, carton, weights, shipping marks, manifests                         | Buyer pack spec, size/colour ratio, GS1 scope                   | Packing Supervisor, QA                           | Sealed carton manifest and SSCC/internal logistic ID   |
| Finished Goods WMS                | FG putaway, carton/bin, availability, reservation and transfer                                  | Carton, SKU/variant, warehouse/bin                              | FG Warehouse Manager                             | Allocatable finished goods                             |
| OMS / B2B                         | Wholesale buyer orders, credit status, allocation, pick/pack/ship, claims                       | Buyer, contract pricing, order terms                            | Wholesale Sales, Order Desk                      | Fulfillment and customer delivery status               |
| Retail / POS / eCommerce          | Branch replenishment, POS, marketplace imports, refunds, omnichannel inventory                  | Store, till, product alias, channel integration                 | Branch Manager, Cashier, eCommerce Ops           | Store/channel sales and returns                        |
| Traceability & Recall             | Forward/backward trace from material to order/carton/shipments                                  | Roll, bundle, carton, shipment lineage                          | QA, Compliance, Customer Service                 | Affected-lot/customer recall graph                     |
| BI / Planning Intelligence        | OTIF, yield, line efficiency, defect, WIP age, stock age, cost variance                         | Source lineage and KPI definitions                              | Management, Planner, Auditor                     | Explainable dashboards and exception workbench         |
| HR & Workforce (independent)      | Employee, contracts, skills, shifts, attendance, leave, compensation, payroll                   | Country rule pack, employment/legal data                        | HR, Payroll                                      | Workforce availability events and HR records           |
| Finance (independent)             | AP, AR, GL, cash/bank, FX, tax, WIP/COGM and close                                              | COA, tax profile, cost centres, periods                         | Finance, Controller                              | Balanced journals and financial statements             |

## Capability profiles

Profiles are composable. A legal entity can operate a retail business plus a separate factory; one customer does not need a separate software fork.

| Capability              |                  Brand |              Outsourced brand |                    CMT factory | Full package factory |     Wholesale |               Retail |
| ----------------------- | ---------------------: | ----------------------------: | -----------------------------: | -------------------: | ------------: | -------------------: |
| Product Master          |               Required |                      Required |                       Required |             Required |      Required |             Required |
| Design / PLM / Samples  |               Required |                      Required |                       Optional |          Recommended |      Optional |             Optional |
| Pattern / Grading       |               Optional |                      Optional |        Optional/Buyer supplied |          Recommended |  Not required |         Not required |
| Costing / Buyer Orders  |               Required |                      Required |                       Required |             Required | Sales pricing |        Sales pricing |
| Supplier / Sourcing     |               Required |                      Required |                       Required |             Required |      Required |             Required |
| Demand / MPS            |               Required |                      Required |                       Required |             Required |      Required |             Required |
| MRP                     |               Optional |                      Optional | Optional by material ownership |             Required |  Not required |         Not required |
| Cutting / MES / SFC     |               Optional |           Disabled by default |         Required when in-scope |             Required |  Not required |         Not required |
| QAM / SQC               | Required by buyer/spec | Required via partner evidence |                       Required |             Required |    Inbound QC |           Returns QC |
| Raw/WIP/FG WMS          |               Optional |                      Optional |                       Required |             Required |   Required FG | Required store stock |
| Packing / Cartonization |               Required |                      Required |                       Required |             Required |      Optional |             Optional |
| OMS / B2B               |               Required |                      Required |                       Optional |             Optional |      Required |             Optional |
| Retail / POS            |               Optional |                      Optional |                       Optional |             Optional |      Optional |             Required |
| HR App                  |   Optional integration |          Optional integration |           Optional integration | Optional integration |      Optional |             Optional |
| Finance App             |   Optional integration |          Optional integration |           Optional integration | Optional integration |      Optional |             Optional |

### Capability-off contract

{% hint style="info" %}
When a tenant has no factory/manufacturing entitlement:
{% endhint %}

* MRP, production order, cutting, MES/SFC and factory work-centre routes are unavailable or return a clear `CAPABILITY_DISABLED` response.
* No manufacturing scheduled job, table, navigation item or report is required for standard sales/warehouse workflows.
* Supplier/external-factory milestone and QC evidence still work for outsourced brands.
* The same Product Master, warehouse, order, ledger and traceability services are used where applicable.

## Application navigation model

Navigation groups are entitlement-aware but domain ownership is independent:

* **Product:** Product Master, Design Studio, Pattern Room, PLM, Samples, Tech Pack.
* **Commercial:** Enquiries, Cost Sheets, Buyers, Sales Orders, Assortment, Wholesale, Retail, eCommerce.
* **Supply:** Suppliers, Mills, RFQ, Purchase Orders, ASN, GRN, Incoming QC, Material WMS.
* **Planning:** Demand, MPS, Capacity, MRP, Shortages, Allocation.
* **Factory (optional):** Production Orders, Cutting, Marker Desk, Sewing Floor, Special Processes, WIP, Maintenance.
* **Quality:** Inspection Plans, Incoming, Inline, End-line, Final, Holds, Rework, CAPA, Audit.
* **Logistics:** Finishing, Packing, Cartons, FG WMS, Pick/Pack/Ship, Returns, Traceability.
* **Administration:** Tenant/Company, Sites, Locations, Users/Roles, Integrations, Standards, Audit, System Health.
* **Separate apps:** HR & Workforce; Finance.

## Dashboard role contracts

Dashboards should be work queues and exceptions, not decorative KPI cards. Each tile links to a scoped source record and actionable route.

* **Designer:** briefs due, assets awaiting review, collection calendar, open design comments.
* **Pattern Master:** unvalidated pieces, grading exceptions, sample fit issues, pattern revisions awaiting sign-off.
* **Merchandiser:** enquiry aging, buyer sample feedback, cost-sheet status, order delivery risks.
* **Planner:** MRP exceptions, line bottlenecks, material shortage dates and orders at risk.
* **Procurement:** approved PRs, RFQ deadlines, PO confirmations, overdue supplier shipments.
* **Raw Material Warehouse:** ASN/GRN pending, QC holds, putaway queue, reservation/issue exceptions.
* **Cutting Manager:** lay plan queue, marker validation failures, roll/shade readiness, cut output and recut variance.
* **Sewing Supervisor:** hourly plan/actual, WIP by operation, line balance, downtime and rework queue.
* **QA Manager:** inspection backlog, defect trends, quarantined quantity, CAPA overdue and release decisions.
* **Finishing/Packing:** released FG awaiting finish/pack, carton discrepancy, packing list readiness.
* **FG Warehouse:** open putaway, allocated cartons, pick/verify queue, dispatch/short-pick exception.
* **Branch Manager / Cashier:** replenishment, transfer receipt, sales, returns, till close and branch exceptions.
* **Finance:** AP/AR exceptions, pending journal events, bank reconciliation, cost variance and close checklist.
* **HR:** attendance/shift exceptions, approvals, payroll status and local statutory review status.
* **Tenant Owner:** tenant onboarding, entitlements, role/security review, unresolved operational exceptions and audit.
