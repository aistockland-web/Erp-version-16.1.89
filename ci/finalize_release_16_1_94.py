#!/usr/bin/env python3
"""Finalize one cumulative Stockland ERP 16.1.94 update from the tested 16.1.93 build.

This changes release/cache identity only after all 16.1.93 patch stages have run.
It does not perform database migrations or change business logic/data.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import tempfile
import zipfile
from pathlib import Path

ROOT = "stockland-erp-app/"
ENTRY = ROOT + "stockland-inventory.php"
FRONTEND = ROOT + "includes/class-stockland-frontend.php"
MANIFEST = ROOT + "RELEASE_MANIFEST.json"
README = ROOT + "README.md"
CSS_MODULES = ROOT + "assets/css/modules.json"
ACTIVATOR = ROOT + "includes/class-stockland-activator.php"
SOURCE_RELEASE = "16.1.93"
TARGET_RELEASE = "16.1.94"


def replace_exact(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise ValueError(f"{label}: expected one exact anchor, found {count}")
    return source.replace(old, new, 1)


def finalize(input_zip: Path, output_zip: Path) -> dict:
    input_zip, output_zip = Path(input_zip), Path(output_zip)
    if not input_zip.is_file():
        raise FileNotFoundError(f"Input package not found: {input_zip}")
    input_sha = hashlib.sha256(input_zip.read_bytes()).hexdigest()

    with zipfile.ZipFile(input_zip, "r") as zin:
        bad = zin.testzip()
        if bad:
            raise ValueError(f"Input ZIP CRC failure: {bad}")
        infos = [info for info in zin.infolist() if not info.is_dir()]
        names = [info.filename for info in infos]
        if len(names) != 75 or len(set(names)) != 75:
            raise ValueError(f"Expected 75 unique plugin files, found {len(names)}")
        if not all(name.startswith(ROOT) and ".." not in Path(name).parts for name in names):
            raise ValueError("Input package has an unsafe path or unexpected plugin root")
        files = {name: zin.read(name) for name in names}

    manifest = json.loads(files[MANIFEST].decode("utf-8"))
    css_manifest = json.loads(files[CSS_MODULES].decode("utf-8"))
    entry = files[ENTRY].decode("utf-8")
    frontend = files[FRONTEND].decode("utf-8")
    readme = files[README].decode("utf-8")

    if manifest.get("release") != SOURCE_RELEASE or css_manifest.get("release") != SOURCE_RELEASE:
        raise ValueError("Input package is not the expected 16.1.93 cumulative build")
    if f"Version:           {SOURCE_RELEASE}" not in entry:
        raise ValueError("Plugin header release marker does not match the expected input")
    if f"define( 'STOCKLAND_VERSION', '{SOURCE_RELEASE}' );" not in entry:
        raise ValueError("STOCKLAND_VERSION does not match the expected input")
    if f"const ROUTE_VERSION      = '{SOURCE_RELEASE}';" not in frontend:
        raise ValueError("Frontend route/cache marker does not match the expected input")
    if manifest.get("schema_contract", {}).get("total") != 49:
        raise ValueError("The 49-table schema contract is missing")
    if manifest.get("schema_contract", {}).get("core_operations") != 29:
        raise ValueError("The 29-table core schema contract is missing")
    if manifest.get("schema_contract", {}).get("accounting_procurement") != 16:
        raise ValueError("The 16-table accounting/procurement contract is missing")
    if manifest.get("schema_contract", {}).get("hr_payroll") != 4:
        raise ValueError("The 4-table HR/payroll contract is missing")
    if "document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport());return null;" not in files[ROOT + "assets/app.js"].decode("utf-8"):
        raise ValueError("Inline report failure/retry fix is missing")
    approval_css = files[ROOT + "assets/css/06-operational-guards.css"].decode("utf-8")
    for marker in (
        ".sl-overlay .sl-req-drawer .sl-approval-grid",
        ".sl-overlay .sl-req-drawer .sl-approval-person small",
        "@media (max-width: 767px)",
    ):
        if marker not in approval_css:
            raise ValueError("Responsive approval layout marker missing: " + marker)
    if "WordPress hosting compatibility</h2>" not in files[ROOT + "includes/class-stockland-admin.php"].decode("utf-8"):
        raise ValueError("WordPress host compatibility diagnostic title is missing")
    if "plugin requires 8.3+" not in files[ROOT + "includes/class-stockland-admin.php"].decode("utf-8"):
        raise ValueError("PHP 8.3 minimum-host diagnostic is missing")
    if "function managementIntelligence()" not in files[ROOT + "assets/app.js"].decode("utf-8"):
        raise ValueError("Management Intelligence feature is missing from the cumulative candidate")
    if files[ACTIVATOR] == b"":
        raise ValueError("Schema activator source is unexpectedly empty")

    # Version/cache identity changes only. Business handlers, schema and transaction logic remain unchanged.
    entry = replace_exact(entry, f"Version:           {SOURCE_RELEASE}", f"Version:           {TARGET_RELEASE}", "plugin header version")
    entry = replace_exact(
        entry,
        f"define( 'STOCKLAND_VERSION', '{SOURCE_RELEASE}' );",
        f"define( 'STOCKLAND_VERSION', '{TARGET_RELEASE}' );",
        "STOCKLAND_VERSION",
    )
    frontend = replace_exact(
        frontend,
        f"const ROUTE_VERSION      = '{SOURCE_RELEASE}';",
        f"const ROUTE_VERSION      = '{TARGET_RELEASE}';",
        "frontend route/cache version",
    )

    manifest["release"] = TARGET_RELEASE
    manifest["baseline"] = SOURCE_RELEASE
    manifest["release_label"] = "Stockland Ethiopia ERP — cumulative WordPress update 16.1.94"
    manifest["classification"] = (
        "Cumulative WordPress plugin update from the 16.1.93 candidate. Preserves the existing "
        "Stockland business schema and transaction model; includes the cumulative stock/reorder "
        "consistency, report fail-closed recovery, host diagnostics and responsive approval-history "
        "presentation changes. This package build does not change live business data."
    )
    manifest["release_changes"] = [
        "Cumulative update from 16.1.93; no schema migration or business-data seed.",
        "Reports: database read errors fail closed; the user sees one inline error with Retry instead of a duplicate blocking toast.",
        "Inventory reporting: physical stock, active reservations, sellable availability and eligible inbound transfers stay distinct.",
        "Requisition approval history: scoped responsive layout wraps approver labels, notes and status pills on phones without changing approval handlers.",
        "Hosting: PHP 8.3+ WordPress compatibility diagnostics; no separate application service, database or scheduled-worker dependency.",
        "Release/cache identity advanced to 16.1.94 to refresh frontend route and PWA asset identity.",
        "Payroll values and employee classifications are not synthesized or auto-filled; real compensation exceptions remain subject to authorized HR/Owner review.",
        "The existing POS posting behavior is preserved; no unsupported multi-line-cart capability is claimed by this package."
    ]
    manifest["release_gate_note"] = (
        "Package/static/browser checks are recorded in the external release receipt. "
        "A passing local fixture does not replace target-host, live-data, statutory payroll, "
        "deep-integrity or Owner acceptance gates."
    )

    css_manifest["release"] = TARGET_RELEASE
    css_manifest["description"] = (
        "16.1.94 cumulative release/cache metadata. Six ordered CSS source modules include the "
        "scoped requisition approval-history responsive layout; app.css and app.min.css must match "
        "the deterministic module build."
    )

    release_section = """## Release 16.1.94 — cumulative update from 16.1.93

