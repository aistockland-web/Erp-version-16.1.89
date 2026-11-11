# Architecture and file map — Stockland ERP 16.2.18

## Architectural boundaries

The existing WordPress plugin is retained as the deployment unit. This release performs focused modular hardening rather than a wholesale MVC rewrite that could break the established REST, data and UI contracts.

- **WordPress bootstrap / lifecycle:** plugin entry point, activator and deactivator.
- **Domain services:** products/SKU, sales, ledger, workflow, accounting, inventory, requisitions, sourcing, business calendar, notifications and HR services.
- **Persistence and integrity:** centralized DB helpers, schema inventory, sequence generation, money arithmetic, audit trail and immutable ledger/integrity services.
- **HTTP application boundary:** versioned REST controllers. Routes enforce WordPress capability, Stockland authority, branch/location and record-level checks before calling domain services.
- **Presentation:** frontend shell, JavaScript application state, REST client, modular CSS, PWA worker and templates. Browser-side values are presentation only; the server revalidates every mutation.
- **Setup authority:** WordPress System Control is exposed to the dedicated schema-install capability; that does not grant WordPress `manage_options` or general site-administration powers.
- **Private resources:** encrypted HR vault and controlled SOP download endpoint. Private files are not served as public WordPress media.

## Directory map

```text
stockland-erp-app/
├── stockland-inventory.php          # WordPress plugin bootstrap/version
├── uninstall.php                    # conservative uninstall behavior
├── includes/
│   ├── class-stockland-activator.php # setup and add-only schema creation
│   ├── class-stockland-deactivator.php
│   ├── class-stockland-db.php        # prepared DB access helpers
│   ├── class-stockland-roles.php     # role/capability profiles
│   ├── class-stockland-authority.php # management/Owner authority checks
│   ├── class-stockland-workflow.php  # transitions, maker/checker, audit events
│   ├── class-stockland-ledger.php    # inventory/financial postings
│   ├── class-stockland-integrity.php # integrity and reconciliation checks
│   ├── class-stockland-schema-contract.php # read-only table-key inventory classifier
│   ├── class-stockland-accounting.php
│   ├── class-stockland-hr.php
│   ├── class-stockland-hr-lifecycle.php
│   ├── class-stockland-hr-payroll-calculator.php
│   ├── class-stockland-payroll-statutory.php # calculation only from approved rules
│   ├── class-stockland-hr-private-vault.php  # encrypted HR attachment storage
│   ├── class-stockland-product-service.php
│   ├── class-stockland-requisition-service.php
│   ├── class-stockland-xlsx.php / class-stockland-csv.php
│   └── rest/
│       ├── class-rest-app.php        # app bootstrap, health, integrity
│       ├── class-rest-setup.php      # bounded protected setup
│       ├── class-rest-sales.php / class-rest-pos-cart.php
│       ├── class-rest-products.php / class-rest-stock.php
│       ├── class-rest-transfers.php / class-rest-requisition-v16.php
│       ├── class-rest-accounting.php / class-rest-reports.php
│       ├── class-rest-hr.php / class-rest-hr-lifecycle.php
│       ├── class-rest-hr-payroll-cycle.php
│       └── class-rest-sop-library.php
├── assets/
│   ├── app.js / pwa.js
│   ├── app.css / app.min.css
│   ├── css/01-foundation.css ... 13-enterprise-saas-system.css
│   ├── css/modules.json
│   ├── manifest.json and app icons
│   └── Stockland logo / background assets
├── resources/erp-sops/
│   ├── manifest.json
│   └── payloads/                    # 24 embedded Amharic-first draft DOCX payloads
├── templates/                       # ERP shell and controlled Excel templates
├── README.md
├── CHANGELOG.md
├── MIGRATION_GUIDE.md
├── GO_LIVE_RUNBOOK.md
├── RELEASE_MANIFEST.json
└── SBOM.spdx.json
```

## Dependency flow

```text
WordPress bootstrap
  ├── Auth / role / authority / location scope
  ├── REST controllers ──> domain services ──> DB helper
  │                         ├── workflow + immutable audit
  │                         ├── inventory / financial ledger
  │                         ├── HR lifecycle + payroll rules
  │                         └── private vault / SOP resources
  └── frontend + PWA ──> authenticated REST endpoints
```

Controllers must not trust client-provided role, approval, branch, amount, stock or tax values. Service methods validate and persist authoritative business state. A UI preview is not a source of truth for a payroll or ledger posting.

## Safe refactoring policy

Keep public REST routes, request fields, capability identifiers, ledger semantics, location scope and existing schema identifiers backward-compatible unless an explicit migration is added. Add tests first for transaction boundaries, idempotency, Maker/Checker, negative authorization and historical records. Keep synthetic data and fixtures outside the installed plugin. Never rewrite posted ledger/payroll history to make new calculations match.
