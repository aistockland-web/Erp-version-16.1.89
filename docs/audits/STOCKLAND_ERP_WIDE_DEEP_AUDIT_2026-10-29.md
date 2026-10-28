# Stockland Ethiopia ERP-wide deep audit — 29 October 2026

**Status: documentation/QA handoff only; production NO-GO.** This audit continues the HRX replacement and fills the prior gap in ERP-wide document-controlled SOP coverage. It is based on the locked 16.1.95 source archive and local 16.1.98-HRX review candidate; it does not claim the site's actual installed 16.1.97 package was available or tested.

## Verified source baseline

- Canonical 16.1.95 installer SHA-256: `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`.
- Source archive: 75 unique paths, 48 PHP files, 49 registered table keys with 49 static DDL declarations (29 Core/Operations + 16 Finance/Procurement + 4 HR/Payroll).
- Static inventory: 116 REST route-registration call sites. Literal `permission_callback` declarations do not prove capability, branch scope, object-level authorization, state handling or privacy correctness.
- Four HR legacy tables: `employees`, `employee_compensation`, `payroll_periods`, `payroll_lines`. Keep as read-only transition sources through migration and historical reconciliation. No drop/truncate/rename/repurpose before separate cleanup approval.
- HRX has 33 logical tables in the current design candidate. This is not an approved final physical table count.

## Local review candidate / tests

The local package named `STOCKLAND_ERP_16.1.98_HRX_ERP_WIDE_SOP_REVIEW_ONLY.zip` was built from 16.1.95. ZIP checksum is recorded in the locally delivered build receipt, not committed as a deployable GitHub release. The exact installed 16.1.97 ZIP is not available in the current working files. Do not install this candidate over the site; it would fail to preserve the previous 16.1.97 Process Center resources.

Completed local checks:
- HRX domain regression: 727 assertions, 0 failures.
- PHP syntax: all 51 candidate PHP files passed.
- JavaScript syntax: `assets/app.js` and `assets/pwa.js` passed Node syntax.
- JSON syntax: release, PWA and SOP manifests passed.
- SOP validation: all 24 generated DOCX files opened and per-file SHA/document-control checks passed.
- Source map: all 49 legacy ERP tables and all 33 HRX logical table names are referenced by at least one SOP.
- Candidate ZIP: 103 unique members, expected `stockland-erp-app/` root, CRC and embedded SOP hashes pass.

These are static/domain/local package checks. They do not establish real WordPress installation or runtime behavior.

## All-ERP SOP Process Center

24 separate Amharic-first Word SOP drafts have document IDs, version `v1.0-DRAFT`, draft status, issue date, process owner, approval authority, effective-date placeholder, review cadence, steps, controls, evidence, KPIs, source table map and legal-review notes. They cover:

1. Document control, policy and change governance
2. Users, roles, locations and approval authority
3. Product Master, SKU, size/color and price history
4. Excel/CSV imports and data validation
5. Central Warehouse receiving
6. Transfers and receipt variance
7. Requisitions, reservations and replenishment
8. Physical counts and stock adjustments
9. Damage, returns and write-offs
10. POS sales, voids and refunds
11. Daily closing, cash and payment reconciliation
12. Wholesale operations
13. Procurement and supplier invoices
14. Accounts payable, expenses and payments
15. Accounts receivable and collections
16. General Ledger/PLEDGER and close
17. Reporting, Management Intelligence and manual social snapshots
18. System health, backups, restore and release
19. Workforce plan, recruitment and candidate privacy
20. Contracts, onboarding, history and access
21. Attendance, shifts, overtime, rest days and leave
22. Compensation, payroll, PAYE/pension, payslips and payout
23. Exit, final settlement and nonemployee payees
24. Personal data, document storage, audit and retention

The local review candidate includes a new HR Process Center SOP catalogue and individual DOCX download actions. The proposed GET catalogue/download endpoints require the existing HR-or-Payroll view capability, use a fixed manifest, check real-path containment and SHA-256, and fail closed if the append-only audit write fails. Personal employee files remain separate and disabled until a private vault and record-level access controls are proven. **This code integration exists in the local candidate only and has not been committed or installed on WordPress.**

## Live environment limitation

Requested site: https://slerp.xo.je/stockland-erp-app/

Web fetch returned a cache-miss result; direct local request returned DNS resolution failure. The test environment has no WP CLI, MySQL/MariaDB CLI, Docker, or PHP `pdo_mysql`/`mysqli` extension. No live authentication, screen render, REST/download request, schema migration, database write, role denial, payroll transaction, payout or GL posting was run. Those tests are **NOT RUN**, not PASS.

