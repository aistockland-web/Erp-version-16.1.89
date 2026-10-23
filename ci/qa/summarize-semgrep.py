#!/usr/bin/env python3
"""Summarize Semgrep JSON and fail on skipped source coverage or ERROR findings."""
from __future__ import annotations
import argparse, json, sys
from collections import Counter
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument("json_report", help="Semgrep JSON report generated from the exact install ZIP")
parser.add_argument("--expected-targets", type=int, required=True, help="Number of PHP source files extracted from that ZIP")
args = parser.parse_args()

path = Path(args.json_report)
data = json.loads(path.read_text(encoding="utf-8"))
results = data.get("results", [])
paths = data.get("paths") or {}
scanned = paths.get("scanned") or []
if not isinstance(scanned, list):
    print("FAIL: Semgrep JSON has no valid paths.scanned list.")
    sys.exit(2)
target_count = len(scanned)
print(f"Semgrep targets scanned: {target_count}; expected packaged PHP files: {args.expected_targets}")
coverage_failed = args.expected_targets < 1 or target_count != args.expected_targets
if coverage_failed:
    print("FAIL: Semgrep did not scan every PHP file extracted from the exact install ZIP.")
counts = Counter(str((r.get("extra") or {}).get("severity", "UNKNOWN")).upper() for r in results)
print("Semgrep findings: " + str(len(results)))
for level in ("ERROR", "WARNING", "INFO", "UNKNOWN"):
    print(f"{level}: {counts.get(level, 0)}")
for item in results[:80]:
    extra = item.get("extra") or {}
    pos = item.get("start") or {}
    print(f"- {str(extra.get('severity','UNKNOWN')).upper()} {item.get('check_id')} {item.get('path')}:{pos.get('line','?')} {extra.get('message','')}")
errors = [r for r in results if str((r.get("extra") or {}).get("severity", "")).upper() in ("ERROR", "CRITICAL")]
if errors:
    print("FAIL: ERROR/CRITICAL Semgrep findings require triage.")
    sys.exit(1)
if coverage_failed:
    sys.exit(2)
print("PASS: all packaged PHP files were scanned; no ERROR/CRITICAL Semgrep findings. WARN/INFO findings remain visible for audit triage.")
