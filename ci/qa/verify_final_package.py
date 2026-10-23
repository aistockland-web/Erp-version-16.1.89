#!/usr/bin/env python3
"""Fail-closed verification of the final cumulative Stockland WordPress package.

This gate compares the final ZIP and its SHA-256 sidecar to the exact locked
baseline and build receipt. It must run AFTER every source/package patch and
before publishing the Action artifact. It never contacts or mutates a live site.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import zipfile
from pathlib import Path

ROOT = "stockland-erp-app/"
EXPECTED_FILES = 75
EXPECTED_RELEASE = "16.1.94"
EXPECTED_BASELINE_RELEASE = "16.1.93"
EXPECTED_SCHEMA = {
    "core_operations": 29,
    "accounting_procurement": 16,
    "hr_payroll": 4,
    "total": 49,
}
ALLOWED_CHANGED = {
    "stockland-erp-app/README.md",
    "stockland-erp-app/RELEASE_MANIFEST.json",
    "stockland-erp-app/assets/app.js",
    "stockland-erp-app/assets/css/modules.json",
    "stockland-erp-app/includes/class-stockland-frontend.php",
    "stockland-erp-app/includes/rest/class-rest-app.php",
    "stockland-erp-app/includes/rest/class-rest-stock.php",
    "stockland-erp-app/includes/rest/class-rest-inventory-report-v16.php",
    "stockland-erp-app/includes/class-stockland-admin.php",
    "stockland-erp-app/includes/class-stockland-xlsx.php",
    "stockland-erp-app/includes/rest/class-rest-hr.php",
    "stockland-erp-app/assets/bootstrap.min.css",
    "stockland-erp-app/assets/css/06-operational-guards.css",
    "stockland-erp-app/assets/app.css",
    "stockland-erp-app/assets/app.min.css",
    "stockland-erp-app/stockland-inventory.php",
}
ALLOWED_URIS = {
    b"http://www.w3.org/2000/svg",
    b"http://schemas.openxmlformats.org/officeDocument/2006/relationships",
    b"https://stocklandshop.rf.gd/wp-content/uploads/2026/09/22623-20kb.jpeg",
}
PROHIBITED_MARKERS = (
    b"Server environment</h2>",
    b"plugin requires 8.4+",
    b"InfinityFree currently provides",
    b"justice.gov.et",
    b"workflow_run_url",
    b"source_gate_evidence",
    b"historical_release",
    b"release_candidate_changes",
    b"test on staging",
    b"postgresql",
    b"node.js",
)
EXTERNAL_URL = re.compile(rb"""https?://[^\s"'<>]+""", re.I)


def fail(message: str) -> None:
    raise SystemExit("FAIL final-package gate: " + message)


def load_archive(path: Path) -> tuple[list[str], dict[str, bytes]]:
    if not path.is_file():
        fail(f"missing package: {path}")
    try:
        with zipfile.ZipFile(path) as archive:
            bad = archive.testzip()
            if bad is not None:
                fail(f"ZIP CRC failure at {bad}")
            infos = [item for item in archive.infolist() if not item.is_dir()]
            names = [item.filename for item in infos]
            if len(names) != EXPECTED_FILES or len(set(names)) != len(names):
                fail(f"expected {EXPECTED_FILES} unique plugin files, got {len(names)}")
            if not all(
                name.startswith(ROOT) and ".." not in name.split("/")
                for name in names
            ):
                fail("archive must contain one safe stockland-erp-app/ root")
            if any(
                name.startswith((".github/", "ci/", "tests/", "test/"))
                or "/node_modules/" in name
                or name.endswith((".pyc", ".log"))
                for name in names
            ):
                fail("test/build tooling leaked into the installable plugin")
            return names, {name: archive.read(name) for name in names}
    except zipfile.BadZipFile as exc:
        fail(f"not a valid ZIP: {exc}")


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def normalize_css(source: str) -> str:
    result = re.sub(r"/\*[\s\S]*?\*/", " ", source)
    result = re.sub(r"\s+", " ", result).strip()
    return re.sub(r"\s*([{}:;,])\s*", r"\1", result).replace(";}", "}")


def parse_json(files: dict[str, bytes], name: str) -> dict:
    try:
        value = json.loads(files[ROOT + name].decode("utf-8"))
        if not isinstance(value, dict):
            fail(f"{name} must be a JSON object")
        return value
    except (KeyError, UnicodeDecodeError, json.JSONDecodeError) as exc:
        fail(f"invalid {name}: {exc}")


