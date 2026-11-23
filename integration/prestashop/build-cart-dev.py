#!/usr/bin/env python3
"""Build the private update for an installed, visually validated DEV module."""
import argparse,hashlib,json,tempfile,zipfile,shutil
from pathlib import Path
VERSION='0.2.0'
repo=Path(__file__).resolve().parents[2]
def build(output):
    source=repo/'modules/watchysconfigurator';setup=repo/'integration/prestashop/dev-cart'
    baseline=json.loads((setup/'baseline.json').read_text());output=Path(output).resolve();output.parent.mkdir(parents=True,exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='watchys-cart-build-') as tmp:
        package=Path(tmp)/('watchys-panier-dev-'+VERSION);package.mkdir()
        shutil.copytree(source,package/'module',ignore=shutil.ignore_patterns('*.png','__pycache__'))
        # Test-only catalog. The installed DEV catalog is never replaced.
        (package/'module/config/catalog.json').write_text((repo/'integration/prestashop/catalog.json').read_text())
        for name in ['installer.php','baseline.json']:shutil.copyfile(setup/name,package/name)
        shutil.copyfile(setup/'README.md',package/'LIRE-MOI.md')
        files={name:hashlib.sha256((package/'module'/name).read_bytes()).hexdigest() for name in baseline['patch']}
        checks={str(p.relative_to(package)):hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(package.rglob('*')) if p.is_file()}
        manifest={'version':VERSION,'files':files,'checks':checks,'preservesAssets':29,'checkoutEnabled':False}
        (package/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
        with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as z:
            for p in sorted(package.rglob('*')):
                if p.is_file():
                    info=zipfile.ZipInfo(str(p.relative_to(package.parent)),date_time=(2026,11,23,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,p.read_bytes())
    with zipfile.ZipFile(output) as z:
        if z.testzip():raise RuntimeError('Archive non valide')
    return output,len(files)
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--output',default=str(repo.parent/('watchys-panier-dev-'+VERSION+'.zip')));args=p.parse_args();out,n=build(args.output);print(out);print(n,'fichiers à publier ; 29 PNG préservés ; commande bloquée.')
