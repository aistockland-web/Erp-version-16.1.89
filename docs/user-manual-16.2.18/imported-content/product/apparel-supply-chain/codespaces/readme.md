# README

**Status:** Prepared instructions only. Codespace was not created, opened, or run in this session.

## Reuse the current canonical Codespace

Use repository `aistockland-web/Erp-version-16.1.89`, branch `delivery/stockland-full-erp-completion-2026-11-11`, pinned base `fe937ea97123a243c410696e4007c48d3332803d` as of this workspace preparation.

The root `.devcontainer/` is already configured for the Stockland PHP/WordPress engineering toolchain and private synthetic sites. This workspace does **not** add or replace:

* `.devcontainer/devcontainer.json`
* `.devcontainer/Dockerfile`
* `.devcontainer/docker-compose.yml`
* `.devcontainer/post-create.sh`
* root `AGENTS.md`
* `src/stockland-erp-app/` or the protected 16.2.18 installer

The reviewed Dev Container requests 4 CPU / 16 GB RAM, PHP 8.3, Node 22, Python 3.12, Composer, GitHub CLI, Docker-outside-of-Docker, MariaDB 10.11 private services, and optional MySQL 8.0.46. WordPress private preview ports are 8080/8081/8082; database services remain internal.

## Future folder placement

After the user with canonical repository write access creates the feature branch, copy this folder to:

`product/apparel-supply-chain/`

This `product/` path is a proposed new folder; it is not present in the canonical repository snapshot at the pinned base. Do not overwrite root `.devcontainer` or existing app source. The new product folder should be documentation/contracts only until AP-00 source/data reconciliation is accepted.

## Safe preparation commands for the future Codespace

The following commands are for a future owner-started Codespace. They are deliberately **not executed now**.

```bash
git status --short --branch
git rev-parse HEAD
git log -8 --oneline --decorate
php -v
node --version
python3 --version
composer --version
docker compose version

# Verify the new planning package only; do not run the WordPress app yet.
find product/apparel-supply-chain/contracts/schemas -name '*.json' -print0 \
  | xargs -0 -n1 jq -e . >/dev/null
sha256sum -c product/apparel-supply-chain/release/SHA256SUMS
```

{% hint style="warning" %}
Do not run `.devcontainer/post-create.sh`, `docker compose up`, WP-CLI, database bootstrap, or application-specific tests merely to inspect this folder. The existing `post-create.sh` installs dependencies and bootstraps disposable WordPress sites. The Owner should initiate that environment when ready.
{% endhint %}

## Production separation

{% hint style="danger" %}
Codespaces and WordPress containers use synthetic test data only. No live Stockland URL, production DB credentials, private HR vault, employee data, customer/supplier data, payroll, journal, or inventory backup is allowed in the workspace.
{% endhint %}
