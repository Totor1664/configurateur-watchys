#!/usr/bin/env python3
"""Reproducible private passive candidate. No deployment or catalog publication."""
import argparse,hashlib,json,shutil,tempfile,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3];HERE=Path(__file__).resolve().parent
sha=lambda p:hashlib.sha256(Path(p).read_bytes()).hexdigest()
NAME='watchys-lot-natif-dev'
CLASSES=['WatchysStockPolicy','WatchysStockSchema','WatchysStockJournal','WatchysStockAllocator','WatchysOrderNomenclature','WatchysNativeStockCoordinator','WatchysOrderStockService','WatchysStockObjectGuard','WatchysNativeSchema','WatchysStockWriteGate','WatchysEffectCodec','WatchysNativeOutbox','WatchysStockReconciler','WatchysNativeBoundary','WatchysNativeServices','WatchysNativeMovementRepository','WatchysNativeRuntime','WatchysReceivedReturns','WatchysHookPolicy']
CORE={
'classes/Hook.php':'d996aafb565966b109c1deb1769a84d9e7f31d81084720b98c150dc9bc7c779e',
'classes/Mail.php':'d55ff869ded3cf817e49d3e4ea20508d6778546a903e0b85b548f2faa39b3514',
'src/PrestaShopBundle/Entity/Repository/StockMovementRepository.php':'afd7e8a85a9e052b45c3a0d3539aaab71be534069f8d25373cd7797edd55c534',
'src/PrestaShopBundle/Entity/Repository/StockManagementRepository.php':'fc6e817b7fe3cc50db1b4915f072d5549276670150a4bfe4c50ff634733aba87',
'classes/stock/StockAvailable.php':'0d426c3dad1ec18e24f73bf11347e0eb58a23818819e4af59f38f6923c605b84',
'classes/ObjectModel.php':'86dce9ec2eabadaf391bfb7f01088076ef0324c0760399b4b51297a161f64322',
'classes/PaymentModule.php':'6e5bfe678ae8d3ad6ae2f2ae3685e754ce8295f9e5aa037a3aa9b998a0ae6d98',
'classes/order/OrderDetail.php':'aa9e3c182b38b93b2641e93903b6f99a4091979ca6cf77a8021110e8af867d57',
'classes/order/OrderHistory.php':'33edb0236d2a4d1a50cd83c541dce1c4cf1e1dc32a9bbe9f8dc47e779aaa548c',
'src/Core/Stock/StockManager.php':'c361a5b256b9c0840884832ef398ef499d326ac92024d42f5c671945608fbd20',
'src/Adapter/StockManager.php':'91e4aff2fe75392bd4c9f0e219e153145c509c5c67cebff157670a5d453a6e19',
'classes/db/Db.php':'c6af6d757b5e1a93f14cc4bdf114201d4cf6ee8f3b4e1549e9e368605dc7f3ef',
'classes/db/DbPDO.php':'4656689c5440bcc0083437af437cd8c2ed9430db9592670ba0905fff01293532',
'src/Adapter/ServiceLocator.php':'c157cb80d280042f15e3bdd774495afa042549807a7483d5b9fadbf2c21a97b9',
'src/Core/Foundation/IoC/Container.php':'278d7413cea1430ed9a2bd837da8410c4464c98e26543bdec7249ade6123cd5c'}
def build(output):
 output=Path(output).resolve();output.parent.mkdir(parents=True,exist_ok=True);source=ROOT/'modules/watchysconfigurator'
 with tempfile.TemporaryDirectory() as tmp:
  p=Path(tmp)/NAME;p.mkdir()
  for file in sorted(HERE.iterdir()):
   if file.is_file() and file.suffix in ('.php','.md','.json'):shutil.copy2(file,p/file.name)
  shutil.copy2(HERE.parent/'dev-stock-native-runtime/dev-contract.json',p/'dev-contract.json')
  for d in ['classes','tests','override']:
   shutil.copytree(source/d,p/'module'/d)
  (p/'module/config').mkdir();shutil.copy2(source/'config/services.yml',p/'module/config/services.yml');shutil.copy2(ROOT/'integration/prestashop/catalog.json',p/'module/config/catalog.json');shutil.copy2(source/'watchysconfigurator.php',p/'module/watchysconfigurator.php')
  patch=['watchysconfigurator.php','classes/WatchysCartService.php','config/services.yml']+['classes/'+n+'.php' for n in CLASSES]+[f.relative_to(source).as_posix() for f in sorted((source/'override').rglob('*.php'))]
  baseline=json.loads((p/'baseline.json').read_text());model=json.loads((p/'module/config/catalog.json').read_text())['models'][0]
  component_ids=sorted({model['movement']['component']['productId']}|{o['component']['productId'] for g in model['options'].values() for o in g.values()})
  publish={f:sha(p/'module'/f) for f in patch}
  for f,h in publish.items():
   if (source/f).is_symlink():raise RuntimeError('Linked patch source')
  overrides=[f for f in patch if f.startswith('override/')]
  render={f:sha(source/f) for f in ['views/js/preview-reference-v013.js','views/js/configurator-cart-v020.js','views/css/configurator.css','views/templates/front/configure-cart-v020.tpl','classes/WatchysCartThumbnails.php']}
  manifest={'version':'0.3.0-passive','checkoutEnabled':False,'effectsEnabled':False,'componentIds':component_ids,'publish':publish,'overrides':overrides,'core':CORE,'render':render,'checks':{f.relative_to(p).as_posix():sha(f) for f in sorted(p.rglob('*')) if f.is_file()}}
  (p/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
  with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as z:
   for f in sorted(p.rglob('*')):
    if f.is_file():
     i=zipfile.ZipInfo(NAME+'/'+f.relative_to(p).as_posix(),date_time=(2026,11,24,0,0,0));i.compress_type=zipfile.ZIP_DEFLATED;i.external_attr=0o100644<<16;z.writestr(i,f.read_bytes())
 return output,len(patch)
if __name__=='__main__':
 a=argparse.ArgumentParser();a.add_argument('--output',type=Path,required=True);args=a.parse_args();out,count=build(args.output);print(out);print(count,'fichiers du module ; 6 surcharges ; candidat passif ; essais DEV à confirmer.')
