<?php
class OrderDetail extends OrderDetailCore
{
 protected function updateProductQuantityInStock($product,$orderStateId): void
 {
  if(!class_exists('WatchysNativeRuntime',false)){require_once _PS_MODULE_DIR_.'watchysconfigurator/classes/WatchysNativeRuntime.php';}
  if(WatchysNativeRuntime::isActive()&&WatchysNativeRuntime::isSupport((int)$product['id_product'])){return;}parent::updateProductQuantityInStock($product,$orderStateId);
 }
}
