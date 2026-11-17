# SECURITY\_TENANCY\_AND\_DATA\_GOVERNANCE

## 1. Tenant provisioning strategy

The current Stockland installation is one configured company. The commercial platform must support separate customer companies without leaking tenant data.

**Recommended first commercial isolation boundary:** one isolated runtime/database and tenant-specific key material per customer from a shared, versioned codebase. Provisioning is orchestrated by a platform control plane; each tenant has its own database, object-storage prefix/bucket, secrets/keyring, backup and upgrade schedule.

This avoids retrofitting tenant\_id into all 49 historical Stockland keys as the first SaaS task. A future shared-database architecture is possible only after proving isolation for every query, unique key, index, route, export, notification, file path, cache, background job and audit view.

### New tenant defaults

* Start with blank legal/company/brand details.
* Do not seed Stockland Ethiopia, Central Warehouse, Goro, Gerji, Mexico or Megenagna names into a new customer.
* Let the customer define legal entities, brands, factories, warehouses, distribution centres, shops, channels, departments, fiscal calendars, currency, timezone and numbering series.
* Use stable `site_purpose` and `location_purpose` codes; display names remain editable labels.
* Manufacturing domains are absent/disabled until the customer chooses a manufacturer or vertically integrated profile and entitlement.

### Existing Stockland tenant

Preserve current company, branch/warehouse/location IDs, Product Master STYLE/VARIANT/SKU identities, opening balances, ledger history, approvals, reports, user roles and all posted business records. Stable-purpose mapping is an additive migration with before/after proof and compatibility fallback. Do not rename/reseed the current site as a SaaS onboarding shortcut.

## 2. Server-side access control

Permission decisions include:

* Tenant and legal entity.
* Site/factory/warehouse/branch/line/channel scope.
* Role, capability/module entitlement and current user status.
* Document owner and lifecycle state.
* Action type (read, upload, approve, release, issue, adjust, export, download, post, close).
* Data classification (public product data, commercial, supplier confidential, Finance, HR PII, private documents).

A WordPress nonce is CSRF protection, not authorization. Every REST callback validates the active profile/capability and location/document scope. UI hiding is only presentation; a direct API call must still be denied.

### Role families

Tenant Owner, Tenant Administrator, Merchandiser, Designer, Technical Designer, Pattern Master, Grader, Sample Room, Costing, Demand Planner, MRP Planner, Procurement, Supplier Collaborator, Factory Manager, Industrial Engineer, Cutting Supervisor, Cutting Operator, Sewing Supervisor, Sewing Operator, Quality Inspector, Quality Manager, Finishing/Packing, Raw-Material Warehouse, Finished-Goods Warehouse, Dispatch, Branch Manager, Store Manager, Cashier, Wholesale Sales, Customer Service, Finance, HR, Auditor and Integration Service.

Roles are tenant-defined templates with least-privilege scope. A Branch Manager is not an IT administrator. A picker sees assigned pick tasks only. A quality inspector can hold an item but cannot silently release their own hold where independent approval applies. Pattern Masters can approve geometry; Cutting Managers authorize a production cut release.

## 3. Segregation of duties

* Creator cannot approve their own document where maker/checker applies.
* A production planner may create a draft MRP proposal; procurement approves a purchase commitment.
* Pattern Master validates pattern/marker geometry; Cutting Manager approves physical release.
* Material Controller confirms roll issue; cutter records cut completion.
* QC inspector creates quality disposition; independent authority releases concession/hold if required.
* Branch manager approves branch-scoped actions; Central Warehouse authority cannot be inferred from branch scope or a display name.
* Finance app controls journal creation/posting and financial close.
* HR app controls compensation/payroll approval and payout; MES/production does not set employee pay.

## 4. File and CAD security

