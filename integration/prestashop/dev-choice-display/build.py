#!/usr/bin/env python3
import argparse,hashlib,json,shutil,tempfile,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3];HERE=Path(__file__).resolve().parent
sha=lambda p:hashlib.sha256(Path(p).read_bytes()).hexdigest()
def build(previous,output):
    paths=['controllers/front/configure.php','views/css/configurator.css','views/js/configurator-cart-v020.js']
    with tempfile.TemporaryDirectory() as tmp:
        package=Path(tmp)/'watchys-interface-dev';package.mkdir()
        for path in paths:
            dest=package/'files'/path;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(ROOT/'modules/watchysconfigurator'/path,dest)
        for name in ['installer.php','README.md']:shutil.copy2(HERE/name,package/name)
        with zipfile.ZipFile(previous) as z:
            before={p:hashlib.sha256(z.read(next(n for n in z.namelist() if n.endswith('/module/'+p)))).hexdigest() for p in paths}
        baseline=json.loads((ROOT/'integration/prestashop/dev-cart/baseline.json').read_text());guards=dict(baseline['assets'])
        for path in ['watchysconfigurator.php','classes/WatchysCatalog.php','classes/WatchysCartService.php','classes/WatchysCartPolicy.php','classes/WatchysCartThumbnails.php','views/js/preview-reference-v013.js','controllers/front/cart.php']:
            guards[path]=sha(ROOT/'modules/watchysconfigurator'/path)
        manifest={'schemaVersion':1,'patch':{p:sha(package/'files'/p) for p in paths},'before':before,'guards':guards,'catalogSha256':'40dbe4a9b28d22a1abc3ce16c5c0c6e9e9c0ea787df22e8e81afef3abe062439'}
        manifest['checks']={p.relative_to(package).as_posix():sha(p) for p in sorted(package.rglob('*')) if p.is_file()}
        (package/'manifest.json').write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n')
        with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as z:
            for p in sorted(package.rglob('*')):
                if p.is_file():
                    info=zipfile.ZipInfo('watchys-interface-dev/'+p.relative_to(package).as_posix(),date_time=(2026,11,24,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,p.read_bytes())
    print(f'SUCCESS: 3 interface files, no MySQL writes: {output}')
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--previous-package',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();build(a.previous_package,a.output)
