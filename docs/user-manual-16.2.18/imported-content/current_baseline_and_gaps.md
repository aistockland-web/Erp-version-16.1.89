# CURRENT\_BASELINE\_AND\_GAPS

**Source base inspected:** canonical delivery commit `fe937ea97123a243c410696e4007c48d3332803d` and its `src/stockland-erp-app/` documentation/source inventory.\
**Separate uploaded installer:** `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER (8).zip`, SHA-256 `aee5c551bd3367717091de066bfd5f86d8298dda1f6361d7534813d8087cd54e`; embedded manifest declares no canonical commit.\
**Purpose:** prevent future agents from treating planned modules as existing implementation or confusing the local installer with the canonical source branch.

## Status labels

* `EXISTING_FOUNDATION`: source/service/route exists in the current Stockland app; not a blanket claim that every route/UI has just been E2E tested.
* `PARTIAL`: a related feature exists but does not fulfil the complete apparel domain workflow.
* `TARGET_GAP`: no dedicated end-to-end apparel domain is established by the inspected source inventory.
* `APPLICATION_BOUNDARY_GAP`: intended independent application or SaaS/tenant boundary has not yet been extracted/implemented.

| Domain                                             | Current status                                                   | Required target work                                                                                                                             |
| -------------------------------------------------- | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| WordPress/PHP ERP and PWA                          | EXISTING\_FOUNDATION                                             | Preserve existing runtime, UI shell, route namespace, build/test and release pipeline                                                            |
| Product Master / SKU / variant                     | EXISTING\_FOUNDATION                                             | Add style/season/collection/fit/size-scale/colour/material metadata against existing IDs; no duplicate SKU master                                |
| Stock ledger / reservations / transfers / receipts | EXISTING\_FOUNDATION                                             | Add garment lot/roll/shade/bundle/carton lineage through existing ledger and movement services                                                   |
| Procurement / suppliers / customers                | EXISTING\_FOUNDATION                                             | Add apparel RFQ, mills, fabric/trim specs, supplier capacity, ASN, landed-cost and 3-way match integration                                       |
| POS / sales / wholesale / returns                  | EXISTING\_FOUNDATION                                             | Extend to assortment, buyer size-colour matrix, OMS, B2B, channel mapping and carton-level fulfillment                                           |
| Accounting / AP / AR / GL / PLEDGER                | EXISTING\_FOUNDATION                                             | Preserve Finance ownership; extract independent Finance app and add WIP/COGM/inventory valuation event integration                               |
| HR/payroll                                         | EXISTING\_FOUNDATION                                             | Preserve current HR rules/data and extract independent HR app; no invented Ethiopian statutory defaults                                          |
| Audit / workflow / authority / notifications       | EXISTING\_FOUNDATION                                             | Reuse for apparel maker-checker, holds/rework/release, AI suggestion review and traceability                                                     |
| Company / sites / branches / warehouses            | PARTIAL                                                          | Existing Stockland one-company seeded locations; add blank customer tenant provisioning and stable location-purpose configuration                |
| Multi-company tenant isolation / entitlements      | APPLICATION\_BOUNDARY\_GAP                                       | Provision isolated customer DB/runtime from shared versioned code; test APIs, files, exports, reports, queues, notifications and cache isolation |
| PLM and technical packs                            | TARGET\_GAP / earlier local design work not rebased              | Rebase the old local PLM idea on `fe937ea...`; finish runtime E2E, UI, sample lifecycle, attachments, grade templates and revision compare       |
| Design studio / technical flats                    | TARGET\_GAP                                                      | Design brief, assets, sketch/flat, approval and versioned style-design linkage                                                                   |
| Pattern drafting / pattern CAD / grading           | TARGET\_GAP                                                      | Canonical geometry, pattern-piece revision, grade rules, sample-fit linkage and CAD adapters                                                     |
| Sample room / fit sessions                         | TARGET\_GAP                                                      | Proto/fit/size-set/PP lifecycle, measurement evidence, comments, rework and buyer approval                                                       |
| BOM / routing / garment costing                    | TARGET\_GAP                                                      | Multi-level BOM, operation routing, SMV, alternates, standard/actual consumption and immutable cost-sheet snapshot                               |
| Capacity planning / MPS / MRP                      | TARGET\_GAP                                                      | Demand, calendars, lines/work-centres, BOM explosion, supply netting and draft shortage proposals                                                |
| Fabric roll / shade / defect trace                 | TARGET\_GAP                                                      | Roll-level width/GSM/shade/lot, defect-map and accepted-QC linkage                                                                               |
| Cutting / lay / marker / bundle tickets            | TARGET\_GAP                                                      | Relax/spread/marker, defect avoidance, ply/lay, cut ticket, bundle/panel identity and yield reconciliation                                       |
| Pre-cut AI design/pattern/cutting assistant        | TARGET\_GAP                                                      | Typed read-only context tools, deterministic geometry/marker engine, candidate validation, SVG/PDF preview and human approval                    |
| Sewing MES/SFC / operation WIP                     | TARGET\_GAP                                                      | Line/operation scans, hourly target/actual, good/rework/scrap/downtime, bundle-level WIP and event idempotency                                   |
| Special processes / subcontract                    | TARGET\_GAP                                                      | Print/embroidery/wash/dye/heat transfer/subcontract custody and receipt reconciliation                                                           |
| QAM/SQC / garment defect / CAPA                    | PARTIAL generic workflow foundations; full domain is TARGET\_GAP | Incoming/inline/end-line/final inspections, sampling plans, defect taxonomy, hold, disposition, rework, CAPA and release                         |
| Finishing / packing / cartonization                | TARGET\_GAP                                                      | Buyer pack specs, carton contents scan, SSCC/internal carton ID, weights, pack-list and count reconciliation                                     |
| Garment WMS/OMS fulfillment                        | PARTIAL existing warehouse/order paths                           | Raw/WIP/FG lot/bin/carton identity, pick/pack/verify/dispatch, store replenishment and channel ATP                                               |
| Traceability / recall                              | PARTIAL stock/audit lineage foundations                          | Full material-roll → cut bundle → operations → QC → carton → shipment reverse trace                                                              |
| HR independent deployment                          | APPLICATION\_BOUNDARY\_GAP                                       | Separate database/migrations, identity, authorization, release and versioned events                                                              |
| Finance independent deployment                     | APPLICATION\_BOUNDARY\_GAP                                       | Separate ledger authority, database/migrations, release and versioned event adapters                                                             |

