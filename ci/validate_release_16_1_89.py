#!/usr/bin/env python3
"""Reproducible release gates for the Stockland ERP 16.1.89 WordPress ZIP.

This validates the exact release artifact already committed to this repository.
It does not install the plugin, change a database, or certify live-host behavior.
"""
from __future__ import annotations

import hashlib
import json
import re
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
EXPECTED_VERSION = "16.1.89"
EXPECTED_ZIP_SIZE = 1_104_097
EXPECTED_ZIP_SHA256 = "4b8a5fe5648ddf062953c671fe572cb62235757d3a092da6b23116aacae392c3"
EXPECTED_FILES = 74
EXPECTED_PHP_FILES = 47
EXPECTED_ROOT = "stockland-erp-app"
EXPECTED_THEMES = ("sapphire", "emerald", "violet", "copper")
EXPECTED_CSS_MODULES = (
    "01-foundation.css",
    "02-shell-navigation.css",
    "03-content-data-components.css",
    "04-overlays-workflows.css",
    "05-responsive-layout.css",
    "06-operational-guards.css",
)


class GateFailure(Exception):
    pass


def require(condition: bool, message: str) -> None:
    if not condition:
        raise GateFailure(message)
    print(f"PASS {message}")


def run_checked(command: list[str], *, cwd: Path | None = None) -> None:
    proc = subprocess.run(command, cwd=cwd, text=True, stdout=subprocess.PIPE,
                          stderr=subprocess.STDOUT, check=False)
    if proc.returncode:
        raise GateFailure(
            f"Command failed ({proc.returncode}): {' '.join(command)}\n{proc.stdout}"
        )
    output = proc.stdout.strip()
    if output:
        print(output)


def normalize_css(source: str) -> str:
    source = re.sub(r"/\*.*?\*/", " ", source, flags=re.S)
    source = re.sub(r"\s+", " ", source).strip()
    source = re.sub(r"\s*([{}:;,])\s*", r"\1", source)
    source = re.sub(r";}", "}", source)
    return source + "\n"


