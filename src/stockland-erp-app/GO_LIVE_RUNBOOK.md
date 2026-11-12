# Go-live runbook — Stockland ERP 16.2.18

## Before installing
1. Take a full backup (files + database) and confirm it restores. Existing sites: note current table counts.
2. Confirm PHP 8.3/8.4, HTTPS, and upload limits of at least 8 MB. Deactivate any older Stockland plugin copy first; do not delete data.

## Install
1. Upload the ZIP (Plugins → Add New → Upload) and activate. Fresh sites finish in one step (86/86 tables).
2. Existing partial site (for example 20/29 Core, no Accounting/HR): activation completes Core and Accounting; open **Stockland → System Control**, enter the HR approval reason, tick the backup confirmation, press **Install / complete everything now**.
3. Confirm System Control shows complete. Nothing is dropped, truncated, renamed or reseeded.

## Configure (your data; the app does not invent it)
- Create WordPress users and assign Stockland roles and branch locations (Owner, Management, Warehouse, Branch Manager, Sales, Finance preparer/approver, HR).
- Load opening stock through the receiving/adjustment workflow and opening balances through Accounting.
- HR/Payroll: enter and approve the effective-dated Ethiopian statutory rule register (PAYE bands, pension rates) after qualified review before the first payroll run. Enter real compensation records.
- Process owners review the 24 SOP documents.

## Authorize
Owner: **System Control → Authorize live operation** (written reason). Then run the deep integrity scan from the System Health screen (52 checks) and confirm LIVE READY.

## Roll back
Restore the backup. Deactivating the plugin leaves all data in place.

## Separation of duties (by design)
Preparers cannot approve their own payments/invoices; the Owner does not prepare customer invoices. Use distinct users per role.

## Host requirements checked before go-live

`live_ready` stays false until the web host allows spreadsheet uploads: PHP `upload_max_filesize` and `post_max_size` must both be at least 8 MB (recommended 16 MB and 20 MB), and PHP 8.3 or newer with MariaDB 10.6+ or MySQL 8.0+. Change them in `php.ini` or the hosting panel, then reload System Control.
