# WORKFLOW\_STATE\_MACHINE\_CATALOG

**Status:** proposed transition contracts. Each final state map must be checked against the current Stockland authority/workflow services before runtime integration.

## Shared transition envelope

Every state transition request carries:

* `aggregate_id`, `expected_version`, `command`, `reason` where required
* Authenticated user, tenant, legal entity, site and object scope from the server session (never trusted from the request body)
* Idempotency key and correlation/request ID
* Evidence/document revision IDs where the transition requires them

The domain service validates actor, scope, current state, prerequisites, reference revisions, quantity and concurrency. It writes the state change, domain event, audit event and outbox record transactionally. A failed write cannot report success. Invalid transitions return a conflict/state error, not an automatic state correction.

## Design and engineering

| Aggregate      | Allowed path                                                                                                                                                              | Required gate                                                                                                         |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Design brief   | `DRAFT → IN_REVIEW → APPROVED → SUPERSEDED`; review may `RETURN_TO_DRAFT` or `REJECT`                                                                                     | Design Lead approves target-market/collection/brand intent; linked AI concept assets remain `AI_DRAFT` until reviewed |
| Technical flat | `DRAFT → IN_REVIEW → APPROVED → SUPERSEDED`                                                                                                                               | Front/back/side view, construction annotations and revision hash verified                                             |
| Pattern set    | `DRAFT → VALIDATION_FAILED / GEOMETRY_VALIDATED → IN_REVIEW → APPROVED → SUPERSEDED`                                                                                      | Geometry and file integrity validated; Pattern Master approves; invalid pattern cannot enter grading/marker           |
| Grade set      | `DRAFT → GENERATED → VALIDATED → IN_REVIEW → APPROVED → SUPERSEDED`                                                                                                       | Every target size valid, POM tolerances pass and required size-set sample approved                                    |
| Tech pack      | `DRAFT → IN_REVIEW → APPROVED → SUPERSEDED`; review may return/reject                                                                                                     | Design, measurement, pattern, grade, BOM, routing, care, label, pack and quality references pinned to exact revisions |
| Sample         | `REQUESTED → PROTO → FIT → SIZE_SET → PRE_PRODUCTION → QA_REVIEW → BUYER_REVIEW → APPROVED`; conditional stages require configured routing; `REWORK` loops to prior stage | Stage transition records evidence, owner, time and reason; buyer handover requires required QA/sample approval        |

## Commercial and planning

| Aggregate     | Allowed path                                                                                                                        | Required gate                                                                                                                              |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Enquiry       | `DRAFT → SUBMITTED → QUOTED → ACCEPTED / DECLINED / EXPIRED`                                                                        | Buyer identity, style/spec revision, quantity/ratio, delivery dates and terms                                                              |
| Cost sheet    | `DRAFT → IN_REVIEW → APPROVED → SUPERSEDED`                                                                                         | Cost basis, currency, approved BOM/routing, rate source and versioned margin assumptions                                                   |
| Buyer order   | `DRAFT → CREDIT_REVIEW → CONFIRMED → ALLOCATED → PARTIALLY_FULFILLED → FULFILLED → CLOSED`; hold/cancel paths are reason-controlled | Approved price/spec snapshot, buyer terms, size-colour quantities, delivery schedule and customer credit policy                            |
| Capacity plan | `DRAFT → VALIDATED → APPROVED → SUPERSEDED`                                                                                         | Factory calendar, machine/line capacity, shift/skill constraints, approved routing/SMV and exception disposition                           |
| MRP run       | `DRAFT → CALCULATED → REVIEWED → APPROVED / SUPERSEDED`                                                                             | Snapshot of demand, approved BOM, material availability states, reservations, confirmed inbound and lead times. MRP creates proposals only |

## Procurement and material

