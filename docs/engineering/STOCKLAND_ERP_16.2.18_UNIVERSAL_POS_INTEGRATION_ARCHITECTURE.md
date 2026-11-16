# Stockland ERP 16.2.18 — Universal POS & External Systems Integration Architecture

**Workstream:** isolated successor work for the installed 16.2.18 product
**Owner scope:** the current 16.2.18 ERP only; do not merge a separate ERP project/version line
**Database compatibility target:** installed Core/Accounting/HR baseline as observed by the Owner; this change must not add tables or alter existing table contracts
**Status vocabulary:** implemented, verified, adapter required, not connected, blocked by vendor evidence

## 1. Product decision

Stockland is to support multiple third-party POS manufacturers, POS software products, fiscal devices, payment terminals and branch-computer solutions through one vendor-neutral integration hub.

There is no universal POS wire protocol. A supplier may expose REST, webhooks, a local desktop service, a manufacturer SDK, a vendor cloud, a supported file export, or no external integration interface at all. Stockland therefore owns one internal transaction contract and a collection of independent provider adapters. An adapter translates a provider's supported protocol into the Stockland contract; it does not replace Stockland business rules.

FIS-NANO is one example device only. Its published user manual describes PC Manager connectivity and operational/reporting functions, but it is not proof of a supported transaction API. It remains ADAPTER_REQUIRED / NOT_CONNECTED unless DataMega supplies an approved technical interface and test access.

## 2. System boundary

Every inbound POS sales source must enter the existing Daily POS import workflow:

1. Authenticate the source through its approved connector or authenticated branch agent.
2. Resolve the registered device and its authorized branch.
3. Preserve the original payload/file and stable source identifiers.
4. Normalize fields without inventing receipts, taxes, prices or transaction references.
5. Resolve external PLU/article codes through the source-system-specific Product Master mapping.
6. Validate business date, branch, currency, quantity, price, discount, tax and control totals.
7. Deduplicate and stage a Daily POS job.
8. Reconcile the imported total against the POS source report/settlement evidence.
9. Allow the assigned Branch Manager to review, correct eligible staged rows, and explicitly release the job through the established Daily POS commit path.
10. Verify sales, inventory ledger, accounting entries, job state and audit trail.

No adapter may insert a sale, adjust a stock balance, post a GL journal, close a business day, or alter official fiscal receipts directly. Adapters have no direct posting capability.

The separate business workflows remain separate:
- Daily POS Sales: completed branch sales and refunds.
- Product Master: product/SKU/catalog import only; no physical quantity.
- New Arrival / Receiving: PO-linked receiving through the existing authorized Warehouse Manager workflow; never a direct inventory-file write.

## 3. Supported connector families

| Family | Examples | Adapter requirement |
|---|---|---|
| Vendor REST API | Cloud POS, retail software supplier | OAuth/API-key or signed request, paging/cursors, rate limits, stable transaction IDs |
| Webhooks | Real-time sale/void/refund notifications | Signature verification, replay window, durable event receipt, idempotent staging |
| Branch local gateway | Windows/Linux POS software, local network service | Outbound HTTPS agent, local authenticated IPC, queue/retry, no public inbound branch ports |
| Device SDK / manufacturer service | Android fiscal POS, specialist retail terminal | Vendor-supported SDK/service, firmware compatibility and permission scope |
| File exchange | CSV, XLSX, XML, JSON, SFTP export | Source profile, original file checksum, strict parser, control totals, duplicate protection |
| USB / serial / Bluetooth bridge | USB/serial fiscal peripherals, local POS equipment | Use a vendor-supported branch-PC service/SDK; browser JavaScript must not assume hardware protocol support |
| Payment-terminal connector | EFT/card terminal/acquirer | Separate payment authorization/settlement adapter; map terminal references to an existing POS sale/payment workflow |

The same provider may offer multiple families. Each connection is registered with the actual protocol used. A file connector does not become a REST API connector merely because it uses HTTPS to upload a file.

## 4. Device and source registry

The first implementation uses the existing Core stock_settings storage and immutable Stockland audit chain. It deliberately creates no new ERP table and does not modify the installed database schema.

Each registered device/source records non-secret metadata:
- opaque device ID;
- vendor, device/software name and model/version;
- connection family;
- assigned active retail branch;
- unique Stockland source_system namespace;
- direction/capabilities;
- adapter state and connection state;
- registration actor/time and audit reference.

Connection state remains NOT_CONNECTED until a provider-specific adapter has completed an authenticated connection test and a verified transaction replay/reconciliation test. Registry presence is not proof of connectivity.

The first implementation rejects passwords, API keys, bearer tokens and client secrets in registration payloads. Credentials are not stored in Core settings and are never returned to the ERP browser.

A Branch Manager can register and view devices only within assigned active branch scope. Owner/Management has company scope. A Sales Employee may view the devices available in the assigned branch for a Daily POS upload but cannot register or manage devices.

## 5. Source-specific product mapping

External identifiers such as PLU, article code, item code or barcode are not assumed globally unique.

Each registered device receives a unique source-system namespace. The same external PLU can therefore map to different Stockland SKUs on different POS devices. Product-code mappings are managed through the existing Owner/Management mapping authority and preserve immutable Stockland variant identity.

Generic legacy POS uploads retain the established THIRD_PARTY_POS mapping namespace. Device-selected uploads use the device's registered namespace. This preserves existing generic uploads while preventing cross-vendor PLU collisions.

## 6. Common transaction envelope for future API adapters

A provider adapter must normalize its source into the following logical fields before staging. This is a data contract, not a new database table:

