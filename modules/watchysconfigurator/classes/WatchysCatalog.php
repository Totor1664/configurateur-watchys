<?php
/** Catalog/price validation independent of PrestaShop persistence. No cart or stock writes. */
class WatchysCatalog
{
    private $catalog;
    public function __construct($path)
    {
        $this->catalog = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($this->catalog['schemaVersion'] ?? null) !== 1 || ($this->catalog['currency'] ?? '') !== 'CHF' || ($this->catalog['priceBasis'] ?? '') !== 'tax-excluded') {
            throw new RuntimeException('Catalogue Watchys invalide.');
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
