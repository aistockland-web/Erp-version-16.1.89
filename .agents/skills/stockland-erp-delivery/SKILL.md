---
name: stockland-erp-delivery
description: "Use for any Stockland Ethiopia ERP implementation, review, release, migration, Codespaces, WordPress, POS, warehouse, finance, HR/payroll, or production-readiness task."
---

# Stockland ERP cumulative delivery

## Start every task
Read the repository-root `AGENTS.md` first. Then read the relevant controlling Notion decision pages linked from it and refresh the exact GitHub branch, HEAD, ancestry, open PRs, Actions runs, package hashes, and current evidence. Never assume an older chat summary or a release label is the latest source of truth.

## Source-line discipline
- 16.2.13 is the operational and upgrade baseline.
- 16.2.15 is a review candidate, not an approved release.
- 16.1.95 is a protected rollback/comparison reference, not the default install target.
- 16.2.9, 16.3.0/16.3.1 HRX, and older candidates are selective comparison/source contributions only unless a newer Owner decision says otherwise.
- Compare exact source paths, hashes, ancestry, behavior, dependencies, schema impact, and tests. Never infer cumulative ancestry from version numbers.

## Product invariants
Preserve one company, STOCKLAND ETHIOPIA, and the five controlled locations: CENTRAL WAREHOUSE, GORO BRANCH, GERJI BRANCH, MEXICO BRANCH, and MEGENAGNA BRANCH. Preserve frontend-first ERP access; Command Center; POS/cart and retail/wholesale; SKU/brand/size/color; stock ledger; Central/User Warehouse Management; receiving, requisition, reservation, transfer, branch receipt/variance, return, damage, adjustment, physical count, daily close; suppliers/customers/procurement/AP/AR/expenses/cash-bank/VAT/GL/PLEDGER; HR lifecycle/payroll; SOP and private resource library; reports/print/export; roles, locations, Owner authority, maker-checker, and immutable history.

Map each affected requirement through UI, REST/API, server-side capability/location authorization, domain service, database schema, transaction side effects, failure/retry behavior, and immutable audit record. Reuse existing Stockland services. Do not create competing POS, inventory, accounting, authorization, or workflow engines.

## Engineering loop
For each coherent target:
1. State expected business behavior and the evidence/decision that authorizes it.
2. Identify exact files, source contribution, API contracts, schema/data risk, access control, and rollback.
3. Add a focused regression test. Prefer the smallest meaningful RED/GREEN cycle.
4. Implement the narrow fix using existing conventions.
5. Run relevant unit/static checks and the cumulative suite; fix the actual causes of failures.
6. Commit the coherent target to a descriptive branch and open/update a focused PR.
7. Attach exact commit/package IDs, command logs, reports, migrations, and evidence to the PR and update Notion with verified facts only.

Do not weaken assertions, skip required jobs, suppress findings, use stale artifacts, or treat a static scan as runtime acceptance. Never claim unrun work passed.

## Runtime and data safeguards
Use only the disposable Codespaces QA environment for synthetic test data. Never connect to the live business WordPress/database or load customer, employee, supplier, payroll, or private HR records into QA. Keep DB port 3306 internal and previews private. Never test destructive migrations, payroll, or synthetic transactions on production.

Preserve the original 49 legacy tables and posted history until exact DDL/data mapping, an additive ERD, approved migration, reconciliation, and rollback are documented. Never use a hard-coded table count as approval. Keep synthetic payroll fixtures test-only and prove production routes cannot enable them. Do not invent Ethiopian tax/pension rules or statutory interpretation. The 24 Amharic-first SOP payloads remain drafts until authorized process-owner and qualified legal/statutory sign-off.

## Result language and delivery cadence
Use affirmative, action-led project language: "implemented", "corrected", "verified on the exact commit", "accepted by the named test", "installer assembled", and "ready for the next release gate". Every finding receives an immediate corrective action: exact files/symbols, source contribution, regression test, exact commit, Actions evidence and resulting artifact. Do not repeat a limitation without stating the concrete fix and the next action.

For engineering reports, retain precise gate outcomes: PASS, FAIL, BLOCKED, NOT RUN, and OWNER INPUT REQUIRED. Distinguish verified engineering behavior from business/legal approval and final live-site authorization. The fixed delivery target is one complete, production-grade Stockland WordPress ERP/PWA and one reproducible installer. Move completed work through commit, exact-head CI, integration and package assembly; do not leave completed work in an uncommitted workspace. Never label a release gate complete without its actual evidence.


## Canonical editable source

The actual WordPress ERP runtime source lives at src/stockland-erp-app/. Codespaces post-create verifies the .13 archive and its per-file intake manifest, then materializes this folder. Confirm src/.stockland-erp-app.source.json before implementation. If the source tree is missing, run bash scripts/codespaces/bootstrap_project_source.sh after package intake. Never edit the disposable WordPress copy, temporary extraction, or a sample project as canonical source. Never overwrite an existing unmarked folder. On first import, create a separate source-intake commit before functional fixes.


## Owner's delivery directive
This project is the three-month cumulative Stockland Ethiopia ERP, not a new demo. The user expects autonomous, prompt execution of repository-side corrections, source reconciliation, coding, commits and tests within available free tools. Do not ask for approval of routine reads, edits, commits, branches, review PRs, test runs or repository configuration. Ask only for a genuine Owner-only decision, account sign-in, unavailable permission, statutory/legal approval, destructive live-data action or final deployment authorization.

Keep one repository integration line and one future Codespace. All container configuration, dependencies, installer build code and test scripts must be committed to GitHub before Codespaces creation. Preserve all prior source/history and use exact manifests to import the 16.2.13 runtime. Reconcile the selected 16.2.15 fixes, historical release branches, Codex/Copilot work and UI preview as source contributions; verify each and port only compatible changes.
