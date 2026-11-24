<?php
require_once __DIR__.'/WatchysNativeSchema.php';require_once __DIR__.'/WatchysHookPolicy.php';require_once __DIR__.'/WatchysNativeBoundary.php';require_once __DIR__.'/WatchysStockWriteGate.php';require_once __DIR__.'/WatchysStockReconciler.php';require_once __DIR__.'/WatchysOrderStockService.php';require_once __DIR__.'/WatchysStockObjectGuard.php';
/** DEV only. Browser checkout remains blocked. Private trial is a CLI-only capability. */
class WatchysNativeRuntime
{
 private static $root=null;private static $trial=null;private static $module=null;private static $proxyOriginal=null;private static $shutdown=false;private static $reactivation=[];
 public static function isActive(){return self::$root!==null||self::$trial!==null;}
 public static function trialActive(){return self::$trial!==null;}
 public static function scopedCatalog(){return self::$trial['catalog']??null;}
 public static function gate(){if(!self::isActive()){return null;}return self::$root['gate']??self::$trial['gate'];}
 public static function pdo(){return Db::getInstance()->getLink();}
 private static function rows($sql,$args=[]){$s=self::pdo()->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);}
 private static function module()
 {
  if(!self::$module){self::$module=Module::getInstanceByName('watchysconfigurator');}
  if(!self::$module||!self::$module->isDev()||_PS_VERSION_!=='8.2.7'||!self::pdo() instanceof PDO||self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'){throw new RuntimeException('Contexte natif DEV non conforme.');}return self::$module;
 }
 private static function ready()
 {
  if(self::$trial){return true;}if(!defined('_PS_VERSION_')||_PS_VERSION_!=='8.2.7'){return false;}
  try{$m=self::module();return (bool)Configuration::get('WATCHYS_NATIVE_STOCK_READY');}catch(Throwable $e){return false;}
 }
 public static function isSupport($pid){if(!self::isActive()&&!self::ready()){return false;}return (bool)self::rows('SELECT id_product FROM `'._DB_PREFIX_.'watchys_model_support` WHERE id_product=?',[$pid]);}
 public static function watched($pid,$attr=0)
 {
  if(!self::isActive()&&!self::ready()){return false;}return (bool)self::rows('SELECT id_product FROM `'._DB_PREFIX_.'watchys_stock_component` WHERE id_product=? AND id_product_attribute=? AND id_shop=1 AND enabled=1',[(int)$pid,(int)$attr]);
 }
 public static function allowsCart($id){return self::$root!==null&&self::$root['kind']==='checkout'&&self::$root['subject']===$id;}
 public static function effect($kind,$payload)
 {
  $out=self::$root?self::$root['boundary']->outbox():(self::$trial['outbox']??null);if(!$out){throw new RuntimeException('Effet externe avant transaction.');}return $out->enqueue($kind,$payload);
 }
 public static function queueNativeMovement($movement)
 {
  $r=self::rows('SELECT id_product,id_product_attribute FROM `'._DB_PREFIX_.'stock_available` WHERE id_stock_available=?',[(int)$movement->getIdStock()]);if(count($r)!==1){throw new RuntimeException('Mouvement natif sans stock.');}
  if(self::isSupport((int)$r[0]['id_product'])){return false;}
  self::effect('movement',['productId'=>(int)$r[0]['id_product'],'combinationId'=>(int)$r[0]['id_product_attribute'],'delta'=>(int)$movement->getSign()*(int)$movement->getPhysicalQuantity(),'params'=>['id_order'=>(int)$movement->getIdOrder(),'id_stock_mvt_reason'=>(int)$movement->getIdStockMvtReason()],'native'=>['id_stock'=>(int)$movement->getIdStock(),'id_supply_order'=>(int)$movement->getIdSupplyOrder(),'id_employee'=>(int)$movement->getIdEmployee(),'employee_firstname'=>(string)$movement->getEmployeeFirstname(),'employee_lastname'=>(string)$movement->getEmployeeLastname(),'date_add'=>$movement->getDateAdd()->format('Y-m-d H:i:s')]]);return true;
 }
 public static function captureHook($name,$args,$moduleId,$arrayReturn,$check,$push,$shop,$chain)
 {
  if(!self::isActive()||!is_array($args)){return [false,null];}
  $modules=HookCore::getHookModuleExecList($name);if(!$modules){return [false,null];}$policy=WatchysHookPolicy::classify($name);
  if($policy==='validation'){foreach($modules as $m){if(($m['module']??'')!=='watchysconfigurator'){throw new RuntimeException('Accroche de validation à examiner : '.$name);}}return [false,null];}
  if($policy==='synchronous'){return [false,null];}
  if($chain){throw new RuntimeException('Accroche chaînée non différable.');}
  if($name==='actionUpdateQuantity'&&self::$root){
   $pid=(int)($args['id_product']??0);$attr=(int)($args['id_product_attribute']??0);$key=$pid.':'.$attr;
   self::$root['quantities'][$key]=self::$root['quantities'][$key]??['id_product'=>$pid,'id_product_attribute'=>$attr,'id_shop'=>1,'delta_quantity'=>0];self::$root['quantities'][$key]['delta_quantity']+=(int)($args['delta_quantity']??0);
  }else{self::effect('hook',['name'=>$name,'args'=>$args,'moduleId'=>$moduleId,'arrayReturn'=>$arrayReturn,'checkExceptions'=>$check,'usePush'=>$push,'shop'=>$shop,'chain'=>$chain]);}
  return [true,$arrayReturn?[]:''];
 }
 public static function startTrial($catalog)
 {
  if(PHP_SAPI!=='cli'||!defined('WATCHYS_PRIVATE_NATIVE_TRIAL')||WATCHYS_PRIVATE_NATIVE_TRIAL!==true||self::$trial||self::$root){throw new RuntimeException('Capacité CLI privée requise.');}
  self::module();$db=self::pdo();if(!$db->inTransaction()){throw new RuntimeException('Transaction extérieure du test requise.');}
  self::$trial=['catalog'=>$catalog,'gate'=>new WatchysStockWriteGate($db,_DB_PREFIX_),'outbox'=>new WatchysNativeOutbox($db,_DB_PREFIX_,hash('sha256',random_bytes(32)))];self::disableCaches();self::installProxy();
 }
 public static function failNextCheckoutForTrial(){if(PHP_SAPI!=='cli'||!self::$trial||self::$root){throw new RuntimeException('Injection réservée à l’essai CLI privé.');}self::$trial['failAfterCheckout']=true;}
 public static function setTrialCatalog($catalog){if(!self::$trial||self::$root){throw new RuntimeException('Catalogue privé hors phase de préparation.');}self::$trial['catalog']=$catalog;}
 public static function stopTrial(){if(self::$root){self::abort();}self::$trial=null;self::restoreProxy();self::$reactivation=[];}
 private static function disableCaches(){Db::getInstance()->disableCache();ObjectModel::disableCache();}
 private static function installProxy()
 {
  if(self::$proxyOriginal!==null){return;}$original=\PrestaShop\PrestaShop\Adapter\ServiceLocator::getContainer();if(!$original){throw new RuntimeException('Conteneur natif absent.');}
  $sf=\PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
  if(!$sf||!$sf->has('prestashop.core.api.stock_movement.repository')||!$sf->get('prestashop.core.api.stock_movement.repository') instanceof WatchysNativeMovementRepository){throw new RuntimeException('Décoration des mouvements native absente : cache Symfony à recompiler.');}
  require_once __DIR__.'/WatchysNativeServices.php';self::$proxyOriginal=$original;\PrestaShop\PrestaShop\Adapter\ServiceLocator::setServiceContainerInstance(new WatchysNativeContainer($original));
 }
 private static function restoreProxy(){if(self::$proxyOriginal!==null){\PrestaShop\PrestaShop\Adapter\ServiceLocator::setServiceContainerInstance(self::$proxyOriginal);self::$proxyOriginal=null;}}
 private static function begin($kind,$subject,$key,$digest,array $requirements,callable $lockSubject,$owner=null)
 {
  if(self::$root){throw new RuntimeException('Frontière native déjà ouverte.');}self::module();self::disableCaches();$boundary=new WatchysNativeBoundary(self::pdo(),_DB_PREFIX_);
  $replay=$boundary->begin($kind,$subject,$key,$digest,$requirements,$lockSubject,self::$trial!==null);
  self::$root=['kind'=>$kind,'subject'=>$subject,'owner'=>$owner,'boundary'=>$boundary,'gate'=>new WatchysStockWriteGate(self::pdo(),_DB_PREFIX_),'orders'=>[],'quantities'=>[],'mailDepth'=>0,'savedHistory'=>null];
  self::installProxy();if(!self::$shutdown){register_shutdown_function(static function(){if(self::$root){try{self::abort();}catch(Throwable $ignored){}}});self::$shutdown=true;}return $replay;
 }
 private static function digest($value){return hash('sha256',WatchysEffectCodec::encode($value));}
 private static function cartRequirements($id)
 {
  $requirements=[];$m=self::module();$supports=array_map('intval',array_column(self::rows('SELECT id_product FROM `'._DB_PREFIX_.'watchys_model_support`'),'id_product'));
  $rows=self::rows('SELECT id_product,id_product_attribute,id_customization,quantity FROM `'._DB_PREFIX_.'cart_product` WHERE id_cart=? ORDER BY id_product,id_customization',[$id]);
  foreach($rows as $line){$pid=(int)$line['id_product'];$attr=(int)$line['id_product_attribute'];$qty=(int)$line['quantity'];if($qty<1||$qty>100||$attr!==0||Pack::isPack($pid)){throw new RuntimeException('Lot natif limité aux articles simples de la boutique contrôlée.');}
   if(in_array($pid,$supports,true)){
    $custom=(int)$line['id_customization'];$c=self::rows('SELECT * FROM `'._DB_PREFIX_.'watchys_configuration` WHERE id_customization=?',[$custom]);if(count($c)!==1){throw new RuntimeException('Configuration native absente.');}
    $requests=WatchysOrderNomenclature::build(1,$id,[['product_id'=>$pid,'id_order_detail'=>$custom,'id_customization'=>$custom,'product_quantity'=>$qty,'id_order'=>1,'id_shop'=>1,'product_attribute_id'=>0]],[$custom=>$c[0]],array_map('intval',array_column(self::rows('SELECT model_id,id_product FROM `'._DB_PREFIX_.'watchys_model_support`'),'id_product','model_id')),$m->catalog());
    foreach($requests as $r){$component=$r['identity']['component_product_id'];if(in_array($component,$supports,true)){throw new RuntimeException('Support technique utilisé comme composant.');}$key=$component.':'.$r['identity']['component_attribute_id'];$requirements[$key]=$requirements[$key]??['productId'=>$component,'combinationId'=>$r['identity']['component_attribute_id'],'required'=>0];$requirements[$key]['required']+=$r['required_units'];}
   }else{$key=$pid.':'.$attr;$requirements[$key]=$requirements[$key]??['productId'=>$pid,'combinationId'=>$attr,'required'=>0];$requirements[$key]['required']+=$qty;}
  }return array_values($requirements);
 }
 private static function handlesCart($id)
 {
  if(!self::ready()){return false;}$r=self::rows('SELECT cp.id_product,cp.id_product_attribute FROM `'._DB_PREFIX_.'cart_product` cp WHERE cp.id_cart=?',[$id]);foreach($r as $x){if(self::isSupport((int)$x['id_product'])||self::watched((int)$x['id_product'],(int)$x['id_product_attribute'])){return true;}if(Pack::isPack((int)$x['id_product'])&&self::rows('SELECT p.id_product_item FROM `'._DB_PREFIX_.'pack` p JOIN `'._DB_PREFIX_.'watchys_stock_component` c ON c.id_product=p.id_product_item AND c.id_product_attribute=p.id_product_attribute_item AND c.id_shop=1 AND c.enabled=1 WHERE p.id_product_pack=?',[(int)$x['id_product']])){return true;}}return false;
 }
 public static function validateOrder($payment,array $args,callable $native)
 {
  $cart=(int)$args[0];if(self::$root){if(self::allowsCart($cart)){return $native();}throw new RuntimeException('Commande imbriquée étrangère.');}
  if(!self::handlesCart($cart)){return $native();}
  $requirements=self::cartRequirements($cart);$fingerprint=$args;$fingerprint[0]=$cart;$fingerprint[1]=(int)$args[1];$fingerprint[2]=number_format((float)$args[2],6,'.','');$fingerprint[9]=$args[9] instanceof Shop?(int)$args[9]->id:null;$digest=self::digest([$payment->name,$fingerprint]);
  try{
   $replay=self::begin('checkout',$cart,'checkout:'.$cart,$digest,$requirements,static function()use($cart){$r=self::rows('SELECT id_cart,id_shop FROM `'._DB_PREFIX_.'cart` WHERE id_cart=? FOR UPDATE',[$cart]);if(count($r)!==1||(int)$r[0]['id_shop']!==1){throw new RuntimeException('Panier DEV absent.');}});
   if($replay){$ids=self::$root['boundary']->replayOrders();if(!$ids){throw new RuntimeException('Reprise sans commande.');}$last=end($ids);$r=self::rows('SELECT id_order,reference,module FROM `'._DB_PREFIX_.'orders` WHERE id_order=? AND id_cart=?',[$last,$cart]);if(count($r)!==1||$r[0]['module']!==$payment->name){throw new RuntimeException('Reprise de paiement étrangère.');}$payment->currentOrder=(int)$last;$payment->currentOrderReference=$r[0]['reference'];self::finish($ids);return true;}
   if(self::rows('SELECT id_order FROM `'._DB_PREFIX_.'orders` WHERE id_cart=?',[$cart])){throw new RuntimeException('Panier déjà commandé hors du journal natif.');}
   // Revalidate after locking the cart: a stale quote must not start an order.
   self::module()->cartService()->assertCart($cart);$current=self::cartRequirements($cart);if(self::digest($current)!==self::digest($requirements)){throw new RuntimeException('Panier modifié pendant la préparation.');}
   $result=$native();if(self::$trial['failAfterCheckout']??false){self::$trial['failAfterCheckout']=false;throw new RuntimeException('Échec injecté après validation native : transaction à annuler.');}if($result===false){throw new RuntimeException('Validation native refusée.');}$ids=array_map('intval',array_column(self::rows('SELECT id_order FROM `'._DB_PREFIX_.'orders` WHERE id_cart=? ORDER BY id_order',[$cart]),'id_order'));if(!$ids){throw new RuntimeException('Commande native non créée.');}self::finish($ids);return $result;
  }catch(Throwable $e){self::abort();throw $e;}
 }
 private static function orderRequirements($id)
 {
  $pairs=[];foreach(self::rows('SELECT product_id,product_attribute_id FROM `'._DB_PREFIX_.'order_detail` WHERE id_order=?',[$id]) as $r){if(!self::isSupport((int)$r['product_id'])){$pairs[$r['product_id'].':'.$r['product_attribute_id']]=['productId'=>(int)$r['product_id'],'combinationId'=>(int)$r['product_attribute_id'],'required'=>0];}}
  foreach(self::rows('SELECT component_product_id,component_attribute_id FROM `'._DB_PREFIX_.'watchys_order_component` WHERE id_order=?',[$id]) as $r){$pairs[$r['component_product_id'].':'.$r['component_attribute_id']]=['productId'=>(int)$r['component_product_id'],'combinationId'=>(int)$r['component_attribute_id'],'required'=>0];}return array_values($pairs);
 }
 public static function beginHistory($history,$newState,$order)
 {
  $id=is_object($order)?(int)$order->id:(int)$order;if(self::$root||!self::ready()){return false;}
  $req=self::orderRequirements($id);$watched=false;foreach($req as $r){$watched=$watched||self::watched($r['productId'],$r['combinationId']);}if(!$watched&&!self::rows('SELECT id_order FROM `'._DB_PREFIX_.'watchys_order_component` WHERE id_order=?',[$id])){if(self::rows('SELECT od.id_order_detail FROM `'._DB_PREFIX_.'order_detail` od JOIN `'._DB_PREFIX_.'watchys_model_support` w ON w.id_product=od.product_id WHERE od.id_order=?',[$id])){throw new RuntimeException('Commande configurée sans journal de stock : reprise historique requise.');}return false;}WatchysStockPolicy::event(['kind'=>'status','status_id'=>(int)$newState]);
  self::begin('history',$id,'change:'.bin2hex(random_bytes(16)),self::digest([$id,(int)$newState]),$req,static function()use($id){$r=self::rows('SELECT id_order,id_shop FROM `'._DB_PREFIX_.'orders` WHERE id_order=? FOR UPDATE',[$id]);if(count($r)!==1||(int)$r[0]['id_shop']!==1){throw new RuntimeException('Commande DEV absente.');}},spl_object_id($history));return true;
 }
 public static function historyAdded($history)
 {
  if(self::$root&&self::$root['kind']==='history'&&self::$root['owner']===spl_object_id($history)){if(self::$root['mailDepth']>0){self::$root['savedHistory']=(int)$history->id;}else{self::finish([(int)$history->id_order],'history:'.(int)$history->id);}}
 }
 public static function historyMailEnter($history)
 {
  if(self::$root&&self::$root['kind']==='history'&&self::$root['owner']===spl_object_id($history)){++self::$root['mailDepth'];return true;}return false;
 }
 public static function historyMailLeave($history,$success)
 {
  if(!self::$root||self::$root['kind']!=='history'||self::$root['owner']!==spl_object_id($history)){return;}
  if(!$success){self::abort();throw new RuntimeException('Enregistrement natif de statut ou email différé refusé.');}
  if(--self::$root['mailDepth']===0){if(self::$root['savedHistory']!==(int)$history->id){self::abort();throw new RuntimeException('Historique natif non sauvegardé.');}self::finish([(int)$history->id_order],'history:'.(int)$history->id);}
 }
 public static function assertHistoryAdd($history)
 {
  if(self::$root||!self::ready()){return;}foreach(self::orderRequirements((int)$history->id_order) as $r){if(self::watched($r['productId'],$r['combinationId'])){throw new RuntimeException('changeIdOrderState requis avant ajout d’un historique coordonné.');}}
 }
 public static function runStock($pid,$attr,callable $native)
 {
  if(self::$root){return self::isSupport((int)$pid)?true:$native();}if(!self::watched((int)$pid,(int)$attr)){return $native();}
  try{self::begin('stock',(int)$pid,'stock:'.bin2hex(random_bytes(16)),self::digest([(int)$pid,(int)$attr]),[['productId'=>(int)$pid,'combinationId'=>(int)$attr,'required'=>0]],static function(){});$r=$native();if($r===false){throw new RuntimeException('Modification native refusée.');}self::finish([]);return $r;}catch(Throwable $e){self::abort();throw $e;}
 }
 public static function reconcileRequested($productId,$orderId)
 {
  $sql='SELECT DISTINCT sa.id_product,sa.id_product_attribute FROM `'._DB_PREFIX_.'stock_available` sa WHERE sa.id_shop=1 AND sa.id_shop_group=0';$args=[];
  if($productId){$sql.=' AND sa.id_product=?';$args[]=(int)$productId;}if($orderId){$sql.=' AND sa.id_product IN (SELECT product_id FROM `'._DB_PREFIX_.'order_detail` WHERE id_order=?)';$args[]=(int)$orderId;}
  $pairs=[];foreach(self::rows($sql,$args) as $r){if(!self::isSupport((int)$r['id_product'])){$pairs[]=[(int)$r['id_product'],(int)$r['id_product_attribute']];}}
  (new WatchysStockReconciler(self::pdo(),_DB_PREFIX_,self::gate()))->run($pairs);return true;
 }
 private static function finish(array $ids,$finalKey=null)
 {
  $root=self::$root;if(!$root){throw new RuntimeException('Frontière native absente.');}if($root['boundary']->replayOrders()!==null){$root['boundary']->commit($ids);self::$root=null;if(!self::$trial){self::restoreProxy();}return;}
  $pairs=$root['boundary']->pairs();$reconcile=new WatchysStockReconciler(self::pdo(),_DB_PREFIX_,self::gate());$reconcile->run($pairs);
  foreach($ids as $id){$hist=self::rows('SELECT id_order_history FROM `'._DB_PREFIX_.'order_history` WHERE id_order=? ORDER BY id_order_history DESC LIMIT 1',[$id]);if(!$hist){throw new RuntimeException('Historique natif non enregistré.');}
   $res=WatchysOrderStockService::fromNative(self::module())->applyHistory($id,(int)$hist[0]['id_order_history'],self::$reactivation[$id]??false);
   foreach($res['deltas'] as $sku=>$delta){[$pid,$attr]=array_map('intval',explode(':',$sku));if($delta['available']){$key=$pid.':'.$attr;self::$root['quantities'][$key]=self::$root['quantities'][$key]??['id_product'=>$pid,'id_product_attribute'=>$attr,'id_shop'=>1,'delta_quantity'=>0];self::$root['quantities'][$key]['delta_quantity']+=$delta['available'];}if($delta['physical']){self::effect('movement',['productId'=>$pid,'combinationId'=>$attr,'delta'=>$delta['physical'],'params'=>['id_order'=>$id,'id_stock_mvt_reason'=>(int)Configuration::get($delta['physical']<0?'PS_STOCK_CUSTOMER_ORDER_REASON':'PS_STOCK_CUSTOMER_RETURN_REASON')]]);}}
  }
  $reconcile->run($pairs);foreach(self::$root['quantities'] as $q){if(!$q['delta_quantity']){continue;}$r=self::rows('SELECT quantity FROM `'._DB_PREFIX_.'stock_available` WHERE id_product=? AND id_product_attribute=? AND id_shop=1 AND id_shop_group=0',[$q['id_product'],$q['id_product_attribute']]);if(count($r)!==1){throw new RuntimeException('Quantité finale native absente.');}$q['quantity']=(int)$r[0]['quantity'];self::effect('hook',['name'=>'actionUpdateQuantity','args'=>$q,'moduleId'=>null,'arrayReturn'=>false,'checkExceptions'=>true,'usePush'=>false,'shop'=>1,'chain'=>false]);}
  $root['boundary']->commit($ids,$finalKey);self::$root=null;if(!self::$trial){self::restoreProxy();}try{self::invalidate($pairs);}catch(Throwable $ignored){}
 }
 private static function invalidate($pairs){foreach($pairs as $pair){Cache::clean('StockAvailable::getQuantityAvailableByProduct_'.(int)$pair[0].'*');}if(method_exists('Cart','resetStaticCache')){Cart::resetStaticCache();}if(defined('_PS_CACHE_ENABLED_')&&_PS_CACHE_ENABLED_){Cache::getInstance()->deleteQuery('UPDATE `'._DB_PREFIX_.'stock_available` SET quantity=quantity');}}
 public static function abort(){if(!self::$root){return;}$root=self::$root;self::$root=null;try{$root['boundary']->abort();}finally{if(!self::$trial){self::restoreProxy();}try{self::invalidate($root['boundary']->pairs());}catch(Throwable $ignored){}}}
 public static function receive($order,$detail,$total,$employee,$key)
 {
  if(!self::ready()){throw new RuntimeException('Réceptions natives non activées.');}
  require_once __DIR__.'/WatchysReceivedReturns.php';
  try{self::begin('return',(int)$detail,'receipt:'.$key,self::digest([$order,$detail,$total,$employee]),self::orderRequirements((int)$order),static function()use($order){$r=self::rows('SELECT id_order FROM `'._DB_PREFIX_.'orders` WHERE id_order=? AND id_shop=1 FOR UPDATE',[(int)$order]);if(count($r)!==1){throw new RuntimeException('Commande DEV absente.');}});
   if(self::$root['boundary']->replayOrders()!==null){self::finish([(int)$order]);return ['replayed'=>true];}
   $result=(new WatchysReceivedReturns(self::pdo(),_DB_PREFIX_,self::gate()))->receive($order,$detail,$total,$employee,$key);
   foreach($result['results'] as $batch){foreach($batch['deltas'] as $sku=>$delta){[$pid,$attr]=array_map('intval',explode(':',$sku));if($delta['available']){self::$root['quantities'][$sku]=['id_product'=>$pid,'id_product_attribute'=>$attr,'id_shop'=>1,'delta_quantity'=>$delta['available']];}if($delta['physical']){self::effect('movement',['productId'=>$pid,'combinationId'=>$attr,'delta'=>$delta['physical'],'params'=>['id_order'=>$order,'id_stock_mvt_reason'=>(int)Configuration::get('PS_STOCK_CUSTOMER_RETURN_REASON'),'id_employee'=>$employee]]);}}}
   self::finish([(int)$order]);return $result;
  }catch(Throwable $e){self::abort();throw $e;}
 }
 public static function reactivate($id,callable $operation)
 {
  if(!self::$trial){throw new RuntimeException('Réactivation native réservée à l’essai CLI tant que la gestion BO n’est pas validée.');}self::$reactivation[(int)$id]=true;try{return $operation();}finally{unset(self::$reactivation[(int)$id]);}
 }
}
