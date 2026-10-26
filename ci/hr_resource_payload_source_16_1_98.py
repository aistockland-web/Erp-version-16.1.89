#!/usr/bin/env python3
"""Build a narrow 16.1.98 HR Resource Center candidate from the SHA-locked 16.1.95 installer.

The build does not connect to WordPress and does not mutate business data or schema.
It adds a role-gated, nonce-protected download handler and three real OOXML DOCX files,
then emits an exact-package receipt. This is not a claim that the full HR lifecycle is complete.
"""
from __future__ import annotations

import argparse
import base64
import hashlib
import html
import json
import re
import sys
import zipfile
from pathlib import Path, PurePosixPath
from xml.sax.saxutils import escape

BASELINE_SHA256 = "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
BASELINE_SIZE = 1_102_405
ROOT = "stockland-erp-app/"
TARGET = "16.1.98"
MIME_DOCX = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"

DOCS = {
    "process-center-guide": {
        "filename": "Stockland_HR_Process_Center_Guide_v1.docx",
        "title": "Stockland Ethiopia HR Process Center",
        "subtitle": "Operational guide • Version 1.0 • Internal resource",
        "sections": [
            ("Purpose and status", [
                "This guide describes the intended Stockland HR operating controls. It is a working operational resource, not a substitute for an approved company SOP, employment contract, legal opinion, or effective-dated payroll rule.",
                "Owner, HR/Management, and Finance must approve relevant policy choices. An Ethiopian labour, payroll, tax, and pension adviser must validate legal treatment before statutory calculations or remittances are activated."
            ]),
            ("End-to-end employee lifecycle", [
                "1. Workforce request: document role, business need, budget, work location, reporting line, requested start date, and approving authority.",
                "2. Recruitment: maintain candidate/application records with restricted access; use human review, documented selection rationale, and a separate approval decision. Do not automate final hiring or rejection decisions.",
                "3. Contract and onboarding: verify the approved contract, effective dates, role, location, required personnel-document references, induction tasks, and Owner approval before employee activation.",
                "4. Attendance and leave: record shifts, worked time, overtime, absence, leave requests/balances, supporting references, corrections, and the responsible approver.",
                "5. Compensation: capture effective-dated base pay, each allowance/benefit/deduction, approval status, classification evidence, and the source rule/version. A label alone does not decide tax or pension treatment.",
                "6. Payroll: freeze period inputs, calculate itemized values, review exceptions and variances, obtain separate Owner approval, record payment/remittance evidence, and reconcile the GL/PLEDGER.",
                "7. Employee changes and exit: record approved increments/transfers/status changes; calculate final salary, leave and other settlement items using reviewed rules; perform clearance and remove access only through authorized workflow."
            ]),
            ("Maker-checker and audit controls", [
                "The person preparing a record must not approve their own sensitive payroll, compensation, statutory rule, or settlement decision where the system requires independent approval.",
                "Use effective dates and revision history. Do not edit posted payroll history to correct an error; use an approved adjustment or reversal linked to the original record.",
                "Payroll payment references must be unique and posting must be idempotent. Debits and credits must balance; failed payments require a reviewed recovery action, not a second unlinked posting.",
                "Use role- and location-scoped access enforced by the server. Hiding a button is not authorization. Access to personal records and document downloads should be logged."
            ]),
            ("Ethiopian statutory-rule control", [
                "Record the exact official instrument, article/clause, official source URL or file hash, publication date, effective date, applicability, interpretation, reviewer, approval date, test vectors, and expiry/review trigger.",
                "Do not hard-code historical pension rates, allowance exemptions, transport caps, or digital-service withholding assumptions without checking the current official rule and scope. Unknown or conflicting cases must stop for professional review.",
                "A payroll preview is not legal certification. Keep rule approval separate from employee compensation entry and payroll-period approval."
            ]),
            ("Personal data and documents", [
                "Collect only the minimum data required for a defined HR purpose. Store identity, bank, signed contract, and payroll documents only in an approved private repository with access controls and retention rules.",
                "The resource downloads in this Center are blank general templates. Do not upload actual personnel documents into public media folders, public URLs, product fields, or ordinary branch notes.",
                "Do not store ERP passwords, MFA codes, recovery codes, private session cookies, or social-platform credentials in HR records."
            ]),
            ("Monthly payroll review checklist", [
                "[ ] Period dates are a complete valid month and the period is not already closed.",
                "[ ] Employee eligibility and effective dates are checked; service/retainer payees are not automatically treated as employees.",
                "[ ] Compensation is approved, effective for the period, and has a complete itemized breakdown.",
                "[ ] Every exception has an evidence reference and a responsible reviewer; unknown legal classifications are held.",
                "[ ] Gross, taxable base, tax, employee/employer pension, other deductions, net pay, and employer cost are reconciled.",
                "[ ] Management review is complete and the Owner independently approves the exact frozen run.",
                "[ ] Payment and remittance references are recorded; GL/PLEDGER balances; exceptions and audit events are retained."
            ]),
            ("Release and acceptance note", [
                "This resource pack is delivered with a functional download handler, but its availability does not prove that all HR lifecycle screens, migrations, statutory rules, or live-host behavior have passed acceptance.",
                "Before production certification, require exact-package tests, role/location authorization tests, synthetic lifecycle E2E, backup/restore and rollback evidence, current legal-source review, and Owner/HR/Finance acceptance."
            ])
        ]
    },
    "onboarding-checklist": {
        "filename": "Stockland_Employee_Onboarding_Checklist_v1.docx",
        "title": "Stockland Ethiopia Employee Onboarding Checklist",
        "subtitle": "Blank controlled form • Version 1.0",
        "sections": [
            ("Employee and position", [
                "Employee full name: _________________________________________________",
                "Employee ID (after authorized allocation): ______________________________",
                "Approved position / department: ______________________________________",
                "Legal employer / work location / cost center: ___________________________",
                "Reporting manager: __________________  Planned start date: ______________",
                "Employment type and contract term: ___________________________________",
                "Approved vacancy / selection decision reference: ________________________"
            ]),
            ("Contract and approvals", [
                "[ ] Role and budget approved by authorized management.",
                "[ ] Human selection decision and rationale are recorded.",
                "[ ] Contract particulars match the approved role and effective dates.",
                "[ ] Signed contract stored in the approved private repository; record reference only here.",
                "Private contract reference (not a public URL): ___________________________",
                "Contract signer / signed date: _________________________________________",
                "[ ] Owner approval completed where required before activation."
            ]),
            ("Personnel records and privacy", [
                "[ ] Required identity/work-eligibility records reviewed by authorized staff.",
                "[ ] Only status and approved private document reference are recorded in this checklist.",
                "[ ] Personal data access is limited to the roles with a business need.",
                "Verification reviewer / date: __________________________________________",
                "Private document vault/reference: ______________________________________"
            ]),
            ("Payroll setup — do not invent values", [
                "[ ] Legal employer and payroll location confirmed.",
                "[ ] Approved bank/payment details captured only in the authorized secure workflow.",
                "[ ] Effective-dated compensation is entered from an approved instruction.",
                "[ ] Allowances and benefits are itemized; unknown statutory treatment is held for review.",
                "[ ] Pensionable-pay basis and current rule source are reviewed by the authorized adviser.",
                "[ ] No salary, tax classification, pension basis, or allowance value is inferred from another employee.",
                "Compensation approval reference / effective date: _________________________",
                "Rule-set version and reviewer (if applicable): ____________________________"
            ]),
            ("Induction and access", [
                "[ ] Code of conduct and applicable role SOPs issued.",
                "[ ] Safety, attendance, leave, timekeeping, and incident-reporting process explained.",
                "[ ] Required training and equipment tasks assigned with due dates.",
                "[ ] ERP access follows least privilege and approved location scope.",
                "[ ] Sensitive access is verified and unnecessary access is not granted.",
                "Induction owner / completion date: _____________________________________"
            ]),
            ("Activation sign-off", [
                "Prepared by / date: __________________________________________________",
                "HR / Management review / date: ________________________________________",
                "Owner approval / date (if required): ____________________________________",
                "Outstanding tasks or exceptions and owner: ______________________________",
                "Employee activation is permitted only after required contract, evidence, payroll setup, and independent approval checks are complete."
            ]),
            ("Important", [
                "This checklist is a general process aid and does not replace a signed contract or legal advice. Confirm the current Ethiopian labour, tax, pension, privacy, and other applicable rules before relying on a legal or payroll conclusion."
            ])
        ]
    },
    "payroll-review-checklist": {
        "filename": "Stockland_Monthly_Payroll_Review_Checklist_v1.docx",
        "title": "Stockland Ethiopia Monthly Payroll Review",
        "subtitle": "Management preparation and independent Owner approval • Version 1.0",
        "sections": [
            ("Run identification", [
                "Payroll period (YYYY-MM): __________________  Run/reference: ____________",
                "Prepared by / timestamp: ______________________________________________",
                "Rule set version(s) and official effective dates: __________________________",
                "Review deadline / payment date: _______________________________________"
            ]),
            ("Before calculation", [
                "[ ] Period is a complete calendar month and has not already been posted or paid.",
                "[ ] Included employee population and status/effective dates are confirmed.",
                "[ ] New hires and terminations have approved dates and supporting workflow records.",
                "[ ] Approved compensation exists for each included employee and is effective in the period.",
                "[ ] Missing compensation, malformed allowance snapshots, or unresolved classifications remain blocking exceptions.",
                "[ ] Retainer/service contractors and other nonemployees are routed under their own reviewed AP/tax process."
            ]),
            ("Calculation and variance review", [
                "[ ] Input snapshot is frozen and can be traced to approved source records.",
                "[ ] Base salary, taxable allowances, any legally approved exempt items, and deductions reconcile.",
                "[ ] PAYE calculation uses the approved current monthly schedule and effective-date treatment.",
                "[ ] Employee and employer pension amounts use the approved current pension rule.",
                "[ ] Net pay and employer total cost reconcile for every line and period total.",
                "[ ] Prior-period comparison and unusual variances have written explanations.",
                "[ ] No employee pay or legal classification was invented or auto-filled to clear a health check."
            ]),
            ("Independent approval and posting", [
                "[ ] Management confirms the run is complete and submits the exact version for approval.",
                "[ ] Owner/reviewer is independent of preparation where required and checks exceptions/evidence.",
                "[ ] Approval references the exact period/run version and audit history records the decision.",
                "[ ] Payroll journal balances and is posted once through the accepted ledger service.",
                "[ ] Payment batch uses unique payment references and supports failed-payment recovery without duplicate posting.",
                "[ ] Tax/pension remittance receipts and payment evidence are retained in approved private storage.",
                "[ ] PLEDGER / GL / payroll control totals reconcile and the period is locked against edits."
            ]),
            ("Exception register", [
                "Employee/reference | Issue | Evidence needed | Responsible reviewer | Due date | Resolution",
                "________________________________________________________________________",
                "________________________________________________________________________",
                "________________________________________________________________________",
                "________________________________________________________________________"
            ]),
            ("Sign-off", [
                "Management preparer: ______________________________  Date: ______________",
                "Independent reviewer: ______________________________  Date: ______________",
                "Owner approval: ____________________________________  Date: ______________",
                "Finance reconciliation: _____________________________  Date: ______________",
                "Open exceptions held from posting: ______________________________________"
            ]),
            ("Legal review boundary", [
                "The system must not be described as fully compliant based on a calculated preview alone. Current official Ethiopian tax, labour, and pension instruments and their effective dates require independent qualified review. Preserve existing posted payroll; use linked corrections/reversals rather than rewriting history."
            ])
        ]
    }
}

