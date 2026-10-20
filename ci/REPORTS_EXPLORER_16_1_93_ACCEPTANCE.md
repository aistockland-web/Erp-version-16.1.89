# Reports & Analytics Explorer — 16.1.93 implementation gate

## Requested default
- Report type: Branch performance
- Period: Today (business-local date)
- Location: All locations the signed-in user is authorized to see
- Output: Print / PDF
- SKU query: empty

## Verified baseline facts
- Baseline target is the 16.1.92 canonical installer and audit.
- 16.1.92 currently has date-range report inputs and summary CSV presets.
- The report UI has a `printOpsReport` handler calling the browser print dialog.
- Print styles already exist; do not change CSS unless isolated-report printing cannot be achieved with existing styles.
- The report summary endpoint accepts date parameters. A new filter must not bypass server-enforced branch authorization.
- The baseline installer is stored as a ZIP artifact in the repository; application source files are embedded inside the archive rather than tracked as editable files at the expected source paths.

## Implementation acceptance criteria
1. Report-type selector: Branch performance, Sales performance, Product/SKU, Inventory/reorder, POS, Receiving/transfers/requisitions, Returns/damages/adjustments, Physical count variance, and Finance/ledger, with each type displaying only relevant filters and sections.
2. Period presets: Today, Yesterday, Last 7 days, This month, and a validated custom date range. Today uses the business-local date, not UTC date.
3. Location selector: only authorized locations are offered. Server validates requested location IDs and intersects them with the authenticated user's permitted scope; a browser-supplied `all` must never widen scope.
4. SKU search: debounced autocomplete from an authorized server endpoint or a documented existing authorized product-search route; exact SKU matching; safe text rendering; no untrusted HTML interpolation.
5. Critical reorder: owner-only access enforced server-side (not only hidden in JavaScript). Stock availability must follow the existing reservation/stock contract and existing branch/central reorder thresholds. No inventory mutation.
6. Print/PDF: the existing Print button prints only the currently selected report, not every section. Keep existing CSS unchanged unless tests demonstrate a specific necessary print-style change. Hide filters/actions/navigation in print, include report title, period, authorized location, generation timestamp and report totals.
7. Preserve current CSV escaping, profit/cost capability gates, no staff PII export, existing POS/ledger/approval/day-close logic, and all 49 database tables. No live deployment or live-data changes.
8. Tests: JS unit/DOM tests for report type switching, filters, empty results, SKU selection, print isolation; PHP tests for unauthorized branch ID rejection and non-owner reorder denial; regression tests for CSV injection protection, profit gating, route permissions, existing schema contract, and CSS source/compiled parity.

## Stop condition for this branch
Do not claim implementation or passing tests until the actual 16.1.92 archive is extracted in CI or a verified workspace, the patch is applied to its exact source, tests are run, and the rebuilt package SHA-256 and file inventory are recorded. This file is a requirement/audit gate only; it does not modify application behavior.
