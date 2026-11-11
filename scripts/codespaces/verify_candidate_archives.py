#!/usr/bin/env python3
"""Verify Stockland ERP packages, complete file manifests and Git artifact provenance."""
from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import stat
import subprocess
import sys
import zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
REPOSITORY = "aistockland-web/Erp-version-16.1.89"
REPOSITORY_URL = f"https://github.com/{REPOSITORY}"
PACKAGES = {
    "16.2.13": {
        "archive": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip",
        "sidecar": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER.zip.sha256",
        "sha256": "74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13",
        "size_bytes": 2188684,
        "entries": 113,
        "php_files": 80,
        "logical_sidecar_name": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip",
        "artifact_commit_sha": "1c3b743296b7a1f4e3b8a0b481e6883b5fed4154",
        "archive_git_blob_sha": "908bbff20399438c76a15233ecd1b4bc5957fe58",
    },
    "16.2.15": {
        "archive": "STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip",
        "sidecar": "STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip.sha256",
        "sha256": "8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df",
        "size_bytes": 2206932,
        "artifact_commit_sha": "6dd5263865cf2d6e4f64c133b9a3ff7212e60f16",
        "archive_git_blob_sha": "01b7e30c3d43a49260ed1fd9072a3015d05dffd0",
    },
}


def git_output(*args: str) -> str:
    result = subprocess.run(
        ["git", *args], cwd=ROOT, capture_output=True, text=True, check=False
    )
    if result.returncode:
        detail = result.stderr.strip() or result.stdout.strip()
        raise RuntimeError(f"Git provenance command failed ({' '.join(args)}): {detail}")
    return result.stdout.strip()


def verify_git_artifact(version: str, spec: dict) -> dict:
    """Bind the package bytes to an immutable Git commit and blob in this repository."""
    commit = spec["artifact_commit_sha"]
    head = git_output("rev-parse", "HEAD")
    if len(head) != 40:
        raise RuntimeError(f"{version}: current Git HEAD is not a full commit SHA")
    git_output("merge-base", "--is-ancestor", commit, head)
    committed_blob = git_output("rev-parse", f"{commit}:{spec['archive']}")
    current_blob = git_output("rev-parse", f"{head}:{spec['archive']}")
    expected_blob = spec["archive_git_blob_sha"]
    if committed_blob != expected_blob:
        raise RuntimeError(
            f"{version}: pinned artifact commit resolves to blob {committed_blob}, "
            f"not {expected_blob}"
        )
    if current_blob != expected_blob:
        raise RuntimeError(
            f"{version}: current branch package differs from the pinned Git artifact blob"
        )
    return {
        "repository": REPOSITORY,
        "repository_url": REPOSITORY_URL,
        "artifact_commit_sha": commit,
        "archive_git_blob_sha": expected_blob,
        "verified_head_sha": head,
    }


def digest(path: Path) -> str:
    hasher = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            hasher.update(chunk)
    return hasher.hexdigest()


