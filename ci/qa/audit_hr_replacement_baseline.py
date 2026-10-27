#!/usr/bin/env python3
"""Read-only source inventory for the checksum-locked Stockland ERP 16.1.95 ZIP."""
from __future__ import annotations
import argparse, csv, hashlib, json, re, zipfile
from pathlib import Path, PurePosixPath

LOCKED_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
LOCKED_SIZE = 1_102_405
ROOT = "stockland-erp-app/"
SOURCES = {
    "core_operations": ("stockland-erp-app/includes/class-stockland-activator.php", "core_table_keys", 29),
    "accounting_procurement": ("stockland-erp-app/includes/class-stockland-accounting.php", "table_keys", 16),
    "legacy_hr_payroll": ("stockland-erp-app/includes/class-stockland-hr.php", "table_keys", 4),
}
LEGACY_HR = {"employees", "employee_compensation", "payroll_periods", "payroll_lines"}

def fail(message):
    raise SystemExit("FAIL: " + message)

def digest(raw):
    return hashlib.sha256(raw).hexdigest()

def table_keys(source, method):
    match = re.search(r"function\s+" + re.escape(method) + r"\s*\([^)]*\)\s*\{(.*?)\n\s*\}", source, re.S)
    if not match:
        fail("missing table-key method " + method)
    return re.findall(r"'([a-z][a-z0-9_]*)'", match.group(1))

def create_table_blocks(source):
    pattern = r"CREATE\s+TABLE\s+(?:\{\$p\}|\$p)stock_([a-z0-9_]+)\s*\((.*?)\)\s*ENGINE\s*=\s*InnoDB[^;]*;"
    return [(m.group(1), source.count("\n", 0, m.start()) + 1, m.group(0))
            for m in re.finditer(pattern, source, re.I | re.S)]

