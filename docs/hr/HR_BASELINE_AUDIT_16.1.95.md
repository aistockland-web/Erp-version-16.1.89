# 16.1.95 HR/Payroll Baseline Audit — Exact CI Artifact

**Audit date:** 28 October 2026. **Type:** read-only package/source inspection; not live database or production acceptance.

| Check | Observed |
|---|---|
| SHA-256 | 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625 |
| Size | 1,102,405 bytes |
| ZIP | CRC pass; 75 unique files; one stockland-erp-app root |
| PHP files | 48 |
| DDL | 49 unique declarations |
| DDL split | 29 activator + 16 accounting + 4 HR/payroll |
| Legacy HR tables | stock_employees, stock_employee_compensation, stock_payroll_periods, stock_payroll_lines |
| Explicit foreign-key clauses in examined static DDL | 0 |

The package manifest and parsed source agree on the 49-table contract. Current Notion v0.5 supersedes historical fixed table-count proposals. Preserve the four legacy tables until dependency inventory, sanitized data health, migration reconciliation, backup/restore, rollback and separate retirement approval pass.

The source review also highlights that PAID does not prove bank settlement/remittance, employee PII in audit payloads needs minimization and scoped access, and PAYE base/pension treatment needs qualified review before statutory calculations are activated.

The 16.1.97 candidate (SHA-256 7822606ded2883d9d5faf8b58962f058c99320d64fcf2da50c9f140448adee30) contains resource REST routes and a JS download handler. Archive/hash checks, 30 embedded DOCX payloads, PHP 80/80 and JavaScript syntax passed locally. This does not prove deployment parity or solve the reported live download failure; authenticated browser/direct-REST tests on restored staging are still required.

**Not verified:** currently installed live file hashes, live DB foreign-key metadata/data health, HR/payroll runtime E2E, legal sign-off, backup restore/rollback, production deployment.
