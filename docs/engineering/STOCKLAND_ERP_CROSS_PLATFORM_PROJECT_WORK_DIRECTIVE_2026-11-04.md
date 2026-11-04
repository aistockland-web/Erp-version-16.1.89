# Stockland Ethiopia ERP — Cross-Platform Project Work Directive
**Ratification date:** 4 November 2026  
**Scope:** Full-scope engineering, integration, correction, optimization and final WordPress installer delivery  
**Status:** GO for implementation under this directive; final installer certification remains evidence-based.

## 1. Ratified objective
## Owner authority clarification — 4 November 2026

**The Owner's decision is the source of project authority.** The Owner, not ChatGPT, Copilot, a repository audit, a developer assistant or an individual version label, determines the business objective, approved scope, priorities and final product acceptance. This directive records the Owner's decision; it does not grant permission on the Owner's behalf.

The Owner's existing plan and decisions across the last two ERP versions and the planned final plugin remain the controlling product direction. They are cumulative project history and requirements, not disposable experiments and not a reason to restart, narrow the scope, substitute a different architecture or discard already agreed functionality. Every new chat, platform or engineer must review and carry forward that established plan, identify what is already implemented, and complete what remains in the one integrated final plugin.

The Owner's instruction is:
- full engineering freedom across chats, platforms, machines, repositories, editors and appropriate tools;
- implement and reconcile the complete agreed Stockland ERP scope, using the approved release history as source material;
- deliver one complete, integrated, production-ready WordPress plugin installer as the intended final product—not a series of partial candidate deliverables;
- preserve existing business rules, data, authority, SOPs, operational history and approved system behavior while completing the implementation;
- do not reinterpret the Owner's goal as permission to stop at a limited baseline, a UI-only pack, an HR-only overlay, a local build or an audit report.

Builds, automated checks, isolated runtime exercises and review branches are engineering methods used to make the final product reliable. They are not alternative product goals and must not be presented as the final deliverable. Evidence must still be factual: no assistant or engineer may call a specific ZIP production-certified until its exact contents and required acceptance evidence have actually been verified. That evidence rule protects the Owner's decision; it does not reduce the Owner's scope or engineering freedom.

When earlier version plans conflict or appear inconsistent, preserve both source records, map the actual implemented behavior and decision history, and bring the discrepancy to the Owner rather than silently choosing, deleting or rewriting the Owner's prior direction.


The project objective is one complete, cumulative Stockland Ethiopia ERP delivered as one installable WordPress plugin ZIP, supported by one canonical source commit and one independently recomputed SHA-256.

The final deliverable is not a demo, mock-up, disconnected add-on collection, temporary test build, partial feature pack or permanently labelled candidate. It must contain the integrated production application, approved business workflows, required resources, configuration, migrations and release evidence as one coherent WordPress plugin.

**GO is granted for the full engineering mandate:** continue implementation, refactoring, bug fixing, reconciliation, UX redesign, security hardening, integration and optimization using any suitable chat, platform, machine, editor, development environment or engineering tool. Work does not have to remain in ChatGPT or in a particular local machine. A new chat or platform must use this directive and the linked GitHub/Notion records as its handover authority; it must not restart from assumptions or silently change the agreed source lineage.

This engineering GO is not a declaration that an unverified ZIP is already production-certified. The final package is approved for production release only when the same exact final package SHA has the required source, package, live operational, recovery and stakeholder evidence. Never turn missing evidence into a fake PASS.

## 2. Cross-platform source of truth

**GitHub owns implementation history and release artifacts.** The canonical engineering repository for this workstream is:

