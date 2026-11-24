<?php
require_once __DIR__.'/WatchysStockPolicy.php';
/** Bookkeeping primitive, intentionally not connected to checkout or stock_available.
 * Caller owns the transaction and must apply inventory deltas atomically in the future adapter.
 * This class alone does not allocate actual stock and must not enable checkout.
 */
class WatchysStockJournal
{
    private $db; private $prefix; private $driver;
    public function __construct(PDO $db,$prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Préfixe invalide.'); }
        $this->db=$db; $this->prefix=$prefix; $this->driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($this->driver,['mysql','sqlite'],true)) { throw new InvalidArgumentException('Pilote non pris en charge.'); }
    }
    private function row($sql,$args)
    {
        $s=$this->db->prepare($sql); $s->execute($args); return $s->fetch(PDO::FETCH_ASSOC);
    }
    public function record($identity,$required,$hash,$requestId,$input)
    {
        if (!$this->db->inTransaction()) { throw new RuntimeException('Transaction du demandeur requise.'); }
        $fields=['id_order_detail','component_product_id','component_attribute_id','id_order','id_customization','id_shop'];
        if (!is_array($identity)||count($identity)!==count($fields)) { throw new InvalidArgumentException('Identité de ligne invalide.'); }
        $ids=[];
        foreach ($fields as $field) { $v=$identity[$field]??null; if (!is_int($v)||$v<($field==='component_attribute_id'?0:1)||$v>4294967295) { throw new InvalidArgumentException('Identifiant de ligne invalide.'); } $ids[$field]=$v; }
        if ($ids['id_shop']!==1||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!is_string($requestId)||!preg_match('/^[a-zA-Z0-9:_-]{1,120}$/D',$requestId)) { throw new InvalidArgumentException('Référence de mouvement invalide.'); }
        WatchysStockPolicy::initial($required); $event=WatchysStockPolicy::event($input);
        $payload=['identity'=>$ids,'required_units'=>$required,'nomenclature_hash'=>$hash,'event'=>$event];
        $digest=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        $operation=hash('sha256',json_encode([$ids,$requestId],JSON_THROW_ON_ERROR));
        $table='`'.$this->prefix.'watchys_order_component`'; $ledger='`'.$this->prefix.'watchys_stock_ledger`';
        $insert='INSERT INTO '.$table.' ('.implode(',',$fields).',required_units,nomenclature_hash) VALUES (?,?,?,?,?,?,?,?)';
        $insert.=$this->driver==='mysql'?' ON DUPLICATE KEY UPDATE id_order_detail=id_order_detail':' ON CONFLICT(id_order_detail,component_product_id,component_attribute_id) DO NOTHING';
        $s=$this->db->prepare($insert); $s->execute(array_merge(array_values($ids),[$required,$hash]));
        $where='id_order_detail=? AND component_product_id=? AND component_attribute_id=?';
        $key=[$ids['id_order_detail'],$ids['component_product_id'],$ids['component_attribute_id']];
        $lock=$this->driver==='mysql'?' FOR UPDATE':'';
        $row=$this->row('SELECT * FROM '.$table.' WHERE '.$where.$lock,$key);
        if (!$row) { throw new RuntimeException('Ligne de stock absente.'); }
        foreach ($ids as $field=>$value) { if ((int)$row[$field]!==$value) { throw new InvalidArgumentException('Ligne rattachée à une autre commande.'); } }
        if ((int)$row['required_units']!==$required||$row['nomenclature_hash']!==$hash) { throw new InvalidArgumentException('Nomenclature de la ligne modifiée.'); }
        if (!in_array((string)$row['released'],['0','1'],true)) { throw new RuntimeException('Indicateur de libération invalide.'); }
        $state=['required_units'=>(int)$row['required_units'],'reserved_units'=>(int)$row['reserved_units'],'shipped_units'=>(int)$row['shipped_units'],'returned_units'=>(int)$row['returned_units'],'released'=>(bool)$row['released']];
        WatchysStockPolicy::validate($state);
        $prior=$this->row('SELECT * FROM '.$ledger.' WHERE operation_key=?'.$lock,[$operation]);
        if ($prior) {
            if (!hash_equals($prior['request_digest'],$digest)) { throw new InvalidArgumentException('Événement déjà utilisé avec un autre contenu.'); }
            return ['replayed'=>true,'operation_key'=>$operation,'state'=>$state,'delta_available'=>0,'delta_reserved'=>0,'delta_physical'=>0];
        }
        $plan=WatchysStockPolicy::plan($state,$event); $next=$plan['after']; $revision=(int)$row['revision'];
        $s=$this->db->prepare('UPDATE '.$table.' SET reserved_units=?,shipped_units=?,returned_units=?,released=?,revision=revision+1 WHERE '.$where.' AND revision=?');
        $s->execute(array_merge([$next['reserved_units'],$next['shipped_units'],$next['returned_units'],$next['released']?1:0],$key,[$revision]));
        if ($s->rowCount()!==1) { throw new RuntimeException('Révision concurrente ; retour arrière requis.'); }
        $values=['operation_key'=>$operation,'request_digest'=>$digest]+$ids+['event_kind'=>$event['kind'],'event_reference'=>$requestId,'event_json'=>json_encode($event,JSON_THROW_ON_ERROR),'created_at'=>gmdate('Y-m-d H:i:s'),'revision_before'=>$revision,'revision_after'=>$revision+1,'delta_available'=>$plan['delta_available'],'delta_reserved'=>$plan['delta_reserved'],'delta_physical'=>$plan['delta_physical'],'before_json'=>json_encode($state,JSON_THROW_ON_ERROR),'after_json'=>json_encode($next,JSON_THROW_ON_ERROR)];
        $s=$this->db->prepare('INSERT INTO '.$ledger.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')'); $s->execute(array_values($values));
        return ['replayed'=>false,'operation_key'=>$operation,'state'=>$next,'delta_available'=>$plan['delta_available'],'delta_reserved'=>$plan['delta_reserved'],'delta_physical'=>$plan['delta_physical']];
    }
}