| Aggregate            | Allowed path                                                                                                                      | Required gate                                                                                                      |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Purchase requisition | `DRAFT → SUBMITTED → APPROVED → SOURCING → CONVERTED_TO_PO / CANCELLED`                                                           | Required-by date, site, item/UOM, quantity, reason, budget/cost centre and approver scope                          |
| RFQ / supplier award | `DRAFT → ISSUED → QUOTES_RECEIVED → COMPARED → AWARDED / CLOSED`                                                                  | Supplier eligibility, comparison inputs, deviation reason and award approval                                       |
| Purchase order       | `DRAFT → IN_REVIEW → APPROVED → SENT → CONFIRMED → PARTIALLY_RECEIVED → RECEIVED → CLOSED`; change creates a revision             | Approved lines/terms/currency, supplier confirmation and amendment approval                                        |
| ASN                  | `DRAFT → SUBMITTED → ACKNOWLEDGED → ARRIVED → RECONCILED`                                                                         | ASN references approved PO, shipment/roll details and expected date                                                |
| GRN                  | `DRAFT → RECEIVED → QC_PENDING → PARTIALLY_ACCEPTED / ACCEPTED / REJECTED → POSTED`                                               | PO match, actual count/UOM, QC disposition and lot identity; accepted usable quantity only enters available supply |
| Fabric roll          | `RECEIVED → QC_PENDING → ACCEPTED / QUARANTINED / REJECTED → RESERVED → ISSUED / PARTIALLY_ISSUED → RETURNED / CONSUMED / CLOSED` | Shade/lot, width, length, quality state, storage location and movement ledger references                           |

## Cutting and factory execution

| Aggregate         | Allowed path                                                                                                                                                            | Required gate                                                                                                     |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Production order  | `DRAFT → APPROVED → SCHEDULED → MATERIAL_READY → RELEASED → IN_PROGRESS → PARTIALLY_COMPLETED / COMPLETED → QC_HOLD / QC_RELEASED → CLOSED`; cancellation is controlled | Buyer order, exact approved technical revisions, capacity/material gates and site authority                       |
| Lay request       | `DRAFT → CONTEXT_VALIDATED → ROLL_SELECTED → READY_FOR_MARKER → MARKER_CANDIDATE_GENERATED → HUMAN_REVIEW`                                                              | Style/order ratio, approved pattern/grade, accepted rolls, shade/defect map and lay constraints                   |
| Marker candidate  | `GENERATED → VALIDATED → PATTERN_MASTER_APPROVED → CUTTING_MANAGER_APPROVED → RELEASED_TO_CUT`; failed candidates `REJECTED`                                            | Exact input/output hashes and independent deterministic validator; changing any input invalidates approval        |
| Cut ticket        | `DRAFT → APPROVED → MATERIAL_ISSUED → CUT_IN_PROGRESS → CUT_RECONCILED → CLOSED`                                                                                        | Approved marker, physical roll issue, lay/ply and cut quantity reconciliation                                     |
| Bundle            | `CUT_ACCEPTED → IN_QUEUE → IN_PROGRESS → PARTIAL_OUTPUT → COMPLETE`; may enter `QC_HOLD`, `REWORK`, `REJECTED`                                                          | Valid cut ticket, exact size/colour, piece quantity and predecessor operation output                              |
| Operation task    | `READY → STARTED → PAUSED → RESUMED → COMPLETED`; `BLOCKED`, `REWORK` or `QC_HOLD` are controlled exceptions                                                            | Line/workstation/skill/machine eligibility, bundle availability, expected operation sequence and idempotent scans |
| Subcontract order | `DRAFT → APPROVED → GATE_PASS_ISSUED → VENDOR_RECEIVED → IN_PROCESS → PARTIAL_RETURN → RETURNED → QC_PENDING → CLOSED`                                                  | Material ownership, issued/returned quantity, service rate, vendor QC and WIP custody reconciliation              |

## Quality and fulfillment

