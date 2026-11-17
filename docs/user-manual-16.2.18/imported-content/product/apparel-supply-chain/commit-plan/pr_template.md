# PR\_TEMPLATE

## Objective

Describe the business capability and customer profile this change supports.

## Exact source base

* Base branch:
* Base commit:
* Proposed branch/head commit:
* Changed paths:
* Source hashes / reference evidence:

## Workflow coverage

* Roles and site scope:
* Document states/transitions:
* Inputs and upstream revisions:
* Outputs and downstream consumers:
* Human approvals / maker-checker:
* Failure/hold/rework/return handling:

## Data and integration

* Existing Product Master identifiers reused:
* New tables/contracts and exact schema fingerprint:
* Stock ledger impact:
* Finance event(s):
* HR event(s):
* Idempotency/concurrency controls:
* Backup/rollback/forward-fix:

## UI/CSS

* Existing Stockland module/component reused:
* CSS files changed (if any) and reason:
* CSS source/build manifest and SHA parity:
* Viewports/accessibility results:

## Tests

* Positive path:
* Permission-deny path:
* Wrong tenant/site:
* Invalid state/stale revision:
* Duplicate/idempotency:
* Concurrency/partial failure:
* Ledger/quantity reconciliation:
* Database engine/runtime matrix:
* Browser/accessibility:
* Backup/restore:

## Evidence and release state

* Exact CI run(s):
* Artifact ID and digest:
* Inner ZIP SHA-256 (if built):
* SBOM/license/dependency report:
* Reviewer approvals:
* Unresolved findings:
* Status: `PASS` / `FAIL` / `BLOCKED` / `NOT RUN` / `OWNER INPUT`.

## Safety declaration

Confirm no live DB/site mutation, no real financial/payroll transaction, no unapproved statutory rule, no unreviewed copied external source/CSS, and no release/deployment unless explicitly authorized.
