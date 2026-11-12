#!/usr/bin/env python3
"""Deterministically build the Stockland ERP one-click WordPress installer ZIP from the
canonical source tree (src/stockland-erp-app). Same source bytes -> same ZIP bytes.

Fails if RELEASE_MANIFEST.json does not match the tree, if any PHP file lacks an
opening tag, or if the plugin header/constant versions disagree.
"""
from __future__ import annotations
import argparse, hashlib, json, re, sys, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / "src" / "stockland-erp-app"
FIXED_TIME = (2026, 11, 11, 0, 0, 0)


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def collect() -> dict[str, bytes]:
    files = {}
    for p in sorted(SRC.rglob("*")):
        if p.is_symlink():
            sys.exit(f"FAIL: symlink in source: {p}")
        if p.is_file():
            files[p.relative_to(SRC).as_posix()] = p.read_bytes()
    return files


def preflight(files: dict[str, bytes]) -> str:
    entry = files["stockland-inventory.php"].decode()
    header = re.search(r"^\s*\*?\s*Version:\s*([0-9.]+)", entry, re.M).group(1)
    const = re.search(r"define\(\s*'STOCKLAND_VERSION',\s*'([0-9.]+)'", entry).group(1)
    if header != const:
        sys.exit(f"FAIL: version mismatch header={header} constant={const}")
    bad = [n for n, b in files.items() if n.endswith(".php") and not b.startswith(b"<?php")]
    if bad:
        sys.exit("FAIL: PHP files without an opening tag: " + ", ".join(bad))
    manifest = json.loads(files["RELEASE_MANIFEST.json"])
    listed = {f["path"]: f["sha256"] for f in manifest["file_inventory"]["files"]}
    actual = {n: sha256(b) for n, b in files.items() if n != "RELEASE_MANIFEST.json"}
    if listed != actual:
        diff = sorted(set(listed) ^ set(actual)) or sorted(k for k in actual if listed.get(k) != actual[k])
        sys.exit("FAIL: RELEASE_MANIFEST.json does not match source tree: " + ", ".join(diff[:10]))
    if manifest["application"]["version"] != header:
        sys.exit("FAIL: manifest version differs from plugin version")
    return header


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--out-dir", default=str(ROOT))
    ap.add_argument("--update-manifest", action="store_true", help="rewrite RELEASE_MANIFEST.json file inventory from the source tree first")
    args = ap.parse_args()
    if args.update_manifest:
        mp = SRC / "RELEASE_MANIFEST.json"
        m = json.loads(mp.read_text())
        inv = [{"path": n, "bytes": len(b), "sha256": sha256(b)} for n, b in collect().items() if n != "RELEASE_MANIFEST.json"]
        m["file_inventory"] = {"count": len(inv), "manifest_excludes_itself": True, "files": inv}
        mp.write_text(json.dumps(m, indent=2))
    files = collect()
    version = preflight(files)
    out = Path(args.out_dir) / f"STOCKLAND_ERP_{version}_MASTER_WORDPRESS_INSTALLER.zip"
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name, data in sorted(files.items()):
            info = zipfile.ZipInfo(f"stockland-erp-app/{name}", FIXED_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            info.create_system = 3
            z.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    digest = sha256(out.read_bytes())
    Path(str(out) + ".sha256").write_text(f"{digest}  {out.name}\n")
    print(f"PASS built {out.name}: {out.stat().st_size} bytes, {len(files)} files, SHA-256 {digest}")


if __name__ == "__main__":
    main()
