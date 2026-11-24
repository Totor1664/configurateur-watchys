<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
try{
 require_once __DIR__.'/DevGuard.php';$path=$argv[1]??'';$state=watchysLotCapability($path,'native-lot-overrides');[$home,$site,$check]=watchysLotDev();$check=null;
 watchysLotBootstrap($site);global $kernel;$kernel=new AppKernel('prod',false);$kernel->boot();Shop::setContext(Shop::CONTEXT_SHOP,1);Context::getContext()->shop=new Shop(1);$m=Module::getInstanceByName('watchysconfigurator');if(!$m||!$m->isDev()||$m->version!=='0.3.0'){throw new RuntimeException('Module natif attendu.');}
 foreach($state['overridePaths'] as $relative){$class=basename($relative,'.php');$target=$site.'/'.$relative;if(file_exists($target)){throw new RuntimeException('Surcharge préexistante refusée.');}
  try{if(!$m->addOverride($class)){throw new RuntimeException('Ajout de surcharge non confirmé : '.$class);}}finally{if(is_file($target)&&!is_link($target)){$state['overrideAfter'][$relative]=hash_file('sha256',$target);file_put_contents($path,json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX);chmod($path,0600);}}
 }
 echo "SUCCÈS — Six surcharges natives installées par l’API du module, code passif hors capacité d’essai.\n";
}catch(Throwable $e){echo 'ARRÊT SURCHARGES — '.get_class($e).' | '.mb_substr(preg_replace('/\b(?:SELECT|INSERT|UPDATE|DELETE)\b.*$/is','détail SQL masqué',$e->getMessage()),0,300)."\n";exit(1);}
