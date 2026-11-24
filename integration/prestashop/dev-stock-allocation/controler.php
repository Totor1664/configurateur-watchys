<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);set_time_limit(120);
echo "========== DÉBUT — TEST ISOLÉ ALLOCATION WATCHYS DEV ==========\n";
register_shutdown_function(static function(){echo "========== FIN — TEST ISOLÉ ALLOCATION WATCHYS DEV ==========\n";});
$db=null;$save=null;$ddlStarted=false;
try{
 if(count($argv)>2||(isset($argv[1])&&$argv[1]!=='--verifier')){throw new RuntimeException('Utilisation : php controler.php [--verifier]');}
 $verify=($argv[1]??'')==='--verifier';$home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');
 if(!$site||is_link($home.'/sites/dev.watchys.ch')||$site===realpath($home.'/web')){throw new RuntimeException('Dossier DEV non confirmé.');}
 $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($manifest['files'] as $file=>$hash){if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$file)||str_contains($file,'..')||is_link(__DIR__.'/'.$file)||!is_file(__DIR__.'/'.$file)||hash_file('sha256',__DIR__.'/'.$file)!==$hash){throw new RuntimeException('Paquet absent ou modifié : '.$file);}}
 foreach(['module/classes/WatchysStockSchema.php','module/classes/WatchysStockJournal.php','module/classes/WatchysStockPolicy.php','module/classes/WatchysStockAllocator.php','module/classes/WatchysOrderNomenclature.php'] as $file){token_get_all(file_get_contents(__DIR__.'/'.$file),TOKEN_PARSE);}
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
 $db->exec('COMMIT');

 foreach($present as $name=>$count){if($count!==0){throw new RuntimeException('Socle réel attendu présent et vide : '.$name);}}
 foreach([7736,8188] as $pid){$found=array_filter($stocks,static function($row)use($pid){return (int)$row['id_product']===$pid&&(int)$row['id_product_attribute']===0&&(int)$row['id_shop']===1&&(int)$row['id_shop_group']===0;});if(count($found)!==1){throw new RuntimeException('Stock de référence non confirmé : '.$pid);}}
 $engine=$rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.'stock_available']);if(count($engine)!==1||strtoupper($engine[0]['ENGINE'])!=='INNODB'){throw new RuntimeException('Stock transactionnel requis.');}
 echo "DEV confirmé — socle réel présent et vide ; protections conservées.\nPLAN — Test MySQL isolé de l’allocateur, sans raccordement aux commandes.\n";
 echo "Après OUI : trois tables TEMPORAIRES privées à cette connexion, copies de stocks et scénarios fictifs.\nRupture partagée, reprise sans doublon, paiement, annulation, réactivation et retours.\nLes tests seront annulés par ROLLBACK ; les tables temporaires disparaîtront à la fermeture.\nAucun stock réel écrit, aucune table persistante créée, aucun fichier du site publié, aucun hook ni commande créé.\nUn journal privé sera créé. Commande et paiement restent bloqués. Aucun accès PROD.\nCe test ne prouve pas encore la concurrence avec les ventes natives PrestaShop.\n";
 if($verify){echo "CONTRÔLE TERMINÉ — Aucune écriture dans le site, les fichiers privés ou MySQL. Aucun bootstrap PrestaShop.\n";exit;}
 $tty=fopen('/dev/tty','r');if(!$tty){throw new RuntimeException('Terminal interactif requis.');}echo 'Tape OUI pour tester sur les copies temporaires : ';$answer=fgets($tty);fclose($tty);if(trim((string)$answer)!=='OUI'){echo "ANNULÉ — Aucune modification.\n";exit;}
 $private=$home.'/watchys-sauvegardes-privees';if(!is_dir($private)||is_link($private)||realpath($private)!==$private||is_link($private.'/module-watchys.lock')){throw new RuntimeException('Dossier privé non confirmé.');}
 $lock=fopen($private.'/module-watchys.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){throw new RuntimeException('Autre mise à jour en cours.');}
 umask(0077);$journal=$private.'/allocation-isolee-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';$h=fopen($journal,'x');if(!$h||!chmod($journal,0600)){throw new RuntimeException('Journal privé indisponible.');}
 $log=['status'=>'test-en-cours','scope'=>'temporary-tables-only','moduleSha256'=>$manifest['moduleSha256']];
 $save=static function()use(&$log,$h){$json=json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(!rewind($h)||!ftruncate($h,0)||fwrite($h,$json)!==strlen($json)||!fflush($h)){throw new RuntimeException('Journal non enregistré.');}if(function_exists('fsync')&&!fsync($h)){throw new RuntimeException('Journal non synchronisé.');}};$save();echo 'Journal : '.$journal."\n";
 if(hash_file('sha256',$main)!==$manifest['moduleSha256']||$canon($readStates())!==$canon($contract['states'])){throw new RuntimeException('État modifié depuis le plan.');}
 foreach($tables as $name=>$unused){if($inspect($name)!==0){throw new RuntimeException('Socle réel modifié depuis le plan.');}}
 $probe='watchys_probe_'.bin2hex(random_bytes(8)).'_';
 if($probe===$prefix||!preg_match('/^watchys_probe_[a-f0-9]{16}_$/D',$probe)){throw new RuntimeException('Préfixe temporaire invalide.');}
 $db->exec('CREATE TEMPORARY TABLE `'.$probe.'stock_available` LIKE `'.$prefix.'stock_available`');
 foreach(WatchysStockSchema::definitions($probe) as $ddl){if(!str_starts_with($ddl,'CREATE TABLE ')){throw new RuntimeException('Schéma temporaire invalide.');}$db->exec(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$ddl,1));}
 $db->exec('INSERT INTO `'.$probe.'stock_available` SELECT * FROM `'.$prefix.'stock_available` WHERE id_product IN ('.implode(',',$manifest['componentIds']).') AND id_shop=1 AND id_shop_group=0');
 require_once __DIR__.'/module/tests/stock-allocation-scenarios.php';$db->beginTransaction();watchysAllocationScenarios($db,$probe);$db->rollBack();
 foreach(['watchys_order_component','watchys_stock_ledger'] as $name){if((int)$db->query('SELECT COUNT(*) FROM `'.$probe.$name.'`')->fetchColumn()!==0){throw new RuntimeException('Journal temporaire non annulé.');}if($inspect($name)!==0){throw new RuntimeException('Table réelle modifiée pendant le test ; contrôle séparé requis.');}}
 $after=$rows('SELECT * FROM `'.$prefix.'stock_available` WHERE id_product IN ('.implode(',',$manifest['componentIds']).',8867) ORDER BY id_stock_available');
 if($canon($after)!==$canon($stocks)){throw new RuntimeException('Stock réel différent du bilan initial ; aucune écriture de ce test ne cible ces lignes, contrôler une activité parallèle.');}
 if(hash_file('sha256',$main)!==$manifest['moduleSha256']){throw new RuntimeException('Module modifié pendant le test.');}
 $log['status']='succes';$log['probe']='temporary-allocation-scenarios-passed-rolled-back';$save();fclose($h);$db=null;
 echo "SUCCÈS — Allocation testée sur copies MySQL temporaires ; scénarios annulés par ROLLBACK.\nStocks réels et deux tables de suivi relus inchangés. Aucun raccordement aux commandes ; paiement toujours bloqué.\nConcurrence avec les ventes natives encore à valider.\n";
}catch(Throwable $e){if($db&&$db->inTransaction()){try{$db->rollBack();}catch(Throwable $ignored){}}$db=null;if($save){try{$log['status']='arret-verification-requise';$save();}catch(Throwable $ignored){}}echo $e instanceof PDOException?"ARRÊT — Erreur MySQL (identifiants masqués).\n":'ARRÊT — '.$e->getMessage()."\n";echo "Aucun succès à déduire ; conserver le bilan avant reprise.\n";exit(1);}
