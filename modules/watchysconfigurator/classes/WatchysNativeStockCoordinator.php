<?php
/** Experimental transaction boundary; NOT registered with PrestaShop.
 * The callback must finish all related native order/detail/status SQL before returning,
 * use this same PDO, and perform no commit, notification or external side effect.
 * Locking only around an already-loaded StockAvailable object is insufficient.
 */
class WatchysNativeStockCoordinator
{
    private $db; private $prefix; private $driver; private $allowed;
    public function __construct(PDO $db,$prefix,array $componentIds)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||!$componentIds){throw new InvalidArgumentException('Périmètre de stock invalide.');}
        foreach($componentIds as $id){if(!is_int($id)||$id<1||$id>2147483647||$id===8867){throw new InvalidArgumentException('Composant non autorisé.');}}
        $this->db=$db;$this->prefix=$prefix;$this->allowed=array_fill_keys($componentIds,true);$this->driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if(!in_array($this->driver,['mysql','sqlite'],true)){throw new InvalidArgumentException('Pilote non pris en charge.');}
    }
    private function rows($sql,$args=[]){$s=$this->db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function run(array $changes,callable $operation)
    {
        if(!$this->db->inTransaction()){throw new RuntimeException('Transaction du demandeur requise.');}
        if(!array_is_list($changes)||!$changes||count($changes)>1000){throw new InvalidArgumentException('Lot natif invalide.');}
        $skus=[];
        foreach($changes as $change){
            $pid=$change['productId']??null;$attr=$change['combinationId']??null;$delta=$change['delta']??null;
            if(!is_int($pid)||!isset($this->allowed[$pid])||!is_int($attr)||$attr<0||$attr>2147483647||!is_int($delta)||abs($delta)>1000000){throw new InvalidArgumentException('Mouvement natif invalide.');}
            $key=$pid.':'.$attr;$skus[$key]=$skus[$key]??['productId'=>$pid,'combinationId'=>$attr,'delta'=>0];$skus[$key]['delta']+=$delta;
        }
        uasort($skus,static function($a,$b){return [$a['productId'],$a['combinationId']]<=>[$b['productId'],$b['combinationId']];});
        $sp='watchys_native_'.bin2hex(random_bytes(6));$this->db->exec('SAVEPOINT '.$sp);$lock=$this->driver==='mysql'?' FOR UPDATE':'';$p=$this->prefix;
        try{
            $before=[];
            foreach($skus as $key=>$sku){
                $rows=$this->rows('SELECT * FROM `'.$p.'stock_available` WHERE id_product=? AND id_product_attribute=? AND id_shop=1 AND id_shop_group=0'.$lock,[$sku['productId'],$sku['combinationId']]);
                if(count($rows)!==1){throw new RuntimeException('Stock unique non confirmé.');}$r=$rows[0];
                foreach(['quantity','reserved_quantity','physical_quantity'] as $field){if(!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$r[$field])||(float)$r[$field]>2147483647){throw new RuntimeException('Compteurs natifs invalides.');}}
                if((int)$r['depends_on_stock']!==0||(int)$r['physical_quantity']!==(int)$r['quantity']+(int)$r['reserved_quantity']){throw new RuntimeException('Stock avancé ou incohérent.');}
                $pending=$this->rows('SELECT reserved_units FROM `'.$p.'watchys_order_component` WHERE component_product_id=? AND component_attribute_id=? AND id_shop=1'.$lock,[$sku['productId'],$sku['combinationId']]);
                if(array_sum(array_map('intval',array_column($pending,'reserved_units')))>(int)$r['reserved_quantity']){throw new RuntimeException('Réservations Watchys précédemment effacées.');}
                $next=(int)$r['quantity']+$sku['delta'];if($next<0||$next>2147483647){throw new InvalidArgumentException('Stock insuffisant pour la vente native.');}$before[$key]=$r;
            }
            // This is the FIRST point where the native writer may load its stock objects.
            $result=$operation();
            if(!$this->db->inTransaction()){throw new RuntimeException('Le callback a fermé la transaction ; reprise manuelle requise.');}
            foreach($skus as $key=>$sku){
                $old=$before[$key];$actual=$this->rows('SELECT * FROM `'.$p.'stock_available` WHERE id_stock_available=?'.$lock,[(int)$old['id_stock_available']]);
                if(count($actual)!==1||(int)$actual[0]['quantity']!==(int)$old['quantity']+$sku['delta']||(int)$actual[0]['depends_on_stock']!==0||(int)$actual[0]['id_product']!==$sku['productId']||(int)$actual[0]['id_product_attribute']!==$sku['combinationId']||(int)$actual[0]['id_shop']!==1||(int)$actual[0]['id_shop_group']!==0){throw new RuntimeException('Débit natif différent du mouvement annoncé.');}
                // Same pending-order predicate as the pinned native Adapter\StockManager (6/8).
                // Order/detail/status changes must already be visible in this transaction.
                $native=$this->rows('SELECT od.product_quantity,od.product_quantity_refunded FROM `'.$p.'orders` o JOIN `'.$p.'order_detail` od ON od.id_order=o.id_order JOIN `'.$p.'order_state` os ON os.id_order_state=o.current_state WHERE o.id_shop=1 AND os.shipped<>1 AND (o.valid=1 OR (os.id_order_state<>8 AND os.id_order_state<>6)) AND od.product_id=? AND od.product_attribute_id=?'.$lock,[$sku['productId'],$sku['combinationId']]);
                $pending=$this->rows('SELECT reserved_units FROM `'.$p.'watchys_order_component` WHERE component_product_id=? AND component_attribute_id=? AND id_shop=1'.$lock,[$sku['productId'],$sku['combinationId']]);
                $nativeUnits=0;foreach($native as $line){$units=(int)$line['product_quantity']-(int)$line['product_quantity_refunded'];if($units<0){throw new RuntimeException('Réservation native négative.');}$nativeUnits+=$units;}
                $reserved=$nativeUnits+array_sum(array_map('intval',array_column($pending,'reserved_units')));$quantity=(int)$actual[0]['quantity'];$physical=$quantity+$reserved;
                if($reserved<0||$physical>2147483647){throw new RuntimeException('Réservations cumulées impossibles.');}
                $s=$this->db->prepare('UPDATE `'.$p.'stock_available` SET reserved_quantity=?,physical_quantity=? WHERE id_stock_available=? AND quantity=?');$s->execute([$reserved,$physical,(int)$old['id_stock_available'],$quantity]);
                $read=$this->rows('SELECT quantity,reserved_quantity,physical_quantity FROM `'.$p.'stock_available` WHERE id_stock_available=?',[(int)$old['id_stock_available']]);
                if(count($read)!==1||(int)$read[0]['quantity']!==$quantity||(int)$read[0]['reserved_quantity']!==$reserved||(int)$read[0]['physical_quantity']!==$physical){throw new RuntimeException('Réservations natives et Watchys non relues.');}
            }
            $this->db->exec('RELEASE SAVEPOINT '.$sp);return $result;
        }catch(Throwable $e){
            try{$this->db->exec('ROLLBACK TO SAVEPOINT '.$sp);$this->db->exec('RELEASE SAVEPOINT '.$sp);}catch(Throwable $rollback){throw new RuntimeException('Retour arrière non confirmé ; transaction complète à annuler.',0,$e);}throw $e;
        }
    }
}
