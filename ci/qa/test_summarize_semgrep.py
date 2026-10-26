#!/usr/bin/env python3
"""Regression tests for fail-closed Semgrep target coverage and severity handling."""
from __future__ import annotations

import json
import subprocess
import sys
import tempfile
from pathlib import Path

SCRIPT = Path(__file__).with_name("summarize-semgrep.py")


def run_case(report: dict, expected: int) -> subprocess.CompletedProcess[str]:
    with tempfile.TemporaryDirectory() as temp:
        path = Path(temp) / "semgrep.json"
        path.write_text(json.dumps(report), encoding="utf-8")
        return subprocess.run(
            [sys.executable, str(SCRIPT), str(path), "--expected-targets", str(expected)],
            capture_output=True,
            text=True,
            check=False,
        )


def require(condition: bool, label: str) -> None:
    if not condition:
        raise SystemExit("FAIL: " + label)
    print("PASS: " + label)


def main() -> None:
    clean = {"paths": {"scanned": ["a.php", "b.php"]}, "results": []}
    result = run_case(clean, 2)
    require(result.returncode == 0 and "PASS:" in result.stdout, "matching target count passes")

    result = run_case(clean, 3)
    require(result.returncode == 2 and "did not scan every PHP file" in result.stdout,
            "target-count mismatch fails closed")

    critical = {
        "paths": {"scanned": ["a.php"]},
        "results": [{"check_id": "test.critical", "path": "a.php",
                     "extra": {"severity": "ERROR", "message": "synthetic finding"}}],
    }
    result = run_case(critical, 1)
    require(result.returncode == 1 and "ERROR/CRITICAL" in result.stdout,
            "ERROR severity blocks despite complete coverage")

    malformed_coverage = {"paths": {"scanned": "not-a-list"}, "results": []}
    result = run_case(malformed_coverage, 1)
    require(result.returncode == 2 and "no valid paths.scanned list" in result.stdout,
            "malformed scan coverage fails closed")


if __name__ == "__main__":
    main()
