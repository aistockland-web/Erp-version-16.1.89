# Stockland Ethiopia ERP — 16.2.18 Master Installer

One WordPress plugin, one upload, one activation for the existing single-company STOCKLAND ETHIOPIA deployment. It installs the Stockland operating system for that deployment: Command Center, POS/cart, wholesale, product master and SKU/variant identity, Central Warehouse and four branches, receiving, requisitions, reservations, transfers, returns, damage, adjustments, physical counts, procurement, suppliers/customers, AP/AR, expenses, GL/PLEDGER, daily close, authorization/audit, and the HR/payroll lifecycle with 24 Amharic SOP documents.

## Install in three steps

1. **Plugins → Add New → Upload Plugin** → choose the ZIP → **Activate**. Activation creates and physically verifies the 49-table operational baseline: 29 Core/Operations, 16 Accounting/Procurement, and four legacy HR/payroll tables. It seeds Stockland's Central Warehouse and the Stockland-specific Goro, Gerji, Mexico and Megenagna branches and gives the installing administrator the Stockland Owner profile. These are defaults for this existing Stockland deployment, not generic new-customer defaults. The separate 37-table HR lifecycle extension is not created automatically.
2. Open **Stockland → System Control** and complete any remaining operational-baseline batches. HR lifecycle DDL and existing legacy-HR column changes are separate Owner-gated actions: the active Stockland Owner must review the exact schema version/fingerprint, confirm a backup, and submit a written reason through the HR approval control before that work can run. Without that approval, the 37-table lifecycle extension remains pending; Core/Finance operational readiness is independent.
3. When the operational baseline is complete, the Owner may use **Authorize live operation** for the exact ready operational schema/version. This authorization does not approve HR lifecycle DDL or Ethiopian statutory payroll rules; those retain their separate approval and acceptance gates.

## What 16.2.18 changes

- **Fixed: missing `<?php` tag in `includes/class-stockland-schema-contract.php`** (present in 16.2.14, 16.2.15 recovery and 16.2.17). PHP printed the file as text, which broke activation and corrupted REST/JSON responses so the setup wizard could stall part-way.
- **Governed one-pack installer** (`Stockland_Installer`): installs and verifies the 49-table operational baseline in bounded batches. The 37-table HR lifecycle extension and any existing legacy-HR column migration remain separate: they require active Stockland Owner approval recorded against the exact schema version/fingerprint, a backup confirmation, and a written reason in the immutable audit chain. Fresh installation does not silently approve or create the lifecycle extension. Core/Finance readiness remains independent while HR lifecycle is pending.
- **Owner go-live authorization** (`Stockland_Release_Authorization`): replaces the hard-coded `false` that made LIVE READY impossible. The Owner records one authorization, bound to this version and the exact schema fingerprint, written to the immutable audit chain. It lapses if the version or schema changes.
- **Fixed:** health check demanded `XMLReader::fromStream` (PHP 8.4 only) although the reader has a streaming fallback; PHP 8.3 could never be LIVE READY.
- **Fixed:** product code-map list queried unprefixed tables and returned nothing.
- **Fixed:** duplicate customer code returned HTTP 500 with a raw database error; now HTTP 409.
- **Fixed:** System Control referenced an undefined variable when evaluating Accounting structural gaps.

## Host requirements

PHP 8.3 or 8.4 with `mysqli`, `zip`, `xmlreader`; MySQL 8 or MariaDB 10.6+ with CREATE/ALTER privileges; HTTPS; `upload_max_filesize` and `post_max_size` of at least 8 MB (health check reports this). See `GO_LIVE_RUNBOOK.md`.

## Items only your organisation can supply

The software cannot supply these, and the app does not invent them: the qualified Ethiopian payroll/tax/pension rule register (effective-dated, approved), real compensation records, process-owner approval of the 24 SOPs, user/role/branch assignments, and opening stock/ledger balances.

## Application scope

The current plugin instance retains the single-company Stockland operational application: Command Center, POS/cart, wholesale, Product Master and SKU/variant identity, Central Warehouse and four branches, receiving, requisitions, reservations, transfers, returns, damage, adjustments, physical counts, reporting, procurement, suppliers/customers, AP/AR, expenses, GL/PLEDGER, daily close, authorization/audit, four legacy HR/payroll tables, and the separately governed HR lifecycle/resource modules. HR lifecycle activation remains Owner-approval-gated.

The one-company model describes this existing STOCKLAND ETHIOPIA deployment only. Customer-neutral, multi-tenant Apparel Supply Chain provisioning—with separate customer companies, sites, factories, warehouses, branches and users—is a future product capability and is not implemented in 16.2.18. The Central Warehouse, Goro, Gerji, Mexico and Megenagna seeds are Stockland-specific defaults and must not be presented as defaults for new customers. Existing Stockland ledgers, POS workflows, routes, capabilities and business data structures are retained; no second accounting or ledger engine is introduced.

## Runtime targets

