#!/usr/bin/env python3
"""Require reviewed HR/wholesale PHPCS signals to be resolved in the candidate."""
from __future__ import annotations
import json
import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit("usage: check-remediation-phpcs.py <phpcs-results.json>")
path = Path(sys.argv[1])
if not path.is_file():
    raise SystemExit("FAIL PHPCS JSON report missing")
report = json.loads(path.read_text(encoding="utf-8"))
watched = {
    "WordPress.Security.EscapeOutput.ExceptionNotEscaped",
    "WordPress.DB.PreparedSQL.NotPrepared",
    "WordPress.DB.DirectDatabaseQuery.DirectQuery",
    "WordPress.DB.DirectDatabaseQuery.NoCaching",
}
target_paths = ("includes/rest/class-rest-hr.php", "includes/rest/class-rest-wholesale.php")
remaining = []
for filename, info in report.get("files", {}).items():
    normalized = filename.replace("\\", "/")
    if not any(normalized.endswith(suffix) for suffix in target_paths):
        continue
    for item in info.get("messages", []):
        if item.get("source") in watched:
            remaining.append((item.get("source"), normalized, item.get("line"), item.get("message")))
if remaining:
    for rule, filename, line, message in remaining:
        print(f"FAIL unresolved {rule} at {filename}:{line}: {message}")
    raise SystemExit(1)
totals = report.get("totals", {})
print("PASS reviewed HR/wholesale security/database PHPCS signals are resolved or narrowly suppressed")
print(f"Candidate WPCS residual baseline: {totals.get('errors', 0)} errors, {totals.get('warnings', 0)} warnings, {totals.get('fixable', 0)} fixable entries")
