#!/usr/bin/env python3
"""Build a DEV package from one canonical catalog; PNGs stay in verified server packs."""
from pathlib import Path
import json,hashlib,shutil,zipfile,tempfile,argparse
VERSION='0.1.1'
repo=Path(__file__).resolve().parents[2]

def build(output):
    module=repo/'modules/watchysconfigurator'
    catalog=json.loads((repo/'integration/prestashop/catalog.json').read_text())
    assets={}
    for model in catalog['models']:
        for options in model['options'].values():
            for option in options.values():
                a=dict(option['visual']);a['target']=str(option['component']['productId'])+'-front.png'
                if a['target'] in assets and assets[a['target']]!=a:raise ValueError('Conflicting source for '+a['target'])
                assets[a['target']]=a
    output=Path(output).resolve();output.parent.mkdir(parents=True,exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='watchys-build-') as tmp:
        package=Path(tmp)/('watchys-module-dev-'+VERSION);package.mkdir()
        shutil.copytree(module,package/'watchysconfigurator',ignore=shutil.ignore_patterns('*.png'))
        (package/'watchysconfigurator/config/catalog.json').write_text(json.dumps(catalog,ensure_ascii=False,indent=2)+'\n')
        for file in ['installer.php','DevFiles.php','legacy-0.1.0.json']:shutil.copyfile(repo/'integration/prestashop/dev-installer'/file,package/file)
        files={str(p.relative_to(package/'watchysconfigurator')):hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted((package/'watchysconfigurator').rglob('*')) if p.is_file()}
        (package/'manifest.json').write_text(json.dumps({'version':VERSION,'files':files,'assets':list(assets.values())},ensure_ascii=False,indent=2)+'\n')
        shutil.copyfile(module/'README.md',package/'LIRE-MOI.md')
        # Stable metadata makes repeated builds byte-identical and avoids deleting existing folders.
        with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as archive:
            for p in sorted(package.rglob('*')):
                if p.is_file():
                    info=zipfile.ZipInfo(str(p.relative_to(package.parent)),date_time=(2026,11,23,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;archive.writestr(info,p.read_bytes())
    with zipfile.ZipFile(output) as archive:
        if archive.testzip() is not None:raise RuntimeError('ZIP corrompu')
    return output,len(assets)

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--output',default=str(repo.parent/('watchys-module-dev-'+VERSION+'.zip')));args=parser.parse_args()
    path,count=build(args.output);print(path);print('PNG requis sur serveur :',count,'; octets :',path.stat().st_size)