def sha256(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()

def replace_once(raw: bytes, old: str, new: str, label: str) -> bytes:
    text = raw.decode("utf-8")
    n = text.count(old)
    if n != 1:
        raise SystemExit(f"{label}: expected one source anchor, found {n}")
    return text.replace(old, new, 1).encode("utf-8")

def w_text(text: str) -> str:
    return '<w:r><w:t xml:space="preserve">' + escape(text) + '</w:t></w:r>'

def make_docx(doc: dict) -> bytes:
    """Create a valid minimal Office Open XML .docx using only Python's standard library."""
    paras = []
    paras.append('<w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr>' + w_text(doc["title"]) + '</w:p>')
    paras.append('<w:p><w:pPr><w:pStyle w:val="Subtitle"/></w:pPr>' + w_text(doc["subtitle"]) + '</w:p>')
    for heading, texts in doc["sections"]:
        paras.append('<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr>' + w_text(heading) + '</w:p>')
        for text in texts:
            style = "BodyText"
            if text.startswith("[ ]"):
                style = "BodyText"
            paras.append('<w:p><w:pPr><w:pStyle w:val="' + style + '"/></w:pPr>' + w_text(text) + '</w:p>')
    sect = '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
    doc_xml = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        '<w:body>' + ''.join(paras) + sect + '</w:body></w:document>').encode()
    styles = b'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="21"/></w:rPr><w:pPr><w:spacing w:after="80" w:line="258" w:lineRule="auto"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:color w:val="17365D"/><w:sz w:val="34"/></w:rPr><w:pPr><w:spacing w:after="180"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:rPr><w:i/><w:color w:val="64748B"/><w:sz w:val="20"/></w:rPr><w:pPr><w:spacing w:after="260"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="BodyText"/><w:qFormat/><w:rPr><w:b/><w:color w:val="17365D"/><w:sz w:val="25"/></w:rPr><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="100"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/><w:basedOn w:val="Normal"/><w:rPr><w:sz w:val="21"/></w:rPr></w:style>
