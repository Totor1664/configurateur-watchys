<?php
/** Passive schema. Enabling a watched row is a separate, explicit operation. */
class WatchysNativeSchema
{
 public static function definitions($p,$driver='mysql')
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||!in_array($driver,['mysql','sqlite'],true)){throw new InvalidArgumentException('Schéma natif invalide.');}
  $auto=$driver==='mysql'?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$tail=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
  return [
   'watchys_stock_component'=>'CREATE TABLE `'.$p.'watchys_stock_component` (id_product INT NOT NULL,id_product_attribute INT NOT NULL,id_shop INT NOT NULL,enabled INT NOT NULL DEFAULT 0,PRIMARY KEY(id_product,id_product_attribute,id_shop))'.$tail,
   'watchys_stock_permit'=>'CREATE TABLE `'.$p.'watchys_stock_permit` (connection_id BIGINT NOT NULL,id_stock_available INT NOT NULL,nonce CHAR(32) NOT NULL,before_quantity INT NOT NULL,before_reserved INT NOT NULL,before_physical INT NOT NULL,after_quantity INT NOT NULL,after_reserved INT NOT NULL,after_physical INT NOT NULL,PRIMARY KEY(connection_id,id_stock_available))'.$tail,
   'watchys_native_operation'=>'CREATE TABLE `'.$p.'watchys_native_operation` (operation_key CHAR(64) PRIMARY KEY,kind VARCHAR(20) NOT NULL,subject_id INT NOT NULL,request_key VARCHAR(120) NOT NULL UNIQUE,request_digest CHAR(64) NOT NULL,state VARCHAR(20) NOT NULL,order_ids TEXT NOT NULL,created_at DATETIME NOT NULL)'.$tail,
   'watchys_native_effect'=>'CREATE TABLE `'.$p.'watchys_native_effect` (id_effect '.$auto.',operation_key CHAR(64) NOT NULL,sequence_no INT NOT NULL,kind VARCHAR(20) NOT NULL,payload MEDIUMTEXT NOT NULL,state VARCHAR(20) NOT NULL,id_stock_mvt INT NULL,created_at DATETIME NOT NULL,UNIQUE(operation_key,sequence_no))'.$tail,
   'watchys_received_return'=>'CREATE TABLE `'.$p.'watchys_received_return` (id_receipt '.$auto.',request_key CHAR(32) NOT NULL UNIQUE,request_digest CHAR(64) NOT NULL,id_order INT NOT NULL,id_order_detail INT NOT NULL,returned_watches INT NOT NULL,id_employee INT NOT NULL,created_at DATETIME NOT NULL)'.$tail,
  ];
 }
 public static function triggers($p)
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)){throw new InvalidArgumentException('Préfixe invalide.');}
  $watched='EXISTS(SELECT 1 FROM `'.$p.'watchys_stock_component` c WHERE c.id_product=OLD.id_product AND c.id_product_attribute=OLD.id_product_attribute AND c.id_shop=OLD.id_shop AND c.enabled=1)';
  $permit='EXISTS(SELECT 1 FROM `'.$p.'watchys_stock_permit` w WHERE w.connection_id=CONNECTION_ID() AND w.id_stock_available=OLD.id_stock_available AND w.before_quantity=OLD.quantity AND w.before_reserved=OLD.reserved_quantity AND w.before_physical=OLD.physical_quantity AND w.after_quantity=NEW.quantity AND w.after_reserved=NEW.reserved_quantity AND w.after_physical=NEW.physical_quantity)';
  return [
   $p.'wcfg_stock_update'=>'CREATE TRIGGER `'.$p.'wcfg_stock_update` BEFORE UPDATE ON `'.$p.'stock_available` FOR EACH ROW BEGIN IF '.$watched.' THEN IF NEW.id_product<>OLD.id_product OR NEW.id_product_attribute<>OLD.id_product_attribute OR NEW.id_shop<>OLD.id_shop OR NEW.id_shop_group<>OLD.id_shop_group OR NEW.id_stock_available<>OLD.id_stock_available THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Watchys: protected stock identity\'; END IF; IF NEW.quantity IS NULL OR NEW.reserved_quantity IS NULL OR NEW.physical_quantity IS NULL OR NEW.quantity<0 OR NEW.reserved_quantity<0 OR NEW.physical_quantity<0 THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Watchys: negative stock forbidden\'; END IF; IF (NEW.quantity<>OLD.quantity OR NEW.reserved_quantity<>OLD.reserved_quantity OR NEW.physical_quantity<>OLD.physical_quantity) AND NOT '.$permit.' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Watchys: coordinated stock permit required\'; END IF; END IF; END',
   $p.'wcfg_stock_delete'=>'CREATE TRIGGER `'.$p.'wcfg_stock_delete` BEFORE DELETE ON `'.$p.'stock_available` FOR EACH ROW BEGIN IF '.$watched.' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Watchys: disable component before deleting stock\'; END IF; END',
  ];
 }
}
