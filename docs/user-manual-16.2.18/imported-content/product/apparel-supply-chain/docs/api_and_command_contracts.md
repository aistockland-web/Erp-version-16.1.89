# API\_AND\_COMMAND\_CONTRACTS

**Status:** proposed API contract. Routes are not implemented by this workspace. The route names below follow the current WordPress `stockland/v16` namespace and existing authorization conventions; final names must be reconciled against the latest canonical route index before code is added.

## 1. API design rules

* Every route has `permission_callback` plus callback/service scope validation. A nonce is not permission.
* Controllers parse/validate identifiers and request bodies, authorize, invoke a domain service and return a versioned response. No business state is mutated in the browser.
* GET is read-only. POST creates or commands a domain transition. PATCH updates a mutable draft where a domain permits. DELETE is not used for posted/approved/traceable records.
* All writes include an idempotency key. Concurrency uses a revision/ETag or aggregate version; stale updates return `409 Conflict`.
* Server-computed totals and identifiers are returned; client-provided amount/stock totals are never trusted.
* Errors use stable code, user-safe message, field errors, correlation ID and retryability. Do not leak SQL, table names, stack traces or secrets.
* Pagination, filtering and sorting are bounded/allowlisted. Search and export respect the same tenant/site/role scope as record reads.

## 2. API namespaces by context

| Context               | Example route family                                                                          | Commands                                                                                    |
| --------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Style/product adapter | `/apparel/styles`, `/apparel/styles/{id}/variants`                                            | Create draft style, add style attributes, resolve existing Product Master identity          |
| Design Studio         | `/apparel/styles/{id}/design-brief`, `/design-assets`                                         | Save brief, register scanned asset, submit design review                                    |
| Pattern Engineering   | `/apparel/styles/{id}/patterns`, `/patterns/{id}/validate`, `/patterns/{id}/grade`            | Import CAD source, validate geometry, generate grade proposal, request review               |
| PLM/Tech Pack         | `/apparel/styles/{id}/tech-packs`, `/tech-packs/{id}/submit`, `/approve`                      | Create revision, update draft, submit, return, approve                                      |
| Samples               | `/apparel/samples`, `/samples/{id}/transition`, `/fit-sessions`                               | Request sample, assign stage, record fit session, accept/rework/reject                      |
| Costing/Orders        | `/apparel/cost-sheets`, `/apparel/enquiries`, `/apparel/buyer-orders`                         | Calculate draft, approve costing, quote, confirm buyer order                                |
| Planning/MRP          | `/apparel/planning/runs`, `/mrp/runs/{id}/shortages`                                          | Create run snapshot, list shortages, convert selected suggestion to draft PR                |
| Sourcing              | `/apparel/suppliers`, `/apparel/rfqs`, `/apparel/purchase-requisitions`                       | Quote supplier, compare, approve award, emit PO draft to existing procurement               |
| Production            | `/apparel/production-orders`, `/production-orders/{id}/release`                               | Create, approve, schedule, release, hold, close                                             |
| Cutting/Marker        | `/apparel/lay-requests`, `/marker-candidates`, `/marker-candidates/{id}/validate`, `/approve` | Create request, generate candidate, validate, submit for human approval, release cut ticket |
| MES/SFC               | `/apparel/bundles/{id}/operations`, `/operation-events`                                       | Scan start/pause/complete/output/transfer/rework/downtime                                   |
| Quality               | `/apparel/inspections`, `/quality-holds`, `/capa`                                             | Inspect, hold, disposition, rework, reinspect, release                                      |
| Packing/WMS           | `/apparel/cartons`, `/cartons/{id}/lines`, `/cartons/{id}/seal`                               | Scan contents, validate ratio, seal carton, putaway                                         |
| OMS/Fulfillment       | Existing sales/wholesale/POS interfaces plus `/apparel/fulfillment/*` adapters                | Allocate, pick, verify, dispatch, return/claim                                              |
| Traceability          | `/apparel/traceability/orders/{id}`, `/lots/{id}`                                             | Forward and reverse trace lookup                                                            |

