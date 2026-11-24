<?php
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';
require_once dirname(__DIR__).'/classes/WatchysCartThumbnails.php';
function checkThumbnail($condition,$message) { if (!$condition) { throw new RuntimeException($message); } }
$catalogPath=is_file(dirname(__DIR__).'/config/catalog.json')?dirname(__DIR__).'/config/catalog.json':dirname(__DIR__,3).'/integration/prestashop/catalog.json';
$catalog=new WatchysCatalog($catalogPath); $m=$catalog->models()[0]; $keys=[];
foreach ($m['options']['case'] as $ca=>$unused) foreach ($m['options']['dial'] as $di=>$unused) foreach ($m['options']['hands'] as $ha=>$unused) foreach ($m['options']['strap'] as $st=>$unused) {
    $s=['case'=>$ca,'dial'=>$di,'hands'=>$ha,'strap'=>$st]; $key=WatchysCartThumbnails::key($m,$s);
    checkThumbnail(is_string($key)&&strlen($key)===64&&!isset($keys[$key]),'Clé visuelle dupliquée.'); $keys[$key]=$s;
}
checkThumbnail(count($keys)===960,'Nombre de miniatures incorrect.');
$s=['case'=>'case-polished','dial'=>'dial-blue','hands'=>'hands-chrome','strap'=>'strap-blue'];
checkThumbnail(WatchysCartThumbnails::key($m,$s)==='907dbedc9b4f7a670e26906cb6c8cc1eee74104e533c720baa688e7078cd7d39','Désaccord PHP / générateur.');
checkThumbnail(WatchysCartThumbnails::key($m,$s+['price'=>1])===null,'Champs inattendus acceptés.');
checkThumbnail(WatchysCartThumbnails::key($m,array_replace($s,['case'=>'../../outside']))===null,'Chemin non fiable accepté.');
$other=array_replace($s,['hands'=>'hands-dauphine-red','case'=>'case-pvd-brushed','dial'=>'dial-green','strap'=>'strap-red']);
$line=static function($pid,$cid) { return ['id_product'=>$pid,'id_customization'=>$cid,'price'=>'190 CHF','quantity'=>2,'cover'=>['id_image'=>16190,'bySize'=>['cart_default'=>['url'=>'generic']]],'customizations'=>[['id_customization'=>$cid]]]; };
$products=[$line(8867,10),$line(8867,11),$line(42,0),$line(8867,99)];
$record=static function ($selection) { return ['id_product'=>8867,'snapshot'=>json_encode(['selection'=>$selection,'quote'=>['modelId'=>'watchys-7750-41mm']])]; };
$records=[10=>$record($s),11=>$record($other)];
$decorated=WatchysCartThumbnails::decorate($products,$records,$catalog,static function(){return true;},static function($key){return '/thumb/'.$key.'.jpg';});
checkThumbnail($decorated[0]['cover']['small']['url']!==$decorated[1]['cover']['small']['url'],'Deux configurations montrent la même image.');
checkThumbnail($decorated[2]===$products[2]&&$decorated[3]===$products[3],'Ligne ordinaire ou étrangère modifiée.');
foreach ([0,1] as $i) {
    checkThumbnail($decorated[$i]['price']===$products[$i]['price']&&$decorated[$i]['quantity']===$products[$i]['quantity']&&$decorated[$i]['customizations']===$products[$i]['customizations'],'Prix, quantité ou personnalisation modifiés.');
    foreach ($decorated[$i]['cover']['bySize'] as $size) { checkThumbnail($size['url']===$decorated[$i]['cover']['small']['url'],'Miniature incohérente entre formats.'); }
}
checkThumbnail(WatchysCartThumbnails::decorate($products,$records,$catalog,static function(){return false;},static function(){throw new Exception('URL appelée malgré image absente.');})===$products,'Secours générique incorrect.');
$invalid=[10=>['id_product'=>8867,'snapshot'=>'{'],11=>['id_product'=>7,'snapshot'=>$records[11]['snapshot']]];
checkThumbnail(WatchysCartThumbnails::decorate($products,$invalid,$catalog,static function(){return true;},static function(){return '/never';})===$products,'Configuration invalide ou autre produit accepté.');
// CartLazyArray exposes products as a rewritable method, and per-line products are ArrayAccess objects.
class ThumbnailLazy extends ArrayObject {}
$lazyProducts=[new ThumbnailLazy($products[0]),new ThumbnailLazy($products[1])];
$lazy=WatchysCartThumbnails::decorate($lazyProducts,$records,$catalog,static function(){return true;},static function($k){return '/thumb/'.$k;});
checkThumbnail($lazy[0]['cover']['small']['url']!==$lazy[1]['cover']['small']['url'],'LazyArray : mélange de configurations.');
echo "SUCCÈS — 960 clés uniques, concordance PHP/JS, deux configurations du même produit distinctes, objets ArrayAccess, formats panier/mini-panier, secours et conservation des prix/quantités. Aucun accès MySQL.\n";
