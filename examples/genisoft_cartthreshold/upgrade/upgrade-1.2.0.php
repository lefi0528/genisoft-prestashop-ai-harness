<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.1.0 -> 1.2.0: the bar follows PrestaShop's own free-shipping amount (PS_SHIPPING_FREE_PRICE)
 * instead of a module amount that PrestaShop never applied. No hook changes: the AJAX refresh
 * uses the front controller controllers/front/bar.php and the existing displayHeader hook.
 *
 * The old module amount is deleted, not copied into PS_SHIPPING_FREE_PRICE: copying it would
 * start giving free shipping on a live shop without the merchant deciding it. While
 * PS_SHIPPING_FREE_PRICE is 0 the bar stays hidden and the configuration page says why.
 *
 * @param Genisoft_Cartthreshold $module
 */
function upgrade_module_1_2_0($module): bool
{
    return Configuration::deleteByName(Genisoft_Cartthreshold::LEGACY_CONFIG_AMOUNT);
}
