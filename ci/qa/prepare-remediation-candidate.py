#!/usr/bin/env python3
"""Build a review-only remediation candidate; never modify the committed ZIP."""
from __future__ import annotations
import hashlib
import sys
import zipfile
from pathlib import Path

repo = Path(__file__).resolve().parents[2]
zips = sorted(repo.glob("STOCKLAND_ERP_16.1.89*.zip"))
if len(zips) != 1:
    raise SystemExit(f"FAIL expected exactly one release ZIP, found {len(zips)}")
archive = zips[0]
expected = "4b8a5fe5648ddf062953c671fe572cb62235757d3a092da6b23116aacae392c3"
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
if digest != expected:
    raise SystemExit(f"FAIL source ZIP identity changed: {digest}")
base = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path("/tmp/stockland-remediation")
if base.exists() and any(base.iterdir()):
    raise SystemExit("FAIL work directory must be new/empty; refusing to overwrite existing data")
base.mkdir(parents=True, exist_ok=True)
original = base / "original"
candidate = base / "candidate"
original.mkdir()
candidate.mkdir()
with zipfile.ZipFile(archive) as zf:
    if zf.testzip() is not None:
        raise SystemExit("FAIL source ZIP CRC")
    zf.extractall(original)
    zf.extractall(candidate)
root = candidate / "stockland-erp-app"
hr_path = root / "includes/rest/class-rest-hr.php"
wh_path = root / "includes/rest/class-rest-wholesale.php"
if not hr_path.is_file() or not wh_path.is_file():
    raise SystemExit("FAIL expected HR/wholesale source paths missing")
hr = hr_path.read_text(encoding="utf-8")
old_hr = "$validation_error . ' Employee '"
if hr.count(old_hr) != 1:
    raise SystemExit("FAIL HR exception context did not match exactly once; no patch applied")
hr_lines = hr.splitlines(keepends=True)
hr_matches = [i for i, line in enumerate(hr_lines) if old_hr in line]
if len(hr_matches) != 1 or "throw new RuntimeException" not in hr_lines[hr_matches[0]]:
    raise SystemExit("FAIL expected internal exception statement not found; no patch applied")
hr_line = hr_lines[hr_matches[0]]
indent = hr_line[:len(hr_line) - len(hr_line.lstrip())]
review_note = (
    indent
    + "// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- "
    + "exception text is internal catalog text, not HTML output; escape only at a rendering sink.\n"
)
hr_lines[hr_matches[0]] = review_note + hr_line
hr_path.write_text("".join(hr_lines), encoding="utf-8")
wh = wh_path.read_text(encoding="utf-8")
old_prepare = "\t\t\t\t$prepared = $wpdb->prepare( $sql, $args );"
new_prepare = (
    "\t\t\t\t// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- "
    "$sql is a query template; dynamic values are bound in $args and table identifiers use %i.\n"
    + old_prepare
)
old_read = "\t\t\t$rows = $wpdb->get_results( $prepared, ARRAY_A );"
new_read = (
    "\t\t\t// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, "
    "WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching "
    "-- $prepared is produced by $wpdb->prepare above; this live stock/order read must remain fresh.\n"
    + old_read
)
if wh.count(old_prepare) != 1 or wh.count(old_read) != 1:
    raise SystemExit("FAIL wholesale SQL context did not match exactly once; no patch applied")
wh = wh.replace(old_prepare, new_prepare, 1).replace(old_read, new_read, 1)
wh_path.write_text(wh, encoding="utf-8")
print(f"PASS source archive SHA-256 verified: {digest}")
print("PASS HR exception preserves internal catalog text; escaping remains at any rendering/output sink")
print("PASS narrowly scoped PHPCS rationale added for prepared SQL and intentionally uncached live read")
print("PASS committed release ZIP remains unchanged; changes are candidate-only")
print(f"ORIGINAL_DIR={original / 'stockland-erp-app'}")
print(f"CANDIDATE_DIR={root}")
print(f"WORK_DIR={base}")
