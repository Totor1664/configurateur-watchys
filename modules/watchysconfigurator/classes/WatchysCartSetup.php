<?php
/** Called by the private DEV migration, after confirmation and full native bootstrap. */
class WatchysCartSetup
{
    const HOOKS=['displayCustomization','actionCartUpdateQuantityBefore','actionObjectOrderAddBefore','actionFrontControllerInitBefore','displayShoppingCartFooter'];
    public static function schema($prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new RuntimeException('Préfixe invalide.');}
        return [
            'watchys_model_support'=>'CREATE TABLE IF NOT EXISTS `'.$prefix.'watchys_model_support` (`model_id` VARCHAR(101) NOT NULL,`id_product` INT UNSIGNED NOT NULL,`reference` VARCHAR(64) NOT NULL,`fields_json` TEXT NOT NULL,PRIMARY KEY (`model_id`),UNIQUE KEY `product` (`id_product`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'watchys_configuration'=>'CREATE TABLE IF NOT EXISTS `'.$prefix.'watchys_configuration` (`id_customization` INT UNSIGNED NOT NULL,`id_cart` INT UNSIGNED NOT NULL,`id_product` INT UNSIGNED NOT NULL,`snapshot` MEDIUMTEXT NOT NULL,`date_add` DATETIME NOT NULL,PRIMARY KEY (`id_customization`),KEY `cart` (`id_cart`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'watchys_cart_request'=>'CREATE TABLE IF NOT EXISTS `'.$prefix.'watchys_cart_request` (`id_cart` INT UNSIGNED NOT NULL,`request_key` CHAR(32) NOT NULL,`request_digest` CHAR(64) NOT NULL,`id_customization` INT UNSIGNED NOT NULL,`date_add` DATETIME NOT NULL,PRIMARY KEY (`id_cart`,`request_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];
    }
    private static function execute($sql){if(!Db::getInstance()->execute($sql)){throw new RuntimeException('Migration SQL non confirmée.');}}
    public static function apply($module)
    {
        if(!$module->isDev()||!(int)$module->id){throw new RuntimeException('Module DEV installé attendu.');}
        $db=Db::getInstance();foreach(self::schema(_DB_PREFIX_) as $sql){self::execute($sql);}
        $expected=[
            'watchys_model_support'=>['model_id','id_product','reference','fields_json'],
            'watchys_configuration'=>['id_customization','id_cart','id_product','snapshot','date_add'],
            'watchys_cart_request'=>['id_cart','request_key','request_digest','id_customization','date_add'],
        ];
        foreach($expected as $table=>$cols){$actual=$db->executeS('SHOW COLUMNS FROM `'._DB_PREFIX_.$table.'`');if(!is_array($actual)||array_column($actual,'Field')!==$cols){throw new RuntimeException('Structure module inattendue : '.$table);}}
        // No previously existing article is used as the commercial support.
        $source=new Product(7753,false,null,1);if(!Validate::isLoadedObject($source)){throw new RuntimeException('Réglages fiscaux du kit de référence absents.');}
        self::execute('START TRANSACTION');
        try{
            $models=$module->catalog()->models();if(count($models)!==1||$models[0]['id']!=='watchys-7750-41mm'){throw new RuntimeException('Préparation attendue pour le modèle 7750 seulement.');}
            $model=$models[0];$existing=$db->getRow('SELECT * FROM `'._DB_PREFIX_."watchys_model_support` WHERE model_id='watchys-7750-41mm' FOR UPDATE");
            if(!$existing){
                if($db->getValue('SELECT id_product FROM `'._DB_PREFIX_."product` WHERE reference='WCFG-7750-41-CARTDEV'")){throw new RuntimeException('Référence technique déjà présente sans correspondance : contrôle nécessaire.');}
                $p=new Product();$p->id_shop_default=1;$p->reference='WCFG-7750-41-CARTDEV';$p->price=0;$p->active=1;$p->visibility='none';$p->indexed=0;$p->available_for_order=1;$p->show_price=1;$p->online_only=1;$p->is_virtual=0;$p->minimal_quantity=1;$p->condition='new';$p->customizable=1;$p->text_fields=6;$p->uploadable_files=0;$p->weight=0;
                $p->id_tax_rules_group=(int)$source->id_tax_rules_group;$p->id_category_default=(int)$source->id_category_default;
                foreach(Language::getLanguages(false,1) as $lang){$id=(int)$lang['id_lang'];$p->name[$id]='Chronographe Watchys 7750 — configuration personnalisée';$p->link_rewrite[$id]='chronographe-watchys-7750-configuration';$p->description_short[$id]='<p>Produit technique du configurateur DEV. Les composants et la formule sont détaillés dans chaque personnalisation. Commande bloquée pendant les tests de stock.</p>';}
                if(!$p->add()||!$p->addToCategories([(int)$source->id_category_default])){throw new RuntimeException('Produit technique non créé.');}
                StockAvailable::setQuantity((int)$p->id,0,0,1);
                StockAvailable::setProductOutOfStock((int)$p->id,1,1);
                if((int)StockAvailable::outOfStock((int)$p->id,1)!==1){throw new RuntimeException('Disponibilité du support non préparée.');}
                $fields=[];
                foreach(['model'=>'Modèle','dial'=>'Cadran','strap'=>'Bracelet','hands'=>'Aiguilles','case'=>'Carrure','offer'=>'Formule'] as $key=>$label){
                    if(!$db->insert('customization_field',['id_product'=>(int)$p->id,'type'=>Product::CUSTOMIZE_TEXTFIELD,'required'=>1,'is_module'=>1,'is_deleted'=>0])){throw new RuntimeException('Champ de personnalisation non créé.');}
                    $fields[$key]=(int)$db->Insert_ID();
                    foreach(Language::getLanguages(false,1) as $lang){if(!$db->insert('customization_field_lang',['id_customization_field'=>$fields[$key],'id_lang'=>(int)$lang['id_lang'],'id_shop'=>1,'name'=>pSQL($label)])){throw new RuntimeException('Libellé du champ non créé.');}}
                }
                if(!$db->insert('watchys_model_support',['model_id'=>pSQL($model['id']),'id_product'=>(int)$p->id,'reference'=>$p->reference,'fields_json'=>pSQL(json_encode($fields,JSON_THROW_ON_ERROR))])){throw new RuntimeException('Correspondance technique non enregistrée.');}
                $productId=(int)$p->id;
            }else{
                $p=new Product((int)$existing['id_product'],false,null,1);$productId=(int)$p->id;
                if(!Validate::isLoadedObject($p)||$p->reference!==$existing['reference']||(float)$p->price!==0.0||!$p->active||!$p->customizable){throw new RuntimeException('Support existant différent du plan.');}
            }
            foreach(self::HOOKS as $hook){if(!$module->registerHook($hook,[1])){throw new RuntimeException('Point d’accroche non enregistré : '.$hook);}}
            if(!Configuration::updateGlobalValue('PS_CUSTOMIZATION_FEATURE_ACTIVE','1')||!Configuration::updateValue('PS_CUSTOMIZATION_FEATURE_ACTIVE','1',false,null,1)||!Configuration::updateValue('WATCHYS_CART_DEV_READY','1',false,null,1)){throw new RuntimeException('Activation DEV non confirmée.');}
            if(!Customization::isFeatureActive()){throw new RuntimeException('Personnalisations natives non actives dans cette boutique.');}
            if(!$db->update('module',['version'=>'0.2.0'],'id_module='.(int)$module->id)){throw new RuntimeException('Version native non actualisée.');}
            $fields=$db->executeS('SELECT id_customization_field FROM `'._DB_PREFIX_.'customization_field` WHERE id_product='.$productId.' AND is_module=1 AND is_deleted=0');
            if(count($fields)!==6){throw new RuntimeException('Six champs de personnalisation attendus.');}
            foreach(self::HOOKS as $hook){if(!$module->isRegisteredInHook($hook)){throw new RuntimeException('Accroche non relue : '.$hook);}}
            $native=$db->getRow('SELECT p.reference,ps.price,ps.active,ps.visibility,ps.customizable FROM `'._DB_PREFIX_.'product` p JOIN `'._DB_PREFIX_.'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=1 WHERE p.id_product='.$productId);
            if(!$native||$native['reference']!=='WCFG-7750-41-CARTDEV'||(float)$native['price']!==0.0||(int)$native['active']!==1||$native['visibility']!=='none'||!(int)$native['customizable']){throw new RuntimeException('Fiche technique non confirmée après écriture.');}
            if($db->getValue('SELECT version FROM `'._DB_PREFIX_.'module` WHERE id_module='.(int)$module->id)!=='0.2.0'){throw new RuntimeException('Version native non relue.');}
            self::execute('COMMIT');return ['productId'=>$productId,'reference'=>'WCFG-7750-41-CARTDEV','hooks'=>self::HOOKS,'checkoutEnabled'=>false];
        }catch(Throwable $e){$db->execute('ROLLBACK');throw $e;}
    }
}
