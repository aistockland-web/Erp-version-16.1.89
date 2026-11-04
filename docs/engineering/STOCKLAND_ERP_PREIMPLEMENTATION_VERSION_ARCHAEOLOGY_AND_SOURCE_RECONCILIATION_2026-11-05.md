# Stockland ERP — Pre-Implementation Version Archaeology and Source Reconciliation

**Owner direction:** 5 November 2026  
**Repository:** `aistockland-web/Erp-version-16.1.89`  
**Working branch:** `owner/incremental-target-commits-production-2026-11-05`  
**Related delivery rule:** `STOCKLAND_ERP_INCREMENTAL_TARGET_COMMITS_AND_PRODUCTION_COMPLETION_2026-11-05.md`

## Owner clarification — do not start by editing code

The project has been developed over approximately three months. The repository's `main` history contains more than twenty versioned releases/iterations, and the Owner confirms that there are two plugin/version series, as well as a local package that the Owner intends to upload.

The historical implementation, decisions, fixes, UI work, payroll work, CI work, migrations, tests, release builders and other best practices from that period are part of the project. The next engineering environment must not treat the latest visible version as the whole project, discard earlier work, or start a fresh implementation from a partial snapshot.

**Before modifying application code in Codespaces or Visual Studio Code, perform source archaeology and reconcile all supplied source lines.**

## 1. Required source sets

Treat these as distinct source inputs until their relationship is proven:

1. **The complete relevant `main` history**, including the more than twenty versioned iterations and the commits that contain reusable fixes, tests, release-builder work and design decisions.
2. **The second plugin/version series** identified by the Owner. Its exact version range and source refs must be inventoried rather than guessed.
3. **The local plugin/package** the Owner will upload. Preserve its original filename, archive structure and checksum; do not silently replace or repackage it before recording the original.
4. **The named milestone artifacts and branches**: 16.1.95 protected operational master/rollback reference; 16.1.98 side line; 16.2.9 selected HR/payroll source from merged PR #39; 16.2.13 reported installed version whose exact package/hash must be recovered from the Owner's supplied source; 16.3.0 HRX candidate; 16.3.1 conditional local verification record; 16.1.101 separate staging candidate; and 16.2.10 referenced Library source whose package was not accessible during the prior check.

The milestone list is a minimum index, not a claim that these are the only versions. Do not collapse the two series into one presumed linear sequence. Do not assume every commit on `main` is a complete installer, or that every version-numbered ZIP is cumulative.

## 2. Mandatory inventory before code changes

Create a version/source register containing, for every located source set:

- series, version/label, branch/tag/ref and exact commit SHA where applicable;
- original package filename, byte size, SHA-256, archive root and file inventory where a ZIP is supplied;
- manifest/build metadata and runtime/schema/migration contract;
- tests, workflows, CI run IDs, artifacts and the exact commit/package they validate;
- implemented capabilities and distinctive best practices worth retaining;
- known regressions, removals, incomplete features, conflicting implementations and open findings;
- parent/base lineage and evidence for whether the source is cumulative, a side line, a selective feature source or an isolated candidate.

Use repository history and PRs to recover intent and provenance. Compare source files and generated installer contents, not just version labels or commit messages. Compare UI/CSS/JS, POS/cart, HR/payroll, finance, inventory, migrations, security, SOP resources, build scripts and tests. Identify additions, removals and behavioral changes between each relevant lineage.

If a source cannot be obtained, record it as unavailable and request the exact package or ref from the Owner. Do not invent its contents or claim it has been reconciled.

## 3. Preserve best practices and project continuity

Build a capability/evidence matrix that maps every accepted requirement and reusable implementation to its source commit/package and intended destination in the cumulative product.

Retain sound work even when it exists in an older version. A newer version label does not automatically make every older implementation obsolete. Conversely, do not copy a whole side-line package over the protected master merely because it contains a desired feature.

For conflicting implementations, compare behavior, tests, data contracts, migration safety, security, accessibility, UI quality and operational fit. Select or merge the strongest proven implementation, document the decision and add regression coverage. Preserve the working POS/cart, product master, inventory/warehouse, financial/ledger, role/access, HR/payroll, UI, CSS protections, SOP and installer work already established.

In particular, distinguish:
- a rollback reference from the actual currently installed source;
- an implementation source from a validated installable package;
- a successful workflow run on an earlier SHA from evidence for the current SHA;
- a selective feature integration from a cumulative replacement;
- a local package receipt from an independently re-hashed and reproducible artifact.

## 4. No destructive reset or premature implementation

Until source inventory and lineage reconciliation are complete:

- do not reset, replace, delete, flatten, squash or overwrite existing version history;
- do not remove existing modules, CSS, JS, migrations, tests, SOPs or build resources to simplify the project;
- do not declare one branch or version to be the sole source of truth without evidence and Owner decision;
- do not begin a clean-room rewrite or generate another arbitrary version series;
- do not alter `main`, the protected operational master or live installation;
- do not treat this directive as permission to merge or deploy.

After the source register and reconciliation matrix are committed, proceed with the affirmative full-scope engineering mandate. Integrate the selected best implementations into one cumulative, maintainable product, preserving history and using focused target commits. Each completed target must be committed immediately, tested against its exact SHA, corrected and retested as specified in the incremental-commit directive.

## 5. Owner-provided local package intake

When the Owner uploads the local package:

1. Record the untouched original name, size and SHA-256 before extraction.
2. Inspect archive contents safely; reject path traversal, unexpected executable payloads and duplicate/conflicting plugin roots.
3. Compare it with both repository series and the milestone packages.
4. Identify whether it contains unique fixes, later work, local-only changes, generated assets, environment-specific configuration or data.
5. Map every worthwhile change to its origin and target; preserve the original as evidence.
6. Do not overwrite a repository source or label the local package canonical until its lineage, contents and compatibility have been reviewed.

The upload is an additional source for reconciliation, not an instruction to discard the GitHub history.

## 6. Deliverable before implementation

Commit a concise **Source Archaeology and Reconciliation Report** to the working branch. It must include:

- the full version/branch/package inventory found;
- both series separately identified;
- the Owner-uploaded local package and its verified hash once received;
- the capability/best-practice matrix;
- a source-lineage graph;
- a file-level comparison for significant divergent modules;
- the selected cumulative base and rationale;
- retained, merged, superseded and unresolved work;
- the implementation sequence with target commits and tests.

Only after this report is grounded in the available source evidence should implementation proceed in Codespaces or Visual Studio Code. If the report reveals missing source, ask for that source instead of guessing.

**Controlling instruction:** the full three-month project history is the engineering asset. Recover it, compare both plugin series and the Owner's local package, preserve the best work, then continue implementation with incremental commits toward one complete production-ready Stockland ERP.
