<?php
/** Notifications are held. Native getters/price filters stay synchronous. */
class WatchysHookPolicy
{
 public static function classify($name)
 {
  if(!is_string($name)){throw new InvalidArgumentException('Nom d’accroche invalide.');}
  if(str_starts_with($name,'action')&&str_ends_with($name,'Before')){return 'validation';}
  if(str_starts_with($name,'actionGet')||in_array($name,['actionProductPriceCalculation','actionPDFInvoiceRender','actionPresentCart'],true)||!str_starts_with($name,'action')){return 'synchronous';}
  return 'deferred';
 }
}
