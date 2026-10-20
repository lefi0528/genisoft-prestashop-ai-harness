<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Genisoft Cart Threshold Progression Module
 *
 * Example module: shows on the front office how much the customer still has to add before
 * PrestaShop's own free-shipping rule applies (Shipping > Preferences > "Free shipping starts at"),
 * refreshes that bar after AJAX cart updates, and is configured through a HelperForm
 * (CSRF token handled by the back office).
 * Installed and tested on PrestaShop 1.7.8.11 (PHP 7.4), 8.2.8 (PHP 8.1) and 9.2.0 (PHP 8.3).
 *
 * @author Genisoft - Lefi Abdelmonem <contact@genisoft.fr>
 * @license MIT
 */
class Genisoft_Cartthreshold extends Module
{
    public const CONFIG_ACTIVE = 'GENISOFT_THRESHOLD_ACTIVE';

    /**
     * PrestaShop's own free-shipping amount, in the default currency, compared with the cart total
     * tax included, discounts deducted, shipping excluded (Cart::getPackageShippingCost()).
     */
    public const CONFIG_FREE_SHIPPING_PRICE = 'PS_SHIPPING_FREE_PRICE';

    /** Module's own amount up to 1.1.0, which PrestaShop never applied (see upgrade/upgrade-1.2.0.php). */
    public const LEGACY_CONFIG_AMOUNT = 'GENISOFT_THRESHOLD_AMOUNT';

    /** Table created by 1.0.0 and dropped since 1.1.0 (see upgrade/upgrade-1.1.0.php). */
    public const LEGACY_LOG_TABLE = 'genisoft_threshold_log';

    public function __construct()
    {
        $this->name = 'genisoft_cartthreshold';
        $this->tab = 'pricing_promotion';
        $this->version = '1.2.0';
        $this->author = 'Genisoft - Lefi Abdelmonem';
        $this->need_instance = 0;
        $this->bootstrap = true;
        // Upper bound = highest version actually tested. Raise it only after testing a newer release.
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => '9.2.99'];

        parent::__construct();

