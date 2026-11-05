# Stockland ERP — Three-Month History Reconciliation and Pre-Codespace Handover

**Owner direction:** 5 November 2026  
**Historical window to preserve:** 5 August–5 November 2026  
**Canonical repository:** `aistockland-web/Erp-version-16.1.89`  
**Default branch inspected:** `main`  
**Main SHA inspected:** `a46214f6bd5e21a9948d5c896d2136d722d36f16`  
**Working review branch:** `owner/incremental-target-commits-production-2026-11-05`

## 1. Owner clarification

The Codespaces and Visual Studio Code work must inherit the actual Stockland ERP project accumulated over the preceding three months. This is not a fresh build, a restart from a single version, or a request for the Owner to re-explain or manually edit the source.

The repository must preserve and expose the work already completed: implementation, design, engineering decisions, test frameworks, release/build tooling, corrections, business rules, operational workflows, SOPs, audit history, and useful practices from every version and workstream. The strongest compatible work is to be consolidated into one cumulative product, with source provenance retained.

**Do not begin new application-code editing until the historical source inventory and integration decisions have been recorded.** Reading and auditing source, preparing the history register, reproducing builds and running isolated checks are part of that preparation.

## 2. What the current canonical repository inspection establishes

The GitHub API inspection on 5 November 2026 established the following facts for the canonical repository:

| Repository evidence | Observed result |
|---|---|
| Default branch | `main` |
| Main head | `a46214f6bd5e21a9948d5c896d2136d722d36f16` |
| Reachable commit-history pages inspected | 200 commits |
| Earliest commit returned in those pages | 26 October 2026 |
| Latest commit returned | 3 November 2026 |
| Further main commit page | Empty |
| Branches listed | 68 |
| Pull requests returned by the all-state listing | 50 |
| Root-level installer ZIPs present on main | 16.1.89, 16.1.90, 16.1.91, 16.1.92 |
| Releases API listing | No published GitHub Releases returned |

**Important:** this repository snapshot does not, by itself, prove that every item from 5 August through 25 October is present as a commit on `main`. It proves a substantial committed engineering history from 26 October to 3 November, plus a wider set of branches, pull requests, version records, source overlays, build scripts, audit documents and test workflows. Do not describe the 200 returned main commits as a complete three-month record.

The history reconciliation must therefore inspect all available sources: all repository refs and PRs, Notion project decisions, prior committed documents, retained Library files and available project handover records. Any historical item not found in those sources must be identified as an evidence gap rather than invented or silently omitted.

## 3. Source inventory already visible in the repository

### Main branch and versioned evidence

The main tree contains installer archives for 16.1.89, 16.1.90, 16.1.91 and 16.1.92, together with release checksums/QA notes where available, the 16.1.93 and 16.1.95 acceptance/CAP records, release provenance, the consolidated system audit, build scripts, CI tests, and the HR replacement design/control records.

The main commit history also records successive 16.2.x work, including the schema-contract fixes, responsive HR workspace, icon and CSS hardening, POS cart work, private HR resource workflows, package/build receipts and regression tests. These commits are real historical implementation evidence; the version label alone is not proof that a standalone installable ZIP was published.

### Branch and PR history

The repository listed 68 branches. The branch inventory includes distinct workstreams for:
- reports/analytics selector and print/export;
- 16.1.98 source and CI work;
- HR/payroll domain, full-cycle and replacement designs;
- recruitment/workforce/people lifecycle, time/leave and private resource flows;
- HRX/16.3.0 integration;
- 16.1.101 SOP/HRX staging candidate;
- CI, CSS, schema, audit, source-export and release tooling.