**Batch/source envelope**
- source system and registered device ID;
- assigned branch location;
- business date and provider timezone;
- stable source batch/report ID;
- original payload/file SHA-256;
- provider schema/adapter version;
- page/cursor or export-period reference;
- source currency and source control totals.

**Transaction/receipt**
- stable external transaction or receipt ID, if one exists;
- source transaction type: sale, void, refund, reversal or payment event;
- source creation and last-updated timestamps;
- original receipt reference for a refund/void;
- subtotal, discount, tax, fees and gross amount as supplied;
- payment method and stable terminal/acquirer references where available.

**Lines**
- stable external line ID;
- external product code;
- quantity and unit of measure;
- unit price, discount and tax values/mode as supplied;
- line total and source line sequence;
- optional size/colour/barcode fields as descriptive cross-checks, not substitutes for Product Master identity.

If a supplier provides a daily summary without receipt identities, the adapter must retain that fact and use a receipt-free daily batch. It must never manufacture receipt numbers.

## 7. Reliability, replay and reconciliation

Every provider adapter must demonstrate:
- stable idempotency key and replay behavior;
- paging/cursor completion with no silent gaps;
- retry handling for 429, timeouts and transient 5xx responses;
- detection of duplicate, missing, changed and out-of-order transactions;
- deterministic handling of voids, refunds, corrections and reversals;
- branch/date isolation;
- mapping version provenance;
- source-to-ERP line and control-total reconciliation;
- immutable audit evidence and useful error reasons;
- an operator-visible last successful sync, last error and retry state.

For file imports, the existing source file SHA-256, branch and business date remain the replay boundary. For API feeds, providers must supply stable transaction/line identities; a timestamp alone is not an idempotency key.

## 8. Authentication, secrets and fiscal boundaries

- Use the provider's documented authentication mechanism. Never invent API URLs, headers, credentials or SDK calls.
- Store secret material only in an approved protected runtime secret source, outside repository, browser payloads and ordinary ERP settings. The current registry does not provision or store secrets.
- Prefer outbound branch-agent connections. Do not expose a branch workstation or USB/serial device directly to the public internet.
- Validate webhook signatures and timestamps before processing payloads.
- Restrict each machine credential to one registered device and one branch.
- Keep logs free of passwords, API keys, bearer tokens, payment-card data and unnecessary customer personal information.
- For fiscal POS equipment, preserve the terminal's official fiscal invoice/journal and statutory fiscalization behavior. Stockland imports/reconciles; it must not reissue, rewrite or bypass official fiscal records.
- Payment terminal authorization is not the same as a completed ERP sale. Settlement and POS sale reconciliation must retain provider reference IDs and the actual ERP sale link.

## 9. Role and approval model

| Actor | Permitted actions |
|---|---|
| Owner / Management | Company-wide integration governance, provider mappings and vendor policy |
| Branch Manager | Register/view branch POS sources, select branch source for upload, review/edit/reconcile and release staged jobs |
| Sales Employee | View assigned-branch device options; upload or use the configured folder agent to stage evidence only |
| Warehouse Manager | Continue existing PO-linked receiving; no POS sale release authority is implied |
| Auditor | Review permitted audit/evidence views only; no connector configuration or posting |

Integration activation and transaction posting are separate decisions. A connection can be registered/tested without granting it permission to post. Transaction release remains the authenticated Branch Manager ERP action under the existing branch/day locks and ledger/accounting transaction path.

## 10. Implemented in the 16.2.18 work branch

The first implementation slice now includes:
- Universal POS integration registry REST routes and adapter-pattern catalogue.
- Branch-scoped device registration using existing Core settings storage and immutable audit events.
- Explicit NOT_CONNECTED and ADAPTER_REQUIRED states; secret fields rejected.
- Branch Manager POS Integrations workspace.
- Optional registered-device selector in the existing Daily POS upload screen.
- Per-device source_system mapping namespace in the existing Daily POS parser/resolver; generic legacy mapping namespace preserved.
- Optional registered-device ID for the Windows branch folder agent; the agent continues to use the same preview/staging route and cannot release jobs.
- Database-backed s17 integration registry test plus source-contract/installer workflow tests.

This slice does not claim that a real vendor API, webhook, SDK, terminal protocol or live hardware session is connected. All provider adapters remain ADAPTER_REQUIRED until supplier evidence exists.

## 11. Required acceptance before a provider can show CONNECTED

For each supplier/device, keep a separate adapter acceptance record with:
1. official vendor technical documentation and supported product/firmware versions;
2. authorized sandbox/test account and least-privilege credential;
3. successful TLS/authentication and branch/device identity checks;
4. product/PLU/variant mapping tests including overlapping codes across vendors;
5. sale, refund, void, discount, tax, multiple-payment and settlement scenarios supported by that provider;
6. duplicate replay, pagination/cursor, retry, rate-limit and out-of-order tests;
7. full source-to-ERP reconciliation;
8. branch-manager staging/release test with sales, stock ledger, GL, daily close and immutable audit evidence;
9. negative tests for unauthorized branches, revoked credentials, bad signatures, unknown SKUs and malformed totals;
10. exact-head PHP/database/browser installer regression and a provider-owner UAT receipt.

Until all required checks for that connector pass, its status remains NOT_CONNECTED or ADAPTER_REQUIRED.

## 12. Release boundary

This is isolated work on the existing 16.2.18 source line. The certified installed installer/package, live WordPress site and live database are not edited by the feature branch. Do not merge, tag, replace an installer or deploy based on registry presence, static tests or synthetic CI alone. The final cumulative 16.2.18 update must be rebuilt from one exact source commit and independently verified as one complete WordPress installer, then separately accepted for the target host.