## 3. Critical command contracts

### Submit tech-pack revision

**Request:** revision ID, expected revision/version, submission reason and idempotency key.

**Checks:** style scope, pattern/grade/BOM/routing references valid and approved where required, required fields complete, no unknown nested fields, file hashes valid, actor may submit.

**Result:** immutable submitted snapshot and workflow/audit reference.

### Approve tech-pack revision

**Request:** revision ID, expected version, reason.

**Checks:** user is authorized reviewer/Owner or Management per tenant rule, creator/submitter separation, revision still in review, all mandatory evidence attached.

**Result:** approved snapshot; later edits create a new revision.

### Generate marker candidate

**Request:** lay request ID, exact pattern/grade revision, roll/lot IDs, constraint profile and solver profile, candidate count limit.

**Checks:** tenant/site scope, approved order/style revision, QC-accepted rolls, valid geometry, current material snapshot, maximum resource budget.

**Result:** candidate IDs, validation metrics, preview references and hashes. No stock movement or cut release.

### Approve marker

**Request:** candidate ID/hash, approval reason, expected state.

**Checks:** independent Pattern Master and Cutting Manager approvals where required; candidate validated; exact roll/grade/pattern/order snapshot still current; roll reservation/issue state valid; no input mutation.

**Result:** approval record tied to marker hash. Release of cut ticket is a separate command.

### Release cut ticket

**Request:** approved marker ID/hash, material issue reference, lay/ply count and production order version.

**Checks:** approved marker, approved order, accepted roll/QC, valid material issue, machine profile and cutting role; quantity and roll reconciliation; no stale revision.

**Result:** cut ticket and bundle identities. This is the first command that can hand off material to cutting; it is never exposed to the AI chatbot.

### Record operation event

**Request:** bundle ID, operation ID, event type, quantity fields, actor, machine/line, timestamp and idempotency key.

**Checks:** bundle current operation, predecessor accepted, quantity available, site/line scope, actor role, no duplicate event, expected bundle state.

**Result:** immutable event and derived WIP update.

### Seal carton

**Request:** carton ID, content manifest hash, pack spec revision, SSCC if used and expected version.

**Checks:** all lines from scanned content; released QC; order allocation; exact content/weight/dimensions; unique SSCC in issuer scope; no open exceptions.

**Result:** sealed immutable manifest and FG WMS handoff.

## 4. Response envelope

```json
{
  "success": true,
  "data": {},
  "meta": {
    "schema_version": 1,
    "request_id": "uuid",
    "correlation_id": "uuid",
    "aggregate_version": 3
  }
}
```

### Example error

```json
{
  "success": false,
  "error": {
    "code": "CANDIDATE_STALE",
    "message": "The pattern or fabric snapshot changed. Generate a new marker candidate.",
    "retryable": false,
    "field_errors": []
  },
  "meta": {"request_id": "uuid", "correlation_id": "uuid"}
}
```

These are interface examples, not live responses. The `success` envelope must not be returned if the DB write/audit/event transaction fails.

## 5. External integrations

* Supplier portal/EDI: supplier identity, RFQ/PO/ASN/ship notice/receipt acknowledgments, validation maps and error queue.
* CAD/CAM: importer/exporter adapters with verified format dialect and machine-specific capability profile.
* Barcode/scanners: variant/roll/bundle/carton identity; idempotent scan event.
* BI/analytics: read model/export scoped to tenant and role; no direct raw database credentials.
* HR/Finance: separate event/API adapters, no direct DB writes.
* AI provider/optimizer: tenant opt-in, minimum payload, fixed tool allowlist, bounded latency/cost and deterministic fallback.

## 6. API contract tests

Every command has a positive fixture, missing required reference, invalid state, wrong tenant, wrong site, wrong role, stale aggregate version, duplicate idempotency key, concurrent replay, audit write failure, downstream event failure and partial DB failure case. Read routes are tested for scope and data classification. Export/download routes are tested for object-level authorization and tenant/file-path isolation.
