<?php
class PaymentModule extends PaymentModuleCore
{
 public function validateOrder($id_cart,$id_order_state,$amount_paid,$payment_method='Unknown',$message=null,$extra_vars=[],$currency_special=null,$dont_touch_amount=false,$secure_key=false,Shop $shop=null,?string $order_reference=null)
 {
  if(!class_exists('WatchysNativeRuntime',false)){require_once _PS_MODULE_DIR_.'watchysconfigurator/classes/WatchysNativeRuntime.php';}
  $args=[$id_cart,$id_order_state,$amount_paid,$payment_method,$message,$extra_vars,$currency_special,$dont_touch_amount,$secure_key,$shop,$order_reference];return WatchysNativeRuntime::validateOrder($this,$args,function()use($args){return parent::validateOrder(...$args);});
 }
}
