<?php
require_once __DIR__.'/WatchysStockAllocator.php';
/** Internal receipt API, not a public route. An authorised employee confirms actual receipt.
 * Financial refunds do not call this service. All component writes share the caller's PDO.
 */
class WatchysReceivedReturns
{
 private $db;private $p;private $gate;private $lock;
 public function __construct(PDO $db,$prefix,$gate=null){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new InvalidArgumentException('Préfixe invalide.');}$this->db=$db;$this->p=$prefix;$this->gate=$gate;$this->lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
 public function receive($order,$detail,$total,$employee,$key)
 {
  foreach([$order,$detail,$total,$employee] as $v){if(!is_int($v)||$v<1||$v>2147483647){throw new InvalidArgumentException('Réception invalide.');}}
  if($total>100||!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key)||!$this->db->inTransaction()){throw new InvalidArgumentException('Réception sous transaction requise.');}
  $p=$this->p;$sp='watchys_receipt_'.bin2hex(random_bytes(6));$this->db->exec('SAVEPOINT '.$sp);
  try{
   $s=$this->db->prepare('SELECT e.id_employee FROM `'.$p.'employee` e JOIN `'.$p.'access` a ON a.id_profile=e.id_profile JOIN `'.$p.'authorization_role` r ON r.id_authorization_role=a.id_authorization_role WHERE e.id_employee=? AND e.active=1 AND r.slug=?');$s->execute([$employee,'ROLE_MOD_MODULE_WATCHYSCONFIGURATOR_UPDATE']);if(!$s->fetchColumn()){throw new RuntimeException('Réception réservée à un employé autorisé.');}
   $s=$this->db->prepare('SELECT product_quantity,id_customization FROM `'.$p.'order_detail` WHERE id_order_detail=? AND id_order=?'.$this->lock);$s->execute([$detail,$order]);$line=$s->fetch(PDO::FETCH_ASSOC);if(!$line||(int)$line['product_quantity']<1||$total>(int)$line['product_quantity']){throw new InvalidArgumentException('Réception supérieure à la ligne commandée.');}
   $s=$this->db->prepare('SELECT * FROM `'.$p.'watchys_order_component` WHERE id_order=? AND id_order_detail=? ORDER BY component_product_id,component_attribute_id'.$this->lock);$s->execute([$order,$detail]);$pieces=$s->fetchAll(PDO::FETCH_ASSOC);if(!$pieces){throw new RuntimeException('Nomenclature de commande non allouée.');}
   $digest=hash('sha256',json_encode([$order,$detail,$total,$employee],JSON_THROW_ON_ERROR));$s=$this->db->prepare('SELECT * FROM `'.$p.'watchys_received_return` WHERE request_key=?'.$this->lock);$s->execute([$key]);$prior=$s->fetch(PDO::FETCH_ASSOC);
   if($prior){if(!hash_equals($prior['request_digest'],$digest)){throw new InvalidArgumentException('Reprise de réception altérée.');}$receipt=(int)$prior['id_receipt'];}else{$s=$this->db->prepare('INSERT INTO `'.$p.'watchys_received_return` (request_key,request_digest,id_order,id_order_detail,returned_watches,id_employee,created_at) VALUES(?,?,?,?,?,?,?)');$s->execute([$key,$digest,$order,$detail,$total,$employee,gmdate('Y-m-d H:i:s')]);$receipt=(int)$this->db->lastInsertId();}
   $results=[];$allocator=new WatchysStockAllocator($this->db,$p,$this->gate);
   foreach($pieces as $piece){$quantity=(int)$line['product_quantity'];$required=(int)$piece['required_units'];if($required%$quantity!==0||(int)$piece['id_customization']!==(int)$line['id_customization']){throw new RuntimeException('Unités de réception incohérentes.');}
    $request=['identity'=>['id_order_detail'=>$detail,'component_product_id'=>(int)$piece['component_product_id'],'component_attribute_id'=>(int)$piece['component_attribute_id'],'id_order'=>$order,'id_customization'=>(int)$piece['id_customization'],'id_shop'=>1],'required_units'=>$required,'nomenclature_hash'=>$piece['nomenclature_hash']];
    $results[]=$allocator->apply([$request],'receipt:'.$receipt,['kind'=>'return','returned_units_total'=>intdiv($required,$quantity)*$total]);
   }
   $this->db->exec('RELEASE SAVEPOINT '.$sp);return ['receiptId'=>$receipt,'results'=>$results,'replayed'=>$prior!==false];
  }catch(Throwable $e){try{$this->db->exec('ROLLBACK TO SAVEPOINT '.$sp);$this->db->exec('RELEASE SAVEPOINT '.$sp);}catch(Throwable $rollback){throw new RuntimeException('Retour arrière de réception non confirmé.',0,$e);}throw $e;}
 }
}
