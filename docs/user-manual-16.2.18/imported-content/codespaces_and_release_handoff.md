# CODESPACES\_AND\_RELEASE\_HANDOFF

{% hint style="warning" %}
Prepare the apparel workspace only. Do **not** start Codespaces, Docker, WordPress, database containers, AI services, cutters, or production workflows as part of this work package.
{% endhint %}

## Existing environment to reuse

The canonical repository at base commit `fe937ea97123a243c410696e4007c48d3332803d` already contains the approved Stockland `.devcontainer/` and supporting scripts.

Reuse these exact files. Do not create:

* A second Dev Container
* A duplicate database stack
* A separate CSS pipeline
* An alternative app sample

Captured existing source file identifiers (Git blob SHA-1, as returned by GitHub):

| File                               | Git blob SHA                               |
| ---------------------------------- | ------------------------------------------ |
| `AGENTS.md`                        | `6b2905b65cc6fa513f2dcd3df2efe024a0737f1e` |
| `.devcontainer/README.md`          | `4fe0044ac94352b025cef05d1404860105d2089f` |
| `.devcontainer/devcontainer.json`  | `8f2b003804439cfe27478884e9ccf567e9a4240a` |
| `.devcontainer/Dockerfile`         | `ee9e1e20777156ccc9eed9f698c0e85332e19257` |
| `.devcontainer/docker-compose.yml` | `b5f89fbdb74136eb0881c82c835f358d4998b12e` |
| `.devcontainer/post-create.sh`     | `2f90012e0f26b5e5fca5c14a6647716624757b9e` |
| `docs/AGENT_FIRST_PROMPT.md`       | `5c023cb5035cb7d3385b1b3bfec18ff6e568fa3a` |

The environment already uses:

* PHP 8.3
* Node 22
* Python 3.12
* Composer
* GitHub CLI
* Docker-outside-of-Docker
* WP-CLI installed only after SHA-256 verification
* Private MariaDB 10.11 WordPress baseline and upgrade sites
* An optional MySQL 8.0.46 profile

Current forwarded ports are private:

* `8080`
* `8081`
* `8082`

Database ports remain internal.

## Important post-create behavior

The existing `.devcontainer/post-create.sh` is not a passive shell. It:

* Installs locked QA dependencies
* Validates or reuses the repaired 16.2.15 QA candidate
* Verifies package identity
* Materializes the existing source tree
* Bootstraps disposable WordPress sites

Do not launch Codespaces as part of the current preparation.

When the Owner later chooses to create the existing Codespace, read the then-current `.devcontainer/README.md` and `AGENTS.md` first and understand exactly which setup scripts execute.

## Workspace location

After branch permission is available, place the workspace in the canonical repository at:

```
product/apparel-supply-chain/README.md
product/apparel-supply-chain/docs/
product/apparel-supply-chain/contracts/schemas/
product/apparel-supply-chain/contracts/events/
product/apparel-supply-chain/ai/
product/apparel-supply-chain/acceptance/
```

Do not copy `.devcontainer/`; it already exists at repository root.

Do not move the existing plugin source.

The workspace is a separate planning and contracts area until each coherent implementation package is approved for integration into `src/stockland-erp-app/`.

## Preflight for a later authorized launch

These commands are for a future approved Codespace session and were **not run** for this package:

```bash
pwd
git remote -v
git status --short --branch
git rev-parse HEAD
git log -8 --oneline --decorate
php -v
node -v
python3 --version
composer --version
docker compose version
wp --info
```

The future session must:

* Start from the refreshed canonical delivery commit.
* Preserve any uncommitted or untracked changes.
* Confirm the selected branch before any code edit.
* Avoid production site credentials, customer backups, HR documents, real payroll, and live business transactions in Codespaces.

## Commit order

Planned commit groups are recorded in `docs/COMMIT_RELEASE_PLAN.md`.

Each group should be a small, reviewable pull request or a logical commit stack with targeted tests.

{% hint style="info" %}
Review product design and contracts before runtime schema or route changes.
{% endhint %}

## Release folder and certification vocabulary

This package contains a `release/` directory with:

* A source lock
* A SHA-256 file manifest
* A status manifest

Its status is `WORKSPACE_REVIEW_ONLY`.

It is not:

* A signed build
* An installable plugin
* A GitHub Release
* A compliance certificate
* A production acceptance record

A future release evidence folder should contain:

* Exact canonical source commit and tree hash.
* GitHub Actions run IDs, job results, and downloadable artifact IDs or digests.
* Exact inner installer SHA-256, ZIP CRC, per-file manifest, and SBOM/dependency/license inventory.
* PHP 8.3/8.4 × MariaDB 10.11/MySQL 8.0.46 results, browser/accessibility, backup/restore, upgrade, role-deny matrix, data reconciliation, security scans, and unresolved findings.
* Product/Owner/legal approval evidence and explicit release decision.

{% hint style="warning" %}
Do not call a feature “certified” solely because its code or package has a checksum.

A checksum proves byte identity. Tests prove only the tested behavior in the stated environment. External certification requires the applicable assessor and process.
{% endhint %}
