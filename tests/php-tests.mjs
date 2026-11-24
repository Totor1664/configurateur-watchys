import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root=fileURLToPath(new URL('../',import.meta.url));
const php=process.env.WATCHYS_PHP_BIN||'php';
for(const file of ['modules/watchysconfigurator/tests/stock-policy.php','modules/watchysconfigurator/tests/stock-journal.php','modules/watchysconfigurator/tests/stock-allocator.php','modules/watchysconfigurator/tests/native-stock-coordinator.php','modules/watchysconfigurator/tests/order-nomenclature.php','modules/watchysconfigurator/tests/cart-thumbnails.php','modules/watchysconfigurator/tests/cart-setup.php','modules/watchysconfigurator/tests/cart-service.php','modules/watchysconfigurator/tests/cart-policy.php','modules/watchysconfigurator/tests/catalog.php','modules/watchysconfigurator/tests/service.php','modules/watchysconfigurator/tests/request.php','modules/watchysconfigurator/tests/schema.php','integration/prestashop/dev-installer/tests/files.php']){
 const r=spawnSync(php,[path.join(root,file)],{cwd:root,encoding:'utf8',env:process.env});
 if(r.stdout)process.stdout.write(r.stdout);if(r.stderr)process.stderr.write(r.stderr);
 if(/Fatal error:|Uncaught [A-Za-z]+(?:Exception|Error)/.test((r.stdout||'')+(r.stderr||'')))process.exit(1);
 if(r.error){console.error('PHP requis : installez PHP 8.2+ ou définissez WATCHYS_PHP_BIN.');process.exit(1);}if(r.status!==0)process.exit(r.status||1);
}