- WordPress 6.8 or later.
- PHP 8.3 or 8.4.
- MySQL 8.0+ or MariaDB 10.6+.
- HTTPS for authenticated operation.
- PHP OpenSSL and Fileinfo extensions for private HR documents.
- PHP ZIP/XMLReader support for workbook flows; actual host extension coverage must be verified.

## Updating an existing site

1. Back up database and files, including the private vault directory and keyring.
2. Upload this ZIP over the existing Stockland ERP plugin (replace in place; never install a second copy or delete the plugin directory).
3. Activate, then follow `GO_LIVE_RUNBOOK.md`. Nothing is dropped, truncated, renamed or reseeded; existing business rows are preserved.
4. Retain the prior ZIP, database backup, vault backup and keys until you have confirmed normal operation.

## Private HR document vault

HR attachment payloads use a versioned authenticated-encryption envelope (OpenSSL AES-256-GCM implementation in the plugin) with an external site-managed keyring. The vault directory must be absolute, outside `ABSPATH` and the web document root, writable only by the hosting account, and free of symbolic-link components. Directories use restrictive permissions and object files are private. The installer contains no encryption key.

Configure in `wp-config.php` (example only; replace values securely):

```php
define( 'STOCKLAND_PRIVATE_VAULT_DIR', '/absolute/private/path/stockland-hr-vault' );
define( 'STOCKLAND_PRIVATE_VAULT_KEYRING', array(
    'key-2026-01' => 'REPLACE_WITH_64_HEX_CHARACTERS',
    'key-2026-02' => 'REPLACE_WITH_64_HEX_CHARACTERS',
) );
define( 'STOCKLAND_PRIVATE_VAULT_ACTIVE_KEY_ID', 'key-2026-02' );
```

Each key must represent 32 cryptographically random bytes (64 hexadecimal characters or strict base64 accepted by the key parser). Store the keyring in a separate encrypted secrets store and back it up separately from encrypted documents. Loss of the decryption key means loss of access to the documents. Do not put keys into the plugin, database, code repository, screenshots or support messages.

Legacy plaintext attachment migration is one file at a time and requires its expected SHA-256 from the matching ERP record. Rehearse on a verified private-vault copy before production migration. A guarded WP-CLI operation is available:

```bash
wp stockland hr-vault-encrypt <private_file_ref> <plaintext_sha256>
wp stockland hr-vault-rotate <private_file_ref> <plaintext_sha256>
```

The rotate operation re-encrypts a verified object to the configured active key. Test backup/restore and key rotation on staging before allowing HR uploads or downloads on a live host.

## Compensation and payroll controls

- Upgrade-added compensation approval defaults to `DRAFT`/review-required, never implicit approval.
- Payroll/hire selectors require explicit `APPROVED`, a positive approver identity, a genuine approval timestamp, and a different actor from the creator.
- Historical rows with an `APPROVED` label but no verifiable approval metadata require independent Owner re-review; historical paid/posted payroll is not silently recalculated.
- The production payroll calculator has no synthetic test-only execution mode.
- The application ships no default current PAYE/pension rule schedule. New compensation drafts can be recorded, but PAYE/pension preview, payroll calculation, finalization and posting remain blocked until an effective-dated rule set is supported by exact official references, qualified Ethiopian payroll/legal review and independent approval.
- Do not infer employee amounts, contribution bases, tax bands, allowance exemptions, rounding or final-settlement calculations from historical constants or labels.

## SOP library

The 24 embedded Amharic-first DOCX resources passed local byte/hash/container-integrity checks. Every SOP remains `DRAFT_NOT_APPROVED`. Integrity is not process-owner approval. Obtain Owner, Operations, HR, Finance and qualified legal approval where relevant before treating any SOP as an effective operating policy, contract or statutory instruction.

## Verification evidence (build 16.2.18)

Executed on real WordPress 6.8 + MariaDB 10.11 + PHP 8.3: single-activation fresh install of the 49/49 operational baseline (29 Core/Operations, 16 Accounting/Procurement, four legacy HR/payroll tables; Owner profile; operational schema ready); the separate 37-table HR lifecycle extension remains pending until explicit Owner approval against its exact schema fingerprint. A separately approved additive setup path was exercised in the partial-database upgrade (20 → 45 → 86 tables; HR approval gate honoured). Deep integrity checks report 52/52 on the tested operational baseline. All GET routes returned without a server error; the tested business flow PO receipt → transfer → POS sale/cart with idempotent replay → return → daily close → AP invoice/payment → customer invoice reconciled stock balances to the transaction sum and every GL journal balanced. These results do not certify full HR lifecycle or statutory payroll acceptance.

Not yet exercised by this build: two full payroll periods with approved statutory rules, role-by-role denial matrix, browser/device/PWA/print acceptance, load/concurrency, and host-specific MariaDB 11.4 behaviour. Run the runbook on your host and keep the backup until satisfied.

See `ARCHITECTURE_AND_FILE_MAP.md`, `MIGRATION_GUIDE.md` and `GO_LIVE_RUNBOOK.md`.
