<?php
require_once dirname(__DIR__).'/classes/WatchysStockSchema.php';
require_once __DIR__.'/stock-allocation-scenarios.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach(WatchysStockSchema::definitions('test_','sqlite') as $sql){$db->exec($sql);}
$db->exec('CREATE TABLE test_stock_available (id_stock_available INTEGER PRIMARY KEY,id_product INTEGER,id_product_attribute INTEGER,id_shop INTEGER,id_shop_group INTEGER,quantity INTEGER,reserved_quantity INTEGER,physical_quantity INTEGER,depends_on_stock INTEGER)');
$db->exec('INSERT INTO test_stock_available VALUES (1,7736,0,1,0,10,0,10,0),(2,8188,0,1,0,10,0,10,0)');
$db->beginTransaction();watchysAllocationScenarios($db,'test_');$db->rollBack();
// Inject a SQL failure on the second inventory row, after the first has been written.
$db->exec("CREATE TRIGGER stock_failure BEFORE UPDATE ON test_stock_available WHEN NEW.id_product=8188 BEGIN SELECT RAISE(ABORT,'injected failure'); END");
$db->beginTransaction();$db->exec('UPDATE test_stock_available SET quantity=9,physical_quantity=9 WHERE id_product=7736');
$before=$db->query('SELECT * FROM test_stock_available ORDER BY id_stock_available')->fetchAll(PDO::FETCH_ASSOC);
$requests=[];foreach([7736,8188] as $pid){$requests[]=['identity'=>['id_order_detail'=>1,'component_product_id'=>$pid,'component_attribute_id'=>0,'id_order'=>1,'id_customization'=>1,'id_shop'=>1],'required_units'=>1,'nomenclature_hash'=>hash('sha256','fixture')];}
$caught=false;try{(new WatchysStockAllocator($db,'test_'))->apply($requests,'sql-failure',['kind'=>'status','status_id'=>10]);}catch(PDOException $e){$caught=str_contains($e->getMessage(),'injected failure');}
if(!$caught||!$db->inTransaction()||$db->query('SELECT * FROM test_stock_available ORDER BY id_stock_available')->fetchAll(PDO::FETCH_ASSOC)!==$before||(int)$db->query('SELECT COUNT(*) FROM test_watchys_stock_ledger')->fetchColumn()!==0||(int)$db->query('SELECT COUNT(*) FROM test_watchys_order_component')->fetchColumn()!==0){throw new RuntimeException('Échec SQL : lot partiel ou travail extérieur perdu.');}
$db->rollBack();echo "SUCCÈS — Allocation SQLite : rupture partagée, reprise, paiement, annulation, réactivation, retour et réservations écrasées ; échec SQL après première écriture annulé par savepoint. Pas de preuve de concurrence MySQL/native.\n";
