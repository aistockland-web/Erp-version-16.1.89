# ENTERPRISE\_ARCHITECTURE

## 1. Decision summary

**Keep the existing Stockland ERP PHP/WordPress modular monolith as the compatibility runtime for the current Stockland tenant.** Add garment domains incrementally with strict service/API boundaries. Do not migrate the current operational product wholesale to .NET, Spring, Go, Node/Express, MongoDB or a second SPA because the reference repositories use them.

The target commercial SaaS architecture may later host the new Apparel, HR and Finance applications as independently deployable services/packages. That is a new product platform architecture and requires separate deployment/security/operations decisions. It does not authorize replacing the current plugin or rewriting existing data.

### Architecture decision records

| ADR     | Decision                                                                                                  | Why                                                                                                |
| ------- | --------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| ADR-001 | Preserve current WordPress/PHP operational runtime first                                                  | Existing ERP, source code, database model, shared hosting and user workflows must continue working |
| ADR-002 | Modular Apparel domain boundaries inside current codebase for the first increments                        | Allows reviewable increments without a broad rewrite or duplicate ledger                           |
| ADR-003 | New commercial tenants begin with isolated runtime/database boundaries from a shared codebase             | Avoids unsafe retrofit of tenant keys into every historical legacy table as the first move         |
| ADR-004 | HR and Finance are separate application/data owners                                                       | They must be independently licensed, deployed, versioned and usable without Apparel coupling       |
| ADR-005 | Manufacturing modules are optional per customer profile                                                   | Retailers, wholesalers and outsourced brands do not require owned factory execution                |
| ADR-006 | AI is a proposal/orchestration layer; deterministic services own validated geometry and quantity outcomes | Prevents generated text/image from becoming pattern, marker, stock or cut authority                |
| ADR-007 | No new CSS framework/source in this increment                                                             | The existing local Bootstrap and ordered Stockland CSS modules are the design system of record     |

## 2. Target application topology

```
SaaS Control Plane
  ├─ Tenant Provisioning / Identity Federation / Subscription Entitlements
  ├─ Tenant Lifecycle / Backup / Upgrade / Support / Observability
  └─ Tenant Deployment Registry
       │
       ├── Tenant Runtime A (isolated database, keyring, file boundary)
       │    ├── Apparel Supply Chain
       │    ├── optional HR & Workforce app
       │    └── optional Finance app
       ├── Tenant Runtime B (isolated database, keyring, file boundary)
       │    ├── Apparel Supply Chain (retailer profile; manufacturing disabled)
       │    └── optional Finance app
       └── Tenant Runtime N ...
```

For the first commercial rollout, per-tenant deployment/database isolation is the recommended baseline. Shared-database tenant scoping may be reconsidered only after a threat model, data partition model, tenant-aware unique-key review and comprehensive isolation proof. Do not retrofit shared-database tenant\_id columns across the current 49 operational keys as the first step.

## 3. Apparel bounded contexts

| Context                           | Owns                                                                                                     | Depends on                                                                 |
| --------------------------------- | -------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Organization & Tenant Adapter     | Tenant config, legal entity, sites, location purpose, customer profile                                   | SaaS control plane; legacy Stockland compatibility adapter                 |
| Apparel Product Master Adapter    | Style extensions, garment attributes, season, collection, size scale, colour, SKU alias                  | Existing Product Master STYLE/VARIANT identities                           |
| Design Studio                     | Brief, concept, flats, artwork, design approval, versioned media refs                                    | Product Master style, tenant file vault                                    |
| Pattern Engineering               | Blocks, pattern pieces, geometry, seam/cut lines, grade rules, pattern revision, CAD adapters            | Style, measurement spec, approved fit feedback                             |
| PLM & Tech Pack                   | Spec, measurements, tolerance, BOM revision, routing, care/packaging, technical documents                | Product Master, design/pattern, sample approvals                           |
| Sample Room                       | Proto/fit/size-set/PP samples, tasks, comments, measurements, status                                     | PLM revision, sample material reservation, quality                         |
| Costing & Merchandising           | Enquiry, cost sheet, quote, buyer order, order breakdown, delivery schedule                              | Approved PLM/BOM/routing, supplier prices, Finance rate feeds if connected |
| Sourcing & Supplier Collaboration | Supplier/mill/factory master, RFQ/quotes, PO reference, shipment milestones, material certification      | Procurement adapter, quality and supplier portal                           |
| Demand & MRP                      | Forecast, confirmed demand, BOM explosion, material shortage, capacity plan                              | Approved order/BOM/routing, warehouse availability, supplier confirmations |
| Production Control                | Production order, operation route, job card, operation events, WIP                                       | MRP, released materials, factory capacity                                  |
| Cutting & Marker                  | Lay, roll allocation, marker candidates, cut tickets, bundles, waste/re-cut                              | Pattern geometry, size ratio, fabric roll/QC, production order             |
| MES/SFC                           | Workcentres, lines, scans, output, downtime, WIP transfers, rework                                       | Production order/routing, HR skill availability if connected               |
| QAM/SQC                           | Inspection plan, defect, quarantine, disposition, CAPA, release                                          | Material lots, operations, final goods/cartons                             |
| WMS                               | Raw/WIP/FG location, bin, lot/roll/shade, putaway, move, reservation, pick/pack/ship                     | Existing Stockland inventory ledger adapter; production/quality/OMS events |
| OMS / Channels                    | Wholesale/B2B orders, retail orders, POS imports, eCommerce/EDI orders, allocation, fulfillment, returns | Product, warehouse, customer, Finance event adapter                        |
| Traceability & Compliance         | Material genealogy, style/order/lot/bundle/carton/shipment chain, certificates and ESG evidence          | Events from every above domain                                             |
| Analytics                         | Operational KPIs, exceptions, demand/production/quality/fulfillment dashboards                           | Read models from domain-owned data and source events                       |

