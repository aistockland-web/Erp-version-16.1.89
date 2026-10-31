#!/usr/bin/env python3
"""Exact-package PHPCS/WPCS coverage and security/database triage for HRX 16.3.0."""
from __future__ import annotations
import json, re, sys
from pathlib import Path

if len(sys.argv) != 3:
    raise SystemExit("usage: test-phpcs-hrx-review.py <extracted-plugin-root> <phpcs-results.json>")
root=Path(sys.argv[1]).resolve()
report_path=Path(sys.argv[2]).resolve()
if not root.is_dir() or not report_path.is_file():
    raise SystemExit("FAIL extracted installer or PHPCS report is missing")
report=json.loads(report_path.read_text(encoding="utf-8"))
expected={p.relative_to(root).as_posix() for p in root.rglob("*.php")}
reported=set()
messages=[]
for path,info in (report.get("files") or {}).items():
    normalized=path.replace("\\","/")
    marker="/stockland-erp-app/"
    relative=normalized.split(marker,1)[1] if marker in normalized else Path(path).name
    reported.add(relative)
    for item in info.get("messages",[]):
        messages.append({
            "rule":str(item.get("source","")),
            "path":relative,
            "line":int(item.get("line",0)),
            "type":str(item.get("type","")),
            "message":str(item.get("message","")),
        })
missing=sorted(expected-reported)
extra=sorted(reported-expected)
if missing or extra or len(reported)!=len(expected):
    print(f"FAIL PHPCS source coverage mismatch: expected={len(expected)} reported={len(reported)} missing={missing[:20]} extra={extra[:20]}")
    raise SystemExit(2)
print(f"PASS PHPCS/WPCS processed all {len(expected)} packaged PHP files")

roles=root/"includes/class-stockland-roles.php"
roles_source=roles.read_text(encoding="utf-8") if roles.is_file() else ""
cap_messages=[m for m in messages if m["rule"]=="WordPress.WP.Capabilities.Unknown"]
capabilities=set(re.findall(r'unknown capability "([^"]+)"',"\n".join(m["message"] for m in cap_messages),flags=re.I))
missing_caps=sorted(x for x in capabilities if x not in roles_source)
if not roles_source or missing_caps:
    print("FAIL unregistered custom capabilities: "+", ".join(missing_caps or ["canonical role registry missing"]))
    raise SystemExit(1)
print(f"PASS all {len(capabilities)} custom capability identifiers map to the canonical role registry")

# Only the exact, previously reviewed wholesale query findings are grandfathered.
# HRX code paths have no blanket exclusions. Any new security/SQL finding blocks.
reviewed={
    ("WordPress.DB.PreparedSQL.NotPrepared","includes/rest/class-rest-wholesale.php",118),
    ("WordPress.DB.PreparedSQL.NotPrepared","includes/rest/class-rest-wholesale.php",125),
    ("WordPress.DB.DirectDatabaseQuery.DirectQuery","includes/rest/class-rest-wholesale.php",125),
    ("WordPress.DB.DirectDatabaseQuery.NoCaching","includes/rest/class-rest-wholesale.php",125),
}
security_db=[m for m in messages if m["rule"].startswith(("WordPress.Security.","WordPress.DB."))]
unreviewed=[m for m in security_db if (m["rule"],m["path"],m["line"]) not in reviewed]
if unreviewed:
    for m in unreviewed:
        print(f"FAIL unreviewed {m['rule']} {m['path']}:{m['line']} {m['message']}")
    raise SystemExit(1)
for m in security_db:
    print(f"REVIEWED {m['rule']} {m['path']}:{m['line']}")
print(f"PASS {len(security_db)} security/database findings are either absent or at exact reviewed legacy locations")
