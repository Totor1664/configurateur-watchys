<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}ini_set('display_errors','0');
try{require_once __DIR__.'/DevGuard.php';watchysLotCapability($argv[1]??'','native-lot-index');[$home,$site,$check]=watchysLotDev();$check=null;watchysLotBootstrap($site);PrestaShopAutoload::getInstance()->generateIndex();echo "Index natif régénéré.\n";}catch(Throwable $e){echo "ARRÊT — Index natif non confirmé.\n";exit(1);}
