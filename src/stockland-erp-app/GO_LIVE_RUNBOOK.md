# Go-live runbook — Stockland ERP 16.2.18

> **Deployment scope:** This runbook installs or updates the existing single-company STOCKLAND ETHIOPIA deployment only. It does not provision separate customer tenants or implement multi-tenant isolation. The Central Warehouse and Goro, Gerji, Mexico and Megenagna location seeds are Stockland-specific defaults, not generic new-customer defaults.

## Before installing
1. Take a full backup (files + database) and confirm it restores. Existing sites: note current table counts.
2. Confirm PHP 8.3/8.4, HTTPS, and upload limits of at least 8 MB. Deactivate any older Stockland plugin copy first; do not delete data.

## Install
1. Upload the ZIP (Plugins → Add New → Upload) and activate on the existing Stockland instance. Fresh Stockland installs create and verify the 49-table operational baseline (29 Core/Operations, 16 Accounting/Procurement, four legacy HR/payroll tables). The 37-table HR lifecycle extension is separate and remains pending by default.
2. For an existing partial site, activation/setup completes the missing operational-baseline tables additively. If HR lifecycle creation or a legacy-HR column migration is required, the active Stockland Owner must review the exact schema version/fingerprint, confirm a restorable backup, and submit the required written reason through the Owner-only System Control approval path before the HR DDL runs. No HR lifecycle DDL is attempted without that approval.
3. Confirm System Control reports the operational baseline and HR lifecycle states separately. Nothing is dropped, truncated, renamed or reseeded by the documented add-only setup path.

## Configure (your data; the app does not invent it)
- Create WordPress users and assign Stockland roles and branch locations (Owner, Management, Warehouse, Branch Manager, Sales, Finance preparer/approver, HR).
- Load opening stock through the receiving/adjustment workflow and opening balances through Accounting.
- HR/Payroll: enter and approve the effective-dated Ethiopian statutory rule register (PAYE bands, pension rates) after qualified review before the first payroll run. Enter real compensation records.
- Process owners review the 24 SOP documents.

## Authorize
Owner: **System Control → Authorize live operation** (written reason) only after the operational baseline is complete and its integrity checks pass. Review the HR lifecycle readiness separately: operational readiness does not mean HR lifecycle DDL is approved or complete. Before enabling that extension, use the separate Owner-only approval tied to the exact schema fingerprint. Run the deep integrity scan from System Health (52 checks) and retain the result with the exact source/package evidence.

## Roll back
Restore the backup. Deactivating the plugin leaves all data in place.

## Separation of duties (by design)
Preparers cannot approve their own payments/invoices; the Owner does not prepare customer invoices. Use distinct users per role.

## Host requirements checked before go-live

`live_ready` stays false until the web host allows spreadsheet uploads: PHP `upload_max_filesize` and `post_max_size` must both be at least 8 MB (recommended 16 MB and 20 MB), and PHP 8.3 or newer with MariaDB 10.6+ or MySQL 8.0+. Change them in `php.ini` or the hosting panel, then reload System Control.
