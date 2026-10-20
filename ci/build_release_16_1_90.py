#!/usr/bin/env python3
"""Build the canonical 16.1.90 plugin from the exact checked-in 16.1.89 ZIP."""
from __future__ import annotations
import argparse, hashlib, json, os, re, shutil, subprocess, tempfile, zipfile
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
BASE_SHA = "4b8a5fe5648ddf062953c671fe572cb62235757d3a092da6b23116aacae392c3"
BASE_SIZE = 1_104_097
BASE_PATTERN = "STOCKLAND_ERP_16.1.89_CANONICAL_WORDPRESS_UPDATE*.zip"
OUT = REPO / "STOCKLAND_ERP_16.1.90_CANONICAL_WORDPRESS_UPDATE.zip"
SIDECAR = REPO / (OUT.name + ".sha256")
LEGACY_REST = (
 "class-rest-accounting.php", "class-rest-app.php", "class-rest-calendar.php",
 "class-rest-controller.php", "class-rest-hr.php", "class-rest-import-v16.php",
 "class-rest-inventory-report-v16.php", "class-rest-locations.php", "class-rest-products.php",
 "class-rest-receipts.php", "class-rest-reports.php", "class-rest-requisition-v16.php",
 "class-rest-returns.php", "class-rest-sales.php", "class-rest-setup.php",
 "class-rest-stock.php", "class-rest-transfers.php", "class-rest-wholesale.php",
)
def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit("Required source context missing: " + label)
    return text.replace(old, new, 1)
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--phpcbf", required=True, help="Path to PHPCBF installed with WordPress Coding Standards")
    ap.add_argument("--output", default=str(OUT))
    args = ap.parse_args()
    bases = sorted(REPO.glob(BASE_PATTERN))
    if len(bases) != 1:
        raise SystemExit(f"Expected exactly one canonical 16.1.89 base ZIP; found {len(bases)}")
    raw = bases[0].read_bytes()
    if len(raw) != BASE_SIZE or hashlib.sha256(raw).hexdigest() != BASE_SHA:
        raise SystemExit("Refusing to build: exact 16.1.89 base ZIP size/checksum mismatch")
    out = Path(args.output)
    with tempfile.TemporaryDirectory(prefix="stockland-1690-build-") as td:
        temp = Path(td)
        with zipfile.ZipFile(bases[0]) as zf:
            if zf.testzip() is not None:
                raise SystemExit("Base ZIP integrity check failed")
            names = zf.namelist()
            if len(names) != 74 or len(set(names)) != 74 or not all(n.startswith("stockland-erp-app/") for n in names):
                raise SystemExit("Base ZIP structure is not the reviewed 74-file plugin package")
            zf.extractall(temp)
        app = temp / "stockland-erp-app"
        # The previous source gate found 162 safe, formatting-only PHPCBF corrections.
        paths = [app / p for p in (
            "includes/class-stockland-payroll-statutory.php",
            "includes/rest/class-rest-app.php",
            "includes/rest/class-rest-wholesale.php",
        )]
        proc = subprocess.run([args.phpcbf, "--standard=WordPress", *map(str, paths)],
                              text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
        if proc.returncode not in (0, 1):
            raise SystemExit("PHPCBF failed:\n" + proc.stdout)
        (REPO / "STOCKLAND_ERP_16.1.90_PHPCBF.log").write_text(proc.stdout, encoding="utf-8")
        hrp = app / "includes/rest/class-rest-hr.php"
        hr = hrp.read_text(encoding="utf-8")
        replacements = [
          ("throw new RuntimeException( $validation_error . ' Employee ' . esc_html( (string) $employee['employee_id'] ) . ', compensation effective ' . esc_html( (string) $c['effective_from'] ) . '. Open HR → Set Compensation and correct the displayed classification/data issue.' );",
           "// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST error data is rendered through textContent, not interpreted as HTML.\n\t\t\t\tthrow new RuntimeException( $validation_error . ' Employee ' . (string) $employee['employee_id'] . ', compensation effective ' . (string) $c['effective_from'] . '. Open HR → Set Compensation and correct the displayed classification/data issue.' );"),
          ("throw new RuntimeException( 'Allowance audit snapshot is missing the ' . esc_html( (string) $treatment ) . ' component needed to reconcile ' . esc_html( (string) $employee['employee_id'] ) . '. Re-save compensation through Owner HR.' );",
           "// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST error data is rendered through textContent, not interpreted as HTML.\n\t\t\t\t\t\tthrow new RuntimeException( 'Allowance audit snapshot is missing the ' . (string) $treatment . ' component needed to reconcile ' . (string) $employee['employee_id'] . '. Re-save compensation through Owner HR.' );"),
          ("throw new RuntimeException( 'Allowance component rounding differs from the statutory aggregate by more than a safe rounding tolerance for ' . esc_html( (string) $employee['employee_id'] ) . '. Re-save compensation.' );",
           "// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST error data is rendered through textContent, not interpreted as HTML.\n\t\t\t\t\t\tthrow new RuntimeException( 'Allowance component rounding differs from the statutory aggregate by more than a safe rounding tolerance for ' . (string) $employee['employee_id'] . '. Re-save compensation.' );"),
          ("throw new RuntimeException( 'Allowance evidence snapshot could not be reconciled without a negative component for ' . esc_html( (string) $employee['employee_id'] ) . '.' );",
           "// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST error data is rendered through textContent, not interpreted as HTML.\n\t\t\t\t\t\tthrow new RuntimeException( 'Allowance evidence snapshot could not be reconciled without a negative component for ' . (string) $employee['employee_id'] . '.' );"),
        ]
        for old, new in replacements:
            hr = replace_once(hr, old, new, "HR plain-text REST exception")
        hrp.write_text(hr, encoding="utf-8")
        for name in LEGACY_REST:
            p = app / "includes/rest" / name
            s = p.read_text(encoding="utf-8")
            comment = "// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- The established REST controller path is loaded by the plugin bootstrap; renaming it would break the include contract.\n"
            if "phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName" not in s:
                s = replace_once(s, "<?php\n", "<?php\n" + comment, "REST include filename " + name)
                p.write_text(s, encoding="utf-8")
        wholesale = app / "includes/rest/class-rest-wholesale.php"
        s = wholesale.read_text(encoding="utf-8")
        s = replace_once(s, "\t\t\t\t$prepared = $wpdb->prepare( $sql, $args );",
          "\t\t\t\t// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query template identifiers use allow-listed %i placeholders and all values use separate prepared arguments.\n\t\t\t\t$prepared = $wpdb->prepare( $sql, $args );", "prepared wholesale SQL")
        s = replace_once(s, "\t\t\t$rows = $wpdb->get_results( $prepared, ARRAY_A );",
          "\t\t\t// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $prepared comes from $wpdb->prepare above; live stock and workflow commitment reads must remain fresh.\n\t\t\t$rows = $wpdb->get_results( $prepared, ARRAY_A );", "fresh wholesale read")
        wholesale.write_text(s, encoding="utf-8")
        plugin = app / "stockland-inventory.php"
        s = plugin.read_text(encoding="utf-8")
        s = replace_once(s, "Version:           16.1.89", "Version:           16.1.90", "plugin header version")
        s = replace_once(s, "define( 'STOCKLAND_VERSION', '16.1.89' );", "define( 'STOCKLAND_VERSION', '16.1.90' );", "plugin version constant")
        plugin.write_text(s, encoding="utf-8")
        front = app / "includes/class-stockland-frontend.php"
        s = front.read_text(encoding="utf-8")
        s = replace_once(s, "const ROUTE_VERSION      = '16.1.89';", "const ROUTE_VERSION      = '16.1.90';", "route/cache version")
        front.write_text(s, encoding="utf-8")
        readme = app / "README.md"
        s = readme.read_text(encoding="utf-8")
        s = replace_once(s, "# Stockland Ethiopia ERP 16.1.89", "# Stockland Ethiopia ERP 16.1.90", "README title")
        new_section = """## Source-quality and REST message-context remediation — 16.1.90

- Keeps payroll validation and allowance-reconciliation errors as plain REST message data. The existing frontend renders toast text with `textContent`, avoiding HTML interpretation and entity double-encoding.
- Applies reviewed WordPress coding-standard alignment to the payroll statutory validator, System Health diagnostics and wholesale advisory/query code; no calculation, query predicate, authority or workflow transition changes.
- Documents only the 18 established REST controller filename exceptions, maps the 36 already-registered Stockland capabilities in the source-analysis ruleset, and narrowly documents prepared live wholesale reads that intentionally remain uncached.
- Preserves the 49-table contract, database schema markers, role capability IDs, variant/SKU effective-date history, immutable stock ledger, current payroll v3 statutory calculation, Purchase Order/GRN/PLEDGER/GL behavior and existing approval/SOP paths. No schema migration or business-data write is included.
- Keeps the six CSS modules, compiled CSS bundles, Bootstrap 5.3.8 and original logos unchanged from 16.1.89. The plugin and route/cache identity are bumped to invalidate stale frontend assets; no unverified visual redesign is bundled.

**Acceptance boundary:** source/package verification does not equal live acceptance. Production certification still requires a verified backup, authenticated live deep-integrity/ledger audit, role-by-role SOP checks, physical-device PWA acceptance and tested restore/rollback.

"""
        anchor = "## Four-theme header and real standalone install UX — 16.1.89"
        if anchor not in s:
            raise SystemExit("README 16.1.89 visual-history section is missing")
        s = s.replace(anchor, new_section + anchor, 1)
        readme.write_text(s, encoding="utf-8")
        modules = app / "assets/css/modules.json"
        md = json.loads(modules.read_text(encoding="utf-8"))
        md["release"] = "16.1.90"
        md["description"] = "16.1.90 source-quality update. Six CSS module files and compiled CSS bundles are byte-identical to 16.1.89."
        modules.write_text(json.dumps(md, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        mp = app / "RELEASE_MANIFEST.json"
        m = json.loads(mp.read_text(encoding="utf-8"))
        m["release"] = "16.1.90"
        m["release_label"] = "16.1.90 — REST message-context and source-quality remediation"
        m["classification"] = "Cumulative corrective update from the exact 16.1.89 installer. Keeps REST validation errors as plain text, documents established REST controller paths and registered capabilities for source analysis, and addresses reviewed PHPCS findings without changing operational business rules, permissions, stock identity, ledger posting, payroll formula or schema."
        m["baseline"] = "Exact 16.1.89 canonical installer SHA-256 " + BASE_SHA + "; 74-file stockland-erp-app package."
        m.setdefault("frontend", {})["notes"] = str(m.get("frontend", {}).get("notes", "")).strip() + " 16.1.90 updates plugin/route cache identity and reviewed PHP source comments only; CSS modules, compiled bundles, Bootstrap and logos remain byte-identical to 16.1.89."
        changes = m.get("release_candidate_changes", [])
        if not isinstance(changes, list): changes = []
        additions = [
          "16.1.90: keeps payroll validation and allowance-reconciliation errors as plain REST message data; client uses textContent to avoid HTML interpretation and entity double-encoding.",
          "16.1.90: applies reviewed WordPress coding-standard alignment to payroll statutory validation, System Health diagnostics and wholesale advisory/query construction without changing formulas or query predicates.",
          "16.1.90: documents the 18 established REST controller filename exceptions, the 36 already-registered custom capability identifiers, and the prepared uncached live wholesale read.",
          "16.1.90: no inventory/SKU history unlock or mutation, schema migration, ledger/GL/PLEDGER posting, role-capability migration, approval transition or payroll formula change."
        ]
        for item in additions:
            if item not in changes: changes.append(item)
        m["release_candidate_changes"] = changes
        m["acceptance"] = {
          "source_package_gate":"Exact-version 16.1.90 package/source/security and regression checks run through the linked GitHub workflow; these checks are not live-system acceptance.",
          "target_host":"PENDING — no authenticated live WordPress/MariaDB execution was performed by the build.",
          "production_deployment":"NOT PERFORMED by this build; install only after verified backup and live acceptance gates.",
          "note":"No live record or ledger was changed. Package checks do not certify actual ledger/audit-chain integrity, financial balances, role-by-role SOP acceptance, physical-device PWA, backup restore or rollback."
        }
        m["quality"] = {
          "release":"16.1.90",
          "local_validation_status":"Package/source validation must be read from the generated QA record and exact GitHub workflow run; the manifest is not a self-asserted CI result.",
          "target_host_acceptance":"PENDING — no authenticated live WordPress/MariaDB execution performed by the build.",
          "production_certification":"PENDING — requires authenticated live deep-integrity/audit-chain/ledger reconciliation, role-scoped operational acceptance, physical-device PWA check, verified backup restore and rollback.",
          "no_unverified_live_acceptance_claims": True,
          "exact_16_1_90_github_actions_status":"See the exact-version release workflow run."
        }
        m["release_acceptance"] = {
          "source_package_gate":"Recorded in the exact-version 16.1.90 release workflow and audit.",
          "target_host":"PENDING — no authenticated live production execution was performed.",
          "production_deployment":"NOT PERFORMED by this build; install only after verified backup and the documented live acceptance gates.",
          "note":"Package/source CI does not certify live database integrity, immutable ledger continuity, approval workflows or restore/rollback. No live-site change is claimed."
        }
        m["corrective_release_16_1_90"] = {
          "scope":"Plain-text REST payroll validation messaging; documented PHP source-quality remediation; no business/schema/CSS asset change.",
          "business_table_contract":"49 unchanged (29 Core/Operations + 16 Accounting/Procurement + 4 HR/Payroll)",
          "schema_changed":False,"database_version_changed":False,"inventory_ledger_changed":False,"payroll_calculation_changed":False,
          "role_capability_ids_changed":False,"css_assets_changed":False,"live_production_deployment":False,
          "target_host_acceptance":"PENDING — requires authenticated live WordPress/MariaDB execution; not inferred from package tests."
        }
        mp.write_text(json.dumps(m, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        # Package contains only the WordPress plugin root and the same 74 source/assets.
        files = sorted(p for p in app.rglob("*") if p.is_file())
        if len(files) != 74: raise SystemExit(f"Expected 74 packaged files, found {len(files)}")
        out.parent.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(out, "w") as zf:
            for p in files:
                name = Path("stockland-erp-app") / p.relative_to(app)
                info = zipfile.ZipInfo(name.as_posix(), (2026, 1, 1, 0, 0, 0))
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = (0o100644 & 0xffff) << 16
                zf.writestr(info, p.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        sha = hashlib.sha256(out.read_bytes()).hexdigest()
        SIDECAR.write_text(f"{sha}  {OUT.name}\n", encoding="utf-8")
        # Release QA script performs a second independent package/source-contract pass in CI.
        report = [
          "STOCKLAND ETHIOPIA ERP 16.1.90 — PACKAGE BUILD RECORD",
          f"Installer: {OUT.name}", f"Size bytes: {out.stat().st_size}", f"SHA-256: {sha}",
          "Base: 16.1.89 exact checksum verified before build",
          "Package root: stockland-erp-app/", "Expected package members: 74", "PHP runtime files: 47",
          "PHPCBF scope: payroll statutory validator, System Health diagnostic, wholesale advisory",
          "Source scope: plain REST validation messages, path/capability-aware PHPCS, release/cache identity",
          "Not changed: CSS assets, schema, database data, inventory ledger/SKU history, role capabilities, approval state, payroll formula",
          "Live WordPress/MariaDB acceptance, production update, backup/restore and physical-device acceptance: NOT PERFORMED"
        ]
        (REPO / "STOCKLAND_ERP_16.1.90_LOCAL_QA_RESULTS.txt").write_text("\n".join(report)+"\n", encoding="utf-8")
        print("\n".join(report))
if __name__ == "__main__":
    main()
