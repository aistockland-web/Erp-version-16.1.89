# Stockland ERP GitBook Read-only Bridge 1.0.0 — Release Audit

**Target:** Stockland ERP 16.2.18  
**Canonical ERP source commit:** `19fe43981723f30e3839d1c8a67498f50c8134d4`  
**Integration branch:** `integration/stockland-gitbook-readonly-bridge-16.2.18`  
**CI workflow run:** [44644560257](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/44644367711)  
**CI result:** SUCCESS  
**Workflow artifact ID:** `14588500023`  
**Installable ZIP SHA-256:** `5acb0e517520d0dad1d9a62c58889eae648380b4078a8dc721a2db88e3cd9070`

## Verified gates

- PHP 8.3 syntax check: PASS.
- MCP behavior harness: PASS — bearer-token allow/deny, initialize, tools/list, Product Master search, exact SKU lookup, import contract and unknown/write tool denial.
- Python static contract tests: PASS (7 tests).
- Package isolation guard: PASS.
- Installable ZIP build and artifact upload: PASS.
- Downloaded installer ZIP CRC and contents: PASS; one plugin root with only runtime PHP, README, release manifest and security note. QA source files are excluded from the installer.
- Installer ZIP SHA-256 independently recomputed after download: PASS; matches the GitHub Actions checksum sidecar.

## Isolation review

- No ERP Core files changed.
- No ERP schema migration or table creation/alteration included.
- No ERP business-record DML or transaction posting included.
- No changes to the published GitBook User Manual 16.2.18.
- The add-on uses one plugin-owned WordPress option for its bearer token.
- Exact-version guard rejects access unless the live ERP reports version 16.2.18.
- Only four fixed, authenticated, read-only MCP tools are exposed.
- Product data excludes prices, purchase costs and stock quantities; staff-facing import contract excludes restricted pricing fields.

## Open acceptance gates — not passed

- Live activation on `https://stockland.freehosting.dev`: NOT RUN.
- Authenticated requests against the real MariaDB Product Master table: NOT RUN.
- GitBook custom MCP server registration using the post-activation endpoint and bearer token: NOT RUN.
- GitBook Ask end-to-end real-session test: NOT RUN.
- Production certification: NOT GRANTED.

The draft pull request must remain unmerged until the live host and GitBook connection gates are completed. The published manual remains the sole SOP source of truth.
