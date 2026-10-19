#!/usr/bin/env python3
"""Summarize Semgrep JSON and fail on ERROR-severity findings."""
import json, sys
from collections import Counter
from pathlib import Path

path = Path(sys.argv[1])
data = json.loads(path.read_text(encoding="utf-8"))
results = data.get("results", [])
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
print("PASS: no ERROR/CRITICAL Semgrep findings; WARN/INFO findings remain visible for audit triage.")
