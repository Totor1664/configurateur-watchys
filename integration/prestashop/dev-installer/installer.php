<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
ini_set('display_errors','0'); set_time_limit(0);
echo "========== DÉBUT — MODULE WATCHYS DEV 0.1.0 ==========\n";
register_shutdown_function(function(){echo "========== FIN — MODULE WATCHYS DEV ==========\n";});
$stage='contrôle'; $published=false; $module=null; $log=[]; $journal=null;
try {
    if(count($argv)>2 || (isset($argv[1]) && $argv[1]!=='--verifier')) { throw new RuntimeException('Utilisation : php installer.php [--verifier]'); }
    $home=getenv('HOME'); $site=realpath($home.'/sites/dev.watchys.ch'); $source=__DIR__.'/watchysconfigurator';
    if(!$site || $site===realpath($home.'/web') || !is_dir($source)) { throw new RuntimeException('DEV ou paquet introuvable.'); }
    $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    $checkFile=function($path,$sha) { if(!is_file($path)||is_link($path)||hash_file('sha256',$path)!==$sha) { throw new RuntimeException('Fichier absent ou différent : '.basename($path)); } };
    foreach($manifest['files'] as $file=>$sha) {
        if(!preg_match('~^[A-Za-z0-9_./-]+$~',$file)||str_contains($file,'..')) { throw new RuntimeException('Chemin de paquet invalide.'); }
        $checkFile($source.'/'.$file,$sha);
        if(str_ends_with($file,'.php')) {
            $out=[];$code=0;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($source.'/'.$file).' 2>&1',$out,$code);
            if($code!==0) { throw new RuntimeException('Syntaxe PHP invalide : '.$file); }
        }
    }
    $out=[];$code=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($source.'/tests/catalog.php').' 2>&1',$out,$code);
    if($code!==0) { throw new RuntimeException('Tests catalogue échoués.'); }
    echo implode("\n",$out)."\n";
    $assets=[];
    foreach($manifest['assets'] as $asset) {
        if(!in_array($asset['package'],['watchys-7750-variantes','watchys-carrures-7750-vues'],true)||!str_starts_with($asset['file'],'images/')||str_contains($asset['file'],'..')) { throw new RuntimeException('Source PNG invalide.'); }
        $package=realpath($home.'/'.$asset['package']); $path=$package ? realpath($package.'/'.$asset['file']) : false;
        if(!$path||!str_starts_with($path,$package.'/images/')) { throw new RuntimeException('Paquet PNG absent : '.$asset['package']); }
        $checkFile($path,$asset['sha256']);$assets[]=['source'=>$path,'name'=>$asset['target'],'sha256'=>$asset['sha256']];
    }
    echo count($assets)." PNG sources vérifiés. Aucun PNG modifié.\n";
    $config=require $site.'/app/config/parameters.php';$p=$config['parameters'];
    if(!preg_match('/^[a-zA-Z0-9_]+$/',$p['database_prefix'])) { throw new RuntimeException('Préfixe invalide.'); }
    $port=!empty($p['database_port'])?';port='.(int)$p['database_port']:'';
    $db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$prefix=$p['database_prefix'];
    $rows=$db->query('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1')->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows) { throw new RuntimeException('Boutique introuvable.'); }
    foreach($rows as $r) { if((int)$r['id_shop']!==1||$r['domain']!=='dev.watchys.ch'||$r['domain_ssl']!=='dev.watchys.ch') { throw new RuntimeException('Base différente du DEV attendu.'); } }
    if((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1) { throw new RuntimeException('Multiboutique non prévue.'); }
    $target=$site.'/modules/watchysconfigurator';
    $installed=(bool)$db->query("SELECT id_module FROM `".$prefix."module` WHERE name='watchysconfigurator'")->fetchColumn();
    if(is_dir($target)||file_exists($target)||is_link($target)||$installed) { throw new RuntimeException('Un module Watchys existe déjà. Aucune mise à jour automatique : transmettre ce bilan.'); }
    $catalog=json_decode(file_get_contents($source.'/config/catalog.json'),true,512,JSON_THROW_ON_ERROR);$components=[];
    foreach($catalog['models'] as $m) { $components[]=$m['movement']['component'];foreach($m['options'] as $opts) { foreach($opts as $o) {$components[]=$o['component'];} } }
    foreach($components as $c) {
        $stmt=$db->prepare('SELECT p.reference,ps.active,ps.available_for_order FROM `'.$prefix.'product` p JOIN `'.$prefix.'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=1 WHERE p.id_product=?');$stmt->execute([(int)$c['productId']]);$r=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$r||isset($c['expectedReference'])&&$r['reference']!==$c['expectedReference']) { throw new RuntimeException('Article absent ou référence différente : '.$c['productId']); }
    }
    echo "DEV confirmé : 24 composants, 23 variantes.\nPLAN — installation de watchysconfigurator 0.1.0 et copie de 23 PNG.\n";
    echo "Création des seules entrées natives du nouveau module. Aucun article, prix, stock, commande, thème ni ancien module modifié.\n";
    echo "Accès par lien direct et bouton Configurer du module. Aucun bouton de panier dans cette version. PROD non consultée.\n";
    if(isset($argv[1])) { echo "CONTRÔLE TERMINÉ — Aucune écriture dans le site ni dans MySQL.\n"; exit; }
    echo 'Tape OUI pour installer ce module sur DEV : ';
    if(trim((string)fgets(STDIN))!=='OUI') { echo "ANNULÉ — Aucune modification.\n";exit; }
    $stage='installation';$private=$home.'/watchys-sauvegardes-privees';umask(0077);
    if(!is_dir($private)||is_link($private)) { throw new RuntimeException('Dossier de journaux privé indisponible.'); }
    $lock=fopen($private.'/module-watchys-install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)) { throw new RuntimeException('Installation déjà en cours.'); }
    $journal=$private.'/module-watchys-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
    $log=['status'=>'installation démarrée, succès non confirmé','version'=>'0.1.0','target'=>$target,'createdFiles'=>[]];
    $save=function()use(&$log,$journal){$bytes=json_encode($log,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($journal,$bytes,LOCK_EX)!==strlen($bytes)) {throw new RuntimeException('Journal non enregistré.');}};$save();echo 'Journal : '.$journal."\n";
    $staging=$site.'/modules/.watchys-stage-'.bin2hex(random_bytes(6));
    if(!mkdir($staging,0755)||!chmod($staging,0755)) { throw new RuntimeException('Dossier temporaire non créé.'); }
    $copy=function($from,$to,$sha)use($checkFile,&$log,$save){
        $checkFile($from,$sha);$dir=dirname($to);
        if(!is_dir($dir)&&!mkdir($dir,0755,true)) {throw new RuntimeException('Dossier non créé.');}
        // mkdir recursive inherits umask: explicitly make all module directories readable.
        $walk=$dir;while(str_contains($walk,'/.watchys-stage-')) {chmod($walk,0755);$walk=dirname($walk);}
        $in=fopen($from,'rb');$out=fopen($to,'xb');if(!$in||!$out){throw new RuntimeException('Copie exclusive impossible.');}
        $ok=stream_copy_to_stream($in,$out);fclose($in);fclose($out);chmod($to,0644);
        if($ok===false||hash_file('sha256',$to)!==$sha) {throw new RuntimeException('Copie non validée.');}$log['createdFiles'][]=$to;$save();
    };
    foreach($manifest['files'] as $file=>$sha) {$copy($source.'/'.$file,$staging.'/'.$file,$sha);}
    foreach($assets as $asset) {if(!preg_match('/^[0-9]+-front\.png$/',$asset['name'])) {throw new RuntimeException('Nom PNG invalide.');}$copy($asset['source'],$staging.'/views/img/'.$asset['name'],$asset['sha256']);}
    if(file_exists($target)||!rename($staging,$target)) {throw new RuntimeException('Publication des fichiers refusée.');}$published=true;
    $_SERVER['HTTP_HOST']='dev.watchys.ch';$_SERVER['SERVER_NAME']='dev.watchys.ch';$_SERVER['HTTPS']='on';$_SERVER['REQUEST_URI']='/';$_SERVER['REMOTE_ADDR']='127.0.0.1';
    require $site.'/config/config.inc.php';
    Shop::setContext(Shop::CONTEXT_SHOP,1);$context=Context::getContext();$context->shop=new Shop(1);
    require_once $target.'/watchysconfigurator.php';
    $module=new Watchysconfigurator();
    if(!$module->isDev()||!$module->install()||!Module::isInstalled('watchysconfigurator')||!Module::isEnabled('watchysconfigurator')) {throw new RuntimeException('Installation native PrestaShop non confirmée.');}
    $presentation=$module->presentation();if(count($presentation['models'][0]['groups'])!==4) {throw new RuntimeException('Lecture des variantes non confirmée.');}
    $log['status']='installation confirmée';$log['moduleId']=$module->id;$log['createdFiles']=array_map(fn($p)=>str_replace($staging,$target,$p),$log['createdFiles']);$save();
    echo "SUCCÈS — Module Watchys 0.1.0 installé sur DEV. 23 PNG copiés et vérifiés.\n";
    echo "Ouvrir : https://dev.watchys.ch/index.php?fc=module&module=watchysconfigurator&controller=configure\n";
    echo "Disponibilités et prix à valider visuellement. Panier et stocks non modifiés par ce module.\n";
} catch(Throwable $e) {
    echo 'ARRÊT pendant : '.$stage."\n";
    echo $e instanceof PDOException ? "Erreur de base de données (identifiants masqués).\n" : $e->getMessage()."\n";
    if($published) {echo "Les fichiers du module ont été copiés. Installation éventuellement partielle : transmettre ce bilan avant de relancer.\n";}
    exit(1);
}
