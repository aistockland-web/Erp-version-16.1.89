#!/usr/bin/env python3
"""Remove nonessential fixed external domains from the installable Stockland ERP ZIP."""
from __future__ import annotations

import hashlib
import json
import os
import tempfile
import zipfile
from pathlib import Path

ROOT = "stockland-erp-app/"
JS = ROOT + "assets/app.js"
HR = ROOT + "includes/rest/class-rest-hr.php"
MANIFEST = ROOT + "RELEASE_MANIFEST.json"
BOOTSTRAP = ROOT + "assets/bootstrap.min.css"


def once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise ValueError(f"{label}: expected exactly one source anchor, found {count}")
    return source.replace(old, new, 1)


def strip_manifest_urls(value):
    if isinstance(value, dict):
        value.pop("workflow_run_url", None)
        for child in value.values():
            strip_manifest_urls(child)
    elif isinstance(value, list):
        for child in value:
            strip_manifest_urls(child)


def clean_archive(path: Path) -> dict:
    path = Path(path)
    before_hash = hashlib.sha256(path.read_bytes()).hexdigest()
    with zipfile.ZipFile(path, "r") as zin:
        if zin.testzip() is not None:
            raise ValueError("Input ERP ZIP failed CRC validation.")
        infos = zin.infolist()
        names = [i.filename for i in infos if not i.is_dir()]
        if len(names) != 75 or len(names) != len(set(names)):
            raise ValueError(f"Unexpected ERP plugin inventory: {len(names)} files.")
        if any(not n.startswith(ROOT) or ".." in Path(n).parts for n in names):
            raise ValueError("Unsafe path or unexpected plugin root.")
        contents = {i.filename: zin.read(i.filename) for i in infos if not i.is_dir()}
        info_by_name = {i.filename: i for i in infos}

    js = contents[JS].decode("utf-8")
    # Keep the payroll rule explanation in the UI, but do not make the installed app
    # link to a fixed external legal URL or fall back to an external host.
    legal_links = (
        '<a href="\'+esc(rules.income_tax_source||\'https://justice.gov.et/en/law/\')+\'" target="_blank" rel="noopener noreferrer">Income tax law</a> · <a href="\'+esc(rules.pension_source||\'https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/\')+\'" target="_blank" rel="noopener noreferrer">Pension law</a>'
    )
    js = once(js, legal_links, "Income tax law · Pension law", "payroll legal hyperlinks")
    contents[JS] = js.encode("utf-8")

    hr = contents[HR].decode("utf-8")
    hr = once(hr, "\t\t\t\t\t'income_tax_source'     => 'https://justice.gov.et/en/law/',\n", "", "income tax external URL")
    hr = once(hr, "\t\t\t\t\t'pension_source'        => 'https://justice.gov.et/en/entity/private-organizatio-employees-social-security-administration/',\n", "", "pension external URL")
    contents[HR] = hr.encode("utf-8")

    manifest = json.loads(contents[MANIFEST].decode("utf-8"))
    strip_manifest_urls(manifest)
    contents[MANIFEST] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")

    # Remove URLs from the bundled Bootstrap header comment while retaining the
    # version and MIT attribution text. This changes comments only, not CSS rules.
    bootstrap = contents[BOOTSTRAP].decode("utf-8")
    bootstrap = once(bootstrap, " (https://getbootstrap.com/)", "", "Bootstrap home link in comment")
    bootstrap = once(bootstrap, " (https://github.com/twbs/bootstrap/blob/main/LICENSE)", "", "Bootstrap license link in comment")
    contents[BOOTSTRAP] = bootstrap.encode("utf-8")

    fd, temp_name = tempfile.mkstemp(prefix=path.stem + "-domain-clean-", suffix=".zip", dir=str(path.parent))
    os.close(fd)
    tmp = Path(temp_name)
    try:
        with zipfile.ZipFile(tmp, "w") as zout:
            for info in infos:
                if info.is_dir():
                    zout.writestr(info, b"")
                else:
                    zi = zipfile.ZipInfo(info.filename, date_time=info.date_time)
                    zi.compress_type = zipfile.ZIP_DEFLATED
                    zi.external_attr = info.external_attr
                    zi.create_system = info.create_system
                    zi.flag_bits = info.flag_bits
                    zout.writestr(zi, contents[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

        with zipfile.ZipFile(tmp, "r") as check:
            if check.testzip() is not None:
                raise ValueError("Cleaned ERP ZIP failed CRC validation.")
            final_names = [i.filename for i in check.infolist() if not i.is_dir()]
            if final_names != names:
                raise ValueError("Plugin file inventory/order changed.")
            final_js = check.read(JS).decode("utf-8")
            final_hr = check.read(HR).decode("utf-8")
            final_manifest = json.loads(check.read(MANIFEST).decode("utf-8"))
            final_css = check.read(BOOTSTRAP).decode("utf-8")
            if "justice.gov.et" in final_js or "justice.gov.et" in final_hr:
                raise ValueError("External legal source domain remains in the installed payroll workflow.")
            if "workflow_run_url" in json.dumps(final_manifest):
                raise ValueError("External workflow URL remains in the installed release manifest.")
            if "https://getbootstrap.com/" in final_css or "https://github.com/twbs/bootstrap/blob/main/LICENSE" in final_css:
                raise ValueError("External framework URLs remain in the Bootstrap comment.")
            # URI namespace identifiers used by SVG/OpenXML are not outbound links:
            # they are required document identifiers and stay local in the package.
            actual_hash = hashlib.sha256(tmp.read_bytes()).hexdigest()
        os.replace(tmp, path)
        return {
            "input_sha256": before_hash,
            "cleaned_sha256": actual_hash,
            "bytes": path.stat().st_size,
            "files": len(names),
            "external_payroll_links_removed": True,
            "build_workflow_urls_removed": True,
            "bootstrap_comment_urls_removed": True,
            "svg_openxml_namespace_identifiers_preserved": True,
            "business_data_or_schema_changed": False,
        }
    finally:
        if tmp.exists():
            tmp.unlink()


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Clean fixed external website URLs from the installable ERP package.")
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    print(json.dumps(clean_archive(args.archive), indent=2))