def counts(ddl):
    inner = ddl[ddl.find("(")+1:ddl.rfind(")")]
    columns = indexes = 0
    for line in inner.splitlines():
        line = line.strip().rstrip(",")
        if not line:
            continue
        if re.match(r"(?:PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN|FULLTEXT|SPATIAL)\b", line, re.I):
            indexes += 1
        elif re.match(r"[A-Za-z_]\w*\s+(?:BIGINT|INT|TINYINT|SMALLINT|MEDIUMINT|VARCHAR|CHAR|TEXT|LONGTEXT|MEDIUMTEXT|DECIMAL|DATE|DATETIME|TIMESTAMP|TIME|JSON|ENUM|BLOB|LONGBLOB|DOUBLE|FLOAT|BOOLEAN)\b", line, re.I):
            columns += 1
    return columns, indexes

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--package", required=True)
    parser.add_argument("--out-dir", required=True)
    parser.add_argument("--allow-unlocked", action="store_true")
    args = parser.parse_args()
    package, out = Path(args.package), Path(args.out_dir)
    if not package.is_file():
        fail("package not found: " + str(package))
    raw = package.read_bytes()
    identity_pass = digest(raw) == LOCKED_SHA256 and len(raw) == LOCKED_SIZE
    if not identity_pass and not args.allow_unlocked:
        fail("baseline identity mismatch: sha256=" + digest(raw) + " size=" + str(len(raw)))
    try:
        archive = zipfile.ZipFile(package)
    except zipfile.BadZipFile:
        fail("invalid ZIP")
    with archive:
        bad = archive.testzip()
        if bad:
            fail("CRC failure: " + bad)
        infos = [item for item in archive.infolist() if not item.is_dir()]
        names = [item.filename for item in infos]
        if len(names) != len(set(names)):
            fail("duplicate member names")
        if len(names) != 75:
            fail("expected 75 files, found " + str(len(names)))
        if any(PurePosixPath(name).parts[0] != ROOT.rstrip("/") or ".." in PurePosixPath(name).parts for name in names):
            fail("unexpected root or unsafe path")
        files = {name: archive.read(name) for name in names}
    table_rows = []
    for domain, (path, method, expected) in SOURCES.items():
        if path not in files:
            fail("missing schema owner " + path)
        source = files[path].decode("utf-8")
        keys = table_keys(source, method)
        if len(keys) != expected or len(keys) != len(set(keys)):
            fail(domain + " key contract mismatch")
        blocks = {key: (line, ddl) for key, line, ddl in create_table_blocks(source)}
        for key in keys:
            if key not in blocks:
                fail(domain + " registered key has no DDL: " + key)
            line, ddl = blocks[key]
            cols, indexes = counts(ddl)
            table_rows.append({
                "domain": domain, "table_key": key, "legacy_hr": key in LEGACY_HR,
                "ddl_file": path, "ddl_line": line,
                "columns_lexical_count": cols, "indexes_and_constraints_lexical_count": indexes,
                "explicit_foreign_key_in_ddl": bool(re.search(r"\bFOREIGN\s+KEY\b", ddl, re.I)),
            })
    all_keys = [row["table_key"] for row in table_rows]
    if len(all_keys) != 49 or len(set(all_keys)) != 49:
        fail("expected 49 unique schema keys")
    if {row["table_key"] for row in table_rows if row["domain"] == "legacy_hr_payroll"} != LEGACY_HR:
        fail("legacy HR table set changed")
    source_files = {name: raw.decode("utf-8", "replace") for name, raw in files.items() if name.endswith((".php", ".js"))}
    for row in table_rows:
        pattern = re.compile(r"(?<![A-Za-z0-9_])(?:stock_)?" + re.escape(row["table_key"]) + r"(?![A-Za-z0-9_])", re.I)
        consumers = [name for name, source in source_files.items() if pattern.search(source)]
        row["lexical_consumer_file_count"] = len(consumers)
        row["lexical_occurrence_count"] = sum(len(pattern.findall(source_files[name])) for name in consumers)
        row["lexical_consumer_files"] = ";".join(consumers)
    rest_sources = {name: source for name, source in source_files.items() if "/rest/" in name}
    route_calls = sum(len(re.findall(r"register_rest_route\s*\(", source)) for source in rest_sources.values())
    callback_tokens = sum(len(re.findall(r"['\"]permission_callback['\"]\s*=>", source)) for source in rest_sources.values())
    hr_source = source_files.get(ROOT + "includes/rest/class-rest-hr.php", "")
    hr_route_calls = len(re.findall(r"register_rest_route\s*\(", hr_source))
    dependencies = []
    for key in sorted(LEGACY_HR):
        consumers = [name for name, source in source_files.items()
                     if re.search(r"(?<![A-Za-z0-9_])(?:stock_)?" + re.escape(key) + r"(?![A-Za-z0-9_])", source, re.I)]
        dependencies.append({"legacy_table": key, "consumer_file_count": len(consumers), "consumer_files": consumers})
    report = {
        "report": "Stockland ERP 16.1.95 HR replacement source inventory",
        "baseline": {"file": package.name, "sha256": digest(raw), "size_bytes": len(raw),
                     "locked_sha256": LOCKED_SHA256, "locked_size_bytes": LOCKED_SIZE,
                     "identity_pass": identity_pass, "zip_crc_pass": True, "files": len(names),
                     "single_root": "stockland-erp-app/"},
        "schema": {"core_operations": 29, "accounting_procurement": 16, "legacy_hr_payroll": 4,
                   "total": 49, "table_keys_unique": True, "every_registered_key_has_source_ddl": True,
                   "explicit_fk_constraint_count": sum(row["explicit_foreign_key_in_ddl"] for row in table_rows)},
        "rest_source_inventory": {"route_registration_calls": route_calls,
                                  "literal_permission_callback_tokens": callback_tokens,
                                  "legacy_hr_route_registration_calls": hr_route_calls,
                                  "note": "Lexical counts are navigation evidence only; callback presence does not prove role, row, location, or state-transition correctness."},
        "legacy_hr_dependencies": dependencies,
        "table_inventory": table_rows,
        "release_decision": "DESIGN/AUDIT ONLY — no migrations or business data changed; target HR schema count is not frozen."
    }
    out.mkdir(parents=True, exist_ok=True)
    (out / "hr_replacement_baseline_inventory.json").write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    with (out / "hr_replacement_49_table_inventory.csv").open("w", newline="", encoding="utf-8-sig") as handle:
        fields = ["domain", "table_key", "legacy_hr", "ddl_file", "ddl_line", "columns_lexical_count",
                  "indexes_and_constraints_lexical_count", "explicit_foreign_key_in_ddl",
                  "lexical_consumer_file_count", "lexical_occurrence_count", "lexical_consumer_files"]
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        writer.writerows(table_rows)
    print(json.dumps({"baseline_sha256": digest(raw), "baseline_identity_pass": identity_pass,
                      "package_files": len(names), "table_contract": report["schema"],
                      "rest_source_inventory": report["rest_source_inventory"],
                      "legacy_hr_dependencies": dependencies,
                      "outputs": ["hr_replacement_baseline_inventory.json", "hr_replacement_49_table_inventory.csv"]},
                     indent=2, ensure_ascii=False))

if __name__ == "__main__":
    main()
