<?php
require_once __DIR__.'/WatchysCartPolicy.php';
class WatchysCartService
{
    private $module;
    private $db;
    public function __construct($module){$this->module=$module;$this->db=Db::getInstance();}
    private function sql($sql){if(!$this->db->execute($sql)){throw new RuntimeException('Écriture panier non confirmée.');}}
    private function rows($sql){$r=$this->db->executeS($sql);if(!is_array($r)){throw new RuntimeException('Lecture panier indisponible.');}return $r;}
    public function support($modelId)
    {
        $r=$this->db->getRow('SELECT * FROM `'._DB_PREFIX_."watchys_model_support` WHERE model_id='".pSQL($modelId)."'");
        if(!$r){throw new RuntimeException('Produit technique du modèle non préparé.');}return $r;
    }
    public function containsSupport($cartId)
    {
        return $cartId>0&&(bool)$this->db->getValue('SELECT 1 FROM `'._DB_PREFIX_.'cart_product` cp JOIN `'._DB_PREFIX_.'watchys_model_support` s ON s.id_product=cp.id_product WHERE cp.id_cart='.(int)$cartId);
    }
    private function snapshot($row,$cartId)
    {
        $id=(int)$row['id_customization'];
        $r=$this->db->getRow('SELECT wc.*,c.id_product AS native_product FROM `'._DB_PREFIX_.'watchys_configuration` wc JOIN `'._DB_PREFIX_.'customization` c ON c.id_customization=wc.id_customization WHERE wc.id_customization='.$id.' AND c.id_cart='.(int)$cartId);
        if(!$r||(int)$r['native_product']!==(int)$row['id_product']||(int)$r['id_product']!==(int)$row['id_product']){throw new InvalidArgumentException('Configuration du panier non reconnue. Supprimez cette ligne puis recommencez depuis le configurateur.');}
        $snapshot=json_decode($r['snapshot'],true,32,JSON_THROW_ON_ERROR);
        $support=$this->support($snapshot['quote']['modelId']);$product=new Product((int)$support['id_product'],false,null,1);
        if((int)$support['id_product']!==(int)$row['id_product']||!Validate::isLoadedObject($product)||$product->reference!==$support['reference']||(float)$product->price!==0.0||!$product->active||!$product->available_for_order){throw new InvalidArgumentException('Support de configuration modifié. Recréez la configuration après vérification.');}
        if((int)$snapshot['nativeCurrencyId']!==(int)Configuration::get('PS_CURRENCY_DEFAULT')){throw new InvalidArgumentException('Devise de base modifiée. Recréez la configuration.');}
        $nativePrice=$this->db->getValue('SELECT SUM(price) FROM `'._DB_PREFIX_.'customized_data` WHERE id_customization='.$id);
        if(abs((float)$nativePrice-(float)$snapshot['nativeUnitPrice'])>0.000001){throw new InvalidArgumentException('Prix de personnalisation modifié. Recréez la configuration.');}
        return $snapshot;
    }
    public function assertCart($cartId,$change=null)
    {
        $lines=[];$regular=[];$found=false;
        $supports=array_map('intval',array_column($this->rows('SELECT id_product FROM `'._DB_PREFIX_.'watchys_model_support`'),'id_product'));
        $rows=$this->rows('SELECT id_product,id_product_attribute,id_customization,SUM(quantity) AS quantity FROM `'._DB_PREFIX_.'cart_product` WHERE id_cart='.(int)$cartId.' GROUP BY id_product,id_product_attribute,id_customization');
        foreach($rows as $row){
            $qty=(int)$row['quantity'];
            if($change&&(int)$row['id_product']===$change['productId']&&(int)$row['id_product_attribute']===$change['combinationId']&&(int)$row['id_customization']===$change['customizationId']){
                $qty=WatchysCartPolicy::proposedQuantity($qty,$change['quantity'],$change['operator']);$found=true;
            }
            if(!$qty){continue;}
            if(in_array((int)$row['id_product'],$supports,true)){
                $snapshot=$this->snapshot($row,$cartId);
                $lines[]=['quantity'=>$qty,'components'=>$snapshot['quote']['components']];
            }else{$row['quantity']=$qty;$regular[]=$row;}
        }
        if($change&&!$found&&$change['operator']==='up'){
            if(in_array($change['productId'],$supports,true)){
                $snapshot=$this->snapshot(['id_product'=>$change['productId'],'id_customization'=>$change['customizationId']],$cartId);
                $lines[]=['quantity'=>$change['quantity'],'components'=>$snapshot['quote']['components']];
            }else{$regular[]=['id_product'=>$change['productId'],'id_product_attribute'=>$change['combinationId'],'quantity'=>$change['quantity']];}
        }
        $requirements=WatchysCartPolicy::requirements($lines);
        // Include component purchases made separately in the same native cart.
        foreach($regular as $row){$key=$row['id_product'].':'.$row['id_product_attribute'];if(isset($requirements[$key])){$requirements[$key]['required']+=(int)$row['quantity'];}}
        WatchysCartPolicy::assertAvailable($requirements,[$this->module,'componentStatus']);
    }
    private function insert($table,$row)
    {
        if(!$this->db->insert($table,$row)){throw new RuntimeException('Enregistrement de configuration impossible.');}return (int)$this->db->Insert_ID();
    }
    public function add($input)
    {
        if(!$this->module->isDev()){throw new RuntimeException('Ajout réservé au DEV.');}
        if(!Customization::isFeatureActive()){throw new RuntimeException('La personnalisation native est désactivée : contrôle nécessaire.');}
        $request=WatchysCartPolicy::request($input,$this->module->catalog());$q=$request['quote'];$context=Context::getContext();
        if(!Validate::isLoadedObject($context->currency)||$context->currency->iso_code!=='CHF'){throw new InvalidArgumentException('Pour ce test DEV, sélectionnez la devise CHF dans la boutique.');}
        $support=$this->support($q['modelId']);$product=new Product((int)$support['id_product'],false,null,1);
        if(!Validate::isLoadedObject($product)||!$product->active||!$product->available_for_order||(float)$product->price!==0.0||$product->reference!==$support['reference']||!$product->customizable){throw new RuntimeException('Produit technique modifié : contrôle nécessaire.');}
        if(!$context->cart||!(int)$context->cart->id){
            $cart=new Cart();$cart->id_shop=1;$cart->id_shop_group=(int)$context->shop->id_shop_group;$cart->id_lang=(int)$context->language->id;$cart->id_currency=(int)$context->currency->id;
            $cart->id_guest=(int)$context->cookie->id_guest;$cart->id_customer=(int)($context->customer->id??0);
            if($cart->id_customer){$cart->secure_key=$context->customer->secure_key;$cart->id_address_delivery=(int)Address::getFirstCustomerAddressId($cart->id_customer);$cart->id_address_invoice=$cart->id_address_delivery;}
            if(!$cart->add()){throw new RuntimeException('Création du panier impossible.');}$context->cart=$cart;$context->cookie->id_cart=(int)$cart->id;$context->cookie->write();
        }
        $cart=$context->cart;$cartId=(int)$cart->id;
        if((int)($cart->id_customer??0)>0&&(int)$cart->id_customer!==(int)($context->customer->id??0)){throw new InvalidArgumentException('Le panier ne correspond pas à votre session. Rechargez la page.');}
        if((int)$cart->id_shop!==1||$cart->OrderExists()||(int)$cart->id_currency!==(int)$context->currency->id){throw new InvalidArgumentException('Panier non modifiable. Rechargez la page.');}
        $this->sql('START TRANSACTION');
        try{
            // Serialise retries and additions to the same cart on the native connection.
            $locked=$this->rows('SELECT id_cart FROM `'._DB_PREFIX_.'cart` WHERE id_cart='.$cartId.' FOR UPDATE');if(count($locked)!==1){throw new RuntimeException('Panier absent.');}
            if($cart->OrderExists()){throw new InvalidArgumentException('Ce panier est déjà commandé.');}
            $prior=$this->db->getRow('SELECT * FROM `'._DB_PREFIX_."watchys_cart_request` WHERE id_cart=".$cartId." AND request_key='".pSQL($request['requestKey'])."'");
            if($prior){
                if(!hash_equals($prior['request_digest'],$request['digest'])){throw new InvalidArgumentException('Identifiant de requête déjà utilisé pour un autre choix.');}
                $this->sql('COMMIT');return ['customizationId'=>(int)$prior['id_customization'],'replayed'=>true];
            }
            $live=$this->module->quoteSelection($input);if(!$live['componentsAvailable']){throw new InvalidArgumentException('Certains composants sont indisponibles ou restent à valider.');}
            $baseCurrency=new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));
            $native=Tools::convertPriceFull($q['unitPriceExclTaxMinor']/100,$context->currency,$baseCurrency);
            $nativePrice=number_format($native,6,'.','');$weight=0.0;
            foreach($q['components'] as $piece){$weight+=(float)$this->db->getValue('SELECT weight FROM `'._DB_PREFIX_.'product` WHERE id_product='.(int)$piece['component']['productId'])*(int)$piece['component']['units'];}
            $custom=new Customization();$custom->id_cart=$cartId;$custom->id_product=(int)$support['id_product'];$custom->id_product_attribute=0;$custom->id_address_delivery=(int)$cart->id_address_delivery;$custom->quantity=0;$custom->quantity_refunded=0;$custom->quantity_returned=0;$custom->in_cart=0;
            if(!$custom->add()){throw new RuntimeException('Personnalisation native non créée.');}
            $fields=json_decode($support['fields_json'],true,32,JSON_THROW_ON_ERROR);
            $model=$this->module->catalog()->model($q['modelId']);$values=['model'=>$model['label'],'offer'=>$q['offerLabel']];
            foreach($q['components'] as $piece){if($piece['group']!=='movement'){$values[$piece['group']]=$piece['label'];}}
            foreach($fields as $key=>$fieldId){
                $this->insert('customized_data',['id_customization'=>(int)$custom->id,'type'=>Product::CUSTOMIZE_TEXTFIELD,'index'=>(int)$fieldId,'value'=>pSQL(mb_substr($values[$key],0,255)),'id_module'=>(int)$this->module->id,'price'=>$key==='offer'?$nativePrice:'0.000000','weight'=>$key==='offer'?number_format($weight,6,'.',''):'0.000000']);
            }
            $snapshot=['schemaVersion'=>1,'quote'=>$q,'selection'=>$request['selection'],'modelLabel'=>$model['label'],'nativeUnitPrice'=>$nativePrice,'nativeCurrencyId'=>(int)$baseCurrency->id,'createdAtUtc'=>gmdate('c')];
            $this->insert('watchys_configuration',['id_customization'=>(int)$custom->id,'id_cart'=>$cartId,'id_product'=>(int)$support['id_product'],'snapshot'=>pSQL(json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),'date_add'=>date('Y-m-d H:i:s')]);
            $change=['productId'=>(int)$support['id_product'],'combinationId'=>0,'customizationId'=>(int)$custom->id,'quantity'=>$q['quantity'],'operator'=>'up'];
            $this->assertCart($cartId,$change);
            if($cart->updateQty($q['quantity'],(int)$support['id_product'],0,(int)$custom->id,'up',(int)$cart->id_address_delivery,$context->shop,false)!==true){throw new RuntimeException('Ajout natif au panier non confirmé.');}
            $line=$this->db->getRow('SELECT quantity FROM `'._DB_PREFIX_.'cart_product` WHERE id_cart='.$cartId.' AND id_product='.(int)$support['id_product'].' AND id_customization='.(int)$custom->id);
            if(!$line||(int)$line['quantity']!==$q['quantity']){throw new RuntimeException('Ligne panier non relue.');}
            $this->insert('watchys_cart_request',['id_cart'=>$cartId,'request_key'=>$request['requestKey'],'request_digest'=>$request['digest'],'id_customization'=>(int)$custom->id,'date_add'=>date('Y-m-d H:i:s')]);
            $this->sql('COMMIT');return ['customizationId'=>(int)$custom->id,'replayed'=>false];
        }catch(Throwable $e){$this->db->execute('ROLLBACK');throw $e;}
    }
}