</w:styles>'''
    content_types = b'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>'''
    rels = b'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>'''
    docrels = b'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>'''
    core = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
        'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        '<dc:title>' + escape(doc["title"]) + '</dc:title><dc:creator>Stockland Ethiopia</dc:creator>'
        '<cp:lastModifiedBy>Stockland Ethiopia</cp:lastModifiedBy></cp:coreProperties>').encode()
    app = b'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
<Application>Microsoft Office Word</Application><Company>Stockland Ethiopia</Company></Properties>'''
    import io
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml", content_types)
        z.writestr("_rels/.rels", rels)
        z.writestr("word/document.xml", doc_xml)
        z.writestr("word/_rels/document.xml.rels", docrels)
        z.writestr("word/styles.xml", styles)
        z.writestr("docProps/core.xml", core)
        z.writestr("docProps/app.xml", app)
    return buf.getvalue()

def resource_files_php() -> bytes:
    items = []
    for key, doc in DOCS.items():
        encoded = base64.b64encode(make_docx(doc)).decode()
        chunks = [encoded[i:i + 4000] for i in range(0, len(encoded), 4000)]
        items.append("        " + json.dumps(key) + " => array(\n            " +
            ",\n            ".join(json.dumps(chunk) for chunk in chunks) + "\n        ),")
    source = """<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Generated, non-public DOCX payloads for authorized Stockland HR resource downloads. */
final class Stockland_HR_Resource_Files {
    private const FILES = array(
""" + "\n".join(items) + """
    );
    public static function get( $key ) {
        if ( ! isset( self::FILES[ $key ] ) ) { return false; }
        $bytes = base64_decode( implode( '', self::FILES[ $key ] ), true );
        return false === $bytes ? false : $bytes;
    }
}
"""
    return source.encode("utf-8")

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    source = Path(args.input)
    output = Path(args.output)
    if not source.is_file():
        raise SystemExit("Missing baseline installer ZIP")
    raw = source.read_bytes()
    if len(raw) != BASELINE_SIZE or sha256(raw) != BASELINE_SHA256:
        raise SystemExit("STOP: baseline installer SHA-256 or size does not match the reviewed 16.1.95 source lock")
    with zipfile.ZipFile(source) as zin:
        if zin.testzip() is not None:
            raise SystemExit("Baseline ZIP CRC failed")
        infos = [i for i in zin.infolist() if not i.is_dir()]
        names = [i.filename for i in infos]
        if len(names) != 75 or len(set(names)) != len(names):
            raise SystemExit("Expected the checksum-locked 75-file baseline package")
        if any(PurePosixPath(n).parts[0] != "stockland-erp-app" or ".." in PurePosixPath(n).parts for n in names):
            raise SystemExit("Unsafe or unexpected package root")
        files = {n: zin.read(n) for n in names}
    main_path = ROOT + "stockland-inventory.php"
    front_path = ROOT + "includes/class-stockland-frontend.php"
    js_path = ROOT + "assets/app.js"
    manifest_path = ROOT + "RELEASE_MANIFEST.json"
    readme_path = ROOT + "README.md"
    authority_path = ROOT + "includes/class-stockland-authority.php"
    require = lambda p: p in files
    for path in (main_path, front_path, js_path, manifest_path, readme_path, authority_path):
        if not require(path):
            raise SystemExit("Required baseline member missing: " + path)
    files[main_path] = replace_once(files[main_path], "* Version:           16.1.95", "* Version:           16.1.98", "plugin header")
    files[main_path] = replace_once(files[main_path], "define( 'STOCKLAND_VERSION', '16.1.95' );", "define( 'STOCKLAND_VERSION', '16.1.98' );", "plugin version constant")
    files[front_path] = replace_once(files[front_path], "const ROUTE_VERSION      = '16.1.95';", "const ROUTE_VERSION      = '16.1.98';", "frontend route/cache version")
    files[main_path] = replace_once(
        files[main_path],
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-authority.php';",
        "require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-authority.php';\nrequire_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-resources.php';",
        "HR resources class include")
    files[main_path] = replace_once(
        files[main_path],
        "new Stockland_App();",
        "new Stockland_App();\n\t\tnew Stockland_HR_Resources();",
        "HR resources download handler registration")
    files[front_path] = replace_once(
        files[front_path],
        "'count_template' => esc_url_raw( STOCKLAND_PLUGIN_URL . 'templates/Stockland_Physical_Count_Template.xlsx' ),",
        "'count_template' => esc_url_raw( STOCKLAND_PLUGIN_URL . 'templates/Stockland_Physical_Count_Template.xlsx' ),\n\t\t\t'hr_resources'  => Stockland_HR_Resources::downloads(),",
        "frontend HR resource catalogue")
    css_manifest_path = ROOT + "assets/css/modules.json"
    css_manifest = json.loads(files[css_manifest_path].decode("utf-8"))
    css_manifest["release"] = TARGET
    css_manifest["description"] = "16.1.98 HR resource download candidate; the six ordered CSS source modules remain byte-identical."
    files[css_manifest_path] = (json.dumps(css_manifest, ensure_ascii=False, indent=2) + "\n").encode()
    js = files[js_path].decode("utf-8")
    helper_anchor = "function slPayrollMinor(value){"
    helper = r'''
