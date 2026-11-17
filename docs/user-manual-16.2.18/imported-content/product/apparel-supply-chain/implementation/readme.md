# README

This folder contains a **source patch**, not a WordPress installer. It is based on the exact 16.2.18 installer ZIP SHA-256:

`4a6bb09b4f5819873fff7f1417de20494c7d791003faf9a55649458458977021`

The patch is relative to the canonical monorepo root (`src/stockland-erp-app/`) and includes only PHP/runtime source paths that applied cleanly to the baseline, plus two CLI contract tests under `ci/qa/`:

* The declarative apparel application/module catalogue
* The separately versioned, Owner-approved PLM schema contract
* Immutable normalized tech-pack revisions and review transitions
* The scoped PLM REST controller
* One guarded plugin bootstrap include

{% hint style="warning" %}
It intentionally excludes documentation/manifest hunks that conflict with newer 16.2.18 `GO_LIVE_RUNBOOK.md`, `README.md` and `RELEASE_MANIFEST.json`. Those must be reconciled manually before a release candidate can be built.

Do not install this overlay as a ZIP or call it a released version.
{% endhint %}

## Local verification performed

* 88 PLM and module-catalog contract assertions: PASS.
* 39 module catalogue assertions: PASS after aligning two expected HR/Finance `runtime_status` strings with the code's more accurate `CO_LOCATED_IN_LEGACY_PLUGIN_REQUIRES_EXTRACTION` value.
* PHP syntax: 87/87 files PASS under PHP 8.4.24.
* Contract test confirms protected baseline files remain byte-identical, including `class-stockland-activator.php`, `class-stockland-schema-contract.php`, accounting, HR, DB gateway, product service and product REST controller.
* PLM schema DDL is not installed during boot/activation. It requires the explicit Owner approval path, verified DDL fingerprint and backup confirmation.

{% hint style="info" %}
These are local static/contract checks only. The patch was applied and replay-tested against a fresh monorepo-shaped copy of the exact 16.2.18 base. No WordPress runtime, database migration, browser workflow, live host, remote commit, PR or deployment was executed.
{% endhint %}

## Applying the patch

{% stepper %}
{% step %}
### Validate the patch

From the root of the canonical `aistockland-web/Erp-version-16.1.89` checkout at the pinned `fe937…` base, run `git apply --check` with the canonical monorepo patch first.
{% endstep %}

{% step %}
### Apply on a feature branch

Apply the patch on a dedicated feature branch.

The patch changes `src/stockland-erp-app/` and adds the two CLI tests in `ci/qa/`; it does not bump the release version or rewrite `RELEASE_MANIFEST.json`.
{% endstep %}

{% step %}
### Reconcile and validate before building

Do not build an installer until the three documentation/manifest conflicts are reconciled and the full canonical CI suite passes.
{% endstep %}
{% endstepper %}
