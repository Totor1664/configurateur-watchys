<?php
require_once __DIR__.'/WatchysEffectCodec.php';
/** Durable held effects. No transport or payment API is called by this class.
 * Claim is deliberately not auto-retried: a crash after external delivery is ambiguous.
 */
class WatchysNativeOutbox
{
 private $db;private $p;private $op;private $sequence=0;
 public function __construct(PDO $db,$prefix,$operation)
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||!preg_match('/^[a-f0-9]{64}$/D',$operation)){throw new InvalidArgumentException('Journal d’effets invalide.');}$this->db=$db;$this->p=$prefix;$this->op=$operation;
 }
 public function enqueue($kind,$payload)
 {
  if(!$this->db->inTransaction()||!in_array($kind,['hook','mail','movement'],true)){throw new RuntimeException('Effet sous transaction requis.');}
  $body=WatchysEffectCodec::encode($payload);$s=$this->db->prepare('INSERT INTO `'.$this->p.'watchys_native_effect` (operation_key,sequence_no,kind,payload,state,id_stock_mvt,created_at) VALUES(?,?,?,?,?,NULL,?)');$s->execute([$this->op,++$this->sequence,$kind,$body,'held',gmdate('Y-m-d H:i:s')]);return (int)$this->db->lastInsertId();
 }
 public static function claim(PDO $db,$prefix,$id)
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||!is_int($id)||$id<1||!$db->inTransaction()){throw new InvalidArgumentException('Réclamation sous transaction requise.');}
  $lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$s=$db->prepare('SELECT e.* FROM `'.$prefix.'watchys_native_effect` e JOIN `'.$prefix.'watchys_native_operation` o ON o.operation_key=e.operation_key WHERE e.id_effect=? AND o.state=?'.$lock);$s->execute([$id,'committed']);$r=$s->fetch(PDO::FETCH_ASSOC);
  if(!$r||$r['state']!=='released'){throw new RuntimeException('Effet non libéré ou déjà réclamé.');}$s=$db->prepare('UPDATE `'.$prefix.'watchys_native_effect` SET state=? WHERE id_effect=? AND state=?');$s->execute(['sending',$id,'released']);if($s->rowCount()!==1){throw new RuntimeException('Réclamation concurrente.');}return $r;
 }
}
