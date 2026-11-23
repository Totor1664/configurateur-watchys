<?php
if (!defined('_PS_VERSION_')) { exit; }
require_once __DIR__.'/classes/WatchysCatalog.php';
class Watchysconfigurator extends Module
{
    public function __construct()
    {
        $this->name='watchysconfigurator'; $this->tab='front_office_features'; $this->version='0.2.1';
        $this->author='Watchys'; $this->need_instance=0; $this->bootstrap=true;
        $this->ps_versions_compliancy=['min'=>'8.2.0','max'=>'8.99.99'];
        parent::__construct();
        $this->displayName='Configurateur Watchys';
        $this->description='Configurateur DEV : aperçu validé, devis et vrai panier. Commande bloquée pendant les tests de stock.';
    }
    public function isDev()
    {
        return $this->context->shop->domain==='dev.watchys.ch' && $this->context->shop->domain_ssl==='dev.watchys.ch' && (int)$this->context->shop->id===1;
    }
    public function install() { return $this->isDev() && parent::install(); }
    protected function catalogFile() { return __DIR__.'/config/catalog.json'; }
    public function catalog() { return new WatchysCatalog($this->catalogFile()); }
    public function getContent()
    {
        if (!$this->isDev()) { return $this->displayError('Cette version est réservée au DEV.'); }
        $url=$this->context->link->getModuleLink($this->name,'configure',[],true);
        return '<div class="panel"><h3>Configurateur Watchys — validation DEV</h3><p>23 variantes, stock des composants et tarifs CHF HT. Le vrai panier DEV conserve chaque configuration. La commande et le paiement restent bloqués pendant les tests de stock.</p><a class="btn btn-primary" target="_blank" rel="noopener" href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Ouvrir le configurateur</a></div>';
    }
    public function componentStatus($component)
    {
        $id=(int)$component['productId']; $shop=(int)$this->context->shop->id;
        $row=Db::getInstance()->getRow('SELECT p.reference, ps.active, ps.available_for_order FROM `'._DB_PREFIX_.'product` p INNER JOIN `'._DB_PREFIX_.'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop='.$shop.' WHERE p.id_product='.$id);
        $exists=is_array($row) && !empty($row);
        $matches=$exists && (!isset($component['expectedReference']) || $row['reference']===$component['expectedReference']);
        $quantity=$matches ? (int)StockAvailable::getQuantityAvailableByProduct($id,(int)$component['combinationId'],$shop) : 0;
        $active=$matches && (bool)$row['active']; $orderable=$matches && (bool)$row['available_for_order'];
        return ['exists'=>$exists,'referenceMatches'=>$matches,'active'=>$active,'orderable'=>$orderable,'quantity'=>$quantity,'available'=>$matches && $active && $orderable && $quantity >= (int)$component['units']];
    }
    public function imageUrl($component, $view='front')
    {
        $relative='views/img/'.(int)$component['productId'].'-'.$view.'.png';
        $file=__DIR__.'/'.$relative;
        return $this->_path.$relative.(is_file($file)?'?v='.substr(hash_file('sha256',$file),0,16):'');
    }
    public function presentation()
    {
        $models=[];
        foreach($this->catalog()->models() as $model) {
            $groups=[];
            foreach($model['options'] as $group=>$options) {
                $items=[];
                foreach($options as $id=>$option) {
                    $items[]=['id'=>$id,'label'=>$option['label'],'productId'=>$option['component']['productId'],'image'=>$this->imageUrl($option['component']),'preview'=>$option['preview'],'status'=>$this->componentStatus($option['component'])];
                }
                $groups[]=['id'=>$group,'label'=>['dial'=>'Cadran','strap'=>'Bracelet cuir 20 mm','hands'=>'Aiguilles','case'=>'Carrure'][$group] ?? $group,'options'=>$items];
            }
            $models[]=['id'=>$model['id'],'label'=>$model['label'],'groups'=>$groups,'preview'=>$model['preview'],'offers'=>$model['offers'],'movementStatus'=>$this->componentStatus($model['movement']['component'])];
        }
        return ['models'=>$models,'currency'=>'CHF','cartEnabled'=>$this->cartReady(),'checkoutEnabled'=>false];
    }
    public function quoteSelection($input)
    {
        if (!is_array($input) || !is_string($input['modelId']??null) || !is_string($input['offerId']??null)) { throw new InvalidArgumentException('Configuration invalide.'); }
        $quote=$this->catalog()->quote($input['modelId'],$input['selection']??null,$input['offerId'],$input['quantity']??1);
        $requirements=[];
        foreach($quote['components'] as &$piece) {
            $piece['status']=$this->componentStatus($piece['component']);
            $key=$piece['component']['productId'].':'.$piece['component']['combinationId'];
            $requirements[$key]=($requirements[$key]??0)+(int)$piece['component']['units']*$quote['quantity'];
        }
        unset($piece);
        $available=true;
        foreach($quote['components'] as &$piece) {
            $key=$piece['component']['productId'].':'.$piece['component']['combinationId'];
            $piece['status']['requiredQuantity']=$requirements[$key];
            $piece['status']['available']=$piece['status']['available'] && $piece['status']['quantity'] >= $requirements[$key];
            $available=$available && $piece['status']['available'];
        }
        unset($piece);
        $quote['componentsAvailable']=$available; $quote['checkoutEnabled']=false; $quote['cartEnabled']=$this->cartReady();
        return $quote;
    }
    public function cartReady()
    {
        return $this->isDev()&&(bool)Configuration::get('WATCHYS_CART_DEV_READY');
    }
    public function cartService()
    {
        require_once __DIR__.'/classes/WatchysCartService.php';
        if(!$this->cartReady()){throw new RuntimeException('Le panier du configurateur n’est pas encore préparé.');}
        return new WatchysCartService($this);
    }
    public function hookDisplayCustomization($params)
    {
        return htmlspecialchars((string)($params['customization']['value']??''),ENT_QUOTES,'UTF-8');
    }
    public function hookActionCartUpdateQuantityBefore($params)
    {
        if(!$this->cartReady()||(int)($params['quantity']??0)<=0||($params['operator']??'')==='down'){return;}
        $cart=$params['cart'];$pid=(int)$params['product']->id;$service=$this->cartService();
        $support=(bool)Db::getInstance()->getValue('SELECT 1 FROM `'._DB_PREFIX_.'watchys_model_support` WHERE id_product='.$pid);
        if(!$support&&!$service->containsSupport((int)$cart->id)){return;}
        $service->assertCart((int)$cart->id,['productId'=>$pid,'combinationId'=>(int)($params['id_product_attribute']??0),'customizationId'=>(int)($params['id_customization']??0),'quantity'=>(int)$params['quantity'],'operator'=>(string)$params['operator']]);
    }
    public function hookActionObjectOrderAddBefore($params)
    {
        if(!$this->cartReady()){return;}
        if($this->cartService()->containsSupport((int)($params['object']->id_cart??0))){throw new PrestaShopException('DEV Watchys : configuration au panier uniquement. La validation de commande attend les tests du cycle de stock.');}
    }
    public function hookActionFrontControllerInitBefore($params)
    {
        if(!$this->cartReady()){return;}
        $controller=$params['controller']??null;
        $checkout=in_array($controller->php_self??'',['order','order-opc'],true)||(($controller->module??null) instanceof PaymentModule);
        if(!$checkout){return;}
        $cartId=!empty($this->context->cart->id)?(int)$this->context->cart->id:(int)($this->context->cookie->id_cart??0);
        if($this->cartService()->containsSupport($cartId)){
            Tools::redirect($this->context->link->getPageLink('cart',true,null,['action'=>'show','watchys_test'=>1]));
        }
    }
    public function hookDisplayShoppingCartFooter($params)
    {
        if(!$this->cartReady()||!$this->cartService()->containsSupport((int)($this->context->cart->id??0))){return '';}
        return '<div class="alert alert-info">Test DEV Watchys : votre configuration est enregistrée dans ce panier. La commande et le paiement sont bloqués pendant la validation du cycle de stock.</div>';
    }
}
