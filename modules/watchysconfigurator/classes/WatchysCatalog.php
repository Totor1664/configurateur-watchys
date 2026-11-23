<?php
/** Catalog/price validation independent of PrestaShop persistence. No cart or stock writes. */
class WatchysCatalog
{
    private $catalog;
    public function __construct($path)
    {
        $this->catalog = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->validate();
    }
    private function integer($value, $min=0, $max=100000000)
    {
        if (!is_int($value) || $value<$min || $value>$max) { throw new RuntimeException('Entier de catalogue invalide.'); }
    }
    private function component($component)
    {
        if (!is_array($component)) { throw new RuntimeException('Composant invalide.'); }
        $this->integer($component['productId']??null,1);$this->integer($component['combinationId']??null,0);$this->integer($component['units']??null,1,100);
        if (isset($component['expectedReference']) && (!is_string($component['expectedReference']) || $component['expectedReference']==='')) { throw new RuntimeException('Référence invalide.'); }
    }
    private function identifier($value)
    {
        if (!is_string($value) || !preg_match('/^[a-z0-9][a-z0-9-]{0,100}$/',$value)) { throw new RuntimeException('Identifiant de catalogue invalide.'); }
    }
    private function vector($v,$length)
    {
        if(!is_array($v)||!array_is_list($v)||count($v)!==$length){throw new RuntimeException('Coordonnées invalides.');}
        foreach($v as $n){if((!is_int($n)&&!is_float($n))||!is_finite((float)$n)||$n<0||$n>10000){throw new RuntimeException('Coordonnée hors limites.');}}
    }
    private function geometry($p,$group)
    {
        if(!is_array($p)){throw new RuntimeException('Géométrie absente.');}$this->vector($p['sourceSize']??null,2);
        foreach($p['sourceSize'] as $n){$this->integer($n,1,5000);}
        if($group==='strap'){$boxes=$p['parts']??null;if(!is_array($boxes)||count($boxes)!==2){throw new RuntimeException('Deux parties de bracelet attendues.');}}
        else{
            $this->vector($p['anchor']??null,2);
            if($group==='hands'){
                $scale=$p['scaleToDial']??null;if((!is_int($scale)&&!is_float($scale))||$scale<=0||$scale>2){throw new RuntimeException('Échelle des aiguilles invalide.');}
                if(!is_array($p['parts']??null)||count($p['parts'])!==3){throw new RuntimeException('Trois aiguilles auxiliaires attendues.');}
                $boxes=[];foreach($p['parts'] as $part){$this->vector($part['anchor']??null,2);$boxes[]=$part['box']??null;}
            }else{
                $diameter=$p[$group==='case'?'openingDiameter':'diameter']??null;
                if((!is_int($diameter)&&!is_float($diameter))||$diameter<=0||$diameter>5000){throw new RuntimeException('Diamètre invalide.');}$boxes=[];
                if($group==='dial'){if(!is_array($p['subdials']??null)||count($p['subdials'])!==3){throw new RuntimeException('Axes auxiliaires absents.');}foreach($p['subdials'] as $v){$this->vector($v,2);}}
            }
        }
        foreach($boxes as $b){$this->vector($b,4);if($b[2]<=0||$b[3]<=0||$b[0]+$b[2]>$p['sourceSize'][0]||$b[1]+$b[3]>$p['sourceSize'][1]){throw new RuntimeException('Découpe hors PNG.');}}
    }
    private function validate()
    {
        $c=$this->catalog;
        if (!is_array($c) || ($c['schemaVersion']??null)!==1 || ($c['currency']??'')!=='CHF' || ($c['priceBasis']??'')!=='tax-excluded' || ($c['minorUnitsPerCurrency']??null)!==100 || !is_array($c['models']??null) || !$c['models']) { throw new RuntimeException('Catalogue Watchys invalide.'); }
        $ids=[];
        foreach($c['models'] as $m) {
            $this->identifier($m['id']??null);if(isset($ids[$m['id']])) { throw new RuntimeException('Modèle dupliqué.'); }$ids[$m['id']]=true;
            $this->vector($m['preview']['center']??null,2);$this->integer($m['preview']['width']??null,1,5000);$this->integer($m['preview']['height']??null,1,5000);$this->integer($m['preview']['openingDiameter']??null,1,5000);$this->integer($m['preview']['strapWidth']??null,1,5000);$this->integer($m['preview']['dialDiameter']??null,1,5000);
            $this->integer($m['kitPriceExclTaxMinor']??null);$this->integer($m['assemblySupplementExclTaxMinor']??null);
            $this->component($m['movement']['component']??null);$this->integer($m['movement']['supplementExclTaxMinor']??null);
            $groups=array_keys($m['options']??[]);sort($groups);if($groups!==['case','dial','hands','strap']) { throw new RuntimeException('Groupes de composants invalides.'); }
            foreach($m['options'] as $group=>$options) {
                if(!is_array($options) || !$options) { throw new RuntimeException('Groupe vide.'); }
                foreach($options as $id=>$option) {
                    $this->identifier($id);$this->component($option['component']??null);$this->integer($option['supplementExclTaxMinor']??0);
                    if(!is_string($option['label']??null) || $option['label']==='') { throw new RuntimeException('Libellé absent.'); }
                    $v=$option['visual']??[];
                    if(!in_array($v['package']??'', ['watchys-7750-variantes','watchys-carrures-7750-vues'],true) || !is_string($v['file']??null) || !str_starts_with($v['file'],'images/') || str_contains($v['file'],'..') || !str_ends_with($v['file'],'.png') || !preg_match('/^[a-f0-9]{64}$/',$v['sha256']??'')) { throw new RuntimeException('Actif PNG invalide.'); }
                    $this->geometry($option['preview']??null,$group);
                }
            }
            if(!is_array($m['offers']??null) || !$m['offers']) {throw new RuntimeException('Formules absentes.');}
            foreach($m['offers'] as $id=>$offer) {
                $this->identifier($id);
                if(!is_bool($offer['includeMovement']??null) || !is_bool($offer['workshopAssembly']??null) || !is_string($offer['label']??null) || ($offer['workshopAssembly']&&!$offer['includeMovement'])) {throw new RuntimeException('Formule invalide.');}
            }
        }
    }
    public function models() { return $this->catalog['models']; }
    public function model($id)
    {
        foreach ($this->models() as $model) { if ($model['id'] === $id) { return $model; } }
        throw new InvalidArgumentException('Modèle inconnu.');
    }
    public function quote($modelId, $selection, $offerId, $quantity = 1)
    {
        $model = $this->model($modelId);
        if (!is_array($selection) || !is_int($quantity) || $quantity < 1 || $quantity > 100) { throw new InvalidArgumentException('Sélection ou quantité invalide.'); }
        $keys = array_keys($selection); $expected = array_keys($model['options']); sort($keys); sort($expected);
        if ($keys !== $expected || !is_string($offerId) || !isset($model['offers'][$offerId])) { throw new InvalidArgumentException('Formule ou composants invalides.'); }
        $offer = $model['offers'][$offerId];
        if ($offer['workshopAssembly'] && !$offer['includeMovement']) { throw new RuntimeException('Formule atelier incohérente.'); }
        $amount = $model['kitPriceExclTaxMinor']; $components = [];
        foreach ($model['options'] as $group => $options) {
            $id = $selection[$group];
            if (!is_string($id) || !isset($options[$id])) { throw new InvalidArgumentException('Variante inconnue : '.$group); }
            $option = $options[$id]; $amount += $option['supplementExclTaxMinor'] ?? 0;
            $components[] = ['group'=>$group,'optionId'=>$id,'label'=>$option['label'],'component'=>$option['component']];
        }
        if ($offer['includeMovement']) {
            $amount += $model['movement']['supplementExclTaxMinor'];
            $components[] = ['group'=>'movement','optionId'=>$model['movement']['id'],'label'=>$model['movement']['label'],'component'=>$model['movement']['component']];
        }
        if ($offer['workshopAssembly']) { $amount += $model['assemblySupplementExclTaxMinor']; }
        if (!is_int($amount) || $amount < 0) { throw new RuntimeException('Prix de référence invalide.'); }
        return ['modelId'=>$modelId,'offerId'=>$offerId,'offerLabel'=>$offer['label'],'quantity'=>$quantity,'unitPriceExclTaxMinor'=>$amount,'totalExclTaxMinor'=>$amount*$quantity,'currency'=>'CHF','taxIncluded'=>false,'components'=>$components];
    }
}
