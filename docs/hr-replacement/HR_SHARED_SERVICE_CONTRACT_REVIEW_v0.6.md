# Stockland ERP — Existing Shared-Service Contract Review for HR Replacement v0.6

Date: 27 October 2026  
Baseline: checksum-locked Stockland ERP 16.1.95 installer. This is a code-level source review, not a runtime penetration test or live DB verification.

## Findings that affect implementation order

### 1. Database access and schema upgrades
Source: stockland-erp-app/includes/class-stockland-db.php

- Stockland_DB::table(name) composes the current WordPress DB prefix plus stock_ and the supplied logical name; it does not itself enforce a table allowlist. DDL uses a separate schema_query boundary that rejects a target outside the plugin Stockland namespace. New table access must therefore be backed by a reviewed schema registry, installation contract, health check and prepared SQL rather than assuming table() guarantees a known table.
- Shared read helpers route to uncached $wpdb reads, avoiding stale object cache for transactional ERP data. insert_record/update_record/delete_record use WordPress prepared CRUD primitives. Reuse them where appropriate rather than inventing a second CRUD gateway.
- execute_query routes ALTER/CREATE/DROP/TRUNCATE/RENAME/GRANT/REVOKE through schema_query. DDL is namespace-scoped but the logical migration/controller still owns the allowed change set, idempotency, safe batching and verification. No migration may drop a legacy HR table as part of ordinary plugin activation/update.
- The current source uses INFORMATION_SCHEMA/SHOW TABLES to check physical table presence. New target schema needs a versioned migration ledger or equivalent reviewed mechanism so partial installs can resume without exposing half-created feature surfaces.
- The current health code compares expected core/accounting/HR table sets and hardcodes schema_contract_exact_49 at stockland-erp-app/includes/rest/class-rest-app.php around lines 1134-1165 and 1298-1320. WordPress System Control at includes/class-stockland-admin.php around lines 217-234 also builds the expected full set from 29 Core + 16 Accounting + the four legacy HR tables. New schema approval must update both health/diagnostic paths coherently. Do not simply append tables and leave exact-49 logic unchanged.

### 2. Authority and role model
Source: stockland-erp-app/includes/class-stockland-authority.php

Observed baseline contracts:
- can_view_hr() currently returns Owner or Management role.
- can_manage_hr() requires Owner/Management plus stockland_manage_hr.
- can_prepare_hr() requires Management plus stockland_manage_hr.
- can_view_payroll() requires Owner/Management plus stockland_view_payroll.
- can_manage_payroll() requires Management plus stockland_manage_payroll.
- can_approve_payroll() requires the Owner role plus stockland_manage_payroll.

These existing separation-of-duty rules are useful and must be preserved in the replacement. They are not the final HR RBAC model: the full design requires narrower authority for confidential employee fields, assigned-candidate panel access, HR case/document access, payroll preparation/review, separate Owner approval, Finance payout and reconciliation, qualified legal/tax rule review, and read-only audit. Add these only after an approved role/RACI matrix; use current Stockland role/capability mechanisms rather than duplicating WP users. Every REST endpoint must also enforce entity/person/field/location/period/state scope in its handler. A registration-level permission callback is not the entire access contract.

### 3. Workflow events
Source: stockland-erp-app/includes/class-stockland-workflow.php

- Workflow::event() is a generic insert into stock_workflow_events containing entity type/id, from/to status, actor, optional location and JSON metadata.
- Workflow::transition() is only a transition-map predicate. It does not load/lock the record, validate actor authority, compare an expected version, manage transaction boundaries or make event history immutable by itself.
- The replacement must implement state transition validation and record locking in the domain service/handler, write the business mutation and event inside a coherent transaction, and keep the metadata PII-minimized. Do not treat an event insert alone as proof the business state transition is valid.

### 4. Audit chain and privacy
Source: stockland-erp-app/includes/class-stockland-audit.php

- Audit::log() takes generic fields (user, location, action, sku, quantity, old/new, reason, reference and approver), acquires a named MySQL lock, calculates a SHA-256 chain hash and inserts into stock_audit_logs; verify_chain() checks continuity/hashes.
- This protects tamper evidence for rows it records but does not replace route-level authorization for audit readers, field minimization, retention/deletion governance, or a correctly scoped HR record audit.
- Historical HR audit payloads may serialize contact/notes fields into generic old/new data. New HR events should record actor, object/version, action, decision, minimal evidence reference and a hash where practical; avoid copying signed-document contents, bank details, personal identifiers or full free-text case notes into generic chain fields.
- Log access to sensitive personnel/payroll data if an approved policy requires it. Distinguish immutable financial/audit evidence from privacy-sensitive payloads; a hash chain is not a permission system.

