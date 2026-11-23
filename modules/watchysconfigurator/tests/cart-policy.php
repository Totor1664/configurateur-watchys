<?php
if(PHP_SAPI!=='cli'){exit(1);}
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';require_once dirname(__DIR__).'/classes/WatchysCartPolicy.php';
$file=is_file(dirname(__DIR__).'/config/catalog.json')?dirname(__DIR__).'/config/catalog.json':dirname(__DIR__,3).'/integration/prestashop/catalog.json';
$catalog=new WatchysCatalog($file);$model=$catalog->models()[0];$n=0;
function must($v,$m){if(!$v){throw new RuntimeException($m);}}
function refused($fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Rejet attendu.');}
foreach($model['options']['dial'] as $dial=>$unused)foreach($model['options']['strap'] as $strap=>$unused)foreach($model['options']['hands'] as $hands=>$unused)foreach($model['options']['case'] as $case=>$unused)foreach($model['offers'] as $offer=>$unused){
    $input=['modelId'=>$model['id'],'selection'=>compact('dial','strap','hands','case'),'offerId'=>$offer,'quantity'=>2,'requestKey'=>str_repeat('a',32),'price'=>1,'productId'=>42];
    $r=WatchysCartPolicy::request($input,$catalog);must($r['quote']['unitPriceExclTaxMinor']===$catalog->quote($model['id'],$input['selection'],$offer,1)['unitPriceExclTaxMinor'],'Prix fourni par le client utilisé.');
    $requirements=WatchysCartPolicy::requirements([['components'=>$r['quote']['components'],'quantity'=>2],['components'=>$r['quote']['components'],'quantity'=>1]]);
    foreach($requirements as $req){must($req['required']===3*$req['component']['units'],'Composants communs non cumulés.');}
    must(isset($requirements['7736:0'])===($offer!=='kit-without-movement'),'Mouvement de formule incorrect.');$n++;
}
$base=$input;$base['quantity']=1;
foreach([0,101,1.2,'2',null] as $bad){$r=$base;$r['quantity']=$bad;refused(fn()=>WatchysCartPolicy::request($r,$catalog));}
foreach(['',str_repeat('x',32),str_repeat('b',33)] as $bad){$r=$base;$r['requestKey']=$bad;refused(fn()=>WatchysCartPolicy::request($r,$catalog));}
$a=WatchysCartPolicy::request($base,$catalog);$same=$base;$same['selection']=array_reverse($base['selection'],true);must(WatchysCartPolicy::request($same,$catalog)['digest']===$a['digest'],'Ordre JSON influençant la requête.');
$other=$base;$other['quantity']=2;must(WatchysCartPolicy::request($other,$catalog)['digest']!==$a['digest'],'Quantité absente du contrôle de reprise.');
$req=WatchysCartPolicy::requirements([['quantity'=>2,'components'=>$a['quote']['components']]]);
$status=fn($c)=>['exists'=>true,'referenceMatches'=>true,'active'=>true,'orderable'=>true,'quantity'=>2];WatchysCartPolicy::assertAvailable($req,$status);
foreach(['exists','referenceMatches','active','orderable'] as $field){refused(function()use($req,$field){WatchysCartPolicy::assertAvailable($req,fn($c)=>array_replace(['exists'=>true,'referenceMatches'=>true,'active'=>true,'orderable'=>true,'quantity'=>9],[$field=>false]));});}
refused(fn()=>WatchysCartPolicy::assertAvailable($req,fn($c)=>['exists'=>true,'referenceMatches'=>true,'active'=>true,'orderable'=>true,'quantity'=>1]));
must(WatchysCartPolicy::proposedQuantity(3,2,'down')===1,'Diminution incorrecte.');must(WatchysCartPolicy::proposedQuantity(3,5,'down')===0,'Suppression incorrecte.');refused(fn()=>WatchysCartPolicy::proposedQuantity(0,1,'replace'));
echo "SUCCÈS — $n configurations/formules : prix serveur, composants partagés, mouvement optionnel, statuts, quantités et empreintes de reprise.\n";
