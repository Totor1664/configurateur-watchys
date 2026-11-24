<?php
$helper=dirname(__DIR__,3).'/integration/prestashop/dev-native-lot/NativeLotFiles.php';if(!is_file($helper)){$helper=dirname(__DIR__,2).'/NativeLotFiles.php';}require $helper;
function filesAssert($condition){if(!$condition){throw new RuntimeException('Publication ou restauration de fichiers non conforme.');}}
$root=sys_get_temp_dir().'/watchys-lot-files-'.bin2hex(random_bytes(6));mkdir($root);foreach(['site','source','backup'] as $d){mkdir($root.'/'.$d);}
try{
 file_put_contents($root.'/site/old.php','old');file_put_contents($root.'/source/old.php','new');file_put_contents($root.'/source/new.php','added');$hashes=['old.php'=>hash('sha256','new'),'new.php'=>hash('sha256','added')];$baseline=['old.php'=>hash('sha256','old')];
 $plan=WatchysNativeLotFiles::stage($root.'/site',$root.'/source',$root.'/backup',$hashes,$baseline);filesAssert(file_get_contents($root.'/site/old.php')==='old'&&!file_exists($root.'/site/new.php'));
 $written=[];WatchysNativeLotFiles::publish($root.'/site',$root.'/source',$plan,static function($item)use(&$written){$written[]=$item;});filesAssert(count($written)===2&&file_get_contents($root.'/site/old.php')==='new'&&file_get_contents($root.'/site/new.php')==='added');
 filesAssert(WatchysNativeLotFiles::restore($root.'/site',$written));filesAssert(file_get_contents($root.'/site/old.php')==='old'&&!file_exists($root.'/site/new.php'));
 WatchysNativeLotFiles::publish($root.'/site',$root.'/source',$plan,static function($item){});file_put_contents($root.'/site/old.php','foreign');filesAssert(!WatchysNativeLotFiles::restore($root.'/site',$plan));filesAssert(file_get_contents($root.'/site/old.php')==='foreign');
 $refused=false;try{WatchysNativeLotFiles::stage($root.'/site',$root.'/source',$root.'/backup',$hashes,$baseline);}catch(RuntimeException $e){$refused=true;}filesAssert($refused);
 $refused=false;try{WatchysNativeLotFiles::path($root.'/site','../outside');}catch(RuntimeException $e){$refused=true;}filesAssert($refused);
 echo "SUCCÈS — Lot de fichiers : sauvegarde préalable, publication, restauration exacte, refus d’un contenu étranger et chemins bornés. Aucun accès au site ou MySQL.\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isDir()){rmdir($f->getPathname());}else{unlink($f->getPathname());}}rmdir($root);}
