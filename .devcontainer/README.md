# Stockland Ethiopia ERP — Codespaces environment

This is the real Stockland ERP engineering workspace, not a sample application. It keeps the existing WordPress plugin and its repository-owned workflows. The .NET Codespaces repository supplied as a reference is not copied into this project.

## Create the Codespace

Open the repository's Codespaces creation page and choose branch:

https://github.com/codespaces/new?repo=aistockland-web%2FErp-version-16.1.89&ref=infra%2Fstockland-codespaces-16218-only-2026-11-14

The host initialization creates random development-only credentials in the ignored repository-root .env file. They are not committed or printed.

## Included environment

- PHP 8.3 CLI with WordPress database/XML/ZIP extensions; Node.js 22; Python 3.12; Composer; GitHub CLI; Docker CLI support; WP-CLI checksum verification.
- MariaDB 10.11 with one default disposable WordPress site; the legacy upgrade site is opt-in only.
- Default MariaDB 10.11 site on private port 8080: installs the exact Owner-approved 16.2.18 artifact when the approved-artifact helper is run.
- Legacy upgrade site on private port 8081: retained in Compose for explicitly requested historical migration research; it is not part of the approved 16.2.18 default test.
- Optional MySQL 8.0.46 site on private port 8082: fresh disposable WordPress target for the exact approved 16.2.18 artifact.
- Repository-locked PostCSS and PHPCS/WPCS development dependencies. These are engineering tools, not plugin runtime dependencies.

The database ports are internal to the Compose network. Only the private WordPress preview ports are forwarded.

## First commands

~~~bash
git status --short --branch
php -v
node -v
python3 --version
wp --info
bash .devcontainer/initialize-host.sh
bash scripts/codespaces/verify_approved_16_2_18.sh all
~~~

## Historical 16.2.13/16.2.15 candidate parity — legacy only

The previous candidate workflow is retained for historical archaeology only. It is not part of the Owner-approved 16.2.18 production environment and must not be used to install, downgrade, replace or promote the ERP. The upgrade-site container is disabled by default; do not enable the `legacy-upgrade` profile for the approved 16.2.18 workflow. For the live-approved version, use `bash scripts/codespaces/verify_approved_16_2_18.sh all`; this validates and installs the exact pinned GitHub Actions artifact without rebuilding it.

## Historical package QA commands (not part of the 16.2.18 default release path)

~~~bash
python3 ci/codespaces/validate_candidate_packages.py --extract-root /tmp/stockland-candidates
node ci/codespaces/check_web_assets.js /tmp/stockland-candidates/16.2.13/stockland-erp-app
node ci/codespaces/check_web_assets.js /tmp/stockland-candidates/16.2.15/stockland-erp-app
bash scripts/codespaces/smoke_test_sites.sh
~~~

## Safety boundaries

This environment uses isolated synthetic databases and random local credentials. Never import production databases, employee files, real payroll, customer/supplier details, real payments or stock ledgers. Never make the database port public. Never use production credentials. Do not run migrations or test destructive operations against the live Stockland site.

The current selected ERP release is the Owner-approved 16.2.18 package identified in the section below. Historical 16.2.13/16.2.15 archives remain reference material only; they are not the production installer or a downgrade path. All tests in this profile use synthetic disposable data and never access the live company database.


## Final full-ERP delivery path

The Owner's single objective and copy-ready agent prompt are in [docs/AGENT_FIRST_PROMPT.md](../docs/AGENT_FIRST_PROMPT.md). The complete Codespaces, full business acceptance and one-click installer runbook is [here](../docs/engineering/STOCKLAND_ERP_FINAL_CODESPACES_ONE_CLICK_RELEASE_RUNBOOK_2026-11-11.md). Read the repository-root AGENTS.md before implementation.

## Current Codespaces default — approved 16.2.18 only

The current Dev Container sets `STOCKLAND_DEV_RELEASE_MODE=approved-16.2.18`. On first creation, `post-create.sh` installs the engineering toolchain (PHP 8.3, Node 22, Python 3.12, Composer, WP-CLI with checksum verification, locked CSS/PHPCS dependencies) and then **skips historical 16.2.13/16.2.15 candidate intake, repair and activation**. It does not rebuild or repackage the approved ERP plugin.

Use the original approved Actions artifact verification command below for disposable MariaDB 10.11 and MySQL 8.0.46 WordPress installs. The older candidate-source and .13/.15 routines remain in the repository for historical/explicit legacy testing only; they are not the default Codespaces startup workflow.

The canonical editable application source remains under `src/stockland-erp-app/` for repository maintenance, but the **approved 16.2.18 Codespaces startup does not materialize or activate historical .13/.15 candidates**. It prepares the engineering toolchain and exits cleanly. The exact approved production package is fetched from GitHub Actions only when the dedicated verifier is run. Legacy source-intake and .13/.15 candidate commands remain in the repository for historical research but are not part of the default profile.

The ERP's installed/approved release baseline is 16.2.18. Continue operational development and testing against this release without rebuilding a replacement installer or changing production data. The pinned installer and SHA are recorded below; future application-version changes require a separate Owner decision. The standard WordPress plugin-upload interface remains the installation interface.

## Legacy source-rebuild verifier — do not use for the approved package

`.devcontainer/verify-master-installer.sh` rebuilds the installer from source. It is retained for future engineering and source archaeology, but it is **not** the production-approved installation path. Do not run it to produce a replacement 16.2.18 package. Use the exact-artifact helper in the next section.

## Exact approved 16.2.18 artifact — no rebuild

The Owner-designated 16.2.18 production installer is the existing GitHub Actions artifact [run 43570216177 / artifact 14104031868](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177). The canonical inner installer SHA-256 is `de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08`.

Do not run `.devcontainer/verify-master-installer.sh` for this exact-artifact check: that older helper rebuilds the installer from source. Use the new helper below; it downloads the pinned GitHub Actions artifact and verifies the exact approved ZIP without rebuilding or changing its bytes.

```bash
bash .devcontainer/initialize-host.sh
docker compose --project-directory .devcontainer -f .devcontainer/docker-compose.yml --profile mysql8 up -d --build
bash scripts/codespaces/verify_approved_16_2_18.sh all
```

The helper starts only the required disposable WordPress/MariaDB services and (for `all` or `mysql8`) the optional MySQL 8.0.46 services; it leaves the legacy upgrade site stopped. It validates both the outer Actions artifact digest and the inner installer SHA, the 16.2.18 manifest, all 124 file hashes, archive paths and CRC. It installs that exact ZIP into the disposable database targets and runs the existing synthetic ERP workflow suites. It does not connect to the live company database, rebuild the plugin, or alter the original approved package. The canonical `scripts/release/verify_master_installer.sh` and the existing package-build/release workflow are left unchanged; the Compose-specific host check and installation sequence live only in `scripts/codespaces/verify_approved_16_2_18.sh`.

## Cloudflare Free plan

See [Cloudflare Free-plan architecture and account inventory](CLOUDFLARE_FREE_PLAN.md). The connected account currently has one asset-only Worker named `erp`, with no bindings, custom domains, DNS zones, Pages projects, D1, KV, Queues or Durable Objects. R2 is not enabled yet. A production domain/zone must be added and delegated to this account before DNS proxy, zone-level WAF, cache or SSL settings can be configured for the live WordPress host. Keep WordPress/PHP/MySQL as the ERP transaction system of record; do not replace it with D1 or a Worker.