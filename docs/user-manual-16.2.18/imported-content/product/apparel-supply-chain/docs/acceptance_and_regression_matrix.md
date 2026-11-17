# ACCEPTANCE\_AND\_REGRESSION\_MATRIX

**Status:** test design only. No application/Codespace runtime was launched for this workspace. Every test must report `PASS`, `FAIL`, `BLOCKED`, `NOT RUN` or `OWNER INPUT` against an exact source commit and environment.

## Global invariants

1. Tenant A cannot read/update/search/export/download/report/notify/subscribe to Tenant B data, even where codes and display names match.
2. Existing Stockland 49 operational schema keys, company/location IDs, Product Master STYLE/VARIANT/SKU IDs, posted movements, Finance and HR history remain unchanged by apparel additions.
3. Manufacturing can be disabled entirely for retailer/wholesaler/outsourced-brand profiles; no required menu/API/table/job depends on it.
4. HR and Finance can be absent/unavailable without breaking Apparel core workflows; integration actions show pending/disabled state rather than fake success.
5. Every production order pins exact tech-pack, BOM, pattern, grade and routing revisions.
6. Only accepted/QC-usable material can be reserved/issued. QC hold/reject cannot be treated as supply.
7. Reservation does not physically reduce stock. Issue/ship is a distinct posted movement.
8. WIP reconciles by operation/bundle; no downstream operation consumes more than accepted upstream input.
9. QC-held/rejected output is not sellable, packable, allocatable or shippable.
10. Carton contents equal scanned variant quantities; sealed carton hash/SSCC/manifest is immutable.
11. Finance journals balance and are posted only by Finance; payroll is owned and finalized only by HR.
12. AI creates proposals only. No AI command approves pattern, issues material, releases cut or starts a machine.
13. Every external import/event/write has idempotency and concurrency safety.
14. Posted history is immutable; corrections are compensating records linked to originals.

## Test families

| ID family | Scope                | Example coverage                                                                                                                                   |
| --------- | -------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| TEN       | Tenant/organization  | Fresh tenant blank setup; duplicate style/SKU across tenants; cross-tenant search/file/report/cache/export deny                                    |
| AUTH      | Role and site        | Owner, Pattern Master, Cutting Manager, planner, procurement, operator, QC, warehouse, branch, Finance, HR; allow and deny every route/action      |
| PM        | Product/PLM          | Style, variant alias, size scale, colour, tech-pack revision, change impact, approval and immutable snapshot                                       |
| DES       | Design/pattern       | File parser, unit declaration, geometry validity, seam allowance, grainline, notch, mirror/pair, grade nest, fit/sample approvals                  |
| CAD       | Interchange          | Supported dialect/units, unsupported entities fail, source hash preserved, conversion report, export round-trip tolerance                          |
| SAMP      | Sample room          | Proto/fit/size-set/PPS transitions, QA reject/rework, measurement delta, buyer review and closure                                                  |
| COST      | Costing/commercial   | Material consumption, price, SMV, subcontract, currency/source date, costing revision, order breakdown and delivery split                          |
| MRP       | Planning             | Approved BOM explosion, usable/committed/inbound calculation, QC-held exclusion, shortage proposals, re-run and snapshot integrity                 |
| PROC      | Procurement          | PR approval, quote, PO approval, partial receipt, tolerance, landed charge, supplier return and Finance event                                      |
| IQC       | Incoming quality     | Roll/trim inspection, shade/width/GSM, hold/reject/concession, only accepted stock usable                                                          |
| WMS       | Warehouse            | Putaway, reservation, issue/return, transfer dispatch/receipt, bin/lot, cycle count, negative-stock prevention                                     |
| CUT       | Cutting/marker       | Size ratio, lay, marker hard constraints, defect avoidance, shade, ply, cutter limits, cut ticket and waste reconciliation                         |
| AI        | Cutting assistant    | Tool allowlist, prompt injection, no forbidden action, reproducible solver inputs/results, explanation grounded in solver outputs, AI-off fallback |
| MES       | Sewing/SFC           | Line assignment, operation dependency, scan idempotency, hourly output, WIP, downtime, split/merge, rework and correction                          |
| SUB       | Special process      | Vendor issue/return, custody, short/damaged output, QC, unreturned WIP and service cost reconciliation                                             |
| QAM       | Quality/CAPA         | Inspection plan, AQL config, critical/major/minor defects, hold, concession, rework, reinspection, CAPA close                                      |
| FIN       | Finishing            | Trims/pressing/measurement/label/metal checks, output, reject and final release                                                                    |
| PACK      | Packing              | Pack spec, scan contents, ratio, carton capacity, weight/dimensions, SSCC uniqueness, reprint/repack audit                                         |
| OMS       | Channels/fulfillment | B2B, wholesale, branch transfer, POS, eCommerce import, reserve, pick, gate scan, dispatch, status callback, return                                |
| HRX       | HR boundary          | Disconnected HR, minimal skill/availability events, no payroll/PII exposure, independently deployed test app                                       |
| FINX      | Finance boundary     | Disconnected Finance, pending events, duplicate/replay, balanced journal upon consumer processing, source document trace                           |
| REC       | Reconciliation       | Material, WIP, finished goods, shipment, AP/AR, stock valuation and GL tie-outs                                                                    |
| PERF      | Performance          | Large marker files, high style variants, large rolls/orders, high-volume scanning, bounded resource consumption                                    |
| SEC       | Security/privacy     | File scanning, auth/CSRF, IDOR, cross-tenant, secret scans, dependency/license/SBOM, API rate limits                                               |
| OPS       | Operations           | Backup/restore, key restore, event replay, service outage, migration partial failure, forward repair                                               |

