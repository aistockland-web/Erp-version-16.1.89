# Stockland ERP 16.2.18 — one-pack install and activation guide

## Contents (verify first: `sha256sum -c SHA256SUMS.txt`)
1. `1_STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip` — the ERP plugin (required). Built from repository source (branch audit/full-2026-11-20, PR #151) with the repository builder.
2. `2_OPTIONAL_STOCKLAND_ERP_GITBOOK_READONLY_BRIDGE_1.0.0.zip` — separate add-on giving GitBook Ask live, read-only catalog facts beside the published User Manual (real-time assistance). It changes no ERP file, table or record.
3. `evidence/` — verification records and the 21-suite run log.

## A. Install the ERP (required)
1. Take a full backup of the site and database (rollback point). Requirements: WordPress 6.8+, PHP 8.3+, MariaDB 10.11+ or MySQL 8.0.
2. Set PHP `upload_max_filesize` and `post_max_size` above the plugin's XLSX limit (the upload-capacity health gate fails otherwise).
3. WordPress admin → Plugins → Add New → Upload Plugin → file 1 → Activate. **Activate while logged in as the person who must be the Owner**: the installing administrator receives the Owner profile.
4. Open ERP Owner System Control and let setup finish: it creates the 49-table operational baseline (29 core, 16 finance, 4 legacy HR).
5. HR/payroll lifecycle (37 further tables) is created only after the Owner approves it with a written reason. Do not approve until the HR scope is accepted.
6. Private HR documents are stored encrypted outside the web root. Define a 64-hex-character key (`STOCKLAND_PRIVATE_VAULT_KEY`) and a private directory outside the web root (`STOCKLAND_PRIVATE_VAULT_DIR`, permissions 0700) in wp-config.php, then confirm in Owner System Control. Keep the key in the company key register; losing it makes stored documents unreadable.
7. Gates that remain closed until the Owner acts: schema-contract approval, release approval, live-ready. The application will not report live-ready by itself.
8. Payroll: enter the real PAYE and pension rules and the independent legal/tax opinion in the application. Payroll generation is refused without an approved, effective-dated rule set; an Owner declaration is not accepted as the legal review.

## B. Optional: real-time manual assistance (GitBook bridge)
1. Upload and activate file 2 on the same site (ERP 16.2.18 must be active; it stops with an error on any other version).
2. Settings → Stockland GitBook Bridge: copy the MCP endpoint (`/wp-json/stockland-bridge/v1/mcp`) and the bearer token.
3. In the existing GitBook site, register a custom MCP server: HTTP transport, header `Authorization: Bearer <token>`. The site must be reachable over HTTPS. Do not edit or republish the manual.
4. Test with GitBook Ask. Status: the bridge is verified locally; the GitBook-side connection is NOT yet tested and the add-on is not certified until it is.

## C. Rollback
Deactivate the add-on, then the ERP plugin; restore the pre-install database backup and the plugin directory. Deactivation does not drop ERP data.

## Status
Technically verified on MySQL 8 (21 business suites, official installer verifier) and, per repository CI, on MariaDB 10.11/11.4 and PHP 8.3/8.4. **Not production-approved**: Owner/HR/Finance/legal sign-off, payroll rule ratification and written release authorization are required.
