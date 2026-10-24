#!/usr/bin/env python3
"""Inventory dependency manifests and third-party assets in the exact ERP ZIP.

A WordPress plugin may depend on the WordPress/PHP host without carrying a
Composer/npm ecosystem. Absence of a manifest is not treated as a vulnerability
or as proof that dependencies do not exist: inspect manifests, lockfiles,
vendored runtime directories, and known bundled assets. A declared manifest
without its supported lockfile is blocked.
"""
from __future__ import annotations
import hashlib, json, re, sys
from pathlib import Path

if len(sys.argv) < 2:
    raise SystemExit("usage: dependency-inventory.py <extracted-plugin-root> [report.json]")
root = Path(sys.argv[1]).resolve()
out = Path(sys.argv[2]).resolve() if len(sys.argv) > 2 else Path("dependency-inventory.json")
release_path = root / "RELEASE_MANIFEST.json"
if not root.is_dir() or not release_path.is_file():
    raise SystemExit("BLOCKED: extracted plugin root or release manifest missing")
manifest = json.loads(release_path.read_text(encoding="utf-8"))

manifest_names = {
    "composer.json", "composer.lock", "package.json", "package-lock.json",
    "npm-shrinkwrap.json", "yarn.lock", "pnpm-lock.yaml", "bun.lockb", "bun.lock",
    "requirements.txt", "pyproject.toml", "poetry.lock", "Pipfile", "Pipfile.lock",
    "go.mod", "go.sum", "Cargo.toml", "Cargo.lock",
}
lock_names = {
    "composer.lock", "package-lock.json", "npm-shrinkwrap.json", "yarn.lock",
    "pnpm-lock.yaml", "bun.lockb", "bun.lock", "requirements.txt", "poetry.lock",
    "Pipfile.lock", "go.sum", "Cargo.lock",
}
manifests = sorted(str(p.relative_to(root)) for p in root.rglob("*")
                   if p.is_file() and p.name in manifest_names)
lockfiles = [p for p in manifests if Path(p).name in lock_names]
manifest_basenames = {Path(p).name for p in manifests}
vendored_dirs = sorted(str(p.relative_to(root)) for p in root.rglob("*")
                       if p.is_dir() and p.name.lower() in {"vendor", "node_modules"})

frontend = manifest.get("frontend", {})
bootstrap_version = str(frontend.get("bootstrap_version", ""))
bootstrap_path = root / "assets" / "bootstrap.min.css"
bootstrap_license = None
bootstrap_sha256 = None
known = []
if bootstrap_path.is_file():
    payload = bootstrap_path.read_bytes()
    prefix = payload[:8192].decode("utf-8", errors="replace")
    if re.search(r"(?i)MIT License|Licensed under MIT|license.*MIT|github\.com/twbs/bootstrap/blob/.*/LICENSE", prefix):
        bootstrap_license = "MIT"
    bootstrap_sha256 = hashlib.sha256(payload).hexdigest()
    known.append({
        "name": "Bootstrap", "version": bootstrap_version or None,
        "purl": f"pkg:npm/bootstrap@{bootstrap_version}" if bootstrap_version else None,
        "license": bootstrap_license, "evidence_path": "assets/bootstrap.min.css",
        "sha256": bootstrap_sha256, "sha384_sri": frontend.get("bootstrap_sri_sha384"),
        "git_blob_sha1": frontend.get("bootstrap_source_git_blob_sha1"),
        "classification": "bundled third-party CSS asset; not an npm runtime installation",
    })

issues = []
if not bootstrap_version:
    issues.append("release manifest does not declare bundled Bootstrap version")
if not bootstrap_path.is_file():
    issues.append("bundled Bootstrap stylesheet is missing")
elif bootstrap_license is None:
    issues.append("Bootstrap MIT license notice was not detected in its first 8 KiB; review vendor provenance")

