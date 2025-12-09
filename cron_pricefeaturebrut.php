<?php
// Script cron do pełnej reindeksacji cechy cenowej.
// Uruchomienie: php /ścieżka/do/modules/pricefeaturebrut/cron_pricefeaturebrut.php

require dirname(__FILE__) . '/../../config/config.inc.php';
require dirname(__FILE__) . '/pricefeaturebrut.php';

if (!Module::isInstalled('pricefeaturebrut')) {
    exit('Module pricefeaturebrut is not installed.' . PHP_EOL);
}

$module = Module::getInstanceByName('pricefeaturebrut');
if (!$module || !$module->active) {
    exit('Module pricefeaturebrut is not active.' . PHP_EOL);
}

$context = Context::getContext();
if (!$context->shop || !$context->shop->id) {
    $context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
}

$languageId = (int) Configuration::get('PS_LANG_DEFAULT');
$batchSize = 200;
$offset = 0;

while (true) {
    $products = Product::getProducts(
        $languageId,
        $offset,
        $batchSize,
        'id_product',
        'ASC',
        false
    );

    if (empty($products)) {
        break;
    }

    foreach ($products as $productRow) {
        $module->recalculateProductFeatureById((int) $productRow['id_product']);
    }

    $offset += $batchSize;
}

echo 'Price feature recalculation finished.' . PHP_EOL;
