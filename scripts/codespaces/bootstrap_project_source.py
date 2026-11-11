#!/usr/bin/env python3
"""Safely materialize the verified Stockland 16.2.13 app as editable source."""
from __future__ import annotations
import argparse, hashlib, json, os, re, shutil, sys, tempfile
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
        raise RuntimeError(".13 intake report has no complete per-file SHA-256 manifest")
    for name, digest in files.items():
        item = PurePosixPath(name)
        if not name or item.is_absolute() or "\\" in name or any(part in ("", ".", "..") for part in item.parts):
            raise RuntimeError(f"Unsafe source path in intake manifest: {name!r}")
        if not isinstance(digest, str) or not re.fullmatch(r"[0-9a-f]{64}", digest):
            raise RuntimeError(f"Invalid source digest for {name!r}")
    return report


def verify_source_tree(source: Path, report: dict) -> None:
    if source.is_symlink() or not source.is_dir():
        raise RuntimeError(f"Verified .13 staging source is absent/unsafe: {source}")
    actual = {}
    for item in source.rglob("*"):
        if item.is_symlink():
            raise RuntimeError(f"Symlink in staged source: {item}")
        if item.is_file():
            actual[item.relative_to(source).as_posix()] = sha256_file(item)
    expected = report["files"]
    if set(actual) != set(expected):
        raise RuntimeError(
            f"Source inventory differs from intake manifest; missing={sorted(set(expected)-set(actual))[:8]}, "
            f"extra={sorted(set(actual)-set(expected))[:8]}"
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


def materialize(stage_root: Path, report_path: Path, destination: Path, marker: Path) -> bool:
    identity = {
        "source_version": "16.2.13",
        "archive": BASELINE["archive"],
        "archive_sha256": BASELINE["sha256"],
        "generated_by": "scripts/codespaces/bootstrap_project_source.py",
    }
    if destination.exists() or destination.is_symlink():
        if destination.is_symlink() or not destination.is_dir():
            raise RuntimeError(f"Existing source destination is unsafe: {destination}")
        if not marker.is_file():
            raise RuntimeError(
                f"Refusing to overwrite pre-existing {destination}. Inspect and back it up manually; "
                "the importer never deletes or replaces an unmarked folder."
            )
        try:
            current = json.loads(marker.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError) as error:
            raise RuntimeError(f"Invalid source provenance marker {marker}: {error}") from error
        if any(current.get(key) != value for key, value in identity.items()):
            raise RuntimeError("Existing source provenance does not match the pinned .13 baseline")
        if not re.fullmatch(r"[0-9a-f]{64}", str(current.get("file_manifest_sha256", ""))):
            raise RuntimeError("Source provenance has no valid per-file manifest digest")
        if not isinstance(current.get("file_count"), int) or current["file_count"] <= 0:
            raise RuntimeError("Source provenance file count is invalid")
        if not (destination / "stockland-inventory.php").is_file():
            raise RuntimeError("Marked source folder is incomplete; refusing to overwrite")
        print(f"PASS: preserving existing editable source without overwrite: {destination}")
        return False

    report = read_baseline_report(report_path)
    staged = stage_root / "16_2_13" / "stockland-erp-app"
    verify_source_tree(staged, report)
    destination.parent.mkdir(parents=True, exist_ok=True)
    provenance = {
        **identity,
        "file_manifest_sha256": manifest_digest(report["files"]),
        "file_count": len(report["files"]),
    }
    with tempfile.TemporaryDirectory(prefix=".stockland-source-import-", dir=destination.parent) as temp:
        temp_root = Path(temp)
        staged_copy = temp_root / "stockland-erp-app"
        shutil.copytree(staged, staged_copy, symlinks=False)
        verify_source_tree(staged_copy, report)
        marker_tmp = temp_root / "source-origin.json"
        marker_tmp.write_text(json.dumps(provenance, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        marker.parent.mkdir(parents=True, exist_ok=True)
        if marker.exists():
            current = json.loads(marker.read_text(encoding="utf-8"))
            if current != provenance:
                raise RuntimeError(f"Existing source marker conflicts with .13 manifest: {marker}")
        else:
            os.replace(marker_tmp, marker)
        if destination.exists():
            raise RuntimeError(f"Destination appeared during import; no files were replaced: {destination}")
        os.rename(staged_copy, destination)
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
    args = parser.parse_args()
    try:
        materialize(args.stage_root, args.intake_report, args.destination, args.marker)
    except (OSError, RuntimeError, ValueError) as error:
        print(f"FAIL source bootstrap: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
