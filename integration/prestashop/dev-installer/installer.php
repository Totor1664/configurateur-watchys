<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');set_time_limit(0);error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
echo "========== DÉBUT — MODULE WATCHYS DEV 0.1.1 ==========\n";
register_shutdown_function(function(){echo "========== FIN — MODULE WATCHYS DEV ==========\n";});
$phase='contrôle';$journal=null;$log=[];$published=false;$backup=null;
try {
    require __DIR__.'/DevFiles.php';
    if(count($argv)>2||(isset($argv[1])&&$argv[1]!=='--verifier')){throw new RuntimeException('Utilisation : php installer.php [--verifier]');}
    if(version_compare(PHP_VERSION,'8.2.0','<')){throw new RuntimeException('PHP 8.2 minimum requis.');}
    $home=getenv('HOME');$site=realpath($home.'/sites/dev.watchys.ch');$source=__DIR__.'/watchysconfigurator';
    if(!$site||$site===realpath($home.'/web')||!is_dir($source)){throw new RuntimeException('DEV ou paquet introuvable.');}
    $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if(($manifest['version']??'')!=='0.1.1'){throw new RuntimeException('Version du paquet inattendue.');}
    $legacy=json_decode(file_get_contents(__DIR__.'/legacy-0.1.0.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($manifest['files'] as $file=>$sha){
        if(!preg_match('~^[A-Za-z0-9_./-]+$~',$file)||str_contains($file,'..')||!is_file($source.'/'.$file)||is_link($source.'/'.$file)||hash_file('sha256',$source.'/'.$file)!==$sha){throw new RuntimeException('Fichier du paquet invalide : '.$file);}
        if(str_ends_with($file,'.php')){$out=[];$code=0;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($source.'/'.$file).' 2>&1',$out,$code);if($code!==0){throw new RuntimeException('Syntaxe PHP invalide : '.$file);}}
    }
    // Validation runs in child PHP processes below; do not preload module classes from the private package.
    $catalogData=json_decode(file_get_contents($source.'/config/catalog.json'),true,512,JSON_THROW_ON_ERROR);$models=$catalogData['models'];
    foreach(['catalog.php','service.php','request.php','schema.php'] as $test){$out=[];$code=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($source.'/tests/'.$test).' 2>&1',$out,$code);if($code!==0){throw new RuntimeException('Tests échoués : '.$test);}echo implode("\n",$out)."\n";}
    $assets=[];
    foreach($manifest['assets'] as $a){
        if(!in_array($a['package'],['watchys-7750-variantes','watchys-carrures-7750-vues'],true)||!str_starts_with($a['file'],'images/')||str_contains($a['file'],'..')||!preg_match('/^[0-9]+-front\.png$/',$a['target'])){throw new RuntimeException('Source PNG invalide.');}
        $package=realpath($home.'/'.$a['package']);$file=$package?realpath($package.'/'.$a['file']):false;
        if(!$file||!str_starts_with($file,$package.'/images/')||hash_file('sha256',$file)!==$a['sha256']){throw new RuntimeException('PNG absent ou différent : '.$a['file']);}
        $a['source']=$file;$assets[]=$a;
    }
    if(realpath($site.'/modules')!==$site.'/modules'){throw new RuntimeException('Dossier modules symbolique ou hors DEV refusé.');}
    $target=$site.'/modules/watchysconfigurator';
    $connect=function()use($site){
        $conf=require $site.'/app/config/parameters.php';$p=$conf['parameters'];if(!preg_match('/^[a-zA-Z0-9_]+$/',$p['database_prefix'])){throw new RuntimeException('Préfixe invalide.');}
        $port=!empty($p['database_port'])?';port='.(int)$p['database_port']:'';
        $db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$prefix=$p['database_prefix'];
        $rows=$db->query('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1')->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1){throw new RuntimeException('Boutique unique non confirmée.');}
        foreach($rows as $r){if((int)$r['id_shop']!==1||$r['domain']!=='dev.watchys.ch'||$r['domain_ssl']!=='dev.watchys.ch'){throw new RuntimeException('Base non DEV ; arrêt.');}}
        $versions=$db->query("SELECT DISTINCT value FROM `".$prefix."configuration` WHERE name='PS_VERSION_DB'")->fetchAll(PDO::FETCH_COLUMN);
        if(count($versions)!==1||version_compare($versions[0],'8.2.0','<')||version_compare($versions[0],'9.0.0','>=')){throw new RuntimeException('Version de base PrestaShop 8.2+ non confirmée.');}
        return [$db,$prefix];
    };
    $moduleState=function($db,$prefix){
        $r=$db->query("SELECT * FROM `".$prefix."module` WHERE name='watchysconfigurator'")->fetch(PDO::FETCH_ASSOC);
        if(!$r){return null;}if(!in_array($r['version'],['0.1.0','0.1.1'],true)){throw new RuntimeException('Version installée non prise en charge.');}
        $id=(int)$r['id_module'];$links=$db->query('SELECT * FROM `'.$prefix.'module_shop` WHERE id_module='.$id.' ORDER BY id_shop')->fetchAll(PDO::FETCH_ASSOC);
        foreach($links as $link){if((int)$link['id_shop']!==1){throw new RuntimeException('Module lié à une autre boutique.');}}
        return ['module'=>$r,'shops'=>$links];
    };
    [$db,$prefix]=$connect();$state=$moduleState($db,$prefix);$files=WatchysDevFiles::checkExisting($target,$manifest['files'],$legacy,$assets);
    $components=[];
    foreach($models as $m){$components[]=$m['movement']['component'];foreach($m['options'] as $options){foreach($options as $o){$components[]=$o['component'];}}}
    foreach($components as $c){$s=$db->prepare('SELECT p.reference FROM `'.$prefix.'product` p JOIN `'.$prefix.'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=1 WHERE p.id_product=?');$s->execute([$c['productId']]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r||(isset($c['expectedReference'])&&$r['reference']!==$c['expectedReference'])){throw new RuntimeException('Article absent ou référence différente : '.$c['productId']);}}
    $db=null;
    $action=$state?($state['module']['version']==='0.1.0'?'mise à jour 0.1.0 → 0.1.1':'reprise/vérification 0.1.1'):(is_dir($target)?'reprise de fichiers présents sans installation confirmée':'première installation');
    echo "\nDEV confirmé. État : ".$action.".\nPLAN — module Watchys 0.1.1\n";
    echo count($assets).' PNG vérifiés ; '.count($components)." correspondances composants contrôlées.\n";
    echo "Copie vérifiée, sauvegarde du dossier existant si présent, installation/activation du seul module Watchys.\n";
    echo "Un journal privé conservera l'état natif du module et les emplacements de sauvegarde.\n";
    echo "Articles, prix, stocks, refonte et ancien module conservés. Aucune connexion PROD. Panier non raccordé dans cette version.\n";
    $private=$home.'/watchys-sauvegardes-privees';if(!is_dir($private)||is_link($private)){throw new RuntimeException('Dossier privé des sauvegardes indisponible.');}
    $needed=array_sum(array_map(fn($a)=>filesize($a['source']),$assets))+20*1024*1024;
    if(disk_free_space($site.'/modules')<$needed){throw new RuntimeException('Espace disque insuffisant pour préparer les fichiers.');}
    if(isset($argv[1])){echo "CONTRÔLE TERMINÉ — Aucune écriture dans le site, les journaux ou MySQL.\n";exit;}
    echo 'Tape OUI pour appliquer ce plan sur DEV : ';if(trim((string)fgets(STDIN))!=='OUI'){echo "ANNULÉ — Aucune modification.\n";exit;}
    $phase='préparation';umask(0077);if(!chmod($private,0700)){throw new RuntimeException('Protection du dossier privé non confirmée.');}$lockFile=$private.'/module-watchys-install.lock';if(is_link($lockFile)){throw new RuntimeException('Verrou symbolique refusé.');}
    $lock=fopen($lockFile,'c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){throw new RuntimeException('Installation déjà en cours.');}
    [$db,$prefixNow]=$connect();
    if($prefixNow!==$prefix||$moduleState($db,$prefix)!==$state||WatchysDevFiles::entries($target)!==$files){throw new RuntimeException('État changé depuis le plan ; relancer le contrôle.');}
    $run=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));$journal=$private.'/module-watchys-'.$run.'.json';$backup=$private.'/module-watchys-'.$run.'-avant';$stage=$site.'/modules/.watchys-stage-'.$run;
    $log=['version'=>'0.1.1','status'=>'preparation','action'=>$action,'target'=>$target,'staging'=>$stage,'backup'=>is_dir($target)?$backup:null,'beforeModule'=>$state,'beforeFiles'=>$files,'createdFiles'=>[]];
    WatchysDevFiles::saveJournal($journal,$log);echo 'Journal : '.$journal."\n";
    if(!mkdir($stage,0755)||!chmod($stage,0755)){throw new RuntimeException('Dossier temporaire non créé.');}
    foreach($manifest['files'] as $file=>$sha){WatchysDevFiles::copy($source.'/'.$file,$stage.'/'.$file,$sha);$log['createdFiles'][]=$target.'/'.$file;}
    foreach($assets as $a){WatchysDevFiles::copy($a['source'],$stage.'/views/img/'.$a['target'],$a['sha256']);$log['createdFiles'][]=$target.'/views/img/'.$a['target'];}
    $log['status']='fichiers-prepares';WatchysDevFiles::saveJournal($journal,$log);$phase='publication';
    WatchysDevFiles::publish($stage,$target,$backup);$published=true;
    $log['status']='fichiers-publies';WatchysDevFiles::saveJournal($journal,$log);
    $phase='installation native';$_SERVER['HTTP_HOST']='dev.watchys.ch';$_SERVER['SERVER_NAME']='dev.watchys.ch';$_SERVER['HTTPS']='on';$_SERVER['REQUEST_URI']='/';$_SERVER['REMOTE_ADDR']='127.0.0.1';
    require $site.'/config/config.inc.php';
    if(version_compare(_PS_VERSION_,'8.2.0','<')||version_compare(_PS_VERSION_,'9.0.0','>=')){throw new RuntimeException('Version PrestaShop non prise en charge.');}
    Shop::setContext(Shop::CONTEXT_SHOP,1);$context=Context::getContext();$context->shop=new Shop(1);require_once $target.'/watchysconfigurator.php';$module=new Watchysconfigurator();
    if(!$module->isDev()){throw new RuntimeException('Contexte natif non DEV.');}
    if(!$state){if(!$module->install()){throw new RuntimeException('Installation native incomplète ; diagnostic puis reprise possibles.');}}
    else {if((int)$module->id!==(int)$state['module']['id_module']||!$module->enable()){throw new RuntimeException('Activation du module non confirmée.');}}
    if(!Module::isInstalled('watchysconfigurator')||!Module::isEnabled('watchysconfigurator')){throw new RuntimeException('État installé/actif non confirmé.');}
    $presentation=$module->presentation();if(count($presentation['models'])!==count($models)){throw new RuntimeException('Lecture des modèles non confirmée.');}
    if($state){$s=$db->prepare('UPDATE `'.$prefix.'module` SET version=? WHERE id_module=? AND name=? AND version=?');$s->execute(['0.1.1',$module->id,'watchysconfigurator',$state['module']['version']]);}
    $after=$moduleState($db,$prefix);if(!$after||$after['module']['version']!=='0.1.1'||!(bool)$after['module']['active']){throw new RuntimeException('Version native non confirmée.');}
    $log['status']='succes';$log['afterModule']=$after;$log['afterFiles']=WatchysDevFiles::entries($target);WatchysDevFiles::saveJournal($journal,$log);
    echo "SUCCÈS — Module Watchys 0.1.1 installé/actif et relu sur DEV.\n";
    echo "Ouvrir : https://dev.watchys.ch/index.php?fc=module&module=watchysconfigurator&controller=configure\n";
    echo "Prix et stocks des articles inchangés. Valider le montage et les formules dans Alysum.\n";
}catch(Throwable $e){
    $message=$e instanceof PDOException?'Erreur MySQL (identifiants masqués).':$e->getMessage();
    if($journal){$log['status']='echec-reprise-a-controler';$log['failedPhase']=$phase;$log['error']=$message;try{WatchysDevFiles::saveJournal($journal,$log);}catch(Throwable $ignored){echo "Journal final non enregistré.\n";}}
    echo 'ARRÊT pendant : '.$phase."\n".$message."\n";
    if($published){echo "Fichiers publiés ; état natif éventuellement partiel. Conserver le bilan puis lancer --verifier avant toute reprise.\n";}
    if($backup&&is_dir($backup)){echo 'Ancien dossier conservé : '.$backup."\n";}
    exit(1);
}
