<?php
class Hook extends HookCore
{
 public static function exec($hook_name,$hook_args=[],$id_module=null,$array_return=false,$check_exceptions=true,$use_push=false,$id_shop=null,$chain=false)
 {
  if(!class_exists('WatchysNativeRuntime',false)){require_once _PS_MODULE_DIR_.'watchysconfigurator/classes/WatchysNativeRuntime.php';}
  if(WatchysNativeRuntime::isActive()){[$held,$value]=WatchysNativeRuntime::captureHook($hook_name,$hook_args,$id_module,$array_return,$check_exceptions,$use_push,$id_shop,$chain);if($held){return $value;}}
  return parent::exec($hook_name,$hook_args,$id_module,$array_return,$check_exceptions,$use_push,$id_shop,$chain);
 }
}
