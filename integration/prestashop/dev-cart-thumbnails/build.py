#!/usr/bin/env python3
"""Package pre-rendered cart thumbnails; no deployment or server access."""
import argparse, hashlib, json, shutil, tempfile, zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3]
HERE=Path(__file__).resolve().parent
sha=lambda p:hashlib.sha256(Path(p).read_bytes()).hexdigest()
def build(assets,previous,output):
    model=json.loads((ROOT/'integration/prestashop/catalog.json').read_text())['models'][0]
    visual=json.loads((assets/'manifest.json').read_text())
    if visual['modelId']!=model['id'] or len(visual['files'])!=960:raise ValueError('Unexpected thumbnail catalogue')
    expected={}
    import itertools
    for ca,di,ha,st in itertools.product(*[model['options'][g] for g in ['case','dial','hands','strap']]):
        s=dict(zip(['case','dial','hands','strap'],[ca,di,ha,st]))
        key=hashlib.sha256('|'.join([model['id'],ca,di,ha,st]).encode()).hexdigest()+'.jpg'
        item=visual['files'][key]
        if item['selection']!=s or sha(assets/key)!=item['sha256']:raise ValueError('Thumbnail mismatch')
        expected[key]=item['sha256']
    baseline=json.loads((ROOT/'integration/prestashop/dev-cart/baseline.json').read_text())
    alias={'case-pvd-polished':'case-black-pvd','case-pvd-brushed':'case-black-brushed','hands-dauphine-blue':'hands-dauphine-royal-blue','hands-dauphine-yellow-gold':'hands-dauphine-gold'}
    for group in ['case','dial','hands']:
        for name,option in model['options'][group].items():
            if visual['sources'][alias.get(name,name)]!=baseline['assets'][f"views/img/{option['component']['productId']}-front.png"]:raise ValueError('Source mismatch')
    for name,digest in visual['sources'].items():
        if name.startswith('strap-') and baseline['assets']['views/img/reference-'+name+'.png']!=digest:raise ValueError('Strap mismatch')
    with tempfile.TemporaryDirectory() as temp:
        package=Path(temp)/'watchys-miniatures-panier-dev';package.mkdir()
        shutil.copytree(ROOT/'modules/watchysconfigurator',package/'module',ignore=shutil.ignore_patterns('__pycache__'))
        (package/'module/config').mkdir(exist_ok=True)
        shutil.copy2(ROOT/'integration/prestashop/catalog.json',package/'module/config/catalog.json')
        (package/'integration/prestashop').mkdir(parents=True)
        shutil.copy2(ROOT/'integration/prestashop/catalog.json',package/'integration/prestashop/catalog.json')
        shutil.copy2(HERE/'installer.php',package/'installer.php')
        shutil.copy2(HERE/'README.md',package/'README.md')
        shutil.copytree(assets,package/'thumbnails')
        patch={p:sha(package/'module'/p) for p in ['watchysconfigurator.php','classes/WatchysCartThumbnails.php']}
        with zipfile.ZipFile(previous) as z:
            names=z.namelist();oldManifest=json.loads(z.read(next(n for n in names if n.endswith('/manifest.json') and n.count('/')==1)))
            if oldManifest['version']!='0.2.2':raise ValueError('0.2.2 package required')
            originalModule=z.read(next(n for n in names if n.endswith('/module/watchysconfigurator.php')))
        guards=dict(baseline['assets'])
        for path,digest in oldManifest['files'].items():
            if path not in patch:guards[path]=digest
        manifest={'schemaVersion':1,'directory':'cart-reference-v1','referenceCommit':visual['referenceCommit'],
                  'catalogSha256':'40dbe4a9b28d22a1abc3ce16c5c0c6e9e9c0ea787df22e8e81afef3abe062439',
                  'patch':patch,'before':{'watchysconfigurator.php':hashlib.sha256(originalModule).hexdigest(),'classes/WatchysCartThumbnails.php':None},
                  'guards':guards,'thumbnails':expected,'componentIds':sorted({model['movement']['component']['productId']}|{o['component']['productId'] for g in model['options'].values() for o in g.values()})}
        manifest['checks']={p.relative_to(package).as_posix():sha(p) for p in sorted(package.rglob('*')) if p.is_file()}
        (package/'manifest.json').write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n')
        with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
            for p in sorted(package.rglob('*')):
                if p.is_file():
                    info=zipfile.ZipInfo('watchys-miniatures-panier-dev/'+p.relative_to(package).as_posix(),date_time=(2026,11,24,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,p.read_bytes())
    print(f'SUCCESS: 960 thumbnails, {len(patch)} code files; {output}')
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--assets',type=Path,required=True);p.add_argument('--previous-package',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();build(a.assets,a.previous_package,a.output)
