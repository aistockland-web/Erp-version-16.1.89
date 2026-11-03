# HR replacement Phase 0 — live-clone diagnostics

**Scope:** read-only data-health evidence for a restored disposable WordPress/MariaDB clone. This utility is not an ERP feature and must not be installed or exposed as an HTTP endpoint.

## Run

From a shell with WP-CLI installed, against the clone URL/configuration and with the Stockland ERP plugin loaded:

    wp eval-file ci/diagnostics/hr_replacement_clone_audit.php > hr-clone-audit.json
    php -r '$j=json_decode(file_get_contents("hr-clone-audit.json"),true); if(!$j){fwrite(STDERR,"invalid JSON\n");exit(2);} echo json_encode(["schema"=>$j["schema"]["actual_matching_table_count"]??null,"missing"=>$j["schema"]["missing_expected_keys"]??[],"query_errors"=>$j["query_errors"]??[],"fingerprint"=>$j["schema"]["schema_fingerprint_sha256"]??null],JSON_PRETTY_PRINT),PHP_EOL;'

The second command displays a compact summary, not record-level data. Keep the full JSON inside the restricted project workspace.

## Safety and privacy

- Do not run against production. First restore a verified backup into a disposable staging clone.
- The utility issues SELECTs and reads INFORMATION_SCHEMA; it does not apply DDL, insert/update/delete rows, start payroll, create journals, or change employee records.
- It emits aggregate counts and schema metadata only. It intentionally does not export names, phone/email, salaries, bank details, TINs, identity documents, contracts, notes, or raw payroll lines.
- The artifact hash shown is the expected 16.1.95 source baseline. The tool does not prove the live plugin files match that hash.
- Engine row counts from INFORMATION_SCHEMA.TABLES are approximate on InnoDB. Treat the data-health figures as investigation leads for reconciliation, not a cutover certificate.
- An OBSERVED count is a measurement, not an automatic pass. NOT_RUN, a missing table/column, or query error is a blocker until investigated.
- The overlap check flags intersecting period ranges; an authorized reviewer must distinguish bad regular-period overlap from a legitimate off-cycle/correction design after examining the permitted business pattern.
- The period/header mismatch check compares aggregate legacy line totals to legacy period totals using a 0.01 tolerance. Validate field meanings and rounding against the exact live code before declaring a financial discrepancy.
- Never attach the full report to a public GitHub issue. Share only sanitized schema hashes, aggregate issue counts and masked identifiers in a restricted review channel.

## Expected output

The JSON includes:
- Exact WordPress/PHP/DB versions, extension availability, request limits and timezone.
- The 49-key expected application-table contract, missing/unexpected table keys, engines, collations, approximate rows, column/index metadata, FK metadata and SHA-256 schema fingerprint.
- Counts for duplicate/blank employee codes, orphan compensation/payroll links, missing effective compensation, negative/missing compensation inputs, duplicate payroll lines, invalid/overlapping periods, posted-period journal gaps, unbalanced linked payroll journals and payroll header/line mismatches.
- Query-error identifiers and the explicit NO_GO_UNTIL_REVIEWED cutover status.

## Still required after execution

Compare this clone schema fingerprint to the frozen source DDL and to the separately recorded deployed package/source hashes. Inspect every code path, report, Health diagnostic, import/export, role/location permission and GL/AP integration. Have HR/Finance review each aggregate count and reconcile the payroll/payments/journals with protected source records. Perform backup restore and rollback rehearsal. Do not run any migration or retire the legacy four tables solely because this audit completed.
