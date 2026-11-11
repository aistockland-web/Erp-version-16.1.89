# Stockland ERP — Codespaces E2E Workspace and .13 → .15 Candidate Runbook

**Date:** 11 November 2026  
**Repository:** aistockland-web/Erp-version-16.1.89  
**Working branch:** integration/stockland-erp-16213-to-16215-codespaces-2026-11-11  
**Base commit:** 1c3b743296b7a1f4e3b8a0b481e6883b5fed4154

## Purpose

The earlier Codespaces PR #67 configured PHP 8.3 and MariaDB, but its CI validated configuration and the PHP image only. It did not start WordPress/Apache or perform a plugin update. This environment adds two disposable WordPress sites and isolated MariaDB/MySQL servers.

The .15 archive was committed on 10 November by 6dd5263865cf2d6e4f64c133b9a3ff7212e60f16, after the 9 November Notion audit. The older Notion statement that no .15 archive was available is historical. Exact package CI for .15 must still run.

## Exact archive identities

| Package | Tracked ZIP | SHA-256 | Role |
|---|---|---|---|
| .13 | STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip | 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13 | Operational upgrade baseline |
| .15 | STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip | 8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df | Selected review candidate, not approved release |

The .13 sidecar preserves its logical filename without Git's (1) suffix; the verifier permits this known alias but pins its exact hash/size and historical 113-entry / 80-PHP inventory. The .15 ZIP and sidecar must match the pinned digest. Path/hash comparisons are structural only.

## Create Codespaces

[Create Stockland ERP Codespace](https://github.com/codespaces/new?repo=aistockland-web%2FErp-version-16.1.89&ref=integration%2Fstockland-erp-16213-to-16215-codespaces-2026-11-11)

Choose the repository devcontainer. A 4-core Codespace is recommended because two WordPress sites and two DB engines start together. Post-create verifies the ZIPs, installs locked QA tools and runs the update lab.

## Private preview services

| Site | Stack | URL |
|---|---|---|
| MariaDB | WordPress PHP 8.3 + MariaDB 10.11 | http://localhost:8080 |
| MySQL | WordPress PHP 8.3 + MySQL 8.0.46 | http://localhost:8081 |

Ports 8080/8081 are private. Database port 3306 stays internal and must not be forwarded/published. In a Codespace, open the private forwarded URL from the Ports tab.

## Commands

Package identity:
~~~bash
python scripts/codespaces/verify_candidate_archives.py --report artifacts/codespaces/package-intake.json --extract-root /tmp/stockland-candidates
~~~

WordPress update:
~~~bash
bash scripts/codespaces/bootstrap_wordpress.sh
~~~

The bootstrap installs .13 into each disposable site, creates a synthetic WordPress option marker and exports a pre-update SQL snapshot, then installs/activates the exact .15 ZIP. It verifies plugin version, active state, marker persistence, database table counts, REST root and login endpoint. A rerun does not downgrade an already updated site.

Workflow:
~~~text
.github/workflows/stockland-cumulative-acceptance.yml
~~~

It checks exact hashes/ZIP/path safety, PHP 8.3 and 8.4 syntax, JS/JSON/CSS, comparative WPCS reports, Semgrep, Gitleaks, Codespaces/Compose build, and WordPress update on MariaDB/MySQL.

## Scope limits

This lab does not prove production database parity, approved schema/ERD, rollback against historical production rows, role/location authorization, POS/inventory/ledger/finance reconciliation, HR vault encryption/access, two real payroll periods, Ethiopian statutory/legal acceptance, SOP approval, device/PWA acceptance or production restore/rollback. This is technical QA infrastructure, not a release certificate.

Keep Notion findings open until exact evidence closes them. Do not change approval states based only on Codespaces results. Production install, tags, releases, signed certificates and deployment remain prohibited until exact package gates and Owner/Operations/HR/Finance/qualified legal acceptance are complete.
