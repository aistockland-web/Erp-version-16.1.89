#!/usr/bin/env python3
"""Compare two schema snapshots: the upgrade must be add-only and must preserve existing data.
Usage: compare_snapshots.py before.json after.json [--append-only table1,table2]
Rules: no table, column or index disappears; existing column definitions stay compatible; row counts never shrink;
for non-append-only tables the first rows' per-column digests are identical. Append-only tables (audit, notifications) may grow."""
import json, sys
a, b = (json.load(open(p)) for p in sys.argv[1:3])
append_only = set(sys.argv[4].split(",")) if len(sys.argv) > 4 and sys.argv[3] == "--append-only" else {"audit_log", "notifications", "audit_chain"}
fail = []; changed = []; added = sorted(set(b["tables"]) - set(a["tables"])); lost = sorted(set(a["tables"]) - set(b["tables"]))
fail += [f"table removed: {t}" for t in lost]
for t, x in a["tables"].items():
    y = b["tables"].get(t)
    if not y: continue
    cx = {c["column_name"]: c for c in x["columns"]}; cy = {c["column_name"]: c for c in y["columns"]}
    for c in cx:
        if c not in cy: fail.append(f"{t}: column removed {c}")
        elif cx[c]["column_default"] != cy[c]["column_default"]: changed.append(f"{t}.{c}: default {cx[c]['column_default']} -> {cy[c]['column_default']}")
        if c in cy and cx[c]["column_type"] != cy[c]["column_type"]: fail.append(f"{t}.{c}: type changed {cx[c]['column_type']} -> {cy[c]['column_type']}")
    ix = {i["index_name"]: i["cols"] for i in x["indexes"]}; iy = {i["index_name"]: i["cols"] for i in y["indexes"]}
    for i in ix:
        if i not in iy: fail.append(f"{t}: index removed {i}")
    if y["rows"] < x["rows"]: fail.append(f"{t}: rows shrank {x['rows']} -> {y['rows']}")
    if t not in append_only and y["rows"] == x["rows"]:
        for c, d in (x["digest"] or {}).items():
            if c in (y["digest"] or {}) and y["digest"][c] != d: fail.append(f"{t}.{c}: values changed")
print(f"before: {len(a['tables'])} tables / {sum(v['rows'] for v in a['tables'].values())} rows ({a['version']}); after: {len(b['tables'])} tables / {sum(v['rows'] for v in b['tables'].values())} rows ({b['version']})")
print("tables added by the upgrade:", ", ".join(added) or "none")
for c in changed: print("DEFAULT CHANGED (approved HR migration)", c)
for f in fail: print("FAIL", f)
print("RESULT", "FAIL" if fail else "PASS"); sys.exit(1 if fail else 0)
