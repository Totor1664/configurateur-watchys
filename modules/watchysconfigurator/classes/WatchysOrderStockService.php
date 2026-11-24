<?php
require_once __DIR__.'/WatchysOrderNomenclature.php';
require_once __DIR__.'/WatchysStockAllocator.php';
/** Native order repository + allocator seam. No hook, route or automatic activation.
 * Caller must own the native PDO transaction and complete native order SQL first.
 * Not a replacement for the coordinated checkout boundary or its side-effect handling.
 */
class WatchysOrderStockService
{
    private $db;private $prefix;private $catalog;private $lock;private $gate;
    public function __construct(PDO $db,$prefix,$catalog,$gate=null)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||!in_array($db->getAttribute(PDO::ATTR_DRIVER_NAME),['mysql','sqlite'],true)){throw new InvalidArgumentException('Connexion de stock invalide.');}
        $this->db=$db;$this->prefix=$prefix;$this->catalog=$catalog;$this->gate=$gate;$this->lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    }
    public static function fromNative($module)
    {
        if(!defined('_PS_VERSION_')||_PS_VERSION_!=='8.2.7'||!defined('_DB_PREFIX_')||($module->name??null)!=='watchysconfigurator'||!$module->isDev()){throw new RuntimeException('Adaptateur réservé au DEV PrestaShop contrôlé.');}
        $link=Db::getInstance()->getLink();
        if(!$link instanceof PDO||$link->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||!$link->inTransaction()){throw new RuntimeException('Transaction PDO native requise ; aucune connexion parallèle créée.');}
        return new self($link,_DB_PREFIX_,$module->catalog(),class_exists('WatchysNativeRuntime',false)&&WatchysNativeRuntime::isActive()?WatchysNativeRuntime::gate():null);
    }
    private function rows($sql,$params=[]){$s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);}
    private function id($value){if(!is_int($value)||$value<1||$value>2147483647){throw new InvalidArgumentException('Identifiant natif invalide.');}return $value;}
    private function load($orderId)
    {
        $p=$this->prefix;$lock=$this->lock;
        $orders=$this->rows('SELECT id_order,id_cart,id_shop,current_state FROM `'.$p.'orders` WHERE id_order=?'.$lock,[$orderId]);
        if(count($orders)!==1||(int)$orders[0]['id_shop']!==1){throw new InvalidArgumentException('Commande DEV absente ou étrangère.');}$order=$orders[0];$cartId=(int)$order['id_cart'];
        $supportRows=$this->rows('SELECT model_id,id_product FROM `'.$p.'watchys_model_support` ORDER BY model_id'.$lock);$supports=[];
        foreach($supportRows as $support){$this->catalog->model($support['model_id']);$supports[$support['model_id']]=$this->id((int)$support['id_product']);}
        if(!$supports){throw new RuntimeException('Supports configurateur non préparés.');}
        $lines=$this->rows('SELECT id_order_detail,id_order,id_shop,product_id,product_attribute_id,id_customization,product_quantity FROM `'.$p.'order_detail` WHERE id_order=? ORDER BY id_order_detail'.$lock,[$orderId]);$records=[];
        foreach($lines as $line){
            if(!in_array((int)$line['product_id'],array_values($supports),true)){continue;}
            $custom=$this->id((int)$line['id_customization']);
            if(isset($records[$custom])){continue;}
            $configs=$this->rows('SELECT wc.*,c.id_cart AS native_cart,c.id_product AS native_product,c.id_product_attribute AS native_attribute,c.quantity AS native_quantity FROM `'.$p.'watchys_configuration` wc JOIN `'.$p.'customization` c ON c.id_customization=wc.id_customization WHERE wc.id_customization=?'.$lock,[$custom]);
            if(count($configs)!==1||(int)$configs[0]['native_cart']!==$cartId||(int)$configs[0]['native_product']!==(int)$line['product_id']||(int)$configs[0]['native_attribute']!==0){throw new InvalidArgumentException('Personnalisation native et commande non concordantes.');}
            $record=$configs[0];
            // Split orders share the customization: validate the TOTAL of native lines,
            // while consuming only this order's quantity. Never trust snapshot quote.quantity.
            $split=$this->rows('SELECT od.product_quantity,od.product_id,od.product_attribute_id FROM `'.$p.'order_detail` od JOIN `'.$p.'orders` o ON o.id_order=od.id_order WHERE o.id_cart=? AND od.id_customization=?'.$lock,[$cartId,$custom]);$total=0;
            foreach($split as $part){if((int)$part['product_id']!==(int)$line['product_id']||(int)$part['product_attribute_id']!==0||(int)$part['product_quantity']<1){throw new InvalidArgumentException('Commande fractionnée incohérente.');}$total+=(int)$part['product_quantity'];}
            if($total>(int)$record['native_quantity']||$total<1){throw new InvalidArgumentException('Quantité commandée supérieure à la personnalisation native.');}$records[$custom]=$record;
        }
        $requests=WatchysOrderNomenclature::build($orderId,$cartId,$lines,$records,$supports,$this->catalog);
        $prior=$this->rows('SELECT id_order_detail,component_product_id,component_attribute_id,required_units,nomenclature_hash FROM `'.$p.'watchys_order_component` WHERE id_order=? ORDER BY id_order_detail,component_product_id,component_attribute_id'.$lock,[$orderId]);
        if($prior){
            $expected=[];foreach($requests as $r){$id=$r['identity'];$expected[$id['id_order_detail'].':'.$id['component_product_id'].':'.$id['component_attribute_id']]=[(int)$r['required_units'],$r['nomenclature_hash']];}
            $actual=[];foreach($prior as $r){$actual[$r['id_order_detail'].':'.$r['component_product_id'].':'.$r['component_attribute_id']]=[(int)$r['required_units'],$r['nomenclature_hash']];}
            ksort($expected);ksort($actual);if($expected!==$actual){throw new InvalidArgumentException('Lignes ou nomenclature modifiées après allocation ; reprise explicite requise.');}
        }
        return ['order'=>$order,'requests'=>$requests];
    }
    public function applyHistory($orderId,$historyId,$allowReactivation=false)
    {
        $this->id($orderId);$this->id($historyId);if(!is_bool($allowReactivation)){throw new InvalidArgumentException('Réactivation serveur invalide.');}
        if(!$this->db->inTransaction()){throw new RuntimeException('Transaction du demandeur requise.');}
        $sp='watchys_order_'.bin2hex(random_bytes(6));$this->db->exec('SAVEPOINT '.$sp);
        try{
            $loaded=$this->load($orderId);$requests=$loaded['requests'];
            if(!$requests){$this->db->exec('RELEASE SAVEPOINT '.$sp);return ['ordinary'=>true,'ignoredStale'=>false,'results'=>[],'deltas'=>[]];}
            $history=$this->rows('SELECT id_order_history,id_order,id_order_state FROM `'.$this->prefix.'order_history` WHERE id_order_history=? AND id_order=?'.$this->lock,[$historyId,$orderId]);
            if(count($history)!==1){throw new InvalidArgumentException('Historique natif absent ou étranger.');}
            $latest=$this->rows('SELECT id_order_history,id_order_state FROM `'.$this->prefix.'order_history` WHERE id_order=? ORDER BY id_order_history DESC LIMIT 1'.$this->lock,[$orderId]);
            if(!$latest){throw new InvalidArgumentException('Dernier historique natif absent.');}
            if((int)$latest[0]['id_order_history']!==$historyId){$this->db->exec('RELEASE SAVEPOINT '.$sp);return ['ordinary'=>false,'ignoredStale'=>true,'results'=>[],'deltas'=>[]];}
            if((int)$history[0]['id_order_state']!==(int)$loaded['order']['current_state']){throw new InvalidArgumentException('Historique et état courant non concordants.');}
            $result=(new WatchysStockAllocator($this->db,$this->prefix,$this->gate))->apply($requests,'history:'.$historyId,['kind'=>'status','status_id'=>(int)$history[0]['id_order_state'],'allow_reactivation'=>$allowReactivation]);
            $this->db->exec('RELEASE SAVEPOINT '.$sp);return ['ordinary'=>false,'ignoredStale'=>false]+$result;
        }catch(Throwable $e){try{$this->db->exec('ROLLBACK TO SAVEPOINT '.$sp);$this->db->exec('RELEASE SAVEPOINT '.$sp);}catch(Throwable $rollback){throw new RuntimeException('Retour arrière du service non confirmé ; transaction complète à annuler.',0,$e);}throw $e;}
    }
}
