<?php
/** Builds stock requirements from trusted native order rows and stored configurations.
 * Does not create an order, reprice it, fetch client input or change native records.
 */
class WatchysOrderNomenclature
{
    private static function integer($value,$min=1,$max=2147483647)
    {
        if ((!is_int($value)&&(!is_string($value)||!preg_match('/^(0|[1-9][0-9]*)$/D',$value)))||(float)$value<$min||(float)$value>$max) { throw new InvalidArgumentException('Ligne native invalide.'); }
        return (int)$value;
    }
    public static function build($orderId,$cartId,$lines,$records,$supports,$catalog)
    {
        $orderId=self::integer($orderId);$cartId=self::integer($cartId);$requests=[];$seen=[];
        if (!is_array($lines)||!is_array($records)||!is_array($supports)) { throw new InvalidArgumentException('Nomenclature native invalide.'); }
        foreach ($lines as $line) {
            $pid=self::integer($line['product_id']??null);
            if (!in_array($pid,array_values($supports),true)) { continue; }
            $detail=self::integer($line['id_order_detail']??null);$custom=self::integer($line['id_customization']??null);$quantity=self::integer($line['product_quantity']??null,1,100);
            if (self::integer($line['id_order']??null)!==$orderId||self::integer($line['id_shop']??null)!==1||self::integer($line['product_attribute_id']??null,0)!==0||isset($seen[$detail])) { throw new InvalidArgumentException('Identité ou ligne de commande dupliquée.'); }
            $seen[$detail]=true;$record=$records[$custom]??null;
            if (!$record||self::integer($record['id_cart']??null)!==$cartId||self::integer($record['id_product']??null)!==$pid||self::integer($record['id_customization']??null)!==$custom) { throw new InvalidArgumentException('Personnalisation rattachée à un autre panier ou produit.'); }
            try {$snapshot=json_decode($record['snapshot'],true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('Instantané de configuration illisible.');}
            if (($snapshot['schemaVersion']??null)!==1||!is_string($snapshot['quote']['modelId']??null)||!is_string($snapshot['quote']['offerId']??null)) { throw new InvalidArgumentException('Instantané de configuration invalide.'); }
            $model=$catalog->model($snapshot['quote']['modelId']);
            if (($supports[$model['id']]??null)!==$pid) { throw new InvalidArgumentException('Modèle et support ne correspondent pas.'); }
            $quote=$catalog->quote($model['id'],$snapshot['selection']??null,$snapshot['quote']['offerId'],1);
            $normalize=static function($pieces) {
                if (!is_array($pieces)) { throw new InvalidArgumentException('Composants absents.'); } $out=[];
                foreach ($pieces as $piece) {
                    if (!is_string($piece['group']??null)||isset($out[$piece['group']])) { throw new InvalidArgumentException('Famille de composant dupliquée.'); }
                    $c=$piece['component']??null;
                    if (!is_array($c)||!is_int($c['productId']??null)||!is_int($c['combinationId']??null)||!is_int($c['units']??null)||$c['productId']<1||$c['productId']>2147483647||$c['combinationId']<0||$c['combinationId']>2147483647||$c['units']<1||$c['units']>100||$c['productId']===8867) { throw new InvalidArgumentException('Composant invalide.'); }
                    $out[$piece['group']]=['productId'=>$c['productId'],'combinationId'=>$c['combinationId'],'units'=>$c['units']];
                }ksort($out);return $out;
            };
            $pieces=$normalize($snapshot['quote']['components']??null);
            if ($pieces!==$normalize($quote['components'])) { throw new InvalidArgumentException('Nomenclature enregistrée différente du catalogue ; reprise historique requise.'); }
            // Use this native order line's quantity, not the original whole cart's snapshot quantity.
            $hash=hash('sha256',json_encode(['model'=>$model['id'],'offer'=>$quote['offerId'],'pieces'=>$pieces,'quantity'=>$quantity],JSON_THROW_ON_ERROR));$grouped=[];
            foreach ($pieces as $piece) {
                $key=$piece['productId'].':'.$piece['combinationId'];
                if (!isset($grouped[$key])) { $grouped[$key]=['identity'=>['id_order_detail'=>$detail,'component_product_id'=>$piece['productId'],'component_attribute_id'=>$piece['combinationId'],'id_order'=>$orderId,'id_customization'=>$custom,'id_shop'=>1],'required_units'=>0,'nomenclature_hash'=>$hash]; }
                $grouped[$key]['required_units']+=$quantity*$piece['units'];
            }
            foreach ($grouped as $request) { $requests[]=$request; }
        }
        return $requests;
    }
}
