<?php
/** Test dependencies only. Native Core\Stock\StockManager.php is loaded unmodified.
 * Stock object persistence, services, hooks/cache and product are doubles.
 * No native order, payment, email or module is loaded by this fixture.
 */
namespace PrestaShop\PrestaShop\Adapter {
 class ServiceLocator {
  public static $services=[];
  public static function get($key){if(!isset(self::$services[$key])){throw new \RuntimeException('Service de fixture absent.');}return self::$services[$key];}
 }
}
namespace {
 class Configuration {public static function get($key){return false;}}
 class WatchysFixtureProduct {public $id=7736;public function hasAttributes(){return false;}}
 class WatchysFixtureStock {
  public $id;public $quantity;public $reserved_quantity;public $physical_quantity;private $db;private $prefix;
  public function __construct(\PDO $db,$prefix,array $row){$this->db=$db;$this->prefix=$prefix;$this->id=(int)$row['id_stock_available'];$this->quantity=(int)$row['quantity'];$this->reserved_quantity=(int)$row['reserved_quantity'];$this->physical_quantity=(int)$row['physical_quantity'];}
  public function update(){ $s=$this->db->prepare('UPDATE `'.$this->prefix.'stock_available` SET quantity=?,reserved_quantity=?,physical_quantity=? WHERE id_stock_available=?');$s->execute([$this->quantity,$this->reserved_quantity,$this->physical_quantity,$this->id]);return true;}
 }
 class WatchysFixtureStockAdapter {
  private $db;private $prefix;private $afterRead;
  public function __construct(\PDO $db,$prefix,callable $afterRead){$this->db=$db;$this->prefix=$prefix;$this->afterRead=$afterRead;}
  public function getStockAvailableByProduct($product,$attribute,$shop){$r=$this->db->query('SELECT * FROM `'.$this->prefix.'stock_available` WHERE id_product=7736 AND id_product_attribute=0 AND id_shop=1 AND id_shop_group=0')->fetch(\PDO::FETCH_ASSOC);if(!$r){throw new \RuntimeException('Stock de fixture absent.');}$stock=new WatchysFixtureStock($this->db,$this->prefix,$r);($this->afterRead)();return $stock;}
 }
 class WatchysFixturePack {public function isPack($p){return false;}public function isPacked($p,$a){return false;}}
 class WatchysFixtureCache {public function clean($key){}}
 class WatchysFixtureHooks {public function exec($name,$args){}}
 function watchysFixtureNative(\PDO $db,$prefix,$nativePath,callable $afterRead){
  if(hash_file('sha256',$nativePath)!=='c361a5b256b9c0840884832ef398ef499d326ac92024d42f5c671945608fbd20'){throw new \RuntimeException('Cœur natif différent du bilan.');}
  require_once $nativePath;
  \PrestaShop\PrestaShop\Adapter\ServiceLocator::$services=[
   '\\PrestaShop\\PrestaShop\\Adapter\\StockManager'=>new WatchysFixtureStockAdapter($db,$prefix,$afterRead),
   '\\PrestaShop\\PrestaShop\\Adapter\\Product\\PackItemsManager'=>new WatchysFixturePack(),
   '\\PrestaShop\\PrestaShop\\Adapter\\CacheManager'=>new WatchysFixtureCache(),
   '\\PrestaShop\\PrestaShop\\Adapter\\HookManager'=>new WatchysFixtureHooks(),
  ];
  (new \PrestaShop\PrestaShop\Core\Stock\StockManager())->updateQuantity(new WatchysFixtureProduct(),0,-1,1,false);
  // A complete native stock boundary includes creation of the related order/detail SQL.
  $db->exec('INSERT INTO `'.$prefix.'orders` VALUES(200,1,10,0)');
  $db->exec('INSERT INTO `'.$prefix.'order_detail` VALUES(200,200,7736,0,1,0)');
 }
}
