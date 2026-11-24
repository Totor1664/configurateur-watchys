<?php
/** Loaded only AFTER the real DEV bootstrap. Native ObjectModel/Db are not doubled. */
class WatchysRuntimeProbeStock extends StockAvailable
{
    // Redeclare to avoid changing the parent's static definition.
    public static $definition=[];
}
function watchysNativeRuntimeProbe($module,PDO $pdo,$sourcePrefix,callable $progress)
{
    if(Db::getInstance()->getLink()!==$pdo||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||_DB_PREFIX_!==$sourcePrefix||$pdo->inTransaction()){throw new RuntimeException('PDO maître natif non confirmé ou transaction déjà ouverte.');}
    $suffix='watchys_runtime_'.bin2hex(random_bytes(6)).'_';$prefix=$sourcePrefix.$suffix;
    if(!preg_match('/^watchys_runtime_[a-f0-9]{12}_$/D',$suffix)||strlen($prefix.'watchys_order_component')>64){throw new RuntimeException('Périmètre temporaire natif invalide.');}
    $created=[];$original=StockAvailable::$definition;
    $ddl=[
      'stock_available'=>'CREATE TEMPORARY TABLE `'.$prefix.'stock_available` LIKE `'.$sourcePrefix.'stock_available`',
      'orders'=>'CREATE TEMPORARY TABLE `'.$prefix.'orders` (id_order INT PRIMARY KEY,id_shop INT NOT NULL,current_state INT NOT NULL,valid INT NOT NULL) ENGINE=InnoDB',
      'order_detail'=>'CREATE TEMPORARY TABLE `'.$prefix.'order_detail` (id_order_detail INT PRIMARY KEY,id_order INT NOT NULL,product_id INT NOT NULL,product_attribute_id INT NOT NULL,product_quantity INT NOT NULL,product_quantity_refunded INT NOT NULL) ENGINE=InnoDB',
      'order_state'=>'CREATE TEMPORARY TABLE `'.$prefix.'order_state` (id_order_state INT PRIMARY KEY,shipped INT NOT NULL) ENGINE=InnoDB',
    ];
    foreach(WatchysStockSchema::definitions($prefix) as $name=>$sql){$ddl[$name]=preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$sql,1);}
    $check=static function($v,$m){if(!$v){throw new RuntimeException($m);}};
    try{
        foreach($ddl as $name=>$sql){$pdo->exec($sql);$created[]=$name;}
        $pdo->exec('INSERT INTO `'.$prefix.'stock_available` SELECT * FROM `'.$sourcePrefix.'stock_available` WHERE id_product=7736 AND id_product_attribute=0 AND id_shop=1 AND id_shop_group=0');
        $check((int)$pdo->query('SELECT COUNT(*) FROM `'.$prefix.'stock_available`')->fetchColumn()===1,'Copie stock natif non unique.');
        $pdo->exec('UPDATE `'.$prefix.'stock_available` SET quantity=10,reserved_quantity=0,physical_quantity=10,depends_on_stock=0');$pdo->exec('INSERT INTO `'.$prefix.'order_state` VALUES(10,0),(4,1),(6,0),(8,0)');
        $stockId=(int)$pdo->query('SELECT id_stock_available FROM `'.$prefix.'stock_available`')->fetchColumn();
        WatchysRuntimeProbeStock::$definition=$original;WatchysRuntimeProbeStock::$definition['table']=$suffix.'stock_available';
        Cache::clean('objectmodel_def_WatchysRuntimeProbeStock');Cache::clean('objectmodel_WatchysRuntimeProbeStock_*');
        // These switches affect this private CLI process only; no shared cache flush.
        ObjectModel::disableCache();Db::getInstance()->disableCache();
        $state=static function()use($pdo,$prefix){return array_map('intval',$pdo->query('SELECT quantity,reserved_quantity,physical_quantity FROM `'.$prefix.'stock_available`')->fetch(PDO::FETCH_NUM));};
        $pdo->beginTransaction();
        $nativeFactory=WatchysOrderStockService::fromNative($module);$check($nativeFactory instanceof WatchysOrderStockService,'Service sur PDO natif absent.');
        $stale=new WatchysRuntimeProbeStock($stockId,null,1);$check((int)$stale->id===$stockId&&(int)$stale->quantity===10,'Hydratation native du stock non confirmée.');
        $staleGuard=WatchysStockObjectGuard::fromNative($module,$stale,$prefix);$stale->quantity=9;
        $request=[['identity'=>['id_order_detail'=>100,'component_product_id'=>7736,'component_attribute_id'=>0,'id_order'=>100,'id_customization'=>100,'id_shop'=>1],'required_units'=>2,'nomenclature_hash'=>hash('sha256','native-runtime-fixture')]];
        (new WatchysStockAllocator($pdo,$prefix))->apply($request,'reserve:runtime',['kind'=>'status','status_id'=>10]);$called=false;$caught=false;
        try{$staleGuard->update(static function()use($stale,&$called){$called=true;return $stale->update();});}catch(RuntimeException $e){$caught=$e->getMessage()==='Objet stock périmé : recharger avant écriture.';}
        $check($caught&&!$called&&$state()===[8,2,10],'Objet natif périmé non refusé avant update.');$progress('Objet StockAvailable natif hydraté ; objet périmé refusé avant écriture.');
        (new WatchysNativeStockCoordinator($pdo,$prefix,[7736]))->run([['productId'=>7736,'combinationId'=>0,'delta'=>-1]],static function()use($module,$pdo,$prefix,$stockId){
            $fresh=new WatchysRuntimeProbeStock($stockId,null,1);$guard=WatchysStockObjectGuard::fromNative($module,$fresh,$prefix);$fresh->quantity=(int)$fresh->quantity-1;
            $guard->update(static function()use($fresh){return $fresh->update();});
            $pdo->exec('INSERT INTO `'.$prefix.'orders` VALUES(200,1,10,0)');$pdo->exec('INSERT INTO `'.$prefix.'order_detail` VALUES(200,200,7736,0,1,0)');
        });
        $check($state()===[7,3,10],'Écriture ObjectModel et réservations cumulées non concordantes.');$progress('Écriture via ObjectModel/Db natifs relue sur le même PDO ; réservations cumulées conservées.');
        $fresh=new WatchysRuntimeProbeStock($stockId,null,1);$guard=WatchysStockObjectGuard::fromNative($module,$fresh,$prefix);$fresh->quantity=6;$caught=false;
        try{$guard->update(static function()use($fresh){if(!$fresh->update()){throw new RuntimeException('update natif faux');}throw new RuntimeException('runtime-fixture-failure');});}catch(RuntimeException $e){$caught=$e->getMessage()==='runtime-fixture-failure';}
        $check($caught&&$pdo->inTransaction()&&$state()===[7,3,10],'Échec après écriture native non annulé.');
        $pdo->rollBack();$check($state()===[10,0,10],'Rollback du PDO natif non confirmé.');
        foreach(['orders','order_detail','watchys_order_component','watchys_stock_ledger'] as $t){$check((int)$pdo->query('SELECT COUNT(*) FROM `'.$prefix.$t.'`')->fetchColumn()===0,'Fixture non annulée : '.$t);}
        $check(StockAvailable::$definition===$original,'Définition StockAvailable réelle modifiée.');$progress('Échec natif annulé puis transaction complète annulée ; définition du stock réel conservée.');
    }finally{
        if($pdo->inTransaction()){$pdo->rollBack();}
        foreach(array_reverse($created) as $name){if(!array_key_exists($name,$ddl)){throw new RuntimeException('Nettoyage temporaire hors périmètre.');}$pdo->exec('DROP TEMPORARY TABLE `'.$prefix.$name.'`');}
        WatchysRuntimeProbeStock::$definition=[];
    }
    return ['nativeObjectModel'=>true,'nativeDb'=>true,'nativePdoShared'=>true,'temporaryTables'=>count($created),'rolledBack'=>true,'notificationsDispatched'=>false];
}
