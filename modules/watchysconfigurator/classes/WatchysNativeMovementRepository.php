<?php
/** Typed decoration for the pinned 8.2.7 repository. All read APIs delegate unchanged.
 * It catches direct `new Core\Stock\StockManager()->saveMovement()` calls as well
 * as service-locator calls, before Doctrine can flush on another connection.
 */
class WatchysNativeMovementRepository extends \PrestaShopBundle\Entity\Repository\StockMovementRepository
{
 private $inner;
 public function __construct(\PrestaShopBundle\Entity\Repository\StockMovementRepository $inner){$this->inner=$inner;}
 public function getEmployees(){return $this->inner->getEmployees();}
 public function getTypes($grouped=false){return $this->inner->getTypes($grouped);}
 public function getData(\PrestaShopBundle\Api\QueryParamsCollection $queryParams){return $this->inner->getData($queryParams);}
 public function countPages(\PrestaShopBundle\Api\QueryParamsCollection $queryParams){return $this->inner->countPages($queryParams);}
 public function saveStockMvt(\PrestaShopBundle\Entity\StockMvt $stockMvt)
 {
  require_once __DIR__.'/WatchysNativeRuntime.php';if(!WatchysNativeRuntime::isActive()){return $this->inner->saveStockMvt($stockMvt);}
  return WatchysNativeRuntime::queueNativeMovement($stockMvt)?1:0;
 }
}
