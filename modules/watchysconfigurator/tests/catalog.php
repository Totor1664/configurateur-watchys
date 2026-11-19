<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function rejected($callback) { try { $callback(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Entrée invalide acceptée.'); }
$catalog=new WatchysCatalog(dirname(__DIR__).'/config/catalog.json'); $count=0;
foreach($catalog->models() as $model) {
    $selections=[[]];
    foreach($model['options'] as $group=>$options) {
        $next=[];foreach($selections as $s) { foreach($options as $id=>$o) { $next[]=array_merge($s,[$group=>$id]); } }$selections=$next;
    }
    check(count($selections)===960,'Nombre de compositions inattendu.');
    foreach($selections as $selection) {
        foreach(['kit-without-movement'=>19000,'kit-with-movement'=>51000,'workshop-assembled'=>61000] as $offer=>$price) {
            $q=$catalog->quote($model['id'],$selection,$offer,2);
            check($q['unitPriceExclTaxMinor']===$price && $q['totalExclTaxMinor']===$price*2,'Montant incorrect.');
            $ids=array_column(array_column($q['components'],'component'),'productId');
            check(in_array(7736,$ids,true)===($offer!=='kit-without-movement'),'Mouvement incorrect.');$count++;
        }
    }
    $selection=$selections[0];
    rejected(fn()=>$catalog->quote('unknown',$selection,'kit-with-movement'));
    rejected(fn()=>$catalog->quote($model['id'],$selection,'unknown'));
    rejected(fn()=>$catalog->quote($model['id'],array_merge($selection,['case'=>'wrong']),'kit-with-movement'));
    rejected(fn()=>$catalog->quote($model['id'],array_merge($selection,['extra'=>'x']),'kit-with-movement'));
    rejected(fn()=>$catalog->quote($model['id'],[],'kit-with-movement'));
    foreach([0,-1,101,1.5,'2'] as $quantity) { rejected(fn()=>$catalog->quote($model['id'],$selection,'kit-with-movement',$quantity)); }
}
echo 'SUCCÈS — '.$count." calculs de formule/quantité et rejets des entrées invalides. Aucune connexion à MySQL.\n";
