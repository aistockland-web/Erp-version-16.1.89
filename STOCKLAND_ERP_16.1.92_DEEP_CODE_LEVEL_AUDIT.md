# Stockland Ethiopia ERP 16.1.92 — Release Audit

- Installer: STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip
- Size: 1,113,446 bytes
- SHA-256: adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd
- Exact base: STOCKLAND_ERP_16.1.91_CANONICAL_WORDPRESS_UPDATE.zip, SHA-256 e3dfe61dcbdfd382147407daca4ec32ed737dc35e5931d55d1ffbaf5a7337b34
- Package: 75 files under the single stockland-erp-app root, 48 PHP files.
- Baseline/schema lock: ci/release-inputs-16.1.92.lock.json. CI-only PostCSS npm and WPCS Composer lockfiles are separately generated once and shared by release jobs.

## Changes and preservation

- Adds daily/today, rolling-seven-day, and month-to-date summary CSV export to Reports & Analytics using the existing role/location-scoped response. Staff detail and live stock snapshots are omitted; gross profit is gated by the existing cost permission.
- Adds bounded streaming CSV reading only to Daily POS. CSV and XLSX both use the existing column mapping, validation, preview, staging/approval, duplicate/source hash and locked posting pipeline. Product Master stays XLSX-only.
- CSV limits are 8 MB file, 15,050 records, 250 columns and 64 KB cells, with UTF-8 and NUL validation. No new business tables, REST routes, role capabilities, external providers, cron dependency, database trigger, ledger posting, or payroll calculation changes.
- Existing SQL day lock, transaction and import-job FOR UPDATE remain intact. CSS module release metadata is updated without changing module CSS source or compiled files.

## Verification and production boundary

Exact-version CI must pass PHP 8.3/8.4, package checksum/inventory, JavaScript, CSV tests, payroll/System Health, existing notification/payroll preview, CSS parity, WPCS and Semgrep. Passing CI does not prove live-site acceptance.

The live 16.1.91 health snapshot supplied by the owner showed 49/49 schema and 103 REST routes passing, with payroll compensation classification as the only failed production gate due to seven active employee records missing compensation records. This candidate does not modify or resolve those records. No live WordPress/MariaDB install or mutation, live role acceptance, backup restoration or rollback was performed by the builder.

## Exact-version GitHub Actions result

- PHP 8.3/8.4 exact-package checks and regression gates passed before package publication.
- Daily POS streaming CSV regression tests and read-only period summary CSV tests passed.
- Payroll/System Health, notification hooks, payroll preview, CSS parity, WPCS and Semgrep passed.
- No live WordPress/MariaDB installation or database mutation was performed.
