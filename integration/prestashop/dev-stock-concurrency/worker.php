<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');set_time_limit(25);$db=null;
function signalFixture($event){echo json_encode(['event'=>$event],JSON_THROW_ON_ERROR)."\n";flush();}
function waitFixture(){if(trim((string)fgets(STDIN))!=='GO'){throw new RuntimeException('Barrière de test interrompue.');}}
try{
 $cfg=json_decode((string)fgets(STDIN),true,32,JSON_THROW_ON_ERROR);$prefix=$cfg['prefix']??'';
 if(!preg_match('/^watchys_conc_[a-f0-9]{16}_$/D',$prefix)||!preg_match('/^[a-f0-9]{64}$/D',$cfg['token']??'')||!in_array($cfg['actor']??'', ['native','watchys'],true)||!in_array($cfg['mode']??'', ['guarded','unguarded'],true)||!in_array($cfg['hold']??'', ['none','read','allocated'],true)||!in_array($cfg['detail']??null,[100,101],true)){throw new RuntimeException('Fixture non autorisée.');}
 $db=new PDO($cfg['dsn'],$cfg['user'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $token=$db->query('SELECT token FROM `'.$prefix.'probe_owner`')->fetchAll(PDO::FETCH_COLUMN);if(count($token)!==1||!hash_equals($cfg['token'],$token[0])){throw new RuntimeException('Propriété des fixtures non confirmée.');}
 $db->exec('SET SESSION innodb_lock_wait_timeout=1');
 require_once __DIR__.'/module/classes/WatchysStockAllocator.php';require_once __DIR__.'/module/classes/WatchysNativeStockCoordinator.php';
 $db->beginTransaction();signalFixture('ATTEMPT');
 if($cfg['actor']==='watchys'){
  $detail=$cfg['detail'];$r=[['identity'=>['id_order_detail'=>$detail,'component_product_id'=>7736,'component_attribute_id'=>0,'id_order'=>$detail,'id_customization'=>$detail,'id_shop'=>1],'required_units'=>1,'nomenclature_hash'=>hash('sha256','concurrency-fixture')]];
  (new WatchysStockAllocator($db,$prefix))->apply($r,'reserve:fixture',['kind'=>'status','status_id'=>10]);
  if($cfg['hold']==='allocated'){signalFixture('HELD');waitFixture();}
 }else{
  require_once __DIR__.'/native-doubles.php';
  $native=static function()use($db,$prefix,$cfg){watchysFixtureNative($db,$prefix,$cfg['nativePath'],static function()use($cfg){if($cfg['hold']==='read'){signalFixture('READ');waitFixture();}});};
  if($cfg['mode']==='guarded'){(new WatchysNativeStockCoordinator($db,$prefix,[7736]))->run([['productId'=>7736,'combinationId'=>0,'delta'=>-1]],$native);}else{$native();}
 }
 $db->commit();signalFixture('COMMITTED');
}catch(InvalidArgumentException $e){if($db&&$db->inTransaction()){$db->rollBack();}signalFixture('REFUSED');}
catch(PDOException $e){if($db&&$db->inTransaction()){$db->rollBack();}if((int)($e->errorInfo[1]??0)===1205){signalFixture('BLOCKED');}else{signalFixture('SQL_ERROR');exit(1);}}
catch(Throwable $e){if($db&&$db->inTransaction()){$db->rollBack();}signalFixture('ERROR');exit(1);}
