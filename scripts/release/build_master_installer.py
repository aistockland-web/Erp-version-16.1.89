#!/usr/bin/env python3
"""Deterministically build the Stockland ERP one-click WordPress installer ZIP from the
canonical source tree (src/stockland-erp-app). Same source bytes -> same ZIP bytes.

Fails if RELEASE_MANIFEST.json does not match the tree, if any PHP file lacks an
opening tag, or if the plugin header/constant versions disagree.
"""
from __future__ import annotations
import argparse, hashlib, json, os, re, subprocess, sys, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / "src" / "stockland-erp-app"
FIXED_TIME = (2026, 11, 11, 0, 0, 0)


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def canonical_source_commit() -> str:
    """Return the exact Git checkout commit used to build this package."""
    try:
        result = subprocess.run(
            ["git", "rev-parse", "--verify", "HEAD"],
            cwd=ROOT,
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )
    except (OSError, subprocess.CalledProcessError) as exc:
        sys.exit(f"FAIL: cannot resolve canonical source commit: {exc}")
    commit = result.stdout.strip().lower()
    if not re.fullmatch(r"[0-9a-f]{40}", commit):
        sys.exit("FAIL: canonical source commit is not a full 40-character SHA")
    github_sha = os.environ.get("GITHUB_SHA", "").strip().lower()
    if github_sha and github_sha != commit:
        sys.exit(f"FAIL: Git checkout {commit} differs from GITHUB_SHA {github_sha}")
    return commit


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
    source_commit = canonical_source_commit()
    mp = SRC / "RELEASE_MANIFEST.json"
    if args.update_manifest:
        m = json.loads(mp.read_text())
        lineage = m.setdefault("source_lineage", {})
        lineage["canonical_source_commit"] = source_commit
        lineage["provenance_note"] = (
            "The canonical editable source is maintained in the repository. "
            "canonical_source_commit records the exact Git checkout used for this build "
            "(the pull-request merge commit in PR verification). The one-pack installer "
            "is deterministically generated from that checkout; CI records the workflow run, "
            "artifact ID and independently verifiable SHA-256. A GitHub Release/tag is not "
            "claimed unless a release ID is separately recorded."
        )
        inv = [{"path": n, "bytes": len(data), "sha256": sha256(data)} for n, data in collect().items() if n != "RELEASE_MANIFEST.json"]
        m["file_inventory"] = {"count": len(inv), "manifest_excludes_itself": True, "files": inv}
        mp.write_text(json.dumps(m, indent=2))
    files = collect()
    manifest = json.loads(files["RELEASE_MANIFEST.json"])
    recorded_commit = manifest.get("source_lineage", {}).get("canonical_source_commit")
    if recorded_commit != source_commit:
        sys.exit(f"FAIL: RELEASE_MANIFEST canonical_source_commit {recorded_commit!r} != checkout {source_commit}")
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
