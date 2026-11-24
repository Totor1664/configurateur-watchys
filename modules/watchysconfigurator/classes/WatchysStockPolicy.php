<?php
/** Stock arithmetic only. No PrestaShop bootstrap, database, payment or inventory write. */
class WatchysStockPolicy
{
    public const RESERVE=[1,2,3,9,10,11,12,13,14,15,16,17,19,20,22,23,24,25,26,27];
    public const SHIP=[4,5];
    public const RELEASE=[6,8];
    public const FINANCIAL_ONLY=[7,18,21];
    private static function units($n)
    {
        if (!is_int($n)||$n<0||$n>1000000) { throw new InvalidArgumentException('Quantité de stock invalide.'); }
    }
    public static function initial($required)
    {
        self::units($required); if ($required===0) { throw new InvalidArgumentException('Nomenclature vide.'); }
        return ['required_units'=>$required,'reserved_units'=>0,'shipped_units'=>0,'returned_units'=>0,'released'=>false];
    }
    public static function validate($state)
    {
        if (!is_array($state)||array_diff(array_keys(self::initial(1)),array_keys($state))) { throw new InvalidArgumentException('État de stock incomplet.'); }
        foreach (['required_units','reserved_units','shipped_units','returned_units'] as $key) { self::units($state[$key]); }
        if ($state['required_units']===0||!is_bool($state['released'])||$state['reserved_units']+$state['shipped_units']>$state['required_units']||$state['returned_units']>$state['shipped_units']||($state['released']&&$state['reserved_units']!==0)) { throw new InvalidArgumentException('Compteurs de stock incohérents.'); }
    }
    public static function event($event)
    {
        if (!is_array($event)||!in_array($event['kind']??null,['status','return'],true)) { throw new InvalidArgumentException('Événement de stock inconnu.'); }
        if ($event['kind']==='status') {
            $status=$event['status_id']??null;
            if (!is_int($status)||!in_array($status,array_merge(self::RESERVE,self::SHIP,self::RELEASE,self::FINANCIAL_ONLY),true)||!is_bool($event['allow_reactivation']??false)) { throw new InvalidArgumentException('Statut non prévu ou réactivation invalide.'); }
            return ['kind'=>'status','status_id'=>$status,'allow_reactivation'=>$event['allow_reactivation']??false];
        }
        self::units($event['returned_units_total']??null);
        return ['kind'=>'return','returned_units_total'=>$event['returned_units_total']];
    }
    public static function plan($state,$input)
    {
        self::validate($state); $event=self::event($input); $next=$state;
        if ($event['kind']==='return') {
            if ($event['returned_units_total']>$state['shipped_units']) { throw new InvalidArgumentException('Retour supérieur aux pièces expédiées.'); }
            // Cumulative receipts make repeated and older receipt events harmless.
            $next['returned_units']=max($state['returned_units'],$event['returned_units_total']);
        } elseif (in_array($event['status_id'],self::RELEASE,true)) {
            $next['reserved_units']=0; $next['released']=true;
        } elseif (!in_array($event['status_id'],self::FINANCIAL_ONLY,true)) {
            if ($state['released']&&!$event['allow_reactivation']) { throw new InvalidArgumentException('Réactivation explicite requise après annulation ou erreur.'); }
            $next['released']=false;
            if (in_array($event['status_id'],self::SHIP,true)) { $next['reserved_units']=0; $next['shipped_units']=$state['required_units']; }
            else { $next['reserved_units']=$state['required_units']-$state['shipped_units']; }
        }
        self::validate($next);
        $reserved=$next['reserved_units']-$state['reserved_units'];
        $shipped=$next['shipped_units']-$state['shipped_units'];
        $returned=$next['returned_units']-$state['returned_units'];
        $physical=-$shipped+$returned; $available=$physical-$reserved;
        return ['before'=>$state,'after'=>$next,'delta_available'=>$available,'delta_reserved'=>$reserved,'delta_physical'=>$physical,'additional_required'=>max(0,-$available),'event'=>$event];
    }
}
