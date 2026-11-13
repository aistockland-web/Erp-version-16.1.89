# Corrective Action Plan (CAP)

## Goal

Deliver one full-scale, end-to-end, live-production ERP web application (WordPress one-pack installer 16.2.18 and its successors) covering operations, finance, HR/payroll, vault, authorization, reporting, SOPs, responsive UI, PWA and security. Each item below is a work package with a measurable acceptance result. An item is complete when its acceptance evidence is recorded as PASS with source SHA, run ID and artifact hash. Status lines record measured results.

Rules for this folder: edit with goal-first wording; record measured results with source SHA, run ID and artifact hash; the Owner alone authorizes release actions on GitHub.

## Work packages

- [ ] **CAP-01 — Repository history and integration register** (High)
  - Goal: Keep one register of every branch, PR, merge base, diff, test result and integration decision.
  - Acceptance: Each workstream appears with source SHA, branch, PR and decision.
  - Evidence so far: Register in 03_GITHUB_INVENTORY. Merged on the delivery branch: PR #102 (transfer notifications), #104 (Owner HR approval and upgrade acceptance), #106/#107 (Management Intelligence separated from HR), #108 (immutable variant identity), #109 (evidence refresh). Base head 1ecb933.
  - Status: IN PROGRESS
- [x] **CAP-02 — Exact release provenance** (High)
  - Goal: Bind source commit, run ID, artifact ID, installer SHA-256, SBOM and manifest into one record.
  - Acceptance: Independent SHA-256 of the installer equals the recorded value; the package tree equals the source at the recorded commit.
  - Evidence so far: Verified 2026-11-13: installer SHA-256 85a8221295718d0b4ffa02c153d63b57c09aeba12af199fbebf300ddd0dbda9d recomputed from the supplied file and equals the recorded value; 124 files; tree equals repository source at 0bb053f except the CI-generated manifest. Run 43432115923, artifact 14039973805.
  - Status: PASS
- [ ] **CAP-03 — Setup and documentation consistency** (High)
  - Goal: Present one table-count statement everywhere: 86 application tables, with the operational baseline and the separately approved HR lifecycle group named identically in README, runbook, UI and System Control.
  - Acceptance: README, runbook, UI text and installer constants give the same split.
  - Evidence so far: The installer creates and verifies 86 tables (deep scan 52/52). The documented split must be aligned to the single wording above.
  - Status: NEXT
- [ ] **CAP-04 — Full HR lifecycle end to end** (Critical)
  - Goal: Run hire, compensation, payroll run (create, generate, submit, approve), payment batch and results, reconciliation, statutory remittance, correction and final settlement on a disposable site.
  - Acceptance: Two consecutive synthetic periods balance to the cent and post to the GL with maker-checker separation.
  - Evidence so far: Calculator vectors and fail-closed refusal paths pass (s7, 9 checks). The cycle runs once the Owner supplies an approved, sourced rule set for the disposable site.
  - Status: NEXT; OWNER INPUT: approved rule set
- [ ] **CAP-05 — Inventory and warehouse breadth** (High)
  - Goal: Cover requisitions, reservations, physical counts, damage, adjustments, write-offs and five-location reconciliation.
  - Acceptance: Isolated fixtures run on MariaDB and MySQL 8 with zero reconciliation differences.
  - Evidence so far: Transfers (with notifications), receipts, counts and balances covered by s2, s3, s8. Remaining flows to add as fixtures.
  - Status: IN PROGRESS
- [ ] **CAP-06 — POS and wholesale workflows** (High)
  - Goal: Cover multi-line retail, returns, voids, daily close, wholesale reservation and fulfilment.
  - Acceptance: Every flow posts balanced stock and GL entries.
  - Evidence so far: Sale, return and daily close covered (s4). Voids, multi-line carts and wholesale fulfilment to add.
  - Status: IN PROGRESS
- [ ] **CAP-07 — Excel/CSV import** (High)
  - Goal: Import real exported formats with mapping, preview, duplicate protection, commit and rollback.
  - Acceptance: Fixture files import identically twice (idempotent) and roll back cleanly.
  - Evidence so far: Import routes exist (13). Fixture-based WordPress E2E to add.
  - Status: NEXT
- [ ] **CAP-08 — Finance end to end** (High)
  - Goal: Cover AP/AR allocations, expenses, payments, GL reversal and close boundaries.
  - Acceptance: Trial balance equals the sum of journals after every scenario.
  - Evidence so far: AP and customer invoice flows covered (s5, s6) with 0 unbalanced journals.
  - Status: IN PROGRESS
