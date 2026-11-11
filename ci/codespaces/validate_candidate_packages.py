#!/usr/bin/env python3
"""Fail-closed identity, integrity and safe-extraction check for Stockland ERP ZIPs."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import shutil
import stat
import zipfile
from pathlib import Path, PurePosixPath

REPOSITORY_ROOT = Path(__file__).resolve().parents[2]
LOCK_PATH = REPOSITORY_ROOT / "ci" / "codespaces" / "candidate-packages.json"
PLUGIN_ROOT = "stockland-erp-app/"


def fail(message: str) -> None:
    raise SystemExit("FAIL candidate package validation: " + message)


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def validate_member_path(name: str) -> None:
    if not name or "\\" in name or name.startswith("/") or re.match(r"^[A-Za-z]:", name):
        fail("unsafe archive path: " + repr(name))
    parts = name.split("/")
    if any(part in ("", ".", "..") for part in parts):
        fail("unsafe archive path component: " + repr(name))
    if not name.startswith(PLUGIN_ROOT):
        fail("unexpected plugin root/path: " + repr(name))


def validate_package(package: dict, extract_root: Path) -> dict:
    archive_path = REPOSITORY_ROOT / package["archive"]
    sidecar_path = REPOSITORY_ROOT / package["sidecar"]
    release = package["release"]
    expected_hash = package["sha256"]

    if not archive_path.is_file():
        fail(f"{release}: missing archive {archive_path.name}")
    if not sidecar_path.is_file():
        fail(f"{release}: missing SHA-256 sidecar {sidecar_path.name}")

    actual_size = archive_path.stat().st_size
    if actual_size != package["size_bytes"]:
        fail(f"{release}: byte size {actual_size} != locked {package['size_bytes']}")

    actual_hash = sha256_file(archive_path)
    if actual_hash != expected_hash:
        fail(f"{release}: SHA-256 {actual_hash} != locked {expected_hash}")

    sidecar_fields = sidecar_path.read_text(encoding="utf-8").strip().split(maxsplit=1)
    if len(sidecar_fields) != 2 or sidecar_fields[0].lower() != expected_hash:
        fail(f"{release}: sidecar digest does not match the package lock")
    if sidecar_fields[1].strip() != archive_path.name:
        fail(f"{release}: sidecar filename must exactly identify {archive_path.name}")

    try:
        with zipfile.ZipFile(archive_path) as archive:
            bad_member = archive.testzip()
            if bad_member is not None:
                fail(f"{release}: ZIP CRC error in {bad_member}")

            infos = [item for item in archive.infolist() if not item.is_dir()]
            names = [item.filename for item in infos]
            if len(names) != len(set(names)):
                fail(f"{release}: duplicate file paths in ZIP")

            roots = set()
            for info in infos:
                validate_member_path(info.filename)
                mode = (info.external_attr >> 16) & 0xFFFF
                if stat.S_ISLNK(mode):
                    fail(f"{release}: symbolic links are not allowed ({info.filename})")
                if info.file_size > 32 * 1024 * 1024:
                    fail(f"{release}: oversized archive member {info.filename}")
                roots.add(PurePosixPath(info.filename).parts[0])

            if roots != {"stockland-erp-app"}:
                fail(f"{release}: expected one stockland-erp-app root, got {sorted(roots)}")
            if "stockland-erp-app/stockland-inventory.php" not in names:
                fail(f"{release}: WordPress plugin entry file is missing")
            if "file_count" in package and len(infos) != package["file_count"]:
                fail(f"{release}: file count {len(infos)} != locked {package['file_count']}")

            plugin_header = archive.read("stockland-erp-app/stockland-inventory.php").decode("utf-8", "replace")
            match = re.search(r"(?mi)^\s*\*?\s*Version:\s*([0-9]+(?:\.[0-9]+)+)", plugin_header)
            if not match or match.group(1) != release:
                fail(f"{release}: plugin header version does not match the locked release")

            php_count = sum(name.endswith(".php") for name in names)
            if "php_count" in package and php_count != package["php_count"]:
                fail(f"{release}: PHP file count {php_count} != locked {package['php_count']}")

            destination = extract_root / release
            if destination.exists():
                shutil.rmtree(destination)
            for info in infos:
                relative = PurePosixPath(info.filename)
                target = destination.joinpath(*relative.parts)
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(archive.read(info.filename))

    except zipfile.BadZipFile as exc:
        fail(f"{release}: invalid ZIP: {exc}")

    summary = {
        "release": release,
        "role": package["role"],
        "archive": package["archive"],
        "bytes": actual_size,
        "sha256": actual_hash,
        "files": len(infos),
        "php_files": php_count,
        "javascript_files": sum(name.endswith(".js") for name in names),
        "json_files": sum(name.endswith(".json") for name in names),
        "css_files": sum(name.endswith(".css") for name in names),
        "root": PLUGIN_ROOT.rstrip("/"),
        "crc": "PASS",
        "safe_paths": "PASS",
        "extract_path": str(destination / "stockland-erp-app"),
    }
    print("PASS " + json.dumps(summary, sort_keys=True))
    return summary


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--extract-root", type=Path, default=Path("/tmp/stockland-candidate-packages"))
    parser.add_argument("--summary", type=Path)
    args = parser.parse_args()

    try:
        lock = json.loads(LOCK_PATH.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        fail("cannot read package lock: " + str(exc))
    if lock.get("schema_version") != 1 or not isinstance(lock.get("packages"), list):
        fail("unsupported candidate package lock format")

    args.extract_root.mkdir(parents=True, exist_ok=True)
    summaries = [validate_package(item, args.extract_root) for item in lock["packages"]]
    if {item["release"] for item in summaries} != {"16.2.13", "16.2.15"}:
        fail("both the 16.2.13 baseline and 16.2.15 candidate are mandatory")
    result = {"result": "PASS", "packages": summaries}
    if args.summary:
        args.summary.parent.mkdir(parents=True, exist_ok=True)
        args.summary.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    print("PASS verified both exact Stockland ERP candidate packages")


if __name__ == "__main__":
    main()
