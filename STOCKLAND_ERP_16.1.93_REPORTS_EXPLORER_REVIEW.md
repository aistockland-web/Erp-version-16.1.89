# Stockland ERP 16.1.93 — Reports Explorer isolated candidate

## Baseline and scope

Built from the exact 16.1.92 canonical WordPress update ZIP:
- SHA-256: `adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd`
- ZIP size: 1,113,446 bytes; 75 plugin files beneath `stockland-erp-app/`.
- Database version/schema contract retained: 29 core/operations + 16 accounting/procurement + 4 HR/payroll = 49 tables.

## Requested defaults

- Report type: Branch performance
- Period: Today (ERP site-local date)
- Location: All authorized locations
- SKU / product / barcode search: empty

The report selector supports Branch performance, Sales performance, Product/SKU performance, Inventory availability, Critical reorder, Receiving/transfer status, Transfer variance, Physical-count variance, and Returns/adjustments summary. SKU filtering is available only on report types where the server implements exact SKU filtering.

## Permission and data boundary

- The report endpoint verifies an existing reports capability server-side.
- The location parameter is validated server-side; users cannot use a client-edited location ID to expand their authorized scope.
- Critical reorder is Owner-only in the REST permission callback and checked again by the handler.
- Gross profit fields are returned only to users with the existing cost/profit capability.
- Report computation is read-only. Suggested replenishment is a calculation only; it does not create purchase orders or change stock.
- No table/migration/DB-version changes. CSS source and compiled CSS are byte-identical to 16.1.92; `assets/css/modules.json` release metadata alone aligns the route/cache release ID. This metadata change must not be mistaken for a stylesheet change.

## Print / PDF

The UI prints the currently selected report only; the browser print dialog's “Save as PDF” produces the PDF. Filter controls and row-detail overlay are temporarily hidden while printing and restored after the print dialog closes. CSV export uses the selected report and its displayed columns.

## Reproducible build and review

The repository tracks the canonical installer artifact, not a checked-in expanded plugin source tree. The patch payload is compressed to keep the source change reviewable within this artifact-oriented release repository. CI decodes it to a normal unified patch, dry-runs and applies it against the checksum-locked ZIP, builds a candidate installer, produces the readable patch as an Actions artifact, and runs static contract checks, PHP lint, JavaScript syntax checks, and the existing period-summary CSV regression test.

CI passing is necessary but not equivalent to an authenticated WordPress integration test. This branch has not been deployed. Before release, review the readable patch artifact and test branch/warehouse/management/owner role and location combinations on a staging clone. No live database/data/workflow was changed by this candidate.
