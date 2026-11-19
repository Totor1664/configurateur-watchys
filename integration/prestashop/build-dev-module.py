#!/usr/bin/env python3
"""Build a DEV-only installer; reuse verified PNG packs already on the user's server."""
from pathlib import Path
import json,hashlib,shutil,zipfile
repo=Path(__file__).resolve().parents[2]
module=repo/'modules/watchysconfigurator'
package=repo.parent/'watchys-module-dev-0.1.0'
if package.exists():shutil.rmtree(package)
package.mkdir()
shutil.copytree(module,package/'watchysconfigurator',ignore=shutil.ignore_patterns('*.png'))
shutil.copyfile(repo/'integration/prestashop/dev-installer/installer.php',package/'installer.php')
catalog=json.loads((module/'config/catalog.json').read_text())
assert catalog==json.loads((repo/'integration/prestashop/catalog.json').read_text())
assets=[]
for model in catalog['models']:
    for options in model['options'].values():
        for option in options.values():
            a=dict(option['visual']);a['target']=str(option['component']['productId'])+'-front.png';assets.append(a)
assert len(assets)==23 and len(set(a['target'] for a in assets))==23
files={str(p.relative_to(package/'watchysconfigurator')):hashlib.sha256(p.read_bytes()).hexdigest() for p in (package/'watchysconfigurator').rglob('*') if p.is_file()}
(package/'manifest.json').write_text(json.dumps({'version':'0.1.0','files':files,'assets':assets},ensure_ascii=False,indent=2)+'\n')
shutil.copyfile(repo/'modules/watchysconfigurator/README.md',package/'LIRE-MOI.md')
output=repo.parent/(package.name+'.zip')
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as archive:
    for p in package.rglob('*'):
        if p.is_file():archive.write(p,p.relative_to(package.parent))
with zipfile.ZipFile(output) as archive:assert archive.testzip() is None
print(str(output));print('Taille :',output.stat().st_size,'octets ; PNG à copier depuis les paquets serveur :',len(assets))
