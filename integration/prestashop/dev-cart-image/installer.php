<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ini_set('display_errors','0'); error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); set_time_limit(120);
echo "========== DÉBUT — VIGNETTE PANIER WATCHYS DEV ==========\n";
register_shutdown_function(static function () { echo "========== FIN — VIGNETTE PANIER WATCHYS DEV ==========\n"; });
$db=null; $committed=false; $created=[]; $log=null; $save=null; $lock=null;
require __DIR__.'/images.php';
try {
    if (count($argv)>2 || (isset($argv[1]) && $argv[1]!=='--verifier')) { throw new RuntimeException('Utilisation : php installer.php [--verifier]'); }
    $verify=($argv[1]??'')==='--verifier'; $home=realpath(getenv('HOME')); $site=realpath($home.'/sites/dev.watchys.ch');
    if (!$site || is_link($home.'/sites/dev.watchys.ch') || $site===realpath($home.'/web')) { throw new RuntimeException('Dossier DEV non confirmé.'); }
    $source=__DIR__.'/montre-generique.png'; $sourceHash='0912426584e35b0710b872b2c68b12dc1c07c40314b3e7d0cd4fa2565e92a377';
    if (is_link($source) || !is_file($source) || hash_file('sha256',$source)!==$sourceHash) { throw new RuntimeException('Visuel validé absent ou différent.'); }
    foreach (['imagecreatefrompng','imagecreatetruecolor','imagejpeg','imagecopyresampled','getimagesizefromstring'] as $fn) { if (!function_exists($fn)) { throw new RuntimeException('Extension GD requise : '.$fn); } }
    $cfg=require $site.'/app/config/parameters.php'; $p=$cfg['parameters']; $prefix=$p['database_prefix'];
    if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new RuntimeException('Préfixe invalide.'); }
    $port=empty($p['database_port'])?'':';port='.(int)$p['database_port'];
    $db=new PDO("mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",$p['database_user'],$p['database_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); unset($cfg,$p);
    $rows=static function ($sql,$args=[]) use ($db) { $s=$db->prepare($sql); $s->execute($args); return $s->columnCount() ? $s->fetchAll(PDO::FETCH_ASSOC) : []; };
    $canon=static function ($value) use (&$canon) { return is_array($value)?array_map($canon,$value):($value===null?null:(string)$value); };
    $db->exec('START TRANSACTION READ ONLY');
    $urls=$rows('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1');
    if (!$urls || (int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1) { throw new RuntimeException('Boutique unique non confirmée.'); }
    foreach ($urls as $url) { if ((int)$url['id_shop']!==1 || $url['domain']!=='dev.watchys.ch' || $url['domain_ssl']!=='dev.watchys.ch') { throw new RuntimeException('Base DEV non confirmée.'); } }
    foreach (['image','image_shop','image_lang'] as $table) {
        $engine=$rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.$table]);
        if (count($engine)!==1 || strtoupper($engine[0]['ENGINE'])!=='INNODB') { throw new RuntimeException('Table image non transactionnelle : '.$table); }
    }
    $snapshot=static function ($locked=false) use ($rows,$prefix) {
        $state=[];
        foreach (['product','product_shop','product_lang','stock_available'] as $table) {
            $order=['product'=>'id_product','product_shop'=>'id_shop','product_lang'=>'id_shop,id_lang','stock_available'=>'id_stock_available'][$table];
            $state[$table]=$rows('SELECT * FROM `'.$prefix.$table.'` WHERE id_product=8867 ORDER BY '.$order.($locked?' FOR UPDATE':''));
        }
        $state['mapping']=$rows('SELECT * FROM `'.$prefix.'watchys_model_support` WHERE id_product=8867');
        $state['images']=$rows('SELECT * FROM `'.$prefix.'image` WHERE id_product=8867 ORDER BY id_image'.($locked?' FOR UPDATE':''));
        $state['image_shop']=$rows('SELECT * FROM `'.$prefix.'image_shop` WHERE id_product=8867 ORDER BY id_image,id_shop'.($locked?' FOR UPDATE':''));
        return $state;
    };
    $before=$snapshot();
    if (count($before['product'])!==1 || $before['product'][0]['reference']!=='WCFG-7750-41-CARTDEV' || count($before['product_shop'])!==1 || (int)$before['product_shop'][0]['id_shop']!==1 || count($before['mapping'])!==1 || $before['mapping'][0]['model_id']!=='watchys-7750-41mm') { throw new RuntimeException('Support technique 8867 non confirmé.'); }
    if ($before['images'] || $before['image_shop']) { throw new RuntimeException('Le produit technique possède déjà une image. Aucun remplacement automatique ; contrôle séparé requis.'); }
    $types=$rows('SELECT * FROM `'.$prefix.'image_type` WHERE products=1 ORDER BY id_image_type');
    if (!$types) { throw new RuntimeException('Formats produits absents.'); }
    $rendered=[''=>watchysJpegBytes($source)]; $names=[];
    foreach ($types as $type) {
        $name=$type['name'];
        if (!preg_match('/^[A-Za-z0-9_-]+$/D',$name) || isset($names[$name])) { throw new RuntimeException('Nom de miniature invalide ou dupliqué.'); }
        $names[$name]=true; $rendered['-'.$name]=watchysJpegBytes($source,(int)$type['width'],(int)$type['height']);
    }
    $languages=$rows('SELECT DISTINCT id_lang FROM `'.$prefix.'product_lang` WHERE id_product=8867 AND id_shop=1 ORDER BY id_lang');
    if (!$languages) { throw new RuntimeException('Langues du support absentes.'); }
    $fields=array_column($rows('SHOW COLUMNS FROM `'.$prefix.'image`'),'Field');
    $hasScPath=in_array('sc_path',$fields,true);
    $guardFiles=[];
    foreach (['watchysconfigurator.php','config/catalog.json','views/js/preview-reference-v013.js'] as $relative) {
        $path=$site.'/modules/watchysconfigurator/'.$relative;
        if (!is_file($path) || is_link($path)) { throw new RuntimeException('Module installé incomplet.'); } $guardFiles[$path]=hash_file('sha256',$path);
    }
    $db->exec('COMMIT');
    echo "DEV confirmé — produit technique 8867 | WCFG-7750-41-CARTDEV.\n";
    echo 'PLAN — Une image générique en couverture ; '.count($types).' miniatures JPEG aux dimensions du DEV, fond blanc.' . "\n";
    foreach ($types as $type) { echo '  '.$type['name'].' : '.$type['width'].' × '.$type['height']."\n"; }
    echo "Vue validée : cadran bleu, bracelet bleu, titane poli, aiguilles chromées.\n";
    echo "Seules les associations image du support 8867 et ses nouveaux JPEG seront ajoutés.\nPrix, stocks, configurations, rendu et autres articles conservés. Commande et paiement restent bloqués. Aucun accès PROD ni bootstrap PrestaShop.\n";
    if ($verify) { echo "CONTRÔLE TERMINÉ — JPEG générés et vérifiés en mémoire uniquement. Aucune écriture dans les fichiers ou MySQL.\n"; exit(0); }
    $tty=fopen('/dev/tty','r'); if (!$tty) { throw new RuntimeException('Terminal interactif requis.'); }
    echo 'Tape OUI pour ajouter la vignette sur DEV : '; $answer=fgets($tty); fclose($tty);
    if (trim((string)$answer)!=='OUI') { echo "ANNULÉ — Aucune écriture.\n"; exit(0); }
    $private=$home.'/watchys-sauvegardes-privees';
    if (!is_dir($private) || is_link($private) || realpath($private)!==$private) { throw new RuntimeException('Dossier privé de journal absent ou non conforme.'); }
    $lockPath=$private.'/vignette-panier.lock'; if (is_link($lockPath)) { throw new RuntimeException('Verrou non autorisé.'); }
    $lock=fopen($lockPath,'c'); if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { throw new RuntimeException('Une autre mise à jour de vignette est en cours.'); }
    $journal=$private.'/vignette-panier-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
    $handle=fopen($journal,'x'); if (!$handle || !chmod($journal,0600)) { throw new RuntimeException('Journal privé indisponible.'); }
    $log=['status'=>'avant-ecriture','before'=>$before,'imageTypes'=>$types,'sourceSha256'=>$sourceHash,'files'=>[]];
    $save=static function () use (&$log,$handle) {
        $json=json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        if (!rewind($handle) || !ftruncate($handle,0) || fwrite($handle,$json)!==strlen($json) || !fflush($handle)) { throw new RuntimeException('Journal non enregistré.'); }
        if (function_exists('fsync') && !fsync($handle)) { throw new RuntimeException('Journal non synchronisé.'); }
    };
    $save(); echo 'Journal : '.$journal."\n";
    $db->exec('START TRANSACTION');
    if ($canon($snapshot(true))!==$canon($before) || $canon($rows('SELECT * FROM `'.$prefix.'image_type` WHERE products=1 ORDER BY id_image_type'))!==$canon($types)) { throw new RuntimeException('Produit ou formats modifiés depuis le plan.'); }
    foreach ($guardFiles as $path=>$hash) { if (hash_file('sha256',$path)!==$hash) { throw new RuntimeException('Code ou catalogue modifié depuis le plan.'); } }
    $sql='INSERT INTO `'.$prefix.'image` (id_product,position,cover'.($hasScPath?',sc_path':'').') VALUES (8867,1,1'.($hasScPath?",''":'').')';
    $db->exec($sql); $iid=(int)$db->lastInsertId(); if ($iid<1) { throw new RuntimeException('Identifiant image absent.'); }
    $rows('INSERT INTO `'.$prefix.'image_shop` (id_product,id_image,id_shop,cover) VALUES (8867,?,1,1)',[$iid]);
    foreach ($languages as $lang) { $rows('INSERT INTO `'.$prefix.'image_lang` (id_image,id_lang,legend) VALUES (?,?,?)',[$iid,(int)$lang['id_lang'],(int)$lang['id_lang']===1?'Chronographe Watchys 7750 — illustration générique':'Watchys 7750 chronograph — generic illustration']); }
    $root=realpath($site.'/img/p'); if (!$root || $root!==$site.'/img/p') { throw new RuntimeException('Racine des images non conforme.'); }
    $folder=$root;
    foreach (str_split((string)$iid) as $digit) {
        $folder.='/'.$digit;
        if (is_link($folder) || (file_exists($folder) && !is_dir($folder))) { throw new RuntimeException('Dossier image non autorisé.'); }
        if (!is_dir($folder) && (!mkdir($folder,0755) || !chmod($folder,0755))) { throw new RuntimeException('Dossier image non créé.'); }
    }
    foreach ($rendered as $suffix=>$bytes) {
        $target=$folder.'/'.$iid.$suffix.'.jpg';
        if (file_exists($target) || is_link($target)) { throw new RuntimeException('JPEG préexistant ; remplacement refusé.'); }
        $log['files'][$target]=hash('sha256',$bytes); $save();
        // Le chemin est réservé au nouvel ID de cette transaction, sans fichier préexistant.
        watchysWriteExclusive($target,$bytes); $created[]=$target;
    }
    $after=$snapshot(); $rest=$after; $rest['images']=[]; $rest['image_shop']=[];
    if ($canon($rest)!==$canon($before) || count($after['images'])!==1 || (int)$after['images'][0]['id_image']!==$iid || (int)$after['images'][0]['cover']!==1 || (int)$after['images'][0]['position']!==1 || count($after['image_shop'])!==1 || (int)$after['image_shop'][0]['id_image']!==$iid || (int)$after['image_shop'][0]['cover']!==1 || (int)$after['image_shop'][0]['id_shop']!==1) { throw new RuntimeException('Image ou fiche non confirmée après écriture.'); }
    $actualLang=$rows('SELECT id_lang,legend FROM `'.$prefix.'image_lang` WHERE id_image=? ORDER BY id_lang',[$iid]);
    if (array_map('intval',array_column($actualLang,'id_lang'))!==array_map('intval',array_column($languages,'id_lang'))) { throw new RuntimeException('Langues image non confirmées.'); }
    foreach ($log['files'] as $path=>$hash) { if (hash_file('sha256',$path)!==$hash) { throw new RuntimeException('JPEG publié non conforme.'); } }
    foreach ($guardFiles as $path=>$hash) { if (hash_file('sha256',$path)!==$hash) { throw new RuntimeException('Module ou catalogue modifié pendant la copie.'); } }
    $log['after']=$after; $log['imageId']=$iid; $log['status']='verifie-avant-commit'; $save();
    $db->exec('COMMIT'); $committed=true; $log['status']='succes'; $save(); fclose($handle);
    echo 'SUCCÈS — Image de couverture '.$iid.' liée au produit 8867 ; '.count($created)." JPEG générés et relus.\n";
    echo "Prix, stocks et rendu conservés. Recharge le panier pour contrôler la vignette. Commande et paiement restent bloqués.\n";
} catch (Throwable $e) {
    if ($db && $db->inTransaction()) { try { $db->rollBack(); } catch (Throwable $ignored) {} }
    if (!$committed) {
        // Les nouveaux fichiers sont conservés pour diagnostic ; aucune image antérieure n'est remplacée.
        if ($log && $save) { try { $log['status']='echec-verification-requise'; $save(); } catch (Throwable $ignored) {} }
    } else { echo "ATTENTION — Commit déjà effectué ; bilan final non confirmé.\n"; }
    echo $e instanceof PDOException?"ARRÊT — Erreur MySQL (identifiants masqués).\n":'ARRÊT — '.$e->getMessage()."\n";
    echo "Aucun succès à déduire. Conserver le journal et relancer --verifier avant reprise. Les nouveaux JPEG éventuels restent conservés.\n"; exit(1);
}