def inspect_payload(files: dict[str, bytes]) -> dict:
    manifest = parse_json(files, "RELEASE_MANIFEST.json")
    css_manifest = parse_json(files, "assets/css/modules.json")
    try:
        json.loads(files[ROOT + "assets/manifest.json"].decode("utf-8"))
    except (KeyError, UnicodeDecodeError, json.JSONDecodeError) as exc:
        fail(f"invalid PWA manifest: {exc}")

    if manifest.get("release") != EXPECTED_RELEASE:
        fail(f"manifest release is {manifest.get('release')!r}, expected {EXPECTED_RELEASE}")
    if css_manifest.get("release") != EXPECTED_RELEASE:
        fail("CSS module release marker does not match the package")
    if manifest.get("minimum_php") != "8.3":
        fail("release manifest does not declare PHP 8.3 compatibility")
    for key, expected in EXPECTED_SCHEMA.items():
        if manifest.get("schema_contract", {}).get(key) != expected:
            fail(f"schema contract mismatch for {key}")

    entry = files[ROOT + "stockland-inventory.php"].decode("utf-8", "replace")
    front = files[ROOT + "includes/class-stockland-frontend.php"].decode("utf-8", "replace")
    admin = files[ROOT + "includes/class-stockland-admin.php"].decode("utf-8", "replace")
    xlsx = files[ROOT + "includes/class-stockland-xlsx.php"].decode("utf-8", "replace")
    php = files[ROOT + "includes/rest/class-rest-app.php"].decode("utf-8", "replace")
    js = files[ROOT + "assets/app.js"].decode("utf-8", "replace")
    hr = files[ROOT + "includes/rest/class-rest-hr.php"].decode("utf-8", "replace")

    if "Version:           16.1.94" not in entry or "define( 'STOCKLAND_VERSION', '16.1.94' );" not in entry:
        fail("plugin header and runtime release constant do not match")
    if "* Requires PHP:      8.3" not in entry or "const ROUTE_VERSION      = '16.1.94';" not in front:
        fail("PHP/route/cache release markers are inconsistent")
    if "WordPress hosting compatibility</h2>" not in admin:
        fail("hosting diagnostics heading was not normalized")
    for phrase in ("PHP version", "MySQL/MariaDB version", "memory_limit", "max_execution_time"):
        if phrase not in admin:
            fail(f"required hosting diagnostic {phrase!r} was removed")
    if "InfinityFree currently provides" in xlsx:
        fail("obsolete hard-coded hosting assumption remains")
    for marker in PROHIBITED_MARKERS:
        for name, data in files.items():
            if marker.lower() in data.lower():
                fail(f"prohibited stale/dev marker {marker.decode('utf-8', 'replace')!r} in {name}")

    schema_path = ROOT + "includes/class-stockland-activator.php"
    if schema_path not in files:
        fail("schema activator missing")
    if files[ROOT + "stockland-inventory.php"].decode("utf-8", "replace").count("16.1.1") == 0:
        fail("DB schema version marker 16.1.1 not retained")

    if php.count("report_source_query_failed") < 10 or "report_location_label_failed" not in php:
        fail("required report-source query failures do not all fail closed")
    for marker in (
        "dashboard_sales_source_failed",
        "dashboard_wholesale_source_failed",
        "dashboard_inventory_source_failed",
        "dashboard_low_stock_source_failed",
        "dashboard_today_sales_source_failed",
    ):
        if marker not in php:
            fail(f"missing dashboard fail-closed guard {marker}")
    if "function managementIntelligence()" not in js or "case'intelligence':b=managementIntelligence();break;" not in js:
        fail("Management Intelligence renderer/route is missing")
    if "loadReport().catch(err=>toast(err.message,true))" in js:
        fail("duplicate report failure toast handler remains")
    if "document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport());return null;" not in js:
        fail("single inline report error/retry handler is missing")
    css = files[ROOT + "assets/css/06-operational-guards.css"].decode("utf-8", "replace")
    desktop_marker = "/* Stockland desktop layering and report UI utilities; emitted by the Python CSS build. */"
    if css.count(desktop_marker) != 1 or "#stockland-app #slGlobalSearchResults" not in css:
        fail("desktop search/action CSS is missing or duplicated in the compiled source module")
    if "sl-desktop-title-action-layer-fix" in js:
        fail("runtime JavaScript CSS injection remains; desktop styling must ship in the CSS bundle")
    approval_marker = "/* Requisition approval routing — keep decisions readable on phones and desktop."
    if css.count(approval_marker) != 1:
        fail("responsive requisition approval CSS is missing or duplicated")
    pos_marker = "/* Retail POS mobile checkout — responsive atomic sale form."
    if css.count(pos_marker) != 1 or 'body[data-sl-view="pos"] #stockland-app #posForm' not in css:
        fail("responsive Retail POS checkout styles are missing or duplicated")

    modules = css_manifest.get("modules")
    if not isinstance(modules, list) or len(modules) != 6 or len(set(modules)) != 6:
        fail("CSS module manifest must list six unique source modules")
    chunks = []
    for module in modules:
        key = ROOT + "assets/css/" + str(module)
        if key not in files:
            fail(f"missing CSS source module {module}")
        chunks.append(files[key].decode("utf-8").strip())
    app_css = "\n\n".join(chunks) + "\n"
    if files[ROOT + "assets/app.css"].decode("utf-8") != app_css:
        fail("app.css differs from the ordered source modules")
    if files[ROOT + "assets/app.min.css"].decode("utf-8").strip() != normalize_css(app_css):
        fail("app.min.css does not match deterministic normalized source")
    if files[ROOT + "includes/class-stockland-activator.php"]:
        # The activator remains present; the build receipt separately records its hash parity.
        pass

    unexpected = []
    for name, data in files.items():
        for uri in EXTERNAL_URL.findall(data):
            if uri not in ALLOWED_URIS:
                unexpected.append((name, uri.decode("utf-8", "replace")))
    if unexpected:
        fail("unapproved fixed URL(s) remain: " + repr(unexpected[:10]))

    if b"Permission is hereby granted, free of charge" not in files[ROOT + "README.md"]:
        fail("Bootstrap MIT notice is missing from the installed guide")
    return {
        "release": EXPECTED_RELEASE,
        "schema_contract": EXPECTED_SCHEMA,
        "plugin_files": len(files),
        "php_files": sum(name.endswith(".php") for name in files),
        "css_modules": len(modules),
        "report_source_guards": php.count("report_source_query_failed"),
        "dashboard_source_guards": 5,
        "management_intelligence": True,
        "responsive_approval_layout": True,
        "hosting_domain_cleanup": True,
        "css_source_build_parity": True,
        "business_data_or_schema_mutated_by_build": False,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--baseline", type=Path, required=True)
    parser.add_argument("--package", type=Path, required=True)
    parser.add_argument("--sha256", type=Path, required=True)
    parser.add_argument("--build-receipt", type=Path, required=True)
    parser.add_argument("--release-receipt", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()

    baseline_names, baseline_files = load_archive(args.baseline)
    package_names, package_files = load_archive(args.package)
    actual_hash = sha256(args.package)
    actual_bytes = args.package.stat().st_size

    try:
        sidecar = args.sha256.read_text(encoding="utf-8").strip().split()
        if len(sidecar) < 1 or sidecar[0].lower() != actual_hash:
            fail("SHA-256 sidecar does not match final ZIP bytes")
    except OSError as exc:
        fail(f"cannot read SHA-256 sidecar: {exc}")

    try:
        build = json.loads(args.build_receipt.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        fail(f"cannot read cumulative 16.1.93 build receipt: {exc}")
    intermediate = build.get("final_package") if isinstance(build, dict) else None
    if not isinstance(intermediate, dict):
        fail("16.1.93 build receipt has no final_package attestation")
    if intermediate.get("files") != EXPECTED_FILES or intermediate.get("release") != EXPECTED_BASELINE_RELEASE:
        fail("intermediate cumulative build inventory/version does not match the expected 16.1.93 input")

    try:
        release = json.loads(args.release_receipt.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        fail(f"cannot read 16.1.94 release identity receipt: {exc}")
    if release.get("release") != EXPECTED_RELEASE or release.get("baseline_release") != EXPECTED_BASELINE_RELEASE:
        fail("release receipt does not identify the 16.1.93 → 16.1.94 cumulative update")
    if release.get("output_sha256") != actual_hash or release.get("output_bytes") != actual_bytes:
        fail("release receipt SHA-256/size does not match the final ZIP")
    if release.get("plugin_file_count") != EXPECTED_FILES or release.get("plugin_root") != ROOT:
        fail("release receipt package inventory/root does not match the final ZIP")
    if release.get("schema_contract") != {"core": 29, "accounting_procurement": 16, "hr_payroll": 4, "total": 49}:
        fail("release receipt schema contract does not match the 49-table contract")
    if not release.get("schema_activator_unchanged") or release.get("live_business_data_changed") is not False:
        fail("release receipt does not prove the no-schema/no-live-data mutation boundary")

    if baseline_names != package_names:
        fail("baseline/final package file order or inventory differs")
    changed = sorted(name for name in baseline_names if baseline_files[name] != package_files[name])
    if set(changed) != ALLOWED_CHANGED:
        fail("changed-file allowlist mismatch: " + repr(changed))
    if sorted(intermediate.get("changed_files", [])) != changed:
        fail("cumulative builder changed-file attestation differs from baseline-to-final source diff")
    if sorted(release.get("changed_files", [])) != sorted([
        ROOT + "README.md", ROOT + "RELEASE_MANIFEST.json", ROOT + "assets/css/modules.json",
        ROOT + "includes/class-stockland-frontend.php", ROOT + "stockland-inventory.php",
        ROOT + "assets/css/06-operational-guards.css", ROOT + "assets/app.css", ROOT + "assets/app.min.css"
    ]):
        fail("release finalizer changed-file allowlist is unexpected")
    if baseline_files[ROOT + "includes/class-stockland-activator.php"] != package_files[ROOT + "includes/class-stockland-activator.php"]:
        fail("schema activator changed from baseline")

    package_checks = inspect_payload(package_files)
    receipt = {
        "result": "PASS",
        "baseline": {
            "path": args.baseline.name,
            "sha256": sha256(args.baseline),
            "bytes": args.baseline.stat().st_size,
        },
        "final_package": {
            "path": args.package.name,
            "sha256": actual_hash,
            "bytes": actual_bytes,
            "files": len(package_names),
            "single_plugin_root": ROOT,
            "changed_files": changed,
            "schema_activator_unchanged": True,
        },
        "checks": package_checks,
    }
    args.output.write_text(json.dumps(receipt, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(receipt, indent=2))


if __name__ == "__main__":
    main()