## 4. Runtime/deployment boundary

### Current Stockland deployment

* Existing plugin runs in WordPress with PHP 8.3/8.4, WordPress REST, MySQL/MariaDB, local Bootstrap 5.3.8 and existing PWA/frontend.
* Node 22 and Python 3.12 in Codespaces/CI are build and test tools; they are not production-host services.
* Preserve `Stockland_DB`, authorization, workflow, audit, notifications, ledger, money helpers and existing route patterns.
* New apparel services should be namespaced and loaded only after dependency classes; module-disabled tenants must not trigger unavailable schema or background work.

### Future commercial SaaS platform

* Tenant lifecycle: provision, pause, backup, upgrade, rotate keys, restore, archive and deprovision with explicit retention controls.
* Applications may become separate deployables when each has stable ownership, API contracts, versioned migrations, release gates and support operations.
* AI/marker optimization may be an optional external tenant-scoped service. It is never required to run a normal Apparel transaction, and no paid provider is enabled by default.

## 5. Cross-app contracts

No shared database transaction is assumed between independently deployed applications. Use an outbox/inbox, idempotency, event replay, DLQ/exception queue, contract versioning and reconciliation.

| Event                         | Producer        | Consumer    | Data minimization                                                                                            |
| ----------------------------- | --------------- | ----------- | ------------------------------------------------------------------------------------------------------------ |
| `MaterialReceiptAccepted.v1`  | Apparel/WMS     | Finance     | Supplier, PO/GRN, accepted item/lot, quantity, valuation references, entity and currency                     |
| `ShipmentConfirmed.v1`        | Apparel/OMS     | Finance     | Order, delivery, customer invoice reference, quantity, amount/tax code refs                                  |
| `ProductionCostFinalized.v1`  | Apparel/Costing | Finance     | Approved cost summary, production order, site, WIP/COGM categories                                           |
| `WorkerSkillAvailability.v1`  | HR              | Apparel/MES | Worker pseudonymous reference, skill code, eligibility, availability window; no salary or personal documents |
| `ProductionOutputAccepted.v1` | MES/SFC         | HR          | Worker reference, operation, accepted quantity, approved incentive evidence; HR decides payroll rules        |
| `CreditStatusChanged.v1`      | Finance         | Apparel/OMS | Customer/account key and status/available credit only                                                        |

## 6. Source-of-truth and version rules

* Style/variant/SKU identity remains in existing Product Master.
* Each buyer order line pins style, colour/size ratio, tech pack, BOM, pattern, grade, routing and approved cost revisions.
* Material quantity and location truth remains in the existing single inventory ledger/stock service.
* WIP is a traceable operational state derived from accepted input/output events; it is not a second stock ledger.
* Finance owns accounting currency, journal lines, GL posting and period close.
* HR owns employee identity, compensation, statutory rules, payroll calculation and payout/remittance.
* A posted event is immutable. Corrections are compensating events linked to the original event.

## 7. Pattern/marker/AI architecture

```
Designer / Pattern Master UI
  -> versioned Style + Pattern + Grade + Tech Pack inputs
  -> file quarantine and CAD parser sandbox
  -> canonical Pattern Geometry model
  -> deterministic validation
  -> Marker Optimization service (rule/solver engine)
  -> candidate placements and metrics
  -> SVG/PDF preview and machine-format adapter
  -> independent validation and diff
  -> Pattern Master approval + Cutting Manager release
  -> Cut Ticket / roll issue / bundle generation
```

The conversational AI does not compute or authorize coordinates. It uses an allowlisted tool interface to call the deterministic validator/solver, explain warnings and prepare a proposal. The same request can be run with AI disabled. Every output binds source style revision, pattern hash, grade revision, order size ratio, fabric roll/shade lot, solver version/configuration and result hash.

## 8. Non-functional architecture requirements

* Consistent tenant isolation for queries, routes, caches, queues, reports, exports and file object paths.
* Exact role/location/document scope checks on server-side actions.
* Idempotent event handlers and safe concurrency for reservations, picking, marker numbering, cutting output and posting.
* Bounded PHP request time/memory; long tasks are resumable in the commercial SaaS control plane, not hidden WP-Cron jobs on shared hosting.
* Audit and event correlation with no HR/PII values in diagnostic logs.
* Accessibility target WCAG 2.2 AA review; current repo PR #134 adds axe browser gates but is still open and not part of this folder's CSS.
* Backups for database, files and keys separately; restore tests must verify decryption and file hashes.
* Dependency, license and secret inventories for every released component.
