<?php
/** Exact before/after permits for a single row on the SAME PDO transaction. */
class WatchysStockWriteGate
{
 private $db;private $p;private $connection;private $lock;
 public function __construct(PDO $db,$prefix)
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new InvalidArgumentException('Préfixe invalide.');}$this->db=$db;$this->p=$prefix;$driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
  if(!in_array($driver,['mysql','sqlite'],true)){throw new InvalidArgumentException('Pilote invalide.');}$this->lock=$driver==='mysql'?' FOR UPDATE':'';$this->connection=$driver==='mysql'?(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn():1;
 }
 public function write($stockId,array $after,callable $write)
 {
  if(!$this->db->inTransaction()||!is_int($stockId)||$stockId<1||!$after||array_diff(array_keys($after),['quantity','reserved_quantity','physical_quantity'])){throw new RuntimeException('Permis de stock sous transaction requis.');}
  foreach($after as $v){if(!is_int($v)||$v<0||$v>2147483647){throw new InvalidArgumentException('Compteur permis invalide.');}}
  $sp='watchys_permit_'.bin2hex(random_bytes(6));$this->db->exec('SAVEPOINT '.$sp);
  try{
   $s=$this->db->prepare('SELECT quantity,reserved_quantity,physical_quantity FROM `'.$this->p.'stock_available` WHERE id_stock_available=?'.$this->lock);$s->execute([$stockId]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r){throw new RuntimeException('Stock permis absent.');}$before=array_map('intval',$r);$next=array_replace($before,$after);$nonce=bin2hex(random_bytes(16));
   $insert=$this->db->prepare('INSERT INTO `'.$this->p.'watchys_stock_permit` (connection_id,id_stock_available,nonce,before_quantity,before_reserved,before_physical,after_quantity,after_reserved,after_physical) VALUES(?,?,?,?,?,?,?,?,?)');$insert->execute([$this->connection,$stockId,$nonce,$before['quantity'],$before['reserved_quantity'],$before['physical_quantity'],$next['quantity'],$next['reserved_quantity'],$next['physical_quantity']]);
   $result=$write();if(!$this->db->inTransaction()){throw new RuntimeException('Transaction fermée par le callback stock.');}
   $s->execute([$stockId]);$actual=$s->fetch(PDO::FETCH_ASSOC);if(!$actual||array_map('intval',$actual)!==$next||$result===false){throw new RuntimeException('Écriture différente du permis.');}
   $del=$this->db->prepare('DELETE FROM `'.$this->p.'watchys_stock_permit` WHERE connection_id=? AND id_stock_available=? AND nonce=?');$del->execute([$this->connection,$stockId,$nonce]);if($del->rowCount()!==1){throw new RuntimeException('Permis non retiré.');}
   $this->db->exec('RELEASE SAVEPOINT '.$sp);return $result;
  }catch(Throwable $e){try{$this->db->exec('ROLLBACK TO SAVEPOINT '.$sp);$this->db->exec('RELEASE SAVEPOINT '.$sp);}catch(Throwable $rollback){throw new RuntimeException('Retour arrière du permis non confirmé.',0,$e);}throw $e;}
 }
}