def main() -> int:
    zips = sorted(REPO.glob(f"STOCKLAND_ERP_{EXPECTED_VERSION}*.zip"))
    require(len(zips) == 1, f"exactly one {EXPECTED_VERSION} installer ZIP exists at repository root")
    archive = zips[0]
    raw = archive.read_bytes()
    actual_sha = hashlib.sha256(raw).hexdigest()
    require(len(raw) == EXPECTED_ZIP_SIZE, f"installer size is {EXPECTED_ZIP_SIZE:,} bytes")
    require(actual_sha == EXPECTED_ZIP_SHA256, "installer SHA-256 matches the versioned audit/QC record")

    qa_path = REPO / f"STOCKLAND_ERP_{EXPECTED_VERSION}_LOCAL_QA_RESULTS.txt"
    audit_path = next(iter(REPO.glob(f"STOCKLAND_ERP_{EXPECTED_VERSION}_DEEP_CODE_LEVEL_AUDIT*.md")), None)
    require(qa_path.is_file() and audit_path is not None, "version-matched QA and audit records are present")
    qa_text = qa_path.read_text(encoding="utf-8")
    require(EXPECTED_ZIP_SHA256 in qa_text and f"{EXPECTED_ZIP_SIZE:,} bytes" in qa_text,
            "QA record agrees with installer identity")
    
    with zipfile.ZipFile(archive) as zf:
        members = [m for m in zf.infolist() if not m.is_dir()]
        require(zf.testzip() is None, "ZIP CRC/integrity test passes")
        names = [m.filename for m in members]
        require(len(members) == EXPECTED_FILES, f"installer has exactly {EXPECTED_FILES} file members")
        require(len(set(names)) == len(names), "installer has no duplicate member paths")
        require(all(n.startswith(EXPECTED_ROOT + "/") for n in names),
                f"all package files are under the single {EXPECTED_ROOT}/ root")
        require(all(".." not in Path(n).parts for n in names), "ZIP contains no parent-traversal paths")
        require(not any(Path(n).suffix.lower() in {".exe", ".msi", ".dmg", ".ipa", ".apk", ".aab", ".deb", ".rpm", ".appx", ".msix"} for n in names),
                "installer contains no fabricated native installer binaries")

        with tempfile.TemporaryDirectory(prefix="stockland-erp-1689-") as temp:
            dest = Path(temp)
            zf.extractall(dest)
            app = dest / EXPECTED_ROOT
            require(app.is_dir(), "plugin root extracted")
            php_files = sorted(app.rglob("*.php"))
            require(len(php_files) == EXPECTED_PHP_FILES,
                    f"installer contains exactly {EXPECTED_PHP_FILES} PHP files")
            require((app / "stockland-inventory.php").is_file(), "WordPress plugin entry point exists")
            require((app / "includes/class-stockland-frontend.php").is_file(), "frontend route/cache source exists")

            for relative in (
                "RELEASE_MANIFEST.json",
                "assets/manifest.json",
                "assets/css/modules.json",
            ):
                json.loads((app / relative).read_text(encoding="utf-8"))
                print(f"PASS valid JSON: {relative}")

            release = json.loads((app / "RELEASE_MANIFEST.json").read_text(encoding="utf-8"))
            modules = json.loads((app / "assets/css/modules.json").read_text(encoding="utf-8"))
            schema = release.get("schema_contract", {})
            require(release.get("release") == EXPECTED_VERSION, "release manifest identifies version 16.1.89")
            require(modules.get("release") == EXPECTED_VERSION, "CSS module manifest identifies version 16.1.89")
            require(tuple(modules.get("modules", ())) == EXPECTED_CSS_MODULES,
                    "six canonical CSS modules are ordered and named correctly")
            require(
                schema.get("core_operations") == 29
                and schema.get("accounting_procurement") == 16
                and schema.get("hr_payroll") == 4
                and schema.get("total") == 49
                and schema.get("new_business_table") is False,
                "49-table contract remains 29 + 16 + 4 with no new business table",
            )
            require(
                schema.get("core_schema_version") == "16.1.1"
                and schema.get("accounting_schema_version") == "16.1.15"
                and schema.get("hr_schema_version") == "16.1.3",
                "Core, Accounting and HR schema version markers are preserved",
            )

            plugin = (app / "stockland-inventory.php").read_text(encoding="utf-8")
            frontend = (app / "includes/class-stockland-frontend.php").read_text(encoding="utf-8")
            require(bool(re.search(r"^\s*\*\s*Version:\s*16\.1\.89\s*$", plugin, re.M)),
                    "WordPress plugin header version matches 16.1.89")
            require("define( 'STOCKLAND_VERSION', '16.1.89' );" in plugin,
                    "STOCKLAND_VERSION constant matches 16.1.89")
            require("define( 'STOCKLAND_DB_VERSION', '16.1.1' );" in plugin,
                    "database version marker remains 16.1.1")
            require(bool(re.search(r"ROUTE_VERSION\s*=\s*['\"]16\.1\.89['\"]", frontend)),
                    "frontend route/cache version matches plugin version")

            assets = app / "assets"
            for relative in ("bootstrap.min.css", "stockland-logo.jpg", "stockland-sidebar-brand.png", "app.css", "app.min.css", "app.js", "pwa.js"):
                require((assets / relative).is_file(), f"required asset exists: assets/{relative}")

            front_manifest = release.get("frontend", {})
            require(front_manifest.get("bootstrap_version") == "5.3.8",
                    "locally bundled Bootstrap version is 5.3.8")
            bootstrap = (assets / "bootstrap.min.css").read_bytes()
            bootstrap_git_blob = hashlib.sha1(b"blob " + str(len(bootstrap)).encode("ascii") + b"\0" + bootstrap).hexdigest()
            bootstrap_sri = __import__("base64").b64encode(hashlib.sha384(bootstrap).digest()).decode("ascii")\n            manifest_sri = str(front_manifest.get("bootstrap_sri_sha384", "")).removeprefix("sha384-")
            require(front_manifest.get("bootstrap_source_git_blob_sha1") == bootstrap_git_blob,
                    "Bootstrap Git blob identity matches release manifest")
            require(manifest_sri == bootstrap_sri,
                    "Bootstrap SHA-384 SRI matches the bundled stylesheet")

            css_dir = assets / "css"
            module_texts = [(css_dir / name).read_text(encoding="utf-8").strip() for name in EXPECTED_CSS_MODULES]
            require(all(text.count("{") == text.count("}") for text in module_texts),
                    "each source CSS module has balanced braces")
            rebuilt_css = "\n\n".join(module_texts) + "\n"
            require((assets / "app.css").read_text(encoding="utf-8") == rebuilt_css,
                    "app.css is reproducibly built from the ordered source modules")
            require((assets / "app.min.css").read_text(encoding="utf-8") == normalize_css(rebuilt_css),
                    "app.min.css matches the deterministic minification contract")

            app_js = (assets / "app.js").read_text(encoding="utf-8")
            pwa_js = (assets / "pwa.js").read_text(encoding="utf-8")
            combined_js = app_js + "\n" + pwa_js
            require("stockland_erp_theme" in combined_js, "saved theme preference key is preserved")
            require("theme-color" in combined_js, "theme-color browser chrome integration is present")
            require(all(theme in combined_js for theme in EXPECTED_THEMES),
                    "all four established theme identifiers are present")
            require("beforeinstallprompt" in combined_js,
                    "genuine browser PWA install event handling is present")

            # Dynamic-language syntax is run against the actual extracted release files.
            for php in php_files:
                run_checked(["php", "-l", str(php)])
            print(f"PASS PHP syntax: {len(php_files)}/{EXPECTED_PHP_FILES}")
            for js in (assets / "app.js", assets / "pwa.js"):
                run_checked(["node", "--check", str(js)])
            print("PASS JavaScript syntax: app.js and pwa.js")

    print("\nRELEASE PACKAGE GATES PASSED.")
    print("Scope: static package/code checks only; no live WordPress database, deep-integrity, backup/restore or device-install claim.")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except GateFailure as exc:
        print(f"FAIL {exc}", file=sys.stderr)
        raise SystemExit(1)
    except (OSError, ValueError, KeyError, zipfile.BadZipFile, json.JSONDecodeError) as exc:
        print(f"FAIL unexpected validation error: {exc}", file=sys.stderr)
        raise SystemExit(1)
