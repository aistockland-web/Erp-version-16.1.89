---
name: stockland-erp-delivery
description: "Use for Stockland ERP implementation, review, release, migration, Codespaces, WordPress, POS, warehouse, finance, HR/payroll, or production readiness."
---
# Stockland ERP cumulative delivery

## Start every task
Read root AGENTS.md, the latest linked Notion decision, this skill, and exact GitHub branch/HEAD/ancestry/PR/Actions state. Inspect source, tests, package hashes and runtime receipts. Never trust stale chat summaries, PR titles or version labels alone.

## Single product and source lineage
Keep one cumulative Stockland Ethiopia WordPress ERP/PWA and one final installable plugin ZIP. Operational baseline 16.2.13; 16.2.15 candidate; 16.1.95 protected rollback/reference. Compare 16.2.9 and 16.3.0/16.3.1 HRX only as selective sources. Preserve five locations, product/SKU variants, POS/cart, warehouse/stock flows, procurement/finance/GL/PLEDGER, HR records, SOP resources, responsive SaaS UI, reports and PWA. Never use a sample app, alternate product or duplicate engine.

Canonical runtime source is src/stockland-erp-app/ with provenance src/.stockland-erp-app.source.json. Verify both before editing. Do not edit disposable WordPress copies or temporary extraction as source of truth. Never overwrite unknown files or unmarked source. Preserve uncommitted work.

## Owner HR/schema decision
Operational baseline is 49 tables: 29 Core/Operations, 16 Accounting/Procurement, and four legacy HR/payroll. The 37 lifecycle extension tables and 33 recognized HRX keys are separate and unapproved. Never auto-create them or ALTER existing legacy HR/payroll tables without explicit Owner approval tied to exact DDL and reviewed migration. Operational readiness is Core + Accounting and must not be globally disabled due to HR lifecycle gaps. Report legacy HR and lifecycle readiness separately.

Retain .15 compensation DRAFT state and independent approver/timestamp evidence. New pension DDL defaults are neutral zero; do not rewrite historic values or fabricate statutory rules. Payroll finalization/payment/posting stays fail-closed without approved effective-dated legal rules and qualified Ethiopian review. An AES-GCM single-key vault is not proof of key rotation, recovery or encrypted backup. Keep 24 Amharic-first SOPs in their real draft state until approved.

## Implementation and verification
Map targets through UI, REST, server-side capability/location permission, domain service, schema, atomic transaction, idempotent retry, audit evidence, failure behavior, recovery and rollback. Reuse existing Stockland services.
For each coherent target, update Notion first, record exact source and plan, add regression coverage, implement, run focused/cumulative tests, inspect failures, commit to a descriptive non-main branch, open/update a PR and write exact commit/CI/artifact/hash evidence back to Notion. Never suppress gates, skip tests, or claim unrun checks passed.

Use disposable synthetic QA only. No live business database, production credentials, real private documents or business transactions. Keep DB ports internal and previews private. Never invent Ethiopian tax, pension, overtime, leave, holiday or settlement rules.

## Free-only workspace
Use one Codespace on the selected delivery branch. GitHub personal Free includes 120 core-hours and 15 GB-month storage; 4-core machines consume four core-hours for each active hour. The workspace requests 4 CPU/16 GB RAM/64 GB storage only if GitHub offers it inside the Free quota. Keep paid overage disabled, stop when idle, never select 8-core/32-GB or larger, and use GitHub Actions for repeated DB matrix runs. Codex CLI is optional with ChatGPT device auth, limited on Free; Codex Cloud is not included. No API key, paid credit or Marketplace plugin is required.

## Result and release boundary
Use PASS, FAIL, BLOCKED, NOT RUN and OWNER INPUT REQUIRED accurately. A green environment, static, activation, schema or single-module job proves only its named scope. No production release, install, merge, tag, publish or deploy until exact final installer, full business/data/security/workflow acceptance, qualified legal/statutory reviews and Owner/Operations/Finance/HR approvals are complete and final Owner authorization exists.
