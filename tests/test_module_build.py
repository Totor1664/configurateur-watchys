import unittest,tempfile,importlib.util,json,zipfile,hashlib,shutil
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('watchys_build',ROOT/'integration/prestashop/build-dev-module.py');build=importlib.util.module_from_spec(spec);spec.loader.exec_module(build)
class BuildTests(unittest.TestCase):
 def test_reproducible_canonical_and_multi_model_assets(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=Path(tmp);clone=root/'repo';(clone/'integration/prestashop').mkdir(parents=True)
   shutil.copytree(ROOT/'modules',clone/'modules');shutil.copytree(ROOT/'integration/prestashop/dev-installer',clone/'integration/prestashop/dev-installer')
   catalog=json.loads((ROOT/'integration/prestashop/catalog.json').read_text());second=json.loads(json.dumps(catalog['models'][0]));second['id']='future-model';second['kitPriceExclTaxMinor']=25000;catalog['models'].append(second)
   (clone/'integration/prestashop/catalog.json').write_text(json.dumps(catalog))
   old=build.repo;build.repo=clone
   try:
    a,count=build.build(root/'a.zip');b,_=build.build(root/'b.zip');self.assertEqual(count,23);self.assertEqual(a.read_bytes(),b.read_bytes())
    self.assertFalse((clone/'modules/watchysconfigurator/config/catalog.json').exists())
    with zipfile.ZipFile(a) as z:
     compiled=json.loads(z.read('watchys-module-dev-0.1.1/watchysconfigurator/config/catalog.json'));self.assertEqual(len(compiled['models']),2)
     manifest=json.loads(z.read('watchys-module-dev-0.1.1/manifest.json'))
     for file,sha in manifest['files'].items():self.assertEqual(hashlib.sha256(z.read('watchys-module-dev-0.1.1/watchysconfigurator/'+file)).hexdigest(),sha)
   finally:build.repo=old
if __name__=='__main__':unittest.main()
