#!/usr/bin/env python3
"""Exact-package regression tests for the 16.1.98 HR Resource Center component.

These tests prove ZIP/source invariants and genuine DOCX packaging only. They do not
prove target-host authorization, full HR lifecycle behavior, or legal compliance.
"""
import argparse
import base64
import hashlib
import importlib
import io
import json
import re
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
EXPECTED_BASELINE_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
EXPECTED_BASELINE_SIZE = 1_102_405
TARGET = "16.1.98"
ROOT_IN_ZIP = "stockland-erp-app/"
ALLOWED_CHANGED = {
    ROOT_IN_ZIP + "stockland-inventory.php",
    ROOT_IN_ZIP + "includes/class-stockland-frontend.php",
    ROOT_IN_ZIP + "assets/app.js",
    ROOT_IN_ZIP + "README.md",
    ROOT_IN_ZIP + "RELEASE_MANIFEST.json",
    ROOT_IN_ZIP + "assets/css/modules.json",
}
EXPECTED_NEW = {
    ROOT_IN_ZIP + "includes/class-stockland-hr-resources.php",
    ROOT_IN_ZIP + "includes/class-stockland-hr-resource-files.php",
}
MIME = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"

def require(condition, message):
    if not condition:
        raise SystemExit("FAIL: " + message)

def digest(data):
    return hashlib.sha256(data).hexdigest()

def docx_payloads(php_source):
    result = {}
    for key in ("process-center-guide", "onboarding-checklist", "payroll-review-checklist"):
        match = re.search(re.escape(json.dumps(key)) + r"\s*=>\s*array\(\s*(.*?)\n        \),", php_source, re.S)
        require(bool(match), "embedded payload missing for " + key)
        chunks = re.findall(r'"([A-Za-z0-9+/=]+)"', match.group(1))
        require(bool(chunks), "no base64 chunks for " + key)
        result[key] = base64.b64decode("".join(chunks), validate=True)
    return result

