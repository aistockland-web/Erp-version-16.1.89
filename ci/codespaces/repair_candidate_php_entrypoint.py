#!/usr/bin/env python3
"""Create a separately hashed .15 candidate with one proven PHP-tag defect repaired."""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
import os
import re
import stat
import tempfile
import zipfile
from pathlib import Path, PurePosixPath
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
LOCK_PATH = ROOT / "ci" / "codespaces" / "candidate-packages.json"
TARGET = "stockland-erp-app/includes/class-stockland-schema-contract.php"
OUTPUT_NAME = "STOCKLAND_ERP_16.2.15_REPAIRED_CANDIDATE_WORDPRESS_INSTALLER.zip"


def digest_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def validate_path(name: str) -> None:
    if not name or "\\" in name or name.startswith("/") or re.match(r"^[A-Za-z]:", name):
        raise ValueError(f"unsafe archive member path: {name!r}")
    parts = name.rstrip("/").split("/")
    if any(part in ("", ".", "..") for part in parts):
        raise ValueError(f"unsafe archive path component: {name!r}")
    if not name.startswith("stockland-erp-app/"):
        raise ValueError(f"unexpected plugin root: {name!r}")


def repair_archive(source: Path, output: Path, expected_sha256: str, expected_size: int) -> dict[str, Any]:
    source = Path(source)
    output = Path(output)
    if not source.is_file():
        raise FileNotFoundError(f"locked .15 source archive is missing: {source}")
    actual_sha = digest_file(source)
    actual_size = source.stat().st_size
    if actual_sha != expected_sha256 or actual_size != expected_size:
        raise ValueError(
            f"original .15 identity mismatch: sha={actual_sha}, bytes={actual_size}; "
            f"expected sha={expected_sha256}, bytes={expected_size}"
        )
    if output.exists():
        raise FileExistsError(f"refusing to overwrite a prior repaired artifact: {output}")
    output.parent.mkdir(parents=True, exist_ok=True)

    temporary: Path | None = None
    before_target_sha = ""
    after_target_sha = ""
    member_count = 0
    try:
        with zipfile.ZipFile(source, "r") as original:
            bad = original.testzip()
            if bad is not None:
                raise ValueError(f"original .15 ZIP CRC failure in {bad}")
            infos = original.infolist()
            names = [item.filename for item in infos]
            file_names = [item.filename for item in infos if not item.is_dir()]
            if len(names) != len(set(names)):
                raise ValueError("original .15 ZIP contains duplicate member paths")
            if file_names.count(TARGET) != 1:
                raise ValueError(f"expected exactly one repair target, found {file_names.count(TARGET)}")
            for info in infos:
                validate_path(info.filename)
                mode = (info.external_attr >> 16) & 0xFFFF
                if stat.S_ISLNK(mode):
                    raise ValueError(f"symlink not allowed in installer: {info.filename}")
                if info.file_size > 32 * 1024 * 1024:
                    raise ValueError(f"oversized ZIP member: {info.filename}")
            roots = {PurePosixPath(name.rstrip("/")).parts[0] for name in file_names}
            if roots != {"stockland-erp-app"}:
                raise ValueError(f"expected one stockland-erp-app root, found {sorted(roots)}")

            original_target = original.read(TARGET)
            before_target_sha = hashlib.sha256(original_target).hexdigest()
            payload = original_target[3:] if original_target.startswith(bytes.fromhex("efbbbf")) else original_target
            if b"<?php" in payload or b"<?=" in payload:
                raise ValueError("repair refused: target already contains a PHP opening tag; inspect new source manually")
            if not payload.startswith(b"/**"):
                raise ValueError("repair refused: target no longer begins with the audited PHP doc-comment signature")
            repaired_target = b"<?php\n" + payload
            if not repaired_target.startswith(b"<?php\n/**"):
                raise ValueError("repair did not produce the expected PHP opening-tag prefix")
            after_target_sha = hashlib.sha256(repaired_target).hexdigest()

            with tempfile.NamedTemporaryFile(
                prefix=output.name + ".", suffix=".tmp", dir=output.parent, delete=False
            ) as temporary_file:
                temporary = Path(temporary_file.name)

            with zipfile.ZipFile(temporary, "w", allowZip64=True) as rebuilt:
                rebuilt.comment = original.comment
                for info in infos:
                    data = repaired_target if info.filename == TARGET else original.read(info.filename)
                    rebuilt.writestr(copy.copy(info), data)

            with zipfile.ZipFile(temporary, "r") as candidate:
                bad = candidate.testzip()
                if bad is not None:
                    raise ValueError(f"repaired candidate ZIP CRC failure in {bad}")
                if candidate.namelist() != names:
                    raise ValueError("repair changed ZIP member order or names")
                for name in names:
                    if name == TARGET:
                        if candidate.read(name) != repaired_target:
                            raise ValueError("repaired PHP target does not match intended bytes")
                    elif candidate.read(name) != original.read(name):
                        raise ValueError(f"repair unexpectedly changed another ZIP member: {name}")
                member_count = len(file_names)

        os.replace(temporary, output)
        os.chmod(output, 0o644)
        temporary = None
    finally:
        if temporary is not None:
            try:
                temporary.unlink()
            except FileNotFoundError:
                pass

    new_sha = digest_file(output)
    new_size = output.stat().st_size
    return {
        "source_archive": source.name,
        "source_sha256": actual_sha,
        "source_bytes": actual_size,
        "repaired_archive": output.name,
        "repaired_sha256": new_sha,
        "repaired_bytes": new_size,
        "zip_file_count": member_count,
        "changed_member": TARGET,
        "changed_member_sha256_before": before_target_sha,
        "changed_member_sha256_after": after_target_sha,
        "changed_members": [TARGET],
        "zip_crc": "PASS",
        "non_target_members_byte_identical": True,
        "source_archive_mutated": False,
    }



