#!/usr/bin/env python3
"""Fail-closed gate proving legacy HR table structure is unchanged by an additive extension."""
from __future__ import annotations
import argparse
import re
import zipfile
from pathlib import Path

ROOT = "stockland-erp-app/"
HR_SOURCE = ROOT + "includes/class-stockland-hr.php"
ACTIVATOR = ROOT + "includes/class-stockland-activator.php"
LEGACY_TABLES = ("employees", "employee_compensation", "payroll_periods", "payroll_lines")

def read_files(path: Path) -> dict[str, bytes]:
    with zipfile.ZipFile(path) as archive:
        bad = archive.testzip()
        if bad:
            raise AssertionError(f"{path.name}: ZIP CRC failure at {bad}")
        names = [item.filename for item in archive.infolist() if not item.is_dir()]
        if len(names) != len(set(names)) or not all(n.startswith(ROOT) for n in names):
            raise AssertionError(f"{path.name}: invalid or duplicate plugin package paths")
        return {name: archive.read(name) for name in names}

def table_ddl(source: str, table: str) -> str:
    start = re.escape(f'$tables[] = "CREATE TABLE {{$p}}stock_{table} (')
    pattern = re.compile(start + r'[\s\S]*?\)\s*ENGINE=InnoDB\s*\{\$cc\};";')
    matches = list(pattern.finditer(source))
    if len(matches) != 1:
        raise AssertionError(f"Expected one fixed CREATE TABLE definition for stock_{table}, found {len(matches)}")
    return matches[0].group(0)

def required_columns_block(source: str) -> str:
    pattern = re.compile(r"public static function required_columns\(\)\s*\{[\s\S]*?\n\t\}")
    matches = list(pattern.finditer(source))
    if len(matches) != 1:
        raise AssertionError(f"Expected one required_columns() function, found {len(matches)}")
    return matches[0].group(0)

SAFE_PENSION_DEFAULT_REWRITES = (
    ("'employee_pension_rate'    => 'DECIMAL(6,4) NOT NULL DEFAULT 0.0700 AFTER pensionable_salary',",
     "'employee_pension_rate'    => 'DECIMAL(6,4) NULL DEFAULT NULL AFTER pensionable_salary',"),
    ("'employer_pension_rate'    => 'DECIMAL(6,4) NOT NULL DEFAULT 0.1100 AFTER employee_pension_rate',",
     "'employer_pension_rate'    => 'DECIMAL(6,4) NULL DEFAULT NULL AFTER employee_pension_rate',"),
    ("employee_pension_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0700,",
     "employee_pension_rate DECIMAL(6,4) NULL DEFAULT NULL,"),
    ("employer_pension_rate DECIMAL(6,4) NOT NULL DEFAULT 0.1100,",
     "employer_pension_rate DECIMAL(6,4) NULL DEFAULT NULL,"),
)


def normalize_pension_rate_defaults(source: str, *, candidate: bool) -> str:
    """Allow only the four exact nullable/NULL changes required to avoid historical backfill."""
    for old, new in SAFE_PENSION_DEFAULT_REWRITES:
        old_count = source.count(old)
        new_count = source.count(new)
        if candidate:
            if old_count != 0 or new_count != 1:
                raise AssertionError("Candidate must use exactly one nullable pension definition: " + new)
        else:
            if old_count != 1 or new_count != 0:
                raise AssertionError("Protected baseline pension definition differs from the reviewed source: " + old)
            source = source.replace(old, new, 1)
    return source


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--baseline", required=True, type=Path)
    ap.add_argument("--candidate", required=True, type=Path)
    args = ap.parse_args()
    base = read_files(args.baseline)
    candidate = read_files(args.candidate)
    for path in (HR_SOURCE, ACTIVATOR):
        if path not in base or path not in candidate:
            raise AssertionError(f"Required legacy schema source missing: {path}")
    base_hr = base[HR_SOURCE].decode("utf-8")
    candidate_hr = candidate[HR_SOURCE].decode("utf-8")
    base_hr_compare = normalize_pension_rate_defaults(base_hr, candidate=False)
    candidate_hr_compare = normalize_pension_rate_defaults(candidate_hr, candidate=True)
    mismatches = []
    for table in LEGACY_TABLES:
        if table_ddl(base_hr_compare, table) != table_ddl(candidate_hr_compare, table):
            mismatches.append(f"stock_{table} CREATE TABLE DDL changed outside the two nullable pension-rate corrections")
    if required_columns_block(base_hr_compare) != required_columns_block(candidate_hr_compare):
        mismatches.append("required_columns() changed outside the two nullable pension-rate corrections")
    if base[ACTIVATOR] != candidate[ACTIVATOR]:
        mismatches.append("schema activator bytes changed")
    if mismatches:
        raise SystemExit("FAIL additive-only HR schema gate: " + "; ".join(mismatches))
    print("PASS additive-only HR schema gate: four existing HR tables preserved; only the two pension-rate columns are changed to nullable NULL defaults")
    print("PASS all other legacy columns, table DDL and schema activator remain unchanged")
    print("PASS candidate may add lifecycle tables only; this check does not certify live migration or payroll legality")

if __name__ == "__main__":
    main()