The all-state pull request listing returned 50 PRs, with a mixture of merged and closed/unmerged branches. Examples include merged HR implementation/design work (#39, #40, #43–#45), and candidate/documentation work that was not merged (#48, #49, #54). PR #55 is the current open Owner-directive PR. Closed or unmerged does not mean that every file in a branch is useless; it means the branch's code must be reviewed and selected deliberately, not presumed integrated.

## 4. Best practices already evidenced in committed work

These are reusable engineering assets and practices to preserve, reconcile and apply where relevant—not blanket approval of every candidate implementation.

1. **Reproducible release provenance:** fixed baseline inputs, exact source commit, locked package inputs, generated file inventory, manifest, independent ZIP SHA-256 and exact-head CI.
2. **Exact-package validation:** test the archive that will be delivered, not only a working directory; compare package file inventory and scan coverage to the archive; preserve workflow/run/artifact identifiers.
3. **Layered security and source quality:** PHP 8.3/8.4 syntax, PHPCS/WPCS, Semgrep, dependency/secret coverage and security checks that inspect the actual extracted package. Do not globally suppress findings to obtain a green job.
4. **Frontend integrity:** pinned local Bootstrap 5.3.8, ordered Stockland CSS modules, PostCSS/source-parity checks, semantics-safe minification, responsive layout contracts and explicit SVG sizing/stroke isolation from theme CSS.
5. **POS correctness:** atomic cart handling, server-authoritative price/stock/totals, validation of edited quantities, idempotent retry identity, safe checkout payloads, authorized branch restore and regression coverage.
6. **Inventory and finance integrity:** fail-closed availability, no unsupported quantity acceptance, transactional writes, stable retry/replay controls, exact money arithmetic fixtures, balanced journals and shared accounting services.
7. **Owner authority and maker-checker:** separate maker and approver, prevent self-approval, use row locks/state checks, enforce server capabilities and location scope, and preserve auditable decisions.
8. **HR/payroll privacy and workflow:** private role-scoped documents, versioned policy/resource records, controlled download audit, effective-dated records, explicit review/approval transitions, and deterministic calculations with legal/statutory inputs verified independently.
9. **Schema stewardship:** preserve the 49-table baseline and legacy HR history until dependency traces, data health, additive migration and rollback are explicitly reviewed. A proposed HRX schema is not automatically approved because it exists in a branch.
10. **Operational evidence:** distinguish static checks, synthetic isolated tests, authenticated WordPress/MariaDB end-to-end tests, deployed-source parity, backups/restores and Owner/Finance/HR/legal acceptance.

Relevant committed reference records include:
- `docs/engineering/STOCKLAND_SAAS_ENGINEERING_BLUEPRINT_2026-10-29.md`
- `docs/engineering/STOCKLAND_16.2.7_REMEDIATION_COMMIT_PLAN_2026-10-30.md`
- `STOCKLAND_ERP_16.1.95_FULL_AUDIT_AND_RELEASE_DECISION_2026-10-24.md`
- `STOCKLAND_ERP_MASTER_END_TO_END_ACCEPTANCE_16.1.95.md`
- `STOCKLAND_ERP_CONSOLIDATED_FULL_SYSTEM_AUDIT_AND_ACCEPTANCE.md` (historical snapshot; do not take old status statements as current)
- `docs/hr-replacement/HR_REPLACEMENT_BASELINE_AND_DESIGN_GATES_v1.md`
- `docs/hr-replacement/HR_ROUTE_AUTHORIZATION_AND_CONTROL_TRACE_v1.md`
- the CI/build and QA assets under `.github/workflows/` and `ci/`.

## 5. Mandatory pre-edit history reconciliation

The Codespaces/VS Code engineer or agent must first produce a committed History and Integration Register. At minimum, it must include one row per relevant version, branch, PR, release artifact and major decision, with these fields:

| Field | Required content |
|---|---|
| Source identity | Version, branch, PR, commit SHA, file/artifact path and originating record |
| Scope | Features, modules, workflows, UI/design and infrastructure covered |
| Implementation evidence | Exact source paths and diff/merge-base relationship to main |
| Best practice to preserve | Specific design, business rule, service, test, build or security improvement |
| Test evidence | Workflow/run ID, exact tested commit and exact package SHA; or explicitly recorded absence |
| Outcome | Merged, unmerged, closed, reverted, superseded, candidate, archived or still active |
| Decision | Retain, port selectively, reconcile, replace, archive or exclude |
| Reason | Compatibility, duplicate, regression, rejected design, data/schema risk, missing evidence or approved choice |
| Integration result | Target commit/PR and evidence after adoption into the cumulative product |

Required repository review steps:

1. Fetch all remote refs and inspect the full graph, not only `main`: branches, tags/refs, commit ancestry, PRs, merge commits, reverts and archived artifacts.
2. For each relevant branch, inspect its merge base, commits and actual changed files against the correct base. Do not infer code content from a branch name or PR title.
3. Read PR discussions, review threads, CI job outcomes and artifact receipts. A green run applies only to the exact commit and exact package tested.
4. Compare versioned ZIPs and generated packages by their actual contents and SHA-256. Identify duplicate packages, divergent branches, missing artifacts and non-cumulative builders.
5. Reconcile the Git history with Notion decisions, Library source files, earlier project plans and available Owner handovers for the full 5 August–5 November window.
6. Record both what is already present in main and what exists only on another branch or external project record. Never silently treat an unmerged branch as part of main.
7. Preserve every useful feature and practice through a reviewed port or merge commit on a cumulative integration branch. Do not wholesale merge all candidate branches or copy old ZIPs over the current application.
8. Commit the register and integration plan before beginning unrelated application-code changes. Then proceed with the implementation target-by-target under the Incremental Target Commits directive.

## 6. Required Codespaces/Visual Studio Code handover behavior

The Owner should not need to reconstruct three months of context, choose code files manually or rewrite the source. The engineer must use this repository record plus its verified supporting sources to understand the project before editing.

The engineer must not:
- restart from 16.1.95 or any other single release as if later work does not exist;
- assume the latest version number is the best complete source;
- merge every branch or overlay every ZIP wholesale;
- discard older code, tests, SOPs or decisions merely because a later candidate exists;
- claim the full three-month history has been preserved until the register proves it;
- begin a large redesign before mapping existing implementation, best practices, regressions and dependencies.

The engineer must:
- preserve the exact current `main` head and all existing refs;
- prepare the complete history and integration register;
- select the best compatible work from all relevant versions and branches;
- preserve Owner authority, business rules, ledgers, history, data, branch scope, SOPs, accepted design and working workflows;
- commit each coherent implementation target as collected, test it in isolated environments, fix findings, and continue;
- deliver one cumulative production-ready WordPress plugin with exact source/package provenance.

## 7. Current conclusion

The canonical repository contains substantial committed work and a rich branch/PR history. It is not yet demonstrated by the inspected `main` history alone that every project activity from the full three-month period is preserved in commits. The correct next step before Codespaces/Visual Studio implementation is therefore a complete history reconciliation and preservation register, followed by selective integration of the best existing work—not another restart or a fresh version-number cycle.

No application code or production data is changed by this document. Its purpose is to ensure that the next engineering environment receives the real project history and continues the work without losing the previous three months.