- Repository: [aistockland-web/Erp-version-16.1.89](https://github.com/aistockland-web/Erp-version-16.1.89)
- Default branch: `main`
- Main SHA at ratification: `a46214f6bd5e21a9948d5c896d2136d722d36f16` (the 3 November documentation-only finalization commit)
- This directive is a documentation-only change proposed on a separate review branch; it does not modify the plugin or production data.

**Notion owns the cross-project working agreement, approved scope, design decisions, SOP references, audit findings and acceptance records.** The controlling decision is [16.1.95 Protected Master — HR/Payroll Integration Decision — 4 November 2026](https://app.notion.com/p/40d6db0f767e8140b744d32a043269f9), under [Stockland ERP — Canonical Release Audit & Acceptance Gates](https://app.notion.com/p/3fc6db0f767e81f2a3bbe644048054f9).

Keep the two records synchronized:
- Accepted scope, source-lineage, architecture or release-policy decisions must be reflected in both GitHub and Notion.
- GitHub commits, PRs, Actions runs, artifacts and hashes must be linked from the matching Notion audit/acceptance record.
- Notion decisions that change implementation must be represented in the GitHub PR/commit before the code is treated as accepted.
- If records conflict, do not silently choose the newest timestamp. Identify the conflicting statements, preserve evidence and reconcile against the controlling 4 November protected-master decision with the Owner.
- Do not substitute `aistockland-web/Erp` or `stocklandethiopia/stockland-erp-project` for this canonical repository.

## 3. Protected source lineage and integration rules

The current approved source decision is:

| Role | Exact identity | Instruction |
| --- | --- | --- |
| Protected operational master / rollback reference | 16.1.95 installer SHA-256: `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625` (75 files) | Preserve byte-for-byte as the protected baseline and recovery reference. |
| Selected HR/Payroll implementation source | Merged PR #39, commit `bd6c78f180e3bc6871317aa32c1f11d4fab8a427`; exact 16.2.9 artifact SHA-256: `c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01` (88 files) | Use as the selected HR/Payroll source only; integrate deliberately, not as a wholesale replacement ZIP. |
| 16.3.0 / 16.3.1 and HRX packages | Historical/consolidation evidence; 16.3.1 local record is `CONDITIONAL_PASS` | Use for code, design and audit reference where individually reviewed. Do not treat as the canonical final package or overlay wholesale. |

Integration requirements:
1. Keep the non-HR 16.1.95 runtime byte-identical except for the smallest reviewed bootstrap, route-registration and scoped HR UI integration required to load the accepted HR functionality.
2. PR #39 is the selected HR/Payroll source. Exclude unrelated POS/cart, sales, stock, database, accounting or global UI changes from that overlay unless separately reviewed, tested and approved.
3. Preserve the original 49-table schema contract and all four legacy HR/payroll tables (`employees`, `employee_compensation`, `payroll_periods`, `payroll_lines`) and their history until a mapped, approved, additive migration and rollback plan is accepted. Do not infer a final HR table count from earlier 14–16, 37, 41 or 47 table proposals.
4. The 47-table HRX schema remains unapproved/disabled until the ERD, dependency and shared-service map, relationships/FK or equivalent orphan checks, data-health inventory, migration strategy, rollback and written Owner/HR/Finance approval are complete.
5. Do not import the 16.2.9 migration behavior that assigns historical records default approval or historical pension rates without explicit approved events and verified statutory basis.
6. Reuse Stockland's existing identity, capabilities, Owner authority, branch/location scope, maker-checker, workflow, database, money, audit, notification, accounting, AP/AR, GL/PLEDGER and business-calendar services. Do not introduce a competing ledger/accounting engine or strand existing routes behind blanket HTTP 409 responses.
7. Preserve posted history. Corrections, refunds, reversals, returns and adjustments must be traceable compensating events; never silently rewrite historical sales, stock, accounting or payroll.

## 4. Full product scope

Complete and integrate the ERP as one coherent application, following the Stockland workbook, existing role permissions, approved SOPs and controlling Notion decisions:

- **Product and catalog:** product master, controlled SKU/variant identity, size/color, brand/category/age segmentation, barcode support, bulk Excel import/export and audit history.
- **Inventory and warehouse:** Central Warehouse and Goro, Gerji, Mexico and Megenagna; receiving, put-away, transfers, reservations, availability, replenishment, physical counts, returns, damage, adjustments, valuation and immutable stock ledger.
- **Retail, wholesale and sales:** POS/cart, customer and wholesale flows, pricing, discounts under authority, receipts, refunds/returns, daily close, tax breakdown, staged COGS, import/replay protection and transactional recovery.
- **Procurement and finance:** suppliers, purchase orders, receipts, AP/AR, expenses, cash/bank, VAT, journals, general ledger, PLEDGER, reports and reconciliation. Posted journals must balance and subsidiary ledgers must reconcile to control accounts.
- **HR and payroll:** hiring-to-offboarding lifecycle, contracts, job descriptions, assignments, attendance, leave, effective-dated compensation, payroll preflight/review/approval, payout attempts and reconciliation, statutory remittance, final settlement and role-scoped private documents.
- **SOP and process resources:** deliver the actual approved Amharic-first SOP/JD/contract/resource pack, verify hashes and rendering, and provide secure role-based preview/download, versioning, independent approval and auditable exports. Do not claim the resource pack is delivered until its actual files are included and verified.
- **Management intelligence and integrations:** keep deterministic, permission-controlled reporting accurate. A provider or external AI/social integration is connected only when the actual provider, credentials, data-handling policy, least-privilege tools, approvals and audit trail are configured and verified. Do not label an unconfigured integration as connected.
- **Enterprise UX:** one consistent SaaS-quality responsive shell; accessible navigation and SVG icons; reliable forms, tables, filters, dialogs, alerts, print/export and mobile behavior. Correct the existing cart, policy, HR/payroll, finance and icon/CSS defects without weakening business rules or obscuring controls.

The single-company/location model and all branch-specific permissions remain enforced on the server, not merely hidden in the interface.

## 5. Runtime and engineering architecture

The adopted production runtime is the WordPress plugin: PHP 8.3+, WordPress REST APIs and the site's supported MySQL/MariaDB database over HTTPS. Python 3.12 and Node.js 22 are build/CI tools; they are not hidden production services or a separate required app server. Keep the runtime architecture consistent with the approved WordPress hosting contract unless a separately reviewed architecture decision changes it.

Preserve the pinned local Bootstrap 5.3.8 asset and the approved ordered CSS-module build contract. No remote CDN dependency, arbitrary runtime CSS injection, unreviewed worker or hidden service dependency. Keep the PWA online-first for transactional data; do not cache salaries, identity documents or private business data in the service worker.

Every business action must be traced end-to-end: UI → authenticated REST → server-side capability/profile/location/row-scope authorization → domain service → transaction/database → inventory/accounting effect → persisted state → audit event → user-visible result. A nonce is CSRF protection, not authorization. Never return success before the write is committed and verified; failures must fail clearly and roll back atomically.

## 6. GitHub change and review protocol

Use this protocol across every chat and platform:

1. Fetch the current canonical `main` SHA and the controlling Notion decisions before editing. Record the exact base SHA; do not rely on chat recollection.
2. Create a dedicated, descriptive branch from that SHA. Do not work directly on `main` or overwrite the protected 16.1.95 artifact.
3. Make focused, reviewable commits. Each commit must explain the defect or requirement, the smallest implementation change and the regression evidence. Avoid giant untraceable ZIP swaps and unrelated refactors.
4. Open a PR to `main`. Include scope, source lineage, changed paths, risks, schema/data impact, tests, exact artifact input/output SHA-256 and acceptance evidence. Use draft status while work is incomplete; mark ready only after required review and evidence exist.
5. Build the one cumulative installer deterministically from the exact approved source lineage. Include a manifest, file inventory, build receipt and SHA-256 sidecar. Independently recompute the inner installable ZIP hash; distinguish it from any outer GitHub Actions artifact hash.
6. Run required exact-head CI against the same source commit and same output ZIP: PHP 8.3 and 8.4 syntax/regressions; Node 22 syntax; Python 3.12 builder/receipt checks; ZIP integrity/path/root checks; CSS module order/source parity/PostCSS/responsive checks; full PHPCS/WPCS report and source-aware security/database triage; Semgrep and applicable CodeQL/secret/dependency checks; functional and release invariants. A skipped job, stale run on another SHA, partial scan or different ZIP is not a pass.
7. Resolve findings in source with focused tests. Do not suppress coding/security warnings globally to make a dashboard green. Report remaining warnings accurately and distinguish style findings from security findings.
8. Update the linked Notion audit/decision/CAP record with the PR, commit SHA, workflow run, exact artifact SHA, findings and status. Conversely, any accepted Notion scope change must be reflected in a GitHub change record.
9. Merge only after approved review and exact-head evidence. Never force-push, rewrite protected history or deploy automatically as a side effect of a test/build.

The 3 November Notion decision reports main branch protection disabled at that time; the connected GitHub branch-protection endpoint was not readable during this ratification. Therefore PR review is a mandatory project working protocol regardless of whether GitHub currently enforces it technically. Do not claim branch protection is enabled until it is verified.

## 7. Data protection and genuine acceptance

No artificial limitation applies to the engineering machine, chat, platform, editor, CI, test tools or implementation scope. The following are controls over business data and release truth, not restrictions on engineering freedom:

- Use a controlled isolated copy of authorized data for synthetic transactions, destructive tests, failure injection, migrations, rollback and backup/restore drills. Never insert invented employees, salaries, sales or payroll into the live company ledger.
- In production, execute only real Owner-authorized business events and approved migrations.
- Never invent missing employee compensation, PAYE/pension rates, exemptions or statutory interpretations. Map the applicable Ethiopian primary-source provisions and effective dates and obtain qualified independent review.
- Encrypt private HR documents at rest using a site-managed key; use encrypted backups and authenticated role/location-scoped access. Keep secrets outside source control and the ZIP.
- A failed or incomplete deep-integrity scan, unknown result, missing batch, unavailable authenticated host or absent source hash is not a PASS. Do not silently repair a broken audit chain using the same chain.

For every requirement report exactly one evidence state: **PASS**, **FAIL**, **BLOCKED** or **NOT RUN**. Add owner/action and evidence reference for unresolved items. CI/static checks, synthetic fixtures, owner-supplied screenshots, authenticated live operations and independent statutory review are different evidence classes; do not treat one as proof of another.

## 8. Definition of the single final production release

The final release is one cumulative WordPress installer ZIP with one canonical GitHub source commit, one manifest, one file inventory, one build receipt and one independently recomputed SHA-256. The exact same SHA must be used for all final evidence.

Before production Go, close and attach:
- exact-source and exact-ZIP PHP/JS/CSS/package/security/coding-standard gates, with no required job skipped;
- authenticated WordPress/MariaDB activation, update, REST, permissions, location and private-document workflows;
- end-to-end Product Master, imports, receiving, requisitions, reservations, transfers, physical counts, POS, refunds, daily close, procurement, AP/AR, VAT, COGS, GL and PLEDGER reconciliation;
- complete HR lifecycle, two payroll periods, authorized compensation, payouts, remittances and final settlement;
- concurrency, idempotency, retry, controlled failure and rollback evidence;
- complete deep-integrity receipt, deployed-source parity, database/uploads backup, successful restore and rollback rehearsal;
- responsive browser/device/PWA, keyboard/accessibility and print/export acceptance;
- actual approved SOP/resource pack included and verified;
- Owner, Operations, HR, Finance and qualified Ethiopian labour/tax/pension/data-protection acceptance tied to the exact final SHA.

When these proofs close, issue the single final installer and an affirmative production Go statement. Until then, implementation continues under the GO mandate, while the production-certification field truthfully reflects the evidence. This is not permission to stop at a staging-only package; the intended end product remains the complete live-ready production installer.

## 9. Reusable instruction for any future chat or platform

> Continue the Stockland Ethiopia ERP under the **Cross-Platform Project Work Directive dated 4 November 2026**. Use GitHub repository `aistockland-web/Erp-version-16.1.89` and the controlling Notion page [16.1.95 Protected Master — HR/Payroll Integration Decision — 4 November 2026](https://app.notion.com/p/40d6db0f767e8140b744d32a043269f9). The engineering mandate is GO for the full cumulative enterprise WordPress ERP and one final installable ZIP; there is no restriction to ChatGPT, a specific machine, platform, editor or tool. Preserve the exact 16.1.95 protected master as the baseline/rollback reference and use merged PR #39 / exact 16.2.9 artifact only as the selected HR/Payroll source for selective integration. Do not overlay later ZIPs wholesale or alter legacy tables/history, role/Owner authority, SOP, branch scope, inventory ledger, finance or PLEDGER without reviewed evidence. Work from the current main SHA on a dedicated branch and PR, run exact-package gates, keep GitHub and Notion aligned, and report PASS/FAIL/BLOCKED/NOT RUN honestly. Deliver one integrated production WordPress plugin ZIP—not a demo or fragmented pack—with a single exact SHA and complete live operational, restore/rollback, statutory and stakeholder acceptance evidence.

## 10. Ratification record

This directive adopts and consolidates the 4 November Protected Master decision and the 29 October SaaS Engineering Blueprint. It is additive project governance; it does not silently revoke prior approved business SOPs, alter the database, merge application code, approve the HRX schema or certify a ZIP that has not been independently verified.
