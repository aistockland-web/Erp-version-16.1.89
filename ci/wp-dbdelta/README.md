# Stockland WordPress + dbDelta integration lab

This is an isolated runtime test bench, not a release workflow or production deploy.
It provisions actual WordPress core, the real WordPress REST server, PHP, and MySQL/MariaDB.

## Exact installers

| Role | Actions run | Installer SHA-256 |
|---|---:|---|
| Protected master 16.1.95 | 40036018031 | 494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625 |
| Selected 16.2.9 PR #39 artifact | 41146685514 | c9f6459c71087575d8554e599a1ed5025a9e21655f24886c933232ac194d9c01 |
| Refreshed 16.2.9 artifact | 42125671298 | f360294feb4aa43f13d43d12a22e55867eda26e0502946dd0d494947277604ca |

The candidates must have byte-identical PHP runtime files before their migration behavior
is considered equivalent.

## Runtime scenarios

1. Fresh WordPress and plugin activation in disposable databases.
2. Real REST access checks on the protected setup route, including anonymous denial.
3. Bounded setup batches with physical table verification after each batch.
4. Protected baseline schema: 29 Core + 16 Accounting/Procurement + 4 HR/Payroll = 49.
5. Actual WordPress dbDelta() create, add-column, repeat-run and existing-row preservation.
6. Re-entry through baseline Core/Accounting dbDelta() repair paths and HR schema repair.
7. Synthetic-only 16.1.95 to 16.2.9 update with legacy HR/payroll rows and all 49 baseline tables preserved.
8. Independent fresh 16.2.9 installation.
9. Reproduction of the migration default difference: existing compensation rows receive APPROVED without an approval event, while fresh-install DDL says DRAFT. This is a release blocker.
10. Verification that pension-rate columns already exist in protected 16.1.95.

The tests use only synthetic IDs and values. They do not access production, real employee data,
sales, stock movements, GL journals, or posted payroll.

## Open in Codespaces

Open branch qa/wordpress-dbdelta-runtime-2026-11-07 as a Codespace.
The .devcontainer starts WordPress on forwarded port 8080 and MariaDB 10.11.
After bootstrap, run:

    bash ci/wp-dbdelta/run-codespaces.sh

Authenticate GitHub CLI first so it can download exact historical Actions artifacts by run ID.
Candidate update tests use separate disposable WordPress directories and databases. The
browser-facing site is left on protected 16.1.95.

## CI matrix

The workflow runs PHP 8.3/8.4, WordPress 6.8.0/latest, and MariaDB 10.11/MySQL 8.0.
The uploaded test evidence is short-lived and is not a release artifact.

A passing characterization test does not approve the 86-table schema, HR model, approval
default conflict, or production deployment.
