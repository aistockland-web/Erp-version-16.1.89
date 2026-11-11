#!/usr/bin/env python3
"""Verify exact Stockland .13/.15 installer identities and safe ZIP structure."""
from __future__ import annotations
import argparse, hashlib, json, shutil, stat, sys, zipfile
from pathlib import Path, PurePosixPath
ROOT=Path(__file__).resolve().parents[2]
PACKAGES={
 '16.2.13': {'archive':'STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip','sidecar':'STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER.zip.sha256','sha256':'74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13','size_bytes':2188684,'entries':113,'php_files':80,'logical_sidecar_name':'STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip'},
 '16.2.15': {'archive':'STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip','sidecar':'STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip.sha256','sha256':'8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df'}
}
def digest(path):
 h=hashlib.sha256()
 with path.open('rb') as f:
  for chunk in iter(lambda:f.read(1024*1024),b''): h.update(chunk)
 return h.hexdigest()
def inspect(version,spec,extract_root):
 archive=ROOT/spec['archive']; sidecar=ROOT/spec['sidecar']
 if not archive.is_file() or not sidecar.is_file(): raise RuntimeError(f'{version}: archive or sidecar missing')
 fields=sidecar.read_text(encoding='utf-8').strip().split(maxsplit=1)
 if len(fields)!=2 or len(fields[0])!=64: raise RuntimeError(f'{version}: malformed sidecar')
 expected_name=spec.get('logical_sidecar_name',archive.name)
 if fields[1]!=expected_name: raise RuntimeError(f'{version}: unexpected sidecar filename {fields[1]}')
 actual=digest(archive)
 if fields[0].lower()!=spec['sha256'] or actual!=spec['sha256']: raise RuntimeError(f'{version}: SHA-256 mismatch; sidecar={fields[0]}, actual={actual}, pin={spec["sha256"]}')
 names=[]; hashes={}; counts={'php':0,'js':0,'json':0,'css':0,'docx':0}
 with zipfile.ZipFile(archive) as z:
  infos=z.infolist()
  for info in infos:
   name=info.filename
   if not name or name.startswith(('/','\\')) or '\\' in name: raise RuntimeError(f'{version}: unsafe path {name!r}')
   p=PurePosixPath(name)
   if p.is_absolute() or any(x in ('','.','..') for x in p.parts): raise RuntimeError(f'{version}: traversal path {name!r}')
   mode=(info.external_attr>>16)&0xffff
   if stat.S_ISLNK(mode): raise RuntimeError(f'{version}: symlink forbidden: {name}')
   if not p.parts or p.parts[0]!='stockland-erp-app': raise RuntimeError(f'{version}: unexpected root {name}')
   names.append(name)
   if info.is_dir(): continue
   suffix=p.suffix.lower()
   counts['php']+=suffix=='.php'; counts['js']+=suffix in ('.js','.mjs'); counts['json']+=suffix=='.json'; counts['css']+=suffix=='.css'; counts['docx']+=suffix=='.docx'
   hashes[str(p.relative_to('stockland-erp-app'))]=hashlib.sha256(z.read(info)).hexdigest()
  dup=sorted({n for n in names if names.count(n)>1})
  if dup: raise RuntimeError(f'{version}: duplicate ZIP paths {dup[:10]}')
  bad=z.testzip()
  if bad: raise RuntimeError(f'{version}: CRC failure at {bad}')
  if {PurePosixPath(n).parts[0] for n in names}!={'stockland-erp-app'}: raise RuntimeError(f'{version}: root check failed')
  if spec.get('entries') is not None and len(infos)!=spec['entries']: raise RuntimeError(f'{version}: entry count mismatch {len(infos)}')
  if spec.get('php_files') is not None and counts['php']!=spec['php_files']: raise RuntimeError(f'{version}: PHP count mismatch {counts["php"]}')
  if spec.get('size_bytes') is not None and archive.stat().st_size!=spec['size_bytes']: raise RuntimeError(f'{version}: byte size mismatch')
  if extract_root:
   target=extract_root/version.replace('.','_')
   if target.exists(): shutil.rmtree(target)
   target.mkdir(parents=True,exist_ok=True); z.extractall(target)
 return {'version':version,'filename':archive.name,'sidecar_filename':sidecar.name,'sidecar_logical_filename':fields[1],'size_bytes':archive.stat().st_size,'sha256':actual,'zip_entries_including_directories':len(names),'file_count':sum(not n.endswith('/') for n in names),'php_files':counts['php'],'javascript_files':counts['js'],'json_files':counts['json'],'css_files':counts['css'],'docx_files':counts['docx'],'plugin_root':'stockland-erp-app/','zip_crc':'PASS','duplicate_paths':0,'unsafe_paths':0,'files':hashes}
def main():
 p=argparse.ArgumentParser(); p.add_argument('--report',type=Path,default=ROOT/'artifacts/codespaces/package-intake.json'); p.add_argument('--extract-root',type=Path); args=p.parse_args()
 extraction=args.extract_root.resolve() if args.extract_root else None
 if extraction: extraction.mkdir(parents=True,exist_ok=True)
 results={v:inspect(v,spec,extraction) for v,spec in PACKAGES.items()}
 base=results['16.2.13']['files']; cand=results['16.2.15']['files']; common=set(base)&set(cand); changed=sorted(f for f in common if base[f]!=cand[f])
 results['comparison']={'same_paths':len(common)-len(changed),'changed_shared_paths':changed,'only_in_16_2_13':sorted(set(base)-set(cand)),'only_in_16_2_15':sorted(set(cand)-set(base)),'note':'Path/hash comparison is structural only; it does not prove ancestry, behavior or production readiness.'}
 results['disposition']='PACKAGE_IDENTITY_AND_ARCHIVE_SAFETY_PASS; UPDATE_AND_PRODUCTION_ACCEPTANCE_PENDING'
 for v in PACKAGES:
  i=results[v]; print(f"PASS {v}: {i['size_bytes']} bytes; SHA-256 {i['sha256']}; {i['file_count']} files; PHP {i['php_files']}; ZIP CRC PASS")
 print(f"COMPARE: {results['comparison']['same_paths']} unchanged; {len(changed)} changed shared; {len(results['comparison']['only_in_16_2_13'])} .13-only; {len(results['comparison']['only_in_16_2_15'])} .15-only")
 args.report.parent.mkdir(parents=True,exist_ok=True); args.report.write_text(json.dumps(results,indent=2,sort_keys=True)+'\n',encoding='utf-8')
 print(f'Report: {args.report}; production remains NOT APPROVED')
if __name__=='__main__':
 try: main()
 except (OSError,zipfile.BadZipFile,RuntimeError,KeyError,ValueError) as exc:
  print(f'FAIL archive intake: {exc}',file=sys.stderr); raise SystemExit(1)
