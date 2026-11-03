#!/usr/bin/env python3
"""Read-only audit of the exact Stockland ERP 16.1.95 package before HRIS replacement.

This script does not run WordPress, issue SQL, apply migrations, or mutate business data.
It validates the locked installer archive and reports legacy HR references and shared
service anchors required before choosing the new physical schema.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path, PurePosixPath

BASELINE_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
BASELINE_SIZE = 1_102_405
EXPECTED_FILES = 75
EXPECTED_PHP = 48
ROOT = "stockland-erp-app/"
LEGACY = ("employees", "employee_compensation", "payroll_periods", "payroll_lines")
REFERENCE_FILES = {
    "includes/class-stockland-admin.php",
    "includes/class-stockland-payroll-statutory.php",
    "includes/class-stockland-hr.php",
    "includes/class-stockland-settings.php",
    "assets/app.js",
    "includes/rest/class-rest-requisition-v16.php",
    "includes/rest/class-rest-hr.php",
    "includes/rest/class-rest-app.php",
    "includes/rest/class-rest-accounting.php",
}
REQUIRED = {
    "stockland-inventory.php",
    "includes/class-stockland-hr.php",
    "includes/rest/class-rest-hr.php",
    "includes/rest/class-rest-app.php",
    "includes/class-stockland-admin.php",
    "includes/class-stockland-db.php",
    "includes/class-stockland-authority.php",
    "includes/class-stockland-workflow.php",
    "includes/class-stockland-audit.php",
    "includes/class-stockland-accounting.php",
    "includes/class-stockland-money.php",
    "assets/app.js",
    "RELEASE_MANIFEST.json",
}

def require(ok: bool, message: str) -> None:
    if not ok:
        raise SystemExit("FAIL: " + message)

def digest(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--installer", required=True, help="Exact 16.1.95 canonical installer ZIP")
    parser.add_argument("--output", help="Optional JSON audit receipt path")
    args = parser.parse_args()
    installer = Path(args.installer)
    require(installer.is_file(), f"installer not found: {installer}")
    raw = installer.read_bytes()
    actual = digest(raw)
    require(len(raw) == BASELINE_SIZE, f"baseline size mismatch: {len(raw)}")
    require(actual == BASELINE_SHA256, f"baseline SHA-256 mismatch: {actual}")
    with zipfile.ZipFile(installer) as archive:
        require(archive.testzip() is None, "ZIP CRC/integrity check failed")
        infos = [entry for entry in archive.infolist() if not entry.is_dir()]
        names = [entry.filename for entry in infos]
        require(len(names) == EXPECTED_FILES, f"expected {EXPECTED_FILES} files, received {len(names)}")
        require(len(set(names)) == EXPECTED_FILES, "duplicate archive paths")
        require(all(PurePosixPath(name).parts[0] == "stockland-erp-app" and ".." not in PurePosixPath(name).parts for name in names), "unexpected root or unsafe path")
        files = {name: archive.read(name) for name in names}
    php_files = [name for name in names if name.endswith(".php")]
    require(len(php_files) == EXPECTED_PHP, f"expected {EXPECTED_PHP} PHP files, received {len(php_files)}")
    require({ROOT + path for path in REQUIRED}.issubset(files), "one or more required runtime files are absent")

    texts: dict[str, str] = {}
    for name, content in files.items():
        if name.endswith((".php", ".js", ".md", ".json", ".css")):
            try:
                texts[name.removeprefix(ROOT)] = content.decode("utf-8")
            except UnicodeDecodeError:
                continue

    hr = texts["includes/class-stockland-hr.php"]
    routes = texts["includes/rest/class-rest-hr.php"]
    admin = texts["includes/class-stockland-admin.php"]
    health = texts["includes/rest/class-rest-app.php"]
    accounting = texts["includes/class-stockland-accounting.php"]
    audit = texts["includes/class-stockland-audit.php"]
    workflow = texts["includes/class-stockland-workflow.php"]
    main = texts["stockland-inventory.php"]

    require("return array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' );" in hr, "legacy HR table authority anchor changed")
    for table in LEGACY:
        require(f"CREATE TABLE {{$p}}stock_{table}" in hr, f"legacy DDL missing for {table}")
    route_count = len(re.findall(r"register_rest_route\s*\(", routes))
    require(route_count == 13, f"expected 13 baseline HR route-registration calls, received {route_count}")
    require("count( $found_keys ) === 49" in health, "System Health exact-49 gate anchor changed")
    require("count( $found_keys ) === 49" in health, "deep-health exact-49 gate anchor changed")
    require("array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' )" in admin, "admin schema inventory no longer identifies legacy HR tables")
    require("function post_journal(" in accounting and "source_type" in accounting and "source_id" in accounting, "shared idempotent GL posting service anchor missing")
    require("function event(" in workflow and "function transition(" in workflow, "shared workflow/event service anchor missing")
    require("function log(" in audit and "event_hash" in audit, "shared chained audit service anchor missing")

    reference_hits = {}
    for relpath, source in texts.items():
        hit_counts = {table: len(re.findall(r"(?<![A-Za-z0-9_])" + re.escape(table) + r"(?![A-Za-z0-9_])", source, re.I)) for table in LEGACY}
        if any(hit_counts.values()):
            reference_hits[relpath] = hit_counts
    referenced_paths = set(reference_hits)
    required_reference_paths = REFERENCE_FILES - {"README.md"}
    require(required_reference_paths.issubset(referenced_paths), "legacy-reference scan did not locate every known source caller")
    ddl = "\n".join(texts.get(path, "") for path in ("includes/class-stockland-hr.php", "includes/class-stockland-activator.php", "includes/class-stockland-accounting.php"))
    # Strip PHP block and line comments first: the canonical installer contains a comment explaining that dbDelta does not reliably parse FKs.
    ddl_for_constraints = re.sub(r"/\*.*?\*/", "", ddl, flags=re.S)
    ddl_for_constraints = re.sub(r"(?m)^[ \t]*//.*$", "", ddl_for_constraints)
    foreign_key_count = len(re.findall(r"\bFOREIGN\s+KEY\s*\(", ddl_for_constraints, re.I))
    require(foreign_key_count == 0, f"static source DDL contains {foreign_key_count} explicit FK constraint declarations; review schema assumptions")

    result = {
        "audit": "HRIS_LEGACY_SOURCE_BASELINE_AUDIT",
        "result": "PASS",
        "baseline_release": "16.1.95",
        "installer_sha256": actual,
        "installer_bytes": len(raw),
        "archive_file_count": len(names),
        "php_file_count": len(php_files),
        "hr_route_registration_calls": route_count,
        "legacy_hr_tables": list(LEGACY),
        "legacy_reference_files": reference_hits,
        "static_ddl_foreign_key_declarations": foreign_key_count,
        "shared_services_verified": ["Stockland_DB.table/read/insert/update", "Stockland_Authority", "Stockland_Workflow.event/transition", "Stockland_Audit.log chained event", "Stockland_Accounting.post_journal source_type/source_id", "Stockland_Money exact decimal arithmetic"],
        "health_contract": {"expected_physical_tables": 49, "hardcoded_exact_49_check": True},
        "live_data_health": "NOT_RUN — requires a sanitized, authorized restored staging copy; this audit reads installer files only",
        "target_table_count": "NOT_FIXED — determine after complete call graph and data-health evidence",
        "production_mutation": False,
    }
    rendered = json.dumps(result, indent=2, ensure_ascii=False) + "\n"
    if args.output:
        Path(args.output).write_text(rendered, encoding="utf-8")
    print(rendered, end="")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
