<?php
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';
require_once dirname(__DIR__).'/classes/WatchysOrderNomenclature.php';
function nomenCheck($v,$m){if(!$v){throw new RuntimeException($m);}}
$path=is_file(dirname(__DIR__).'/config/catalog.json')?dirname(__DIR__).'/config/catalog.json':dirname(__DIR__,3).'/integration/prestashop/catalog.json';$catalog=new WatchysCatalog($path);$m=$catalog->models()[0];$count=0;
$line=['product_id'=>8867,'id_order_detail'=>100,'id_customization'=>50,'product_quantity'=>2,'id_order'=>20,'id_shop'=>1,'product_attribute_id'=>0];
foreach($m['options']['case'] as $ca=>$unused)foreach($m['options']['dial'] as $di=>$unused)foreach($m['options']['hands'] as $ha=>$unused)foreach($m['options']['strap'] as $st=>$unused)foreach($m['offers'] as $offer=>$offerData){
 $selection=['case'=>$ca,'dial'=>$di,'hands'=>$ha,'strap'=>$st];$quote=$catalog->quote($m['id'],$selection,$offer,7);
 $snapshot=['schemaVersion'=>1,'quote'=>$quote,'selection'=>$selection];$record=['id_cart'=>30,'id_product'=>8867,'id_customization'=>50,'snapshot'=>json_encode($snapshot)];
 $requests=WatchysOrderNomenclature::build(20,30,[$line,['product_id'=>7736]],[50=>$record],[$m['id']=>8867],$catalog);
 nomenCheck(count($requests)===($offerData['includeMovement']?5:4),'Pièce ou mouvement optionnel incorrect.');
 foreach($requests as $r){nomenCheck($r['required_units']===2&&$r['identity']['id_order_detail']===100,'Quantité du panier utilisée au lieu de la ligne native.');}++$count;
}
$second=$line;$second['id_order_detail']=101;$second['id_customization']=51;$second['product_quantity']=1;$other=$record;$other['id_customization']=51;
$both=WatchysOrderNomenclature::build(20,30,[$line,$second],[50=>$record,51=>$other],[$m['id']=>8867],$catalog);
nomenCheck(count(array_unique(array_column(array_column($both,'identity'),'id_order_detail')))===2,'Deux configurations du même produit fusionnées.');
$deny=static function($lines,$records)use($m,$catalog){$caught=false;try{WatchysOrderNomenclature::build(20,30,$lines,$records,[$m['id']=>8867],$catalog);}catch(InvalidArgumentException $e){$caught=true;}nomenCheck($caught,'Instantané ou identité invalide accepté.');};
$bad=$record;$bad['id_cart']=31;$deny([$line],[50=>$bad]);$deny([$line,$line],[50=>$record]);
$bad=$snapshot;$bad['quote']['components'][0]['component']['productId']=123;$badRecord=$record;$badRecord['snapshot']=json_encode($bad);$deny([$line],[50=>$badRecord]);
$badLine=$line;$badLine['id_order']=21;$deny([$badLine],[50=>$record]);
echo 'SUCCÈS — '.$count." nomenclatures : composants enregistrés contrôlés, mouvement optionnel, quantités des lignes natives, commandes fractionnées, configurations distinctes et refus des instantanés étrangers/altérés.\n";
