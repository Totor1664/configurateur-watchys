<?php
/** Bounded file patching with private backups. Existing unknown bytes are never replaced. */
class WatchysNativeLotFiles
{
 public static function path($root,$relative)
 {
  if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$relative)||str_contains($relative,'..')){throw new RuntimeException('Chemin de lot invalide.');}
  $full=$root.'/'.$relative;$walk=$full;while($walk!==$root){if(is_link($walk)){throw new RuntimeException('Lien symbolique refusé.');}$walk=dirname($walk);}return $full;
 }
 public static function stage($root,$source,$backup,array $files,array $baseline)
 {
  $changes=[];foreach($files as $relative=>$hash){$dest=self::path($root,$relative);$src=self::path($source,$relative);if(!is_file($src)||hash_file('sha256',$src)!==$hash){throw new RuntimeException('Source de lot modifiée.');}$before=is_file($dest)?hash_file('sha256',$dest):null;
   if($before!==null&&!in_array($before,[$hash,$baseline[$relative]??null],true)){throw new RuntimeException('Fichier non reconnu : '.$relative);}
   $item=['path'=>$relative,'before'=>$before,'after'=>$hash,'backup'=>null];if($before!==null&&$before!==$hash){$save=self::path($backup,$relative);self::mkdir(dirname($save),0700);if(!copy($dest,$save)||!chmod($save,0600)||hash_file('sha256',$save)!==$before){throw new RuntimeException('Sauvegarde non confirmée.');}$item['backup']=$save;}$changes[]=$item;
  }return $changes;
 }
 private static function mkdir($dir,$mode){if(!is_dir($dir)&&!mkdir($dir,$mode,true)){throw new RuntimeException('Dossier non créé.');}}
 public static function publish($root,$source,array $changes,callable $record)
 {
  foreach($changes as $item){$dest=self::path($root,$item['path']);$now=is_file($dest)?hash_file('sha256',$dest):null;if($now===$item['after']){continue;}if($now!==$item['before']){throw new RuntimeException('Cible changée depuis le plan.');}self::mkdir(dirname($dest),0755);$temp=tempnam(dirname($dest),'.watchys-lot-');if($temp===false){throw new RuntimeException('Préparation atomique impossible.');}
   try{if(!copy(self::path($source,$item['path']),$temp)||!chmod($temp,0644)||hash_file('sha256',$temp)!==$item['after']){throw new RuntimeException('Copie du lot non confirmée.');}
    $record($item);if(!rename($temp,$dest)){throw new RuntimeException('Publication du lot impossible.');}if(hash_file('sha256',$dest)!==$item['after']){throw new RuntimeException('Publication du lot non relue.');}if(function_exists('opcache_invalidate')){@opcache_invalidate($dest,true);}
   }finally{if(is_file($temp)){unlink($temp);}}
  }
 }
 public static function restore($root,array $items)
 {
  $ok=true;foreach(array_reverse($items) as $item){$dest=self::path($root,$item['path']);$now=is_file($dest)?hash_file('sha256',$dest):null;if($now===$item['before']){continue;}if($now!==$item['after']){$ok=false;continue;}
   if($item['before']===null){if(!unlink($dest)){$ok=false;}}else{if(!$item['backup']||!is_file($item['backup'])||hash_file('sha256',$item['backup'])!==$item['before']){$ok=false;continue;}$temp=tempnam(dirname($dest),'.watchys-restore-');if($temp===false||!copy($item['backup'],$temp)||!chmod($temp,0644)||!rename($temp,$dest)){$ok=false;}if($temp&&is_file($temp)){unlink($temp);}}
   if((is_file($dest)?hash_file('sha256',$dest):null)!==$item['before']){$ok=false;}
   if(function_exists('opcache_invalidate')){@opcache_invalidate($dest,true);}
  }return $ok;
 }
 public static function process(array $command,$cwd,$input='', $timeout=180)
 {
  $pipes=[];$proc=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd);if(!is_resource($proc)){throw new RuntimeException('Sous-processus natif non démarré.');}if($input!==''){fwrite($pipes[0],$input);}fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$started=microtime(true);$last=$started;$out='';$err='';$exit=null;
  try{while(true){$status=proc_get_status($proc);$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);if(strlen($out)+strlen($err)>2000000){throw new RuntimeException('Sortie du processus trop volumineuse.');}if(!$status['running']){$exit=$status['exitcode'];break;}if(microtime(true)-$started>$timeout){throw new RuntimeException('Délai du processus natif dépassé.');}if(microtime(true)-$last>5){echo 'Progression native : '.(int)(microtime(true)-$started)." s\n";$last=microtime(true);}usleep(50000);}
  }finally{if(proc_get_status($proc)['running']){proc_terminate($proc);}foreach([1,2] as $i){$outPart=stream_get_contents($pipes[$i]);if($i===1){$out.=$outPart;}else{$err.=$outPart;}fclose($pipes[$i]);}$closed=proc_close($proc);if($exit===null){$exit=$closed;}}
  return ['exit'=>$exit,'out'=>$out,'err'=>$err];
 }
}
