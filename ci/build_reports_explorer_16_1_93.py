#!/usr/bin/env python3
"""Build a review-only 16.1.93 installer from the checksum-locked 16.1.92 artifact."""
from __future__ import annotations
import base64, gzip, hashlib, json, shutil, subprocess, tempfile, zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]
BASELINE = ROOT / "STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip"
PAYLOAD = ROOT / "ci/patches/reports-explorer-16.1.93.patch.gz.b64"
EXPECTED_SHA256 = "adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd"
EXPECTED_SIZE = 1_113_446
OUTPUT = ROOT / "dist" / "STOCKLAND_ERP_16.1.93_REPORTS_EXPLORER_CANDIDATE.zip"

def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def safe_names(zf: zipfile.ZipFile) -> list[str]:
    infos = [item for item in zf.infolist() if not item.is_dir()]
    names = [item.filename for item in infos]
    if len(names) != 75 or len(set(names)) != 75:
        raise ValueError("Baseline must contain exactly 75 unique files")
    for name in names:
        p = PurePosixPath(name)
        if p.is_absolute() or ".." in p.parts or not p.parts or p.parts[0] != "stockland-erp-app":
            raise ValueError(f"Unsafe or unexpected archive path: {name}")
    return names

def build() -> dict:
    raw = BASELINE.read_bytes()
    if len(raw) != EXPECTED_SIZE or sha256(raw) != EXPECTED_SHA256:
        raise ValueError("Baseline ZIP does not match the audited 16.1.92 identity")
    patch_bytes = gzip.decompress(base64.b64decode("".join(PAYLOAD.read_text(encoding="ascii").split()), validate=True))
    if not patch_bytes.startswith(b"--- a/stockland-erp-app/") or b"+++ b/stockland-erp-app/" not in patch_bytes:
        raise ValueError("Decoded patch header validation failed")
    with tempfile.TemporaryDirectory(prefix="stockland-16-1-93-") as td:
        tmp = Path(td)
        with zipfile.ZipFile(BASELINE) as zin:
            bad = zin.testzip()
            if bad:
                raise ValueError(f"Baseline CRC failure: {bad}")
            infos = [item for item in zin.infolist() if not item.is_dir()]
            names = safe_names(zin)
            before = {name: zin.read(name) for name in names}
        for name in names:
            target = tmp / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(before[name])
        patch_file = tmp / "reports-explorer-16.1.93.patch"
        patch_file.write_bytes(patch_bytes)
        subprocess.run(["patch", "--batch", "--forward", "--dry-run", "-p1", "-i", str(patch_file)], cwd=tmp, check=True, capture_output=True, text=True)
        subprocess.run(["patch", "--batch", "--forward", "-p1", "-i", str(patch_file)], cwd=tmp, check=True, capture_output=True, text=True)
        patched = {name: (tmp / name).read_bytes() for name in names}
        OUTPUT.parent.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(OUTPUT, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
            for info in infos:
                zi = zipfile.ZipInfo(info.filename, date_time=info.date_time)
                zi.compress_type = zipfile.ZIP_DEFLATED
                zi.external_attr = info.external_attr
                zi.create_system = info.create_system
                zi.flag_bits = info.flag_bits
                zout.writestr(zi, patched[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(OUTPUT) as out:
        bad = out.testzip()
        if bad:
            raise ValueError(f"Candidate CRC failure: {bad}")
        out_names = [item.filename for item in out.infolist() if not item.is_dir()]
        if out_names != names:
            raise ValueError("Candidate package inventory/order differs from audited baseline")
    receipt = {
        "baseline_release": "16.1.92",
        "baseline_sha256": EXPECTED_SHA256,
        "candidate_release": "16.1.93",
        "candidate_path": str(OUTPUT.relative_to(ROOT)),
        "candidate_sha256": sha256(OUTPUT.read_bytes()),
        "candidate_bytes": OUTPUT.stat().st_size,
        "files": len(names),
        "live_deployment_performed": False,
        "schema_changed": False,
        "css_source_or_compiled_bytes_changed": False,
        "note": "Build artifact only. Target-host acceptance and authenticated role tests remain pending."
    }
    (OUTPUT.parent / "BUILD_RECEIPT.json").write_text(json.dumps(receipt, indent=2) + "\n", encoding="utf-8")
    (OUTPUT.parent / "reports-explorer-16.1.93.patch").write_bytes(patch_bytes)
    (OUTPUT.parent / "candidate.sha256").write_text(receipt["candidate_sha256"] + "  " + OUTPUT.name + "\n", encoding="ascii")
    print(json.dumps(receipt, indent=2))
    return receipt

if __name__ == "__main__":
    build()
