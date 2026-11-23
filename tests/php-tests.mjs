import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root=fileURLToPath(new URL('../',import.meta.url));
const php=process.env.WATCHYS_PHP_BIN||'php';
for(const file of ['modules/watchysconfigurator/tests/catalog.php','modules/watchysconfigurator/tests/service.php','modules/watchysconfigurator/tests/request.php','modules/watchysconfigurator/tests/schema.php','integration/prestashop/dev-installer/tests/files.php']){
 const r=spawnSync(php,[path.join(root,file)],{cwd:root,stdio:'inherit',env:process.env});
 if(r.error){console.error('PHP requis : installez PHP 8.2+ ou définissez WATCHYS_PHP_BIN.');process.exit(1);}if(r.status!==0)process.exit(r.status||1);
}
