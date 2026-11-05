# Stockland ERP — Agent Autonomy, CI/CLI Repair and Build-Focused Execution Addendum

**Owner direction:** 5 November 2026  
**Repository:** aistockland-web/Erp-version-16.1.89  
**Working branch:** owner/incremental-target-commits-production-2026-11-05  
**Related directives:** Incremental Target Commits; Pre-Implementation Version Archaeology; Codespaces Master Handover.

## 1. Work independently and focus on completing the ERP

The agent is expected to act as the engineering implementer for the agreed Stockland ERP project—not merely as an auditor, planner or prompt writer.

After a bounded, evidence-based inventory of the available versions and source lines, proceed with implementation. Do not let repeated audits, status summaries or planning documents replace actual engineering progress. Convert findings into code changes, tests, repaired workflows, integration commits and reproducible build outputs.

Use engineering judgment and available free/open-source tools to make routine decisions. Do not ask the Owner to approve every ordinary code correction, test addition, CSS repair, build-script improvement, dependency pin, CI workflow correction or CLI usability fix. Record the rationale, implement the safe correction, test it, commit it and report it.

Ask the Owner only when a genuinely owner-controlled input or decision is needed: unavailable source package or business resource; unresolved business-policy conflict; legal/statutory payroll interpretation; a paid service or cost; destructive or irreversible data decision; production credential; or actual live deployment/release authorization.

## 2. CI is part of the product engineering work — inspect, modify and fix it

Treat the entire Continuous Integration and Continuous Delivery configuration as first-class project source. Inspect and repair as necessary:

- GitHub Actions workflow definitions, triggers, branch/PR filters, job dependencies, permissions, concurrency groups and cancellation behavior.
- PHP/runtime matrix, WordPress test harness, MariaDB/MySQL services, service health checks, PHP extensions and environment setup.
- Composer/npm/Python dependency installation, lockfiles, version pinning, caching and reproducible build configuration.
- PHP lint, PHPCS/WPCS, PHPStan where useful, PHPUnit, integration and migration tests.
- JavaScript lint/tests/build, CSS/PostCSS pipeline, module order, responsive CSS verification and compiled/source parity.
- Semgrep, Gitleaks, dependency/license/security checks and artifact scanning.
- ZIP packaging, plugin-root/path checks, manifest/file inventory, source commit identity, build receipts and SHA-256 verification.
- Workflow artifact upload/retention, test reports, failure logs, PR check summaries and release artifact provenance.
- Exact-commit and exact-artifact association: each result must identify the SHA it actually tested.
- Test sharding/parallelism and resource usage where this improves reliability without hiding failures.

When CI fails, read the full logs, determine the actual root cause, fix the relevant source/workflow/environment/test and rerun. Do not make CI green by disabling required jobs, weakening assertions, excluding relevant directories, skipping tests, downgrading scanners or accepting stale artifacts. Remove flaky behavior through deterministic fixtures, health checks, isolated databases, stable waits and repeat-safe tests.

CI should continuously validate every coherent target commit, run cumulative regression on the integration branch and build the exact final installer from the exact final source commit.

## 3. CLI and command-line developer experience are also in scope

Inspect, improve and repair the command-line tools used to build, test, install, diagnose, migrate, package and validate the Stockland ERP. This includes:

- WP-CLI commands and plugin setup/health/diagnostic helpers where appropriate.
- Project CLI wrappers, shell scripts, Makefile/Taskfile commands, Composer scripts, npm scripts and Python build/receipt utilities.
- One-command local setup for the supported WordPress/PHP/MariaDB test environment.
- Safe database export/import/backup/restore helpers for isolated test databases.
- Deterministic install, upgrade, schema verification, seed-fixture, regression, packaging and checksum commands.
- CI-equivalent commands that developers can run locally and that return meaningful exit codes.
- Clear help, required/optional arguments, validation, safe defaults, logging, error messages and non-zero exit codes on failure.
- Environment checks that detect missing PHP extensions, incompatible PHP/Node/Python/database versions, missing credentials and unsafe target environments before running.
- Guards that refuse destructive actions against a production URL/database unless the command is explicitly designed for a separately approved production operation.
- Avoid hardcoded production hostnames, credentials, user data or developer-specific paths.

