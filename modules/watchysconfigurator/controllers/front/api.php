<?php
class WatchysconfiguratorApiModuleFrontController extends ModuleFrontController
{
    public $ssl=true;
    public function init()
    {
        parent::init();
        if (!$this->module->isDev()) { http_response_code(404); exit; }
    }
    public function displayAjax()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        try {
            if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { http_response_code(405); header('Allow: POST'); throw new InvalidArgumentException('Requête POST attendue.'); }
            $raw=file_get_contents('php://input',false,null,0,16385);
            if (strlen($raw)>16384) { throw new InvalidArgumentException('Requête trop volumineuse.'); }
            $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($input) || !is_string($input['token']??null) || !hash_equals(Tools::getToken(false),$input['token'])) { http_response_code(403); throw new InvalidArgumentException('Session expirée. Rechargez la page.'); }
            $result=$this->module->quoteSelection($input);
            $this->ajaxRender(json_encode(['ok'=>true,'quote'=>$result],JSON_THROW_ON_ERROR));
        } catch (InvalidArgumentException $e) {
            if (http_response_code()===200) { http_response_code(400); }
            $this->ajaxRender(json_encode(['ok'=>false,'error'=>$e->getMessage()]));
        } catch (Throwable $e) {
            http_response_code(503);
            $this->ajaxRender(json_encode(['ok'=>false,'error'=>'La configuration ne peut pas être vérifiée pour le moment. Réessayez.']));
        }
    }
}