def inspect(version: str, spec: dict, extract_root: Path | None) -> dict:
    archive = ROOT / spec["archive"]
    sidecar = ROOT / spec["sidecar"]
    if not archive.is_file() or not sidecar.is_file():
        raise RuntimeError(f"{version}: archive or sidecar missing")

    fields = sidecar.read_text(encoding="utf-8").strip().split(maxsplit=1)
    if len(fields) != 2 or len(fields[0]) != 64:
        raise RuntimeError(f"{version}: malformed SHA-256 sidecar")
    expected_name = spec.get("logical_sidecar_name", archive.name)
    if fields[1] != expected_name:
        raise RuntimeError(f"{version}: unexpected sidecar filename {fields[1]!r}")

    actual_sha = digest(archive)
    if fields[0].lower() != spec["sha256"] or actual_sha != spec["sha256"]:
        raise RuntimeError(
            f"{version}: SHA-256 mismatch; sidecar={fields[0]}, "
            f"actual={actual_sha}, pin={spec['sha256']}"
        )
    actual_size = archive.stat().st_size
    if actual_size != spec["size_bytes"]:
        raise RuntimeError(f"{version}: byte size mismatch {actual_size}")

    git_provenance = verify_git_artifact(version, spec)
    names: list[str] = []
    hashes: dict[str, str] = {}
    counts = {"php": 0, "js": 0, "json": 0, "css": 0, "docx": 0}
    with zipfile.ZipFile(archive) as package:
        infos = package.infolist()
        for info in infos:
            name = info.filename
            if not name or name.startswith(("/", "\\\\")) or "\\\\" in name:
                raise RuntimeError(f"{version}: unsafe path {name!r}")
            path = PurePosixPath(name)
            if path.is_absolute() or any(part in ("", ".", "..") for part in path.parts):
                raise RuntimeError(f"{version}: traversal path {name!r}")
            if not path.parts or path.parts[0] != "stockland-erp-app":
                raise RuntimeError(f"{version}: unexpected plugin root/path {name!r}")
            mode = (info.external_attr >> 16) & 0xFFFF
            if stat.S_ISLNK(mode):
                raise RuntimeError(f"{version}: symlink forbidden: {name}")
            names.append(name)
            if info.is_dir():
                continue
            suffix = path.suffix.lower()
            counts["php"] += suffix == ".php"
            counts["js"] += suffix in (".js", ".mjs")
            counts["json"] += suffix == ".json"
            counts["css"] += suffix == ".css"
            counts["docx"] += suffix == ".docx"
            hashes[str(path.relative_to("stockland-erp-app"))] = hashlib.sha256(
                package.read(info)
            ).hexdigest()

        duplicates = sorted({name for name in names if names.count(name) > 1})
        if duplicates:
            raise RuntimeError(f"{version}: duplicate ZIP paths {duplicates[:10]}")
        bad_member = package.testzip()
        if bad_member:
            raise RuntimeError(f"{version}: CRC failure at {bad_member}")
        if {PurePosixPath(name).parts[0] for name in names} != {"stockland-erp-app"}:
            raise RuntimeError(f"{version}: single plugin root check failed")
        if spec.get("entries") is not None and len(infos) != spec["entries"]:
            raise RuntimeError(f"{version}: entry count mismatch {len(infos)}")
        if spec.get("php_files") is not None and counts["php"] != spec["php_files"]:
            raise RuntimeError(f"{version}: PHP file count mismatch {counts['php']}")

        if extract_root is not None:
            target = extract_root / version.replace(".", "_")
            if target.exists():
                shutil.rmtree(target)
            target.mkdir(parents=True, exist_ok=True)
            for info in infos:
                relative = PurePosixPath(info.filename)
                if info.is_dir():
                    target.joinpath(*relative.parts).mkdir(parents=True, exist_ok=True)
                else:
                    destination = target.joinpath(*relative.parts)
                    destination.parent.mkdir(parents=True, exist_ok=True)
                    destination.write_bytes(package.read(info))

    result = {
        "version": version,
        "filename": archive.name,
        "sidecar_filename": sidecar.name,
        "sidecar_logical_filename": fields[1],
        "size_bytes": actual_size,
        "sha256": actual_sha,
        "zip_entries_including_directories": len(names),
        "file_count": len(hashes),
        "php_files": counts["php"],
        "javascript_files": counts["js"],
        "json_files": counts["json"],
        "css_files": counts["css"],
        "docx_files": counts["docx"],
        "plugin_root": "stockland-erp-app/",
        "zip_crc": "PASS",
        "duplicate_paths": 0,
        "unsafe_paths": 0,
        "files": hashes,
        "artifact_provenance": git_provenance,
    }
    return result


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--report", type=Path, default=ROOT / "artifacts/codespaces/package-intake.json"
    )
    parser.add_argument("--extract-root", type=Path)
    args = parser.parse_args()

    extraction = args.extract_root.resolve() if args.extract_root else None
    if extraction:
        extraction.mkdir(parents=True, exist_ok=True)
    results = {
        version: inspect(version, spec, extraction)
        for version, spec in PACKAGES.items()
    }
    baseline = results["16.2.13"]["files"]
    candidate = results["16.2.15"]["files"]
    common = set(baseline) & set(candidate)
    changed = sorted(path for path in common if baseline[path] != candidate[path])
    results["comparison"] = {
        "same_paths": len(common) - len(changed),
        "changed_shared_paths": changed,
        "only_in_16_2_13": sorted(set(baseline) - set(candidate)),
        "only_in_16_2_15": sorted(set(candidate) - set(baseline)),
        "note": (
            "Path/hash comparison is structural only; artifact Git provenance is "
            "recorded separately and does not prove runtime behavior."
        ),
    }
    results["disposition"] = (
        "PACKAGE_IDENTITY_ARCHIVE_SAFETY_AND_GIT_PROVENANCE_PASS; "
        "BUSINESS_WORKFLOW_ACCEPTANCE_RECORDED_SEPARATELY"
    )
    for version in PACKAGES:
        item = results[version]
        print(
            f"PASS {version}: {item['size_bytes']} bytes; SHA-256 {item['sha256']}; "
            f"{item['file_count']} files; PHP {item['php_files']}; "
            f"Git artifact {item['artifact_provenance']['artifact_commit_sha']}; "
            "ZIP CRC PASS"
        )
    print(
        f"COMPARE: {results['comparison']['same_paths']} unchanged; "
        f"{len(changed)} changed shared; "
        f"{len(results['comparison']['only_in_16_2_13'])} .13-only; "
        f"{len(results['comparison']['only_in_16_2_15'])} .15-only"
    )
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(
        json.dumps(results, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )
    print(f"Report: {args.report}")


if __name__ == "__main__":
    try:
        main()
    except (OSError, zipfile.BadZipFile, RuntimeError, KeyError, ValueError) as error:
        print(f"FAIL archive intake: {error}", file=sys.stderr)
        raise SystemExit(1)
