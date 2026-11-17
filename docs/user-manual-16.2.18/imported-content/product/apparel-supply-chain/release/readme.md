# README

**Release status:** `FOUR_REFERENCE_WORK_PACKAGE_LOCALLY_CONTRACT_TESTED_NOT_RELEASED`.

This directory stores integrity and review metadata for the product-engineering package. It does not contain a WordPress plugin installer and must not be uploaded to the production site.

## Verified scope

* Four contributor-specific best-practice maps: Euro Suits PLM/samples, Production Quality MES/SFC, Thread picker/gate, and T-VERSE channel OMS.
* 30 JSON Schema Draft 2020-12 contracts and five representative payload tests.
* A local Euro Suits PLM/module source patch against the exact 16.2.18 base, with 88 PLM assertions, 39 module-catalog assertions and 87/87 PHP syntax checks passing on a disposable extracted copy.
* Existing 49 operational table keys and protected Stockland files were not modified in the local PLM overlay. PLM DDL is Owner-approved and is not installed during plugin boot.

## Release status ladder

{% stepper %}
{% step %}
## `WORKSPACE_REVIEW_ONLY`

Current state: product docs/contracts and a locally tested source patch; not merged into the canonical repository.
{% endstep %}

{% step %}
## `FEATURE_BRANCH`

Source tree added on a named canonical Git branch.
{% endstep %}

{% step %}
## `PR_REVIEWED`

Exact diff reviewed; independent review and security/a11y findings reconciled.
{% endstep %}

{% step %}
## `CI_ACCEPTED`

Required tests pass on exact commit.
{% endstep %}

{% step %}
## `PACKAGE_BUILT`

Reproducible release built with per-file manifest, dependency/license inventory, SBOM and SHA-256.
{% endstep %}

{% step %}
## `INSTALLER_VERIFIED`

Exact ZIP install/update and full test matrix pass on disposable WordPress/MySQL/MariaDB.
{% endstep %}

{% step %}
## `RELEASE_APPROVED`

Business/legal/Owner approvals and operational readiness present.
{% endstep %}

{% step %}
## `PUBLISHED`

Accepted GitHub Release/tag/artifact.
{% endstep %}

{% step %}
## `DEPLOYED`

Separately authorized live deployment with backup/rollback evidence.
{% endstep %}
{% endstepper %}

{% hint style="warning" %}
The current package is **state 1 only**. No remote commit, PR, WordPress runtime, database migration, full application E2E, installer build, publication, live deployment or certification has been performed. `SHA256SUMS` proves file identity/integrity inside this workspace; it does not certify application behavior or compliance.
{% endhint %}

## Required release evidence

* Canonical repository, exact source commit and source tree SHA.
* Protected baseline package identities and before/after source diff.
* File inventory, build environment, dependency/license inventory, SBOM and source provenance.
* Unit/domain/REST authorization/DB integration/browser acceptance outputs for every contributed domain.
* MySQL 8 and MariaDB 10.11 plus supported PHP versions, backup/restore, concurrency and rollback.
* Security, accessibility, privacy, file/CAD parsing and AI governance reviews.
* Human release approvals and unresolved issue disposition.
* Exact artifact SHA-256 and a separate deployment receipt if deployment is approved.
