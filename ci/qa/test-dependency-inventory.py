#!/usr/bin/env python3
"""Regression tests for dependency inventory coverage classification."""
from __future__ import annotations
import json, subprocess, sys, tempfile
from pathlib import Path

SCRIPT = Path(__file__).with_name("dependency-inventory.py")
passed = failed = 0

def check(label: str, condition: bool) -> None:
    global passed, failed
    if condition:
        passed += 1
        print(f"PASS {label}")
    else:
        failed += 1
        print(f"FAIL {label}")

def run_case(files: dict[str, str], dirs: tuple[str, ...] = ()) -> tuple[int, dict]:
    with tempfile.TemporaryDirectory(prefix="stockland-dep-test-") as temp:
        root = Path(temp) / "stockland-erp-app"
        root.mkdir()
        for directory in dirs:
            (root / directory).mkdir(parents=True, exist_ok=True)
        for relative, content in files.items():
            path = root / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(content, encoding="utf-8")
        (root / "RELEASE_MANIFEST.json").write_text(json.dumps({
            "release":"16.1.89",
            "frontend":{"bootstrap_version":"5.3.8","bootstrap_sri_sha384":"abc","bootstrap_source_git_blob_sha1":"def"}
        }), encoding="utf-8")
        assets = root / "assets"
        assets.mkdir(exist_ok=True)
        (assets / "bootstrap.min.css").write_text("/* Bootstrap CSS - MIT License */\n.x{}", encoding="utf-8")
        report = Path(temp) / "report.json"
        proc = subprocess.run([sys.executable, str(SCRIPT), str(root), str(report)], text=True, capture_output=True, check=False)
        return proc.returncode, json.loads(report.read_text(encoding="utf-8"))

rc, data = run_case({})
check("No package manager manifests is PASS/N-A when no vendored runtime exists", rc == 0 and data["inventory_status"] == "PASS" and data["dependency_model"] == "NO_PACKAGE_MANAGER_ECOSYSTEM_IN_PLUGIN_ARCHIVE")
rc, data = run_case({"composer.json": '{"require":{"vendor/pkg":"^1"}}'})
check("Composer manifest without composer.lock remains blocked", rc != 0 and data["inventory_status"] == "BLOCKED")
rc, data = run_case({"package.json": '{"dependencies":{"example":"1.0.0"}}', "package-lock.json": '{"lockfileVersion":3}'})
check("Package manifest with matching lockfile is accepted for downstream dependency review", rc == 0 and data["inventory_status"] == "PASS")
rc, data = run_case({}, ("vendor/example/pkg",))
check("Vendored runtime without lockfile remains blocked", rc != 0 and data["inventory_status"] == "BLOCKED")
rc, data = run_case({"composer.lock": '{"packages":[]}'})
check("Orphan lockfile remains blocked", rc != 0 and data["inventory_status"] == "BLOCKED")
print(f"DEPENDENCY INVENTORY POLICY: {passed} passed; {failed} failed")
raise SystemExit(1 if failed else 0)
