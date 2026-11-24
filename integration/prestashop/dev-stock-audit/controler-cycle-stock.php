<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);set_time_limit(120);
echo "========== DÉBUT — AUDIT CYCLE DE STOCK WATCHYS DEV ==========\n";
register_shutdown_function(static function(){echo "========== FIN — AUDIT CYCLE DE STOCK WATCHYS DEV ==========\n";});
$db=null;
try{
 if(count($argv)!==1){throw new RuntimeException('Ce contrôle ne prend aucun argument.');}
 echo "PLAN — Lire statuts de commande, réglages de stock, composants et accroches DEV. Aucun client ni détail de commande affiché.\nAucune écriture, aucune installation, aucun déblocage de commande ou paiement. Aucun accès PROD.\n";
 $tty=fopen('/dev/tty','r');if(!$tty){throw new RuntimeException('Terminal interactif requis.');}echo 'Tape OUI pour lancer ce contrôle en lecture seule : ';$answer=fgets($tty);fclose($tty);if(trim((string)$answer)!=='OUI'){echo "ANNULÉ — Aucune modification.\n";exit;}
 $home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');if(!$site||is_link($home.'/sites/dev.watchys.ch')||$site===realpath($home.'/web')){throw new RuntimeException('Dossier DEV non confirmé.');}
 $cfg=require $site.'/app/config/parameters.php';$p=$cfg['parameters'];$prefix=$p['database_prefix'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new RuntimeException('Préfixe invalide.');}$port=empty($p['database_port'])?'':';port='.(int)$p['database_port'];
 $db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($p,$cfg);
 $rows=static function($sql,$args=[])use($db){$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);};
 $clean=static function($v){return preg_replace('/[\x00-\x1F\x7F]+/u',' ',(string)$v);};
 $db->exec('START TRANSACTION READ ONLY');$urls=$rows('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1');
 if(!$urls||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1){throw new RuntimeException('Boutique unique non confirmée.');}foreach($urls as $url){if((int)$url['id_shop']!==1||$url['domain']!=='dev.watchys.ch'||$url['domain_ssl']!=='dev.watchys.ch'){throw new RuntimeException('Base DEV non confirmée.');}}
 echo 'DEV confirmé — PHP '.PHP_VERSION." — transaction MySQL en lecture seule.\n";
 $settings=['PS_VERSION_DB','PS_STOCK_MANAGEMENT','PS_ADVANCED_STOCK_MANAGEMENT','PS_ORDER_OUT_OF_STOCK','PS_PACK_STOCK_TYPE','PS_CURRENCY_DEFAULT','PS_OS_PAYMENT','PS_OS_WS_PAYMENT','PS_OS_BANKWIRE','PS_OS_CHEQUE','PS_OS_CANCELED','PS_OS_ERROR','PS_OS_REFUND','PS_OS_SHIPPING','PS_OS_DELIVERED','PS_OS_OUTOFSTOCK_PAID','PS_OS_OUTOFSTOCK_UNPAID','PS_ENABLE_BACKORDER_STATUS','PS_STOCK_CUSTOMER_ORDER_REASON','PS_STOCK_CUSTOMER_ORDER_CANCEL_REASON','WATCHYS_CART_DEV_READY'];
 $config=$rows('SELECT name,value,id_shop,id_shop_group FROM `'.$prefix.'configuration` WHERE name IN ('.implode(',',array_fill(0,count($settings),'?')).') ORDER BY name,id_shop_group,id_shop',$settings);
 echo "\nRÉGLAGES — toutes les portées ; les valeurs boutique peuvent remplacer les valeurs globales\n";
 foreach($config as $r){echo $clean($r['name']).'='.$clean($r['value']).' | boutique='.($r['id_shop']??'global').' | groupe='.($r['id_shop_group']??'global')."\n";}
 echo "\nSTATUTS — ID | nom FR | payé | expédié | livraison | validé/logable | facture | supprimé | module\n";
 $states=$rows('SELECT os.id_order_state,osl.name,os.paid,os.shipped,os.delivery,os.logable,os.invoice,os.deleted,os.module_name FROM `'.$prefix.'order_state` os LEFT JOIN `'.$prefix.'order_state_lang` osl ON osl.id_order_state=os.id_order_state AND osl.id_lang=1 ORDER BY os.id_order_state');
 foreach($states as $r){echo implode(' | ',array_map($clean,$r))."\n";}
 echo "\nMOTIFS DE MOUVEMENT — ID | signe | supprimé | nom FR\n";
 foreach($rows('SELECT r.id_stock_mvt_reason,r.sign,r.deleted,l.name FROM `'.$prefix.'stock_mvt_reason` r LEFT JOIN `'.$prefix.'stock_mvt_reason_lang` l ON l.id_stock_mvt_reason=r.id_stock_mvt_reason AND l.id_lang=1 ORDER BY r.id_stock_mvt_reason') as $r){echo implode(' | ',array_map($clean,$r))."\n";}
 $module=$rows('SELECT id_module,version,active FROM `'.$prefix."module` WHERE name='watchysconfigurator'");
 if(count($module)!==1||(int)$module[0]['active']!==1){throw new RuntimeException('Module Watchys actif non confirmé.');}
 echo "\nMODULE WATCHYS — version=".$clean($module[0]['version']).' | ID='.(int)$module[0]['id_module']."\n";
 echo "\nACCROCHES — modules actifs liés à la boutique 1\n";
 $hookNames=['actionObjectOrderAddBefore','actionObjectOrderAddAfter','actionValidateOrder','actionValidateOrderAfter','actionOrderStatusUpdate','actionOrderStatusPostUpdate','actionOrderHistoryAddAfter','actionProductCancel','actionUpdateQuantity','actionObjectStockAvailableUpdateBefore','actionObjectStockAvailableUpdateAfter','actionPresentCart','actionFrontControllerInitBefore'];
 foreach($hookNames as $name){
  $list=$rows('SELECT m.name,m.version,hm.position,h.active FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook AND hm.id_shop=1 JOIN `'.$prefix.'module` m ON m.id_module=hm.id_module AND m.active=1 WHERE LOWER(h.name)=LOWER(?) ORDER BY hm.position,m.name',[$name]);
  echo $name.' : '.($list?implode(' ; ',array_map(static function($r)use($clean){return $clean($r['name']).' '.$clean($r['version']).' position='.(int)$r['position'].' hook_actif='.(int)$r['active'];},$list)):'aucun module actif lié')."\n";
 }
 echo "\nMODULES DE PAIEMENT — candidats par classe principale, code non exécuté\n";
 foreach($rows('SELECT m.name,m.version FROM `'.$prefix.'module` m JOIN `'.$prefix.'module_shop` ms ON ms.id_module=m.id_module AND ms.id_shop=1 WHERE m.active=1 ORDER BY m.name') as $r){
  $name=$r['name'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name)){continue;}$path=$site.'/modules/'.$name.'/'.$name.'.php';
  if(is_file($path)&&!is_link($path)){$code=file_get_contents($path,false,null,0,150000);if(preg_match('/\bclass\s+[A-Za-z0-9_]+\s+extends\s+\\\\?PaymentModule\b/i',$code)){echo $clean($name).' | version='.$clean($r['version'])."\n";}}
 }
 echo "Ce repérage ne confirme ni les identifiants de paiement ni leur mode test/réel. Aucun secret de passerelle lu ou affiché.\n";
 $catalogPath=$site.'/modules/watchysconfigurator/config/catalog.json';if(!is_file($catalogPath)||is_link($catalogPath)){throw new RuntimeException('Catalogue installé absent.');}$catalog=json_decode(file_get_contents($catalogPath),true,512,JSON_THROW_ON_ERROR);$ids=[];
 foreach(($catalog['models']??[]) as $model){$components=[$model['movement']['component']??null];foreach(($model['options']??[]) as $options){foreach($options as $option){$components[]=$option['component']??null;}}foreach($components as $component){if(!is_int($component['productId']??null)||$component['productId']<1||($component['combinationId']??null)!==0){throw new RuntimeException('Correspondance composant inattendue.');}$ids[]=$component['productId'];}}
 $ids=array_values(array_unique($ids));sort($ids);if(!$ids||count($ids)>200){throw new RuntimeException('Liste de composants non confirmée.');}
 echo "\nCOMPOSANTS — stock disponible/réservé/physique et vente hors stock\n";
 $stocks=$rows('SELECT sa.id_product,sa.id_product_attribute,sa.id_shop,sa.id_shop_group,sa.quantity,sa.reserved_quantity,sa.physical_quantity,sa.depends_on_stock,sa.out_of_stock,ps.active,ps.available_for_order FROM `'.$prefix.'stock_available` sa LEFT JOIN `'.$prefix.'product_shop` ps ON ps.id_product=sa.id_product AND ps.id_shop=1 WHERE sa.id_product IN ('.implode(',',$ids).') ORDER BY sa.id_product,sa.id_product_attribute,sa.id_shop,sa.id_shop_group');
 foreach($stocks as $s){echo 'article='.$s['id_product'].' | déclinaison='.$s['id_product_attribute'].' | boutique='.$s['id_shop'].' | groupe='.$s['id_shop_group'].' | disponible='.$s['quantity'].' | réservé='.$s['reserved_quantity'].' | physique='.$s['physical_quantity'].' | dépend_stock='.$s['depends_on_stock'].' | hors_stock='.$s['out_of_stock'].' | actif='.$s['active'].' | commandable='.$s['available_for_order']."\n";}
 echo "\nSTRUCTURES — tables nécessaires au cycle transactionnel\n";
 foreach(['orders','order_detail','order_history','order_state','stock_available','stock_mvt','stock_mvt_reason','customization','customized_data','cart','cart_product','watchys_model_support','watchys_configuration','watchys_cart_request','watchys_stock_ledger','watchys_order_component'] as $table){$r=$rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.$table]);echo $table.' : '.($r?$clean($r[0]['ENGINE']):'absente')."\n";}
 foreach(['order_detail'=>['id_customization','product_id','product_attribute_id','product_quantity','product_quantity_refunded','product_quantity_return'],'stock_available'=>['quantity','reserved_quantity','physical_quantity']] as $table=>$expected){$columns=array_column($rows('SHOW COLUMNS FROM `'.$prefix.$table.'`'),'Field');echo $table.' — colonnes attendues absentes : '.(implode(',',array_diff($expected,$columns))?:'aucune')."\n";}
 $support=$rows('SELECT model_id,id_product,reference FROM `'.$prefix.'watchys_model_support` ORDER BY model_id');
 echo "\nSUPPORTS TECHNIQUES\n";foreach($support as $s){echo implode(' | ',array_map($clean,$s))."\n";}
 foreach($rows('SELECT sa.id_product,sa.quantity,sa.reserved_quantity,sa.physical_quantity,sa.out_of_stock FROM `'.$prefix.'stock_available` sa JOIN `'.$prefix.'watchys_model_support` s ON s.id_product=sa.id_product WHERE sa.id_shop=1 AND sa.id_product_attribute=0 ORDER BY sa.id_product') as $r){echo 'Stock du support technique (hors inventaire composants) : '.implode(' | ',array_map($clean,$r))."\n";}
 $count=(int)$db->query('SELECT COUNT(DISTINCT od.id_order) FROM `'.$prefix.'order_detail` od JOIN `'.$prefix.'watchys_model_support` s ON s.id_product=od.product_id')->fetchColumn();echo 'Commandes contenant un support Watchys : '.$count." (aucun détail client affiché).\n";
 echo "\nSIGNATURES DU CODE NATIF — aucun code chargé\n";
 foreach(['classes/PaymentModule.php','classes/order/OrderDetail.php','classes/order/OrderHistory.php','src/Core/Stock/StockManager.php','src/Adapter/StockManager.php','modules/watchysconfigurator/watchysconfigurator.php'] as $relative){$path=$site.'/'.$relative;echo $relative.' : '.(is_file($path)&&!is_link($path)?hash_file('sha256',$path):'absent ou lien')."\n";}
 $db->exec('COMMIT');$db=null;
 echo "\nCONTRÔLE TERMINÉ — Aucune donnée ni fichier modifié. Aucun bootstrap PrestaShop, aucun module exécuté, aucune commande créée. Aucun accès PROD.\n";
}catch(Throwable $e){if($db&&$db->inTransaction()){try{$db->rollBack();}catch(Throwable $ignored){}}echo $e instanceof PDOException?"ARRÊT — Erreur MySQL (identifiants masqués).\n":'ARRÊT — '.$e->getMessage()."\n";echo "Contrôle incomplet. Aucune modification effectuée.\n";exit(1);}