Do not create a second competing CLI framework merely for appearance. Reuse existing conventions and dependencies where sound; consolidate duplicates only after proving compatibility with current scripts and CI. Preserve backward compatibility or provide a documented migration for any changed command.

## 4. Build-system autonomy and source/resource maintenance

The agent may independently edit and repair source files, tests, build scripts, CI configuration, CLI commands, styles, documentation, manifests, migration helpers and resource-packaging logic when required to complete the approved project.

Treat CSS, JavaScript, icons, templates, policy/SOP documents, translations, SQL migrations, Composer/npm configuration, generated assets and packaging receipts as part of the product—not incidental extras. If a resource is missing, search the repository, branches, Library inputs and artifacts; do not fabricate an official Stockland SOP, contract, legal rule or employee record.

For every change:
1. Link it to an existing requirement, defect, audit finding or clear maintainability/reliability improvement.
2. Preserve the stronger prior implementation and verify behavior before replacing it.
3. Add or update automated tests and developer/operational documentation as appropriate.
4. Run the directly affected checks and cumulative regression.
5. Commit the coherent target and any necessary fix as separate, understandable commits.
6. Record exact source SHA, test commands/results and generated artifact identities.

## 5. Keep the full product as the main priority

Do not spend the entire available engineering effort polishing the CI dashboard while leaving core business workflows unfinished. CI and CLI fixes should make implementation and release safer and faster, but the primary work remains the full ERP scope in the master handover: Product Master, POS/cart, retail and wholesale, Central Warehouse and branches, inventory ledger, receiving, requisitions, reservations, transfers, returns, counts, finance, procurement, AP/AR, GL/PLEDGER, HR, payroll, SOP/resource management, reports, responsive SaaS interface and PWA.

Use a practical sequence:
- repair blockers in the development/build pipeline that prevent reliable work;
- implement a complete business target;
- commit and test it;
- repair any pipeline or CLI issue exposed by the target;
- continue to the next target;
- run full cumulative and final-package gates.

Do not postpone business features indefinitely in pursuit of perfect tooling. Do not bypass engineering controls to make features appear complete.

## 6. Boundaries that remain Owner-controlled

The agent has broad autonomy over reversible engineering work in branches and isolated environments. It may create commits, push working branches, open/update PRs, edit application code, adjust CI/CLI/build resources, add test fixtures and repair failures.

The agent must not:
- reset, force-push or overwrite protected history;
- silently replace the canonical source or the Owner's installed plugin;
- run synthetic transactions, destructive migrations, load/failure tests or test payroll against the live production site/database;
- expose production data, credentials or private HR documents in test systems or artifacts;
- incur paid resource or subscription costs without approval;
- invent payroll/tax/pension/legal rules or official SOP content;
- merge a release or deploy/install into the live site without the Owner's explicit release authorization.

## 7. Expected execution behavior and reporting

Work proactively through the available repository and tool access. Do not stop at a plan if code or workflow changes can safely be made. Do not claim to have edited a local Codespace, run a command, fixed a CLI tool, or passed a CI job unless that action actually occurred in the relevant environment.

Keep progress reports short and actionable:
- what target was implemented;
- commit SHA and PR;
- the exact CI/CLI/build commands run;
- results tied to the exact SHA;
- findings fixed and follow-up commit;
- remaining owner inputs or owner-controlled decision.

The final outcome remains one complete, integrated Stockland ERP WordPress plugin and a reproducible production release package with exact source/artifact identity and complete acceptance evidence.

**Owner's practical instruction:** Build the project yourself. Improve and repair the code, CI, CLI, scripts, resources and build pipeline whenever needed. Commit every coherent finished target, fix what the tests reveal, and continue until the complete product is ready for the Owner's release decision.
