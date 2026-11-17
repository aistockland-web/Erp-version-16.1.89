# SCHEMA\_TEST\_RESULTS

**Date:** 17 November 2026\
**Runtime:** Python 3.13 / jsonschema 4.26.0

## Commands

* `python -m unittest qa/test_schema_registry.py -v`
* `python -m unittest qa/test_four_reference_contracts.py -v`

## Results

10 tests passed, 0 failures.

The schema registry validates 30 JSON Schema Draft 2020-12 contracts, unique `$id` values, closed objects, and required fields.

Five additional representative payload tests cover:

* Staged T-VERSE external orders and bundle decomposition
* Production Quality hourly operation events
* Thread-style short-pick reasons
* Gate manifest/scan evidence
* Euro Suits sample review decisions

Unknown properties such as direct stock posting, payroll values, and user-supplied efficiency are rejected by the relevant contracts.

{% hint style="warning" %}
The new contracts are specifications, not active ERP endpoints.
{% endhint %}

Application-level rules still must enforce:

* Idempotency against persisted keys
* Role/location scope
* Manifest quantity equality
* Reviewer independence
* Frozen routing revisions
* Exactly-once stock ledger posting

{% hint style="info" %}
This test does not start WordPress, MySQL/MariaDB, browser E2E, Codespaces, a factory line, a warehouse scanner, a channel API, an AI model, or production.
{% endhint %}