## Findings by module and required controls

| Module | Source tables in baseline | Required behavior / open verification |
|---|---|---|
| Users / branches / audit / system | users, user_locations, locations, settings, workflow_events, audit_logs, notifications | Server-side least privilege, location isolation, revoked-user denial, audit retention/hash integrity and restore drill |
| Product Master / import | products, product_code_maps, product_sku_history, price_history, import_jobs/profiles/rows | Unique SKU, source mapping, validate-preview-approve-commit; distinguish catalog import from POS and physical count |
| Inventory / warehouse | balances, transactions, receipts, transfers, requisitions/items, reservations, counts/lines, adjustments, returns | Physical/reserved/available/in-transit separate; no duplicate receiving or dispatch, location scope, variance approvals and immutable corrections |
| Retail / wholesale / daily close | sales, sale_batches, invoice_items, wholesale_sales, daily_closings, payments/allocations | Retry idempotency, void/refund authority, tender-to-cash/bank reconciliation, credit/dispatch separation |
| Procurement / finance | suppliers, customers, purchase orders/items/receipts, supplier/customer invoices/items, expenses, payments/allocations, GL accounts/journals/lines | PO/receipt/invoice matching, AP/AR aging, debit=credit, idempotent journal post/reversal, period locks and external payment evidence |
| HR / payroll | four legacy tables + HRX people/time/leave/comp/rules/runs/payout/remittance/separation/payee domains | Complete time/leave math, approved effective-dated law, three-person preparer/reviewer/Owner control, frozen input hash, payout/remittance and ledger reconciliation |
| Reports / social | read-only source from sales, stock, closing and GL; HRX social snapshots | Source, date, location, timestamp, provenance and stale-data flags; no unverified live analytics or credential scraping |

## Critical blockers and release gates

**P0 — establish correct baseline:** recover exact installed 16.1.97 ZIP and deployed source hashes; take verified DB/uploads backup; restore an isolated staging clone; fingerprint live schema and host capabilities. Do not install the 16.1.98 review candidate over live 16.1.97.

**P1 — prove SOP delivery in WordPress:** keep existing 16.1.97 resources; add a separate ERP-wide Procedures/SOP listing after HR resources. Test all 24 downloads plus unauthenticated, wrong role, invalid ID, tampered hash, missing file, audit write failure, attachment MIME/filename, repeated clicks and mobile/keyboard layout.

**P2 — finish operational HR/payroll:** persisted recruitment → offer → signed contract → onboarding → approved pay setup → active employee; attendance/leave/cutoff; approved statutory registry and detailed payroll calculation; recipient payment outcomes, remittance receipts, balanced GL/PLEDGER; exit/final settlement; creator/freelancer/service-provider central AP. Missing or conflicting tax treatment must hold finalization.

**P3 — acceptance:** historical payroll rows/amounts/journal references reconciled; two synthetic parallel payroll periods; broad ERP transaction replay/rollback; role/location negative tests; backup/restore/rollback; exact deployed SHA; Owner + HR + Finance and qualified Ethiopian legal/tax/pension review.

## Legal / privacy review is open

Use official starting sources and map the controlling article, applicability, publication/effective date, current amendments and expected test vectors before activation:
- Labour Proclamation 1156/2019: https://justice.gov.et/en/law/labour-proclamation/
- Income Tax Amendment Proclamation 1395/2025 / Gazette 64: https://www.ffic.gov.et/Portals/0/1395-2017.pdf
- Ministry of Finance tax-directive register: https://www.mofed.gov.et/mof-directive/tax-directive/
- Personal Data Protection Proclamation 1321/2024: https://justice.gov.et/en/law/personal-data-protection-proclamation/
- Pension/social-security materials: https://justice.gov.et/am/sector/social-security-pensions/

All 24 SOPs are draft—not legally approved, deployed or effective. No payroll legal-compliance claim is made.

## Notion authority

- Design reset: https://app.notion.com/p/b2c96e7d1c2c45f7a0b269152fad0b2d
- Full lifecycle scope: https://app.notion.com/p/4026db0f767e81aaa3ecc4383f5402f9
- HR resource acceptance register: https://app.notion.com/p/4056db0f767e810d8ca2cbc1fe3e830e
- New detailed audit/decision page: https://app.notion.com/p/4076db0f767e815eba7cc49314197c22

**Disposition: local review candidate + audit docs only; no production approval.**