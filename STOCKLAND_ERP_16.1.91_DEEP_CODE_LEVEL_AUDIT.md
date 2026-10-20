# Stockland Ethiopia ERP 16.1.91 — Deep Code-Level Release Audit

- Installer: STOCKLAND_ERP_16.1.91_CANONICAL_WORDPRESS_UPDATE.zip
- Size: 1,108,406 bytes
- SHA-256: e3dfe61dcbdfd382147407daca4ec32ed737dc35e5931d55d1ffbaf5a7337b34
- Exact base: 16.1.90 SHA-256 6a7619c834fb37ef69024e68f15ae524efae39b5586b01441ab47270842588e5
- Package: 74 files, one stockland-erp-app root, 47 PHP files.

## Included changes

- Shared notification title/body/metadata/action/toast typography improved for all authorized roles, with action/success/warning/danger/info accents and responsive button touch targets. Existing notification authorization, action IDs and JavaScript handlers remain unchanged.
- Both Excel templates remain unprotected for ordinary entry. Physical Count explicitly defines Count Date as the actual count date in YYYY-MM-DD format; history/effective dates are not overwritten. Product Master remains quantity-free and stock adjustments still follow authorized, reasoned, auditable workflows.
- Plugin and frontend route/cache markers are 16.1.91. JavaScript, REST business services, role capabilities, payroll formulas, schema, posted inventory/financial ledgers and SOP transitions are unchanged. The 49-table contract is retained.

## Verification

The exact-head workflow runs package integrity, source-preservation, editable workbook/date controls, CSS source-build/minified parity, PHP lint on 8.3 and 8.4, JavaScript syntax, payroll/System Health, notification action hooks, payroll preview, PostCSS, WPCS and Semgrep. Enterprise security runs dependency/license inventory, secret scanning, Dependency Review and CodeQL. The linked run records are authoritative; this document alone is not a CI assertion.

## Production acceptance boundary

No authenticated live WordPress/MariaDB connection or deployment was performed by this build. GitHub CI does not verify live ledger/audit-chain/AP/AR/GL/PLEDGER reconciliation, role-by-role SOP operation, backup restoration/rollback, or physical-device PWA operation. Those production acceptance CAPs remain open until evidenced on the running site.