def workspace_candidate_lock(
    baseline: dict[str, Any],
    selected: dict[str, Any],
    output: Path,
    sidecar: Path,
    repository_root: Path = ROOT,
) -> dict[str, Any]:
    """Build a second lock with repo-relative paths for the mounted Codespaces checkout."""
    root = Path(repository_root).resolve()
    try:
        archive_relative = Path(output).resolve().relative_to(root)
        sidecar_relative = Path(sidecar).resolve().relative_to(root)
    except ValueError as exc:
        raise ValueError("workspace repair artifact and sidecar must live inside the repository") from exc
    repaired = dict(selected)
    repaired.update({
        "role": "repaired_selected_update_candidate",
        "archive": archive_relative.as_posix(),
        "sidecar": sidecar_relative.as_posix(),
    })
    return {"schema_version": 1, "packages": [dict(baseline), repaired]}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-dir", type=Path, default=ROOT / "artifacts" / "codespaces")
    parser.add_argument("--lock-output", type=Path, required=True)
    args = parser.parse_args()

    try:
        lock = json.loads(LOCK_PATH.read_text(encoding="utf-8"))
        if lock.get("schema_version") != 1:
            raise ValueError("unsupported candidate-package lock schema")
        packages = lock.get("packages", [])
        baseline = next((item for item in packages if item.get("release") == "16.2.13"), None)
        selected = next((item for item in packages if item.get("release") == "16.2.15"), None)
        if baseline is None or selected is None or len(packages) != 2:
            raise ValueError("package lock must contain exactly .13 baseline and .15 selected candidate")

        source = Path(selected["archive"])
        if not source.is_absolute():
            source = ROOT / source
        source_sidecar = Path(selected["sidecar"])
        if not source_sidecar.is_absolute():
            source_sidecar = ROOT / source_sidecar
        if not source_sidecar.is_file():
            raise FileNotFoundError(f"original .15 sidecar is missing: {source_sidecar}")
        fields = source_sidecar.read_text(encoding="utf-8").strip().split(maxsplit=1)
        if len(fields) != 2 or fields[0].lower() != selected["sha256"] or fields[1].strip() != source.name:
            raise ValueError("original .15 sidecar does not match the original package lock")

        output_dir = args.output_dir
        output = output_dir / OUTPUT_NAME
        result = repair_archive(source, output, selected["sha256"], int(selected["size_bytes"]))
        sidecar = output.with_name(output.name + ".sha256")
        sidecar.write_text(result["repaired_sha256"] + "  " + output.name + "\n", encoding="utf-8")
        os.chmod(sidecar, 0o644)

        receipt_path = output_dir / "repair-receipt.json"
        receipt_path.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
        os.chmod(receipt_path, 0o644)

        repaired_package = dict(selected)
        repaired_package.update({
            "role": "repaired_selected_update_candidate",
            "archive": str(output.resolve()),
            "sidecar": str(sidecar.resolve()),
            "sha256": result["repaired_sha256"],
            "size_bytes": result["repaired_bytes"],
        })
        args.lock_output.parent.mkdir(parents=True, exist_ok=True)
        args.lock_output.write_text(
            json.dumps({"schema_version": 1, "packages": [baseline, repaired_package]}, indent=2) + "\n",
            encoding="utf-8",
        )

        workspace_lock_path = output_dir / "repaired-candidate-lock.json"
        workspace_selected = dict(selected)
        workspace_selected.update({
            "sha256": result["repaired_sha256"],
            "size_bytes": result["repaired_bytes"],
        })
        workspace_lock = workspace_candidate_lock(baseline, workspace_selected, output, sidecar)
        workspace_lock_path.write_text(json.dumps(workspace_lock, indent=2) + "\n", encoding="utf-8")
        os.chmod(workspace_lock_path, 0o644)
        result["workspace_lock"] = workspace_lock_path.name
        receipt_path.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
        print("PASS " + json.dumps(result, sort_keys=True))
        return 0
    except (OSError, ValueError, KeyError, json.JSONDecodeError, zipfile.BadZipFile) as exc:
        raise SystemExit("FAIL .15 candidate repair: " + str(exc)) from exc


if __name__ == "__main__":
    raise SystemExit(main())
