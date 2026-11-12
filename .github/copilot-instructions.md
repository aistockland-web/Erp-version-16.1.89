# Copilot agent instructions: Stockland ERP 16.2.18

Read `AGENTS.md` first; it governs. Work only on branch `delivery/stockland-erp-16.2.18-master-installer-2026-11-11` (PR #89) unless an issue says otherwise. Plugin source of truth: `src/stockland-erp-app/` (16.2.18). Installer: `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`, SHA-256 in the neighbouring `.sha256` file. Final review: `docs/engineering/STOCKLAND_ERP_16.2.18_FINAL_RELEASE_REVIEW_2026-11-12.md`.

## Verify inside the dev container / Codespace
1. `bash .devcontainer/verify-master-installer.sh all` (MariaDB site on 8080, MySQL 8.0.46 profile on 8082; disposable sites only).
2. `node scripts/release/ui/ui_smoke.js <site-url> <admin> <password> ./shots`.
3. Confirm PHP `upload_max_filesize` and `post_max_size` are at least 8M, otherwise `live_ready` correctly stays false.
4. Append versions, PASS/FAIL per check, run IDs and the ZIP SHA-256 to `docs/engineering/STOCKLAND_ERP_16.2.18_MASTER_INSTALLER_EVIDENCE_2026-11-11.md`; commit to the same branch.

## If a check fails
Find the root cause, make the smallest fix in `src/stockland-erp-app/`, run `python3 scripts/release/build_master_installer.py --update-manifest`, re-run the checks, update the `.sha256`, the evidence file and the PR description. Never weaken a check to make it pass.

## Hard rules
- Dedicated branch and PR only. No merge, tag, release or deployment without the Owner's explicit written authorization.
- Disposable QA sites and synthetic data only; never production data.
- Schema changes are add-only; never drop, truncate or rename ERP tables.
- Keep maker-checker separation (Owner cannot prepare customer invoices; preparers cannot self-approve).
- Never invent Ethiopian payroll rates, tax bands or legal approvals. Payroll stays blocked until an Owner-approved, sourced, independently reviewed rule set exists.
- Do not commit `__pycache__` or other generated files.
- Record the outcome in the Notion execution record: https://app.notion.com/p/4159f20c06128155865bc34a206cac3d
