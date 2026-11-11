# Stockland ERP Codespaces environment

This is an isolated synthetic QA workspace, never a production host.

## Private sites

- Port 8080: WordPress 7.1.4 / PHP 8.3 with MariaDB 10.11. Installs and activates .13.
- Port 8081: separate WordPress 7.1.4 / PHP 8.3 with MySQL 8.0.46. Installs .13, then replaces plugin files with the verified .15 candidate to exercise the update boot path.
- Database ports are internal and not forwarded. Databases, WordPress files and uploads are separate.

## First launch

Open the Codespace from the review branch and allow post-create to finish. It verifies both source ZIPs, installs local QA tools, installs WP-CLI only after sidecar verification, and provisions the two test sites. Use the Ports tab for private previews.

Generated test administrator credentials are stored with restrictive permissions under .codespaces/runtime/credentials/. This directory is ignored by Git. Never share or commit these credentials.

## Exact archives

.13: STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip and the .13 SHA-256 sidecar.
.15: STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip and its adjacent .sha256 sidecar.

The verifier recomputes SHA-256 and checks ZIP CRC, unique paths, one stockland-erp-app root, absolute/traversal paths, symlinks, bounded size and WordPress plugin entry point. Do not rename, repack or alter the source archives.

## Commands

- python3 ci/codespaces/verify_installers.py --verify-all
- bash ci/codespaces/acceptance.sh

The smoke report is written under the ignored runtime folder. It verifies sandbox install, plugin activation and REST boot. It does not create company products, stock, transactions or employee records.

No production credentials, live DB import, SMTP, payments, AI keys, employee/customer information or production backup destination is configured. Full business, schema, statutory, browser/device, restore/rollback and Owner acceptance remain separate gates.
