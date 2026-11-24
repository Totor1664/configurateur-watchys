<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);set_time_limit(120);
echo "========== DÉBUT — ORDRE ET SURVOL CONFIGURATEUR DEV ==========\n";
register_shutdown_function(static function(){echo "========== FIN — ORDRE ET SURVOL CONFIGURATEUR DEV ==========\n";});
$db=null;$published=[];$save=null;$phase='contrôle';
function choicesSafePath($path,$root){$parent=realpath(dirname($path));if(!$parent||$parent!==dirname($path)||!str_starts_with($parent.'/',$root.'/')||is_link($path)||(file_exists($path)&&!is_file($path))){throw new RuntimeException('Chemin non autorisé.');}}
function choicesCopy($src,$dst){$in=fopen($src,'rb');$out=fopen($dst,'xb');if(!$in||!$out){throw new RuntimeException('Copie exclusive impossible.');}try{$n=stream_copy_to_stream($in,$out);if($n!==filesize($src)||!fflush($out)){throw new RuntimeException('Copie incomplète.');}}finally{fclose($in);fclose($out);}if(hash_file('sha256',$src)!==hash_file('sha256',$dst)){throw new RuntimeException('Copie non conforme.');}}
try{
 if(count($argv)>2||(isset($argv[1])&&$argv[1]!=='--verifier')){throw new RuntimeException('Utilisation : php installer.php [--verifier]');}
 $verify=($argv[1]??'')==='--verifier';$home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');
 if(!$site||is_link($home.'/sites/dev.watchys.ch')||$site===realpath($home.'/web')){throw new RuntimeException('Dossier DEV non confirmé.');}
 $target=$site.'/modules/watchysconfigurator';$manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
 $allowed=['controllers/front/configure.php','views/css/configurator.css','views/js/configurator-cart-v020.js'];
 if(array_keys($manifest['patch']??[])!==$allowed){throw new RuntimeException('Liste des trois fichiers différente du plan.');}
 foreach($manifest['checks'] as $file=>$hash){if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$file)||str_contains($file,'..')||is_link(__DIR__.'/'.$file)||!is_file(__DIR__.'/'.$file)||hash_file('sha256',__DIR__.'/'.$file)!==$hash){throw new RuntimeException('Paquet absent ou modifié : '.$file);}}
 token_get_all(file_get_contents(__DIR__.'/files/controllers/front/configure.php'),TOKEN_PARSE);
 $changes=[];
 foreach($manifest['patch'] as $file=>$after){choicesSafePath($target.'/'.$file,$site);$before=is_file($target.'/'.$file)?hash_file('sha256',$target.'/'.$file):null;if($before!==$manifest['before'][$file]&&$before!==$after){throw new RuntimeException('Fichier installé différent : '.$file);}
  if($before!==$after){$changes[]=['relative'=>$file,'target'=>$target.'/'.$file,'source'=>__DIR__.'/files/'.$file,'beforeSha256'=>$before,'afterSha256'=>$after];}
 }
 foreach($manifest['guards'] as $file=>$hash){choicesSafePath($target.'/'.$file,$site);if(!is_file($target.'/'.$file)||hash_file('sha256',$target.'/'.$file)!==$hash){throw new RuntimeException('Code métier ou visuel installé différent : '.$file);}}
 if(hash_file('sha256',$target.'/config/catalog.json')!==$manifest['catalogSha256']){throw new RuntimeException('Catalogue installé différent ; contrôle préalable requis.');}
 $cfg=require $site.'/app/config/parameters.php';$p=$cfg['parameters'];$prefix=$p['database_prefix'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new RuntimeException('Préfixe invalide.');}$port=empty($p['database_port'])?'':';port='.(int)$p['database_port'];
 $db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($cfg,$p);
 $rows=static function($sql,$args=[])use($db){$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);};
 $db->exec('START TRANSACTION READ ONLY');$urls=$rows('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1');
 if(!$urls||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1){throw new RuntimeException('Boutique unique non confirmée.');}foreach($urls as $url){if((int)$url['id_shop']!==1||$url['domain']!=='dev.watchys.ch'||$url['domain_ssl']!=='dev.watchys.ch'){throw new RuntimeException('Base DEV non confirmée.');}}
 $module=$rows('SELECT id_module,version,active FROM `'.$prefix."module` WHERE name='watchysconfigurator'");if(count($module)!==1||$module[0]['version']!=='0.2.2'||(int)$module[0]['active']!==1){throw new RuntimeException('Module 0.2.2 actif attendu.');}
 $hooks=[];foreach(['actionPresentCart','actionObjectOrderAddBefore','actionFrontControllerInitBefore','actionCartUpdateQuantityBefore'] as $name){$r=$rows('SELECT h.id_hook,h.active,hm.id_module,hm.id_shop FROM `'.$prefix.'hook` h JOIN `'.$prefix.'hook_module` hm ON hm.id_hook=h.id_hook WHERE LOWER(h.name)=LOWER(?) AND hm.id_module=? AND hm.id_shop=1',[$name,(int)$module[0]['id_module']]);if(count($r)!==1||(int)$r[0]['active']!==1){throw new RuntimeException('Accroche du panier ou protection non confirmée : '.$name);}$hooks[$name]=$r;}
 $db->exec('COMMIT');
 echo "DEV confirmé — rendu et miniatures personnalisées conservés.\nPLAN — Carrure → Cadran → Aiguilles → Bracelet. Même ordre pour les vues de détail.\nSurvol des vignettes : gris clair #F2F2F2 ; sélection bleue et focus clavier conservés.\n";
 echo count($changes)." fichiers d'interface à publier ; adresses de cache CSS/JS renouvelées.\nAucune écriture MySQL, aucune installation native, aucun accès PROD. Prix, stocks, configurations, images et blocages de commande conservés.\n";
 if($verify){echo "CONTRÔLE TERMINÉ — Aucune écriture dans le site ou MySQL.\n";exit;}
 if(!$changes){echo "DÉJÀ APPLIQUÉ — Aucun fichier à modifier.\n";exit;}
 $tty=fopen('/dev/tty','r');if(!$tty){throw new RuntimeException('Terminal interactif requis.');}echo 'Tape OUI pour appliquer ces ajustements sur DEV : ';$answer=fgets($tty);fclose($tty);if(trim((string)$answer)!=='OUI'){echo "ANNULÉ — Aucune écriture.\n";exit;}
 $private=$home.'/watchys-sauvegardes-privees';if(!is_dir($private)||is_link($private)||realpath($private)!==$private||is_link($private.'/module-watchys.lock')){throw new RuntimeException('Dossier privé ou verrou non autorisé.');}
 $lock=fopen($private.'/module-watchys.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){throw new RuntimeException('Autre mise à jour Watchys en cours.');}
 umask(0077);$backup=$private.'/ordre-survol-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));if(!mkdir($backup,0700)){throw new RuntimeException('Dossier journal non créé.');}
 $journal=$backup.'/journal.json';$log=['status'=>'preparation','moduleBefore'=>$module,'hooksBefore'=>$hooks,'files'=>[]];
 $save=static function()use(&$log,$journal){$json=json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($journal,$json,LOCK_EX)!==strlen($json)){throw new RuntimeException('Journal non enregistré.');}};$save();echo 'Journal : '.$journal."\n";
 foreach($changes as $i=>&$change){choicesSafePath($change['target'],$site);if(hash_file('sha256',$change['target'])!==$change['beforeSha256']){throw new RuntimeException('Interface modifiée depuis le plan.');}$change['backup']=$backup.'/'.$i.'.before';choicesCopy($change['target'],$change['backup']);}unset($change);$log['files']=$changes;$save();
 foreach($manifest['guards'] as $f=>$h){if(hash_file('sha256',$target.'/'.$f)!==$h){throw new RuntimeException('Code métier ou PNG modifié depuis le plan.');}}
 $phase='publication de l’interface';
 foreach($changes as $change){choicesSafePath($change['target'],$site);if(hash_file('sha256',$change['target'])!==$change['beforeSha256']){throw new RuntimeException('Interface modifiée avant publication.');}$tmp=$change['target'].'.watchys-'.bin2hex(random_bytes(4));choicesCopy($change['source'],$tmp);if(!chmod($tmp,0644)||!rename($tmp,$change['target'])){throw new RuntimeException('Publication échouée.');}$published[]=$change;if(hash_file('sha256',$change['target'])!==$change['afterSha256']){throw new RuntimeException('Fichier publié non conforme.');}if(function_exists('opcache_invalidate')){@opcache_invalidate($change['target'],true);}}
 foreach($manifest['patch'] as $f=>$h){if(hash_file('sha256',$target.'/'.$f)!==$h){throw new RuntimeException('Interface finale non conforme.');}}
 foreach($manifest['guards'] as $f=>$h){if(hash_file('sha256',$target.'/'.$f)!==$h){throw new RuntimeException('Code métier ou PNG non conservé.');}}
 if(hash_file('sha256',$target.'/config/catalog.json')!==$manifest['catalogSha256']){throw new RuntimeException('Catalogue non conservé.');}
 $log['status']='succes';$save();
 echo "SUCCÈS — Ordre des choix et survol gris publiés ; fichiers relus. Cache CSS/JS renouvelé.\nRendu, miniatures personnalisées, prix et stocks conservés. Recharge le configurateur pour contrôler. Aucune écriture MySQL ni aucun accès PROD.\n";
}catch(Throwable $e){
 if($db&&$db->inTransaction()){try{$db->rollBack();}catch(Throwable $ignored){}}
 $restored=true;foreach(array_reverse($published) as $c){try{if(hash_file('sha256',$c['target'])!==$c['afterSha256']){throw new RuntimeException('Code modifié après publication.');}$tmp=$c['target'].'.restore-'.bin2hex(random_bytes(4));choicesCopy($c['backup'],$tmp);if(!chmod($tmp,0644)||!rename($tmp,$c['target'])){throw new RuntimeException('Restauration échouée.');}if(function_exists('opcache_invalidate')){@opcache_invalidate($c['target'],true);}}catch(Throwable $ignored){$restored=false;}}
 if($save){try{$log['status']=$restored?'arret-interface-restauree':'arret-restauration-a-controler';$log['phase']=$phase;$save();}catch(Throwable $ignored){}}
 echo 'ARRÊT pendant : '.$phase."\n";echo $e instanceof PDOException?"Erreur MySQL (identifiants masqués).\n":$e->getMessage()."\n";echo $restored?"Interface antérieure conservée ou rétablie. Aucun succès à déduire.\n":"Restauration à contrôler dans le journal. Aucun succès à déduire.\n";exit(1);
}
