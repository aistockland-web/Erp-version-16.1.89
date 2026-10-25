#!/usr/bin/env python3
"""Regression tests for the 16.1.96 HR patcher's PHP batch boundary matcher."""
import importlib.util
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MODULE_PATH = ROOT / "patch_full_hr_payroll_16_1_96.py"
spec = importlib.util.spec_from_file_location("hr_patch_builder", MODULE_PATH)
module = importlib.util.module_from_spec(spec)
assert spec and spec.loader
spec.loader.exec_module(module)

def check(source, expected, label):
    actual = module.find_batch_boundary(source, source.find("CREATE TABLE {$p}stock_payroll_lines"))
    assert actual == expected, f"{label}: expected {expected}, got {actual}"

def main():
    lf = "CREATE TABLE {$p}stock_payroll_lines (\n);\n\t\tif ( $batch_size > 0 ) {\n"
    check(lf, lf.index("\t\tif"), "LF with literal tabs")
    crlf = lf.replace("\n", "\r\n")
    check(crlf, crlf.index("\t\tif"), "CRLF with literal tabs")
    spaces = "CREATE TABLE {$p}stock_payroll_lines (\n);\n        if ( $batch_size > 0 ) {\n"
    check(spaces, spaces.index("        if"), "space indentation")
    missing = "CREATE TABLE {$p}stock_payroll_lines (\n);\n        if ( $other > 0 ) {\n"
    assert module.find_batch_boundary(missing, missing.find("CREATE TABLE {$p}stock_payroll_lines")) == -1
    assert module.find_batch_boundary(lf, -1) == -1
    print("PASS HR batch boundary matcher: LF, CRLF, spaces, missing guard, invalid start")

if __name__ == "__main__":
    main()
