import unittest,tempfile,importlib.util,json,zipfile,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def importer(path):
 spec=importlib.util.spec_from_file_location('builder',path);module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module
build=importer(ROOT/'integration/prestashop/dev-native-lot/build.py')
class BuildTests(unittest.TestCase):
 def test_reproducible_patch_and_preserved_visuals(self):
  with tempfile.TemporaryDirectory() as tmp:
   a,n=build.build(Path(tmp)/'a.zip');b,_=build.build(Path(tmp)/'b.zip');self.assertEqual(n,28);self.assertEqual(a.read_bytes(),b.read_bytes())
   with zipfile.ZipFile(a) as z:
    prefix='watchys-lot-natif-dev/';manifest=json.loads(z.read(prefix+'manifest.json'));baseline=json.loads(z.read(prefix+'baseline.json'))
    self.assertFalse(manifest['checkoutEnabled']);self.assertFalse(manifest['effectsEnabled']);self.assertEqual(len(baseline['assets']),29);self.assertEqual(len(manifest['componentIds']),24);self.assertEqual(len(manifest['overrides']),6)
    self.assertNotIn('config/catalog.json',manifest['publish']);self.assertFalse(any(f.startswith('views/') for f in manifest['publish']))
    for name,sha in manifest['publish'].items():self.assertEqual(hashlib.sha256(z.read(prefix+'module/'+name)).hexdigest(),sha)
    for name,sha in manifest['checks'].items():self.assertEqual(hashlib.sha256(z.read(prefix+name)).hexdigest(),sha)
 def test_legacy_builders_disabled(self):
  for file in ['build-dev-module.py','build-cart-dev.py']:
   with self.assertRaises(RuntimeError):importer(ROOT/'integration/prestashop'/file).build('unused.zip')
 def test_pinned_history_body_has_only_coordinated_adapter_change(self):
  native=(ROOT/'tests/fixtures/OrderHistory-8.2.7.php').read_text();self.assertEqual(hashlib.sha256(native.encode()).hexdigest(),'33edb0236d2a4d1a50cd83c541dce1c4cf1e1dc32a9bbe9f8dc47e779aaa548c')
  start=native.index('        if (!$new_order_state || !$id_order)');end=native.index('        ShopUrl::resetMainDomainCache();',start)+len('        ShopUrl::resetMainDomainCache();')
  body=native[start:end];self.assertEqual(body.count('(new StockManagerAdapter())'),1);expected=body.replace('(new StockManagerAdapter())','(new \\WatchysNativeStockAdapter())')
  override=(ROOT/'modules/watchysconfigurator/override/classes/order/OrderHistory.php').read_text();self.assertIn(expected,override)
if __name__=='__main__':unittest.main()
