<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);set_time_limit(180);
echo "========== DÉBUT — PRÉPARATION CLASSES NATIVES WATCHYS DEV ==========\n";
register_shutdown_function(static function(){echo "========== FIN — PRÉPARATION CLASSES NATIVES WATCHYS DEV ==========\n";});
$db=null;$nativePdo=null;$save=null;$published=[];$target=null;
try{
 if(count($argv)>2||(isset($argv[1])&&$argv[1]!=='--verifier')){throw new RuntimeException('Utilisation : php preparer.php [--verifier]');}
 $verify=($argv[1]??'')==='--verifier';$home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');
 if(!$site||is_link($home.'/sites/dev.watchys.ch')||$site===realpath($home.'/web')){throw new RuntimeException('Dossier DEV non confirmé.');}
 $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($manifest['files'] as $file=>$hash){if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$file)||str_contains($file,'..')||is_link(__DIR__.'/'.$file)||!is_file(__DIR__.'/'.$file)||hash_file('sha256',__DIR__.'/'.$file)!==$hash){throw new RuntimeException('Paquet absent ou modifié : '.$file);}}
 foreach(['module/classes/WatchysStockSchema.php','module/classes/WatchysStockJournal.php','module/classes/WatchysStockPolicy.php','module/classes/WatchysStockAllocator.php','module/classes/WatchysOrderNomenclature.php','module/classes/WatchysNativeStockCoordinator.php','module/classes/WatchysOrderStockService.php','module/classes/WatchysStockObjectGuard.php'] as $file){token_get_all(file_get_contents(__DIR__.'/'.$file),TOKEN_PARSE);}
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

 $target=$site.'/modules/watchysconfigurator';$classes=$target.'/classes';
 if(is_link($target)||is_link($classes)||realpath($classes)!==$classes){throw new RuntimeException('Dossier de classes non confirmé.');}
 $expectedNative=[
  'classes/stock/StockAvailable.php'=>'0d426c3dad1ec18e24f73bf11347e0eb58a23818819e4af59f38f6923c605b84',
  'classes/ObjectModel.php'=>'86dce9ec2eabadaf391bfb7f01088076ef0324c0760399b4b51297a161f64322',
  'classes/db/Db.php'=>'c6af6d757b5e1a93f14cc4bdf114201d4cf6ee8f3b4e1549e9e368605dc7f3ef',
  'classes/db/DbPDO.php'=>'4656689c5440bcc0083437af437cd8c2ed9430db9592670ba0905fff01293532',
 ];
 foreach($expectedNative as $relative=>$hash){if(!is_file($site.'/'.$relative)||is_link($site.'/'.$relative)||hash_file('sha256',$site.'/'.$relative)!==$hash){throw new RuntimeException('Source native différente : '.$relative);}}
 foreach(['classes/stock/StockAvailable.php','classes/ObjectModel.php','classes/db/Db.php','classes/db/DbPDO.php'] as $relative){if(is_file($site.'/override/'.$relative)||(glob($site.'/modules/*/override/'.$relative)?:[])){throw new RuntimeException('Surcharge nouvelle à examiner : '.$relative);}}
 foreach(['config/config.inc.php','app/AppKernel.php'] as $relative){if(!is_file($site.'/'.$relative)){throw new RuntimeException('Bootstrap natif incomplet.');}}
 $readProbeHooks=static function()use($rows,$prefix){return $rows('SELECT h.name,m.name AS module_name FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook JOIN `'.$prefix.'module` m ON m.id_module=hm.id_module WHERE hm.id_shop=1 AND m.active=1 AND LOWER(h.name) IN (\'actionobjectupdatebefore\',\'actionobjectupdateafter\',\'actionobjectwatchysruntimeprobestockupdatebefore\',\'actionobjectwatchysruntimeprobestockupdateafter\') ORDER BY h.name,m.name');};
 $genericHooks=$readProbeHooks();if($genericHooks){echo 'ACCROCHES GÉNÉRIQUES À EXAMINER : '.json_encode($genericHooks,JSON_THROW_ON_ERROR)."\n";throw new RuntimeException('Des hooks ObjectModel pourraient produire des effets externes ; essai natif refusé.');}
 $plan=[];foreach($manifest['publish'] as $name=>$hash){
  if(!preg_match('/^Watchys[A-Za-z]+\.php$/D',$name)||is_link($classes.'/'.$name)){throw new RuntimeException('Cible de classe invalide.');}
  $exists=file_exists($classes.'/'.$name);if($exists&&(!is_file($classes.'/'.$name)||hash_file('sha256',$classes.'/'.$name)!==$hash)){throw new RuntimeException('Classe existante différente ; aucun remplacement automatique : '.$name);}
  $plan[$name]=['before'=>$exists?$hash:null,'after'=>$hash,'action'=>$exists?'identique-conservee':'nouvelle'];
 }
 echo "DEV confirmé — cœur natif conforme et aucune accroche générique active sur l’objet de test.\nPLAN — Préparer les classes de stock, sans activation des commandes.\n";
 echo "Après OUI : bootstrap PrestaShop réel (réchauffage éventuel des caches natifs), puis six tables TEMPORARY sur son PDO maître.\nLe vrai ObjectModel/StockAvailable écrit uniquement une copie de stock ; objet périmé, écriture fraîche et rollback seront vérifiés.\nAucune notification de stock ni paiement exécuté. Les scénarios seront annulés et copies temporaires supprimées.\nAprès réussite seulement : ajout des huit classes préparées dans le module. Fichiers existants identiques conservés ; tout contenu différent est refusé.\n";
 foreach($plan as $name=>$item){echo 'CLASSE | '.$name.' | '.$item['action']."\n";}
 echo "Un journal privé suivra test et publication. Aucun override ni hook ajouté, aucune modification du fichier principal du module.\nStocks, prix, articles, commandes et rendu conservés. Commande et paiement restent bloqués. Aucun accès PROD.\n";
 if($verify){echo "CONTRÔLE TERMINÉ — Aucune écriture dans le site, fichiers privés ou MySQL ; aucun bootstrap natif.\n";exit;}
 $tty=fopen('/dev/tty','r');if(!$tty){throw new RuntimeException('Terminal interactif requis.');}echo 'Tape OUI pour tester les classes natives et les préparer sur DEV : ';$answer=fgets($tty);fclose($tty);if(trim((string)$answer)!=='OUI'){echo "ANNULÉ — Aucune modification.\n";exit;}
 $private=$home.'/watchys-sauvegardes-privees';if(!is_dir($private)||is_link($private)||realpath($private)!==$private||is_link($private.'/module-watchys.lock')){throw new RuntimeException('Dossier privé non confirmé.');}
 $lock=fopen($private.'/module-watchys.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){throw new RuntimeException('Autre mise à jour en cours.');}
 umask(0077);$journal=$private.'/classes-stock-natives-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';$h=fopen($journal,'x');if(!$h||!chmod($journal,0600)){throw new RuntimeException('Journal privé indisponible.');}
 $log=['status'=>'preparation','classes'=>$plan,'published'=>[],'probe'=>null];
 $save=static function()use(&$log,$h){$json=json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(!rewind($h)||!ftruncate($h,0)||fwrite($h,$json)!==strlen($json)||!fflush($h)){throw new RuntimeException('Journal non enregistré.');}if(function_exists('fsync')&&!fsync($h)){throw new RuntimeException('Journal non synchronisé.');}};$save();echo 'Journal : '.$journal."\n";
 if($readProbeHooks()||hash_file('sha256',$main)!==$manifest['moduleSha256']||$canon($readStates())!==$canon($contract['states'])){throw new RuntimeException('État modifié depuis le plan.');}
 $_SERVER['HTTP_HOST']='dev.watchys.ch';$_SERVER['SERVER_NAME']='dev.watchys.ch';$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['HTTPS']='on';$_SERVER['REMOTE_ADDR']='127.0.0.1';
 require $site.'/config/config.inc.php';global $kernel;$kernel=new AppKernel('prod',false);$kernel->boot();
 Shop::setContext(Shop::CONTEXT_SHOP,1);$context=Context::getContext();$context->shop=new Shop(1);$context->language=new Language((int)Configuration::get('PS_LANG_DEFAULT'));$context->currency=new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));
 require_once $target.'/watchysconfigurator.php';$watchysModule=new Watchysconfigurator();
 if(!$watchysModule->isDev()||_PS_VERSION_!=='8.2.7'||_DB_PREFIX_!==$prefix||$readProbeHooks()){throw new RuntimeException('Contexte natif DEV non confirmé.');}
 $nativePdo=Db::getInstance()->getLink();if(!$nativePdo instanceof PDO||$nativePdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'){throw new RuntimeException('Pilote natif PDO MySQL requis.');}
 if(!is_a('StockAvailable','StockAvailableCore',true)){throw new RuntimeException('Classe stock native substituée.');}
 $stockReflection=new ReflectionClass('StockAvailable');foreach($stockReflection->getMethods() as $method){if($method->getDeclaringClass()->getName()==='StockAvailable'){throw new RuntimeException('Surcharge stock chargée inattendue.');}}
 foreach($manifest['publish'] as $name=>$hash){$class=substr($name,0,-4);if(class_exists($class,false)||trait_exists($class,false)){$reflection=new ReflectionClass($class);if(realpath((string)$reflection->getFileName())!==realpath(__DIR__.'/module/classes/'.$name)){throw new RuntimeException('Classe de stock déjà chargée depuis une autre source ; reprise à examiner : '.$class);}}}
 // Include in dependency order. StockPolicy/StockJournal may be loaded by earlier requires.
 foreach(['WatchysStockPolicy.php','WatchysStockSchema.php','WatchysStockJournal.php','WatchysStockAllocator.php','WatchysOrderNomenclature.php','WatchysNativeStockCoordinator.php','WatchysOrderStockService.php','WatchysStockObjectGuard.php'] as $name){require_once __DIR__.'/module/classes/'.$name;}
 require_once __DIR__.'/probe.php';$progress=static function($message)use(&$log,$save){echo $message."\n";$log['results'][]=$message;$save();};
 $log['probe']=watchysNativeRuntimeProbe($watchysModule,$nativePdo,$prefix,$progress);$save();
 foreach(['watchys_order_component','watchys_stock_ledger'] as $name){if($inspect($name)!==0){throw new RuntimeException('Socle réel modifié pendant le test ; contrôle séparé requis.');}}
 $after=$rows('SELECT * FROM `'.$prefix.'stock_available` WHERE id_product IN ('.implode(',',$manifest['componentIds']).',8867) ORDER BY id_stock_available');if($canon($after)!==$canon($stocks)){throw new RuntimeException('Stock réel différent du bilan ; contrôler une activité parallèle.');}
 if(hash_file('sha256',$main)!==$manifest['moduleSha256']){throw new RuntimeException('Module modifié depuis le plan.');}
 foreach($plan as $name=>$item){
  $destination=$classes.'/'.$name;
  if(file_exists($destination)){if(is_link($destination)||hash_file('sha256',$destination)!==$item['after']){throw new RuntimeException('Classe modifiée depuis le plan : '.$name);}continue;}
  $source=__DIR__.'/module/classes/'.$name;if(hash_file('sha256',$source)!==$item['after']){throw new RuntimeException('Source modifiée depuis le contrôle.');}
  $temp=tempnam($classes,'.watchys-stage-');if($temp===false){throw new RuntimeException('Fichier de préparation indisponible.');}
  try{if(!copy($source,$temp)||!chmod($temp,0644)||hash_file('sha256',$temp)!==$item['after']){throw new RuntimeException('Copie de classe non confirmée.');}
   if(!link($temp,$destination)){throw new RuntimeException('Publication exclusive impossible : '.$name);}$published[]=$name;$log['published']=$published;$save();
   if(hash_file('sha256',$destination)!==$item['after']){throw new RuntimeException('Classe publiée non conforme.');}
  }finally{if(is_file($temp)){unlink($temp);}}
 }
 $log['status']='succes-classes-pretes-non-activees';$save();fclose($h);
 echo "SUCCÈS — Objet stock/Db natifs testés sur copies temporaires et même PDO ; scénarios annulés.\nHuit classes prêtes sur DEV ; aucune activation de hook ni override. Module principal, stocks réels et socle inchangés.\nLa frontière native de commande complète reste à raccorder ; commande et paiement toujours bloqués.\n";
}catch(Throwable $e){if($nativePdo&&$nativePdo->inTransaction()){try{$nativePdo->rollBack();}catch(Throwable $ignored){}}if($db&&$db->inTransaction()){try{$db->rollBack();}catch(Throwable $ignored){}}
 $restored=true;foreach(array_reverse($published) as $name){$file=$target.'/classes/'.$name;if(!is_file($file)||is_link($file)||hash_file('sha256',$file)!==($manifest['publish'][$name]??null)||!unlink($file)){$restored=false;}}
 if($save){try{$log['status']='arret-verification-requise';$log['newClassesRemoved']=$restored;$save();}catch(Throwable $ignored){}}
 echo $e instanceof PDOException?'ARRÊT — Erreur MySQL, SQLSTATE '.$e->getCode().' / code '.(int)($e->errorInfo[1]??0)." (identifiants masqués).\n":'ARRÊT — '.$e->getMessage()."\n";
 echo $restored?"Nouvelles classes éventuellement publiées retirées ; aucun fichier préexistant remplacé.\n":"Retrait des nouvelles classes non confirmé ; conserver le journal.\n";echo "Aucun succès à déduire. Le bootstrap peut avoir réchauffé les caches natifs ; les tables temporaires disparaissent à la fin du processus.\n";exit(1);}
