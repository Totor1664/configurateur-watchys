<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
define('_PS_VERSION_','8.2.7');define('_DB_PREFIX_','test_');
class Module {public $name,$tab,$version,$author,$need_instance,$bootstrap,$ps_versions_compliancy,$displayName,$description,$context; protected $_path='/modules/watchysconfigurator/';public function __construct(){$this->context=Context::getContext();}}
class Configuration {public static $ready=false;public static function get($key){return self::$ready;}}
class Context {public $shop,$cart,$cookie,$link;private static $instance;public static function getContext(){if(!self::$instance){self::$instance=new self();self::$instance->shop=(object)['id'=>1,'domain'=>'dev.watchys.ch','domain_ssl'=>'dev.watchys.ch'];}return self::$instance;}}
class Db {public static $rows=[];public static function getInstance(){return new self();}public function getValue($sql){return str_contains($sql,'id_product=9000');}public function getRow($sql){preg_match('/p.id_product=(\d+)$/',$sql,$m);return self::$rows[(int)$m[1]]??false;}}
class StockAvailable {public static $quantities=[];public static function getQuantityAvailableByProduct($id,$attribute,$shop){return self::$quantities[$id]??0;}}
require_once dirname(__DIR__).'/watchysconfigurator.php';
function check($condition,$message){if(!$condition){throw new RuntimeException($message);}}
class TestableWatchysconfigurator extends Watchysconfigurator {protected function catalogFile(){return is_file(dirname(__DIR__).'/config/catalog.json') ? dirname(__DIR__).'/config/catalog.json' : dirname(__DIR__,3).'/integration/prestashop/catalog.json';}}
$module=new TestableWatchysconfigurator();$model=$module->catalog()->models()[0];$selection=[];
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

class PrestaShopException extends Exception {}
class TestRedirect extends Exception {}
class Tools {public static function redirect($url){throw new TestRedirect($url);}}
class GuardService {public $checked=[];public function containsSupport($id){return $id===10;}public function assertCart($id,$change){$this->checked[]=[$id,$change];}}
class GuardModule extends TestableWatchysconfigurator {public $guard;public function cartService(){return $this->guard;}}
Context::getContext()->shop->domain='dev.watchys.ch';Configuration::$ready=true;
$guard=new GuardModule();$guard->guard=new GuardService();$blocked=false;
try{$guard->hookActionObjectOrderAddBefore(['object'=>(object)['id_cart'=>10]]);}catch(PrestaShopException $e){$blocked=true;}check($blocked,'Commande Watchys non bloquée avant création.');
$guard->hookActionObjectOrderAddBefore(['object'=>(object)['id_cart'=>11]]);
$context=Context::getContext();$context->cart=(object)['id'=>0];$context->cookie=(object)['id_cart'=>10];$context->link=new class{public function getPageLink(...$args){return '/cart?action=show';}};
$redirected=false;try{$guard->hookActionFrontControllerInitBefore(['controller'=>(object)['php_self'=>'order']]);}catch(TestRedirect $e){$redirected=true;}check($redirected,'Commande accessible avant chargement du panier.');
$guard->hookActionCartUpdateQuantityBefore(['cart'=>(object)['id'=>10],'product'=>(object)['id'=>9000],'id_customization'=>101,'quantity'=>2,'operator'=>'up']);check(count($guard->guard->checked)===1,'Contrôle quantité non appelé.');
$guard->hookActionCartUpdateQuantityBefore(['quantity'=>1,'operator'=>'down']);check(count($guard->guard->checked)===1,'Diminution de nettoyage bloquée.');
echo "SUCCÈS — hooks natifs : commande Watchys bloquée avant création, commande ordinaire conservée, accès checkout redirigé même avant chargement du panier, quantités contrôlées.\n";
