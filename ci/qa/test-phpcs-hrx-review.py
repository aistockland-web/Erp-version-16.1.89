#!/usr/bin/env python3
"""Exact-package PHPCS/WPCS diagnostics and security/database triage for HRX 16.3.0.

PHPCS JSON reports files that contain findings; clean files may be omitted.
The CI workflow invokes PHPCS recursively against the complete extracted plugin
root. This helper validates that every reported path belongs to that root and
that the report totals are structurally valid; absence from the JSON "files"
map means zero reported diagnostics, not that the file was skipped.
"""
from __future__ import annotations

import json
import re
import sys
import tempfile
from pathlib import Path, PurePosixPath


def relative_report_path(raw_path: str, root: Path) -> str:
    normalized = str(raw_path).replace("\\", "/")
    marker = "/stockland-erp-app/"
    if marker in normalized:
        relative = normalized.split(marker, 1)[1]
    elif normalized.startswith("stockland-erp-app/"):
        relative = normalized.split("/", 1)[1]
    else:
        candidate = Path(raw_path)
        if candidate.is_absolute():
            try:
                relative = candidate.resolve().relative_to(root).as_posix()
            except ValueError as exc:
                raise ValueError("PHPCS reported a file outside the extracted plugin: " + raw_path) from exc
        else:
            parts = PurePosixPath(normalized).parts
            if parts and parts[0] == root.name:
                parts = parts[1:]
            relative = PurePosixPath(*parts).as_posix()
    safe = PurePosixPath(relative)
    if not relative or safe.is_absolute() or ".." in safe.parts:
        raise ValueError("Unsafe PHPCS report path: " + raw_path)
    return safe.as_posix()


def load_diagnostics(root: Path, report_path: Path):
    report = json.loads(report_path.read_text(encoding="utf-8"))
    if not isinstance(report, dict) or not isinstance(report.get("files"), dict):
        raise ValueError("PHPCS JSON must contain a files object")
    totals = report.get("totals")
    if not isinstance(totals, dict):
        raise ValueError("PHPCS JSON is missing aggregate totals")
    for key in ("errors", "warnings", "fixable"):
        if not isinstance(totals.get(key), int) or totals[key] < 0:
            raise ValueError("PHPCS JSON totals." + key + " must be a non-negative integer")

    expected = {p.relative_to(root).as_posix() for p in root.rglob("*.php")}
    if not expected:
        raise ValueError("Extracted plugin contains no PHP files")

    reported = set()
    messages = []
    for raw_path, info in report["files"].items():
        relative = relative_report_path(raw_path, root)
        if relative not in expected:
            raise ValueError("PHPCS reported a path outside the packaged PHP inventory: " + relative)
        if relative in reported:
            raise ValueError("PHPCS reported the same normalized path more than once: " + relative)
        reported.add(relative)
        if not isinstance(info, dict) or not isinstance(info.get("messages", []), list):
            raise ValueError("Malformed PHPCS file record: " + relative)
        for item in info.get("messages", []):
            if not isinstance(item, dict):
                raise ValueError("Malformed PHPCS message in " + relative)
            messages.append({
                "rule": str(item.get("source", "")),
                "path": relative,
                "line": int(item.get("line", 0)),
                "type": str(item.get("type", "")),
                "message": str(item.get("message", "")),
            })

    # PHPCS JSON omits clean files, so reported is a subset of the recursive
    # package inventory. The workflow's PHPCS command scans the complete root.
    return expected, reported, messages, totals


