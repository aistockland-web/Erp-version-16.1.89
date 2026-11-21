#!/usr/bin/env python3
"""Strictly compare physical schema and pre-existing row identity/value fingerprints."""
import json
import sys

APPEND_ONLY_TABLES = {"stock_audit_logs", "stock_audit_chain", "stock_workflow_events"}
# Rows the product itself ships after the Owner-approved HR schema exists: the Owner-approved payroll rule set (ET-2025-AMEND-1395) and its audit event. At most this many may be added.
SHIPPED_ROW_ALLOWANCE = {"stock_hr_rule_sets": 1, "stock_hr_payroll_events": 1}
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
        elif table in SHIPPED_ROW_ALLOWANCE and 0 < new_rows - old_rows <= SHIPPED_ROW_ALLOWANCE[table]:
            notices.append(f"{table}: shipped Owner-approved payroll rule rows added {old_rows} -> {new_rows}")
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

        # MySQL drivers may return INFORMATION_SCHEMA field labels in
        # uppercase (COLUMN_NAME) while MariaDB commonly returns lowercase.
        # Normalize metadata keys so the same physical schema snapshot compares
        # identically across the supported database engines.
        def normalized_metadata(items):
            return [
                {str(key).lower(): value for key, value in item.items()}
                for item in (items or [])
            ]

        old_columns = {
            column["column_name"]: column
            for column in normalized_metadata(old.get("columns"))
            if column.get("column_name")
        }
        new_columns = {
            column["column_name"]: column
            for column in normalized_metadata(new.get("columns"))
            if column.get("column_name")
        }
        if len(old_columns) != len(old.get("columns") or []) or len(new_columns) != len(new.get("columns") or []):
            failures.append(f"{table}: schema snapshot contains a column entry without column_name")
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
                    and current.get("column_default") in (None, "NULL")
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
                for item in normalized_metadata(items)
                if item.get("index_name")
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
                for item in normalized_metadata(items)
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
