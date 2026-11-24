<?php
/** Explicit guard for a stock object captured BEFORE its quantity is changed.
 * Does not install an override, start/commit a transaction, or repair native reservations.
 * Caller must use the native shared PDO and coordinate all native SQL/side effects.
 */
class WatchysStockObjectGuard
{
    private $db;private $prefix;private $object;private $baseline;private $lock;
    public function __construct(PDO $db,$prefix,$object)
    {
        $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||!in_array($driver,['mysql','sqlite'],true)||!is_object($object)){throw new InvalidArgumentException('Objet stock non contrôlé.');}
        $this->db=$db;$this->prefix=$prefix;$this->object=$object;$this->lock=$driver==='mysql'?' FOR UPDATE':'';$this->baseline=$this->capture();
        if($this->baseline['id_shop']!==1||$this->baseline['id_shop_group']!==0||$this->baseline['id_product']===8867){throw new InvalidArgumentException('Périmètre stock non autorisé.');}
    }
    public static function fromNative($module,$object,$prefix)
    {
        if(!defined('_PS_VERSION_')||_PS_VERSION_!=='8.2.7'||!defined('_DB_PREFIX_')||($module->name??null)!=='watchysconfigurator'||!$module->isDev()||!$object instanceof StockAvailable){throw new RuntimeException('Objet natif DEV requis.');}
        $pdo=Db::getInstance()->getLink();$definition=ObjectModel::getDefinition($object);
        if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||!$pdo->inTransaction()||_DB_PREFIX_.$definition['table']!==$prefix.'stock_available'||$definition['primary']!=='id_stock_available'){throw new RuntimeException('PDO natif partagé, transaction et table exacte requis.');}
        return new self($pdo,$prefix,$object);
    }
    private function integer($v,$min=0)
    {
        if((!is_int($v)&&(!is_string($v)||!preg_match('/^(0|[1-9][0-9]*)$/D',$v)))||(float)$v<$min||(float)$v>2147483647){throw new InvalidArgumentException('Champ de stock invalide.');}return (int)$v;
    }
    private function capture()
    {
        $row=['id_stock_available'=>$this->integer($this->object->id??null,1),'quantity'=>$this->integer($this->object->quantity??null)];
        foreach(['id_product','id_product_attribute','id_shop','id_shop_group'] as $key){$row[$key]=$this->integer($this->object->$key??null,$key==='id_product'?1:0);}return $row;
    }
    private function read()
    {
        $s=$this->db->prepare('SELECT id_stock_available,id_product,id_product_attribute,id_shop,id_shop_group,quantity,depends_on_stock FROM `'.$this->prefix.'stock_available` WHERE id_stock_available=?'.$this->lock);$s->execute([$this->baseline['id_stock_available']]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)!==1||(int)$rows[0]['depends_on_stock']!==0){throw new RuntimeException('Stock unique manuel non confirmé.');}$r=[];foreach($this->baseline as $key=>$unused){$r[$key]=$this->integer($rows[0][$key],in_array($key,['id_stock_available','id_product'],true)?1:0);}return $r;
    }
    public function update(callable $nativeUpdate)
    {
        if(!$this->db->inTransaction()){throw new RuntimeException('Transaction native du demandeur requise.');}
        $next=$this->capture();$identity=$next;$identity['quantity']=$this->baseline['quantity'];if($identity!==$this->baseline){throw new InvalidArgumentException('Identité du stock modifiée.');}
        $sp='watchys_object_'.bin2hex(random_bytes(6));$this->db->exec('SAVEPOINT '.$sp);
        try{
            if($this->read()!==$this->baseline){throw new RuntimeException('Objet stock périmé : recharger avant écriture.');}
            $result=$nativeUpdate();
            if(!$this->db->inTransaction()){throw new RuntimeException('Le callback a fermé la transaction native.');}
            if($result===false||$this->read()!==$next){throw new RuntimeException('Écriture native non confirmée.');}
            $this->db->exec('RELEASE SAVEPOINT '.$sp);$this->baseline=$next;return $result;
        }catch(Throwable $e){try{$this->db->exec('ROLLBACK TO SAVEPOINT '.$sp);$this->db->exec('RELEASE SAVEPOINT '.$sp);}catch(Throwable $rollback){throw new RuntimeException('Retour arrière natif non confirmé ; transaction complète à annuler.',0,$e);}throw $e;}
    }
}
