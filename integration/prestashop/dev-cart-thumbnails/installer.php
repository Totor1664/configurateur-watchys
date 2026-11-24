<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);set_time_limit(0);
echo "========== DÉBUT — MINIATURES PERSONNALISÉES WATCHYS DEV ==========\n";
register_shutdown_function(static function(){echo "========== FIN — MINIATURES PERSONNALISÉES WATCHYS DEV ==========\n";});
$db=null;$published=[];$save=null;$log=null;$nativeStarted=false;$nativeApplied=false;$phase='préparation';
function thumbCopyExclusive($src,$dst){$in=fopen($src,'rb');$out=fopen($dst,'xb');if(!$in||!$out){throw new RuntimeException('Copie exclusive impossible.');}try{$n=stream_copy_to_stream($in,$out);if(!fflush($out)||$n!==filesize($src)){throw new RuntimeException('Copie incomplète.');}}finally{fclose($in);fclose($out);}if(hash_file('sha256',$src)!==hash_file('sha256',$dst)){throw new RuntimeException('Copie non conforme.');}}
function thumbSafePath($path,$root){$parent=realpath(dirname($path));if(!$parent||$parent!==dirname($path)||!str_starts_with($parent.'/',$root.'/')||is_link($path)||(file_exists($path)&&!is_file($path))){throw new RuntimeException('Chemin non autorisé.');}}
try{
 if(count($argv)>2||(isset($argv[1])&&$argv[1]!=='--verifier')){throw new RuntimeException('Utilisation : php installer.php [--verifier]');}
 $verify=($argv[1]??'')==='--verifier';$home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');
 if(!$site||is_link($home.'/sites/dev.watchys.ch')||$site===realpath($home.'/web')){throw new RuntimeException('Dossier DEV non confirmé.');}
 $target=$site.'/modules/watchysconfigurator';$manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
 if(($manifest['schemaVersion']??null)!==1||count($manifest['thumbnails']??[])!==960||($manifest['directory']??'')!=='cart-reference-v1'){throw new RuntimeException('Manifeste inattendu.');}
 foreach($manifest['checks'] as $file=>$hash){if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$file)||str_contains($file,'..')||is_link(__DIR__.'/'.$file)||!is_file(__DIR__.'/'.$file)||hash_file('sha256',__DIR__.'/'.$file)!==$hash){throw new RuntimeException('Paquet modifié : '.$file);}}
 foreach($manifest['thumbnails'] as $name=>$hash){if(!preg_match('/^[a-f0-9]{64}\.jpg$/D',$name)||is_link(__DIR__.'/thumbnails/'.$name)||hash_file('sha256',__DIR__.'/thumbnails/'.$name)!==$hash){throw new RuntimeException('Miniature absente ou différente.');}$size=getimagesize(__DIR__.'/thumbnails/'.$name);if(!$size||$size[0]!==380||$size[1]!==494||$size[2]!==IMAGETYPE_JPEG){throw new RuntimeException('Format miniature non conforme.');}}
 // Isolated tests do not load PrestaShop or open a database connection.
 if(!function_exists('proc_open')){throw new RuntimeException('Sous-processus PHP requis pour les tests isolés.');}
 foreach(['cart-thumbnails.php','cart-service.php'] as $test){
  $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/module/tests/'.$test],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__);
  if(!is_resource($process)){throw new RuntimeException('Test non démarré.');}fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
  if($exit!==0||str_contains($out.$err,'Fatal error:')){throw new RuntimeException('Test isolé échoué : '.$test);}echo $out;
 }
 foreach($manifest['guards'] as $file=>$hash){thumbSafePath($target.'/'.$file,$site);if(!is_file($target.'/'.$file)||hash_file('sha256',$target.'/'.$file)!==$hash){throw new RuntimeException('Code métier ou visuel installé différent : '.$file);}}
 $catalogPath=$target.'/config/catalog.json';if(is_link($catalogPath)||hash_file('sha256',$catalogPath)!==$manifest['catalogSha256']){throw new RuntimeException('Catalogue installé modifié depuis le contrôle.');}
 $changes=[];
 foreach($manifest['patch'] as $file=>$after){thumbSafePath($target.'/'.$file,$site);$before=is_file($target.'/'.$file)?hash_file('sha256',$target.'/'.$file):null;
  if($before!==$after&&$before!==$manifest['before'][$file]){throw new RuntimeException('Fichier installé inconnu : '.$file);}
  if($before!==$after){$changes[]=['relative'=>$file,'target'=>$target.'/'.$file,'source'=>__DIR__.'/module/'.$file,'beforeSha256'=>$before,'afterSha256'=>$after];}
 }
 $imageDir=$target.'/views/img/'.$manifest['directory'];$parent=realpath(dirname($imageDir));
 if(!$parent||$parent!==dirname($imageDir)||is_link($imageDir)||(file_exists($imageDir)&&!is_dir($imageDir))){throw new RuntimeException('Dossier miniatures non autorisé.');}
 $toCopy=[];foreach($manifest['thumbnails'] as $name=>$hash){$dst=$imageDir.'/'.$name;if(is_link($dst)||(file_exists($dst)&&(!is_file($dst)||hash_file('sha256',$dst)!==$hash))){throw new RuntimeException('Miniature cible différente ; aucun remplacement automatique.');}if(!is_file($dst)){$toCopy[$name]=$hash;}}
 $cfg=require $site.'/app/config/parameters.php';$p=$cfg['parameters'];$prefix=$p['database_prefix'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new RuntimeException('Préfixe invalide.');}
 $port=empty($p['database_port'])?'':';port='.(int)$p['database_port'];$db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($cfg,$p);
 $rows=static function($sql,$args=[])use($db){$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);};
 $db->exec('START TRANSACTION READ ONLY');$urls=$rows('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1');
 if(!$urls||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1){throw new RuntimeException('Boutique unique non confirmée.');}
 foreach($urls as $url){if((int)$url['id_shop']!==1||$url['domain']!=='dev.watchys.ch'||$url['domain_ssl']!=='dev.watchys.ch'){throw new RuntimeException('Base DEV non confirmée.');}}
 $state=$rows('SELECT id_module,version,active FROM `'.$prefix."module` WHERE name='watchysconfigurator'");if(count($state)!==1||$state[0]['version']!=='0.2.2'||(int)$state[0]['active']!==1){throw new RuntimeException('Module 0.2.2 actif attendu.');}$moduleId=(int)$state[0]['id_module'];
 $linked=$rows('SELECT id_shop FROM `'.$prefix.'module_shop` WHERE id_module=?',[$moduleId]);if(count($linked)!==1||(int)$linked[0]['id_shop']!==1){throw new RuntimeException('Association module/boutique inattendue.');}
 $support=$rows('SELECT * FROM `'.$prefix.'watchys_model_support`');if(count($support)!==1||(int)$support[0]['id_product']!==8867||$support[0]['model_id']!=='watchys-7750-41mm'){throw new RuntimeException('Support technique non confirmé.');}
 $hooks=[];foreach(['actionCartUpdateQuantityBefore','actionObjectOrderAddBefore','actionFrontControllerInitBefore','displayCustomization','displayShoppingCartFooter'] as $name){$r=$rows('SELECT h.id_hook,h.name,h.active,hm.id_module,hm.id_shop FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook WHERE LOWER(h.name)=LOWER(?) AND hm.id_module=? AND hm.id_shop=1',[$name,$moduleId]);if(count($r)!==1||(int)$r[0]['active']!==1){throw new RuntimeException('Protection native non confirmée : '.$name);}$hooks[$name]=$r;}
 $newHook=$rows('SELECT h.id_hook,h.name,h.active,hm.id_module,hm.id_shop FROM `'.$prefix.'hook` h LEFT JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook AND hm.id_module=? AND hm.id_shop=1 WHERE LOWER(h.name)=LOWER(?)',[$moduleId,'actionPresentCart']);
 if(count($newHook)>1||($newHook&&(int)$newHook[0]['active']!==1)){throw new RuntimeException('Accroche de présentation ambiguë ou inactive.');}
 $core=$site.'/src/Adapter/Presenter/Cart/CartPresenter.php';if(!is_file($core)||!str_contains(file_get_contents($core),"'actionPresentCart'")){throw new RuntimeException('Point de présentation natif absent ; contrôle du cœur nécessaire.');}
 $stock=$rows('SELECT * FROM `'.$prefix.'stock_available` WHERE id_product IN ('.implode(',',$manifest['componentIds']).',8867) ORDER BY id_stock_available');
 $product=$rows('SELECT * FROM `'.$prefix.'product_shop` WHERE id_product=8867 AND id_shop=1');
 $db->exec('COMMIT');
 echo "DEV confirmé — module 0.2.2 ; 960 miniatures de la référence SVG validée.\n";
 echo 'PLAN — '.count($changes).' fichiers de module à publier ; '.count($toCopy)." nouveaux JPEG ; une accroche native actionPresentCart.\n";
 echo 'Accroche actuelle : '.(!$newHook?'à créer':($newHook[0]['id_module']===null?'présente, à lier':'déjà liée'))."\n";
 echo "Chaque ligne personnalisée choisit son image depuis la configuration enregistrée côté serveur ; panier et mini-panier natifs.\n";
 echo "Couverture générique 16190 conservée comme secours. Aucun changement de prix, stock, configuration ou moteur SVG. Commande et paiement restent bloqués. Aucun accès PROD.\n";
 // Report theme templates for follow-up if a custom mini-cart does not use CartPresenter.
 foreach(glob($site.'/themes/*/modules/ps_shoppingcart/*.tpl')?:[] as $tpl){echo 'Mini-panier thème repéré : '.substr($tpl,strlen($site)+1)."\n";}
 if($verify){echo "CONTRÔLE TERMINÉ — Aucune écriture, aucun bootstrap PrestaShop.\n";exit;}
 $tty=fopen('/dev/tty','r');if(!$tty){throw new RuntimeException('Terminal interactif requis.');}echo 'Tape OUI pour appliquer ce plan sur DEV : ';$answer=fgets($tty);fclose($tty);if(trim((string)$answer)!=='OUI'){echo "ANNULÉ — Aucune écriture.\n";exit;}
 $private=$home.'/watchys-sauvegardes-privees';if(!is_dir($private)||is_link($private)||realpath($private)!==$private||is_link($private.'/module-watchys.lock')){throw new RuntimeException('Dossier privé ou verrou non autorisé.');}
 $lock=fopen($private.'/module-watchys.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){throw new RuntimeException('Autre migration Watchys en cours.');}
 umask(0077);$backup=$private.'/miniatures-panier-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));if(!mkdir($backup,0700)){throw new RuntimeException('Dossier journal non créé.');}
 $journal=$backup.'/journal.json';$log=['status'=>'preparation','moduleBefore'=>$state,'hooksBefore'=>$hooks,'presentationHookBefore'=>$newHook,'supportBefore'=>$support,'stockBefore'=>$stock,'productBefore'=>$product,'files'=>[],'thumbnails'=>array_keys($toCopy)];
 $save=static function()use(&$log,$journal){$bytes=json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($journal,$bytes,LOCK_EX)!==strlen($bytes)){throw new RuntimeException('Journal non enregistré.');}};$save();echo 'Journal : '.$journal."\n";
 foreach($changes as $i=>&$change){if((is_file($change['target'])?hash_file('sha256',$change['target']):null)!==$change['beforeSha256']){throw new RuntimeException('Code changé depuis le plan.');}$change['backup']=null;if($change['beforeSha256']!==null){$change['backup']=$backup.'/'.$i.'.before';thumbCopyExclusive($change['target'],$change['backup']);}}unset($change);$log['files']=$changes;$save();
 $phase='publication des miniatures';
 if(!is_dir($imageDir)&&(!mkdir($imageDir,0755)||!chmod($imageDir,0755))){throw new RuntimeException('Dossier miniatures non créé.');}
 foreach($toCopy as $name=>$hash){$dst=$imageDir.'/'.$name;thumbSafePath($dst,$site);thumbCopyExclusive(__DIR__.'/thumbnails/'.$name,$dst);if(!chmod($dst,0644)){throw new RuntimeException('Droits JPEG non confirmés.');}}
 foreach($manifest['thumbnails'] as $name=>$hash){if(hash_file('sha256',$imageDir.'/'.$name)!==$hash){throw new RuntimeException('Miniature publiée non conforme.');}}
 $phase='publication du code';
 foreach($changes as $change){thumbSafePath($change['target'],$site);if((is_file($change['target'])?hash_file('sha256',$change['target']):null)!==$change['beforeSha256']){throw new RuntimeException('Code modifié pendant la préparation.');}$tmp=$change['target'].'.watchys-'.bin2hex(random_bytes(4));thumbCopyExclusive($change['source'],$tmp);if(!chmod($tmp,0644)||!rename($tmp,$change['target'])){throw new RuntimeException('Publication du code échouée.');}$published[]=$change;if(hash_file('sha256',$change['target'])!==$change['afterSha256']){throw new RuntimeException('Code publié non conforme.');}if(function_exists('opcache_invalidate')){@opcache_invalidate($change['target'],true);}}
 foreach($manifest['guards'] as $f=>$h){if(hash_file('sha256',$target.'/'.$f)!==$h){throw new RuntimeException('Code métier ou PNG modifié depuis le plan.');}}if(hash_file('sha256',$catalogPath)!==$manifest['catalogSha256']){throw new RuntimeException('Catalogue modifié depuis le plan.');}
 $phase='accroche native de présentation';$nativeStarted=true;$log['status']='accroche-native-en-cours';$save();
 require $site.'/config/config.inc.php';global $kernel;$kernel=new AppKernel('prod',false);$kernel->boot();Shop::setContext(Shop::CONTEXT_SHOP,1);$context=Context::getContext();$context->shop=new Shop(1);$context->language=new Language((int)Configuration::get('PS_LANG_DEFAULT'));$context->currency=new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));if(!$context->employee){$context->employee=new Employee(42);}
 require_once $target.'/watchysconfigurator.php';$module=new Watchysconfigurator();if(!$module->isDev()||(int)$module->id!==$moduleId){throw new RuntimeException('Module natif différent.');}
 foreach(['hook_idsbyname*','active_hooks',Hook::MODULE_LIST_BY_HOOK_KEY.'*'] as $key){Cache::clean($key);}
 if(!$module->registerHook('actionPresentCart',[1])){throw new RuntimeException('Enregistrement natif non confirmé.');}
 foreach(['hook_idsbyname*','active_hooks',Hook::MODULE_LIST_BY_HOOK_KEY.'*'] as $key){Cache::clean($key);}
 $hookAfter=$rows('SELECT h.id_hook,h.name,h.active,hm.id_module,hm.id_shop FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook WHERE LOWER(h.name)=LOWER(?) AND hm.id_module=? AND hm.id_shop=1',['actionPresentCart',$moduleId]);
 if(count($hookAfter)!==1||(int)$hookAfter[0]['active']!==1){throw new RuntimeException('Accroche non relue directement en SQL.');}
 foreach($hooks as $name=>$before){$r=$rows('SELECT h.id_hook,h.name,h.active,hm.id_module,hm.id_shop FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook WHERE LOWER(h.name)=LOWER(?) AND hm.id_module=? AND hm.id_shop=1',[$name,$moduleId]);if($r!==$before){throw new RuntimeException('Protection native modifiée.');}}
 if($rows('SELECT * FROM `'.$prefix.'stock_available` WHERE id_product IN ('.implode(',',$manifest['componentIds']).',8867) ORDER BY id_stock_available')!==$stock||$rows('SELECT * FROM `'.$prefix.'product_shop` WHERE id_product=8867 AND id_shop=1')!==$product){throw new RuntimeException('Stock ou support modifié pendant la préparation ; contrôler.');}
 foreach($manifest['patch'] as $f=>$h){if(hash_file('sha256',$target.'/'.$f)!==$h){throw new RuntimeException('Code final non conforme.');}}
 $nativeApplied=true;$log['hookAfter']=$hookAfter;$log['status']='succes';$save();
 echo "SUCCÈS — 960 miniatures relues ; choix par configuration et accroche native vérifiés.\nRecharge le panier, puis contrôle deux configurations différentes et le mini-panier. Image générique conservée ; prix, stocks, rendu et blocages de commande conservés.\n";
}catch(Throwable $e){
 if($db&&$db->inTransaction()){try{$db->rollBack();}catch(Throwable $ignored){}}
 $restored=true;
 if(!$nativeApplied){foreach(array_reverse($published) as $c){try{if(hash_file('sha256',$c['target'])!==$c['afterSha256']){throw new RuntimeException('Code changé après publication.');}if($c['backup']===null){if(!unlink($c['target'])){throw new RuntimeException('Nouveau fichier non retiré.');}}else{$tmp=$c['target'].'.restore-'.bin2hex(random_bytes(4));thumbCopyExclusive($c['backup'],$tmp);chmod($tmp,0644);if(!rename($tmp,$c['target'])){throw new RuntimeException('Restauration impossible.');}}if(function_exists('opcache_invalidate')){@opcache_invalidate($c['target'],true);}}catch(Throwable $ignored){$restored=false;}}}
 if($save){try{$log['status']=$nativeApplied?'succes-journal-a-controler':($restored?'arret-code-restaure':'arret-code-partiel');$log['phase']=$phase;$log['nativeStarted']=$nativeStarted;$save();}catch(Throwable $ignored){}}
 echo 'ARRÊT pendant : '.$phase."\n";echo $e instanceof PDOException?"Erreur MySQL (identifiants masqués).\n":$e->getMessage()."\n";
 echo "Aucun succès à déduire. Conserver le journal et relancer --verifier. Les nouvelles miniatures éventuelles restent présentes ; l'état natif doit être contrôlé si son enregistrement a commencé.\n";exit(1);
}
