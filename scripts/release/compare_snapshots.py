#!/usr/bin/env python3
"""Strictly compare physical schema and pre-existing row identity/value fingerprints."""
import json
import sys

APPEND_ONLY_TABLES = {"stock_audit_logs", "stock_audit_chain", "stock_workflow_events"}
APPROVED_HR_RATE_DEFAULTS = {
    ("stock_payroll_lines", "income_tax_rate"),
    ("stock_payroll_lines", "employee_pension_rate"),
    ("stock_payroll_lines", "employer_pension_rate"),
}


def compare(before, after):
    failures = []
    notices = []
    old_tables = before["tables"]
    new_tables = after["tables"]
    added_tables = sorted(set(new_tables) - set(old_tables))
    removed_tables = sorted(set(old_tables) - set(new_tables))
    failures.extend(f"unapproved table added: {name}" for name in added_tables)
    failures.extend(f"table removed: {name}" for name in removed_tables)

    for table, old in old_tables.items():
        new = new_tables.get(table)
        if new is None:
            continue
        old_rows = int(old["rows"])
        new_rows = int(new["rows"])
        append_only = table in APPEND_ONLY_TABLES
        if new_rows < old_rows:
            failures.append(f"{table}: rows shrank {old_rows} -> {new_rows}")
        elif not append_only and new_rows != old_rows:
            failures.append(f"{table}: unexpected row-count change {old_rows} -> {new_rows}")
        elif append_only and new_rows > old_rows:
            notices.append(f"{table}: append-only rows increased {old_rows} -> {new_rows}")

        if not old.get("row_fingerprints_complete") or not new.get("row_fingerprints_complete"):
            failures.append(f"{table}: complete row fingerprints unavailable (limit={old.get('row_fingerprints_limit')})")
        else:
            old_fp = old.get("row_fingerprints") or {}
            new_fp = new.get("row_fingerprints") or {}
            for key, value_hash in old_fp.items():
                if key not in new_fp:
                    failures.append(f"{table}: pre-upgrade row identity missing ({key[:12]})")
                elif new_fp[key] != value_hash:
                    failures.append(f"{table}: pre-upgrade row changed ({key[:12]})")

        old_columns = {column["column_name"]: column for column in old["columns"]}
        new_columns = {column["column_name"]: column for column in new["columns"]}
        for column in sorted(set(old_columns) - set(new_columns)):
            failures.append(f"{table}: column removed {column}")
        for column in sorted(set(new_columns) - set(old_columns)):
            failures.append(f"{table}: unapproved column added {column}")
        for column in sorted(set(old_columns) & set(new_columns)):
            prior = old_columns[column]
            current = new_columns[column]
            if prior.get("column_type") != current.get("column_type"):
                failures.append(f"{table}.{column}: type changed {prior.get('column_type')} -> {current.get('column_type')}")
            for attribute in ("extra", "character_set_name", "collation_name"):
                if prior.get(attribute) != current.get(attribute):
                    failures.append(f"{table}.{column}: {attribute} changed {prior.get(attribute)} -> {current.get(attribute)}")
            nullable_changed = prior.get("is_nullable") != current.get("is_nullable")
            default_changed = prior.get("column_default") != current.get("column_default")
            if nullable_changed or default_changed:
                allowed = (
                    (table, column) in APPROVED_HR_RATE_DEFAULTS
                    and current.get("is_nullable") == "YES"
                    and current.get("column_default") is None
                )
                if allowed:
                    notices.append(f"approved HR neutral-default normalization: {table}.{column}")
                else:
                    failures.append(
                        f"{table}.{column}: unapproved null/default change "
                        f"({prior.get('is_nullable')}, {prior.get('column_default')}) -> "
                        f"({current.get('is_nullable')}, {current.get('column_default')})"
                    )

        def index_map(items):
            return {
                item["index_name"]: (item.get("non_unique"), item.get("index_type"), item.get("cols"))
                for item in items
            }

        old_indexes = index_map(old.get("indexes") or [])
        new_indexes = index_map(new.get("indexes") or [])
        if old_indexes != new_indexes:
            for name in sorted(set(old_indexes) - set(new_indexes)):
                failures.append(f"{table}: index removed {name}")
            for name in sorted(set(new_indexes) - set(old_indexes)):
                failures.append(f"{table}: unapproved index added {name}")
            for name in sorted(set(old_indexes) & set(new_indexes)):
                if old_indexes[name] != new_indexes[name]:
                    failures.append(f"{table}: index definition changed {name}")

        def foreign_key_set(items):
            return {
                (
                    item.get("constraint_name"),
                    item.get("column_name"),
                    item.get("referenced_table_name"),
                    item.get("referenced_column_name"),
                    int(item.get("ordinal_position") or 0),
                )
                for item in items
            }

        if foreign_key_set(old.get("foreign_keys") or []) != foreign_key_set(new.get("foreign_keys") or []):
            failures.append(f"{table}: foreign-key contract changed")

    return failures, notices, added_tables


def main():
    if len(sys.argv) != 3:
        print("Usage: compare_snapshots.py before.json after.json", file=sys.stderr)
        return 2
    with open(sys.argv[1], encoding="utf-8") as handle:
        before = json.load(handle)
    with open(sys.argv[2], encoding="utf-8") as handle:
        after = json.load(handle)
    failures, notices, added = compare(before, after)
    print(
        f"before: {len(before['tables'])} tables / "
        f"{sum(int(item['rows']) for item in before['tables'].values())} rows ({before.get('version', '')}); "
        f"after: {len(after['tables'])} tables / "
        f"{sum(int(item['rows']) for item in after['tables'].values())} rows ({after.get('version', '')})"
    )
    print("tables added by the upgrade:", ", ".join(added) or "none")
    for notice in notices:
        print("ALLOWED:", notice)
    for failure in failures:
        print("FAIL:", failure)
    print("RESULT", "FAIL" if failures else "PASS")
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
