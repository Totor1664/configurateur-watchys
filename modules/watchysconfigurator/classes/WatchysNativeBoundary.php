<?php
require_once __DIR__.'/WatchysNativeOutbox.php';
/** Transaction owner with a savepoint mode reserved for the private rollback trial. */
class WatchysNativeBoundary
{
 private $db;private $p;private $active=false;private $owned;private $sp;private $operation;private $outbox;private $pairs=[];private $replay=null;
 public function __construct(PDO $db,$prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new InvalidArgumentException('Préfixe invalide.');}$this->db=$db;$this->p=$prefix;}
 public function begin($kind,$subject,$requestKey,$digest,array $requirements,callable $lockSubject,$allowOuter=false)
 {
  if($this->active||!in_array($kind,['checkout','history','stock','return'],true)||!is_int($subject)||$subject<1||!preg_match('/^[A-Za-z0-9:_-]{1,120}$/D',$requestKey)||!preg_match('/^[a-f0-9]{64}$/D',$digest)){throw new InvalidArgumentException('Opération native invalide.');}
  if($this->db->inTransaction()&&!$allowOuter){throw new RuntimeException('Transaction externe non coordonnée.');}
  $this->owned=!$this->db->inTransaction();if($this->owned){$this->db->beginTransaction();}else{$this->sp='watchys_boundary_'.bin2hex(random_bytes(6));$this->db->exec('SAVEPOINT '.$this->sp);}$this->active=true;
  try{
   $lockSubject();$lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
   $q=$this->db->prepare('SELECT * FROM `'.$this->p.'watchys_native_operation` WHERE request_key=?'.$lock);$q->execute([$requestKey]);$prior=$q->fetch(PDO::FETCH_ASSOC);
   if($prior){if($prior['state']!=='committed'||$prior['kind']!==$kind||(int)$prior['subject_id']!==$subject||!hash_equals($prior['request_digest'],$digest)){throw new InvalidArgumentException('Reprise native différente ou incomplète.');}$this->replay=json_decode($prior['order_ids'],true,16,JSON_THROW_ON_ERROR);return true;}
   $this->pairs=[];foreach($requirements as $r){if(!is_array($r)||!is_int($r['productId']??null)||$r['productId']<1||!is_int($r['combinationId']??null)||$r['combinationId']<0||!is_int($r['required']??null)||$r['required']<0||$r['required']>1000000){throw new InvalidArgumentException('Besoin natif invalide.');}$k=$r['productId'].':'.$r['combinationId'];if(isset($this->pairs[$k])){throw new InvalidArgumentException('SKU natif dupliqué.');}$this->pairs[$k]=$r;}
   uasort($this->pairs,static function($a,$b){return [$a['productId'],$a['combinationId']]<=>[$b['productId'],$b['combinationId']];});
   foreach($this->pairs as $r){$q=$this->db->prepare('SELECT quantity,depends_on_stock FROM `'.$this->p.'stock_available` WHERE id_product=? AND id_product_attribute=? AND id_shop=1 AND id_shop_group=0'.$lock);$q->execute([$r['productId'],$r['combinationId']]);$a=$q->fetchAll(PDO::FETCH_ASSOC);if(count($a)!==1||(int)$a[0]['depends_on_stock']!==0||(int)$a[0]['quantity']<$r['required']){throw new InvalidArgumentException('Stock insuffisant ou non coordonné avant commande.');}}
   $this->operation=hash('sha256',random_bytes(32));$this->outbox=new WatchysNativeOutbox($this->db,$this->p,$this->operation);
   $q=$this->db->prepare('INSERT INTO `'.$this->p.'watchys_native_operation` (operation_key,kind,subject_id,request_key,request_digest,state,order_ids,created_at) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$this->operation,$kind,$subject,$requestKey,$digest,'preparing','[]',gmdate('Y-m-d H:i:s')]);return false;
  }catch(Throwable $e){$this->abort();throw $e;}
 }
 public function outbox(){if(!$this->active||!$this->outbox){throw new RuntimeException('Aucun journal d’effets actif.');}return $this->outbox;}
 public function pairs(){return array_map(static function($r){return [$r['productId'],$r['combinationId']];},array_values($this->pairs));}
 public function replayOrders(){return $this->replay;}
 public function commit(array $orderIds,$finalRequestKey=null)
 {
  if(!$this->active||!$this->db->inTransaction()){throw new RuntimeException('Transaction native perdue.');}
  foreach($orderIds as $id){if(!is_int($id)||$id<1){throw new InvalidArgumentException('Commande résultante invalide.');}}
  if($finalRequestKey!==null&&!preg_match('/^[A-Za-z0-9:_-]{1,120}$/D',$finalRequestKey)){throw new InvalidArgumentException('Clé finale invalide.');}
  if($this->replay===null){$q=$this->db->prepare('UPDATE `'.$this->p.'watchys_native_operation` SET state=?,order_ids=?'.($finalRequestKey!==null?',request_key=?':'').' WHERE operation_key=? AND state=?');$args=['committed',json_encode($orderIds,JSON_THROW_ON_ERROR)];if($finalRequestKey!==null){$args[]=$finalRequestKey;}$args[]=$this->operation;$args[]='preparing';$q->execute($args);if($q->rowCount()!==1){throw new RuntimeException('Opération native non confirmée.');}}
  if($this->owned){$this->db->commit();}else{$this->db->exec('RELEASE SAVEPOINT '.$this->sp);}$this->active=false;
 }
 public function abort()
 {
  if(!$this->active){return;}
  try{if($this->db->inTransaction()){if($this->owned){$this->db->rollBack();}else{$this->db->exec('ROLLBACK TO SAVEPOINT '.$this->sp);$this->db->exec('RELEASE SAVEPOINT '.$this->sp);}}}finally{$this->active=false;}
 }
 public function __destruct(){if($this->active){try{$this->abort();}catch(Throwable $ignored){}}}
}
