#!/usr/bin/env python3
"""Compare two exact 16.2.9 installer binaries for runtime PHP parity."""
from __future__ import annotations
import hashlib
import sys
import zipfile

def load_php(path: str) -> dict[str, bytes]:
    with zipfile.ZipFile(path) as archive:
        if archive.testzip() is not None:
            raise SystemExit(f"FAIL archive integrity: {path}")
        return {name: archive.read(name) for name in archive.namelist() if name.endswith(".php") and not name.endswith("/")}

def main() -> int:
    if len(sys.argv) != 3:
        raise SystemExit("usage: compare-candidates.py <selected-16.2.9.zip> <refreshed-16.2.9.zip>")
    selected, refreshed = map(load_php, sys.argv[1:])
    if set(selected) != set(refreshed):
        print(f"FAIL PHP path inventory changed; missing={sorted(set(selected)-set(refreshed))}; extra={sorted(set(refreshed)-set(selected))}")
        return 1
    changed = [name for name in sorted(selected) if selected[name] != refreshed[name]]
    if changed:
        print("FAIL runtime PHP differs between candidates:")
        for name in changed:
            print(f"  {name}: selected={hashlib.sha256(selected[name]).hexdigest()} refreshed={hashlib.sha256(refreshed[name]).hexdigest()}")
        return 1
    print(f"PASS: {len(selected)} PHP runtime files are byte-identical between selected and refreshed 16.2.9 installers.")
    print("Database migration characterization therefore exercises identical PHP runtime source.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
