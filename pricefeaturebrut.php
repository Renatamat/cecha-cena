<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PriceFeatureBrut extends Module
{
    /**
     * Feature that stores the price range.
     */
    private const FEATURE_ID = 8;

    /**
     * Map of maximum gross price => feature value ID.
     */
    private const PRICE_TO_FEATURE_VALUE = [
        ['max' => 25, 'value' => 42],
        ['max' => 50, 'value' => 43],
        ['max' => 100, 'value' => 44],
        ['max' => 200, 'value' => 45],
    ];

    /**
     * Default feature value used when the price is above every declared threshold.
     */
    private const FALLBACK_FEATURE_VALUE = 46;

    public function __construct()
    {
        $this->name = 'pricefeaturebrut';
        $this->tab = 'administration';
        $this->version = '1.1.0';
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
        $this->handleProductHook($params);
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        $this->handleProductHook($params);
    }

    /**
     * Public entry point for cron or manual recalculation.
     */
    public function recalculateProductFeatureById($idProduct)
    {
        $product = new Product((int) $idProduct);

        if (!Validate::isLoadedObject($product)) {
            return false;
        }

        $this->assignPriceFeature($product);

        return true;
    }

    private function handleProductHook(array $params)
    {
        $product = $this->extractProductFromParams($params);

        if (!$product) {
            return;
        }

        $this->assignPriceFeature($product);
    }

    private function extractProductFromParams(array $params)
    {
        if (isset($params['object']) && $params['object'] instanceof Product) {
            return $params['object'];
        }

        if (isset($params['id_product'])) {
            $product = new Product((int) $params['id_product']);

            if (Validate::isLoadedObject($product)) {
                return $product;
            }
        }

        return null;
    }

    private function assignPriceFeature(Product $product)
    {
        $idProduct = (int) $product->id;

        if ($idProduct <= 0) {
            return;
        }

        $priceTtc = (float) Product::getPriceStatic($idProduct, true);
        $idFeatureValue = $this->resolveFeatureValue($priceTtc);

        if (!$idFeatureValue) {
            return;
        }

        $db = Db::getInstance();
        $idFeature = (int) self::FEATURE_ID;

        $currentValue = (int) $db->getValue(
            'SELECT id_feature_value FROM ' . _DB_PREFIX_ . 'feature_product '
            . 'WHERE id_product = ' . (int) $idProduct . ' AND id_feature = ' . (int) $idFeature
            . ' ORDER BY id_feature_value DESC'
        );

        if ($currentValue === $idFeatureValue) {
            return;
        }

        $db->delete(
            'feature_product',
            'id_product = ' . (int) $idProduct . ' AND id_feature = ' . (int) $idFeature
        );

        $db->insert('feature_product', [
            'id_feature' => (int) $idFeature,
            'id_product' => (int) $idProduct,
            'id_feature_value' => (int) $idFeatureValue,
        ]);
    }

    private function resolveFeatureValue($price)
    {
        foreach (self::PRICE_TO_FEATURE_VALUE as $range) {
            if ($price <= $range['max']) {
                return (int) $range['value'];
            }
        }

        return (int) self::FALLBACK_FEATURE_VALUE;
    }
}
