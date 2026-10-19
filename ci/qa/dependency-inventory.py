#!/usr/bin/env python3
"""Inventory supported dependency manifests and bundled third-party assets.

A missing manifest is reported as a coverage limitation, never as proof that
the installer contains no third-party components.
"""
from __future__ import annotations
import json, re, sys
from pathlib import Path

if len(sys.argv) < 2:
    raise SystemExit("usage: dependency-inventory.py <extracted-plugin-root> [report.json]")
root = Path(sys.argv[1]).resolve()
out = Path(sys.argv[2]).resolve() if len(sys.argv) > 2 else Path("dependency-inventory.json")
release_path = root / "RELEASE_MANIFEST.json"
if not release_path.is_file():
    raise SystemExit("BLOCKED: release manifest missing from extracted plugin")
manifest = json.loads(release_path.read_text(encoding="utf-8"))
known = []
frontend = manifest.get("frontend", {})
bootstrap_version = str(frontend.get("bootstrap_version", ""))
bootstrap_path = root / "assets" / "bootstrap.min.css"
bootstrap_license = None
if bootstrap_path.is_file():
    prefix = bootstrap_path.read_bytes()[:8192].decode("utf-8", errors="replace")
    if re.search(r"(?i)MIT License|Licensed under MIT|license.*MIT|github\.com/twbs/bootstrap/blob/.*/LICENSE", prefix):
        bootstrap_license = "MIT"
    known.append({
        "name": "Bootstrap",
        "version": bootstrap_version or None,
        "purl": f"pkg:npm/bootstrap@{bootstrap_version}" if bootstrap_version else None,
        "license": bootstrap_license,
        "evidence_path": "assets/bootstrap.min.css",
        "sha384_sri": frontend.get("bootstrap_sri_sha384"),
        "git_blob_sha1": frontend.get("bootstrap_source_git_blob_sha1"),
        "classification": "bundled third-party asset",
    })

manifest_names = {
    "composer.json", "composer.lock", "package.json", "package-lock.json",
    "npm-shrinkwrap.json", "yarn.lock", "pnpm-lock.yaml", "bun.lockb",
    "bun.lock", "requirements.txt", "poetry.lock", "Pipfile.lock",
    "go.mod", "go.sum", "Cargo.toml", "Cargo.lock"
}
manifests = sorted(str(p.relative_to(root)) for p in root.rglob("*")
                   if p.is_file() and p.name in manifest_names)
lockfiles = [p for p in manifests if Path(p).name in {
    "composer.lock", "package-lock.json", "npm-shrinkwrap.json",
    "yarn.lock", "pnpm-lock.yaml", "bun.lockb", "bun.lock",
    "poetry.lock", "Pipfile.lock", "go.sum", "Cargo.lock"
}]
unresolved = []
if not bootstrap_version:
    unresolved.append("release manifest does not declare bundled Bootstrap version")
if not bootstrap_path.is_file():
    unresolved.append("bundled Bootstrap stylesheet missing")
elif bootstrap_license is None:
    unresolved.append("Bootstrap license notice was not found in the first 8 KiB; manually verify vendor license")
if not lockfiles:
    unresolved.append("no supported package lockfile found; dependency-review/lockfile CVE audit cannot establish transitive runtime dependencies from the installer ZIP")
result = {
    "release": manifest.get("release"),
    "inventory_status": "CONDITIONAL_PASS" if not unresolved else "BLOCKED",
    "installer_root": root.name,
    "manifest_files": manifests,
    "lockfiles": lockfiles,
    "bundled_components": known,
    "unresolved_coverage_gaps": unresolved,
    "policy": "Absence of a manifest is not evidence of zero dependencies; retain this condition until every shipped component has provenance, license and vulnerability review."
}
out.parent.mkdir(parents=True, exist_ok=True)
out.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n", encoding="utf-8")
print("Dependency inventory: " + result["inventory_status"])
print("Manifest files: " + (", ".join(manifests) if manifests else "none"))
print("Lockfiles: " + (", ".join(lockfiles) if lockfiles else "none"))
for item in known:
    print(f"Bundled component: {item['name']} {item['version'] or 'version unknown'}; license={item['license'] or 'NOT CONFIRMED'}; source={item['evidence_path']}")
for issue in unresolved:
    print("COVERAGE GAP: " + issue)
print("Report written: " + str(out))
if result["inventory_status"] == "BLOCKED":
    raise SystemExit(1)
