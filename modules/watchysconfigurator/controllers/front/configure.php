<?php
class WatchysconfiguratorConfigureModuleFrontController extends ModuleFrontController
{
    public $ssl=true;
    public function init()
    {
        parent::init();
        if (!$this->module->isDev()) { http_response_code(404); exit; }
    }
    public function setMedia()
    {
        parent::setMedia();
        $this->registerStylesheet('watchys-configurator','modules/watchysconfigurator/views/css/configurator.css',['media'=>'all','priority'=>200,'version'=>$this->module->version]);
        $this->registerJavascript('watchys-preview','modules/watchysconfigurator/views/js/preview-reference-v013.js',['position'=>'bottom','priority'=>190,'version'=>'reference-faec627']);
        $this->registerJavascript('watchys-configurator','modules/watchysconfigurator/views/js/configurator-cart-v020.js',['position'=>'bottom','priority'=>200,'version'=>$this->module->version]);
    }
    public function initContent()
    {
        parent::initContent();
        header('Cache-Control: no-store, private');
        $payload=$this->module->presentation();
        $payload['apiUrl']=$this->context->link->getModuleLink($this->module->name,'api',['ajax'=>1],true);
        $payload['token']=Tools::getToken(false);
        $payload['cartUrl']=$this->context->link->getModuleLink($this->module->name,'cart',['ajax'=>1],true);
        $this->context->smarty->assign('watchys_payload',json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR));
        $this->setTemplate('module:watchysconfigurator/views/templates/front/configure-cart-v020.tpl');
    }
}
