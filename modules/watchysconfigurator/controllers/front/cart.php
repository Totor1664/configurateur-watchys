<?php
class WatchysconfiguratorCartModuleFrontController extends ModuleFrontController
{
    public $ssl=true;
    public function init(){parent::init();if(!$this->module->isDev()){http_response_code(404);exit;}}
    public function displayAjax()
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
        require_once dirname(__DIR__,2).'/classes/WatchysRequest.php';
        try{
            if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');throw new InvalidArgumentException('Requête POST attendue.');}
            if((isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!=='https://dev.watchys.ch')||(isset($_SERVER['HTTP_SEC_FETCH_SITE'])&&!in_array($_SERVER['HTTP_SEC_FETCH_SITE'],['same-origin','none'],true))){http_response_code(403);throw new InvalidArgumentException('Requête externe refusée.');}
            http_response_code(400);
            $input=WatchysRequest::decode(file_get_contents('php://input',false,null,0,16385));
            if(!is_string($input['token']??null)||!hash_equals(Tools::getToken(false),$input['token'])){http_response_code(403);throw new InvalidArgumentException('Session expirée. Rechargez la page.');}
            http_response_code(200);
            $result=$this->module->cartService()->add($input);
            $result['cartUrl']=$this->context->link->getPageLink('cart',true,null,['action'=>'show']);
            $this->ajaxRender(json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR));
        }catch(InvalidArgumentException $e){
            if(http_response_code()===200){http_response_code(409);}
            $this->ajaxRender(json_encode(['ok'=>false,'error'=>$e->getMessage()]));
        }catch(Throwable $e){
            http_response_code(503);$reference=bin2hex(random_bytes(4));
            try{PrestaShopLogger::addLog('watchys cart '.$reference.' '.get_class($e),3);}catch(Throwable $ignored){}
            $this->ajaxRender(json_encode(['ok'=>false,'error'=>'Ajout non confirmé. Réessayez sans changer votre sélection. Référence : '.$reference]));
        }
    }
}