def verify_docx(key, data, builder):
    require(data.startswith(b"PK\x03\x04"), key + " is not a ZIP/OOXML file")
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        require(z.testzip() is None, key + " DOCX CRC failure")
        names = set(z.namelist())
        required = {"[Content_Types].xml", "_rels/.rels", "word/document.xml",
                    "word/_rels/document.xml.rels", "word/styles.xml",
                    "docProps/core.xml", "docProps/app.xml"}
        require(required.issubset(names), key + " lacks required DOCX OOXML parts")
        for xml_name in ("[Content_Types].xml", "_rels/.rels", "word/document.xml",
                         "word/_rels/document.xml.rels", "word/styles.xml", "docProps/core.xml"):
            ET.fromstring(z.read(xml_name))
        document_text = z.read("word/document.xml").decode("utf-8")
        require(builder.DOCS[key]["title"] in document_text, key + " title missing from document")
        require("Stockland" in document_text, key + " company reference missing")

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--baseline", required=True)
    parser.add_argument("--package", required=True)
    parser.add_argument("--receipt", required=True)
    args = parser.parse_args()
    baseline_path = Path(args.baseline)
    package_path = Path(args.package)
    receipt_path = Path(args.receipt)
    require(baseline_path.is_file() and package_path.is_file() and receipt_path.is_file(),
            "baseline, package, and build receipt must exist")
    raw_base = baseline_path.read_bytes()
    require(len(raw_base) == EXPECTED_BASELINE_SIZE, "baseline size does not match lock")
    require(digest(raw_base) == EXPECTED_BASELINE_SHA256, "baseline SHA-256 does not match lock")
    receipt = json.loads(receipt_path.read_text(encoding="utf-8"))
    raw = package_path.read_bytes()
    require(receipt.get("release") == TARGET, "receipt release does not match 16.1.98")
    require(receipt.get("installer_sha256") == digest(raw), "receipt hash does not match exact package")
    require(receipt.get("schema_changed") is False, "candidate must preserve 49-table schema")
    require(receipt.get("live_business_data_changed") is False, "builder must not claim live-data mutation")
    require(receipt.get("production_ready") is False, "component-only candidate must remain explicitly not production-ready")
    sha_file = package_path.with_suffix(package_path.suffix + ".sha256")
    require(sha_file.is_file() and sha_file.read_text().split()[0] == digest(raw),
            "package SHA-256 sidecar mismatch")
    with zipfile.ZipFile(baseline_path) as bz, zipfile.ZipFile(package_path) as z:
        require(bz.testzip() is None and z.testzip() is None, "archive CRC check failed")
        before_names = [n.filename for n in bz.infolist() if not n.is_dir()]
        after_names = [n.filename for n in z.infolist() if not n.is_dir()]
        require(len(before_names) == 75 and len(set(before_names)) == 75,
                "reviewed baseline inventory differs from its lock")
        require(len(after_names) == 77 and len(set(after_names)) == 77,
                "candidate must contain exactly 77 unique files")
        require(after_names[:len(before_names)] == before_names,
                "existing installer member order changed")
        require(set(after_names) - set(before_names) == EXPECTED_NEW,
                "candidate added unexpected package members")
        require(all(PurePosixPath(n).parts[0] == "stockland-erp-app" and ".." not in PurePosixPath(n).parts
                    for n in after_names), "unsafe or unexpected package path")
        old = {n: bz.read(n) for n in before_names}
        new = {n: z.read(n) for n in after_names}
    changed = {n for n in old if old[n] != new[n]}
    require(changed == ALLOWED_CHANGED, "unapproved baseline changes: " + repr(sorted(changed ^ ALLOWED_CHANGED)))
    require(sum(n.endswith(".php") for n in new) == sum(n.endswith(".php") for n in old) + 2,
            "candidate PHP file inventory is unexpected")

    main = new[ROOT_IN_ZIP + "stockland-inventory.php"].decode()
    front = new[ROOT_IN_ZIP + "includes/class-stockland-frontend.php"].decode()
    js = new[ROOT_IN_ZIP + "assets/app.js"].decode()
    manifest = json.loads(new[ROOT_IN_ZIP + "RELEASE_MANIFEST.json"].decode())
    handler = new[ROOT_IN_ZIP + "includes/class-stockland-hr-resources.php"].decode()
    payload_file = new[ROOT_IN_ZIP + "includes/class-stockland-hr-resource-files.php"].decode()
    require("* Version:           " + TARGET in main and "define( 'STOCKLAND_VERSION', '" + TARGET + "' );" in main,
            "plugin header and runtime version mismatch")
    require("const ROUTE_VERSION      = '" + TARGET + "';" in front, "route/cache version mismatch")
    css_manifest = json.loads(new[ROOT_IN_ZIP + "assets/css/modules.json"].decode())
    require(css_manifest.get("release") == TARGET and len(css_manifest.get("modules", [])) == 6, "CSS module cache manifest release/inventory mismatch")
    require("require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-resources.php';" in main,
            "download handler is not loaded by plugin bootstrap")
    require("'hr_resources'  => Stockland_HR_Resources::downloads()" in front,
            "authorized resource list is not attached to frontend config")
    require("function hrResourceCenter()" in js and "hrResourceCenter()+" in js,
            "resource center not rendered in HR & Payroll view")
    require("function bindHRResourceDownloads()" in js and "bindHRResourceDownloads()" in js,
            "working click handler / feedback is missing")
    require("response.ok" in js and "createObjectURL" in js and "Download failed" in js,
            "success and error download feedback is missing")
    require("admin_post_" in handler and "wp_nonce_url" in handler and "check_admin_referer" in handler,
            "authenticated nonce-protected download handler is incomplete")
    require("Stockland_Authority::can_view_hr()" in handler,
            "server-side HR capability check is missing")
    require("Content-Disposition: attachment" in handler and MIME in handler,
            "correct DOCX download headers are missing")
    require("sanitize_key" in handler and "self::catalog()" in handler,
            "fixed resource allow-list validation is missing")
    require("readfile(" not in handler and "STOCKLAND_PLUGIN_URL" not in handler,
            "resources must not be served from predictable static public URLs")
    require("class-stockland-hr-resource-files.php" in handler,
            "embedded, non-public payload file is not loaded")
    require("public static function get( $key )" in payload_file, "embedded payload loader missing")

    contract = manifest.get("schema_contract", {})
    require(contract == {"total":49,"core_operations":29,"accounting_procurement":16,"hr_payroll":4},
            "49-table schema contract changed")
    require(manifest.get("schema_changed") is False and manifest.get("live_business_data_changed") is False,
            "manifest must explicitly preserve schema/live records")
    acceptance = manifest.get("production_acceptance", {})
    require(acceptance.get("status", "").startswith("PENDING"),
            "candidate must not claim production acceptance")
    require(manifest.get("hr_resource_downloads", {}).get("status") == "candidate component only; does not complete the full HR lifecycle",
            "manifest overstates HR completion")

    sys_path = str(ROOT / "ci")
    if sys_path not in __import__("sys").path:
        __import__("sys").path.insert(0, sys_path)
    builder = importlib.import_module("build_release_16_1_98")
    actual_payloads = docx_payloads(payload_file)
    require(set(actual_payloads) == set(builder.DOCS), "embedded DOCX catalog does not match builder source")
    expected_filenames = {item["filename"] for item in builder.DOCS.values()}
    require(set(receipt.get("resource_downloads", [])) == expected_filenames,
            "receipt resource inventory differs from built DOCX files")
    for key, payload in actual_payloads.items():
        require(payload == builder.make_docx(builder.DOCS[key]), key + " payload differs from deterministic DOCX source")
        verify_docx(key, payload, builder)
    require(all(name.endswith(".docx") and not name.endswith(".doc") for name in expected_filenames),
            "download filenames must use genuine .docx extensions")
    print("PASS 16.1.98 exact-package resource-download component: ZIP/hash, schema preservation, auth/nonce source contracts, UI wiring, and three genuine DOCX payloads.")
    print("NOT PASSED / NOT CLAIMED: live target-host behavior, full HR lifecycle, legal review, backup/restore, Owner/HR/Finance sign-off.")

if __name__ == "__main__":
    main()
