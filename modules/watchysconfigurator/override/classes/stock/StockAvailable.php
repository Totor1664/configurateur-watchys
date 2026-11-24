<?php
class StockAvailable extends StockAvailableCore
{
 private $watchysGuard=null;
 public function __construct($id=null,$id_lang=null,$id_shop=null,$translator=null)
 {
  parent::__construct($id,$id_lang,$id_shop,$translator);if(!class_exists('WatchysNativeRuntime',false)){require_once _PS_MODULE_DIR_.'watchysconfigurator/classes/WatchysNativeRuntime.php';}
  if($this->id&&WatchysNativeRuntime::watched((int)$this->id_product,(int)$this->id_product_attribute)){$this->watchysGuard=new WatchysStockObjectGuard(Db::getInstance()->getLink(),_DB_PREFIX_,$this);}
 }
 public function update($null_values=false)
 {
  if(!$this->watchysGuard){return parent::update($null_values);}
  return WatchysNativeRuntime::runStock((int)$this->id_product,(int)$this->id_product_attribute,function()use($null_values){return $this->watchysGuard->update(function()use($null_values){return parent::update($null_values);});});
 }
 public static function updateQuantity($id_product,$id_product_attribute,$delta_quantity,$id_shop=null,$add_movement=false,$params=[])
 {
  if(!class_exists('WatchysNativeRuntime',false)){require_once _PS_MODULE_DIR_.'watchysconfigurator/classes/WatchysNativeRuntime.php';}return WatchysNativeRuntime::runStock((int)$id_product,(int)$id_product_attribute,static function()use($id_product,$id_product_attribute,$delta_quantity,$id_shop,$add_movement,$params){return parent::updateQuantity($id_product,$id_product_attribute,$delta_quantity,$id_shop,$add_movement,$params);});
 }
 public static function setQuantity($id_product,$id_product_attribute,$quantity,$id_shop=null,$add_movement=true)
 {
  if(!class_exists('WatchysNativeRuntime',false)){require_once _PS_MODULE_DIR_.'watchysconfigurator/classes/WatchysNativeRuntime.php';}return WatchysNativeRuntime::runStock((int)$id_product,(int)$id_product_attribute,static function()use($id_product,$id_product_attribute,$quantity,$id_shop,$add_movement){return parent::setQuantity($id_product,$id_product_attribute,$quantity,$id_shop,$add_movement);});
 }
}
