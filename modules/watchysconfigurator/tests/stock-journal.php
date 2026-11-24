<?php
require_once dirname(__DIR__).'/classes/WatchysStockSchema.php';
require_once dirname(__DIR__).'/classes/WatchysStockJournal.php';
function ledgerCheck($condition,$message){if(!$condition){throw new RuntimeException($message);}}
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){throw new RuntimeException('Test local SQLite requis.');}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach(WatchysStockSchema::definitions('test_','sqlite') as $sql){$db->exec($sql);}
$journal=new WatchysStockJournal($db,'test_');
$id=['id_order_detail'=>100,'component_product_id'=>7736,'component_attribute_id'=>0,'id_order'=>50,'id_customization'=>60,'id_shop'=>1];$hash=hash('sha256','nomenclature-fixture');
$event=static function($status){return ['kind'=>'status','status_id'=>$status];};
try{$journal->record($id,2,$hash,'outside',$event(10));throw new Exception('Transaction absente acceptée.');}catch(RuntimeException $e){ledgerCheck($e->getMessage()==='Transaction du demandeur requise.','Erreur inattendue.');}
$db->beginTransaction();$a=$journal->record($id,2,$hash,'history:1',$event(10));ledgerCheck(!$a['replayed']&&$a['delta_available']===-2,'Allocation initiale incorrecte.');
$b=$journal->record($id,2,$hash,'history:1',$event(10));ledgerCheck($b['replayed']&&$b['delta_available']===0,'Reprise comptée deux fois.');$db->commit();
$db->beginTransaction();$journal->record($id,2,$hash,'history:2',$event(4));$old=$journal->record($id,2,$hash,'history:1',$event(10));ledgerCheck($old['replayed']&&$old['state']['shipped_units']===2&&$old['delta_reserved']===0,'Ancienne reprise a régressé l’état expédié.');$db->commit();
$db->beginTransaction();$conflict=false;try{$journal->record($id,2,$hash,'history:1',$event(6));}catch(InvalidArgumentException $e){$conflict=true;}$db->rollBack();ledgerCheck($conflict,'Réutilisation d’événement altéré acceptée.');
$before=(int)$db->query('SELECT COUNT(*) FROM test_watchys_stock_ledger')->fetchColumn();
$db->beginTransaction();$journal->record($id,2,$hash,'receipt:1',['kind'=>'return','returned_units_total'=>1]);$db->rollBack();
ledgerCheck((int)$db->query('SELECT COUNT(*) FROM test_watchys_stock_ledger')->fetchColumn()===$before,'Retour arrière du journal échoué.');
ledgerCheck((int)$db->query('SELECT returned_units FROM test_watchys_order_component')->fetchColumn()===0,'Compteurs non rétablis après échec.');
$db->beginTransaction();$changed=false;try{$journal->record($id,3,$hash,'changed',$event(2));}catch(InvalidArgumentException $e){$changed=true;}$db->rollBack();ledgerCheck($changed,'Nomenclature modifiée acceptée.');
$db->beginTransaction();$other=$id;$other['id_order_detail']=101;$other['id_customization']=61;$other['component_product_id']=8188;$journal->record($other,2,hash('sha256','other'),'history:1',$event(10));$db->commit();
ledgerCheck((int)$db->query('SELECT COUNT(*) FROM test_watchys_order_component')->fetchColumn()===2,'Deux lignes ont été fusionnées.');
echo "SUCCÈS — Journal SQLite : transactions réelles, reprise sans doublon ni régression, conflit d’événement, nomenclature immuable et retour arrière. Aucun accès MySQL ; concurrence native encore à tester.\n";
