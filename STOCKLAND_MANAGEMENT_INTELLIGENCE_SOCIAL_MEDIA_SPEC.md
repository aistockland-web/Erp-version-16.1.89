# Stockland ERP — Management Intelligence and Social Reporting Scope

**Status:** Approved scope proposal for a separate, review-only feature workstream. This document is not a claim that social login, analytics APIs, or social reporting have already been implemented.

## 1. Correct ownership of the three import jobs

| Job | Correct work area | Purpose | What it may write |
|---|---|---|---|
| **Job 1 — Daily POS transaction import** | Sales & POS → Daily POS Import / Branch Daily Sale | Import external branch POS transaction-level CSV/XLSX evidence through the existing validation, preview, staging, approval, duplicate-protection, business-day lock, and transactional posting flow. | Existing authorized POS workflow only; any posting must retain current stock, cash, accounting and audit behavior. |
| **Job 2 — Social account performance report** | Management Intelligence → Social Media Reports | Record a platform/account report manually, or import a report file exported by an authorized account user. Store account, reporting period, captured date/time, source/file reference, headline metrics and notes. | New social-report records only; no POS, inventory, payroll, AP/AR, PLEDGER, GL or stock-ledger writes. |
| **Job 3 — Influencer campaign / deliverables report** | Management Intelligence → Influencer & Campaign Reports | Plan campaigns/content, assign an influencer/operator, track planned/published/overdue deliverables and post URLs, then record owner-facing results and evidence. Allow manual capture and later file import. | New campaign/report records only; no POS or financial posting. |

**Placement correction:** the Daily POS import shortcut should not be rendered on Reports & Analytics or Management Intelligence. The dedicated “Daily POS Import” entry in Sales & POS is the sole UI entry point for Job 1. The existing server-side capability and branch/date checks remain authoritative.

## 2. Management Intelligence owner workspace

Management Intelligence is a read-only reporting/decision-support view for authorized Owner and Management users. It can show source-backed ERP KPIs plus manual social/campaign reporting. ERP values must show date basis, location scope, currency, source report and explicit failure/unknown states; failed source reads must never be shown as zero.

Planned social/campaign panels:
- **Accounts:** platform, display name/handle, business owner, enabled/disabled status, responsible internal operator, assigned influencer(s), external profile link and last report capture date.
- **Content calendar:** campaign, objective, target audience/market, content type, planned date, owner/approver, assigned influencer, deliverable status and internal notes.
- **Publication register:** provider/post URL, published timestamp, campaign link, responsible influencer, review/approval state and evidence attachment/reference.
- **Performance snapshots:** reporting period, captured time, source (manual entry or uploaded export), views/reach, engagement, clicks/conversions when present in the supplied source, and provenance/notes. Unknown metrics stay blank/unknown; no values are fabricated.
- **Owner report:** account/campaign comparison for a selected period, completed/overdue deliverables, reported metrics, source timestamps and downloadable/printable report.

## 3. Access and integration model

No provider API is required for planning or manual reporting. Authorized operators can use the provider’s ordinary website sign-in and manually record results or upload a report exported by the account user. Prefer opening the provider’s official site in a new tab with the current user's normal browser; do not embed provider login pages in an iframe.

Use provider-supported business roles/delegated access where possible, per-user permissions, clear assignment and audit logs. Never store social account passwords, recovery codes or session tokens in ERP; do not bypass MFA, silently reuse another user’s private session, or label an account “connected” unless that status has been verified. API, OAuth, automatic publication and automatic metrics synchronization are separate optional integrations only after configuration, privacy/security review and tested provider authorization. Their absence must not block ERP POS/stock/finance operations.

## 4. Data, security and release invariants

- Existing 49 legacy ERP tables, their columns, stock/financial ledgers, historical transactions, SOPs and audit history remain unchanged.
- Any later persistence layer must be additive, reviewed through an entity/data dictionary and migration inventory, and must never alter/recreate the existing 49 tables.
- Use explicit WordPress REST permission callbacks/capabilities for every operation. Owner/Management may read the cross-account summary; an influencer/operator may create/update only assigned campaign deliverables and permitted report captures. Enforce account/campaign ownership and role checks server-side, not only in the UI.
- Validate CSV/XLSX type, size, encoding, headers, row/cell limits and formula-injection risk. Preview before commit; capture source fingerprint and actor/time; duplicate imports must be idempotent. Restrict uploads to allowed formats and safe storage; never process arbitrary uploaded scripts or remote URLs.
- Social report imports are **not sales imports** and must never call daily-POS sale posting, inventory ledger, payroll or general-ledger posting handlers.
- Audit creation, editing, approvals, upload/source fingerprint, publication URLs and exports. Redact private tokens and unnecessary personal data. Respect retention and branch/account scope.
- Add exact-head CI, PHP 8.3/8.4 lint, JS/PostCSS checks, WPCS/security scan, CSV parser/CSV formula injection tests, REST negative-permission/IDOR tests, idempotency tests, browser E2E and a source-to-table/endpoint map before release.

## 5. Current known limitation and validation required

The repository’s existing built package has the dedicated Daily POS Import menu/route. Its Reports & Analytics patchers had added a redundant, capability-gated “DAILY POS CSV IMPORT” shortcut; this workstream removes that redundant shortcut and adds regression checks. The literal live-screen labels for any numbered “Option 2” / “Option 3” controls have not been established from the available repository source. Before remapping those actual controls, record their live label/selector → click handler → REST route → capability/location scope → data effects. The proposed Job 2 and Job 3 names above are functional design labels, not a verified transcription of the deployed screen.

This PR changes build/QA logic and scope documentation only. It does not yet implement persistent social accounts, influencer assignments, campaigns, report capture endpoints or a social provider login. Those require a separately reviewed additive feature implementation and acceptance tests.
