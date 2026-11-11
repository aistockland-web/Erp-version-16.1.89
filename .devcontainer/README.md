# Stockland Ethiopia ERP — Codespaces environment

This is the real Stockland ERP engineering workspace, not a sample application. It keeps the existing WordPress plugin and its repository-owned workflows. The .NET Codespaces repository supplied as a reference is not copied into this project.

## Create the Codespace

Open the repository's Codespaces creation page and choose branch:

https://github.com/codespaces/new?repo=aistockland-web%2FErp-version-16.1.89&ref=infra%2Fstockland-erp-e2e-codespaces-2026-11-11

The host initialization creates random development-only credentials in the ignored repository-root .env file. They are not committed or printed.

## Included environment

- PHP 8.3 CLI with WordPress database/XML/ZIP extensions; Node.js 22; Python 3.12; Composer; GitHub CLI; Docker CLI support; WP-CLI checksum verification.
- MariaDB 10.11 with two isolated WordPress sites and separate databases.
- Baseline site on private port 8080: installs the exact 16.2.13 operational package.
- Upgrade site on private port 8081: installs 16.2.13 first, then replaces it with the selected 16.2.15 candidate to exercise the upgrade/activation path.
- Optional MySQL 8.0.46 site on private port 8082: installs the 16.2.13 baseline first, then upgrades to 16.2.15 and verifies the synthetic marker and pre-update database snapshot.
- Repository-locked PostCSS and PHPCS/WPCS development dependencies. These are engineering tools, not plugin runtime dependencies.

The database ports are internal to the Compose network. Only the private WordPress preview ports are forwarded.

## First commands

~~~bash
git status --short --branch
git rev-parse HEAD
php -v
node -v
python3 --version
wp --info
python3 ci/codespaces/test_rest_index.py
bash scripts/codespaces/smoke_test_sites.sh
~~~

## MySQL 8.0.46 parity

Run this from the Codespaces terminal:

~~~bash
bash scripts/codespaces/run_mysql8_parity.sh
~~~

It starts the optional MySQL 8 service, creates a disposable WordPress instance, installs the exact 16.2.13 baseline, snapshots the disposable database, upgrades to the exact 16.2.15 candidate, verifies marker preservation, and records a local QA receipt.

## Repository QA commands

~~~bash
python3 ci/codespaces/validate_candidate_packages.py --extract-root /tmp/stockland-candidates
node ci/codespaces/check_web_assets.js /tmp/stockland-candidates/16.2.13/stockland-erp-app
node ci/codespaces/check_web_assets.js /tmp/stockland-candidates/16.2.15/stockland-erp-app
bash scripts/codespaces/smoke_test_sites.sh
~~~

## Safety boundaries

This environment uses isolated synthetic databases and random local credentials. Never import production databases, employee files, real payroll, customer/supplier details, real payments or stock ledgers. Never make the database port public. Never use production credentials. Do not run migrations or test destructive operations against the live Stockland site.

The 16.2.13 archive remains the operational/upgrade baseline. The 16.2.15 archive remains a candidate until exact archive validation, WordPress install/update, database compatibility and all separate release acceptance gates pass. A green Codespaces build is not production approval.
