# Stockland ERP application source

The editable WordPress app is materialized at src/stockland-erp-app/ from the verified 16.2.13 operational baseline. This is the actual Stockland ERP plugin, not a demo, tutorial or replacement application.

## Exact source identity

- Archive: STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip
- SHA-256: 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13
- Size: 2,188,684 bytes
- Inventory: 113 ZIP entries, 80 PHP files
- Plugin entry: src/stockland-erp-app/stockland-inventory.php

16.2.13 is the operational/upgrade baseline. 16.2.15 is a review candidate only and is not copied over this source. 16.1.95 remains the protected rollback/reference package.

## First launch

Codespaces post-create runs the pinned archive verifier, extracts both packages to /tmp/stockland-candidate-packages, then runs scripts/codespaces/bootstrap_project_source.sh. The importer compares every staged file with the intake report SHA-256 manifest and writes provenance to src/.stockland-erp-app.source.json.

On rebuild, a marked source directory is preserved without overwrite. An existing unmarked directory is never removed or replaced; the importer stops for manual inspection/backup. For an already-running Codespace, sync the branch and run:

    bash scripts/codespaces/bootstrap_project_source.sh

If package intake is missing, run:

    python scripts/codespaces/verify_candidate_archives.py --report artifacts/codespaces/package-intake.json --extract-root /tmp/stockland-candidate-packages

## Work rules

Edit PHP, JavaScript, CSS, templates and other runtime assets in src/stockland-erp-app/. Do not edit the temporary extraction or the disposable WordPress container copy as canonical source. Before the first behavior fix, commit the complete verified source tree and provenance marker as a separate source-intake commit. Keep the original .13/.15 ZIPs unchanged. Package only from a reviewed canonical source commit using the approved builder. Import does not certify runtime acceptance or production readiness.

Read AGENTS.md and the Stockland ERP delivery skill for the controlling engineering and data safeguards.
