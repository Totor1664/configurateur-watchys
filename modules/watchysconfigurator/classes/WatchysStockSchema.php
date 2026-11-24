<?php
/** Dedicated empty bookkeeping tables. DDL is not rolled back by MySQL. */
class WatchysStockSchema
{
    public static function definitions($prefix,$driver='mysql')
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||!in_array($driver,['mysql','sqlite'],true)) { throw new InvalidArgumentException('Schéma non autorisé.'); }
        $id=$driver==='mysql'?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
        return [
            'watchys_order_component'=>'CREATE TABLE `'.$prefix.'watchys_order_component` (id_order_detail INT UNSIGNED NOT NULL,component_product_id INT UNSIGNED NOT NULL,component_attribute_id INT UNSIGNED NOT NULL,id_order INT UNSIGNED NOT NULL,id_customization INT UNSIGNED NOT NULL,id_shop INT UNSIGNED NOT NULL,required_units INT UNSIGNED NOT NULL,nomenclature_hash CHAR(64) NOT NULL,reserved_units INT UNSIGNED NOT NULL DEFAULT 0,shipped_units INT UNSIGNED NOT NULL DEFAULT 0,returned_units INT UNSIGNED NOT NULL DEFAULT 0,released SMALLINT NOT NULL DEFAULT 0,revision INT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY(id_order_detail,component_product_id,component_attribute_id))'.$suffix,
            'watchys_stock_ledger'=>'CREATE TABLE `'.$prefix.'watchys_stock_ledger` (id_stock_ledger '.$id.',operation_key CHAR(64) NOT NULL,request_digest CHAR(64) NOT NULL,id_order_detail INT UNSIGNED NOT NULL,component_product_id INT UNSIGNED NOT NULL,component_attribute_id INT UNSIGNED NOT NULL,id_order INT UNSIGNED NOT NULL,id_customization INT UNSIGNED NOT NULL,id_shop INT UNSIGNED NOT NULL,event_kind VARCHAR(16) NOT NULL,event_reference VARCHAR(120) NOT NULL,event_json TEXT NOT NULL,created_at DATETIME NOT NULL,revision_before INT UNSIGNED NOT NULL,revision_after INT UNSIGNED NOT NULL,delta_available INT NOT NULL,delta_reserved INT NOT NULL,delta_physical INT NOT NULL,before_json TEXT NOT NULL,after_json TEXT NOT NULL,UNIQUE(operation_key))'.$suffix,
        ];
    }
    public static function columns()
    {
        return [
            'watchys_order_component'=>['id_order_detail','component_product_id','component_attribute_id','id_order','id_customization','id_shop','required_units','nomenclature_hash','reserved_units','shipped_units','returned_units','released','revision'],
            'watchys_stock_ledger'=>['id_stock_ledger','operation_key','request_digest','id_order_detail','component_product_id','component_attribute_id','id_order','id_customization','id_shop','event_kind','event_reference','event_json','created_at','revision_before','revision_after','delta_available','delta_reserved','delta_physical','before_json','after_json'],
        ];
    }
}
