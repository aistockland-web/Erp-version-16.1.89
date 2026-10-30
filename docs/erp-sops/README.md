# ERP-wide SOP library — v1.0-DRAFT

The 24 Amharic-first Word SOPs are supplied as a separate local deliverable and are bundled into the local 16.1.98-HRX review candidate. This repository page describes their scope and acceptance boundary; it does not mean the binary ZIP has been released or installed.

## Document catalogue

| SOP ID | Area |
|---|---|
| STK-ERP-SOP-001 | Document control / governance / change |
| STK-ERP-SOP-002 | Users, roles, locations, approvals |
| STK-ERP-SOP-003 | Product Master, SKU, sizes/colors, price history |
| STK-ERP-SOP-004 | Excel/CSV import and validation |
| STK-ERP-SOP-005 | Central Warehouse receiving |
| STK-ERP-SOP-006 | Transfers and receiving variance |
| STK-ERP-SOP-007 | Requisitions, reservations, replenishment |
| STK-ERP-SOP-008 | Physical counts and adjustments |
| STK-ERP-SOP-009 | Damage, returns and write-offs |
| STK-ERP-SOP-010 | Retail POS, voids, refunds |
| STK-ERP-SOP-011 | Daily close, cash and payment reconciliation |
| STK-ERP-SOP-012 | Wholesale |
| STK-ERP-SOP-013 | Procurement and supplier invoices |
| STK-ERP-SOP-014 | Accounts payable, expenses and payments |
| STK-ERP-SOP-015 | Accounts receivable and collections |
| STK-ERP-SOP-016 | General Ledger/PLEDGER and period close |
| STK-ERP-SOP-017 | Reports, Management Intelligence, manual social snapshots |
| STK-ERP-SOP-018 | System health, backups, restore, release |
| STK-ERP-SOP-019 | Workforce plan, recruitment, candidate privacy |
| STK-ERP-SOP-020 | Contract, onboarding, history, access |
| STK-ERP-SOP-021 | Attendance, shifts, overtime, rest days, leave |
| STK-ERP-SOP-022 | Compensation, payroll, PAYE/pension, payslip/payout |
| STK-ERP-SOP-023 | Exit, final settlement, non-employee payees |
| STK-ERP-SOP-024 | Data privacy, private documents, audit, retention |

Every document has an ID, v1.0-DRAFT state, issue date, process owner, approver placeholder, effective-date placeholder, review cadence, steps, controls, evidence, measures, source-table map and legal review notes. The set maps all 49 baseline table names and all 33 HRX logical table names; it is a static mapping, not proof of runtime implementation.

## Delivery / validation boundary

- Local SOP ZIP: `STOCKLAND_ERP_ALL_ERP_SOP_LIBRARY_v1.0_DRAFT.zip`.
- Local ERP review candidate: `STOCKLAND_ERP_16.1.98_HRX_ERP_WIDE_SOP_REVIEW_ONLY.zip` (built on 16.1.95, NOT the installed 16.1.97 package).
- Local tests: 727 HRX domain assertions passed; all 51 candidate PHP files passed syntax; app.js/pwa.js syntax passed; 24 DOCX files/hash/control checks passed; ZIP CRC and embedded hashes passed.
- Live WordPress/MariaDB E2E is NOT RUN. The site URL was unavailable from the current environment and no local WordPress/MariaDB testing stack existed.
- The new SOP UI/API wiring is in the local review candidate only; it is not yet committed as executable repository source.
- No SOP is effective until its process owner and approver sign; statutory portions require qualified Ethiopian professional review.

## 29 October evidence refresh

Individual repository procedures are indexed in [the SOP register](ERP_WIDE_SOP_REGISTER_2026-10-29.md). For authoritative baseline traceability, see [ERP-wide audit refresh](../audits/STOCKLAND_ERP_WIDE_AUDIT_REFRESH_2026-10-29.md), [49-table CSV](../audits/STOCKLAND_ERP_SOURCE_TABLE_COVERAGE_49_2026-10-29.csv), and [49-table JSON receipt](../audits/STOCKLAND_ERP_SOURCE_TABLE_COVERAGE_49_2026-10-29.json). The locked source ZIP is 16.1.95 SHA-256 `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`. Installed 16.1.97 binary parity remains unverified; `https://slerp.xo.je/stockland-erp-app/` did not resolve DNS from this execution environment, so live E2E is NOT RUN. Production remains NO-GO.


## Current offline pack checksum

Current DOCX+PDF+Markdown offline SOP pack: **SHA-256 `22a48035e7cb70635f2fc01dd508c33db1da5f8363cacc35a1aca981a3c33f66`**, 4,426,124 bytes, 77 ZIP members. The individual document SHA-256 manifest validates. Older SOP ZIP hashes are superseded. The archive is a document library, not an installable WordPress release.
