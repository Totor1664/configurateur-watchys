<?php
if(PHP_SAPI!=='cli'){exit(1);}
define('_DB_PREFIX_','test_');
function pSQL($s){return $s;}
class Validate{public static function isLoadedObject($o){return $o&&($o->id??0)>0;}}
class Configuration{public static function get($k){return $k==='PS_CURRENCY_DEFAULT'?2:false;}}
class Currency{public $id,$iso_code='CHF';public function __construct($id){$this->id=$id;}}
class Tools{public static function convertPriceFull($v,$from,$to){return $v;}}
class Product{const CUSTOMIZE_TEXTFIELD=1;public $id,$active=1,$available_for_order=1,$price=0,$reference='WCFG-7750-41-CARTDEV',$customizable=1;public function __construct($id,...$unused){$this->id=$id;}}
class Db{
    public static $instance;public $tables=[],$backup=null,$next=100,$last=0;
    public static function getInstance(){return self::$instance??=new self();}
    public function execute($sql){
        if($sql==='START TRANSACTION'){$this->backup=unserialize(serialize($this->tables));return true;}
        if($sql==='COMMIT'){$this->backup=null;return true;}
        if($sql==='ROLLBACK'){$this->tables=$this->backup;$this->backup=null;return true;}
        throw new RuntimeException('SQL inattendu : '.$sql);
    }
    public function insert($table,$row){$this->tables[$table][]=$row;return true;}
    public function Insert_ID(){return $this->last;}
    public function executeS($sql){
        if(str_contains($sql,'SELECT id_product FROM `test_watchys_model_support`')){return [['id_product'=>9000]];}
        if(str_contains($sql,'cart` WHERE id_cart=')){return [['id_cart'=>10]];}
        if(str_contains($sql,'GROUP BY')){preg_match('/WHERE id_cart=(\d+)/',$sql,$m);return array_values(array_filter($this->tables['cart_product']??[],fn($r)=>(int)$r['id_cart']===(int)$m[1]));}
        throw new RuntimeException('SELECT inattendu : '.$sql);
    }
    public function getRow($sql){
        if(str_contains($sql,'watchys_model_support')){return ['id_product'=>9000,'reference'=>'WCFG-7750-41-CARTDEV','fields_json'=>json_encode(['model'=>1,'dial'=>2,'strap'=>3,'hands'=>4,'case'=>5,'offer'=>6])];}
        if(str_contains($sql,'watchys_cart_request')){preg_match("/id_cart=(\d+) AND request_key='([a-f0-9]+)'/",$sql,$m);foreach($this->tables['watchys_cart_request']??[] as $r){if($r['id_cart']==$m[1]&&$r['request_key']===$m[2]){return $r;}}return false;}
        if(str_contains($sql,'JOIN `test_customization`')){
            preg_match('/wc.id_customization=(\d+) AND c.id_cart=(\d+)/',$sql,$m);$custom=$this->tables['customization'][(int)$m[1]]??null;
            if(!$custom||$custom['id_cart']!=$m[2]){return false;}
            foreach($this->tables['watchys_configuration']??[] as $r){if($r['id_customization']==$m[1]){return $r+['native_product'=>$custom['id_product']];}}return false;
        }
        if(str_contains($sql,'SELECT quantity FROM')){preg_match('/id_customization=(\d+)/',$sql,$m);foreach($this->tables['cart_product']??[] as $r){if($r['id_customization']==$m[1]){return $r;}}return false;}
        throw new RuntimeException('getRow inattendu : '.$sql);
    }
    public function getValue($sql){
        if(str_contains($sql,'SELECT weight')){return '0.02';}
        if(str_contains($sql,'SUM(price)')){preg_match('/id_customization=(\d+)/',$sql,$m);return array_sum(array_column(array_filter($this->tables['customized_data']??[],fn($r)=>$r['id_customization']==$m[1]),'price'));}
        throw new RuntimeException('getValue inattendu : '.$sql);
    }
}
class Customization{
    public static function isFeatureActive(){return true;}
    public $id,$id_cart,$id_product,$id_product_attribute,$id_address_delivery,$quantity,$quantity_refunded,$quantity_returned,$in_cart;
    public function add(){$db=Db::getInstance();$this->id=++$db->next;$db->tables['customization'][$this->id]=get_object_vars($this);return true;}
}
class Cart{
    public $id_customer=0,$id=10,$id_shop=1,$id_currency=2,$id_address_delivery=0,$fail=false;
    public function OrderExists(){return false;}
    public function updateQty($qty,$product,$attribute,$customization,...$rest){
        Db::getInstance()->tables['cart_product'][]=['id_cart'=>10,'id_product'=>$product,'id_product_attribute'=>0,'id_customization'=>$customization,'quantity'=>$qty];
        Db::getInstance()->tables['customization'][$customization]['quantity']=$qty;
        Db::getInstance()->tables['customization'][$customization]['in_cart']=1;
        return !$this->fail;
    }
}
class Context{
    public static $instance;public $cart,$currency,$shop,$customer;
    public static function getContext(){return self::$instance;}
}
require_once dirname(__DIR__).'/classes/WatchysCatalog.php';require_once dirname(__DIR__).'/classes/WatchysCartService.php';
class TestCartModule{
    public $id=145,$catalogue,$stock=[];
    public function isDev(){return true;}
    public function catalog(){return $this->catalogue;}
    public function componentStatus($c){return ['exists'=>true,'referenceMatches'=>true,'active'=>true,'orderable'=>true,'quantity'=>$this->stock[$c['productId']]??10,'available'=>true];}
    public function quoteSelection($input){$q=$this->catalogue->quote($input['modelId'],$input['selection'],$input['offerId'],$input['quantity']);$q['componentsAvailable']=true;return $q;}
}
function ensure($v,$m){if(!$v){throw new RuntimeException($m);}}
function reject($fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Rejet attendu.');}
$c=Context::$instance=new Context();$c->cart=new Cart();$c->currency=new Currency(2);$c->shop=(object)['id'=>1];
$module=new TestCartModule();$path=is_file(dirname(__DIR__).'/config/catalog.json')?dirname(__DIR__).'/config/catalog.json':dirname(__DIR__,3).'/integration/prestashop/catalog.json';$module->catalogue=new WatchysCatalog($path);$model=$module->catalogue->models()[0];$selection=[];
foreach($model['options'] as $g=>$opts){$selection[$g]=array_key_first($opts);}
$service=new WatchysCartService($module);$input=['modelId'=>$model['id'],'offerId'=>'kit-without-movement','selection'=>$selection,'quantity'=>1,'requestKey'=>str_repeat('a',32),'price'=>0];
$r=$service->add($input);$db=Db::getInstance();ensure(count($db->tables['cart_product'])===1,'Ligne absente.');
ensure(array_sum(array_column($db->tables['customized_data'],'price'))===190.0,'Prix natif incorrect.');
$replay=$service->add($input);ensure($replay['replayed']&&count($db->tables['cart_product'])===1,'Double clic doublant la ligne.');
$other=$input;$other['quantity']=2;reject(fn()=>$service->add($other));
foreach(['kit-with-movement','workshop-assembled'] as $i=>$offer){$next=$input;$next['offerId']=$offer;$next['requestKey']=str_repeat((string)($i+1),32);$service->add($next);}
ensure(count($db->tables['cart_product'])===3,'Formules fusionnées.');ensure(array_sum(array_column($db->tables['customized_data'],'price'))===1310.0,'Somme des trois formules incorrecte.');
$pid=$model['options']['dial'][$selection['dial']]['component']['productId'];$module->stock[$pid]=3;$next=$input;$next['requestKey']=str_repeat('b',32);reject(fn()=>$service->add($next));ensure(count($db->tables['cart_product'])===3,'Composant commun surconsommé.');
$module->stock[$pid]=4;
$db->tables['cart_product'][]=['id_cart'=>10,'id_product'=>$pid,'id_product_attribute'=>0,'id_customization'=>0,'quantity'=>1];reject(fn()=>$service->add($next));array_pop($db->tables['cart_product']);
$module->stock[$pid]=10;$c->cart->fail=true;$before=$db->tables;
try{$service->add($next);throw new LogicException('Erreur native attendue.');}catch(RuntimeException $e){ensure(!($e instanceof LogicException),'Ajout échoué accepté.');}
ensure($db->tables===$before,'Écritures partielles conservées après échec natif.');$c->cart->fail=false;
$id=$r['customizationId'];$db->tables['customization'][$id]['id_cart']=99;reject(fn()=>$service->assertCart(10));$db->tables['customization'][$id]['id_cart']=10;
$db->tables['customized_data'][0]['price']='1.000000';reject(fn()=>$service->assertCart(10));$db->tables['customized_data'][0]['price']='0.000000';
$c->cart->id_customer=44;reject(fn()=>$service->add($next));$c->cart->id_customer=0;
$c->currency->iso_code='EUR';reject(fn()=>$service->add($next));
echo "SUCCÈS — pont panier avec doubles natifs : 3 formules distinctes, reprise sans doublon, composants communs et ventes séparées, refus inter-paniers, altération de prix, retour arrière après échec. Aucun stock modifié.\n";
