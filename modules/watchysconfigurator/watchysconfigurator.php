<?php
if (!defined('_PS_VERSION_')) { exit; }
require_once __DIR__.'/classes/WatchysCatalog.php';
class Watchysconfigurator extends Module
{
    public function __construct()
    {
        $this->name='watchysconfigurator'; $this->tab='front_office_features'; $this->version='0.1.1';
        $this->author='Watchys'; $this->need_instance=0; $this->bootstrap=true;
        $this->ps_versions_compliancy=['min'=>'8.2.0','max'=>'8.99.99'];
        parent::__construct();
        $this->displayName='Configurateur Watchys';
        $this->description='Validation du configurateur 7750 sur DEV : variantes, images, stock et prix. Sans commande dans cette version.';
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
        return '<div class="panel"><h3>Configurateur Watchys — validation DEV</h3><p>23 variantes, stock des composants et tarifs CHF HT. Le panier sera raccordé après validation de cette étape.</p><a class="btn btn-primary" target="_blank" rel="noopener" href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Ouvrir le configurateur</a></div>';
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
        return $this->_path.'views/img/'.(int)$component['productId'].'-'.$view.'.png';
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
        return ['models'=>$models,'currency'=>'CHF','checkoutEnabled'=>false];
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
        $quote['componentsAvailable']=$available; $quote['checkoutEnabled']=false;
        return $quote;
    }
}
