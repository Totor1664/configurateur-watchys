<?php
require_once dirname(__DIR__).'/classes/WatchysStockAllocator.php';
/** Runs against caller-owned fixture tables only; caller must roll back. */
function watchysAllocationScenarios(PDO $db,$prefix)
{
 $check=static function($v,$m){if(!$v){throw new RuntimeException($m);}};
 $deny=static function($f)use($check){$caught=false;try{$f();}catch(InvalidArgumentException|RuntimeException $e){$caught=true;}$check($caught,'Refus attendu absent.');};
 $stock='`'.$prefix.'stock_available`';$component='`'.$prefix.'watchys_order_component`';$ledger='`'.$prefix.'watchys_stock_ledger`';
 $state=static function()use($db,$stock,$component,$ledger){return [$db->query('SELECT * FROM '.$stock.' ORDER BY id_stock_available')->fetchAll(PDO::FETCH_ASSOC),$db->query('SELECT * FROM '.$component.' ORDER BY id_order_detail,component_product_id,component_attribute_id')->fetchAll(PDO::FETCH_ASSOC),$db->query('SELECT * FROM '.$ledger.' ORDER BY id_stock_ledger')->fetchAll(PDO::FETCH_ASSOC)];};
 $req=static function($detail,$pid,$units){return ['identity'=>['id_order_detail'=>$detail,'component_product_id'=>$pid,'component_attribute_id'=>0,'id_order'=>2000000000,'id_customization'=>$detail,'id_shop'=>1],'required_units'=>$units,'nomenclature_hash'=>hash('sha256','fixture:'.$detail.':'.$pid.':'.$units)];};
 $status=static function($id,$resume=false){return ['kind'=>'status','status_id'=>$id,'allow_reactivation'=>$resume];};
 $alloc=new WatchysStockAllocator($db,$prefix);
 // Real stock fixture starts from copies of live rows; force deterministic shadow quantities.
 $db->exec('UPDATE '.$stock.' SET quantity=10,reserved_quantity=0,physical_quantity=10,depends_on_stock=0');
 $db->exec('UPDATE '.$stock.' SET quantity=1,physical_quantity=1 WHERE id_product=8188');
 $before=$state();$batch=[$req(2000000001,7736,1),$req(2000000001,8188,1),$req(2000000002,8188,1)];
 $deny(static function()use($alloc,$batch,$status){$alloc->apply($batch,'insufficient',$status(10));});
 $check($state()===$before,'Rupture partagée : modification partielle du stock ou journal.');
 $db->exec('UPDATE '.$stock.' SET quantity=10,physical_quantity=10 WHERE id_product=8188');
 $batch=[$req(2000000001,7736,2),$req(2000000001,8188,2),$req(2000000002,8188,1)];
 $r=$alloc->apply($batch,'history:1',$status(10));$check($r['deltas']['8188:0']['available']===-3,'Pièce partagée non cumulée.');
 $before=$state();$r=$alloc->apply($batch,'history:1',$status(10));$check($state()===$before&&$r['results'][0]['replayed'],'Reprise a retiré deux fois le stock.');
 $r=$alloc->apply($batch,'history:2',$status(2));$check($r['deltas']['8188:0']['available']===0,'Paiement : deuxième retrait.');
 $alloc->apply($batch,'history:3',$status(6));$check((int)$db->query('SELECT quantity FROM '.$stock.' WHERE id_product=8188')->fetchColumn()===10,'Annulation non restituée.');
 $before=$state();$deny(static function()use($alloc,$batch,$status){$alloc->apply($batch,'resume-denied',$status(2));});$check($state()===$before,'Réactivation implicite non annulée.');
 $alloc->apply($batch,'resume',$status(2,true));$alloc->apply($batch,'shipping',$status(4));
 $check((int)$db->query('SELECT physical_quantity FROM '.$stock.' WHERE id_product=8188')->fetchColumn()===7,'Expédition incorrecte.');
 $alloc->apply($batch,'receipt:1',['kind'=>'return','returned_units_total'=>1]);
 $check((int)$db->query('SELECT quantity FROM '.$stock.' WHERE id_product=8188')->fetchColumn()===9,'Retours par ligne non cumulés.');
 $before=$state();$deny(static function()use($alloc,$batch,$status){$alloc->apply($batch,'history:1',$status(6));});$check($state()===$before,'Événement altéré a modifié un état.');
 $changed=$batch;$changed[0]['nomenclature_hash']=hash('sha256','altered');$deny(static function()use($alloc,$changed,$status){$alloc->apply($changed,'altered',$status(2));});$check($state()===$before,'Nomenclature altérée a laissé des écritures.');
 $duplicate=[$batch[0],$batch[0]];$deny(static function()use($alloc,$duplicate,$status){$alloc->apply($duplicate,'dup',$status(2));});
 // A native reserved-counter reset must fail closed, not silently release someone else's units.
 $pending=[$req(2000000003,7736,1)];$alloc->apply($pending,'pending',$status(10));$db->exec('UPDATE '.$stock.' SET reserved_quantity=0,physical_quantity=quantity WHERE id_product=7736');
 $before=$state();$deny(static function()use($alloc,$pending,$status){$alloc->apply($pending,'native-reset',$status(2));});$check($state()===$before,'Réservations natives écrasées non détectées.');
 return true;
}
