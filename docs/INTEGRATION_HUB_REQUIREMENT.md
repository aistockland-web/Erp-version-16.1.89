# Stockland Universal POS & External Systems Integration Hub — requirement and build slices

Status: requirement approved as the target architecture by the Owner (conversation 2026-11-16). Not yet implemented.
Baseline protection: certified 16.2.18 stays the protected baseline. The hub is built on branch `feature/integration-hub-2026-11-16` with its own tests and evidence.
FIS-NANO is the first candidate for a real connector test, not the foundation.

## What already exists (reused, not rebuilt)
| Hub component | Existing asset |
|---|---|
| Import Job Control Center | `stock_import_jobs` / `stock_import_rows`, status workflow, manager release gate (`/import/daily-sales/*`) |
| File Import Engine | Excel/CSV daily sales and product import (three-option intake, PR #115) |
| Data Mapping Engine | `stock_import_profiles`, code maps `/import/code-maps` (external code -> SKU/variant, as-of date) |
| Reconciliation | POS summary reconciliation in daily import |
| Security and Audit | audit ledger, roles, maker-checker, AES-256-GCM vault key handling |

## Gaps (to build)
1. Integration Registry: vendor/product/connector version, branch, device id, connection type, auth method, direction, frequency, last sync, health, error history.
2. Connector framework: documented adapter interface; a connector turns vendor data into the versioned common transaction format v1.
3. Secure intake: per-connection credential (shown once, stored protected), HMAC-signed webhook/API endpoint, idempotency on external transaction id, no direct writes to inventory or ledger.
4. Trusted-source auto-posting policy (Management-configured): clean, duplicate-free rows post automatically; unknown product, unusual total or reconciliation difference go to review.
5. Monitoring dashboard: last sync, rejected records, retries, failed-job queue with controlled retry.
6. Local device gateway (outbound-only from the branch), SFTP pickup, outbound price/stock export.

## Build slices (each its own PR, CI green before the next)
- Slice 1: registry tables (add-only) + REST + Owner/Management activation (two-level approval) + UI list/form.
- Slice 2: common transaction format v1 + signed webhook intake -> existing staging + idempotency + audit; E2E (replayed delivery is harmless, bad signature refused, unknown SKU held).
- Slice 3: monitoring, failed-job retry, trusted-source policy.
- Slice 4: SFTP/file connector, FIS-NANO connector (needs vendor API or export sample), outbound sync.

## Needs from the Owner
- A FIS-NANO (or other POS) sample export or vendor API document to build and test the first real connector. Synthetic data only until then.