| Aggregate     | Allowed path                                                                                                                  | Required gate                                                                                               |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Inspection    | `PLANNED → OPEN → SAMPLED → ACCEPTED / REJECTED / HOLD → DISPOSITIONED`                                                       | Approved inspection plan and sample rule; inspector/site scope and evidence                                 |
| Quality hold  | `OPEN → CONTAINED → REWORK / REINSPECT / CONCESSION_REVIEW / SCRAP → RELEASED / CLOSED`                                       | Authorized disposition; no sellable availability while held                                                 |
| CAPA          | `OPEN → CONTAINED → ACTION_IN_PROGRESS → VERIFICATION → CLOSED`; ineffective action may `REOPEN`                              | Named owner, due date, evidence and effectiveness check                                                     |
| Finishing lot | `READY → IN_PROGRESS → FINISHED → FINAL_QC_PENDING → RELEASED / REWORK / REJECTED`                                            | Required finishing operations, count and final inspection                                                   |
| Carton        | `OPEN → SCAN_IN_PROGRESS → VALIDATED → SEALED → PUTAWAY → ALLOCATED → DISPATCHED`                                             | Scanned variant contents, pack spec, released QC quantity, weights/dimensions and unique SSCC/internal ID   |
| Shipment      | `DRAFT → PICK_WAVE → PICKED → VERIFIED → PACKED → DISPATCH_READY → SHIPPED → DELIVERED`; hold/short/cancel paths are separate | Allocation, reservation, scan verification, sealed cartons, shipping documents and dispatch authorization   |
| Return        | `REQUESTED → RECEIVED → QC_PENDING → RESTOCK / REPAIR / SCRAP / SUPPLIER_CLAIM → CLOSED`                                      | Original shipment/order reference, condition, new movement and Finance credit/refund event where applicable |

## Inventory movement semantics

A command must name the intended movement type. State transitions do not imply a physical stock movement unless the domain command explicitly posts one.

| Command                     | Physical quantity                                 | Reservation                            | Required lineage                                                |
| --------------------------- | ------------------------------------------------- | -------------------------------------- | --------------------------------------------------------------- |
| `RESERVE`                   | unchanged                                         | increases                              | order/production line, variant/material lot and source location |
| `RELEASE_RESERVATION`       | unchanged                                         | decreases                              | original reservation and reason                                 |
| `ISSUE_MATERIAL`            | decreases raw material                            | clears matching reservation            | production order, roll/lot, lay or job, quantity and receiver   |
| `RETURN_UNUSED_MATERIAL`    | increases raw/available only after QC/disposition | reconciles issue reservation           | original issue, source roll and quantity                        |
| `TRANSFER_DISPATCH`         | source decreases / in-transit increases           | unchanged unless explicitly reassigned | transfer, source/destination, manifest                          |
| `TRANSFER_RECEIPT`          | destination increases / in-transit decreases      | reconciles if reserved transfer        | received quantity, variance and receiver                        |
| `SHIP_FINISHED_GOODS`       | FG decreases                                      | matching order reservation clears      | pick verification, carton, shipment and order                   |
| `PHYSICAL_COUNT_ADJUSTMENT` | changes only after approved variance posting      | separately reconciled                  | count session, counters, variance and approval                  |

## Separation from HR and Finance state

* Apparel emits operational facts/events; Finance creates/approves/posts journals and controls period close. A shipment event is not a paid invoice.
* Apparel may send approved skill/shift availability/output references to HR; HR owns worker identity, attendance, compensation and payroll. An MES hour event is not a payroll line.
* HR/Finance disconnected states must be explicitly visible (`INTEGRATION_PENDING`, `NOT_CONNECTED`, `RETRY_REQUIRED`); Apparel may continue nondependent operations without claiming an external action completed.

## Universal transition-denial tests

Every transition must reject:

* Wrong tenant/site
* Missing capability
* Invalid object state
* Stale version
* Missing upstream approval
* Missing source hash
* Duplicate idempotency key with conflicting payload
* Over-quantity
* Wrong predecessor operation
* QC hold
* Quarantined material
* Missing audit event
* Out-of-scope file
* Failed DB transaction

No rejected command may leave a partial ledger movement, outbox event or state update.
