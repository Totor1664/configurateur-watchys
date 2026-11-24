<?php
/** Current reads under stock locks. Native + Watchys reservations, without changing available. */
class WatchysStockReconciler
{
 private $db;private $p;private $gate;private $lock;
 public function __construct(PDO $db,$prefix,$gate=null){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new InvalidArgumentException('Préfixe invalide.');}$this->db=$db;$this->p=$prefix;$this->gate=$gate;$this->lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
 public function run(array $pairs)
 {
  if(!$this->db->inTransaction()){throw new RuntimeException('Réconciliation sous transaction requise.');}$p=$this->p;$lock=$this->lock;$keys=[];
  foreach($pairs as $pair){if(!is_array($pair)||count($pair)!==2||!is_int($pair[0])||$pair[0]<1||!is_int($pair[1])||$pair[1]<0){throw new InvalidArgumentException('SKU invalide.');}$keys[$pair[0].':'.$pair[1]]=$pair;}uasort($keys,static function($a,$b){return $a<=>$b;});
  foreach($keys as $pair){
   $s=$this->db->prepare('SELECT * FROM `'.$p.'stock_available` WHERE id_product=? AND id_product_attribute=? AND id_shop=1 AND id_shop_group=0'.$lock);$s->execute($pair);$rows=$s->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1||(int)$rows[0]['quantity']<0||(int)$rows[0]['depends_on_stock']!==0){throw new RuntimeException('Stock réconciliable non confirmé.');}$row=$rows[0];
   $s=$this->db->prepare('SELECT od.product_quantity,od.product_quantity_refunded FROM `'.$p.'order_detail` od JOIN `'.$p.'orders` o ON o.id_order=od.id_order JOIN `'.$p.'order_state` os ON os.id_order_state=o.current_state WHERE o.id_shop=1 AND os.shipped<>1 AND (o.valid=1 OR (os.id_order_state<>6 AND os.id_order_state<>8)) AND od.product_id=? AND od.product_attribute_id=?'.$lock);$s->execute($pair);$reserved=0;foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$n=(int)$r['product_quantity']-(int)$r['product_quantity_refunded'];if($n<0){throw new RuntimeException('Réservation native négative.');}$reserved+=$n;}
   $s=$this->db->prepare('SELECT reserved_units FROM `'.$p.'watchys_order_component` WHERE component_product_id=? AND component_attribute_id=? AND id_shop=1'.$lock);$s->execute($pair);foreach($s->fetchAll(PDO::FETCH_COLUMN) as $n){if((int)$n<0){throw new RuntimeException('Réservation Watchys négative.');}$reserved+=(int)$n;}
   $physical=(int)$row['quantity']+$reserved;if($physical>2147483647){throw new RuntimeException('Physique hors limites.');}
   $write=function()use($row,$reserved,$physical,$p){$q=$this->db->prepare('UPDATE `'.$p.'stock_available` SET reserved_quantity=?,physical_quantity=? WHERE id_stock_available=?');$q->execute([$reserved,$physical,(int)$row['id_stock_available']]);return true;};
   if($this->gate){$this->gate->write((int)$row['id_stock_available'],['reserved_quantity'=>$reserved,'physical_quantity'=>$physical],$write);}else{$write();}
   $s=$this->db->prepare('SELECT quantity,reserved_quantity,physical_quantity FROM `'.$p.'stock_available` WHERE id_stock_available=?');$s->execute([(int)$row['id_stock_available']]);$a=$s->fetch(PDO::FETCH_ASSOC);if(!$a||(int)$a['quantity']!==(int)$row['quantity']||(int)$a['reserved_quantity']!==$reserved||(int)$a['physical_quantity']!==$physical){throw new RuntimeException('Réconciliation non relue.');}
  }
 }
}
