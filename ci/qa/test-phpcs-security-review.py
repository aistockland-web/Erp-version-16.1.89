#!/usr/bin/env python3
"""Review PHPCS security/database signals while preserving the full style report.

The full WordPress Coding Standards result remains an uploaded diagnostic report.
This gate blocks on unreviewed security/database sniffs and asserts source-level
invariants for the narrowly triaged findings in the exact 16.1.89 package.
"""
from __future__ import annotations
import json, re, sys
from collections import Counter
from pathlib import Path

if len(sys.argv) != 3:
    raise SystemExit("usage: test-phpcs-security-review.py <extracted-plugin-root> <phpcs-results.json>")
root = Path(sys.argv[1]).resolve()
report_path = Path(sys.argv[2]).resolve()
if not root.is_dir() or not report_path.is_file():
    raise SystemExit("FAIL required extracted source or PHPCS JSON report missing")
report = json.loads(report_path.read_text(encoding="utf-8"))

def relative_path(path: str) -> str:
    normalized = path.replace("\\", "/")
    marker = "/stockland-erp-app/"
    return normalized.split(marker, 1)[1] if marker in normalized else normalized

messages = []
for path, info in report.get("files", {}).items():
    for item in info.get("messages", []):
        messages.append({
            "rule": str(item.get("source", "")),
            "path": relative_path(path),
            "line": int(item.get("line", 0)),
            "type": str(item.get("type", "")),
            "message": str(item.get("message", "")),
        })

roles_path = root / "includes/class-stockland-roles.php"
roles_source = roles_path.read_text(encoding="utf-8") if roles_path.is_file() else ""
cap_messages = [m for m in messages if m["rule"] == "WordPress.WP.Capabilities.Unknown"]
capabilities = set(re.findall(r'unknown capability "([^"]+)"', "\n".join(m["message"] for m in cap_messages), flags=re.I))
missing_capabilities = sorted(cap for cap in capabilities if cap not in roles_source)
if not roles_source:
    missing_capabilities = ["role capability source file missing"]
if missing_capabilities:
    print("FAIL custom capabilities are not declared in the canonical role source: " + ", ".join(missing_capabilities))
    raise SystemExit(1)
print(f"PASS {len(cap_messages)} custom-capability PHPCS warnings map to {len(capabilities)} identifiers in the canonical ERP role/capability registry")

# Findings are allowed only at these reviewed locations. New security/database
# findings or changed locations require a new review and block the gate.
reviewed = {
    ("WordPress.Security.EscapeOutput.ExceptionNotEscaped", "includes/rest/class-rest-hr.php", 884),
    ("WordPress.DB.PreparedSQL.NotPrepared", "includes/rest/class-rest-wholesale.php", 118),
    ("WordPress.DB.PreparedSQL.NotPrepared", "includes/rest/class-rest-wholesale.php", 125),
    ("WordPress.DB.DirectDatabaseQuery.DirectQuery", "includes/rest/class-rest-wholesale.php", 125),
    ("WordPress.DB.DirectDatabaseQuery.NoCaching", "includes/rest/class-rest-wholesale.php", 125),
}
security_db_messages = [m for m in messages if m["rule"].startswith(("WordPress.Security.", "WordPress.DB."))]
unreviewed = [m for m in security_db_messages if (m["rule"], m["path"], m["line"]) not in reviewed]
if unreviewed:
    for item in unreviewed:
        print(f"FAIL unreviewed {item['rule']} at {item['path']}:{item['line']}: {item['message']}")
    raise SystemExit(1)
for item in security_db_messages:
    print(f"REVIEWED {item['rule']} at {item['path']}:{item['line']}")

wholesale_path = root / "includes/rest/class-rest-wholesale.php"
hr_path = root / "includes/rest/class-rest-hr.php"
payroll_path = root / "includes/class-stockland-payroll-statutory.php"
db_path = root / "includes/class-stockland-db.php"
if not all(p.is_file() for p in (wholesale_path, hr_path, payroll_path, db_path)):
    raise SystemExit("FAIL required security review source files missing")
wholesale = wholesale_path.read_text(encoding="utf-8")
hr = hr_path.read_text(encoding="utf-8")
payroll = payroll_path.read_text(encoding="utf-8")
db = db_path.read_text(encoding="utf-8")
validator_start = payroll.find("public static function compensation_record_validation_error")
validator_end = payroll.find("\n\tpublic static function ", validator_start + 10)
validator = payroll[validator_start:validator_end] if validator_start >= 0 and validator_end > validator_start else ""

checks = [
    ("wholesale query is prepared inside its read wrapper", bool(re.search(r"\$prepared\s*=\s*\$wpdb->prepare\(\s*\$sql\s*,\s*\$args\s*\)", wholesale))),
    ("only prepared SQL is passed to get_results", bool(re.search(r"\$wpdb->get_results\(\s*\$prepared\s*,\s*ARRAY_A\s*\)", wholesale)) and not re.search(r"\$wpdb->get_results\(\s*\$sql\b", wholesale)),
    ("prepared SQL must be a non-empty string before execution", "! is_string( $prepared ) || '' === $prepared" in wholesale),
    ("table identifiers in wholesale queries use WordPress %i placeholders", wholesale.count("%i") >= 12 and "Stockland_DB::table( 'products' )" in wholesale and "Stockland_DB::table( 'transactions' )" in wholesale),
    ("SKU filter uses a %s placeholder and a separate argument", "AND sku=%s" in wholesale and "$product_args[] = $only_sku;" in wholesale),
    ("ID IN lists use %d placeholders and integer IDs", "array_fill( 0, count( $variant_ids ), '%d' )" in wholesale and "array_map( 'intval', array_column( $branches, 'id' ) )" in wholesale),
    ("wholesale advice fails closed on prepare/query uncertainty", all(k in wholesale for k in ("surplus_query_prepare_failed", "surplus_query_failed", "surplus_query_unknown"))),
    ("live wholesale/ledger reads explicitly avoid stale cache", "object caching is not transaction-aware" in db and "serve potentially stale values" in db),
    ("payroll validation errors are fixed catalog strings", bool(validator) and not re.search(r"return\s+\$", validator) and not re.search(r"return\s+\"[^\"\\]*\$", validator)),
    ("HR validation error is thrown, not directly echoed as HTML", "throw new RuntimeException( $validation_error" in hr and not re.search(r"(?:echo|print)\s+\$validation_error", hr)),
]
for label, ok in checks:
    print(("PASS " if ok else "FAIL ") + label)
if not all(ok for _, ok in checks):
    raise SystemExit(1)

totals = report.get("totals", {})
print(f"FULL WPCS DIAGNOSTIC BASELINE: {totals.get('errors', 0)} errors; {totals.get('warnings', 0)} warnings; {totals.get('fixable', 0)} fixable style/standard entries")
print("The full report remains an artifact for maintainability triage. These aggregate style/custom-capability counts are not described as a vulnerability count. Security/database findings must match this exact reviewed context and pass the source-contract checks.")
print("PHPCS SECURITY/DATABASE REVIEW: PASS")
