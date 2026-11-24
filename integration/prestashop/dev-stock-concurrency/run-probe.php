<?php
/** Shared, owned fixture tables only. Does not load native PrestaShop bootstrap. */
function watchysRunConcurrency(PDO $db,$prefix,$sourcePrefix,$token,array $connection,$nativePath,callable $created,callable $progress)
{
 if(!preg_match('/^watchys_conc_[a-f0-9]{16}_$/D',$prefix)||!preg_match('/^[A-Za-z0-9_]+$/D',$sourcePrefix)||$prefix===$sourcePrefix){throw new RuntimeException('Périmètre de test invalide.');}
 require_once __DIR__.'/module/classes/WatchysStockSchema.php';
 $ddl=[
  'stock_available'=>'CREATE TABLE `'.$prefix.'stock_available` LIKE `'.$sourcePrefix.'stock_available`',
  'orders'=>'CREATE TABLE `'.$prefix.'orders` (id_order INT PRIMARY KEY,id_shop INT NOT NULL,current_state INT NOT NULL,valid INT NOT NULL) ENGINE=InnoDB',
  'order_detail'=>'CREATE TABLE `'.$prefix.'order_detail` (id_order_detail INT PRIMARY KEY,id_order INT NOT NULL,product_id INT NOT NULL,product_attribute_id INT NOT NULL,product_quantity INT NOT NULL,product_quantity_refunded INT NOT NULL) ENGINE=InnoDB',
  'order_state'=>'CREATE TABLE `'.$prefix.'order_state` (id_order_state INT PRIMARY KEY,shipped INT NOT NULL) ENGINE=InnoDB',
 ]+WatchysStockSchema::definitions($prefix)+['probe_owner'=>'CREATE TABLE `'.$prefix.'probe_owner` (token CHAR(64) PRIMARY KEY) ENGINE=InnoDB'];
 $tables=[];$children=[];$done=false;
 $stop=static function()use(&$children){foreach($children as $w){if(is_resource($w->process)){if(proc_get_status($w->process)['running']){proc_terminate($w->process);}foreach($w->pipes as $pipe){if(is_resource($pipe)){fclose($pipe);}}proc_close($w->process);}}$children=[];};
 try{
  foreach($ddl as $name=>$sql){$db->exec($sql);$tables[]=$name;$created($tables);}
  $s=$db->prepare('INSERT INTO `'.$prefix.'probe_owner` VALUES(?)');$s->execute([$token]);
  $db->exec('INSERT INTO `'.$prefix.'stock_available` SELECT * FROM `'.$sourcePrefix.'stock_available` WHERE id_product=7736 AND id_product_attribute=0 AND id_shop=1 AND id_shop_group=0');
  if((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'stock_available`')->fetchColumn()!==1){throw new RuntimeException('Copie unique du stock non confirmée.');}
  $db->exec('INSERT INTO `'.$prefix.'order_state` VALUES(10,0),(4,1),(6,0),(8,0)');
  $read=static function($w,$expected){stream_set_timeout($w->pipes[1],10);$line=fgets($w->pipes[1]);if($line===false||strlen($line)>1000){throw new RuntimeException('Processus de test interrompu ou trop lent.');}$m=json_decode($line,true,8,JSON_THROW_ON_ERROR);if(($m['event']??null)!==$expected){throw new RuntimeException('Étape de concurrence non confirmée : '.$expected.' / '.($m['event']??'inconnu'));}};
  $start=static function($actor,$mode,$hold,$detail=100)use($prefix,$token,$connection,$nativePath,&$children,$read){
   $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__);if(!is_resource($process)){throw new RuntimeException('Second processus PHP indisponible.');}
   $w=(object)['process'=>$process,'pipes'=>$pipes];$children[]=$w;
   $cfg=$connection+['prefix'=>$prefix,'token'=>$token,'nativePath'=>$nativePath,'actor'=>$actor,'mode'=>$mode,'hold'=>$hold,'detail'=>$detail];
   $json=json_encode($cfg,JSON_THROW_ON_ERROR)."\n";if(fwrite($pipes[0],$json)!==strlen($json)||!fflush($pipes[0])){throw new RuntimeException('Processus non initialisé.');}$read($w,'ATTEMPT');return $w;
  };
  $go=static function($w){if(fwrite($w->pipes[0],"GO\n")!==3||!fflush($w->pipes[0])){throw new RuntimeException('Barrière non libérée.');}};
  $reset=static function()use($db,$prefix,$stop){$stop();foreach(['watchys_stock_ledger','watchys_order_component','order_detail','orders'] as $t){$db->exec('DELETE FROM `'.$prefix.$t.'`');}$db->exec('UPDATE `'.$prefix.'stock_available` SET quantity=1,reserved_quantity=0,physical_quantity=1,depends_on_stock=0');};
  $assert=static function($v,$m){if(!$v){throw new RuntimeException($m);}};
  $state=static function()use($db,$prefix){return array_map('intval',$db->query('SELECT quantity,reserved_quantity,physical_quantity FROM `'.$prefix.'stock_available`')->fetch(PDO::FETCH_NUM));};
  $count=static function($t)use($db,$prefix){return (int)$db->query('SELECT COUNT(*) FROM `'.$prefix.$t.'`')->fetchColumn();};
  // Demonstrate the original stale native arithmetic race on fixture stocks only.
  $reset();$native=$start('native','unguarded','read');$read($native,'READ');$watchys=$start('watchys','guarded','none');$read($watchys,'COMMITTED');$go($native);$read($native,'COMMITTED');
  $assert($state()===[0,0,1]&&$count('watchys_order_component')===1&&$count('orders')===1,'Course native témoin non reproduite.');$progress('Témoin isolé : écriture native périmée reproduite et détectée.');
  // Native writer wins: Watchys is blocked while it holds the lock, then refused.
  $reset();$native=$start('native','guarded','read');$read($native,'READ');$watchys=$start('watchys','guarded','none');$read($watchys,'BLOCKED');$go($native);$read($native,'COMMITTED');$retry=$start('watchys','guarded','none');$read($retry,'REFUSED');
  $assert($state()===[0,1,1]&&$count('watchys_order_component')===0&&$count('watchys_stock_ledger')===0&&$count('orders')===1,'Vente native gagnante : état incohérent.');$progress('Dernière pièce : vente native gagnante ; configurateur bloqué puis refusé sans journal résiduel.');
  // Watchys wins: native callback must never load/write a stock after insufficiency.
  $reset();$watchys=$start('watchys','guarded','allocated');$read($watchys,'HELD');$native=$start('native','guarded','none');$read($native,'BLOCKED');$go($watchys);$read($watchys,'COMMITTED');$retry=$start('native','guarded','none');$read($retry,'REFUSED');
  $assert($state()===[0,1,1]&&$count('watchys_order_component')===1&&$count('watchys_stock_ledger')===1&&$count('orders')===0,'Configuration gagnante : état incohérent.');$progress('Dernière pièce : configurateur gagnant ; vente native bloquée puis refusée avant callback.');
  $reset();$watchys=$start('watchys','guarded','allocated');$read($watchys,'HELD');$other=$start('watchys','guarded','none',101);$read($other,'BLOCKED');$go($watchys);$read($watchys,'COMMITTED');$retry=$start('watchys','guarded','none',101);$read($retry,'REFUSED');
  $assert($state()===[0,1,1]&&$count('watchys_order_component')===1&&$count('watchys_stock_ledger')===1,'Deux configurations ont pris la même dernière pièce.');$progress('Deux configurations distinctes : une seule allocation, deuxième refusée sans double retrait.');$done=true;
 }finally{
  $stop();
  // Only names successfully CREATED by this invocation are eligible. Never a live name.
  foreach(array_reverse($tables) as $name){if(!array_key_exists($name,$ddl)){throw new RuntimeException('Nettoyage hors périmètre interdit.');}$db->exec('DROP TABLE `'.$prefix.$name.'`');}
  $remaining=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ?');$remaining->execute([$prefix.'%']);if((int)$remaining->fetchColumn()!==0){throw new RuntimeException('Nettoyage des fixtures non confirmé.');}
  $created([]);
 }
 return $done;
}
