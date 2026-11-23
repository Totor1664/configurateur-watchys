<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/classes/WatchysRequest.php';
foreach(['','{','null','[]','true',str_repeat('x',16385)] as $raw){try{WatchysRequest::decode($raw);throw new RuntimeException('JSON invalide accepté.');}catch(InvalidArgumentException $e){}}
$input=WatchysRequest::decode('{"modelId":"test","quantity":2}');if($input['quantity']!==2){throw new RuntimeException('JSON valide rejeté.');}
class ModuleFrontController {public $module;public function ajaxRender($s){echo $s;}}
require dirname(__DIR__).'/controllers/front/api.php';
foreach(['POST'=>400,'GET'=>405] as $method=>$expected){$_SERVER['REQUEST_METHOD']=$method;http_response_code(200);$controller=new WatchysconfiguratorApiModuleFrontController();ob_start();$controller->displayAjax();$response=json_decode(ob_get_clean(),true);if(http_response_code()!==$expected||$response['ok']!==false){throw new RuntimeException('Code HTTP incorrect.');}}
echo "SUCCÈS — erreurs JSON classées comme requêtes invalides et taille bornée.\n";
