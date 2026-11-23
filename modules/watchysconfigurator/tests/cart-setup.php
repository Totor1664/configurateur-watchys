<?php
if(PHP_SAPI!=='cli'){exit(1);}
define('_DB_PREFIX_','test_');function pSQL($s){return $s;}
class Validate{public static function isLoadedObject($o){return $o&&$o->id>0;}}
class Language{public static function getLanguages(...$args){return [['id_lang'=>1],['id_lang'=>4]];}}
#[AllowDynamicProperties]
class Product{
    const CUSTOMIZE_TEXTFIELD=1;public $id=0;
    public function __construct($id=0,...$unused){
        if($id===7753){$this->id=7753;$this->id_tax_rules_group=6;$this->id_category_default=2150;}
        elseif($id&&isset(Db::getInstance()->state['products'][$id])){foreach(Db::getInstance()->state['products'][$id] as $k=>$v){$this->$k=$v;}}
    }
    public function add(){$this->id=9001;Db::getInstance()->state['products'][$this->id]=get_object_vars($this);return true;}
    public function addToCategories($ids){return $ids===[2150];}
}
class StockAvailable{
    public static function setQuantity($id,$attr,$qty,$shop){if($id!==9001||$qty!==0){throw new RuntimeException('Stock existant touché.');}}
    public static function setProductOutOfStock($id,$value,$shop){Db::getInstance()->state['out_of_stock'][$id]=$value;}
    public static function outOfStock($id,$shop){return Db::getInstance()->state['out_of_stock'][$id]??0;}
}
class Configuration{
    public static function updateGlobalValue($key,$value){Db::getInstance()->state['flags'][$key]=$value;return true;}
    public static function updateValue($key,$value,...$unused){return self::updateGlobalValue($key,$value);}
}
class Customization{public static function isFeatureActive(){return (bool)(Db::getInstance()->state['flags']['PS_CUSTOMIZATION_FEATURE_ACTIVE']??0);}}
class Cache{public static $cleaned=[];public static function clean($key){self::$cleaned[]=$key;}}
class Hook{const MODULE_LIST_BY_HOOK_KEY='hook_module_exec_list_';}
class Db{
    public static $instance;public $state=['products'=>[],'watchys_model_support'=>[],'customization_field'=>[],'customization_field_lang'=>[],'flags'=>[],'registered_hooks'=>[],'version'=>'0.1.1','out_of_stock'=>[]],$backup,$last=10,$locked=[];
    public static function getInstance(){return self::$instance??=new self();}
    public function disableCache(){}
    public function execute($sql){
        if(str_starts_with($sql,'CREATE TABLE'))return true;
        if($sql==='START TRANSACTION'){$this->backup=$this->state;return true;}
        if($sql==='COMMIT'){$this->backup=null;return true;}
        if($sql==='ROLLBACK'){$this->state=$this->backup;$this->backup=null;return true;}
        throw new RuntimeException('SQL inattendu.');
    }
    public function queryRows($sql,$cache){
        // Native PrestaShop 8.2.7 getRow() adds LIMIT 1, even after FOR UPDATE.
        if(preg_match('/FOR UPDATE\s+LIMIT 1/i',$sql)){throw new RuntimeException('1064 : LIMIT après FOR UPDATE');}
        if(str_contains($sql,'FOR UPDATE')){if($cache!==false){throw new RuntimeException('Lecture verrouillée mise en cache.');}$this->locked[]=$sql;}
        if(str_starts_with($sql,'SHOW COLUMNS')){
            foreach(['watchys_model_support'=>['model_id','id_product','reference','fields_json'],'watchys_configuration'=>['id_customization','id_cart','id_product','snapshot','date_add'],'watchys_cart_request'=>['id_cart','request_key','request_digest','id_customization','date_add']] as $table=>$columns){if(str_contains($sql,$table)){return array_map(fn($c)=>['Field'=>$c],$columns);}}
        }
        if(str_contains($sql,'SELECT h.id_hook')){
            if($cache!==false){throw new RuntimeException('Relecture des accroches en cache.');}
            preg_match("/LOWER\('([^']+)'\)/",$sql,$match);$hook=$match[1];return isset($this->state['registered_hooks'][$hook])?[$this->state['registered_hooks'][$hook]]:[];
        }
        if(str_contains($sql,'SELECT * FROM `test_watchys_model_support`')){return $this->state['watchys_model_support'];}
        if(str_contains($sql,'SELECT id_product FROM `test_product`')){return $this->state['products']?[['id_product'=>9001]]:[];}
        if(str_contains($sql,'SELECT id_customization_field')){return array_map(fn($r)=>['id_customization_field'=>$r['id_customization_field']],$this->state['customization_field']);}
        if(str_contains($sql,'SELECT p.reference')){return [$this->state['products'][9001]];}
        if(str_contains($sql,'SELECT version')){return [['version'=>$this->state['version']]];}
        throw new RuntimeException('SELECT inattendu : '.$sql);
    }
    public function executeS($sql,$array=true,$cache=true){return $this->queryRows($sql,$cache);}
    public function getRow($sql,$cache=true){return $this->queryRows(rtrim($sql," \t\n\r\0\x0B;").' LIMIT 1',$cache)[0]??false;}
    public function getValue($sql,$cache=true){$r=$this->getRow($sql,$cache);return $r?reset($r):false;}
    public function insert($table,$row){if($table==='customization_field'){$row['id_customization_field']=++$this->last;}$this->state[$table][]=$row;return true;}
    public function Insert_ID(){return $this->last;}
    public function update($table,$row,$where){if($table!=='module'){throw new RuntimeException('Table modifiée inattendue.');}$this->state['version']=$row['version'];return true;}
}
require dirname(__DIR__).'/classes/WatchysCartSetup.php';
class TestSetupModule{
    public $id=145,$hooks=[],$fail=false,$omit=null;
    public function isDev(){return true;}
    public function catalog(){return new class{public function models(){return [['id'=>'watchys-7750-41mm']];}};}
    public function registerHook($hook,$shops){if($this->fail){return false;}$this->hooks[]=$hook;if($this->omit!==$hook){Db::getInstance()->state['registered_hooks'][$hook]=['id_hook'=>100+count($this->hooks),'name'=>$hook,'active'=>1,'id_shop'=>1,'id_module'=>145];}return true;}
    public function isRegisteredInHook($hook){return false; /* Simulate the stale native with-aliases cache. */}
}
function check($ok,$why){if(!$ok){throw new RuntimeException($why);}}
$db=Db::getInstance();$detected=false;
try{$db->getRow('SELECT * FROM `test_watchys_model_support` FOR UPDATE',false);}catch(RuntimeException $e){$detected=str_contains($e->getMessage(),'1064');}
check($detected,'Double natif ne reproduisant pas le LIMIT implicite.');
$module=new TestSetupModule();$r=WatchysCartSetup::apply($module);check($r['productId']===9001,'Support non créé.');check(count($db->state['products'])===1,'Plusieurs articles créés.');check(count($db->state['customization_field'])===6,'Champs absents.');check(count($db->locked)===1,'Support non verrouillé.');check($db->state['version']==='0.2.2','Version native non mise à jour.');
WatchysCartSetup::apply($module);check(count($db->state['products'])===1,'Reprise créant un doublon.');check(count($db->state['customization_field'])===6,'Reprise doublant les champs.');
check(count($r['hookState'])===5,'Accroches SQL non relues.');check(in_array('hook_idsbyname*',Cache::$cleaned,true),'Cache des alias non invalidé.');
$module->omit='displayCustomization';unset($db->state['registered_hooks']['displayCustomization']);$beforeMissing=$db->state;$missingRejected=false;try{WatchysCartSetup::apply($module);}catch(RuntimeException $e){$missingRejected=str_contains($e->getMessage(),'Accroche absente');}check($missingRejected&&$db->state===$beforeMissing,'Accroche réellement absente acceptée.');$module->omit=null;
$before=$db->state;$module->fail=true;$failed=false;try{WatchysCartSetup::apply($module);}catch(RuntimeException $e){$failed=true;}
check($failed&&$db->state===$before,'Erreur native non annulée.');
echo "SUCCÈS — préparation native avec LIMIT implicite reproduit : lecture verrouillée non cachée, création et reprise sans doublon, relecture SQL avec cache natif périmé, refus d’accroche réellement absente, retour arrière après échec.\n";
