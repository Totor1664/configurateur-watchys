<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);set_time_limit(120);
echo "========== DÉBUT — COMPATIBILITÉ RACCORDEMENT NATIF WATCHYS DEV ==========\n";
register_shutdown_function(static function(){echo "========== FIN — COMPATIBILITÉ RACCORDEMENT NATIF WATCHYS DEV ==========\n";});
$db=null;$save=null;$ddlStarted=false;
try{
 if(count($argv)>2||(isset($argv[1])&&$argv[1]!=='--verifier')){throw new RuntimeException('Utilisation : php controler.php [--verifier]');}
 $verify=($argv[1]??'')==='--verifier';$home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');
 if(!$site||is_link($home.'/sites/dev.watchys.ch')||$site===realpath($home.'/web')){throw new RuntimeException('Dossier DEV non confirmé.');}
 $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($manifest['files'] as $file=>$hash){if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$file)||str_contains($file,'..')||is_link(__DIR__.'/'.$file)||!is_file(__DIR__.'/'.$file)||hash_file('sha256',__DIR__.'/'.$file)!==$hash){throw new RuntimeException('Paquet absent ou modifié : '.$file);}}
 foreach(['module/classes/WatchysStockSchema.php','module/classes/WatchysStockJournal.php','module/classes/WatchysStockPolicy.php','module/classes/WatchysStockAllocator.php','module/classes/WatchysOrderNomenclature.php','module/classes/WatchysNativeStockCoordinator.php','module/classes/WatchysOrderStockService.php'] as $file){token_get_all(file_get_contents(__DIR__.'/'.$file),TOKEN_PARSE);}
 if(!function_exists('proc_open')){throw new RuntimeException('Sous-processus PHP requis.');}
 foreach(['stock-policy.php','order-nomenclature.php'] as $test){
 $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/module/tests/'.$test],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__);
 if(!is_resource($process)){throw new RuntimeException('Test isolé non démarré.');}fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0||str_contains($out.$err,'Fatal error:')){throw new RuntimeException('Test isolé échoué : '.$test);}echo $out;
 }
 require_once __DIR__.'/module/classes/WatchysStockSchema.php';require_once __DIR__.'/module/classes/WatchysStockJournal.php';
 $cfg=require $site.'/app/config/parameters.php';$p=$cfg['parameters'];$prefix=$p['database_prefix'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new RuntimeException('Préfixe invalide.');}$port=empty($p['database_port'])?'':';port='.(int)$p['database_port'];
 $db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);unset($cfg,$p);
 $rows=static function($sql,$args=[])use($db){$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);};
 $canon=static function($value)use(&$canon){return is_array($value)?array_map($canon,$value):($value===null?null:(string)$value);};
 $db->exec('START TRANSACTION READ ONLY');$urls=$rows('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1');
 if(!$urls||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1){throw new RuntimeException('Boutique unique non confirmée.');}foreach($urls as $u){if((int)$u['id_shop']!==1||$u['domain']!=='dev.watchys.ch'||$u['domain_ssl']!=='dev.watchys.ch'){throw new RuntimeException('Base DEV non confirmée.');}}
 $module=$rows('SELECT id_module,version,active FROM `'.$prefix."module` WHERE name='watchysconfigurator'");if(count($module)!==1||$module[0]['version']!=='0.2.2'||(int)$module[0]['active']!==1){throw new RuntimeException('Module 0.2.2 actif attendu.');}
 $main=$site.'/modules/watchysconfigurator/watchysconfigurator.php';if(is_link($main)||hash_file('sha256',$main)!==$manifest['moduleSha256']){throw new RuntimeException('Module installé différent du bilan ; protections à contrôler.');}
 $catalogPath=$site.'/modules/watchysconfigurator/config/catalog.json';if(is_link($catalogPath)||hash_file('sha256',$catalogPath)!=='40dbe4a9b28d22a1abc3ce16c5c0c6e9e9c0ea787df22e8e81afef3abe062439'){throw new RuntimeException('Catalogue installé différent du bilan.');}
 $previousOrders=(int)$db->query('SELECT COUNT(DISTINCT od.id_order) FROM `'.$prefix.'order_detail` od JOIN `'.$prefix.'watchys_model_support` s ON s.id_product=od.product_id')->fetchColumn();if($previousOrders!==0){throw new RuntimeException('Commandes Watchys déjà présentes ; reprise historique à examiner avant préparation.');}
 $contract=json_decode(file_get_contents(__DIR__.'/dev-contract.json'),true,512,JSON_THROW_ON_ERROR);
 $readStates=static function()use($rows,$prefix){return $rows('SELECT id_order_state,paid,shipped,delivery,logable,invoice,deleted FROM `'.$prefix.'order_state` ORDER BY id_order_state');};
 if($canon($readStates())!==$canon($contract['states'])){throw new RuntimeException('Statuts modifiés depuis le contrôle.');}
 foreach($contract['configuration'] as $key=>$expected){$values=$rows('SELECT value FROM `'.$prefix.'configuration` WHERE name=? AND (id_shop=1 OR id_shop IS NULL OR id_shop=0)',[$key]);if(!$values){throw new RuntimeException('Réglage absent : '.$key);}foreach($values as $value){if((string)$value['value']!==$expected){throw new RuntimeException('Réglage ou portée modifiés : '.$key);}}}
 foreach(['actionObjectOrderAddBefore','actionFrontControllerInitBefore','actionCartUpdateQuantityBefore','actionPresentCart'] as $name){$hooks=$rows('SELECT h.active FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook WHERE LOWER(h.name)=LOWER(?) AND hm.id_module=? AND hm.id_shop=1',[$name,(int)$module[0]['id_module']]);if(count($hooks)!==1||(int)$hooks[0]['active']!==1){throw new RuntimeException('Garde ou présentation native absente : '.$name);}}
 $tables=WatchysStockSchema::definitions($prefix);
 $inspect=static function($table)use($rows,$db,$prefix){
  $engine=$rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.$table]);if(!$engine){return false;}
  if(count($engine)!==1||strtoupper($engine[0]['ENGINE'])!=='INNODB'){throw new RuntimeException('Table non transactionnelle : '.$table);}
  $columns=$rows('SHOW COLUMNS FROM `'.$prefix.$table.'`');if(array_column($columns,'Field')!==WatchysStockSchema::columns()[$table]){throw new RuntimeException('Structure de table différente : '.$table);}
  foreach($columns as $column){$f=$column['Field'];$type=strtolower($column['Type']);
   $pattern=in_array($f,['nomenclature_hash','operation_key','request_digest'],true)?'/^char\(64\)$/':($f==='event_kind'?'/^varchar\(16\)$/':($f==='event_reference'?'/^varchar\(120\)$/':(in_array($f,['before_json','after_json','event_json'],true)?'/^text$/':($f==='created_at'?'/^datetime$/':($f==='released'?'/^smallint(?:\([0-9]+\))?$/':($f==='id_stock_ledger'?'/^bigint(?:\([0-9]+\))? unsigned$/':'/^int(?:\([0-9]+\))?(?: unsigned)?$/'))))));
   if(!preg_match($pattern,$type)||$column['Null']!=='NO'){throw new RuntimeException('Type de colonne inattendu : '.$table.'.'.$f);}
  }
  $indexes=$rows('SHOW INDEX FROM `'.$prefix.$table.'`');$unique=[];
  foreach($indexes as $index){if((int)$index['Non_unique']===0){$unique[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name'];}}
  foreach($unique as &$cols){ksort($cols);$cols=array_values($cols);}unset($cols);
  $primary=$table==='watchys_order_component'?['id_order_detail','component_product_id','component_attribute_id']:['id_stock_ledger'];
  if(($unique['PRIMARY']??[])!==$primary||($table==='watchys_stock_ledger'&&!in_array(['operation_key'],array_values($unique),true))){throw new RuntimeException('Clés uniques non confirmées : '.$table);}
  return (int)$db->query('SELECT COUNT(*) FROM `'.$prefix.$table.'`')->fetchColumn();
 };
 $present=[];foreach($tables as $name=>$ddl){$count=$inspect($name);if($count!==false&&$count!==0){throw new RuntimeException('Table déjà alimentée ; audit séparé requis : '.$name);}$present[$name]=$count;}
 $stocks=$rows('SELECT * FROM `'.$prefix.'stock_available` WHERE id_product IN ('.implode(',',$manifest['componentIds']).',8867) ORDER BY id_stock_available');


 foreach($present as $name=>$count){if($count!==0){throw new RuntimeException('Socle réel attendu présent et vide : '.$name);}}
 foreach([7736,8188] as $pid){$found=array_filter($stocks,static function($row)use($pid){return (int)$row['id_product']===$pid&&(int)$row['id_product_attribute']===0&&(int)$row['id_shop']===1&&(int)$row['id_shop_group']===0;});if(count($found)!==1){throw new RuntimeException('Stock de référence non confirmé : '.$pid);}}
 $engine=$rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.'stock_available']);if(count($engine)!==1||strtoupper($engine[0]['ENGINE'])!=='INNODB'){throw new RuntimeException('Stock transactionnel requis.');}


 echo "DEV confirmé — socle réel vide ; commande et paiement protégés.\n";
 echo "LECTURE SEULE — Recherche des surcharges et points de branchement, sans charger les classes du site.\n";
 $nativeFiles=[
  'classes/PaymentModule.php'=>'6e5bfe678ae8d3ad6ae2f2ae3685e754ce8295f9e5aa037a3aa9b998a0ae6d98',
  'classes/order/OrderDetail.php'=>'aa9e3c182b38b93b2641e93903b6f99a4091979ca6cf77a8021110e8af867d57',
  'classes/order/OrderHistory.php'=>'33edb0236d2a4d1a50cd83c541dce1c4cf1e1dc32a9bbe9f8dc47e779aaa548c',
  'src/Core/Stock/StockManager.php'=>'c361a5b256b9c0840884832ef398ef499d326ac92024d42f5c671945608fbd20',
  'src/Adapter/StockManager.php'=>'91e4aff2fe75392bd4c9f0e219e153145c509c5c67cebff157670a5d453a6e19',
  'classes/stock/StockAvailable.php'=>null,
  'classes/db/Db.php'=>null,
  'classes/db/DbPDO.php'=>null,
  'src/Adapter/ServiceLocator.php'=>null,
  'src/Core/Foundation/IoC/Container.php'=>null,
 ];
 foreach($nativeFiles as $relative=>$expected){$file=$site.'/'.$relative;if(!is_file($file)||is_link($file)||!str_starts_with((string)realpath($file),$site.'/')){throw new RuntimeException('Source native absente ou lien inattendu : '.$relative);}$hash=hash_file('sha256',$file);echo 'SOURCE | '.$relative.' | SHA256='.$hash.' | '.($expected===null?'empreinte relevée':($hash===$expected?'conforme au bilan':'DIFFÉRENTE DU BILAN'))."\n";if($expected!==null&&$hash!==$expected){throw new RuntimeException('Source native modifiée ; audit requis.');}}
 $methodNames=static function($file){$tokens=token_get_all(file_get_contents($file),TOKEN_PARSE);$names=[];for($i=0;$i<count($tokens);++$i){if(is_array($tokens[$i])&&$tokens[$i][0]===T_FUNCTION){for($j=$i+1;$j<count($tokens);++$j){$t=$tokens[$j];if($t==='('){break;}if(is_array($t)&&$t[0]===T_STRING){$names[]=$t[1];break;}}}}return array_values(array_unique($names));};
 $targets=['classes/stock/StockAvailable.php','classes/PaymentModule.php','classes/order/OrderDetail.php','classes/order/OrderHistory.php','classes/db/Db.php','classes/db/DbPDO.php'];$found=[];
 foreach($targets as $target){$relative='override/'.$target;if(is_file($site.'/'.$relative)){$found[$relative]=true;}foreach(glob($site.'/modules/*/override/'.$target)?:[] as $file){$relative=substr($file,strlen($site)+1);$found[$relative]=true;}}
 ksort($found);echo 'SURCHARGES CANDIDATES : '.count($found)." (présence sur disque ; chargement effectif non déduit).\n";
 foreach($found as $relative=>$unused){$file=$site.'/'.$relative;if(is_link($file)||!str_starts_with((string)realpath($file),$site.'/')){throw new RuntimeException('Surcharge hors périmètre : '.$relative);}echo 'SURCHARGE | '.$relative.' | SHA256='.hash_file('sha256',$file).' | méthodes='.implode(',',$methodNames($file))."\n";}
 foreach(['var/cache/prod/class_index.php','var/cache/dev/class_index.php','cache/class_index.php'] as $relative){if(is_file($site.'/'.$relative)&&!is_link($site.'/'.$relative)){echo 'INDEX CLASSES | '.$relative.' | SHA256='.hash_file('sha256',$site.'/'.$relative)." (non exécuté).\n";}}
 $serviceFiles=[];foreach(['config/services.yml','config/services.yaml','config/admin/services.yml','config/front/services.yml'] as $tail){foreach(glob($site.'/modules/*/'.$tail)?:[] as $file){if(!is_link($file)&&str_starts_with((string)realpath($file),$site.'/')&&filesize($file)<=1048576){$text=file_get_contents($file);if(str_contains($text,'StockManager')||str_contains($text,'ServiceLocator')){$serviceFiles[substr($file,strlen($site)+1)]=hash_file('sha256',$file);}}}}
 echo 'CONFIGURATIONS SERVICES CANDIDATES : '.count($serviceFiles)." (références textuelles uniquement).\n";ksort($serviceFiles);foreach($serviceFiles as $relative=>$hash){echo 'SERVICE | '.$relative.' | SHA256='.$hash."\n";}
 foreach(['actionObjectOrderDetailAddBefore','actionObjectOrderDetailAddAfter','actionObjectOrderHistoryAddAfter','actionOrderStatusPostUpdate','actionObjectStockAvailableUpdateBefore','actionObjectStockAvailableUpdateAfter'] as $hook){
  $bound=$rows('SELECT m.name,m.version,hm.position FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook JOIN `'.$prefix.'module` m ON m.id_module=hm.id_module WHERE LOWER(h.name)=LOWER(?) AND hm.id_shop=1 AND m.active=1 ORDER BY hm.position,m.name',[$hook]);
  echo 'ACCROCHE | '.$hook.' | '.($bound?json_encode($bound,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):'aucun module actif lié')."\n";
 }
 $db->exec('COMMIT');
 echo "SERVICE PRÉPARÉ — Commandes/lignes/personnalisations/historiques natifs et PDO partagé. Non publié ni raccordé aux accroches.\n";
 echo "PROCHAINE DÉCISION — Choisir l’adaptation des écrivains à partir des surcharges et services réellement présents ; aucun remplacement automatique.\n";
 echo "CONTRÔLE TERMINÉ — Aucune écriture dans les fichiers ou MySQL, aucun bootstrap natif, aucune installation et aucun accès PROD.\n";
}catch(Throwable $e){if($db&&$db->inTransaction()){try{$db->rollBack();}catch(Throwable $ignored){}}echo $e instanceof PDOException?'ARRÊT — Erreur MySQL, SQLSTATE '.$e->getCode().' / code '.(int)($e->errorInfo[1]??0)." (identifiants masqués).\n":'ARRÊT — '.$e->getMessage()."\n";exit(1);}
