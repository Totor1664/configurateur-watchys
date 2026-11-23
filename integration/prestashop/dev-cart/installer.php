<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');set_time_limit(0);
echo "========== DÉBUT — PANIER WATCHYS DEV 0.2.0 ==========\n";
register_shutdown_function(function(){echo "========== FIN — PANIER WATCHYS DEV ==========\n";});
$db=null;$published=[];$journal=null;$save=null;$nativeApplied=false;$lock=null;$phase='contrôle';
function copyPrivate($src,$dst){$in=fopen($src,'rb');$out=fopen($dst,'xb');if(!$in||!$out){throw new RuntimeException('Copie exclusive impossible.');}$n=stream_copy_to_stream($in,$out);fclose($in);fflush($out);fclose($out);if($n!==filesize($src)||hash_file('sha256',$src)!==hash_file('sha256',$dst)){throw new RuntimeException('Copie non conforme.');}}
function safePath($path,$root){$parent=realpath(dirname($path));if(!$parent||$parent!==dirname($path)||!str_starts_with($parent.'/',$root.'/')||is_link($path)||(file_exists($path)&&!is_file($path))){throw new RuntimeException('Chemin non autorisé.');}}
try{
    if(count($argv)>2||(count($argv)===2&&$argv[1]!=='--verifier')){throw new RuntimeException('Utilisation : php installer.php [--verifier]');}
    $verify=($argv[1]??'')==='--verifier';$home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');
    if(!$site||$site===realpath($home.'/web')||is_link($home.'/sites/dev.watchys.ch')){throw new RuntimeException('DEV non confirmé.');}
    $target=$site.'/modules/watchysconfigurator';$manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);$baseline=json_decode(file_get_contents(__DIR__.'/baseline.json'),true,512,JSON_THROW_ON_ERROR);
    if($manifest['version']!=='0.2.0'||$baseline['version']!=='0.2.0'){throw new RuntimeException('Version du paquet invalide.');}
    foreach($manifest['checks'] as $file=>$hash){if(!preg_match('~^[A-Za-z0-9_./-]+$~',$file)||str_contains($file,'..')||is_link(__DIR__.'/'.$file)||!is_file(__DIR__.'/'.$file)||hash_file('sha256',__DIR__.'/'.$file)!==$hash){throw new RuntimeException('Paquet modifié : '.$file);}}
    if(!function_exists('proc_open')){throw new RuntimeException('Sous-processus PHP nécessaire pour isoler les contrôles avant le bootstrap natif.');}
    foreach(['catalog.php','cart-policy.php','cart-service.php','service.php','request.php'] as $test){
        $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/module/tests/'.$test],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__);
        if(!is_resource($process)){throw new RuntimeException('Contrôle PHP non démarré.');}
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
        if($exit!==0||str_contains($out.$err,'Fatal error:')){throw new RuntimeException('Contrôle isolé échoué : '.$test);}
        echo $out;
    }
    foreach($baseline['assets'] as $file=>$hash){safePath($target.'/'.$file,$site);if(!is_file($target.'/'.$file)||hash_file('sha256',$target.'/'.$file)!==$hash){throw new RuntimeException('Visuel validé différent : '.$file);}}
    foreach($baseline['expected'] as $file=>$hash){
        safePath($target.'/'.$file,$site);$current=is_file($target.'/'.$file)?hash_file('sha256',$target.'/'.$file):null;
        $after=$manifest['files'][$file]??null;
        if($current!==$hash&&($after===null||$current!==$after)){throw new RuntimeException('Code installé différent : '.$file);}
    }
    $changes=[];
    foreach($manifest['files'] as $file=>$hash){
        safePath($target.'/'.$file,$site);$current=is_file($target.'/'.$file)?hash_file('sha256',$target.'/'.$file):null;
        if(!isset($baseline['expected'][$file])&&$current!==null&&$current!==$hash){throw new RuntimeException('Fichier nouveau déjà présent avec un autre contenu.');}
        if($current!==$hash){$changes[]=['relative'=>$file,'target'=>$target.'/'.$file,'source'=>__DIR__.'/module/'.$file,'beforeSha256'=>$current,'afterSha256'=>$hash];}
    }
    foreach(['bin/console','app/AppKernel.php'] as $f){if(!is_file($site.'/'.$f)){throw new RuntimeException('Bootstrap natif incomplet.');}}
    $cfg=require $site.'/app/config/parameters.php';$p=$cfg['parameters'];$prefix=$p['database_prefix'];
    if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new RuntimeException('Préfixe invalide.');}
    $port=empty($p['database_port'])?'':';port='.(int)$p['database_port'];$db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($p,$cfg);
    $rows=function($sql,$args=[])use($db){$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);};
    $db->exec('START TRANSACTION READ ONLY');
    $urls=$rows('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1');
    if(!$urls||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1){throw new RuntimeException('Boutique unique non confirmée.');}
    foreach($urls as $u){if((int)$u['id_shop']!==1||$u['domain']!=='dev.watchys.ch'||$u['domain_ssl']!=='dev.watchys.ch'){throw new RuntimeException('Base DEV non confirmée.');}}
    $state=$rows('SELECT id_module,version,active FROM `'.$prefix."module` WHERE name='watchysconfigurator'");
    if(count($state)!==1||!in_array($state[0]['version'],['0.1.1','0.2.0'],true)||(int)$state[0]['active']!==1){throw new RuntimeException('Module DEV actif 0.1.1 ou 0.2.0 attendu.');}
    $flagsBefore=$rows('SELECT * FROM `'.$prefix."configuration` WHERE name IN ('WATCHYS_CART_DEV_READY','PS_CUSTOMIZATION_FEATURE_ACTIVE')");
    $hooksBefore=$rows('SELECT * FROM `'.$prefix.'hook_module` WHERE id_module='.(int)$state[0]['id_module']);
    $support=$rows('SELECT p.id_product,p.reference,ps.id_tax_rules_group,ps.id_category_default FROM `'.$prefix.'product` p JOIN `'.$prefix.'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=1 WHERE p.id_product=7753');if(count($support)!==1){throw new RuntimeException('Kit de référence pour les règles fiscales absent.');}
    foreach(['cart','cart_product','cart_cart_rule','customization','customized_data','product','product_shop','product_lang','category_product','stock_available','customization_field','customization_field_lang','module','hook','hook_module','hook_module_exceptions','configuration','configuration_lang'] as $table){
        $r=$rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.$table]);if(count($r)!==1||strcasecmp($r[0]['ENGINE'],'InnoDB')!==0){throw new RuntimeException('Table transactionnelle attendue : '.$table);}
    }
    $columns=array_column($rows('SHOW COLUMNS FROM `'.$prefix.'customized_data`'),'Field');foreach(['id_module','price','weight'] as $col){if(!in_array($col,$columns,true)){throw new RuntimeException('Personnalisation tarifaire native absente.');}}
    $version=$db->query('SELECT value FROM `'.$prefix."configuration` WHERE name='PS_VERSION_DB' LIMIT 1")->fetchColumn();if($version!=='8.2.7'){throw new RuntimeException('PrestaShop 8.2.7 attendu.');}
    $db->commit();$catalogPath=$target.'/config/catalog.json';$catalogHash=hash_file('sha256',$catalogPath);
    echo "DEV confirmé. Rendu validé : 29 PNG et code du gabarit conservés.\n";
    echo "PLAN — vrai panier DEV, sans validation de commande\n";
    echo count($changes)." fichiers de module à publier ; trois tables Watchys ; une fiche technique masquée par modèle (7750 actuellement).\n";
    echo "Six champs natifs détailleront chaque configuration. Prix serveur : 190 / 510 / 610 CHF HT selon formule.\n";
    echo 'Règles fiscales et catégorie reprises en lecture de la fiche 7753 : taxe '.$support[0]['id_tax_rules_group'].', catégorie '.$support[0]['id_category_default'].". La fiche 7753 reste inchangée.\n";
    echo "Accroches : affichage de personnalisation, contrôle de quantité, blocage commande et paiement pour les paniers Watchys.\n";
    echo "Anciennes fiches, stocks des composants, statuts des variantes, tarifs, refonte et ancien module conservés. Aucun accès PROD.\n";
    if($verify){echo "CONTRÔLE TERMINÉ — Aucune écriture, aucun bootstrap PrestaShop.\n";exit;}
    echo 'Tape OUI pour préparer le vrai panier sur DEV : ';if(trim((string)fgets(STDIN))!=='OUI'){echo "ANNULÉ — Aucune modification.\n";exit;}
    $private=$home.'/watchys-sauvegardes-privees';if(!is_dir($private)||is_link($private)||realpath($private)!==$private||is_link($private.'/module-watchys.lock')){throw new RuntimeException('Dossier privé ou verrou non autorisé.');}
    $lock=fopen($private.'/module-watchys.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){throw new RuntimeException('Autre migration Watchys en cours.');}
    umask(0077);$backup=$private.'/panier-watchys-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));if(!mkdir($backup,0700)){throw new RuntimeException('Dossier de sauvegarde non créé.');}
    $journal=$backup.'/journal.json';$log=['status'=>'preparation','nativeBefore'=>$state,'flagsBefore'=>$flagsBefore,'hooksBefore'=>$hooksBefore,'taxSource'=>$support,'files'=>[]];
    $save=function()use(&$log,$journal){$bytes=json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($journal,$bytes,LOCK_EX)!==strlen($bytes)){throw new RuntimeException('Journal non enregistré.');}};$save();echo 'Journal : '.$journal."\n";
    foreach($changes as $i=>&$change){safePath($change['target'],$site);if((is_file($change['target'])?hash_file('sha256',$change['target']):null)!==$change['beforeSha256']){throw new RuntimeException('Code changé depuis le plan.');}
        $change['backup']=null;if($change['beforeSha256']!==null){$change['backup']=$backup.'/'.$i.'.before';copyPrivate($change['target'],$change['backup']);}
        $change['prepared']=$backup.'/'.$i.'.after';copyPrivate($change['source'],$change['prepared']);
    }unset($change);$log['files']=$changes;$save();
    if(hash_file('sha256',$catalogPath)!==$catalogHash){throw new RuntimeException('Catalogue changé depuis le plan.');}
    foreach($baseline['assets'] as $f=>$h){if(hash_file('sha256',$target.'/'.$f)!==$h){throw new RuntimeException('PNG changé depuis le plan.');}}
    $phase='publication du code';
    foreach($changes as $change){
        $temp=$change['target'].'.watchys-'.bin2hex(random_bytes(4));copyPrivate($change['prepared'],$temp);if(!chmod($temp,0644)){unlink($temp);throw new RuntimeException('Droits du fichier non confirmés.');}
        if(!rename($temp,$change['target'])){unlink($temp);throw new RuntimeException('Publication impossible.');}$published[]=$change;
        if(hash_file('sha256',$change['target'])!==$change['afterSha256']){throw new RuntimeException('Fichier publié non conforme.');}
        if(function_exists('opcache_invalidate')){@opcache_invalidate($change['target'],true);}
    }
    $db=null;$phase='préparation native du panier';
    require $site.'/config/config.inc.php';
    global $kernel;$kernel=new AppKernel('prod',false);$kernel->boot();
    Shop::setContext(Shop::CONTEXT_SHOP,1);$context=Context::getContext();$context->shop=new Shop(1);
    $context->language=new Language((int)Configuration::get('PS_LANG_DEFAULT'));$context->currency=new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));
    if(!$context->employee){$context->employee=new Employee(42);}
    require_once $target.'/watchysconfigurator.php';require_once $target.'/classes/WatchysCartSetup.php';$module=new Watchysconfigurator();
    $result=WatchysCartSetup::apply($module);$nativeApplied=true;
    $log['status']='valide';$log['result']=$result;$save();
    echo 'SUCCÈS — Produit technique '.$result['productId'].' | '.$result['reference']." ; personnalisations et accroches relues.\n";
    echo "Bouton et vrai panier préparés. Rendu validé conservé. Aucun stock de composant décrémenté.\n";
    echo "Commande et paiement des configurations Watchys bloqués pendant les tests du cycle de stock.\n";
}catch(Throwable $e){
    if($db&&$db->inTransaction()){try{$db->rollBack();}catch(Throwable $ignored){}}
    $restored=true;
    if(!$nativeApplied){foreach(array_reverse($published) as $c){try{
        if(hash_file('sha256',$c['target'])!==$c['afterSha256']){throw new RuntimeException('Code modifié après publication.');}
        if($c['backup']===null){if(!unlink($c['target'])){throw new RuntimeException('Nouveau fichier non retiré.');}}
        else{$tmp=$c['target'].'.restore-'.bin2hex(random_bytes(4));copyPrivate($c['backup'],$tmp);chmod($tmp,0644);if(!rename($tmp,$c['target'])){throw new RuntimeException('Restauration impossible.');}}
    }catch(Throwable $ignored){$restored=false;}}}
    if($save){try{$log['status']=$nativeApplied?'natif-valide-journal-a-controler':($restored?'arret-code-restaure':'arret-restauration-a-controler');$log['phase']=$phase;$log['errorClass']=get_class($e);$save();}catch(Throwable $ignored){}}
    echo 'ARRÊT pendant : '.$phase."\n";
    echo $e instanceof PDOException?'Erreur MySQL (identifiants masqués).'."\n":$e->getMessage()."\n";
    echo $nativeApplied?"Préparation native validée ; journal final à contrôler.\n":($restored?"Code antérieur rétabli. La préparation native n’est pas confirmée ; conserver le journal et relancer --verifier.\n":"Code partiel : contrôler le journal avant toute reprise.\n");
    exit(1);
}
