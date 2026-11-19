<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
define('_PS_VERSION_','8.2.7');define('_DB_PREFIX_','test_');
class Module {public $name,$tab,$version,$author,$need_instance,$bootstrap,$ps_versions_compliancy,$displayName,$description,$context; protected $_path='/modules/watchysconfigurator/';public function __construct(){$this->context=Context::getContext();}}
class Context {public $shop;private static $instance;public static function getContext(){if(!self::$instance){self::$instance=new self();self::$instance->shop=(object)['id'=>1,'domain'=>'dev.watchys.ch','domain_ssl'=>'dev.watchys.ch'];}return self::$instance;}}
class Db {public static $rows=[];public static function getInstance(){return new self();}public function getRow($sql){preg_match('/p.id_product=(\d+)$/',$sql,$m);return self::$rows[(int)$m[1]]??false;}}
class StockAvailable {public static $quantities=[];public static function getQuantityAvailableByProduct($id,$attribute,$shop){return self::$quantities[$id]??0;}}
require_once dirname(__DIR__).'/watchysconfigurator.php';
function check($condition,$message){if(!$condition){throw new RuntimeException($message);}}
$module=new Watchysconfigurator();$model=$module->catalog()->models()[0];$selection=[];
foreach($model['options'] as $group=>$options){$selection[$group]=array_key_first($options);foreach($options as $o){$c=$o['component'];Db::$rows[$c['productId']]=['reference'=>$c['expectedReference']??'','active'=>1,'available_for_order'=>1];StockAvailable::$quantities[$c['productId']]=10;}}
Db::$rows[7736]=['reference'=>'MVT26','active'=>1,'available_for_order'=>1];StockAvailable::$quantities[7736]=2;
$input=['modelId'=>$model['id'],'offerId'=>'workshop-assembled','selection'=>$selection,'quantity'=>2,'price'=>1,'productId'=>1];
$q=$module->quoteSelection($input);check($q['componentsAvailable'],'Stock disponible attendu.');check($q['totalExclTaxMinor']===122000,'Prix navigateur accepté.');check(!$q['checkoutEnabled'],'Commande activée par erreur.');
$input['quantity']=3;check(!$module->quoteSelection($input)['componentsAvailable'],'Mouvement insuffisant ignoré.');
$input['offerId']='kit-without-movement';check($module->quoteSelection($input)['componentsAvailable'],'Mouvement requis pour kit sans mouvement.');
$case=$model['options']['case'][$selection['case']]['component'];Db::$rows[$case['productId']]['active']=0;check(!$module->quoteSelection($input)['componentsAvailable'],'Composant désactivé accepté.');Db::$rows[$case['productId']]['active']=1;
$input['selection']['case']='case-pvd-polished';Db::$rows[8861]['reference']='OTHER';check(!$module->quoteSelection($input)['componentsAvailable'],'Référence différente acceptée.');
$presentation=$module->presentation();check(count($presentation['models'][0]['groups'])===4,'Groupes incomplets.');check(!$presentation['checkoutEnabled'],'Commande activée par erreur.');
Context::getContext()->shop->domain='www.watchys.ch';check(!$module->isDev(),'PROD non exclue.');
echo "SUCCÈS — prix serveur, quantités, formules, articles désactivés, références et exclusion PROD vérifiés avec doubles de test.\n";
