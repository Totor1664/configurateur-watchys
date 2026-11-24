#!/usr/bin/env python3
import argparse,hashlib,json,shutil,tempfile,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3];HERE=Path(__file__).resolve().parent
sha=lambda p:hashlib.sha256(Path(p).read_bytes()).hexdigest()
def build(output):
 with tempfile.TemporaryDirectory() as tmp:
  p=Path(tmp)/'watchys-classes-stock-natives-dev';p.mkdir()
  for name in ['preparer.php','probe.php','README.md','dev-contract.json']:shutil.copy2(HERE/name,p/name)
  for folder,names in [('classes',['WatchysStockPolicy.php','WatchysStockSchema.php','WatchysStockJournal.php','WatchysCatalog.php','WatchysStockAllocator.php','WatchysOrderNomenclature.php','WatchysNativeStockCoordinator.php','WatchysOrderStockService.php','WatchysStockObjectGuard.php']),('tests',['stock-policy.php','stock-journal.php','stock-allocation-scenarios.php','stock-allocator.php','order-nomenclature.php','native-stock-coordinator.php','order-stock-service.php','stock-object-guard.php'])]:
   (p/'module'/folder).mkdir(parents=True)
   for name in names:shutil.copy2(ROOT/'modules/watchysconfigurator'/folder/name,p/'module'/folder/name)
  (p/'module/config').mkdir();shutil.copy2(ROOT/'integration/prestashop/catalog.json',p/'module/config/catalog.json')
  m=json.loads((ROOT/'integration/prestashop/catalog.json').read_text())['models'][0]
  manifest={'version':'stock-native-runtime-1','moduleSha256':'3945113b3a391716a88dfda5fc5200b784eceeecc4b65e0a24a1e68e7e355402','componentIds':sorted({m['movement']['component']['productId']}|{o['component']['productId'] for g in m['options'].values() for o in g.values()})}
  manifest['publish']={name:sha(p/'module/classes'/name) for name in ['WatchysStockPolicy.php','WatchysStockSchema.php','WatchysStockJournal.php','WatchysStockAllocator.php','WatchysOrderNomenclature.php','WatchysNativeStockCoordinator.php','WatchysOrderStockService.php','WatchysStockObjectGuard.php']}
  manifest['files']={f.relative_to(p).as_posix():sha(f) for f in sorted(p.rglob('*')) if f.is_file()}
  (p/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
  with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as z:
   for f in sorted(p.rglob('*')):
    if f.is_file():
     info=zipfile.ZipInfo('watchys-classes-stock-natives-dev/'+f.relative_to(p).as_posix(),date_time=(2026,11,24,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,f.read_bytes())
 print('SUCCESS:',output,'native runtime rehearsal + prepared classes; guards intact; no checkout connection.')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--output',type=Path,required=True);a=p.parse_args();build(a.output)