function hrResourceCenter(){
  const resources=Array.isArray(A.hr_resources)?A.hr_resources:[];
  if(!resources.length)return '';
  return '<section class="sl-panel sl-hr-resource-center"><div class="sl-block-head"><div><div class="sl-eyebrow">HR PROCESS CENTER</div><h3>Downloadable Resources</h3><small>Approved general templates and process guides. Personnel records and signed contracts are not stored here.</small></div><span class="sl-pill info">DOCX</span></div><div class="sl-grid sl-grid-compact">'+resources.map(r=>'<article class="sl-panel"><div class="sl-eyebrow">'+esc(r.category||'HR RESOURCE')+'</div><h4>'+esc(r.title||r.filename||'HR resource')+'</h4><p class="sl-sub">'+esc(r.description||'')+'</p><div class="sl-sub">'+esc(r.language||'English')+' · '+esc(r.version||'v1.0')+' · '+esc(r.filename||'DOCX')+'</div><a class="sl-btn primary" data-hr-resource-download data-filename="'+esc(r.filename||'resource.docx')+'" data-resource-title="'+esc(r.title||r.filename||'HR resource')+'" href="'+esc(r.download_url||'#')+'" download>DOWNLOAD DOCX</a></article>').join('')+'</div><p class="sl-sub">Templates are working resources, not a legal opinion or approval of current Ethiopian statutory treatment. Confirm official source, effective date, and Owner/HR/Finance review before operational use.</p></section>';
}
function bindHRResourceDownloads(){
  document.querySelectorAll('[data-hr-resource-download]').forEach(a=>{
    if(a.__hrResourceBound)return;
    a.__hrResourceBound=true;
    a.addEventListener('click',async ev=>{
      ev.preventDefault();
      if(a.getAttribute('aria-busy')==='true')return;
      a.setAttribute('aria-busy','true');
      try{
        const response=await fetch(a.href,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
        if(!response.ok)throw new Error('HTTP '+response.status+' — check your HR access and sign-in.');
        const type=String(response.headers.get('content-type')||'').toLowerCase();
        if(!type.includes('application/vnd.openxmlformats-officedocument.wordprocessingml.document'))throw new Error('The server did not return a valid DOCX download.');
        const blob=await response.blob();
        if(blob.size<1500)throw new Error('The downloaded document is unexpectedly small; try again or contact the ERP Owner.');
        const url=URL.createObjectURL(blob);
        const temp=document.createElement('a');temp.href=url;temp.download=a.dataset.filename||'Stockland_HR_Resource.docx';temp.style.display='none';document.body.appendChild(temp);temp.click();temp.remove();
        window.setTimeout(()=>URL.revokeObjectURL(url),1500);
        toast('Downloaded '+(a.dataset.resourceTitle||a.dataset.filename||'HR resource')+'.');
      }catch(err){toast('Download failed: HR resource — '+String(err.message||err),true)}
      finally{a.removeAttribute('aria-busy')}
    });
  });
}
'''
    js = replace_once(js.encode(), helper_anchor, helper + "\n" + helper_anchor, "resource center renderer and download feedback").decode()
    js = replace_once(
        js.encode(),
        "      setup+\n      '<div class=\"sl-panel\"><div class=\"sl-block-head\"><div><h3>Employee Master</h3>",
        "      setup+\n      hrResourceCenter()+\n      '<div class=\"sl-panel\"><div class=\"sl-block-head\"><div><h3>Employee Master</h3>",
        "responsive HR resource center placement").decode()
    js = replace_once(
        js.encode(),
        "if(c.payroll_manage||c.payroll_approve){renderPayrollTable(d,c);}document.querySelectorAll('[data-hr-comp]')",
        "if(c.payroll_manage||c.payroll_approve){renderPayrollTable(d,c);}bindHRResourceDownloads();document.querySelectorAll('[data-hr-comp]')",
        "bind resource click feedback after HR render").decode()
    files[js_path] = js.encode()
    manifest = json.loads(files[manifest_path].decode())
    manifest["release"] = TARGET
    manifest["release_label"] = "Stockland Ethiopia ERP — HR resource download candidate 16.1.98"
    manifest.setdefault("release_changes", []).append(
        "16.1.98 candidate: responsive HR Process Center resources with nonce-protected, role-checked, real DOCX downloads and browser success/error feedback.")
    manifest["schema_contract"] = {"total":49,"core_operations":29,"accounting_procurement":16,"hr_payroll":4}
    manifest["schema_changed"] = False
    manifest["live_business_data_changed"] = False
    manifest["hr_resource_downloads"] = {
        "resources": [v["filename"] for v in DOCS.values()],
        "format": "OOXML DOCX (not a legacy .doc file or renamed HTML)",
        "handler": "admin-post with signed WordPress nonce and Stockland_Authority::can_view_hr",
        "direct_public_urls": False,
        "status": "candidate component only; does not complete the full HR lifecycle"
    }
    manifest["production_acceptance"] = {
        "status":"PENDING — candidate component only",
        "live_source_parity":"PENDING — builder input is the checksum-locked 16.1.95 installer; live site was reported as 16.1.97. No in-place install until source parity and intervening fixes are proven.",
        "pending":["full HR lifecycle implementation and isolated end-to-end tests","role/location authorization and direct-download denial tests on target hosting","current Ethiopian labour/tax/pension legal review","Owner/HR/Finance acceptance","verified backup/restore and rollback receipts"]
    }
    files[manifest_path] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode()
    readme = files[readme_path].decode("utf-8")
    note = "\n\n## HR Process Center download candidate (16.1.98)\n\nThe candidate adds nonce-protected, role-checked and audited downloads of genuine DOCX general templates in the HR & Payroll workspace. It does not contain employee-specific documents, does not change the 49-table schema or live business data, and is not by itself a complete HR lifecycle release. Source-parity gate: the verified builder input is 16.1.95 while the live site was reported as 16.1.97. Do not install in place until exact source parity is proven and intervening fixes are preserved. See the release manifest and acceptance gates.\n"
    if "HR Process Center download candidate (16.1.98)" in readme:
        raise SystemExit("README already contains 16.1.98 HR resource section")
    files[readme_path] = (readme.rstrip() + note).encode()
    files[ROOT + "includes/class-stockland-hr-resources.php"] = (Path(__file__).resolve().parent / "hr_payroll_overlay_16_1_98" / "class-stockland-hr-resources.php").read_bytes()
    files[ROOT + "includes/class-stockland-hr-resource-files.php"] = resource_files_php()
    if not files[ROOT + "includes/class-stockland-hr-resources.php"]:
        raise SystemExit("HR resource handler source is empty")
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
        for name, data in files.items():
            zout.writestr(name, data)
    final = output.read_bytes()
    digest = sha256(final)
    output.with_suffix(output.suffix + ".sha256").write_text(digest + "  " + output.name + "\n", encoding="utf-8")
    receipt = {
        "release": TARGET,
        "baseline_installer_sha256": BASELINE_SHA256,
        "installer_sha256": digest,
        "installer_size_bytes": len(final),
        "package_files": len(files),
        "schema_changed": False,
        "live_business_data_changed": False,
        "resource_downloads": [doc["filename"] for doc in DOCS.values()],
        "candidate_component_only": True,
        "production_ready": False,
        "production_gates_pending": ["prove exact source parity with live 16.1.97 and preserve all intervening fixes; builder baseline is 16.1.95", "full HR lifecycle E2E", "target-host access/download tests", "legal review", "Owner/HR/Finance acceptance", "backup/restore and rollback"]
    }
    print(json.dumps(receipt, ensure_ascii=False, sort_keys=True))

if __name__ == "__main__":
    main()
