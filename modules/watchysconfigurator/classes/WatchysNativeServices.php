<?php
/** Loaded inside a native boundary only. Delegates all services except stock managers. */
class WatchysNativeContainer extends \PrestaShop\PrestaShop\Core\Foundation\IoC\Container
{
 private $original;private $stock;private $core;
 public function __construct($original){$this->original=$original;$this->stock=new WatchysNativeStockAdapter();$this->core=new WatchysDeferredStockManager();}
 public function knows($name){return in_array(ltrim($name,'\\'),['PrestaShop\\PrestaShop\\Adapter\\StockManager','PrestaShop\\PrestaShop\\Core\\Stock\\StockManager'],true)||$this->original->knows($name);}
 public function make($name){$key=ltrim($name,'\\');if($key==='PrestaShop\\PrestaShop\\Adapter\\StockManager'){return $this->stock;}if($key==='PrestaShop\\PrestaShop\\Core\\Stock\\StockManager'){return $this->core;}return $this->original->make($name);}
 public function bind($name,$constructor,$shared=false){if(in_array(ltrim($name,'\\'),['PrestaShop\\PrestaShop\\Adapter\\StockManager','PrestaShop\\PrestaShop\\Core\\Stock\\StockManager'],true)){throw new RuntimeException('Service stock déjà coordonné.');}$this->original->bind($name,$constructor,$shared);return $this;}
 public function aliasNamespace($alias,$prefix){$this->original->aliasNamespace($alias,$prefix);return $this;}
 public function resolveClassName($name){return $this->original->resolveClassName($name);}
}
class WatchysNativeStockAdapter extends \PrestaShop\PrestaShop\Adapter\StockManager
{
 public function updatePhysicalProductQuantity($shopId,$errorState,$cancellationState,$idProduct=null,$idOrder=null)
 {
  if(!WatchysNativeRuntime::isActive()){return parent::updatePhysicalProductQuantity($shopId,$errorState,$cancellationState,$idProduct,$idOrder);}
  if((int)$shopId!==1||(int)$errorState!==8||(int)$cancellationState!==6){throw new RuntimeException('Contrat natif de réservation modifié.');}
  return WatchysNativeRuntime::reconcileRequested($idProduct,$idOrder);
 }
}
class WatchysDeferredStockManager extends \PrestaShop\PrestaShop\Core\Stock\StockManager
{
 public function saveMovement($productId,$productAttributeId,$deltaQuantity,$params=[])
 {
  if(!WatchysNativeRuntime::isActive()){return parent::saveMovement($productId,$productAttributeId,$deltaQuantity,$params);}
  if(WatchysNativeRuntime::isSupport((int)$productId)||!(int)$deltaQuantity){return false;}
  WatchysNativeRuntime::effect('movement',['productId'=>(int)$productId,'combinationId'=>(int)$productAttributeId,'delta'=>(int)$deltaQuantity,'params'=>$params]);return true;
 }
}
