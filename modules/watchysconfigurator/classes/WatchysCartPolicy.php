<?php
/** Pure cart rules: no browser-supplied price or component IDs are accepted. */
class WatchysCartPolicy
{
    public static function request($input, WatchysCatalog $catalog)
    {
        if(!is_array($input)||!is_string($input['requestKey']??null)||!preg_match('/^[a-f0-9]{32}$/D',$input['requestKey'])){throw new InvalidArgumentException('Identifiant de requête invalide.');}
        if(!is_string($input['modelId']??null)||!is_string($input['offerId']??null)){throw new InvalidArgumentException('Configuration invalide.');}
        if(array_key_exists('quantity',$input)&&!is_int($input['quantity'])){throw new InvalidArgumentException('Quantité entière attendue.');}
        $q=$catalog->quote($input['modelId'],$input['selection']??null,$input['offerId'],$input['quantity']??1);
        $selection=$input['selection'];ksort($selection);
        $normalized=['modelId'=>$q['modelId'],'selection'=>$selection,'offerId'=>$q['offerId'],'quantity'=>$q['quantity']];
        return ['quote'=>$q,'selection'=>$selection,'requestKey'=>$input['requestKey'],'digest'=>hash('sha256',json_encode($normalized,JSON_THROW_ON_ERROR))];
    }
    public static function requirements($lines)
    {
        $requirements=[];
        foreach($lines as $line){
            $quantity=$line['quantity']??null;
            if(!is_int($quantity)||$quantity<0||$quantity>100){throw new InvalidArgumentException('Quantité de configuration hors limites (100 maximum).');}
            foreach($line['components'] as $piece){
                $c=$piece['component'];$key=$c['productId'].':'.$c['combinationId'];
                if(!isset($requirements[$key])){$requirements[$key]=['component'=>$c,'label'=>$piece['label'],'required'=>0];}
                $requirements[$key]['required']+=$quantity*$c['units'];
            }
        }
        return $requirements;
    }
    public static function assertAvailable($requirements, callable $status)
    {
        foreach($requirements as $r){
            $s=$status($r['component']);
            if($r['required']>0&&(!$s['exists']||!$s['referenceMatches']||!$s['active']||!$s['orderable']||$s['quantity']<$r['required'])){
                throw new InvalidArgumentException('Composant indisponible ou quantité insuffisante : '.$r['label'].'.');
            }
        }
    }
    public static function proposedQuantity($current,$delta,$operator)
    {
        if(!is_int($delta)||$delta<1||!in_array($operator,['up','down'],true)){throw new InvalidArgumentException('Modification de quantité invalide.');}
        return max(0,$current+($operator==='up'?$delta:-$delta));
    }
}
