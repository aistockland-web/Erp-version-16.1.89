#!/usr/bin/env python3
"""Focused invariants for the non-deployable remediation candidate."""
from __future__ import annotations
import sys
from pathlib import Path
root = Path(sys.argv[1]).resolve()
hr = (root / "includes/rest/class-rest-hr.php").read_text(encoding="utf-8")
wh = (root / "includes/rest/class-rest-wholesale.php").read_text(encoding="utf-8")
roles = (root / "includes/class-stockland-roles.php").read_text(encoding="utf-8")
db = (root / "includes/class-stockland-db.php").read_text(encoding="utf-8")
checks = [
    ("HR exception message remains context-neutral rather than HTML-escaped", "$validation_error . ' Employee '" in hr and "esc_html( (string) $validation_error ) . ' Employee '" not in hr),
    ("HR exception-context PHPCS suppression explains output-boundary escaping", "phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception text is internal catalog text, not HTML output; escape only at a rendering sink." in hr),
    ("HR exception remains a RuntimeException", "throw new RuntimeException(" in hr),
    ("prepared SQL uses $wpdb->prepare", "$wpdb->prepare( $sql, $args )" in wh),
    ("prepared result is checked before execution", "! is_string( $prepared ) || '' === $prepared" in wh),
    ("query result uses only prepared SQL", "$wpdb->get_results( $prepared, ARRAY_A )" in wh and "$wpdb->get_results( $sql" not in wh),
    ("prepared SQL suppression is narrow and documented", "phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a query template" in wh),
    ("uncached operational read suppression is narrow and documented", "WordPress.DB.DirectDatabaseQuery.NoCaching -- $prepared is produced" in wh),
    ("live read freshness rationale is explicit", "this live stock/order read must remain fresh" in wh),
    ("canonical role registry remains present", "class Stockland_Roles" in roles or "class-stockland-roles" in roles),
    ("live database layer preserves no-stale-cache rationale", "object caching is not transaction-aware" in db and "serve potentially stale values" in db),
]
failed = 0
for label, ok in checks:
    print(("PASS " if ok else "FAIL ") + label)
    failed += not ok
if failed:
    raise SystemExit(1)
print(f"REMEDIATION CANDIDATE SOURCE CONTRACTS: PASS ({len(checks)}/{len(checks)})")
