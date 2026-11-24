<?php
/** Signatures match the pinned 8.2.7 methods; no PrestaShop/bootstrap/MySQL. */
define('_PS_MODULE_DIR_',dirname(__DIR__,2).'/');define('_PS_MAIL_DIR_','/mail/');
class Context{} class Shop{}
class HookCore{public static function exec($hook_name,$hook_args=[],$id_module=null,$array_return=false,$check_exceptions=true,$use_push=false,$id_shop=null,$chain=false){return func_get_args();}}
class MailCore{public static function send($idLang,$template,$subject,$templateVars,$to,$toName=null,$from=null,$fromName=null,$fileAttachment=null,$mode_smtp=null,$templatePath=_PS_MAIL_DIR_,$die=false,$idShop=null,$bcc=null,$replyTo=null,$replyToName=null){return func_get_args();}}
class PaymentModuleCore{public function validateOrder($id_cart,$id_order_state,$amount_paid,$payment_method='Unknown',$message=null,$extra_vars=[],$currency_special=null,$dont_touch_amount=false,$secure_key=false,Shop $shop=null,?string $order_reference=null){return func_get_args();}}
class OrderDetailCore{public $stockCalls=0;protected function updateProductQuantityInStock($product,$orderStateId):void{++$this->stockCalls;}public function probe(){return $this->updateProductQuantityInStock(['id_product'=>1],10);}}
class OrderHistoryCore{public function changeIdOrderState($new_order_state,$id_order,$use_existing_payment=false){return func_get_args();}public function add($autodate=true,$null_values=false){return [$autodate,$null_values];}public function addWithemail($autodate=true,$template_vars=false,Context $context=null){return [$autodate,$template_vars,$context];}}
class StockAvailableCore{public $id=0;public function __construct($id=null,$id_lang=null,$id_shop=null,$translator=null){}public function update($null_values=false){return $null_values;}public static function updateQuantity($id_product,$id_product_attribute,$delta_quantity,$id_shop=null,$add_movement=false,$params=[]){return func_get_args();}public static function setQuantity($id_product,$id_product_attribute,$quantity,$id_shop=null,$add_movement=true){return func_get_args();}}
function same($a,$b){if($a!==$b){throw new RuntimeException('Surcharge passive a modifié les arguments ou le retour natifs.');}}
require_once __DIR__.'/../classes/WatchysNativeRuntime.php';
foreach(['Hook.php','Mail.php','PaymentModule.php','order/OrderDetail.php','order/OrderHistory.php','stock/StockAvailable.php'] as $file){require __DIR__.'/../override/classes/'.$file;}
same(Hook::exec('displayTest',['x'=>1],3,true,false,true,1,false),HookCore::exec('displayTest',['x'=>1],3,true,false,true,1,false));
$mailArgs=[1,'x','y',[],'test@example.invalid',null,null,null,null,null,_PS_MAIL_DIR_,false,null,null,null,null];same(Mail::send(...$mailArgs),MailCore::send(...$mailArgs));
$args=[12,10,123.5,'test',null,[],2,false,'secret',new Shop(),'abc'];same((new PaymentModule())->validateOrder(...$args),(new PaymentModuleCore())->validateOrder(...$args));
$detail=new OrderDetail();$detail->probe();same($detail->stockCalls,1);
$history=new OrderHistory();same($history->changeIdOrderState(2,12,true),[2,12,true]);same($history->add(false,true),[false,true]);same($history->addWithemail(false,[]),[false,[],null]);
same(StockAvailable::updateQuantity(1,0,-1,1,true,['a'=>2]),[1,0,-1,1,true,['a'=>2]]);same(StockAvailable::setQuantity(1,0,10,1,false),[1,0,10,1,false]);same((new StockAvailable())->update(true),true);
same(WatchysHookPolicy::classify('actionObjectOrderAddBefore'),'validation');same(WatchysHookPolicy::classify('actionOrderStatusPostUpdate'),'deferred');same(WatchysHookPolicy::classify('actionGetProductPropertiesAfter'),'synchronous');
echo "SUCCÈS — Six surcharges chargées : signatures compatibles avec les doubles 8.2.7 et transmission passive inchangée. Aucun bootstrap ni MySQL.\n";
