#!/usr/bin/env python3
"""Verify and safely extract committed Stockland ERP 16.2.13 and 16.2.15 installers."""
import argparse
import hashlib
import json
import os
import shutil
import stat
import sys
import tempfile
import zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
SPECS = {
    "16.2.13": {
        "archive": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip",
        "sidecar": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER.zip.sha256",
        "sha256": "74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13",
    },
    "16.2.15": {
        "archive": "STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip",
        "sidecar": "STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip.sha256",
        "sha256": "8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df",
    },
}
PLUGIN_ROOT = "stockland-erp-app/"
MAX_ENTRIES = 5000
MAX_UNCOMPRESSED = 250 * 1024 * 1024


class VerificationError(RuntimeError):
    pass


def sha256_file(path):
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def validate_member_path(name):
    """Return safe path parts below the one permitted plugin root."""
    if not name or "\\\\" in name:
        raise VerificationError("empty or non-portable ZIP path: " + repr(name))
    is_directory = name.endswith("/")
    normalized = name[:-1] if is_directory else name
    parts = normalized.split("/")
    if not normalized or any(part in ("", ".", "..") for part in parts):
        raise VerificationError("unsafe ZIP path: " + repr(name))
    path = PurePosixPath(normalized)
    if path.is_absolute() or ":" in parts[0]:
        raise VerificationError("absolute or drive-qualified ZIP path: " + repr(name))
    if parts[0] != PLUGIN_ROOT.rstrip("/"):
        raise VerificationError("unexpected top-level path: " + repr(name))
    if len(parts) == 1 and not is_directory:
        raise VerificationError("file cannot be the plugin root: " + repr(name))
    return parts[1:]


def ensure_unique_member_paths(names):
    if len(names) != len(set(names)):
        raise VerificationError("duplicate ZIP paths detected")


def validate_archive(version):
    if version not in SPECS:
        raise VerificationError("Unsupported package version: " + version)
    spec = SPECS[version]
    archive = ROOT / spec["archive"]
    sidecar = ROOT / spec["sidecar"]
    if not archive.is_file() or not sidecar.is_file():
        raise VerificationError(version + ": archive or checksum sidecar missing")
    sidecar_tokens = sidecar.read_text(encoding="utf-8").strip().split()
    if not sidecar_tokens or sidecar_tokens[0].lower() != spec["sha256"]:
        raise VerificationError(version + ": committed sidecar differs from approved intake hash")
    actual = sha256_file(archive)
    if actual != spec["sha256"]:
        raise VerificationError(version + ": ZIP SHA-256 mismatch: " + actual)

    with zipfile.ZipFile(archive, "r") as zf:
        infos = zf.infolist()
        names = [item.filename for item in infos]
        if not infos or len(infos) > MAX_ENTRIES:
            raise VerificationError(version + ": invalid ZIP entry count")
        try:
            ensure_unique_member_paths(names)
        except VerificationError as exc:
            raise VerificationError(version + ": " + str(exc)) from exc
        roots = set()
        php_files, js_files, json_files = [], [], []
        total = 0
        for info in infos:
            name = info.filename
            try:
                validate_member_path(name)
            except VerificationError as exc:
                raise VerificationError(version + ": " + str(exc)) from exc
            path = PurePosixPath(name.rstrip("/"))
            roots.add(PLUGIN_ROOT)
            mode = info.external_attr >> 16
            if stat.S_IFMT(mode) == stat.S_IFLNK:
                raise VerificationError(version + ": symlink entry " + repr(name))
            total += info.file_size
            if total > MAX_UNCOMPRESSED:
                raise VerificationError(version + ": uncompressed size exceeds limit")
            if info.is_dir():
                continue
            suffix = path.suffix.lower()
            if suffix == ".php":
                php_files.append(name)
            elif suffix == ".js":
                js_files.append(name)
            elif suffix == ".json":
                json_files.append(name)
        if roots != {PLUGIN_ROOT}:
            raise VerificationError(version + ": expected one Stockland plugin root")
        bad = zf.testzip()
        if bad:
            raise VerificationError(version + ": ZIP CRC failure " + bad)
        if not php_files:
            raise VerificationError(version + ": no PHP source files")
        entry = None
        for name in php_files:
            relative_parts = validate_member_path(name)
            if len(relative_parts) != 1:
                continue
            if "Plugin Name:" in zf.read(name)[:16384].decode("utf-8", errors="replace"):
                entry = name
                break
        if not entry:
            raise VerificationError(version + ": root plugin header not found")

    return archive, {
        "version": version,
        "archive": archive.name,
        "sha256": actual,
        "size_bytes": archive.stat().st_size,
        "entries": len(infos),
        "php_files": len(php_files),
        "javascript_files": len(js_files),
        "json_files": len(json_files),
        "uncompressed_bytes": total,
        "plugin_root": PLUGIN_ROOT,
        "plugin_entry_point": entry,
        "zip_crc": "PASS",
        "path_safety": "PASS",
        "sidecar": "PASS",
    }


def extract_verified(version, destination_root):
    archive, report = validate_archive(version)
    destination_root = destination_root.resolve()
    destination_root.mkdir(parents=True, exist_ok=True)
    if destination_root == Path("/") or len(destination_root.parts) < 3:
        raise VerificationError("Refusing unsafe extraction destination")
    final_dir = destination_root / "stockland-erp-app"
    stage = Path(tempfile.mkdtemp(prefix=".stockland-extract-", dir=destination_root))
    try:
        with zipfile.ZipFile(archive, "r") as zf:
            for info in zf.infolist():
                if info.is_dir():
                    continue
                relative = PurePosixPath(info.filename.rstrip("/")).relative_to(PLUGIN_ROOT.rstrip("/"))
                target = stage.joinpath(*relative.parts)
                target.parent.mkdir(parents=True, exist_ok=True)
                with zf.open(info, "r") as source, target.open("wb") as output:
                    shutil.copyfileobj(source, output)
                os.chmod(target, 0o644)
        relative_entry = PurePosixPath(report["plugin_entry_point"]).relative_to(PLUGIN_ROOT.rstrip("/"))
        if not stage.joinpath(*relative_entry.parts).is_file():
            raise VerificationError(version + ": entry point missing after extract")
        if final_dir.exists():
            shutil.rmtree(final_dir)
        os.replace(stage, final_dir)
        report["extracted_to"] = str(final_dir)
        return report
    finally:
        if stage.exists():
            shutil.rmtree(stage, ignore_errors=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--verify-all", action="store_true")
    parser.add_argument("--extract-version", choices=sorted(SPECS))
    parser.add_argument("--destination-root", type=Path)
    parser.add_argument("--report", type=Path)
    args = parser.parse_args()
    if not args.verify_all and not args.extract_version:
        parser.error("Choose --verify-all or --extract-version")
    try:
        reports = []
        if args.verify_all:
            for version in SPECS:
                _, result = validate_archive(version)
                reports.append(result)
        if args.extract_version:
            if not args.destination_root:
                parser.error("--destination-root is required for extraction")
            reports.append(extract_verified(args.extract_version, args.destination_root))
        rendered = json.dumps({"result": "PASS", "packages": reports}, indent=2)
        print(rendered)
        if args.report:
            args.report.parent.mkdir(parents=True, exist_ok=True)
            args.report.write_text(rendered + "\n", encoding="utf-8")
        return 0
    except (OSError, zipfile.BadZipFile, VerificationError) as exc:
        print("FAIL: " + str(exc), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
