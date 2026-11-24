<?php
require_once dirname(__DIR__).'/classes/WatchysNativeStockCoordinator.php';
require_once dirname(__DIR__).'/classes/WatchysStockAllocator.php';
require_once dirname(__DIR__).'/classes/WatchysStockSchema.php';
function coordCheck($v,$m){if(!$v){throw new RuntimeException($m);}}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach(WatchysStockSchema::definitions('t_','sqlite') as $ddl){$db->exec($ddl);}
$db->exec('CREATE TABLE t_stock_available(id_stock_available INTEGER PRIMARY KEY,id_product INTEGER,id_product_attribute INTEGER,id_shop INTEGER,id_shop_group INTEGER,quantity INTEGER,reserved_quantity INTEGER,physical_quantity INTEGER,depends_on_stock INTEGER)');
$db->exec('CREATE TABLE t_orders(id_order INTEGER PRIMARY KEY,id_shop INTEGER,current_state INTEGER,valid INTEGER)');
$db->exec('CREATE TABLE t_order_detail(id_order_detail INTEGER PRIMARY KEY,id_order INTEGER,product_id INTEGER,product_attribute_id INTEGER,product_quantity INTEGER,product_quantity_refunded INTEGER)');
$db->exec('CREATE TABLE t_order_state(id_order_state INTEGER PRIMARY KEY,shipped INTEGER)');
$db->exec('INSERT INTO t_stock_available VALUES(1,7736,0,1,0,10,0,10,0)');$db->exec('INSERT INTO t_order_state VALUES(10,0),(4,1),(6,0),(8,0)');
$alloc=new WatchysStockAllocator($db,'t_');$coord=new WatchysNativeStockCoordinator($db,'t_',[7736]);
$request=[['identity'=>['id_order_detail'=>100,'component_product_id'=>7736,'component_attribute_id'=>0,'id_order'=>100,'id_customization'=>100,'id_shop'=>1],'required_units'=>2,'nomenclature_hash'=>hash('sha256','fixture')]];
$native=static function($n)use($db){$q=(int)$db->query('SELECT quantity FROM t_stock_available')->fetchColumn();$s=$db->prepare('UPDATE t_stock_available SET quantity=?,reserved_quantity=0,physical_quantity=?');$s->execute([$q-$n,$q-$n]);$db->exec('INSERT INTO t_orders VALUES(200,1,10,0)');$db->exec('INSERT INTO t_order_detail VALUES(200,200,7736,0,'.(int)$n.',0)');};
$db->beginTransaction();$alloc->apply($request,'reserve',['kind'=>'status','status_id'=>10]);
$coord->run([['productId'=>7736,'combinationId'=>0,'delta'=>-3]],static function()use($native){$native(3);return 'native-result';});
$r=$db->query('SELECT quantity,reserved_quantity,physical_quantity FROM t_stock_available')->fetch(PDO::FETCH_NUM);coordCheck(array_map('intval',$r)===[5,5,10],'Réservations ordinaires et Watchys non additionnées.');
$before=$r;$caught=false;try{$coord->run([['productId'=>7736,'combinationId'=>0,'delta'=>-6]],static function(){throw new LogicException('Ne doit pas atteindre le callback.');});}catch(InvalidArgumentException $e){$caught=true;}coordCheck($caught,'Dépassement autorisé.');
$caught=false;try{$coord->run([['productId'=>7736,'combinationId'=>0,'delta'=>-1]],static function()use($db){$db->exec('UPDATE t_stock_available SET quantity=0');throw new RuntimeException('failure');});}catch(RuntimeException $e){$caught=true;}
coordCheck($caught&&$db->query('SELECT quantity,reserved_quantity,physical_quantity FROM t_stock_available')->fetch(PDO::FETCH_NUM)===$before,'Callback échoué non annulé.');
$caught=false;try{$coord->run([['productId'=>7736,'combinationId'=>0,'delta'=>-1]],static function()use($db){$db->exec('UPDATE t_stock_available SET quantity=1');});}catch(RuntimeException $e){$caught=true;}coordCheck($caught&&$db->query('SELECT quantity,reserved_quantity,physical_quantity FROM t_stock_available')->fetch(PDO::FETCH_NUM)===$before,'Écriture périmée non refusée.');
$coord->run([['productId'=>7736,'combinationId'=>0,'delta'=>0]],static function()use($db){$db->exec('UPDATE t_orders SET current_state=4 WHERE id_order=200');});
$r=$db->query('SELECT quantity,reserved_quantity,physical_quantity FROM t_stock_available')->fetch(PDO::FETCH_NUM);coordCheck(array_map('intval',$r)===[5,2,7],'Expédition ordinaire a effacé la réservation Watchys.');
$db->rollBack();echo "SUCCÈS — Coordination isolée SQLite : réservations natives + Watchys, rupture avant callback, échec et débit périmé annulés, expédition ordinaire. Aucun raccordement natif installé.\n";
