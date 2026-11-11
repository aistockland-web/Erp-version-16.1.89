#!/usr/bin/env python3
"""Assert that a 16.2.13 -> 16.2.15 QA upgrade does not remove schema objects."""
from __future__ import annotations
import argparse
import json
from pathlib import Path
import sys


def load(path: Path) -> dict:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise SystemExit(f"Cannot read schema fingerprint {path}: {exc}") from exc
    if data.get("fingerprint_type") != "stockland-isolated-qa-schema-fingerprint-v1":
        raise SystemExit(f"Unexpected fingerprint schema in {path}")
    return data


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("before", type=Path)
    parser.add_argument("after", type=Path)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    before, after = load(args.before), load(args.after)
    old_tables, new_tables = before.get("tables", {}), after.get("tables", {})
    removed_tables = sorted(set(old_tables) - set(new_tables))
    removed_columns = []
    changed_columns = []
    removed_indexes = []
    for table, old in old_tables.items():
        new = new_tables.get(table)
        if new is None:
            continue
        new_cols = new.get("columns", {})
        for column, old_def in old.get("columns", {}).items():
            if column not in new_cols:
                removed_columns.append(f"{table}.{column}")
            elif old_def != new_cols[column]:
                changed_columns.append({
                    "object": f"{table}.{column}",
                    "before": old_def,
                    "after": new_cols[column],
                })
        new_indexes = new.get("indexes", {})
        for index_name, old_def in old.get("indexes", {}).items():
            if index_name not in new_indexes:
                removed_indexes.append(f"{table}.{index_name}")
            elif old_def != new_indexes[index_name]:
                removed_indexes.append(f"{table}.{index_name} (definition changed)")
    result = {
        "comparison_type": "stockland-schema-non-destructive-upgrade-v1",
        "before_plugin": before.get("plugin_version"),
        "after_plugin": after.get("plugin_version"),
        "before_database": before.get("database_server"),
        "after_database": after.get("database_server"),
        "tables_before": len(old_tables),
        "tables_after": len(new_tables),
        "tables_added": sorted(set(new_tables) - set(old_tables)),
        "tables_removed": removed_tables,
        "columns_removed": sorted(removed_columns),
        "indexes_removed_or_changed": sorted(removed_indexes),
        "column_definition_changes_for_review": changed_columns,
        "status": "PASS" if not (removed_tables or removed_columns or removed_indexes) else "FAIL",
        "schema_approval": "NOT_GRANTED_BY_AUTOMATED_COMPARISON",
        "row_count_observations": {
            table: {
                "before": old_tables[table].get("row_count"),
                "after": new_tables.get(table, {}).get("row_count"),
            }
            for table in sorted(set(old_tables) & set(new_tables))
            if old_tables[table].get("row_count") != new_tables.get(table, {}).get("row_count")
        },
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps(result, indent=2, sort_keys=True))
    return 0 if result["status"] == "PASS" else 1


if __name__ == "__main__":
    sys.exit(main())