* Upload allowlist based on file type, signature/magic bytes and configured business need; never trust filename extension alone.
* Store original source bytes immutably with SHA-256, size, uploader, timestamp, tenant, access scope, parser version and malware-scan state.
* Reject path traversal, zip-slip, oversized archives, decompression bombs, macros/scripts, unsafe external links and unsupported CAD entities.
* Parse CAD/PDF/image files in a sandbox with CPU, memory, file-count and timeout ceilings. Do not allow arbitrary SVG scripts/external references.
* Convert imports into a canonical geometry/preview object; preserve the original and record any lossy conversion or unsupported element.
* Private file retrieval uses authorization and short-lived access path; file URLs never become globally public.
* Hash mismatch invalidates file use and raises an incident; do not rewrite a manifest to make mismatched bytes pass.

## 5. AI and model governance

* AI features are opt-in per tenant, with model/provider identity, version, region, retention, processing purpose and permitted fields recorded.
* No personal payroll, HR documents, tax IDs, employee home/contact details, bank details, customer secrets or vendor bank information is sent to an AI model.
* Pattern and marker assistant receives only scoped style geometry, approved pattern specs, material/roll/defect constraints and order ratios needed for the task.
* Imported file text and metadata are untrusted content, never instructions.
* Tool calls are typed and allowlisted. AI cannot approve, post, issue, allocate or run machine actions.
* Every AI result is a proposal with provenance and must pass deterministic validation and human approval.
* If the provider is unavailable or AI is disabled, the deterministic/manual workflow still works.

## 6. Immutable history and correction

Approved style/tech-pack/pattern revisions are immutable. Posted stock, shipment and Finance records are immutable. Changes append a version, reversal, adjustment, return, credit/debit or correction event. Preserve `created_by`, `approved_by`, `occurred_at`, reason, correlation and source hash.

## 7. Integration security

* Signed/authenticated service credentials with scoped audience/tenant and short-lived tokens.
* API contracts versioned and schema-validated; rate limit and replay protection.
* Outbox/inbox with event dedupe and dead-letter/reconciliation process.
* No API key or webhook secret in plugin files, Git, screenshots or audit payloads.
* Events include correlation/causation IDs; log identifiers and result state without sensitive business/PII payloads.

## 8. Observability and recovery

Capture code version, tenant/site scope, request/event ID, duration, result and error classification. Avoid salary, employee documents, private pattern content and confidential supplier/customer data in logs.

Backups cover database, document/object store, file metadata, encryption keys/keyring and tenant configuration separately. Test restore into an isolated environment and verify object hashes/decryption plus application reconciliation. A backup without key material is not a complete private-document recovery.

## 9. Schema and migration governance

* Existing 49 operational schema keys are protected.
* 37 HR lifecycle tables are Owner-approved and exact-fingerprint controlled; no automatic DDL from a count.
* 33 HRX keys are preservation-only.
* Every new apparel domain schema has independent version, exact DDL fingerprint, relationship map, tenant scope, backup receipt, reviewed migration, physical verification, rollback/forward-fix and DB engine matrix.
* No DROP/TRUNCATE/rename/reseed of existing Stockland core data.
* Every migration tests clean install, upgrade from protected baseline, replay/idempotence, rollback or forward repair, partial failure, orphan detection and row/quantity reconciliation.

## 10. AI-specific safety case for cutting

| Threat                              | Control                                                                                             |
| ----------------------------------- | --------------------------------------------------------------------------------------------------- |
| AI proposes invalid geometry        | Independent canonical geometry validator before preview or release                                  |
| AI invents fabric/roll availability | Inputs come from authorized inventory and QC records; AI cannot create stock                        |
| Prompt injection in CAD metadata    | Parse as untrusted data; no instruction/tool authority from file text                               |
| Wrong tenant pattern access         | Tenant/site-scoped lookup and file access on every tool call                                        |
| Unsafe machine command              | No machine start/cut tool exposed; cut release is a separate human-approved action                  |
| Stale marker after pattern change   | Bind candidate/approval to exact style/pattern/grade/roll hashes; stale input invalidates candidate |
| Model/provider outage               | Manual marker planning and deterministic solver remain available                                    |
| Data/IP leakage                     | Tenant opt-in, provider/retention controls, minimum payload and explicit CAD upload consent         |
