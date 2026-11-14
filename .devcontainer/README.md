# Stockland Ethiopia ERP — Codespaces environment

This is the real Stockland ERP engineering workspace, not a sample application. It keeps the existing WordPress plugin and its repository-owned workflows. The .NET Codespaces repository supplied as a reference is not copied into this project.

## Create the Codespace

Open the repository's Codespaces creation page and choose branch:

https://github.com/codespaces/new?repo=aistockland-web%2FErp-version-16.1.89&ref=delivery%2Fstockland-full-erp-completion-2026-11-11

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


## Final full-ERP delivery path

The Owner's single objective and copy-ready agent prompt are in [docs/AGENT_FIRST_PROMPT.md](../docs/AGENT_FIRST_PROMPT.md). The complete Codespaces, full business acceptance and one-click installer runbook is [here](../docs/engineering/STOCKLAND_ERP_FINAL_CODESPACES_ONE_CLICK_RELEASE_RUNBOOK_2026-11-11.md). Read the repository-root AGENTS.md before implementation.

The canonical editable runtime source is `src/stockland-erp-app/`, not the disposable WordPress site or an extracted /tmp candidate. On first Codespaces post-create the environment verifies the exact .13/.15 original ZIP identities, creates or reuses a distinct repaired .15 QA candidate, verifies the .13 per-file manifest, and materializes the real .13 source plus `src/.stockland-erp-app.source.json`. A marked source tree is preserved on rebuild; an unmarked existing folder is never overwritten.

Check source intake:

```bash
test -f src/.stockland-erp-app.source.json
test -f src/stockland-erp-app/stockland-inventory.php
python3 scripts/codespaces/test_project_source.py
git status --short --branch
```

The repaired .15 QA artifact is distinct from the original candidate ZIP. Do not rename its digest to the original digest. The first post-create uses the repaired artifact for disposable WordPress activation/update; the original .13 archive remains the operational baseline.

After the smoke tests, the next target is the resumable ERP setup wizard and physical schema/data verification on MariaDB 10.11 and MySQL 8.0.46, followed by full authenticated module workflows and full-cycle HR/payroll. A 12-table WordPress-only receipt is not full ERP schema acceptance. Build the final installable WordPress ZIP only from the final reviewed canonical source commit, with release manifest, file hashes, SBOM, build receipt and exact SHA-256. The standard WordPress plugin-upload flow is the final installer interface; production publication/deployment follows final Owner release authorization.

## 16.2.18 master installer verification

`bash .devcontainer/verify-master-installer.sh [mariadb|mysql8|all]` builds `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` from `src/stockland-erp-app/`, resets the disposable WordPress databases, installs the ZIP through WordPress, activates once, verifies all 86 tables, setup state and the business-workflow regression scripts. Use the MySQL 8 profile (`docker compose --profile mysql8 up -d`) for the `mysql8` target. Synthetic data only; the script refuses to run without `STOCKLAND_DISPOSABLE_SITE=yes`.

## Exact approved 16.2.18 artifact — no rebuild

The Owner-designated 16.2.18 production installer is the existing GitHub Actions artifact [run 43570216177 / artifact 14104031868](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177). The canonical inner installer SHA-256 is `de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08`.

Do not run `.devcontainer/verify-master-installer.sh` for this exact-artifact check: that older helper rebuilds the installer from source. Use the new helper below; it downloads the pinned GitHub Actions artifact and verifies the exact approved ZIP without rebuilding or changing its bytes.

```bash
bash .devcontainer/initialize-host.sh
docker compose --project-directory .devcontainer -f .devcontainer/docker-compose.yml --profile mysql8 up -d --build
bash scripts/codespaces/verify_approved_16_2_18.sh all
```

The helper validates both the outer Actions artifact digest and the inner installer SHA, its 16.2.18 manifest, 124 file hashes, ZIP paths and CRC. It then installs that exact ZIP in the disposable MariaDB 10.11 and MySQL 8.0.46 WordPress Compose sites and runs the existing synthetic ERP workflow suites. It does not connect to the live company database, rebuild the plugin, or alter the original approved package.

## Cloudflare Free plan

See [Cloudflare Free-plan architecture and account inventory](CLOUDFLARE_FREE_PLAN.md). The connected account currently has one asset-only Worker named `erp`, with no bindings, custom domains, DNS zones, Pages projects, D1, KV, Queues or Durable Objects. R2 is not enabled yet. A production domain/zone must be added and delegated to this account before DNS proxy, zone-level WAF, cache or SSL settings can be configured for the live WordPress host. Keep WordPress/PHP/MySQL as the ERP transaction system of record; do not replace it with D1 or a Worker.