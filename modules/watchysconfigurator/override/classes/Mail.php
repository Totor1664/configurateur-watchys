<?php
class Mail extends MailCore
{
 public static function send($idLang,$template,$subject,$templateVars,$to,$toName=null,$from=null,$fromName=null,$fileAttachment=null,$mode_smtp=null,$templatePath=_PS_MAIL_DIR_,$die=false,$idShop=null,$bcc=null,$replyTo=null,$replyToName=null)
 {
  if(!class_exists('WatchysNativeRuntime',false)){require_once _PS_MODULE_DIR_.'watchysconfigurator/classes/WatchysNativeRuntime.php';}
  $args=[$idLang,$template,$subject,$templateVars,$to,$toName,$from,$fromName,$fileAttachment,$mode_smtp,$templatePath,$die,$idShop,$bcc,$replyTo,$replyToName];
  if(WatchysNativeRuntime::isActive()){WatchysNativeRuntime::effect('mail',$args);return true;}return parent::send(...$args);
 }
}
