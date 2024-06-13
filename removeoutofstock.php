<?php

class RemoveOutOfStock extends Module {

    
    public function __construct() {
        $this->name = 'removeoutofstock';
        $this->version = '0.9.0';
        $this->author = 'Venerucci Comunicazione';
        $this->need_instance = 1; //??
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
    
        parent::__construct();
    
        $this->displayName = $this->l('Out of Stock Product visibility');
        $this->description = $this->l('Enable extra features to handle out of stock products');
    
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install() {
        
        // Inizializzo i parametri di configurazione
        Configuration::updateValue('REMOVE_OFS_CATEGORIES', null); // Categorie
        Configuration::updateValue('REMOVE_OFS_DISABLE', true); // Disabilitare il prodotto
        Configuration::updateValue('REMOVE_OFS_HIDE', true); // Nascodere il prodotto
        Configuration::updateValue('REMOVE_OFS_BACK_STATES', null); // Stati Ordine per riabilitazione 
        Configuration::updateValue('REMOVE_OFS_CEMETERY_CAT', null); // Categoria Cemetery

        if(
            parent::install() == false 
            || $this->registerHook('actionOrderStatusPostUpdate') == false
            || $this->registerHook('actionValidateOrder') == false)
        {
            return false;
        }
        
        return true;
    }

    public function uninstall() {

        Configuration::deleteByName('REMOVE_OFS_CATEGORIES');
        Configuration::deleteByName('REMOVE_OFS_DISABLE');
        Configuration::deleteByName('REMOVE_OFS_HIDE');
        Configuration::deleteByName('REMOVE_OFS_BACK_STATES');
        Configuration::deleteByName('REMOVE_OFS_CEMETERY_CAT');

        return parent::uninstall();
    }

    /**
     * Funzione che mostra e gestisce la form di configurazione del modulo
     * La form viene mostrata solo lato Backoffice
     */
    public function getContent() {

        if(Tools::isSubmit('submitConfiguration')) 
        {
            $selected_categories = Tools::getValue('categoryBox');
            Configuration::updateValue('REMOVE_OFS_CATEGORIES', serialize($selected_categories));

            Configuration::updateValue('REMOVE_OFS_DISABLE', Tools::getValue('remove_ofs_disable'));
            Configuration::updateValue('REMOVE_OFS_HIDE', Tools::getValue('remove_ofs_hide'));
            Configuration::updateValue('REMOVE_OFS_BACK_STATES', serialize(Tools::getValue('remove_ofs_back_states')));
            Configuration::updateValue('REMOVE_OFS_CEMETERY_CAT', Tools::getValue('remove_ofs_cemetery_cat'));
        }

        $categories = Tools::unSerialize(Configuration::get('REMOVE_OFS_CATEGORIES'));

        $tree_categories_helper = new HelperTreeCategories('test');
        $tree_categories_helper
            ->setRootCategory((Shop::getContext() == Shop::CONTEXT_SHOP ? Category::getRootCategory()->id_category : 0))
            ->setUseCheckBox(true);

        if($categories != '') {
            $tree_categories_helper->setSelectedCategories($categories);
        }

        $orderStates = OrderState::getOrderStates($this->context->language->id);
        $cemetery_categories = Category::getSimpleCategories($this->context->language->id);

        $this->context->smarty->assign(array(
            'action' => AdminController::$currentIndex.'&configure='.$this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'categories_tree' => $tree_categories_helper->render(),
            'remove_ofs_disable' => Configuration::get('REMOVE_OFS_DISABLE'),
            'remove_ofs_hide' => Configuration::get('REMOVE_OFS_HIDE'),
            'orders_states' => $orderStates,
            'remove_ofs_back_states' => Tools::unSerialize(Configuration::get('REMOVE_OFS_BACK_STATES')),
            'cemetery_categories' => $cemetery_categories,
            'remove_ofs_cemetery_cat' => Configuration::get('REMOVE_OFS_CEMETERY_CAT')
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/configuration.tpl');
    }

    /*
     * Funzione che gestisce la riabilitazione o meno del prodotto al cambio di stato dell'ordine
     * - Se il nuovo stato è uno dei stati di annullamento dell'ordine allora riabilito il prodotto (es: annullato, reso completato, ecc.. )
     * 
     * @param Array params Array dei parametri dell'hook:
     *     - @param OrderState newOrderState
     *     - @param int id_order
     */
    public function hookActionOrderStatusPostUpdate($params) { 
        
        //0. Recupero la lista dei stati che provocano l'annullamento dell'ordine
        $states = Tools::unSerialize(Configuration::get('REMOVE_OFS_BACK_STATES'));

        //1. Verifico che il nuovo stato sia tra quelli scelti
        if(in_array($params['newOrderStatus']->id, $states)) {

            //1. Recupera la lista dei prodotti dell'ordine appartenenti alle categorie scelte
            $sql = new DbQuery();

            $sql->select('od.product_id, od.product_attribute_id, od.id_warehouse');
            $sql->from('order_detail', 'od');
            $sql->innerJoin('category_product', 'cp', 'od.product_id = cp.id_product');
            $sql->where('cp.id_category IN (' . implode(', ', Tools::unSerialize(Configuration::get('REMOVE_OFS_CATEGORIES'))) . ')');
            $sql->where('od.id_order = ' . $params['id_order']);
            $sql->groupBy('od.product_id, od.product_attribute_id');

            $products = Db::getInstance()->executeS($sql);

            foreach ($products as $product) {

                //2. Recupero l'oggetto product
                $product_obj = new Product($product['product_id']);
                if (!Validate::isLoadedObject($product_obj)) {
                    // Se il prodotto non esiste scrivo nel log ed esco
                    PrestaShopLogger::addLog($this->l('Unable to load Product Object to enable'), 3, null, 'Product', $product['id_product']);
                    return; 
                }

                // 2.1 Carico i dati di stock
                $product_obj->loadStockData();

                //2.2 Se il prodotto non è più disponibile e il suo comportamento a quantità finite è "Rifiuta gli ordini"    
                if(
                    !Product::isAvailableWhenOutOfStock($product_obj->out_of_stock)
                    && $this->isProductAvailable($product_obj->id, $product_obj->advanced_stock_management, $product['id_warehouse'])
                ) {
                    
                    //2.2.1 Riabilito il prodotto se ho scelto la disabilitazione
                    if(Configuration::get('REMOVE_OFS_DISABLE')) {
                        $product_obj->active = 1;
                        $product_obj->update();
                    }

                    //2.2.2 Lo rendo visibile se ho scelto di non renderlo visibile (visibility = none)
                    if(Configuration::get('REMOVE_OFS_HIDE')) {
                        $product_obj->visibility = 'both';
                        $product_obj->update();
                    }

                    //2.2.3 Lo tolgo dalla categoria cemetery se è selezionata
                    if(Configuration::get('REMOVE_OFS_CEMETERY_CAT') != null) {
                        $product_obj->deleteCategory(Configuration::get('REMOVE_OFS_CEMETERY_CAT'));

                        $product_obj->id_category_default = end($product_obj->getCategories());
                        $product_obj->update();
                    }
                }
            }   
        }
    }

    /**
     * Funzione che va a gestire la visibilità del prodotto
     * a seguito del completamento dell'ordine
     * @param Array params Array dei parametri dell'hook
     */
    public function hookActionValidateOrder($params) {
        
        //0. Verifico che sia abilitata la gestione del magazzino
        if(Configuration::get('PS_STOCK_MANAGEMENT')) {
            
            $order = $params['order'];

            //1. Recupera la lista dei prodotti dell'ordine appartenenti alle categorie scelte
            $sql = new DbQuery();

            $sql->select('od.product_id, od.product_attribute_id, od.id_warehouse');
            $sql->from('order_detail', 'od');
            $sql->innerJoin('category_product', 'cp', 'od.product_id = cp.id_product');
            $sql->where('cp.id_category IN (' . implode(', ', Tools::unSerialize(Configuration::get('REMOVE_OFS_CATEGORIES'))) . ')');
            $sql->where('od.id_order = ' . $order->id);
            $sql->groupBy('od.product_id, od.product_attribute_id');

            $products = Db::getInstance()->executeS($sql);

            foreach ($products as $product) {

                //2. Recupero l'oggetto product
                $product_obj = new Product($product['product_id']);
                if (!Validate::isLoadedObject($product_obj)) {
                    // Se il prodotto non esiste scrivo nel log ed esco
                    PrestaShopLogger::addLog($this->l('Unable to load Product Object to disable'), 3, null, 'Product', $product['id_product']);
                    return; 
                }

                // 2.1 Carico i dati di stock
                $product_obj->loadStockData();

                //2.2 Se il prodotto non è più disponibile e il suo comportamento a quantità finite è "Rifiuta gli ordini"    
                if(
                    !Product::isAvailableWhenOutOfStock($product_obj->out_of_stock)
                    && !$this->isProductAvailable($product_obj->id, $product_obj->advanced_stock_management, $product['id_warehouse'])
                ) {
                    
                    //2.2.1 Disabilito il prodotto se ho scelto la disabilitazione
                    if(Configuration::get('REMOVE_OFS_DISABLE')) {
                        $product_obj->active = 0;
                        $product_obj->update();
                    }

                    //2.2.2 Lo rendo non visibile se ho scelto di non renderlo visibile (visibility = none)
                    if(Configuration::get('REMOVE_OFS_HIDE')) {
                        $product_obj->visibility = 'none';
                        $product_obj->update();
                    }

                    //2.2.3 Lo sposto nella categoria cemetery se è selezionata
                    if(Configuration::get('REMOVE_OFS_CEMETERY_CAT') != null) {
                        $product_obj->addToCategories(array(Configuration::get('REMOVE_OFS_CEMETERY_CAT')));
                        $product_obj->id_category_default = Configuration::get('REMOVE_OFS_CEMETERY_CAT');
                        $product_obj->update();
                    }
                }
            }
        }
        
    }

    /**
     * Funzione che verifica se è presente a magazzino il prodotto
     * 
     * vers. A
     * - Se il prodotto non ha combinazioni, basta verificare che quantity >= 1
     *   da ps_stock_available per id_product = X e id_product_attribute = 0
     * - Se il prodotto ha combinazioni, devo verificare che ci sia almeno 
     *   una combinazione con quantity >= 1
     * 
     * ------------------------------------------------------------------
     * vers. B
     * Nel caso in cui il prodotto non è diponibile all'ordine la quantità
     * non può scendere sotto 0, quindi posso andare a recuperare direttamente
     * la quantità del prodotto senza considerare gli attributi.
     * La quantità del prodotto è data dalla somma delle quantità degli attributi,
     * non andando sotto zero non ho problemi con le somme.
     * 
     */
    private function isProductAvailable($id_product, $advanced_stock_management, $id_warehouse) {

        $quantity = 0;

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') 
            && (int)$advanced_stock_management == 1
            && (int)$id_warehouse > 0) {

            //$quantity = StockManagerFactory::getManager()->getProductPhysicalQuantities($id_product, 0, $id_warehouse, true); //vers. B

            $sql = new DbQuery();
            $sql->select('MAX(usable_quantity)');
            $sql->from('stock', 's');
            $sql->where('s.id_product = ' . $id_product);
            $sql->where('s.id_warehouse = ' . $id_warehouse);
            $sql->groupBy('s.id_product');

            $quantity = Db::getInstance()->getValue($sql);
            
        } else {
            //$quantity = StockAvailable::getQuantityAvailableByProduct($id_product, 0, (int)$this->context->shop->id); //vers. A

            $sql = new DbQuery();
            $sql->select('MAX(quantity)');
            $sql->from('stock_available', 'sa');
            $sql->where('sa.id_product = ' . $id_product);
            $sql->groupBy('sa.id_product');

            $quantity = Db::getInstance()->getValue($sql);
        }

        return $quantity >= 1;
    }

    // out_of_stock = 0 --> Rifiuta Ordini
    // out_of_stock = 1 --> Accetta Ordini
    // out_of_stock = 2 --> Comportamento di default

}