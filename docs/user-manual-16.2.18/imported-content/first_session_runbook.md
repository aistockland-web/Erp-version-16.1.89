# FIRST\_SESSION\_RUNBOOK

**Purpose:** Import and review this package as a feature folder without disturbing the existing ERP or starting runtime services.

## Before creating the branch

{% stepper %}
{% step %}
### Confirm repository write permission

Confirm write permission on `aistockland-web/Erp-version-16.1.89`.

The current connection reported `push:false`; a user with repository write access must enable the appropriate GitHub connection or perform the branch creation.
{% endstep %}

{% step %}
### Refresh canonical delivery context

Refresh the current canonical delivery branch and all open PRs.

The base used for this package was `fe937ea97123a243c410696e4007c48d3332803d`; if it moved, re-read:

* Root `AGENTS.md`
* Current `src/README.md`
* Release manifest
* PR #134
* Current CAP and Notion decisions
{% endstep %}

{% step %}
### Preserve existing working tree changes

Preserve all existing working tree changes.

Do not reset or clean the canonical repository, and do not overwrite any existing directory.
{% endstep %}

{% step %}
### Confirm existing development configuration

Confirm the existing root `.devcontainer/` hashes and root instructions.

This workspace does not supply a replacement Codespaces configuration.
{% endstep %}
{% endstepper %}

## Proposed branch and folder

**Proposed branch:** `feat/apparel-supply-chain-foundation-2026-11-15`

**Proposed destination:**

```
product/apparel-supply-chain/
```

The folder contains:

* Product README
* `AGENTS.md`
* Documentation
* Contracts
* QA-only schema tests
* Codespaces instructions
* A review-only release folder

It does not contain plugin runtime code or CSS.

## Import steps — not executed in this session

```bash
# First verify repository context; do not reset any files.
git status --short --branch
git rev-parse HEAD
git log -8 --oneline --decorate

# After the current base and permissions are confirmed:
git switch -c feat/apparel-supply-chain-foundation-2026-11-15
mkdir -p product/apparel-supply-chain
# Copy the prepared folder contents into product/apparel-supply-chain/.

# Verify file integrity and static contracts only.
python3 -m pip install -r product/apparel-supply-chain/qa/requirements.txt
cd product/apparel-supply-chain
python3 -m unittest qa/test_schema_registry.py -v
sha256sum -c release/SHA256SUMS
```

{% hint style="warning" %}
The copy step must preserve exact filenames and bytes. Do not copy `README.md` alone; import the whole package and its manifest. Do not add an old 16.1.78 archive or the unmerged PR #134 CSS changes.
{% endhint %}

## First commits

Use `commit-plan/changesets/` as the exact file-to-commit map.

Commit only one changeset at a time. Before every commit, inspect:

* `git diff --cached --stat`
* `git diff --cached --check`
* File hashes
* The corresponding test command

Update the same Notion execution record with the exact SHA, changed files, tests, and any blocked items after each real commit.

{% hint style="warning" %}
No `git commit` command should be run until the folder is imported into the canonical repository with current base, exact changeset, and repository write permission.

The local workspace folder itself is not a Git repository and contains no fabricated commit history.
{% endhint %}

## Runtime stage is separate

This first import is a documentation/contracts-only target.

Do not execute:

* `.devcontainer/post-create.sh`
* `docker compose up`
* WP-CLI
* Installer verification
* Database bootstrap
* Production deployment

These actions start disposable services or run application setup and belong to later user-authorized engineering increments.

## AP-00 before PHP/domain implementation

Before the first runtime source change, reconcile:

* Exact source archive/commit/file manifest and source provenance
* Full current 49-key schema and current HR extension/HRX approval state
* Product Master STYLE/VARIANT IDs and route contracts
* Stock ledger, reservation, procurement/GRN, and transfer state semantics
* Existing role/capability/site/location matrix and audit/workflow APIs
* Existing PHP/CSS/JS build and test commands
* Tenant isolation design against existing one-company Stockland records
* Finance and HR application data ownership and current integration points

{% hint style="danger" %}
A missing source artifact, ambiguous field, unknown schema default, or mismatched file SHA blocks code adoption; do not guess a replacement.
{% endhint %}
