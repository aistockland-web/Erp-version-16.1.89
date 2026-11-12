# Stockland ERP 16.2.18: final release review (2026-11-12)

Installer: `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`, SHA-256 `be60d5645a3717494afeb198b65d89dfd33db192f5c0c0c36d10d04f1ede428a`, 123 files, 86 tables (29 core, 16 accounting/procurement, 41 HR/payroll).
Branch `delivery/stockland-erp-16.2.18-master-installer-2026-11-11`, PR #89. GitHub has no releases or tags; none were created.

## What is in the one pack

| Area | Contents | Evidence |
|---|---|---|
| Installer | Single activation builds Core, Accounting and HR schema, verifies every step, never drops or reseeds; Owner go-live authorization bound to version and schema fingerprint, written to the audit chain | CI run 43176369919 and fresh run 43210783292: MySQL 8.0.46 and MariaDB 10.11.19 PASS, deep scan 52/52 |
| Operations | Products and variants, locations, stock ledger, receipts, transfers, POS and wholesale sales, returns, requisitions, procurement, reports, XLSX import | Business-workflow scripts PASS, 0 unbalanced journals |
| Finance | Chart of accounts, GL journals, AR/AP, expenses, customer invoices, maker-checker | Same scripts PASS |
| HR core | Employees, recruitment and hire activation, contracts, onboarding, leave, compensation, exits and final settlement, private document vault, SOP and resource library, management social reporting | Schema and routes present; physical tables verified |
| Payroll | Payroll runs (create, generate, submit, approve), payment batches with per-item results and reconciliation, statutory remittances and reconciliation, reimbursements, individual payees and obligations, GL posting | Schema and routes present; business payroll run NOT RUN (see below) |
| SaaS-style web UI | Self-contained responsive app (Bootstrap 5.3.8 bundled, no CDN), role-aware navigation across Sales and POS, Inventory, Warehouse, Procurement, Finance, HR and Payroll, Management, Reports, Settings | Browser smoke PASS at desktop and 390 px, no overflow |
| Dev container / Codespaces | `.devcontainer` plus `verify-master-installer.sh`; environment validation workflow | Environment validation PASS in CI; interactive Codespace start NOT RUN |

## Payroll: confirmed gate, not fabricated rules

The payroll calculator refuses to run without an Owner-approved, effective-dated Ethiopian statutory rule set carrying an official HTTPS source, its SHA-256, the clause, and independent qualified legal/payroll review evidence; the rule creator cannot approve their own rule. Confirmed on a live install: calculating without such a rule set is refused. This is deliberate. No tax bands, pension rates or allowance exemptions are shipped, because they cannot be asserted by code. Before the first live payroll the HR preparer drafts the rule set through `POST /hr/lifecycle/rules`, and the Owner approves it with the reviewer note.

## Not run

- Interactive Codespace start and the dev container verification script inside it.
- A complete synthetic payroll cycle (needs an approved rule set first).
- Upgrade path from 16.2.13/16.2.15 on a populated database (the partial-database upgrade was tested on MariaDB only).
- Merge, tag, release, deployment: require the Owner's explicit authorization.

## Host requirements

PHP 8.3 or newer, MariaDB 10.6+ or MySQL 8.0+, `upload_max_filesize` and `post_max_size` of at least 8 MB (16 MB and 20 MB recommended).