## Mandatory end-to-end business scenarios

{% stepper %}
{% step %}
## E2E-01 — In-house garment production

Create a synthetic tenant with a garment manufacturer profile, factory, raw-material/QC/WIP/FG warehouses, lines and scoped roles.

Create a style, sizes/colours, design assets, technical flat, pattern pieces, grade, BOM and route.

Build proto/fit/size-set/PPS samples; reject one sample, create revision/rework, approve the final sample.

Create buyer order with a size-colour ratio and delivery split. Approve costing snapshot.

Run capacity/MRP. Include insufficient fabric and show a shortage proposal without automatic PO.

PR/RFQ/PO; receive fabric partially. Accept one roll, hold a second and reject a third. Only accepted quantity counts as supply.

Reserve material, allocate shade-compatible rolls, issue to lay. Reconcile issue/return/waste.

Generate marker proposals using the deterministic engine and optional AI chat. Compare candidates, approve one, release cut ticket.

Cut panels/bundles, record defect/re-cut/waste, reconcile roll consumption and bundle counts.

Run sewing operation scans, hourly board, downtime, WIP transfer, rejects and rework.

Use a special process/subcontract branch, with material custody and returned quantity.

Inline/endline/final QC; hold failed goods and allow rework/reinspection; release only accepted goods.

Finish garments, run final checks, pack/cartonize, scan each carton, create SSCC only with the tenant's own licensed prefix.

Receive into FG warehouse, allocate/pick/verify/dispatch and emit Finance event.

Run reverse trace from carton to roll/operation and from failed roll to all affected customers/orders.

**Pass:** all stock, WIP, cut, QC, carton, shipment and financial quantities reconcile. Held material/goods never enter usable or sellable stock. No duplicate scans/postings. AI never issues cut release.
{% endstep %}

{% step %}
## E2E-02 — Outsourced brand without owned factory

Enable PLM, samples, costing, sourcing, supplier collaboration, external QC, inbound FG, OMS and wholesale; disable owned MRP/MES/SFC. Process style/sample/buyer order → external factory milestone → inbound cartons → QC → FG warehouse → shipment → Finance event.

**Pass:** all business operations complete without factory execution tables, background workers or UI. Material/quality/carton traceability remains linked to supplier and buyer order.
{% endstep %}

{% step %}
## E2E-03 — Retailer / wholesaler without manufacturing

Create a retailer/wholesaler tenant. Supplier PO/GRN/QC → putaway → B2B/store order → reserve/pick/scan/pack/ship/POS → returns. No MRP/cutting/sewing module is installed or required.

**Pass:** no manufacturing dependencies and stock/order states reconcile.
{% endstep %}

{% step %}
## E2E-04 — Design/pattern change after buyer order

Approve tech pack/pattern; create buyer order and MRP snapshot; revise a sleeve measurement and pattern. Assess impacted open orders/markers/WIP. Require explicit change/hold/waiver; never mutate an already approved marker/cut ticket silently.

**Pass:** revision history and impact graph show exactly which orders/markers require reapproval.
{% endstep %}

{% step %}
## E2E-05 — QC failure and traceability

Fail incoming roll inspection or final garment inspection. Create hold/CAPA and trace affected lay, cut bundles, production, cartons, shipments and customer. Perform disposition and, if needed, customer/supplier claim.

**Pass:** full forward/reverse lineage is queryable and original transactions remain intact.
{% endstep %}

{% step %}
## E2E-06 — HR and Finance independence

Run Apparel with HR and Finance disconnected. Verify design, materials, production, quality and warehouse still function; Finance-owned settlement shows pending integration. Deploy/upgrade each application independently and reconnect through versioned event contracts.

**Pass:** no direct table dependency/write across app boundaries; replay/reconciliation succeeds.
{% endstep %}

{% step %}
## E2E-07 — Tenant isolation and same-code collision

Create two tenants with identical `ST-001`, `SKU-001`, supplier name and warehouse label. Test direct ID probing, search, export, attachments, reports, notifications, event replay and cache isolation.

**Pass:** no cross-tenant read or mutation under any role/route.
{% endstep %}
{% endstepper %}

## Quantity and finance reconciliations

* **Material by item/lot/location:** opening + accepted GRN + accepted returns + transfer receipts − issues − supplier returns − transfers out − approved adjustments = closing.
* **Cutting:** issued fabric = accepted cut consumption + approved remnant return + recorded waste + documented variance.
* **Operation:** accepted predecessor input = accepted next-stage output + reject + rework/hold + scrap + remaining WIP + documented variance.
* **Finished goods:** final QC release = packed + unpacked + approved hold/scrap/variance.
* **Shipment:** confirmed order quantity = shipped + open/backorder + cancelled/approved short quantity.
* **Finance:** AP/AR subledger balances reconcile with posted GL; inventory valuation, WIP and COGM tie to Finance-owned accounts; each journal balances.
* **HR:** compensation/payroll/reconciliation occurs inside HR. Apparel receives only approved minimal events.

## Release gate evidence

Each test receipt must include:

`test_id`, source commit, package SHA-256, environment, PHP/WordPress/Node/Python/database versions, tenant fixture, role/site, request/event IDs, expected, actual, status, logs/artifact, database snapshot hash, backup/restore receipt, reviewer and date.

Local syntax/contract tests, disposable WP database tests, browser/UI tests, security scans, production-host tests and legal/statutory approvals are separate evidence classes. Do not roll them into one green badge.
