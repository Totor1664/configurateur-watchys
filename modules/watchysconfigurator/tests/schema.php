<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';
$path=is_file(dirname(__DIR__).'/config/catalog.json')?dirname(__DIR__).'/config/catalog.json':dirname(__DIR__,3).'/integration/prestashop/catalog.json';
$base=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
$validate=function($data){$f=tmpfile();try{fwrite($f,json_encode($data));return new WatchysCatalog(stream_get_meta_data($f)['uri']);}finally{fclose($f);}};
$validate($base);$second=$base;$second['models'][]=$base['models'][0];$second['models'][1]['id']='second-model';$second['models'][1]['kitPriceExclTaxMinor']=25000;$validate($second);
$cases=[];$c=$base;$c['models'][]=$base['models'][0];$cases[]=$c;
$c=$base;$c['models'][0]['kitPriceExclTaxMinor']=-1;$cases[]=$c;
$c=$base;$c['models'][0]['kitPriceExclTaxMinor']='19000';$cases[]=$c;
$c=$base;$c['models'][0]['movement']['component']['units']=0;$cases[]=$c;
$c=$base;$c['models'][0]['options']['case']=[];$cases[]=$c;
$c=$base;$c['models'][0]['options']['case']['case-polished']['visual']['file']='../private';$cases[]=$c;
$c=$base;$c['models'][0]['options']['strap']['strap-blue']['preview']['parts'][0]=[0,0,5000,5000];$cases[]=$c;
$c=$base;$c['models'][0]['offers']['kit-without-movement']['workshopAssembly']=true;$cases[]=$c;
foreach($cases as $c){$rejected=false;try{$validate($c);}catch(RuntimeException $e){$rejected=true;}if(!$rejected){throw new RuntimeException('Catalogue invalide accepté.');}}
echo "SUCCÈS — schéma, unités, montants, géométrie, modèles et formules vérifiés.\n";