### 5. Accounting and GL/PLEDGER
Source: stockland-erp-app/includes/class-stockland-accounting.php

- ensure_payroll_statutory_accounts() establishes and verifies the expected payroll expense/payable accounts. It checks an existing account’s type and active state rather than assuming INSERT IGNORE repairs a conflicting account.
- post_journal() validates nonnegative one-sided debit/credit amounts per line, verifies account existence, uses exact-money helpers, and rejects unbalanced totals. It checks an existing journal by source_type/source_id and returns it only if the existing journal matches the requested posting, supporting idempotency. It detects whether an outer transaction is active and uses a savepoint to avoid implicitly committing a caller’s transaction.
- Retain the accepted accounting service for payroll accrual and payment, but test its unique source/idempotency behavior under concurrent requests, closed periods, a missing/inactive account, journal mismatch, audit failure and rollback.
- Existing payroll approval posts expense and payroll liabilities, then saves APPROVED state/journal reference. Existing payroll payment posts a separate PAYROLL_PAYMENT journal that debits payroll payable (account 2020) and credits BANK/CASH. The accounting direction is liability-clearing, not another salary expense; this behavior must be preserved.
- A posted GL journal is not proof a bank has accepted payment. The replacement must track payment instruction/batch, external reference, per-employee payout result, partial/failed/retry outcomes, and statutory remittance evidence separately from ledger posting. The legacy endpoint accepts BANK/CASH, records actor/time/method and moves the period to PAID but does not collect the needed external bank/batch reference or individual outcomes.

### 6. Existing HR table owner and REST facade
Sources: stockland-erp-app/includes/class-stockland-hr.php and stockland-erp-app/includes/rest/class-rest-hr.php

- The HR schema owner currently registers only employees, employee_compensation, payroll_periods and payroll_lines and has add-only required-column migrations. The REST controller directly reads/writes these table keys. Current maker/checker and transactional posting logic is valuable as a source of invariants, but does not provide workforce planning, recruitment, contract lifecycle, attendance, leave, secure documents, rule registry, payment settlement/retries or final settlement.
- Do not extend the four-table model to pretend it is the complete domain. Use the approved new model for target authority, then keep the old APIs as compatibility facades or adapters while consumers are migrated and tested.
- Exact direct calls in this locked source are: employees 14, employee_compensation 5, payroll_periods 17 and payroll_lines 13. The new workflow produces a line-level CSV of all 49 calls. These calls are source locations for manual function/query review, not automated proof of full data-flow.
- Additional consumers include frontend state/bootstrap, settings/admin/System Health, accounting REST and requisition REST, so the release checklist must cover those modules and reports too. Replacing only class-rest-hr.php or removing the old UI is not sufficient.

### 7. Locations, person identity and individual payees
Existing stock_locations is an operational physical location, not necessarily the employing legal entity, cost center, payroll group or organizational unit. Model these separately if the approved policy/data audit requires them. Existing supplier/customer/AP masters must not be used to fabricate an employee or unregistered creator/freelancer identity. Design an appropriate individual-payee/AP workflow and reuse existing approved payment/GL posting only after identity, legal classification, withholding and reconciliation requirements are tested.

### 8. Document privacy and resource handling
The baseline does not establish a private HR document vault. Do not assume WordPress media paths are private. General HR templates and private employee-specific evidence are separate resources. For contracts, identity/bank/tax/payslip documents, deny anonymous/wrong-role/wrong-person direct access and store only in an approved private repository. If the host cannot enforce the access policy, keep metadata references only and do not upload sensitive bytes. General template downloads still require valid content type/disposition, server-side permission and nonce, feedback, version/hash and audit.

## Release gates resulting from this review

1. Keep this exact baseline pinned and keep the new HR work in a separate branch/PR.
2. Complete manual query and side-effect review for every direct legacy table call and every indirect helper/report/health consumer.
3. Approve the role/RACI matrix and field/location access contract before route implementation.
4. Prove DB/migration, workflow, audit, and GL service invariants in a local isolated WordPress/MariaDB harness using synthetic data.
5. Design and review the physical schema only after domain/retention/merge/split decisions; the final table count is deliberately not predetermined.
6. Keep statutory legal rule approval separate from Owner business approval.
7. Do not migrate, post payroll, change live data or cut over until copy-only rehearsal, orphan/duplicate checks, financial reconciliation, backup/restore, rollback, two parallel payroll periods and Owner/HR/Finance plus qualified legal/payroll sign-off have passed.
