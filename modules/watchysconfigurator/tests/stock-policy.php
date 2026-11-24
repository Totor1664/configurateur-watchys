<?php
require_once dirname(__DIR__).'/classes/WatchysStockPolicy.php';
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';
function stockCheck($condition,$message) { if (!$condition) { throw new RuntimeException($message); } }
function stockRefused($call) { try { $call(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Entrée invalide acceptée.'); }
$status=static function($id,$resume=false){return ['kind'=>'status','status_id'=>$id,'allow_reactivation'=>$resume];};
$initial=WatchysStockPolicy::initial(2);
$p=WatchysStockPolicy::plan($initial,$status(10));
stockCheck($p['delta_available']===-2&&$p['delta_reserved']===2&&$p['delta_physical']===0,'Attente virement non réservée.');
$reserved=$p['after'];
foreach(array_merge(WatchysStockPolicy::RESERVE,WatchysStockPolicy::FINANCIAL_ONLY) as $id) {
    $p=WatchysStockPolicy::plan($reserved,$status($id)); stockCheck($p['delta_available']===0&&$p['delta_physical']===0,'Deuxième retrait lors d’un paiement/remboursement.');
}
$canceled=WatchysStockPolicy::plan($reserved,$status(6));
stockCheck($canceled['delta_available']===2&&$canceled['delta_reserved']===-2&&$canceled['delta_physical']===0,'Annulation non restituée.');
stockCheck(WatchysStockPolicy::plan($canceled['after'],$status(6))['delta_available']===0,'Double restitution.');
stockRefused(static function()use($canceled,$status){WatchysStockPolicy::plan($canceled['after'],$status(2));});
$resumed=WatchysStockPolicy::plan($canceled['after'],$status(2,true));stockCheck($resumed['additional_required']===2,'Réactivation sans nouveau besoin.');
$shipped=WatchysStockPolicy::plan($reserved,$status(4));stockCheck($shipped['delta_available']===0&&$shipped['delta_physical']===-2&&$shipped['delta_reserved']===-2,'Expédition double retrait ou réservations conservées.');
foreach([5,6,7,8,18,21,23] as $id){$p=WatchysStockPolicy::plan($shipped['after'],$status($id));stockCheck($p['delta_available']===0&&$p['delta_physical']===0,'Restitution sans retour après expédition.');}
$returned=WatchysStockPolicy::plan($shipped['after'],['kind'=>'return','returned_units_total'=>1]);stockCheck($returned['delta_available']===1&&$returned['delta_physical']===1,'Retour partiel incorrect.');
stockCheck(WatchysStockPolicy::plan($returned['after'],['kind'=>'return','returned_units_total'=>1])['delta_available']===0,'Retour compté deux fois.');
$full=WatchysStockPolicy::plan($returned['after'],['kind'=>'return','returned_units_total'=>2]);stockCheck($full['delta_available']===1,'Retour complet cumulatif incorrect.');
stockCheck(WatchysStockPolicy::plan($full['after'],['kind'=>'return','returned_units_total'=>1])['delta_available']===0,'Ancien événement de retour réappliqué.');
stockRefused(static function()use($shipped){WatchysStockPolicy::plan($shipped['after'],['kind'=>'return','returned_units_total'=>3]);});
stockRefused(static function()use($initial,$status){WatchysStockPolicy::plan($initial,$status(28));});
stockRefused(static function()use($initial){WatchysStockPolicy::plan($initial,['kind'=>'status','status_id'=>'2']);});
stockRefused(static function(){WatchysStockPolicy::initial(0);});
$path=is_file(dirname(__DIR__).'/config/catalog.json')?dirname(__DIR__).'/config/catalog.json':dirname(__DIR__,3).'/integration/prestashop/catalog.json';$catalog=new WatchysCatalog($path);$m=$catalog->models()[0];$count=0;
foreach($m['options']['case'] as $ca=>$unused)foreach($m['options']['dial'] as $di=>$unused)foreach($m['options']['hands'] as $ha=>$unused)foreach($m['options']['strap'] as $st=>$unused)foreach($m['offers'] as $offer=>$offerData){
 $quote=$catalog->quote($m['id'],['case'=>$ca,'dial'=>$di,'hands'=>$ha,'strap'=>$st],$offer,2);
 stockCheck(count($quote['components'])===($offerData['includeMovement']?5:4),'Mouvement optionnel ou prestation stockée incorrectement.');
 foreach($quote['components'] as $piece){$required=$piece['component']['units']*$quote['quantity'];$state=WatchysStockPolicy::initial($required);$available=100;$physical=100;
  foreach([$status(10),$status(2),$status(3),$status(4),$status(5),$status(7),['kind'=>'return','returned_units_total'=>$required]] as $event){$plan=WatchysStockPolicy::plan($state,$event);$available+=$plan['delta_available'];$physical+=$plan['delta_physical'];$state=$plan['after'];stockCheck($physical===$available+$state['reserved_units'],'Relation disponible/réservé/physique cassée.');}
  stockCheck($available===100&&$physical===100,'Cycle complet non soldé.');
 }$count++;
}
echo "SUCCÈS — $count configurations/formules : réservation, paiement sans double retrait, expédition, annulation, réactivation explicite, remboursements et retours partiels/cumulatifs. Aucune connexion MySQL ni écriture de stock.\n";
