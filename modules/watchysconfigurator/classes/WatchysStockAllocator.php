<?php
require_once __DIR__.'/WatchysStockJournal.php';
/** Low-level allocator. Not registered on any hook and not a checkout integration.
 * Uses the caller's PDO/transaction; isolates its batch with a savepoint.
 * Native stock caches, movements, alerts and native writers still need an integration adapter.
 */
class WatchysStockAllocator
{
    private $db; private $prefix; private $driver; private $gate;
    public function __construct(PDO $db,$prefix,$gate=null)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Préfixe invalide.'); }
        $this->db=$db; $this->prefix=$prefix; $this->gate=$gate; $this->driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($this->driver,['mysql','sqlite'],true)) { throw new InvalidArgumentException('Pilote non pris en charge.'); }
    }
    private function rows($sql,$args=[])
    {
        $s=$this->db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    private function number($v,$min=0)
    {
        if (!is_int($v)||$v<$min||$v>2147483647) { throw new InvalidArgumentException('Quantité ou identifiant invalide.'); }
        return $v;
    }
    public function apply($requests,$requestId,$input)
    {
        if (!$this->db->inTransaction()) { throw new RuntimeException('Transaction du demandeur requise.'); }
        if (!is_array($requests)||!array_is_list($requests)||!$requests||count($requests)>1000) { throw new InvalidArgumentException('Lot de composants invalide.'); }
        $event=WatchysStockPolicy::event($input);$keys=[];$unique=[];
        foreach ($requests as $request) {
            $id=$request['identity']??null;
            if (!is_array($id)||count($id)!==6) { throw new InvalidArgumentException('Identité composant invalide.'); }
            foreach (['id_order_detail','component_product_id','component_attribute_id','id_order','id_customization','id_shop'] as $field) { $this->number($id[$field]??null,$field==='component_attribute_id'?0:1); }
            if ($id['id_shop']!==1||$id['component_product_id']===8867) { throw new InvalidArgumentException('Composant ou boutique non autorisé.'); }
            WatchysStockPolicy::initial($request['required_units']??null);
            if (!is_string($request['nomenclature_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$request['nomenclature_hash'])) { throw new InvalidArgumentException('Nomenclature invalide.'); }
            $sku=$id['component_product_id'].':'.$id['component_attribute_id'];
            $line=$id['id_order_detail'].':'.$sku;
            if (isset($unique[$line])) { throw new InvalidArgumentException('Composant dupliqué dans une même ligne.'); }
            $unique[$line]=true;$keys[$sku]=[$id['component_product_id'],$id['component_attribute_id']];
        }
        uasort($keys,static function($a,$b){return $a<=>$b;});
        usort($requests,static function($a,$b){$x=$a['identity'];$y=$b['identity'];return [$x['component_product_id'],$x['component_attribute_id'],$x['id_order_detail']]<=>[$y['component_product_id'],$y['component_attribute_id'],$y['id_order_detail']];});
        $savepoint='watchys_allocate_'.bin2hex(random_bytes(6));$this->db->exec('SAVEPOINT '.$savepoint);
        try {
            $table='`'.$this->prefix.'stock_available`';$lock=$this->driver==='mysql'?' FOR UPDATE':'';$stocks=[];
            foreach ($keys as $sku=>$pair) {
                $rows=$this->rows('SELECT * FROM '.$table.' WHERE id_product=? AND id_product_attribute=? AND id_shop=1 AND id_shop_group=0'.$lock,$pair);
                if (count($rows)!==1) { throw new RuntimeException('Stock unique non confirmé : '.$sku); }
                $row=$rows[0];
                foreach (['quantity','reserved_quantity','physical_quantity'] as $field) {
                    if (!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$row[$field])||(float)$row[$field]>2147483647) { throw new RuntimeException('Stock négatif ou hors limites : '.$sku); }
                }
                if ((int)$row['depends_on_stock']!==0||(int)$row['physical_quantity']!==(int)$row['quantity']+(int)$row['reserved_quantity']) { throw new RuntimeException('Stock avancé ou compteurs incohérents : '.$sku); }
                // A native recalculation must not silently erase pending Watchys commitments.
                $pending=$this->rows('SELECT reserved_units FROM `'.$this->prefix.'watchys_order_component` WHERE component_product_id=? AND component_attribute_id=? AND id_shop=1'.$lock,$pair);
                $sum=array_sum(array_map('intval',array_column($pending,'reserved_units')));
                if ($sum>(int)$row['reserved_quantity']) { throw new RuntimeException('Réservations Watchys absentes du stock natif : '.$sku); }
                $stocks[$sku]=$row;
            }
            $journal=new WatchysStockJournal($this->db,$this->prefix);$results=[];$deltas=[];
            foreach ($requests as $request) {
                $result=$journal->record($request['identity'],$request['required_units'],$request['nomenclature_hash'],$requestId,$event);
                $id=$request['identity'];$sku=$id['component_product_id'].':'.$id['component_attribute_id'];
                $deltas[$sku]=$deltas[$sku]??['available'=>0,'reserved'=>0,'physical'=>0];
                foreach (['available','reserved','physical'] as $field) { $deltas[$sku][$field]+=$result['delta_'.$field]; }
                $results[]=$result;
            }
            // Validate the whole requirement before updating even the first inventory row.
            foreach ($deltas as $sku=>$delta) {
                $row=$stocks[$sku];$available=(int)$row['quantity']+$delta['available'];$reserved=(int)$row['reserved_quantity']+$delta['reserved'];$physical=(int)$row['physical_quantity']+$delta['physical'];
                if (min($available,$reserved,$physical)<0||max($available,$reserved,$physical)>2147483647||$physical!==$available+$reserved) { throw new InvalidArgumentException('Stock insuffisant ou compteurs impossibles : '.$sku); }
            }
            $update=$this->db->prepare('UPDATE '.$table.' SET quantity=quantity+?,reserved_quantity=reserved_quantity+?,physical_quantity=physical_quantity+? WHERE id_stock_available=? AND id_shop=1 AND id_shop_group=0 AND depends_on_stock=0 AND quantity=? AND reserved_quantity=? AND physical_quantity=?');
            foreach ($deltas as $sku=>$delta) {
                if (!$delta['available']&&!$delta['reserved']&&!$delta['physical']) { continue; }
                $row=$stocks[$sku];$write=static function()use($update,$delta,$row){$update->execute([$delta['available'],$delta['reserved'],$delta['physical'],(int)$row['id_stock_available'],(int)$row['quantity'],(int)$row['reserved_quantity'],(int)$row['physical_quantity']]);return true;};
                if($this->gate){$this->gate->write((int)$row['id_stock_available'],['quantity'=>(int)$row['quantity']+$delta['available'],'reserved_quantity'=>(int)$row['reserved_quantity']+$delta['reserved'],'physical_quantity'=>(int)$row['physical_quantity']+$delta['physical']],$write);}else{$write();}
                if ($update->rowCount()!==1) { throw new RuntimeException('Stock modifié simultanément : '.$sku); }
            }
            foreach ($keys as $sku=>$pair) {
                $actual=$this->rows('SELECT quantity,reserved_quantity,physical_quantity FROM '.$table.' WHERE id_product=? AND id_product_attribute=? AND id_shop=1 AND id_shop_group=0'.$lock,$pair);
                $old=$stocks[$sku];$delta=$deltas[$sku];
                if (count($actual)!==1||(int)$actual[0]['quantity']!==(int)$old['quantity']+$delta['available']||(int)$actual[0]['reserved_quantity']!==(int)$old['reserved_quantity']+$delta['reserved']||(int)$actual[0]['physical_quantity']!==(int)$old['physical_quantity']+$delta['physical']) { throw new RuntimeException('Stock non relu après allocation : '.$sku); }
            }
            $this->db->exec('RELEASE SAVEPOINT '.$savepoint);
            return ['results'=>$results,'deltas'=>$deltas];
        } catch (Throwable $e) {
            // Never roll back unrelated work in the caller's surrounding transaction.
            try { $this->db->exec('ROLLBACK TO SAVEPOINT '.$savepoint);$this->db->exec('RELEASE SAVEPOINT '.$savepoint); }
            catch (Throwable $rollback) { throw new RuntimeException('Retour arrière du lot non confirmé ; transaction complète à annuler.',0,$e); }
            throw $e;
        }
    }
}
