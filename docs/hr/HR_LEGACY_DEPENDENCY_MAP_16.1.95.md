# HR Legacy Dependency Map — ERP 16.1.95

**Scope:** static source mapping of the exact checksum-locked 16.1.95 inner installer; not a live database audit.

## Artifact baseline

- SHA-256: `494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625`
- 1,102,405 bytes; 75 unique plugin files; 48 PHP files; ZIP CRC passes.
- 49 DDL declarations: 29 in `includes/class-stockland-activator.php`, 16 in `includes/class-stockland-accounting.php`, four in `includes/class-stockland-hr.php`.
- Examined static DDL has no explicit foreign-key clauses. This is not a claim about live database metadata.

## Four legacy HR tables — transition/history sources

| Table | DDL source | Direct source consumers observed | Migration concern |
|---|---|---|---|
| `employees` | `includes/class-stockland-hr.php` | `includes/rest/class-rest-hr.php`; `includes/rest/class-rest-app.php`; `includes/class-stockland-admin.php`; `assets/app.js` | Employee identity/status; pending/active decisions; linked compensation and payroll history. Preserve IDs and employment status/effective dates. |
| `employee_compensation` | `includes/class-stockland-hr.php` | `includes/rest/class-rest-hr.php`; `includes/rest/class-rest-app.php`; `includes/class-stockland-payroll-statutory.php`; `assets/app.js` | Existing compensation snapshots and allowance breakdowns feed payroll. Old `required_columns()`/upgrade paths can ALTER existing tables. |
| `payroll_periods` | `includes/class-stockland-hr.php` | `includes/rest/class-rest-hr.php`; `includes/rest/class-rest-app.php`; `includes/class-stockland-admin.php`; `assets/app.js` | Period key/status/totals/close and journal linkage are historical anchors. Preserve state and reconcile period-to-run mapping. |
| `payroll_lines` | `includes/class-stockland-hr.php` | `includes/rest/class-rest-hr.php`; `includes/rest/class-rest-app.php`; `assets/app.js` | Itemized amounts, classifications, employee/period references and approval locks. Keep historical lines immutable and crosswalk IDs. |

## Cross-cutting dependencies

- **Setup/health:** `class-stockland-hr.php`, `class-stockland-admin.php`, and `rest/class-rest-app.php` enumerate legacy keys, check schema gaps and validate compensation readiness. These need dual awareness during coexistence so an old missing-column check does not mutate or incorrectly block the successor module.
- **REST/business flow:** `rest/class-rest-hr.php` has 13 registration call sites, expanding to 15 method/path operations. Retain independent Management preparation/Owner approval only after row scope, field scope, locking and atomicity are tested against the new model.
- **Statutory calculation:** `class-stockland-payroll-statutory.php` owns legacy calculator and compensation validation. Do not silently make its constants the successor's legal policy. PAYE base/pension interaction and other unresolved matters remain blocked pending independent qualified review.
- **Accounting:** `class-stockland-accounting.php` contains shared journal/GL services. The successor must post balanced, idempotent entries through accepted services; failed posting/audit must roll back and posted history is corrected through linked reversals/adjustments.
- **Audit, workflow and authority:** evaluate `class-stockland-audit.php`, `class-stockland-workflow.php`, `class-stockland-authority.php` and notification services for append-only history, capability enforcement, transaction rollback and PII minimization.
- **Frontend:** `assets/app.js` renders the old employee/payroll workspace. Remove old endpoints as a source of truth only through a reviewed route migration and preserve report/history parity.

## Critical schema-upgrade risk

The legacy HR class declares add-on columns for compensation, payroll periods and lines; its install/upgrade path can ALTER those existing tables. “No DROP” alone does not prove that legacy tables remain frozen. Successor installation/read-only access must not invoke old upgrade methods as a side effect. Add a regression test comparing legacy DDL and verifying that the new module's installation does not alter legacy structures or rows.

## Migration gates

1. Capture installed-file hashes and live DDL/indexes before cutover; this archive is not proof of live parity.
2. Generate sanitized quality counts for duplicate identities, missing/orphan compensation, invalid dates, period/line inconsistencies, orphan journal/payment references and balance gaps. No PII/salary/bank/tax identifiers in GitHub or CI artifacts.
3. Approve the data dictionary, ERD and source-to-target ID crosswalk; do not preselect a table count.
4. Rehearse in disposable staging. Keep the four legacy tables intact/read-only throughout coexistence.
5. Reconcile payroll values by period/person (gross/items, net, PAYE, pension, payment, remittance and GL/PLEDGER); document and approve every difference.
6. Prove repeatable migration, verified backup restore and rollback after simulated partial cutover.
7. Retire legacy tables only under a separate approved change after pilot sign-off and the rollback horizon.

**Conclusion:** these tables are connected to REST, health/setup and UI behavior. A real replacement requires coordinated dependency cutover, not just new DDL or a version label. No production schema or data was changed.
