#!/usr/bin/env python3
"""Safely materialize the verified Stockland 16.2.13 app as editable source."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import sys
import tempfile
from pathlib import Path, PurePosixPath

BASELINE = {
    "archive": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip",
    "sha256": "74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13",
    "size_bytes": 2188684,
    "php_files": 80,
}
ROOT = Path(__file__).resolve().parents[2]


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def manifest_digest(files: dict[str, str]) -> str:
    raw = json.dumps(files, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(raw).hexdigest()


def read_baseline_report(path: Path) -> dict:
    try:
        report = json.loads(path.read_text(encoding="utf-8"))["16.2.13"]
    except (OSError, json.JSONDecodeError, KeyError, TypeError) as error:
        raise RuntimeError(f"Missing/invalid .13 package-intake report {path}: {error}") from error
    expected = {
        "filename": BASELINE["archive"],
        "sha256": BASELINE["sha256"],
        "size_bytes": BASELINE["size_bytes"],
        "php_files": BASELINE["php_files"],
        "plugin_root": "stockland-erp-app/",
        "zip_crc": "PASS",
        "duplicate_paths": 0,
        "unsafe_paths": 0,
    }
    for key, value in expected.items():
        if report.get(key) != value:
            raise RuntimeError(f".13 intake report mismatch for {key}: {report.get(key)!r}")
    files = report.get("files")
    if not isinstance(files, dict) or not files or report.get("file_count") != len(files):
        raise RuntimeError(" .13 intake report has no complete per-file SHA-256 manifest")
    for name, digest in files.items():
        item = PurePosixPath(name)
        if not name or item.is_absolute() or "\\" in name or any(part in ("", ".", "..") for part in item.parts):
            raise RuntimeError(f"Unsafe source path in intake manifest: {name!r}")
        if not isinstance(digest, str) or not re.fullmatch(r"[0-9a-f]{64}", digest):
            raise RuntimeError(f"Invalid source digest for {name!r}")
    return report


def verify_source_tree(source: Path, report: dict) -> None:
    if source.is_symlink() or not source.is_dir():
        raise RuntimeError(f"Source directory is absent or unsafe: {source}")
    actual = {}
    for item in source.rglob("*"):
        if item.is_symlink():
            raise RuntimeError(f"Symlink in source tree: {item}")
        if item.is_file():
            actual[item.relative_to(source).as_posix()] = sha256_file(item)
    expected = report["files"]
    if set(actual) != set(expected):
        raise RuntimeError(
            "Source inventory differs from intake manifest; "
            f"missing={sorted(set(expected) - set(actual))[:8]}, "
            f"extra={sorted(set(actual) - set(expected))[:8]}"
        )
    changed = [name for name in expected if actual[name] != expected[name]]
    if changed:
        raise RuntimeError(f"Per-file SHA-256 mismatch: {changed[:8]}")
    if sum(name.lower().endswith(".php") for name in actual) != BASELINE["php_files"]:
        raise RuntimeError("The verified .13 source does not contain exactly 80 PHP files")
    entry = source / "stockland-inventory.php"
    if not entry.is_file():
        raise RuntimeError("WordPress entry point stockland-inventory.php is missing")
    text = entry.read_text(encoding="utf-8", errors="replace")
    if not re.search(r"^\s*\*\s*Version:\s*16\.2\.13\s*$", text, re.MULTILINE):
        raise RuntimeError("Plugin header is not version 16.2.13")
    if "define( 'STOCKLAND_VERSION', '16.2.13' );" not in text:
        raise RuntimeError("STOCKLAND_VERSION constant is not 16.2.13")


def expected_provenance(report: dict) -> dict:
    return {
        "source_version": "16.2.13",
        "archive": BASELINE["archive"],
        "archive_sha256": BASELINE["sha256"],
        "generated_by": "scripts/codespaces/bootstrap_project_source.py",
        "file_manifest_sha256": manifest_digest(report["files"]),
        "file_count": len(report["files"]),
    }


def read_and_verify_marker(marker: Path, provenance: dict) -> dict:
    if marker.is_symlink():
        raise RuntimeError(f"Source provenance marker is a symlink; refusing to trust it: {marker}")
    if not marker.is_file():
        raise RuntimeError(f"Source provenance marker is absent or not a regular file: {marker}")
    try:
        current = json.loads(marker.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise RuntimeError(f"Invalid source provenance marker {marker}: {error}") from error
    if not isinstance(current, dict):
        raise RuntimeError(f"Source provenance marker is not a JSON object: {marker}")
    for key, value in provenance.items():
        if current.get(key) != value:
            raise RuntimeError(
                f"Source provenance marker mismatch for {key}; preserving all source files: {marker}"
            )
    if set(current) != set(provenance):
        raise RuntimeError(f"Unexpected or missing provenance fields; preserving source: {marker}")
    return current


def fsync_directory(path: Path) -> None:
    flags = os.O_RDONLY | getattr(os, "O_DIRECTORY", 0)
    descriptor = os.open(path, flags)
    try:
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def write_provenance_marker(marker: Path, provenance: dict) -> None:
    """Publish a complete marker atomically and never replace an existing marker."""
    marker.parent.mkdir(parents=True, exist_ok=True)
    if marker.exists() or marker.is_symlink():
        raise RuntimeError(f"Provenance marker already exists; refusing to replace it: {marker}")

    descriptor, temporary_name = tempfile.mkstemp(
        prefix=f".{marker.name}.", suffix=".tmp", dir=marker.parent
    )
    temporary = Path(temporary_name)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8") as stream:
            json.dump(provenance, stream, indent=2, sort_keys=True)
            stream.write("\n")
            stream.flush()
            os.fsync(stream.fileno())
        try:
            # A hard-link creates the final name atomically and fails if it already exists.
            os.link(temporary, marker)
        except FileExistsError as error:
            raise RuntimeError(
                f"Provenance marker appeared during import; preserving source: {marker}"
            ) from error
        fsync_directory(marker.parent)
    finally:
        try:
            temporary.unlink()
        except FileNotFoundError:
            pass


def materialize(
    stage_root: Path,
    report_path: Path,
    destination: Path,
    marker: Path,
    *,
    allow_local_modifications: bool = False,
) -> bool:
    report = read_baseline_report(report_path)
    provenance = expected_provenance(report)

    if destination.exists() or destination.is_symlink():
        if destination.is_symlink() or not destination.is_dir():
            raise RuntimeError(f"Existing source destination is unsafe: {destination}")

        if marker.exists() or marker.is_symlink():
            read_and_verify_marker(marker, provenance)
            try:
                verify_source_tree(destination, report)
            except RuntimeError as error:
                if not allow_local_modifications:
                    raise RuntimeError(
                        "Existing marked editable source differs from the exact .13 intake manifest. "
                        "All files were preserved. Review and commit/save the local changes, or rerun "
                        "with --allow-local-modifications to explicitly keep this non-baseline working tree. "
                        f"Verification detail: {error}"
                    ) from error
                if not (destination / "stockland-inventory.php").is_file():
                    raise RuntimeError(
                        "Modified source is missing its WordPress entry point; preserving it and refusing "
                        "the local-modification override."
                    ) from error
                print(
                    "WARNING: preserving an explicitly allowed locally modified source tree. "
                    "It is not verified as byte-identical to the pinned .13 package."
                )
                return False
            print(f"PASS: existing source re-hashed and matches the exact .13 manifest: {destination}")
            return False

        # Recover a prior interrupted import only when the unmarked directory is the exact .13 tree.
        try:
            verify_source_tree(destination, report)
        except RuntimeError as error:
            raise RuntimeError(
                f"Refusing to adopt unmarked pre-existing source {destination}; "
                "all files were preserved. The complete tree does not match the .13 manifest."
            ) from error
        write_provenance_marker(marker, provenance)
        print(f"PASS: recovered exact .13 source and repaired its missing provenance marker: {destination}")
        return False

    # The earlier importer published the marker before moving the source directory.
    # Safely remove only a stale marker that proves the exact pinned .13 identity.
    if marker.exists() or marker.is_symlink():
        read_and_verify_marker(marker, provenance)
        marker.unlink()
        fsync_directory(marker.parent)
        print("Recovered a matching stale provenance marker left without its source directory.")

    staged = stage_root / "16_2_13" / "stockland-erp-app"
    verify_source_tree(staged, report)
    destination.parent.mkdir(parents=True, exist_ok=True)
    marker.parent.mkdir(parents=True, exist_ok=True)

    with tempfile.TemporaryDirectory(prefix=".stockland-source-import-", dir=destination.parent) as temp:
        temp_root = Path(temp)
        staged_copy = temp_root / "stockland-erp-app"
        shutil.copytree(staged, staged_copy, symlinks=False)
        verify_source_tree(staged_copy, report)

        if destination.exists() or destination.is_symlink():
            raise RuntimeError(
                f"Destination appeared during import; no files were replaced: {destination}"
            )

        # Move the verified source first. A crash here leaves an unmarked directory
        # that the recovery branch above can re-hash before publishing provenance.
        os.rename(staged_copy, destination)
        fsync_directory(destination.parent)

    # Provenance becomes visible only after the complete source tree is in place.
    # If this step fails, the next run verifies the exact unmarked tree and repairs it.
    write_provenance_marker(marker, provenance)
    print(f"PASS: Stockland ERP 16.2.13 source materialized at {destination}")
    print(f"PASS: {len(report['files'])} files match the exact per-file SHA-256 intake manifest")
    print("Original .13 and .15 installer archives remain unchanged.")
    return True


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage-root", type=Path, default=Path("/tmp/stockland-candidate-packages"))
    parser.add_argument("--intake-report", type=Path, default=ROOT / "artifacts/codespaces/package-intake.json")
    parser.add_argument("--destination", type=Path, default=ROOT / "src/stockland-erp-app")
    parser.add_argument("--marker", type=Path, default=ROOT / "src/.stockland-erp-app.source.json")
    parser.add_argument(
        "--allow-local-modifications",
        action="store_true",
        help=(
            "Explicitly preserve a marked source tree whose files differ from the pinned .13 manifest. "
            "The tree remains identified as locally modified and is not claimed to be exact .13 source."
        ),
    )
    args = parser.parse_args()
    try:
        materialize(
            args.stage_root,
            args.intake_report,
            args.destination,
            args.marker,
            allow_local_modifications=args.allow_local_modifications,
        )
    except (OSError, RuntimeError, ValueError) as error:
        print(f"FAIL source bootstrap: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
