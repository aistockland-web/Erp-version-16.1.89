#!/usr/bin/env python3
"""Reproduce the exact 16.1.96 installer from the locked 16.1.95 binary and reviewed Cloud AI patch."""
from __future__ import annotations
import argparse, base64, gzip, hashlib, json, os, re, subprocess, tempfile, zipfile
from pathlib import Path, PurePosixPath
ROOT="stockland-erp-app/"
BASE_SHA="494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625"
PATCH_GZ_SHA="cda7d512bc6285ab8667f265558f18f6b11aa91cd1186996aae39943756ca8be"
PATCH_SHA="a6be2e6f417b3eba0f44d82c061bb7d8314d074398c2f0c0dcacc7bee33dbeba"
OUTPUT_SHA="f77c4a809158f0e69874ac30ed80ca4c904d8d6729a338f8862e153b40f0dd72"
def sha(data: bytes) -> str: return hashlib.sha256(data).hexdigest()
def main():
    p=argparse.ArgumentParser()
    p.add_argument("--input",type=Path,required=True); p.add_argument("--patch-part-prefix",required=True)
    p.add_argument("--output",type=Path,required=True); p.add_argument("--receipt",type=Path,required=True)
    a=p.parse_args()
    if not a.input.is_file() or sha(a.input.read_bytes())!=BASE_SHA or a.input.stat().st_size!=1102405:
        raise SystemExit("FAIL: exact 16.1.95 source ZIP hash/size mismatch")
    with zipfile.ZipFile(a.input) as z:
        if z.testzip() is not None: raise SystemExit("FAIL: baseline ZIP CRC")
        infos=[i for i in z.infolist() if not i.is_dir()]
        names=[i.filename for i in infos]; before={i.filename:z.read(i.filename) for i in infos}
    if len(names)!=75 or len(set(names))!=75 or not all(n.startswith(ROOT) and ".." not in PurePosixPath(n).parts for n in names):
        raise SystemExit("FAIL: baseline ZIP path/inventory contract")
    if json.loads(before[ROOT+"RELEASE_MANIFEST.json"])["release"]!="16.1.95":
        raise SystemExit("FAIL: baseline release marker")
    b64="".join(Path(f"{a.patch_part_prefix}{i:02d}").read_text(encoding="ascii").strip() for i in range(1,7))
    if len(b64)!=20092: raise SystemExit("FAIL: patch fragment count/length")
    compressed=base64.b64decode(b64,validate=True)
    if sha(compressed)!=PATCH_GZ_SHA: raise SystemExit("FAIL: compressed patch checksum")
    patch_data=gzip.decompress(compressed)
    if sha(patch_data)!=PATCH_SHA: raise SystemExit("FAIL: expanded patch checksum")
    with tempfile.TemporaryDirectory(prefix="stockland-16-1-96-") as td:
        temp=Path(td)
        for name,data in before.items():
            dest=temp/name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(data)
        proc=subprocess.run(["patch","--batch","--fuzz=0","-p1","-d",str(temp)],input=patch_data,capture_output=True,check=False)
        if proc.returncode: raise SystemExit("FAIL: exact source patch: "+proc.stderr.decode("utf-8","replace")[-2500:])
        app=temp/ROOT
        manifest=json.loads((app/"RELEASE_MANIFEST.json").read_text())
        cssmanifest=json.loads((app/"assets/css/modules.json").read_text())
        expected={"core_operations":29,"accounting_procurement":16,"hr_payroll":4,"total":49}
        if manifest.get("release")!="16.1.96" or cssmanifest.get("release")!="16.1.96": raise SystemExit("FAIL: plugin/CSS release identity")
        if manifest.get("schema_contract")!=expected: raise SystemExit("FAIL: 49-table contract changed")
        css="\n\n".join((app/"assets/css"/str(n)).read_text().strip() for n in cssmanifest["modules"])+"\n"
        mini=re.sub(r"/\*[\s\S]*?\*/"," ",css);mini=re.sub(r"\s+"," ",mini).strip()
        mini=re.sub(r"\s*([{}:;,])\s*",r"\1",mini).replace(";}", "}")
        (app/"assets/app.css").write_text(css,encoding="utf-8")
        (app/"assets/app.min.css").write_text(mini,encoding="utf-8")
        after={path.relative_to(temp).as_posix():path.read_bytes() for path in app.rglob("*") if path.is_file()}
        if len(after)!=76 or not all(n.startswith(ROOT) and ".." not in PurePosixPath(n).parts for n in after):
            raise SystemExit("FAIL: final 76-file archive inventory/root")
        entry=(app/"stockland-inventory.php").read_text();front=(app/"includes/class-stockland-frontend.php").read_text()
        if "Version:           16.1.96" not in entry or "define( 'STOCKLAND_VERSION', '16.1.96' );" not in entry:
            raise SystemExit("FAIL: plugin version marker")
        if "const ROUTE_VERSION      = '16.1.96';" not in front or "define( 'STOCKLAND_DB_VERSION', '16.1.1' );" not in entry:
            raise SystemExit("FAIL: route/cache or DB marker")
        a.output.parent.mkdir(parents=True,exist_ok=True)
        fd,tn=tempfile.mkstemp(prefix="stockland-96-",suffix=".zip",dir=str(a.output.parent));os.close(fd);ztemp=Path(tn)
        try:
            with zipfile.ZipFile(ztemp,"w",compression=zipfile.ZIP_DEFLATED,compresslevel=9) as zout:
                for info in infos:
                    zi=zipfile.ZipInfo(info.filename,date_time=info.date_time)
                    zi.compress_type=zipfile.ZIP_DEFLATED;zi.external_attr=info.external_attr;zi.create_system=info.create_system;zi.flag_bits=info.flag_bits
                    zout.writestr(zi,after[info.filename],compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
                for name in sorted(set(after)-set(names)):
                    zi=zipfile.ZipInfo(name,date_time=(2026,10,24,0,0,0));zi.compress_type=zipfile.ZIP_DEFLATED;zi.create_system=3;zi.external_attr=0o100644<<16
                    zout.writestr(zi,after[name],compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
            with zipfile.ZipFile(ztemp) as final:
                if final.testzip() is not None: raise SystemExit("FAIL: finalized ZIP CRC")
                final_names=[i.filename for i in final.infolist() if not i.is_dir()]
                if len(final_names)!=76 or len(set(final_names))!=76: raise SystemExit("FAIL: final ZIP duplicate/path count")
            ztemp.replace(a.output)
        finally:
            if ztemp.exists():ztemp.unlink()
    out_sha=sha(a.output.read_bytes())
    if out_sha!=OUTPUT_SHA: raise SystemExit("FAIL: final SHA differs from locked candidate: "+out_sha)
    Path(str(a.output)+".sha256").write_text(f"{out_sha}  {a.output.name}\n",encoding="utf-8")
    receipt={"result":"PASS","release":"16.1.96","baseline_release":"16.1.95","baseline_sha256":BASE_SHA,"expanded_patch_sha256":PATCH_SHA,"compressed_patch_sha256":PATCH_GZ_SHA,"output_sha256":out_sha,"output_bytes":a.output.stat().st_size,"plugin_files":76,"php_files":49,"plugin_root":ROOT,"schema_contract":expected,"schema_contract_changed":False,"live_business_data_changed":False,"cloud_ai":"optional, explicit-consent, aggregate-only, advisory-only"}
    a.receipt.parent.mkdir(parents=True,exist_ok=True);a.receipt.write_text(json.dumps(receipt,indent=2)+"\n",encoding="utf-8")
    print(json.dumps(receipt,indent=2))
if __name__=="__main__": main()
