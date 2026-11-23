<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/DevFiles.php';
function check($c,$m){if(!$c){throw new RuntimeException($m);}}
function fails($fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Opération non autorisée acceptée.');}
$base=sys_get_temp_dir().'/watchys-installer-test-'.bin2hex(random_bytes(8));mkdir($base,0700);$sha=hash('sha256','old');$newSha=hash('sha256','new');
try{
 mkdir($base.'/stage');file_put_contents($base.'/stage/main.php','new');
 WatchysDevFiles::publish($base.'/stage',$base.'/target',$base.'/backup');check(file_get_contents($base.'/target/main.php')==='new','Première publication incorrecte.');
 mkdir($base.'/stage');file_put_contents($base.'/stage/main.php','old');
 WatchysDevFiles::publish($base.'/stage',$base.'/target',$base.'/backup');check(file_get_contents($base.'/backup/main.php')==='new','Sauvegarde absente.');
 check(file_get_contents($base.'/target/main.php')==='old','Remplacement incorrect.');
 $state=WatchysDevFiles::checkExisting($base.'/target',['main.php'=>$newSha],['main.php'=>$sha],[]);check($state['main.php']===$sha,'Ancienne version non reconnue.');
 fails(fn()=>WatchysDevFiles::checkExisting($base.'/target',[],[],[]));
 file_put_contents($base.'/target/custom.php','manual');fails(fn()=>WatchysDevFiles::checkExisting($base.'/target',['main.php'=>$sha],[],[]));unlink($base.'/target/custom.php');
 WatchysDevFiles::copy($base.'/target/main.php',$base.'/copy/one.php',$sha);fails(fn()=>WatchysDevFiles::copy($base.'/target/main.php',$base.'/copy/one.php',$sha));
 fails(fn()=>WatchysDevFiles::copy($base.'/target/main.php',$base.'/copy/two.php',$newSha));
 set_error_handler(fn()=>true);
 try{fails(fn()=>WatchysDevFiles::publish($base.'/missing-stage',$base.'/target',$base.'/restore-backup'));}finally{restore_error_handler();}
 check(file_get_contents($base.'/target/main.php')==='old','Ancien dossier non restauré après échec.');
 WatchysDevFiles::saveJournal($base.'/journal.json',['status'=>'failed','target'=>$base.'/target']);check(json_decode(file_get_contents($base.'/journal.json'),true)['status']==='failed','Journal non conservé.');
 echo "SUCCÈS — première copie, sauvegarde, reprise connue, refus de modifications, copie exclusive, restauration et journal vérifiés.\n";
}finally{
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $p){if($p->isDir()){rmdir($p->getPathname());}else{unlink($p->getPathname());}}rmdir($base);
}
