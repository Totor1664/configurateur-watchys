<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function rejected($callback) { try { $callback(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Entrée invalide acceptée.'); }
$catalog=new WatchysCatalog(($argv[1] ?? (is_file(dirname(__DIR__).'/config/catalog.json') ? dirname(__DIR__).'/config/catalog.json' : dirname(__DIR__,3).'/integration/prestashop/catalog.json'))); $count=0;
foreach($catalog->models() as $model) {
    $selections=[[]];
    foreach($model['options'] as $group=>$options) {
        $next=[];foreach($selections as $s) { foreach($options as $id=>$o) { $next[]=array_merge($s,[$group=>$id]); } }$selections=$next;
    }
    if($model['id']==='watchys-7750-41mm'){check(count($selections)===960,'Nombre de compositions inattendu.');}
    foreach($selections as $selection) {
        foreach($model['offers'] as $offer=>$definition) {
            $price=$model['kitPriceExclTaxMinor']+($definition['includeMovement']?$model['movement']['supplementExclTaxMinor']:0)+($definition['workshopAssembly']?$model['assemblySupplementExclTaxMinor']:0);
            foreach($selection as $group=>$id){$price+=$model['options'][$group][$id]['supplementExclTaxMinor']??0;}
            $q=$catalog->quote($model['id'],$selection,$offer,2);
            check($q['unitPriceExclTaxMinor']===$price && $q['totalExclTaxMinor']===$price*2,'Montant incorrect.');
            $ids=array_column(array_column($q['components'],'component'),'productId');
            check(in_array($model['movement']['component']['productId'],$ids,true)===$definition['includeMovement'],'Mouvement incorrect.');$count++;
        }
    }
    $selection=$selections[0];
    $validOffer=array_key_first($model['offers']);
    rejected(fn()=>$catalog->quote('unknown',$selection,$validOffer));
    rejected(fn()=>$catalog->quote($model['id'],$selection,'unknown'));
    rejected(fn()=>$catalog->quote($model['id'],array_merge($selection,['case'=>'wrong']),$validOffer));
    rejected(fn()=>$catalog->quote($model['id'],array_merge($selection,['extra'=>'x']),$validOffer));
    rejected(fn()=>$catalog->quote($model['id'],[],$validOffer));
    foreach([0,-1,101,1.5,'2'] as $quantity) { rejected(fn()=>$catalog->quote($model['id'],$selection,$validOffer,$quantity)); }
}
echo 'SUCCÈS — '.$count." calculs de formule/quantité et rejets des entrées invalides. Aucune connexion à MySQL.\n";
