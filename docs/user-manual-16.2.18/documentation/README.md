---
description: Practical operating instructions for Stockland Ethiopia ERP 16.2.18.
---

![Stockland Ethiopia ERP User Manual — blue and orange edition](https://raw.githubusercontent.com/aistockland-web/Erp-version-16.1.89/docs/stockland-erp-user-manual-16.2.18/docs/user-manual-16.2.18/documentation/assets/stockland-erp-manual-cover.svg)

# Stockland Ethiopia ERP — User Manual

**Production reference:** Version 16.2.18  
**Company:** STOCKLAND ETHIOPIA  
**Audience:** Owner, Management, Branch Managers, Sales Employees, Warehouse Managers, Warehouse Employees, Finance users and Auditors.

This manual explains the actual Stockland ERP 16.2.18 application. It is based on the canonical source and the 16.2.18 application interface, not on a generic ERP template or later development versions.

## Visual guide

The manual uses a simple two-color reading system:

| Marker | Meaning |
|---|---|
| 🔵 **Blue — guidance** | Normal navigation, explanations and routine operating steps |
| 🟠 **Orange — attention** | Important checks, approval points and actions that require care |

Blue is the primary reading color; orange is reserved for meaningful action and caution cues. The cover artwork follows the same palette.

## Read this first

- Use the menus and buttons visible to your signed-in account. Access depends on ERP role, assigned location and server-side permissions.
- A visible button does not override the server's authorization check.
- Never enter invented stock, customer, supplier, price, tax, payroll or accounting values.
- A preview, upload or staged job is not the same as a posted transaction.
- Do not repeat a transaction because a screen appears slow. Check its queue or history first.
- Ordinary users work in the ERP front end. WordPress administration remains a separate technical area for authorized administrators.

## Important 16.2.18 boundaries

The operational baseline covers Command Center, Retail POS, Product Master, inventory, Central Warehouse, requisitions, transfers, returns and adjustments, physical counts, procurement, suppliers and customers, AP/AR, expenses, general ledger, daily close, reports, audit and access management.

Some functions have separate approval/readiness gates:

- **Daily POS Import:** Sales Employees upload and stage assigned-branch source files. Branch Managers review, edit and release them to ERP. Optional folder pickup is a separate Windows branch-PC component. A supplier POS API is not connected in this release.
- **Product Master bulk import:** Management/Owner only. It updates catalog data; it does not create stock.
- **Goods receiving:** PO-linked. Receive only against an approved purchase order and its lines. Free-form manual receipts do not post inventory.
- **HR lifecycle and payroll:** the 37-table HR lifecycle extension is separately Owner-gated. Current Ethiopian PAYE/pension calculations, payroll finalization and posting require an approved effective-dated statutory rule set.
- **Embedded SOP files:** 24 Amharic-first SOP documents are drafts. File integrity is not process-owner approval.

## Start here

1. [Getting started](01-getting-started.md)
2. [Roles and locations](02-roles-and-locations.md)
3. [Command Center](03-command-center.md)
4. Select the page for your task from the navigation.

## Evidence and screenshots

The steps and labels are grounded in the 16.2.18 source. An authenticated production browser session was not available during this documentation pass, so this manual does not pretend that screenshots are live captures. The [Screenshot capture checklist](15-screenshot-capture-checklist.md) identifies the real screens to capture before publishing a fully visual guide.

## Release identity

- Canonical source commit: 387c193c7999840c731d37acd854ec920f134507
- Installer-bound source commit: 1f796f46ad840384e6d1601f307401e98842e1b5
- Release: 16.2.18

This documentation is isolated on a documentation branch. It does not modify the production application, installer or certified release branch.
