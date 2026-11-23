import unittest,tempfile,importlib.util,json,zipfile,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('watchys_cart_build',ROOT/'integration/prestashop/build-cart-dev.py');build=importlib.util.module_from_spec(spec);spec.loader.exec_module(build)
class BuildTests(unittest.TestCase):
 def test_reproducible_patch_and_preserved_visuals(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=Path(tmp);a,count=build.build(root/'a.zip');b,_=build.build(root/'b.zip');self.assertEqual(count,9);self.assertEqual(a.read_bytes(),b.read_bytes())
   with zipfile.ZipFile(a) as z:
    prefix='watchys-panier-dev-0.2.2/';manifest=json.loads(z.read(prefix+'manifest.json'));baseline=json.loads(z.read(prefix+'baseline.json'))
    self.assertFalse(manifest['checkoutEnabled']);self.assertEqual(len(baseline['assets']),29)
    self.assertNotIn('config/catalog.json',manifest['files']);self.assertNotIn('views/js/preview-reference-v013.js',manifest['files']);self.assertFalse(any(x.endswith('.png') for x in manifest['files']))
    for name,sha in manifest['files'].items():self.assertEqual(hashlib.sha256(z.read(prefix+'module/'+name)).hexdigest(),sha)
    for name,sha in manifest['checks'].items():self.assertEqual(hashlib.sha256(z.read(prefix+name)).hexdigest(),sha)
 def test_legacy_builder_disabled(self):
  spec=importlib.util.spec_from_file_location('legacy',ROOT/'integration/prestashop/build-dev-module.py');legacy=importlib.util.module_from_spec(spec);spec.loader.exec_module(legacy)
  with self.assertRaises(RuntimeError):legacy.build('unused.zip')
if __name__=='__main__':unittest.main()