- [x] **CAP-09 — Security gates on the exact package** (High)
  - Goal: Run PHP lint, PHP 8.3-8.4 compatibility, secret scan, Semgrep and WordPress security sniffs over the exact package, and fail on any new finding.
  - Acceptance: Gate exits 0 on the exact package and exits non-zero when a secret or new finding is planted.
  - Evidence so far: PASS on the exact 124-file package (SHA 85a82212...): lint PASS, compatibility 0 findings, secret scan PASS, Semgrep 7 findings and WPCS 12 + 17 escape-output items, each reviewed and recorded with its reason in 08_CAP_EXECUTION/accepted_findings.json. A planted secret fails the gate. CI job added.
  - Status: PASS
- [ ] **CAP-10 — Role and location matrix** (Critical)
  - Goal: Exercise every read, write, approve, export and download path for Owner, Management, Finance, HR, warehouse and branch roles, allow and deny.
  - Acceptance: A generated matrix shows the expected result for every role-route-location cell.
  - Evidence so far: Role sync and per-role smoke exist. Full matrix generator to build from the route list (about 180 routes).
  - Status: NEXT
- [ ] **CAP-11 — Private HR vault on the Owner host** (High)
  - Goal: Verify vault directory, keyring, encryption, download, key rotation and restore on the real host.
  - Acceptance: Rotation and restore succeed and the vault path is outside the web root.
  - Evidence so far: AES-256-GCM implementation reviewed (authenticated decrypt, SHA-256 compare, 0600 files). Host verification needs access to the Owner host.
  - Status: OWNER INPUT: host access
- [ ] **CAP-12 — Data integrity expansion** (High)
  - Goal: Check references, duplicate IDs, ledgers, stock balances and payroll/GL consistency on protected copies.
  - Acceptance: Zero orphan references and zero ledger differences.
  - Evidence so far: Deep scan 52/52 and snapshot comparison (no row loss) pass in CI.
  - Status: IN PROGRESS
- [ ] **CAP-13 — Runtime matrix** (Medium)
  - Goal: Run the exact package on PHP 8.3 and PHP 8.4 with MariaDB and MySQL 8.
  - Acceptance: Install, E2E and gates pass on all four combinations.
  - Evidence so far: PHP 8.3 runtime PASS. PHP 8.3-8.4 compatibility scan 0 findings. PHP 8.4 runtime job added to CI.
  - Status: IN PROGRESS
- [ ] **CAP-14 — Concurrency and load** (Medium)
  - Goal: Benchmark simultaneous POS, warehouse posting, imports and reports.
  - Acceptance: Documented throughput and zero lost or duplicated postings.
  - Evidence so far: To build.
  - Status: NEXT
- [ ] **CAP-15 — Frontend and PWA acceptance** (Medium)
  - Goal: Cover viewports, keyboard, accessibility, print/export, update/offline and host-theme behaviour.
  - Acceptance: Smoke and accessibility checks pass at phone, tablet and desktop widths.
  - Evidence so far: Smoke at 390 px and desktop passes; Management Intelligence refresh, social panel and server-set theme asserted in CI.
  - Status: IN PROGRESS
- [ ] **CAP-16 — SOP governance** (High)
  - Goal: Record owner, approver, language, version and effective date for each of the 24 embedded SOPs.
  - Acceptance: Each SOP carries all five fields; Owner approval moves its state to APPROVED.
  - Evidence so far: Payload integrity PASS; the current state value is DRAFT_NOT_APPROVED until the Owner approves.
  - Status: NEXT; OWNER INPUT: approvals
- [ ] **CAP-17 — Idempotent replay behaviour** (Medium)
  - Goal: Return one consistent response for a legitimate replay and a distinct one for a state-transition conflict, across modules.
  - Acceptance: Regression covers both paths for every module.
  - Evidence so far: Duplicate customer code returns 409 (baseline returned 500); further modules to cover.
  - Status: IN PROGRESS
- [ ] **CAP-18 — Operational parity review** (High)
  - Goal: Compare the installed site (source, schema, roles, branches, opening balances, backups, vault) with the verified package.
  - Acceptance: Zero unexplained differences.
  - Evidence so far: Needs authorized read access to the live site, which was unreachable from this environment.
  - Status: OWNER INPUT: live-site access