        $this->displayName = $this->trans('Genisoft Cart Threshold Progression', [], 'Modules.Genisoftcartthreshold.Admin');
        $this->description = $this->trans('Shows customers how much more to spend before PrestaShop offers free shipping.', [], 'Modules.Genisoftcartthreshold.Admin');
    }

    /**
     * Strings use the domains Modules.Genisoftcartthreshold.Admin and .Shop, translated in
     * translations/<locale>/*.xlf and in the back office (International > Translations).
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        // parent::install() has already registered and enabled the module: undo it if a later step
        // fails, whether it returns false or throws (Hook::registerHook() throws on an invalid name).
        try {
            $installed = $this->registerHook(['displayHeader', 'displayTop'])
                && Configuration::updateValue(self::CONFIG_ACTIVE, 1);
        } catch (Throwable $e) {
            $this->uninstall();

            throw $e;
        }

        if (!$installed) {
            $this->uninstall();
            $this->_errors[] = $this->trans('Could not register the hooks or save the settings.', [], 'Modules.Genisoftcartthreshold.Admin');

            return false;
        }

        return true;
    }

    public function uninstall(): bool
    {
        // PS_SHIPPING_FREE_PRICE belongs to PrestaShop and is kept.
        Configuration::deleteByName(self::CONFIG_ACTIVE);
        Configuration::deleteByName(self::LEGACY_CONFIG_AMOUNT);

        return self::dropLegacyLogTable() && parent::uninstall();
    }

    /**
     * Removes the write-only log table that 1.0.0 filled in actionCartSave.
     * Nothing ever read it, and computing the cart total in that hook crashed
     * PrestaShop 1.7.8 when a customer with a cart switched language or currency.
     */
    public static function dropLegacyLogTable(): bool
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::LEGACY_LOG_TABLE . '`');
    }

    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitGenisoftCartThreshold')) {
            $output .= $this->processConfiguration();
        }

        if ((float) Configuration::get(self::CONFIG_FREE_SHIPPING_PRICE) <= 0) {
            $output .= $this->displayWarning($this->trans('Free shipping by order amount is off ("Free shipping starts at" is 0), so the bar is hidden. Enter an amount below to turn it on.', [], 'Modules.Genisoftcartthreshold.Admin'));
        }

        return $output . $this->renderForm();
    }

    private function processConfiguration(): string
    {
        // A French back office types "49,90": accept the decimal comma instead of casting it to 49.
        $amount = str_replace(',', '.', trim((string) Tools::getValue(self::CONFIG_FREE_SHIPPING_PRICE)));

        if (!Validate::isPrice($amount) || (float) $amount <= 0) {
            return $this->displayError($this->trans('Enter an amount greater than zero, for example 49.90 or 49,90.', [], 'Modules.Genisoftcartthreshold.Admin'));
        }

        Configuration::updateValue(self::CONFIG_FREE_SHIPPING_PRICE, (float) $amount);
        Configuration::updateValue(self::CONFIG_ACTIVE, (int) (bool) Tools::getValue(self::CONFIG_ACTIVE));

        return $this->displayConfirmation($this->trans('Settings updated successfully.', [], 'Modules.Genisoftcartthreshold.Admin'));
    }

    public function hookDisplayHeader(): void
    {
        if (!Configuration::get(self::CONFIG_ACTIVE)) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'genisoft-cart-threshold-css',
            'modules/' . $this->name . '/views/css/cart_threshold.css',
            ['media' => 'all', 'priority' => 150]
        );
        // Loaded after the theme's core.js, which defines prestashop.on().
        $this->context->controller->registerJavascript(
            'genisoft-cart-threshold-js',
            'modules/' . $this->name . '/views/js/cart_threshold.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    public function hookDisplayTop(): string
    {
        return $this->renderBar();
    }

    /**
     * Renders the bar container for the current cart. Also called by controllers/front/bar.php,
     * which views/js/cart_threshold.js requests after each AJAX cart update.
     */
    public function renderBar(): string
    {
        if (!Configuration::get(self::CONFIG_ACTIVE) || !isset($this->context->cart, $this->context->currency)) {
            return '';
        }

        $this->context->smarty->assign([
            'genisoft_bar' => $this->getBarData($this->context->cart),
            'genisoft_refresh_url' => $this->context->link->getModuleLink($this->name, 'bar'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/cart_threshold_bar.tpl');
    }

    /**
     * Amounts shown by the bar, or null when PrestaShop has no free shipping to offer for this cart:
     * no free-shipping amount, or virtual products only (nothing to ship). The template then keeps
     * an empty container, so that the bar can still appear after a later AJAX cart change.
     */
    private function getBarData(Cart $cart): ?array
    {
        $isSaved = Validate::isLoadedObject($cart);
        if ($isSaved && $cart->isVirtualCart()) {
            return null;
        }

        $threshold = $this->getFreeShippingThreshold($cart);
        if ($threshold <= 0) {
            return null;
        }

        // Same total and comparison as Cart::getPackageShippingCost().
        $totalCart = $isSaved ? (float) $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING) : 0.0;
        $reached = $totalCart >= $threshold;

        return [
            'threshold' => $this->formatPrice($threshold),
            'remaining' => $this->formatPrice($reached ? 0.0 : $threshold - $totalCart),
            'reached' => $reached,
            'progress_percent' => min(100, (int) (($totalCart / $threshold) * 100)),
        ];
    }

    /**
     * Cart amount at which PrestaShop makes shipping free, in the visitor's currency (0 = never).
     * Same rule as Cart::getPackageShippingCost(): PS_SHIPPING_FREE_PRICE (default currency)
     * converted to the visitor's currency, then, since PrestaShop 9.1, the
     * actionOverrideShippingFreePrice hook through which other modules change that amount, for example per zone.
     */
    private function getFreeShippingThreshold(Cart $cart): float
    {
        $currency = $this->context->currency;
        $threshold = (float) Configuration::get(self::CONFIG_FREE_SHIPPING_PRICE);
        if ($threshold > 0) {
            $threshold = (float) Tools::convertPrice($threshold, $currency);
        }

        if (version_compare(_PS_VERSION_, '9.1.0', '>=')) {
            Hook::exec('actionOverrideShippingFreePrice', [
                'shippingFreePrice' => &$threshold,
                'id_zone' => $this->getDeliveryZoneId($cart),
                'id_currency' => (int) $currency->id,
            ]);
        }

        return (float) $threshold;
    }

    /** Zone of the customer's delivery address, else of the visitor's country, as in Cart::getPackageShippingCost(). */
    private function getDeliveryZoneId(Cart $cart): int
    {
        if ($cart->id_address_delivery
            && Customer::customerHasAddress((int) $cart->id_customer, (int) $cart->id_address_delivery)
        ) {
            return (int) Address::getZoneById((int) $cart->id_address_delivery);
        }

        return isset($this->context->country) ? (int) $this->context->country->id_zone : 0;
    }

    /**
     * Tools::displayPrice() was removed in PrestaShop 9; the CLDR locale API
     * exists in 1.7.6 through 9.x and formats in the visitor's currency and language.
     */
    private function formatPrice(float $amount): string
    {
        return $this->context->getCurrentLocale()->formatPrice($amount, $this->context->currency->iso_code);
    }

    private function renderForm(): string
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitGenisoftCartThreshold';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value[self::CONFIG_ACTIVE] = Configuration::get(self::CONFIG_ACTIVE);
        $helper->fields_value[self::CONFIG_FREE_SHIPPING_PRICE] = Configuration::get(self::CONFIG_FREE_SHIPPING_PRICE);

        // PS_SHIPPING_FREE_PRICE is stored in the shop's default currency.
        $defaultCurrency = Currency::getDefaultCurrency();

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Free shipping bar', [], 'Modules.Genisoftcartthreshold.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Show the bar', [], 'Modules.Genisoftcartthreshold.Admin'),
                        'name' => self::CONFIG_ACTIVE,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Enabled', [], 'Modules.Genisoftcartthreshold.Admin')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('Disabled', [], 'Modules.Genisoftcartthreshold.Admin')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Free shipping starts at', [], 'Modules.Genisoftcartthreshold.Admin'),
                        'name' => self::CONFIG_FREE_SHIPPING_PRICE,
                        'suffix' => $defaultCurrency ? $defaultCurrency->sign : '',
                        'required' => true,
                        'class' => 'fixed-width-lg',
                        'desc' => $this->trans('PrestaShop setting, also in Shipping > Preferences. Once the cart reaches this amount (tax incl., discounts deducted, shipping excluded), PrestaShop makes shipping free; the bar counts down to it. Saving it here changes it there.', [], 'Modules.Genisoftcartthreshold.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Genisoftcartthreshold.Admin'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        return $helper->generateForm([$fieldsForm]);
    }
}