This cumulative package advances the plugin/route/cache identity to 16.1.94. It includes the report error/retry presentation fix, responsive requisition approval-history layout, stock/reorder consistency updates, WordPress host diagnostics cleanup and existing Management Intelligence UI. Existing REST handlers, database schema marker, stock/accounting posting and historical records are preserved; the package build does not touch live data.

Before deployment, back up the WordPress files and MariaDB/MySQL database and verify restoration/rollback. After updating the existing plugin in place, verify System Health and the exact plugin/schema markers. Resolve any payroll compensation readiness exception through authorized HR/Owner review. This update does not invent employee compensation or claim that unverified HR lifecycle functions are complete.

"""
    if not readme.startswith(f"# Stockland Ethiopia ERP — WordPress plugin update {SOURCE_RELEASE}"):
        raise ValueError("README title does not match the expected 16.1.93 input")
    readme = readme.replace(
        f"# Stockland Ethiopia ERP — WordPress plugin update {SOURCE_RELEASE}",
        f"# Stockland Ethiopia ERP — WordPress plugin update {TARGET_RELEASE}",
        1,
    )
    readme = readme.replace(
        f"# Stockland Ethiopia ERP — WordPress plugin update {TARGET_RELEASE}\n\n",
        f"# Stockland Ethiopia ERP — WordPress plugin update {TARGET_RELEASE}\n\n" + release_section,
        1,
    )

    files[ENTRY] = entry.encode("utf-8")
    files[FRONTEND] = frontend.encode("utf-8")
    files[MANIFEST] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    files[CSS_MODULES] = (json.dumps(css_manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    files[README] = readme.encode("utf-8")

    if output_zip.resolve() == input_zip.resolve():
        raise ValueError("Output must be a new file; the input package is never overwritten")
    output_zip.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp_name = tempfile.mkstemp(prefix="stockland-16-1-94-", suffix=".zip", dir=str(output_zip.parent))
    os.close(fd)
    tmp = Path(tmp_name)
    try:
        with zipfile.ZipFile(tmp, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
            for info in infos:
                zi = zipfile.ZipInfo(info.filename, date_time=info.date_time)
                zi.compress_type = zipfile.ZIP_DEFLATED
                zi.external_attr = info.external_attr
                zi.create_system = info.create_system
                zi.flag_bits = info.flag_bits
                zout.writestr(zi, files[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        with zipfile.ZipFile(tmp, "r") as check:
            bad = check.testzip()
            out_names = [item.filename for item in check.infolist() if not item.is_dir()]
            if bad or out_names != names:
                raise ValueError(f"Final archive CRC/inventory mismatch: {bad}")
            final_files = {name: check.read(name) for name in out_names}
            final_manifest = json.loads(final_files[MANIFEST].decode("utf-8"))
            final_css_manifest = json.loads(final_files[CSS_MODULES].decode("utf-8"))
            final_entry = final_files[ENTRY].decode("utf-8")
            final_frontend = final_files[FRONTEND].decode("utf-8")
            if final_manifest.get("release") != TARGET_RELEASE or final_css_manifest.get("release") != TARGET_RELEASE:
                raise ValueError("Final manifest/CSS release marker mismatch")
            if f"Version:           {TARGET_RELEASE}" not in final_entry or f"define( 'STOCKLAND_VERSION', '{TARGET_RELEASE}' );" not in final_entry:
                raise ValueError("Final plugin header/constant mismatch")
            if f"const ROUTE_VERSION      = '{TARGET_RELEASE}';" not in final_frontend:
                raise ValueError("Final route/cache identity mismatch")
            if final_manifest.get("schema_contract", {}).get("total") != 49:
                raise ValueError("Final 49-table schema contract changed")
            if final_files[ACTIVATOR] != files[ACTIVATOR]:
                raise ValueError("Schema activator changed during version finalization")
            changed = sorted(name for name in names if files[name] != final_files[name])
            allowed = sorted([README, MANIFEST, CSS_MODULES, FRONTEND, ENTRY])
            if changed != allowed:
                raise ValueError(f"Unexpected finalizer changed-file allowlist: {changed}")
        tmp.replace(output_zip)
    finally:
        if tmp.exists():
            tmp.unlink()

    out_sha = hashlib.sha256(output_zip.read_bytes()).hexdigest()
    receipt = {
        "release": TARGET_RELEASE,
        "baseline_release": SOURCE_RELEASE,
        "input_file": input_zip.name,
        "input_sha256": input_sha,
        "output_file": output_zip.name,
        "output_sha256": out_sha,
        "output_bytes": output_zip.stat().st_size,
        "plugin_file_count": 75,
        "plugin_root": ROOT,
        "schema_contract": {"core": 29, "accounting_procurement": 16, "hr_payroll": 4, "total": 49},
        "changed_files": sorted([README, MANIFEST, CSS_MODULES, FRONTEND, ENTRY]),
        "schema_activator_unchanged": True,
        "live_business_data_changed": False,
        "report_error_retry_marker": True,
        "responsive_approval_layout_marker": True,
        "php_minimum": "8.3+",
        "pwa_cache_bust": "STOCKLAND_VERSION + frontend route version 16.1.94",
        "certification": "LOCAL_PACKAGE_PASS_ONLY; TARGET_HOST_AND_DATA_ACCEPTANCE_REQUIRED",
    }
    receipt_path = output_zip.with_suffix(".receipt.json")
    receipt_path.write_text(json.dumps(receipt, indent=2) + "\n", encoding="utf-8")
    output_zip.with_suffix(output_zip.suffix + ".sha256").write_text(
        f"{out_sha}  {output_zip.name}\n", encoding="utf-8"
    )
    print(json.dumps(receipt, indent=2))
    return receipt


def main() -> None:
    parser = argparse.ArgumentParser(description="Finalize cumulative Stockland ERP WordPress update 16.1.94.")
    parser.add_argument("input_zip", type=Path)
    parser.add_argument("--output", type=Path, default=Path("STOCKLAND_ERP_16.1.94_CANONICAL_WORDPRESS_UPDATE.zip"))
    args = parser.parse_args()
    finalize(args.input_zip, args.output)


if __name__ == "__main__":
    main()