## Protected invariants

* Existing Stockland tenant remains one company; Goro, Gerji, Mexico, Megenagna and Central Warehouse names/IDs are Stockland defaults, not new-customer defaults.
* Preserve the 49 operational schema keys: 29 Core/Operations + 16 Finance/Procurement + 4 legacy HR/payroll.
* The separate 37-table HR lifecycle extension remains Owner-approved and fingerprint-controlled; 33 HRX keys remain recognition/preservation only.
* Product Master STYLE/VARIANT identities, SKU mapping, stock movement ledger, reservations, audit, posted accounting and HR/payroll history remain authoritative.
* No automatic table creation or alteration based on a README/table count. Every new apparel schema has its own version, fingerprint, approval, backup and rollback evidence.
* No production DB/site write, real financial transaction, payroll disbursement or deployment is performed by this workspace.

## Work sequence

{% stepper %}
{% step %}
### AP-00

Exact source/archive/manifest/schema/route/permission reconciliation and protected baseline register.
{% endstep %}

{% step %}
### AP-01

Tenant-neutral org/location-purpose foundation with one-company compatibility.
{% endstep %}

{% step %}
### AP-02

Product Master style/variant metadata bridge.
{% endstep %}

{% step %}
### AP-03

Design Studio, Pattern Engineering, PLM, samples and Tech Pack.
{% endstep %}

{% step %}
### AP-04

Enquiry, costing, buyer order and demand.
{% endstep %}

{% step %}
### AP-05

Capacity/MPS/MRP and sourcing/procurement.
{% endstep %}

{% step %}
### AP-06

Roll/lot warehouse, incoming QC, material issue and cutting.
{% endstep %}

{% step %}
### AP-07

Sewing MES/SFC, operation WIP and special process.
{% endstep %}

{% step %}
### AP-08

QAM/SQC, CAPA, finishing, packing and FG WMS.
{% endstep %}

{% step %}
### AP-09

OMS/B2B/retail/eCommerce, dispatch, returns and traceability.
{% endstep %}

{% step %}
### AP-10/AP-11

Independent Finance and HR extraction.
{% endstep %}

{% step %}
### AP-12

Tenant platform operations, subscriptions, monitoring, backup/restore and customer onboarding.
{% endstep %}
{% endstepper %}

This gap register is source-informed planning, not implementation evidence. Each status must be updated only after exact source, tests, commit and artifact prove the runtime behavior.
