# Stockland Ethiopia ERP — Incremental Target Commits and Full Production Completion Directive

**Owner direction date:** 5 November 2026  
**Repository:** `aistockland-web/Erp-version-16.1.89`  
**Base commit preserved:** `a46214f6bd5e21a9948d5c896d2136d722d36f16`  
**Working branch:** `owner/incremental-target-commits-production-2026-11-05`  
**Relationship:** Additive to the 4 November Cross-Platform Project Work Directive and the controlling Notion Protected Master decision.

## 1. Affirmative engineering objective

The mission is to complete and deliver the full, integrated Stockland Ethiopia ERP as a genuinely production-ready WordPress application and one complete installable plugin package.

Proceed affirmatively through implementation, integration, validation, correction and final release. Do not reduce the scope to a staging exercise, a partial baseline, a report, a mock-up or a collection of disconnected plugin packages. Do not stop when an earlier audit records an unfinished item. Carry the work forward, implement what is missing, correct what is defective and complete the acceptance evidence.

This directive authorizes full-scope engineering across the repository, Codespaces, Visual Studio Code, local development environments, CI and suitable connected tools. It does not authorize unsafe tests against the live company system or fabricated production transactions.

## 2. Commit every completed target as it is collected

**Do not wait until the entire ERP is finished before committing the work.**

As soon as a target workstream has been implemented to a coherent, reviewable state:

1. Commit that target work to the canonical repository immediately, using a descriptive, focused commit message.
2. Push it to the cumulative integration branch or its review branch and associate it with a PR/change record. Preserve the exact commit SHA.
3. Include the files and resources required for that target, its migrations or compatibility changes where applicable, automated tests, and a short implementation/evidence note.
4. Run the target-specific checks and the applicable integration/regression suite against that exact commit.
5. Fix every reproducible defect or finding in source and tests, commit the correction separately, and rerun the affected checks plus regression coverage.
6. Continue to the next target without waiting for unrelated workstreams to finish.

A completed target must not remain only in a Codespace, local editor, chat response, ZIP attachment or uncommitted workspace. The repository is the durable implementation record.

Use a cumulative, traceable integration line so that each accepted target builds on the preceding targets. Where independent work requires separate branches, bring each reviewed target into that integration line with a PR and record the resulting merge/commit SHA. Do not create thousands of arbitrary version-number packages or overwrite prior history. Prefer focused commits over frequent disconnected ZIP releases.

## 3. Workstream sequence and completion scope

Use the established Stockland requirements and historical decisions as the cumulative specification. Organize commits around coherent targets such as:

- Core application shell, authentication, role/capability model, Owner authority, branch/location access and shared services.
- Product Master, SKU/variant structure, brand/category/size/color data and Excel import/export.
- Central Warehouse and Goro, Gerji, Mexico and Megenagna inventory, receiving, reservations, requisitions, transfers, counts, returns, damage, adjustments and stock ledger.
- Retail POS, multi-line cart, wholesale flows, pricing controls, receipts, refunds, daily closing and transactional integrity.
- Procurement, supplier/customer records, expenses, AP/AR, cash/bank, VAT, COGS, journals, GL, PLEDGER and reconciliations.
- HR lifecycle, employee records, job descriptions, contracts, attendance, leave, effective-dated compensation, payroll review/approval, payments, remittance and offboarding.
- Actual approved SOP/resource library, document versioning, secure preview/download, reports and Management Intelligence.
- SaaS-quality responsive interface, accessible navigation, design system, forms, tables, cart interactions, policy screens, HR/payroll/finance presentation, print/export and PWA behavior.
- Security, performance, observability, migrations, installer/build pipeline, backup/restore and release packaging.

This sequence is an implementation guide, not a reason to omit, defer indefinitely or replace any requirement in the approved plan. Reconcile existing code and version history before rewriting. Preserve business rules, posted history, the 49-table baseline and legacy HR data unless an explicitly reviewed additive migration is approved. Continue using existing accounting, inventory, authorization, audit and workflow services rather than introducing competing engines.