ecosystems = {
    "composer.json": "composer.lock",
    "package.json": {"package-lock.json", "npm-shrinkwrap.json", "yarn.lock", "pnpm-lock.yaml", "bun.lock", "bun.lockb"},
    "pyproject.toml": {"poetry.lock"},
    "Pipfile": {"Pipfile.lock"},
    "go.mod": "go.sum",
    "Cargo.toml": "Cargo.lock",
}
for declaration, expected in ecosystems.items():
    if declaration in manifest_basenames:
        expected_set = {expected} if isinstance(expected, str) else expected
        if not (expected_set & manifest_basenames):
            issues.append(f"dependency manifest {declaration} exists without a supported lockfile")
lock_to_manifest = {
    "composer.lock": {"composer.json"}, "package-lock.json": {"package.json"},
    "npm-shrinkwrap.json": {"package.json"}, "yarn.lock": {"package.json"},
    "pnpm-lock.yaml": {"package.json"}, "bun.lock": {"package.json"}, "bun.lockb": {"package.json"},
    "poetry.lock": {"pyproject.toml"}, "Pipfile.lock": {"Pipfile"},
    "go.sum": {"go.mod"}, "Cargo.lock": {"Cargo.toml"},
}
for lock in lockfiles:
    if Path(lock).name in lock_to_manifest and not (lock_to_manifest[Path(lock).name] & manifest_basenames):
        issues.append(f"orphan lockfile {lock} has no matching supported manifest")
if vendored_dirs and not lockfiles:
    issues.append("vendored dependency directories exist without a supported lockfile")

no_package_manager_ecosystem = not manifests and not lockfiles and not vendored_dirs
if no_package_manager_ecosystem:
    dependency_model = "NO_PACKAGE_MANAGER_ECOSYSTEM_IN_PLUGIN_ARCHIVE"
    coverage_note = (
        "The exact WordPress plugin archive contains no supported Composer/npm/Python/Go/Rust manifests, "
        "no lockfiles and no vendor/node_modules runtime directory. The host WordPress/PHP runtime is "
        "outside this ZIP. Lockfile-based dependency review is not applicable to this artifact; the bundled "
        "Bootstrap stylesheet is inventoried separately. If a managed dependency manifest or vendored runtime "
        "is introduced in a future build, lockfile and vulnerability review become mandatory."
    )
else:
    dependency_model = "PACKAGE_MANAGED_OR_VENDORED_DEPENDENCIES_PRESENT"
    coverage_note = "Package-managed or vendored dependency evidence was found and is checked for manifest/lockfile parity."

status = "PASS" if not issues else "BLOCKED"
result = {
    "release": manifest.get("release"), "inventory_status": status,
    "dependency_model": dependency_model, "installer_root": root.name,
    "manifest_files": manifests, "lockfiles": lockfiles,
    "vendored_dependency_directories": vendored_dirs, "bundled_components": known,
    "coverage_note": coverage_note, "findings": issues,
    "policy": "N/A is only allowed when no package manager is shipped. Fail closed on incomplete manifests or vendored runtime dependencies without provenance.",
}
out.parent.mkdir(parents=True, exist_ok=True)
out.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n", encoding="utf-8")
print(f"Dependency inventory: {status}")
print(f"Dependency model: {dependency_model}")
print("Manifest files: " + (", ".join(manifests) if manifests else "none"))
print("Lockfiles: " + (", ".join(lockfiles) if lockfiles else "none"))
print("Vendored runtime directories: " + (", ".join(vendored_dirs) if vendored_dirs else "none"))
for component in known:
    print(f"Bundled component: {component['name']} {component['version'] or 'version unknown'}; license={component['license'] or 'NOT CONFIRMED'}; SHA-256={component['sha256']}; path={component['evidence_path']}")
print(coverage_note)
for issue in issues:
    print("FINDING: " + issue)
print("Report written: " + str(out))
if issues:
    raise SystemExit(1)
