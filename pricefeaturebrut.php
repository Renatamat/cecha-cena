<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceFeatureBrut extends Module
{
    public function __construct()
    {
        $this->name = 'pricefeaturebrut';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Artnova - RM';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Automatic price range feature (gross)');
        $this->description = $this->l('Automatically sets feature ID 8 based on product price tax included.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionObjectProductAddAfter')
            && $this->registerHook('actionObjectProductUpdateAfter');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookActionObjectProductAddAfter($params)
    {
        $this->updateProductPriceFeature($params);
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        $this->updateProductPriceFeature($params);
    }

    /**
     * Główna logika – oblicza cenę brutto i przypisuje odpowiednią wartość cechy.
     */
    private function updateProductPriceFeature($params) {
        $product = null;

        if (isset($params['object']) && $params['object'] instanceof Product) {
            $product = $params['object'];
        } elseif (isset($params['id_product'])) {
            $product = new Product((int) $params['id_product']);
        } else {
            return;
        }

        if (!Validate::isLoadedObject($product)) {
            return;
        }

        $idProduct = (int) $product->id;
        if ($idProduct <= 0) {
            return;
        }

        // cena brutto
        $priceTtc = Product::getPriceStatic($idProduct, true);

        if ($priceTtc <= 25) {
            $idFeatureValue = 42;
        } elseif ($priceTtc <= 50) {
            $idFeatureValue = 43;
        } elseif ($priceTtc <= 100) {
            $idFeatureValue = 44;
        } elseif ($priceTtc <= 200) {
            $idFeatureValue = 45;
        } else {
            $idFeatureValue = 46;
        }

        $this->applyFeatureToProduct($idProduct, 8, $idFeatureValue);
    }

    private function applyFeatureToProduct($idProduct, $idFeature, $idFeatureValue) {
        $idProduct = (int) $idProduct;
        $idFeature = (int) $idFeature;
        $idFeatureValue = (int) $idFeatureValue;

        if ($idProduct <= 0 || $idFeature <= 0 || $idFeatureValue <= 0) {
            return;
        }

        $db = Db::getInstance();

        // kasujemy stare wpisy dla cechy 8
        $db->delete(
                'feature_product',
                'id_product = ' . (int) $idProduct . ' AND id_feature = ' . (int) $idFeature
        );

        // wstawiamy nową wartość
        $db->insert('feature_product', [
            'id_feature' => (int) $idFeature,
            'id_product' => (int) $idProduct,
            'id_feature_value' => (int) $idFeatureValue,
        ]);
    }
}
