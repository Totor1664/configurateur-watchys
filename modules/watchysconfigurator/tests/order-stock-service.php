<?php
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';
require_once dirname(__DIR__).'/classes/WatchysStockSchema.php';
require_once dirname(__DIR__).'/classes/WatchysOrderStockService.php';
function orderStockCheck($v,$m){if(!$v){throw new RuntimeException($m);}}
function orderStockDeny($f){$caught=false;try{$f();}catch(InvalidArgumentException|RuntimeException $e){$caught=true;}orderStockCheck($caught,'Rejet attendu absent.');}
$path=is_file(dirname(__DIR__).'/config/catalog.json')?dirname(__DIR__).'/config/catalog.json':dirname(__DIR__,3).'/integration/prestashop/catalog.json';$catalog=new WatchysCatalog($path);$model=$catalog->models()[0];$selection=[];foreach($model['options'] as $g=>$options){$selection[$g]=array_key_first($options);}foreach($model['offers'] as $key=>$offer){if($offer['includeMovement']){$offerId=$key;break;}}$quote=$catalog->quote($model['id'],$selection,$offerId,99);
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);foreach(WatchysStockSchema::definitions('s_','sqlite') as $ddl){$db->exec($ddl);}
foreach([
 'stock_available'=>'id_stock_available INTEGER PRIMARY KEY,id_product INT,id_product_attribute INT,id_shop INT,id_shop_group INT,quantity INT,reserved_quantity INT,physical_quantity INT,depends_on_stock INT',
 'orders'=>'id_order INT PRIMARY KEY,id_cart INT,id_shop INT,current_state INT,total_paid_tax_excl TEXT',
 'order_detail'=>'id_order_detail INT PRIMARY KEY,id_order INT,id_shop INT,product_id INT,product_attribute_id INT,id_customization INT,product_quantity INT',
 'order_history'=>'id_order_history INT PRIMARY KEY,id_order INT,id_order_state INT',
 'watchys_model_support'=>'model_id TEXT PRIMARY KEY,id_product INT',
 'watchys_configuration'=>'id_customization INT PRIMARY KEY,id_cart INT,id_product INT,snapshot TEXT',
 'customization'=>'id_customization INT PRIMARY KEY,id_cart INT,id_product INT,id_product_attribute INT,quantity INT'
] as $table=>$fields){$db->exec('CREATE TABLE s_'.$table.'('.$fields.')');}
$db->prepare('INSERT INTO s_watchys_model_support VALUES(?,8867)')->execute([$model['id']]);
$db->exec("INSERT INTO s_orders VALUES(100,30,1,10,'510.000000'),(101,30,1,10,'1020.000000'),(900,90,1,10,'320.000000')");
$db->exec('INSERT INTO s_order_detail VALUES(1000,100,1,8867,0,50,1),(1001,100,1,7736,0,0,1),(1010,101,1,8867,0,50,2),(9000,900,1,7736,0,0,1)');
$db->exec('INSERT INTO s_order_history VALUES(10,100,10),(20,101,10),(90,900,10)');
$db->exec('INSERT INTO s_customization VALUES(50,30,8867,0,3)');
$snapshot=json_encode(['schemaVersion'=>1,'quote'=>$quote,'selection'=>$selection]);$db->prepare('INSERT INTO s_watchys_configuration VALUES(50,30,8867,?)')->execute([$snapshot]);
$pid=1;foreach($quote['components'] as $piece){$id=$piece['component']['productId'];$q=$id===7736?9:10;$res=$id===7736?1:0;$db->prepare('INSERT INTO s_stock_available VALUES(?,?,0,1,0,?,?,10,0)')->execute([$pid++,$id,$q,$res]);}
$service=new WatchysOrderStockService($db,'s_',$catalog);orderStockDeny(static function()use($service){$service->applyHistory(100,10);});
$db->beginTransaction();$prices=$db->query('SELECT total_paid_tax_excl FROM s_orders ORDER BY id_order')->fetchAll(PDO::FETCH_COLUMN);
$r=$service->applyHistory(900,90);orderStockCheck($r['ordinary']&&!(int)$db->query('SELECT COUNT(*) FROM s_watchys_stock_ledger')->fetchColumn(),'Commande ordinaire traitée comme Watchys.');
$service->applyHistory(100,10);$service->applyHistory(101,20);
$state=static function()use($db){return array_map('intval',$db->query('SELECT quantity,reserved_quantity,physical_quantity FROM s_stock_available WHERE id_product=7736')->fetch(PDO::FETCH_NUM));};
orderStockCheck($state()===[6,4,10],'Commandes fractionnées ou ventes séparées mal cumulées.');
$ledger=(int)$db->query('SELECT COUNT(*) FROM s_watchys_stock_ledger')->fetchColumn();$service->applyHistory(100,10);orderStockCheck($state()===[6,4,10]&&(int)$db->query('SELECT COUNT(*) FROM s_watchys_stock_ledger')->fetchColumn()===$ledger,'Reprise a doublé le débit.');
$db->exec('UPDATE s_orders SET current_state=6 WHERE id_order=101');$db->exec('INSERT INTO s_order_history VALUES(21,101,6)');$service->applyHistory(101,21);orderStockCheck($state()===[8,2,10],'Annulation fractionnée incorrecte.');
$r=$service->applyHistory(101,20);orderStockCheck($r['ignoredStale']&&$state()===[8,2,10],'Historique ancien a réactivé une commande.');
$db->exec('UPDATE s_orders SET current_state=2 WHERE id_order=101');$db->exec('INSERT INTO s_order_history VALUES(22,101,2)');orderStockDeny(static function()use($service){$service->applyHistory(101,22);});orderStockCheck($state()===[8,2,10],'Réactivation implicite a modifié le stock.');$service->applyHistory(101,22,true);orderStockCheck($state()===[6,4,10],'Réactivation explicite incorrecte.');
$db->exec('UPDATE s_orders SET current_state=4 WHERE id_order=101');$db->exec('INSERT INTO s_order_history VALUES(23,101,4)');$service->applyHistory(101,23);orderStockCheck($state()===[6,2,8],'Expédition fractionnée incorrecte.');
$db->exec('SAVEPOINT invalid');$db->exec('UPDATE s_watchys_configuration SET id_cart=31');orderStockDeny(static function()use($service){$service->applyHistory(100,10);});$db->exec('ROLLBACK TO SAVEPOINT invalid');
$db->exec('SAVEPOINT duplicate');$db->exec('INSERT INTO s_order_detail VALUES(1011,101,1,8867,0,50,1)');orderStockDeny(static function()use($service){$service->applyHistory(101,23);});$db->exec('ROLLBACK TO SAVEPOINT duplicate');
orderStockDeny(static function()use($service){$service->applyHistory(100,23);});
orderStockCheck($db->query('SELECT total_paid_tax_excl FROM s_orders ORDER BY id_order')->fetchAll(PDO::FETCH_COLUMN)===$prices,'Montants natifs modifiés.');
$db->rollBack();orderStockCheck((int)$db->query('SELECT COUNT(*) FROM s_watchys_order_component')->fetchColumn()===0,'Transaction appelante non respectée.');
echo "SUCCÈS — Service de commandes SQL : lignes natives/personnalisations, commandes fractionnées, reprises et historiques anciens, annulation/réactivation/expédition, refus des doublons/instantanés étrangers, prix conservés et rollback appelant. SQLite ; aucun raccordement natif activé.\n";
