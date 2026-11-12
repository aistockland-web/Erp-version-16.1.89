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
import subprocess
import stat
import zipfile
from pathlib import Path, PurePosixPath

BASELINE = {
    "archive": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip",
    "sha256": "74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13",
    "size_bytes": 2188684,
    "php_files": 80,
    "sidecar": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER.zip.sha256",
    "sidecar_logical_filename": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip",
    "repository": "aistockland-web/Erp-version-16.1.89",
    "artifact_commit_sha": "1c3b743296b7a1f4e3b8a0b481e6883b5fed4154",
    "archive_git_blob_sha": "908bbff20399438c76a15233ecd1b4bc5957fe58",
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


def git_output(*args: str) -> str:
    result = subprocess.run(
        ["git", *args], cwd=ROOT, capture_output=True, text=True, check=False
    )
    if result.returncode:
        detail = result.stderr.strip() or result.stdout.strip()
        raise RuntimeError(f"Git provenance command failed ({' '.join(args)}): {detail}")
    return result.stdout.strip()


def verify_git_artifact_provenance(report: dict) -> dict:
    """Verify the pinned ZIP's immutable Git object and its ancestry in this checkout."""
    recorded = report.get("artifact_provenance")
    if not isinstance(recorded, dict):
        raise RuntimeError(".13 intake report has no Git artifact provenance record")
    expected = {
        "repository": BASELINE["repository"],
        "artifact_commit_sha": BASELINE["artifact_commit_sha"],
        "archive_git_blob_sha": BASELINE["archive_git_blob_sha"],
    }
    for key, value in expected.items():
        if recorded.get(key) != value:
            raise RuntimeError(f".13 Git artifact provenance mismatch for {key}")
    verified_head = recorded.get("verified_head_sha")
    if not isinstance(verified_head, str) or not re.fullmatch(r"[0-9a-f]{40}", verified_head):
        raise RuntimeError(".13 provenance does not contain a full verified Git HEAD SHA")
    current_head = git_output("rev-parse", "HEAD")
    if verified_head != current_head:
        raise RuntimeError("Package intake report was generated for a different Git HEAD")
    git_output("merge-base", "--is-ancestor", BASELINE["artifact_commit_sha"], current_head)
    committed_blob = git_output(
        "rev-parse", f"{BASELINE['artifact_commit_sha']}:{BASELINE['archive']}"
    )
    current_blob = git_output("rev-parse", f"{current_head}:{BASELINE['archive']}")
    if committed_blob != BASELINE["archive_git_blob_sha"] or current_blob != committed_blob:
        raise RuntimeError(".13 archive Git blob does not match its pinned artifact provenance")
    return expected


def verify_archive_matches_report(report: dict) -> None:
    """Recompute archive SHA, sidecar, ZIP safety and every member hash independently."""
    archive_path = ROOT / BASELINE["archive"]
    sidecar_path = ROOT / BASELINE["sidecar"]
    if not archive_path.is_file() or not sidecar_path.is_file():
        raise RuntimeError("Pinned .13 archive or SHA-256 sidecar is missing from the repository")
    if archive_path.stat().st_size != BASELINE["size_bytes"]:
        raise RuntimeError("Pinned .13 archive byte size changed")
    if sha256_file(archive_path) != BASELINE["sha256"]:
        raise RuntimeError("Pinned .13 archive SHA-256 changed")
    sidecar_fields = sidecar_path.read_text(encoding="utf-8").strip().split(maxsplit=1)
    if sidecar_fields != [BASELINE["sha256"], BASELINE["sidecar_logical_filename"]]:
        raise RuntimeError("Pinned .13 sidecar digest or logical archive filename changed")

    actual = {}
    with zipfile.ZipFile(archive_path) as package:
        if package.testzip() is not None:
            raise RuntimeError("Pinned .13 archive CRC verification failed")
        names = [info.filename for info in package.infolist()]
        if len(names) != len(set(names)):
            raise RuntimeError("Pinned .13 archive contains duplicate ZIP paths")
        for info in package.infolist():
            name = info.filename
            if not name or "\\\\" in name or name.startswith(("/", "\\\\")):
                raise RuntimeError(f"Unsafe path in pinned .13 archive: {name!r}")
            relative = PurePosixPath(name)
            if relative.is_absolute() or any(part in ("", ".", "..") for part in relative.parts):
                raise RuntimeError(f"Unsafe path in pinned .13 archive: {name!r}")
            if not relative.parts or relative.parts[0] != "stockland-erp-app":
                raise RuntimeError(f"Unexpected root in pinned .13 archive: {name!r}")
            if stat.S_ISLNK((info.external_attr >> 16) & 0xFFFF):
                raise RuntimeError(f"Symlink in pinned .13 archive: {name!r}")
            if info.is_dir():
                continue
            actual[str(relative.relative_to("stockland-erp-app"))] = hashlib.sha256(
                package.read(info)
            ).hexdigest()
    if actual != report["files"]:
        missing = sorted(set(report["files"]) - set(actual))[:8]
        extra = sorted(set(actual) - set(report["files"]))[:8]
        changed = sorted(name for name in set(actual) & set(report["files"]) if actual[name] != report["files"][name])[:8]
        raise RuntimeError(
            "Intake report file manifest does not match the actual pinned ZIP; "
            f"missing={missing}, extra={extra}, changed={changed}"
        )


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
    verify_git_artifact_provenance(report)
    verify_archive_matches_report(report)
    return report


def assert_source_tree_safe(source: Path) -> None:
    """Reject unsafe filesystem objects before deciding whether ordinary drift is tolerable."""
    if source.is_symlink() or not source.is_dir():
        raise RuntimeError(f"Source directory is absent or unsafe: {source}")
    for item in source.rglob("*"):
        if item.is_symlink():
            raise RuntimeError(f"Symlink in source tree: {item}")
    entry = source / "stockland-inventory.php"
    if entry.is_symlink() or not entry.is_file():
        raise RuntimeError("WordPress entry point stockland-inventory.php is missing or unsafe")


def verify_source_tree(source: Path, report: dict) -> None:
    assert_source_tree_safe(source)
    actual = {}
    for item in source.rglob("*"):
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
        "repository": BASELINE["repository"],
        "artifact_commit_sha": BASELINE["artifact_commit_sha"],
        "archive_git_blob_sha": BASELINE["archive_git_blob_sha"],
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
            # A marker establishes provenance, not that the current working tree is unchanged.
            # Reject unsafe filesystem objects unconditionally before handling ordinary code drift.
            assert_source_tree_safe(destination)
            try:
                verify_source_tree(destination, report)
            except RuntimeError as error:
                if os.environ.get("STOCKLAND_SOURCE_STRICT", "").strip() == "1":
                    raise RuntimeError(
                        "Existing marked editable source has drifted from the exact .13 intake manifest "
                        "while STOCKLAND_SOURCE_STRICT=1 is enabled. All files were preserved. "
                        f"Verification detail: {error}"
                    ) from error
                if allow_local_modifications:
                    print(
                        "WARNING: preserving an explicitly allowed locally modified source tree. "
                        "It is not verified as byte-identical to the pinned .13 package."
                    )
                    return False
                print(
                    "WARNING: editable source differs from the exact .13 intake manifest; "
                    "preserving all files without overwriting them. Set STOCKLAND_SOURCE_STRICT=1 "
                    f"to fail instead. Verification detail: {error}"
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
