<?php
/** Pre-bootstrap DEV identification shared by private children. No site classes loaded. */
function watchysLotDev()
{
 $home=realpath(getenv('HOME'));$site=realpath($home.'/sites/dev.watchys.ch');
 if(!$site||is_link($home.'/sites/dev.watchys.ch')||$site===realpath($home.'/web')){throw new RuntimeException('Racine DEV non confirmée.');}
 $parameterFile=$site.'/app/config/parameters.php';if(is_link($parameterFile)){throw new RuntimeException('Configuration liée refusée.');}
 $config=require $parameterFile;$p=$config['parameters'];$prefix=$p['database_prefix'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new RuntimeException('Préfixe invalide.');}
 $port=empty($p['database_port'])?'':';port='.(int)$p['database_port'];$connection=['dsn'=>"mysql:host={$p['database_host']}{$port};dbname={$p['database_name']};charset=utf8mb4",'user'=>$p['database_user'],'password'=>$p['database_password']];
 $db=new PDO($connection['dsn'],$connection['user'],$connection['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $db->exec('START TRANSACTION READ ONLY');try{$urls=$db->query('SELECT id_shop,domain,domain_ssl FROM `'.$prefix.'shop_url` WHERE active=1')->fetchAll(PDO::FETCH_ASSOC);if(!$urls||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'shop`')->fetchColumn()!==1){throw new RuntimeException('Boutique unique non confirmée.');}foreach($urls as $u){if((int)$u['id_shop']!==1||$u['domain']!=='dev.watchys.ch'||$u['domain_ssl']!=='dev.watchys.ch'){throw new RuntimeException('Base DEV non confirmée.');}}}finally{$db->exec('ROLLBACK');}
 return [$home,$site,$db,$prefix,$connection,hash_file('sha256',$parameterFile)];
}
function watchysLotCapability($path,$purpose)
{
 $home=realpath(getenv('HOME'));$real=realpath($path);if(!$real||!str_starts_with($real,$home.'/watchys-sauvegardes-privees/')||is_link($path)||(fileperms($real)&0777)!==0600){throw new RuntimeException('Capacité privée requise.');}
 $c=json_decode(file_get_contents($real),true,32,JSON_THROW_ON_ERROR);$token=trim((string)fgets(STDIN));
 if(($c['purpose']??null)!==$purpose||!is_string($c['token']??null)||!hash_equals($c['token'],$token)||(int)($c['expires']??0)<time()||!hash_equals($c['packetSha']??'',hash_file('sha256',__DIR__.'/manifest.json'))){throw new RuntimeException('Capacité privée invalide.');}return $c;
}
function watchysLotBootstrap($site)
{
 $_SERVER['HTTP_HOST']='dev.watchys.ch';$_SERVER['SERVER_NAME']='dev.watchys.ch';$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['HTTPS']='on';$_SERVER['REMOTE_ADDR']='127.0.0.1';require_once $site.'/config/config.inc.php';
}