## 4. Test and repair discipline for each commit

Every target commit must carry executable checks appropriate to the changed behavior. Add missing tests as part of the work; do not leave an untested feature simply labelled as waiting for tests.

Run checks in controlled, isolated development/CI/WordPress/MariaDB test environments. Never run synthetic payroll, invented sales, destructive migration tests, load tests, failure injection or test data writes against the live Stockland production database or live company transactions.

For each target, use the applicable checks, including:

- PHP syntax and static analysis; PHP 8.3/8.4 compatibility where applicable.
- JavaScript syntax, module/build checks, CSS/PostCSS order and responsive layout checks.
- PHPCS/WPCS, Semgrep, dependency/secret scanning and applicable security analysis.
- WordPress activation, upgrade, REST, nonce/capability, role and location-scope tests.
- Database migrations, constraints, transaction rollback, idempotency, concurrency and replay protection.
- Feature-specific functional tests, integration tests and regression tests for existing workflows.
- Exact installer build, ZIP integrity, root/path checks, manifest, file inventory and independently calculated SHA-256.
- Browser/device checks for desktop and mobile, keyboard/accessibility, PWA behavior and print/export where relevant.

Run the target checks on the commit that contains the target, and rerun them after fixes. Run the full applicable regression suite on every cumulative integration update and the complete release suite on the final exact source commit and final exact ZIP. A result from a different commit or different ZIP does not validate the current candidate.

Do not suppress findings, loosen assertions, skip required jobs or reuse a stale green run to manufacture a green result. Record actual outcomes accurately, with evidence links and exact SHAs. A test that has not run must not be described as passed. Where a check exposes a defect or missing coverage, fix it and rerun; do not use its existence as a reason to abandon the workstream.

## 5. Production-safety boundary

The objective remains full production readiness. Testing is performed before release in representative, isolated environments with controlled test data and a verified restore path—not by experimenting on the live production system.

Do not test with fabricated business events in production. After release, only perform non-destructive operational verification and real Owner-authorized business activity. Any live action that changes company data must be an actual approved business transaction, not a synthetic test.

Protect HR documents, payroll, credentials and customer/supplier data. Enforce authorization and location scope on the server. Preserve immutable audit/ledger history; make corrections through traceable compensating events rather than silent edits. Never invent Ethiopian statutory payroll rates or legal interpretations; use verified authoritative requirements and qualified review.

## 6. Repository and review protocol

- Keep `main` and the protected 16.1.95 rollback reference intact while cumulative work is being assembled.
- Work through descriptive branches, focused commits and PRs. Each commit must be attributable to a target requirement, change set and evidence.
- Do not force-push, rewrite protected history, silently replace a source lineage or deploy as a side effect of tests.
- Keep the GitHub implementation record aligned with the controlling Notion scope/decision record. Link commits, PRs, Actions runs, artifacts, hashes, findings and acceptance evidence.
- Integrate the full application into one cumulative plugin. Do not declare a collection of workstream commits to be separate final products.
- Once all product targets are integrated and the exact final package has completed the complete acceptance programme, prepare the single final production installer with canonical source commit, manifest, inventory, build receipt and SHA-256.

## 7. Final delivery

The final handoff is one coherent, complete Stockland ERP WordPress plugin ZIP, not a sequence of partial release packages. It must include all approved operational, financial, HR/payroll, SOP, reporting, UI, security, migration and installation resources; documented configuration; reproducible build evidence; and the exact final checksum.

The final production acceptance must use the same exact source commit and ZIP hash across code checks, WordPress/MariaDB end-to-end tests, authorization tests, financial/inventory reconciliation, HR/payroll verification, recovery drills, responsive/accessibility checks and Owner/Operations/HR/Finance/legal acceptance.

**Execution instruction:** collect each completed target, commit it to the repository immediately, run its tests, fix findings, commit the fixes, rerun and continue. Build the full integrated production product from that traceable sequence. Do not stop at a partial candidate, and do not claim a test or release result that the evidence does not support.