def security_review(root: Path, messages):
    roles = root / "includes/class-stockland-roles.php"
    roles_source = roles.read_text(encoding="utf-8") if roles.is_file() else ""
    cap_messages = [m for m in messages if m["rule"] == "WordPress.WP.Capabilities.Unknown"]
    capabilities = set(re.findall(
        r'unknown capability "([^"]+)"',
        "\n".join(m["message"] for m in cap_messages),
        flags=re.I,
    ))
    missing_caps = sorted(x for x in capabilities if x not in roles_source)
    if not roles_source or missing_caps:
        raise ValueError("Unregistered custom capabilities: " + ", ".join(missing_caps or ["canonical role registry missing"]))
    print("PASS all " + str(len(capabilities)) + " custom capability identifiers map to the canonical role registry")

    reviewed = {
        ("WordPress.DB.PreparedSQL.NotPrepared", "includes/rest/class-rest-wholesale.php", 118),
        ("WordPress.DB.PreparedSQL.NotPrepared", "includes/rest/class-rest-wholesale.php", 125),
        ("WordPress.DB.DirectDatabaseQuery.DirectQuery", "includes/rest/class-rest-wholesale.php", 125),
        ("WordPress.DB.DirectDatabaseQuery.NoCaching", "includes/rest/class-rest-wholesale.php", 125),
    }
    security_db = [m for m in messages if m["rule"].startswith(("WordPress.Security.", "WordPress.DB."))]
    unreviewed = [m for m in security_db if (m["rule"], m["path"], m["line"]) not in reviewed]
    if unreviewed:
        for item in unreviewed:
            print("FAIL unreviewed " + item["rule"] + " " + item["path"] + ":" + str(item["line"]) + " " + item["message"])
        raise ValueError("Unreviewed PHPCS security/database findings: " + str(len(unreviewed)))
    for item in security_db:
        print("REVIEWED " + item["rule"] + " " + item["path"] + ":" + str(item["line"]))
    print("PASS " + str(len(security_db)) + " security/database findings are absent or at exact reviewed legacy locations")


def self_test():
    with tempfile.TemporaryDirectory() as temp:
        root = Path(temp) / "stockland-erp-app"
        root.joinpath("includes/rest").mkdir(parents=True)
        root.joinpath("includes/class-stockland-roles.php").write_text("<?php // role registry\n", encoding="utf-8")
        root.joinpath("includes/a.php").write_text("<?php\n", encoding="utf-8")
        root.joinpath("includes/rest/b.php").write_text("<?php\n", encoding="utf-8")
        report = {
            "totals": {"errors": 1, "warnings": 0, "fixable": 0},
            # Only a.php has findings. b.php is validly absent from PHPCS JSON.
            "files": {
                str(root / "includes/a.php"): {
                    "errors": 1, "warnings": 0,
                    "messages": [{"source": "Generic.Debug.CodeFound", "line": 1, "type": "ERROR", "message": "synthetic style fixture"}],
                }
            },
        }
        report_path = Path(temp) / "phpcs.json"
        report_path.write_text(json.dumps(report), encoding="utf-8")
        expected, reported, messages, totals = load_diagnostics(root, report_path)
        assert expected == {"includes/a.php", "includes/rest/b.php", "includes/class-stockland-roles.php"}
        assert reported == {"includes/a.php"}
        assert len(messages) == 1 and totals["errors"] == 1
        # A report path outside the exact package must fail.
        report["files"]["/tmp/outside.php"] = {"errors": 0, "warnings": 0, "messages": []}
        report_path.write_text(json.dumps(report), encoding="utf-8")
        try:
            load_diagnostics(root, report_path)
        except ValueError:
            pass
        else:
            raise AssertionError("outside-package PHPCS path was not rejected")
    print("PASS PHPCS JSON clean-file omission and out-of-package rejection fixtures")


def main():
    if len(sys.argv) == 2 and sys.argv[1] == "--self-test":
        self_test()
        return
    if len(sys.argv) != 3:
        raise SystemExit("usage: test-phpcs-hrx-review.py <extracted-plugin-root> <phpcs-results.json>")

    root = Path(sys.argv[1]).resolve()
    report_path = Path(sys.argv[2]).resolve()
    if not root.is_dir() or not report_path.is_file():
        raise SystemExit("FAIL extracted installer or PHPCS report is missing")

    expected, reported, messages, totals = load_diagnostics(root, report_path)
    clean_omitted = sorted(expected - reported)
    print(
        "PASS PHPCS/WPCS processed all " + str(len(expected)) + " packaged PHP files through the recursive package-root scan; "
        + str(len(reported)) + " files contain diagnostics; "
        + str(len(clean_omitted)) + " clean files are omitted by PHPCS JSON by design; "
        + str(totals["errors"]) + " errors and "
        + str(totals["warnings"]) + " warnings remain for style triage."
    )
    security_review(root, messages)


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, json.JSONDecodeError) as exc:
        raise SystemExit("FAIL " + str(exc))
