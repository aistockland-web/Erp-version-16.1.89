# Breaking changes & migration guide — 16.2.16

## Compatibility stance

This is an update to the existing Stockland ERP plugin, not a replacement application. Existing routes, module identities, core/accounting/legacy HR table names, ledger contracts and branch model are retained. The package adds no second plugin, no independent ledger and no destructive reset.

## Behavior changes

1. **Compensation approval:** upgrade-added approval is no longer implicitly `APPROVED`. Legacy rows without `approved_by` and `approved_at` are excluded from payroll/hire activation until an independent Owner re-review. A maker cannot approve their own row. Approval requires a reason and an immutable workflow event.
2. **Payroll rule availability:** embedded current statutory percentages/tax bands/effective dates are removed as defaults. Existing historical payroll records remain untouched. Payroll generation/finalization/posting requires an approved effective-dated ruleset with legal evidence and review metadata. Compensation can be saved as a draft while statutory preview remains unavailable.
3. **Synthetic payroll path:** synthetic test execution is removed from installed payroll runtime. Test fixtures remain in isolated QA only.
4. **Private HR documents:** encrypted file envelopes and external keyring are required for new writes. Legacy plaintext files require hash-verified one-file conversion. Missing key, invalid path, symlink, hash mismatch or authentication failure rejects access rather than falling back to plaintext.
5. **Schema diagnostics:** counts are diagnostic. The legacy 49-key subset is preserved; the current source inventory includes additive HR lifecycle keys. No exact final DB schema is owner-approved by a count alone. The HRX proposal is not activated.
6. **Health wording:** release/schema/approval review is distinct from actual DB, ledger and integrity failures. Unknown or failed global integrity checks remain visible; a review-only marker is never treated as a pass.

## Upgrade preparation

- Export the database; separately back up `wp-content`, the existing plugin directory, private vault objects and key material.
- Restore backup to a separate staging instance and confirm restoration before running setup/migrations.
- Record existing plugin version and database schema marker. Export a source-to-live dictionary of table names, columns, types, defaults, nullability, indexes, foreign/reference keys and row counts.
- Preserve the 29 core + 16 accounting + 4 legacy HR subset and every historical row. Keep the additional lifecycle schema additive and review it as a separate mapping.
- Do not DROP, TRUNCATE, RENAME, reset or recreate production data tables.

## Vault key migration

1. Create a fresh 32-byte key for the `STOCKLAND_PRIVATE_VAULT_KEYRING` in `wp-config.php` and configure an active key ID.
2. Store an encrypted offline escrow copy of every key. Back up ciphertext and keyring separately.
3. Run a vault self-test on staging; test upload, role/location/object-scoped download, SHA-256 verification, tamper rejection, key rotation, backup and restore.
4. Convert legacy plaintext one object at a time using its expected hash from the ERP resource record. Stop on any mismatch; do not delete/overwrite the original until ciphertext and restored download are verified.
5. Only then enable private HR uploads/downloads on the live environment.

## Compensation and payroll transition

- Reconcile every existing compensation row. An `APPROVED` text label without an independent approver identity and timestamp is not an approval.
- Independent Owner review records the actor, timestamp, written reason and source data. Never bulk-approve historical rows.
- Load statutory rules only after a qualified Ethiopian payroll/legal adviser validates the official source, effective dates, employee coverage, contribution base, allowance treatment, rounding and final-settlement method. Record source URLs/document references and review sign-off.
- Run at least two full controlled payroll periods on staging, including corrections, failed payout retry, remittance and reconciliation. Do not reuse synthetic test fixtures or overwrite paid/posted history.

## Rollback

Rollback requires the exact prior plugin ZIP, matching pre-upgrade database backup, vault ciphertext backup and all decryption keys. Restore the database and private vault as a coordinated recovery unit. Do not downgrade plugin files over a newer live schema without first restoring the compatible DB snapshot. Rehearse the complete rollback on staging and capture verification logs.

## Breaking changes summary

- Implicit historical approval is no longer accepted.
- Default PAYE/pension calculations are no longer provided.
- HR vault storage requires encryption key configuration.
- HRX table set remains inactive and unapproved.

No live database has been migrated as part of this local package build. All upgrade behavior must be verified on a restored copy before production.


## 16.2.16 protected setup repair

After taking a restorable database and file/vault backup, open WordPress Admin → Stockland ERP → System Control. An authorized schema administrator can use **Continue all remaining setup** to advance Core, Accounting/Procurement and HR/Payroll through sequential, bounded, physically verified requests. The System Control page also exposes the HR-only continuation action after Core and Accounting/Procurement are complete. Do not use a second plugin copy or reset existing tables. If a request reports no progress or a database error, stop and inspect the exact missing table/structural item before retrying.
