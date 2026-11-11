#!/usr/bin/env python3
"""Print a bounded diagnostic for the known candidate PHP entrypoint anomaly."""
from __future__ import annotations

import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ARCHIVE = ROOT / "STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip"
MEMBER = "stockland-erp-app/includes/class-stockland-schema-contract.php"

def main() -> int:
    if not ARCHIVE.is_file():
        print(f"NOT FOUND: {ARCHIVE}")
        return 0
    with zipfile.ZipFile(ARCHIVE) as zf:
        try:
            payload = zf.read(MEMBER)
        except KeyError:
            print(f"NOT FOUND IN PACKAGE: {MEMBER}")
            return 0
    data = payload[3:] if payload.startswith(bytes.fromhex("efbbbf")) else payload
    open_tag = data.find(b"<?php")
    short_tag = data.find(b"<?=")
    tags = [x for x in (open_tag, short_tag) if x >= 0]
    first_tag = min(tags) if tags else -1
    print(f"FILE: {MEMBER}")
    print(f"BYTES: {len(payload)}")
    print(f"STARTS_WITH_PHP_TAG: {data.startswith(b'<?php') or data.startswith(b'<?=')}")
    print(f"FIRST_PHP_TAG_OFFSET: {first_tag}")
    print("FIRST 32 LINES:")
    for number, line in enumerate(data.splitlines()[:32], 1):
        text = line.decode("utf-8", "replace")[:240]
        print(f"{number:02d}: {text}")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
