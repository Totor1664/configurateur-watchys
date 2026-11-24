<?php
/** Typed bounded payloads. Never PHP-unserialize objects from the database. */
class WatchysEffectCodec
{
 public const CLASSES=['Order','OrderHistory','OrderState','Cart','Customer','Currency','Product','Address','OrderInvoice','Employee','Shop','OrderDetail','OrderCarrier','OrderPayment','OrderCartRule','OrderSlip','StockAvailable','Carrier','SpecificPrice','Customization','StockMvt','OrderReturn'];
 public static function encode($value)
 {
  $nodes=0;$walk=static function($v,$depth=0)use(&$walk,&$nodes){
   if($depth>20||++$nodes>10000){throw new InvalidArgumentException('Effet natif trop complexe.');}
   if($v===null){return ['n',null];}if(is_bool($v)){return ['b',$v];}if(is_int($v)){return ['i',$v];}if(is_float($v)&&is_finite($v)){return ['f',$v];}if(is_string($v)){return ['s',base64_encode($v)];}
   if(is_array($v)){$out=[];foreach($v as $k=>$x){$out[]=[$walk($k,$depth+1),$walk($x,$depth+1)];}return ['a',$out];}
   if(is_object($v)&&in_array(get_class($v),self::CLASSES,true)&&(int)($v->id??0)>0){return ['o',[get_class($v),(int)$v->id,(int)($v->id_lang??0),(int)($v->id_shop??0)]];}
   throw new InvalidArgumentException('Type d’effet natif non pris en charge.');
  };
  $json=json_encode($walk($value),JSON_THROW_ON_ERROR);if(strlen($json)>6000000){throw new InvalidArgumentException('Effet natif trop volumineux.');}return $json;
 }
 public static function decode($json,callable $factory)
 {
  if(!is_string($json)||strlen($json)>6000000){throw new InvalidArgumentException('Effet natif invalide.');}$nodes=0;
  $walk=static function($v,$depth=0)use(&$walk,&$nodes,$factory){
   if($depth>20||++$nodes>10000||!is_array($v)||!array_is_list($v)||count($v)!==2){throw new InvalidArgumentException('Arbre d’effet invalide.');}[$t,$x]=$v;
   if($t==='n'&&$x===null){return null;}if($t==='b'&&is_bool($x)){return $x;}if($t==='i'&&is_int($x)){return $x;}if($t==='f'&&(is_int($x)||is_float($x))&&is_finite((float)$x)){return (float)$x;}
   if($t==='s'&&is_string($x)){$s=base64_decode($x,true);if($s===false){throw new InvalidArgumentException('Chaîne d’effet invalide.');}return $s;}
   if($t==='a'&&is_array($x)&&array_is_list($x)){$out=[];foreach($x as $pair){if(!is_array($pair)||count($pair)!==2||!array_is_list($pair)){throw new InvalidArgumentException('Paire d’effet invalide.');}$key=$walk($pair[0],$depth+1);if(!is_int($key)&&!is_string($key)){throw new InvalidArgumentException('Clé invalide.');}if(array_key_exists($key,$out)){throw new InvalidArgumentException('Clé d’effet dupliquée.');}$out[$key]=$walk($pair[1],$depth+1);}return $out;}
   if($t==='o'&&is_array($x)&&array_is_list($x)&&count($x)===4&&in_array($x[0],self::CLASSES,true)&&is_int($x[1])&&$x[1]>0&&$x[1]<=2147483647&&is_int($x[2])&&$x[2]>=0&&$x[2]<=2147483647&&is_int($x[3])&&$x[3]>=0&&$x[3]<=2147483647){return $factory($x[0],$x[1],$x[2],$x[3]);}
   throw new InvalidArgumentException('Nœud d’effet inconnu.');
  };return $walk(json_decode($json,true,64,JSON_THROW_ON_ERROR));
 }
}
