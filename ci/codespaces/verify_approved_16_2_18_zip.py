#!/usr/bin/env python3
"""Verify the exact Owner-approved 16.2.18 Actions installer artifact without rebuilding it."""
from __future__ import annotations

import hashlib
import json
import re
import stat
import sys
import zipfile
from pathlib import PurePosixPath
from pathlib import Path

EXPECTED_SHA256 = "de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08"
EXPECTED_SIZE = 2_251_794
EXPECTED_SOURCE_COMMIT = "387c193c7999840c731d37acd854ec920f134507"
EXPECTED_FILE_COUNT = 124
ROOT = "stockland-erp-app"


def fail(message: str) -> None:
    raise SystemExit(f"FAIL approved 16.2.18 installer: {message}")


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def main() -> None:
    if len(sys.argv) != 2:
        fail("usage: verify_approved_16_2_18_zip.py <installer.zip>")
    archive = Path(sys.argv[1]).resolve()
    if not archive.is_file():
        fail(f"installer not found: {archive}")
    size = archive.stat().st_size
    digest = sha256_file(archive)
    if size != EXPECTED_SIZE:
        fail(f"unexpected byte size {size}; expected {EXPECTED_SIZE}")
    if digest != EXPECTED_SHA256:
        fail(f"SHA-256 mismatch: {digest}")

    try:
        with zipfile.ZipFile(archive) as package:
            if package.testzip() is not None:
                fail("ZIP CRC/integrity check failed")
            infos = package.infolist()
            names = [info.filename for info in infos]
            if len(names) != len(set(names)):
                fail("duplicate ZIP member names")
            roots = {PurePosixPath(name).parts[0] for name in names if name}
            if roots != {ROOT}:
                fail(f"unexpected archive roots: {sorted(roots)}")

            file_bytes: dict[str, bytes] = {}
            for info in infos:
                name = info.filename
                if not name or "\\\\" in name or name.startswith(("/", "\\\\")):
                    fail(f"unsafe archive path: {name!r}")
                relative = PurePosixPath(name)
                if relative.is_absolute() or any(part in ("", ".", "..") for part in relative.parts):
                    fail(f"path traversal or invalid path: {name!r}")
                if relative.parts[0] != ROOT:
                    fail(f"unexpected archive root for {name!r}")
                mode = (info.external_attr >> 16) & 0xFFFF
                if stat.S_ISLNK(mode):
                    fail(f"symlink is not allowed: {name!r}")
                if info.is_dir():
                    continue
                key = str(relative.relative_to(ROOT))
                file_bytes[key] = package.read(info)

            manifest_raw = file_bytes.get("RELEASE_MANIFEST.json")
            if manifest_raw is None:
                fail("RELEASE_MANIFEST.json is missing")
            manifest = json.loads(manifest_raw)
            if manifest.get("application", {}).get("version") != "16.2.18":
                fail("embedded application version is not 16.2.18")
            if manifest.get("source_lineage", {}).get("canonical_source_commit") != EXPECTED_SOURCE_COMMIT:
                fail("embedded canonical source commit does not match the approved artifact")
            inventory = manifest.get("file_inventory", {})
            if inventory.get("count") != EXPECTED_FILE_COUNT:
                fail(f"manifest inventory count is not {EXPECTED_FILE_COUNT}")
            listed = inventory.get("files")
            if not isinstance(listed, list):
                fail("manifest file inventory is malformed")
            expected = {}
            for item in listed:
                path = item.get("path")
                checksum = item.get("sha256")
                if not isinstance(path, str) or not isinstance(checksum, str) or path in expected:
                    fail("manifest contains invalid or duplicate file records")
                expected[path] = checksum
            actual = {
                path: hashlib.sha256(data).hexdigest()
                for path, data in file_bytes.items()
                if path != "RELEASE_MANIFEST.json"
            }
            if len(actual) != EXPECTED_FILE_COUNT:
                fail(f"archive has {len(actual)} app files; expected {EXPECTED_FILE_COUNT}")
            if actual != expected:
                missing = sorted(set(expected) - set(actual))[:8]
                extra = sorted(set(actual) - set(expected))[:8]
                changed = sorted(
                    path for path in set(actual) & set(expected)
                    if actual[path] != expected[path]
                )[:8]
                fail(f"per-file manifest mismatch; missing={missing}, extra={extra}, changed={changed}")

            entry = file_bytes.get("stockland-inventory.php")
            if entry is None:
                fail("plugin entrypoint is missing")
            text = entry.decode("utf-8")
            header = re.search(r"^\\s*\\*?\\s*Version:\\s*([0-9.]+)", text, re.M)
            constant = re.search(r"define\\(\\s*'STOCKLAND_VERSION',\\s*'([0-9.]+)'", text)
            if not header or not constant or header.group(1) != "16.2.18" or constant.group(1) != "16.2.18":
                fail("plugin header/constant versions do not both equal 16.2.18")
    except (zipfile.BadZipFile, OSError, UnicodeDecodeError, json.JSONDecodeError, KeyError) as exc:
        fail(str(exc))

    print(
        "PASS exact approved 16.2.18 artifact "
        f"sha256={digest} bytes={size} manifest_files={EXPECTED_FILE_COUNT} "
        f"source_commit={EXPECTED_SOURCE_COMMIT}"
    )


if __name__ == "__main__":
    main()
