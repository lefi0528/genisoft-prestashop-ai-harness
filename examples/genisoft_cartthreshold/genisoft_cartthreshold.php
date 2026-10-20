<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Genisoft Cart Threshold & Free Gift Progression Module
 *
 * Example module: shows a free-shipping progress bar on the front office,
 * configured through a HelperForm (CSRF token handled by the back office).
 * Installed and smoke-tested on PrestaShop 1.7.8.11 (PHP 7.4) and 9.2.0 (PHP 8.3).
 *
 * @author Genisoft - Lefi Abdelmonem <contact@genisoft.fr>
 * @license MIT
 */
class Genisoft_Cartthreshold extends Module
{
    public const CONFIG_AMOUNT = 'GENISOFT_THRESHOLD_AMOUNT';
    public const CONFIG_ACTIVE = 'GENISOFT_THRESHOLD_ACTIVE';

    /** Table created by 1.0.0 and dropped since 1.1.0 (see upgrade/upgrade-1.1.0.php). */
    public const LEGACY_LOG_TABLE = 'genisoft_threshold_log';

    public function __construct()
    {
        $this->name = 'genisoft_cartthreshold';
        $this->tab = 'pricing_promotion';
        $this->version = '1.1.0';
        $this->author = 'Genisoft - Lefi Abdelmonem';
        $this->need_instance = 0;
        $this->bootstrap = true;
        // Upper bound = highest version actually tested. Raise it only after testing a newer release.
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => '9.2.99'];

        parent::__construct();

        $this->displayName = $this->l('Genisoft Cart Threshold Progression');
        $this->description = $this->l('Displays a dynamic progress bar for free shipping thresholds.');
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        $hooks = [
            'displayHeader',
            'displayTop'
        ];

        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        Configuration::updateValue(self::CONFIG_AMOUNT, 50.00);
        Configuration::updateValue(self::CONFIG_ACTIVE, 1);

        return true;
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName(self::CONFIG_AMOUNT);
        Configuration::deleteByName(self::CONFIG_ACTIVE);

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
            $amount = (float) Tools::getValue(self::CONFIG_AMOUNT);
            $active = (int) Tools::getValue(self::CONFIG_ACTIVE);

            if ($amount <= 0) {
                $output .= $this->displayError($this->l('Threshold amount must be greater than zero.'));
            } else {
                Configuration::updateValue(self::CONFIG_AMOUNT, $amount);
                Configuration::updateValue(self::CONFIG_ACTIVE, $active);
                $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
            }
        }

        return $output . $this->renderForm();
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
    }

    public function hookDisplayTop(): string
    {
        if (!Configuration::get(self::CONFIG_ACTIVE) || !isset($this->context->cart, $this->context->currency)) {
            return '';
        }

        // The threshold is stored in the shop's default currency; the cart total is in the visitor's.
        $threshold = (float) Tools::convertPrice((float) Configuration::get(self::CONFIG_AMOUNT), $this->context->currency);
        $cart = $this->context->cart;
        $totalCart = Validate::isLoadedObject($cart)
            ? (float) $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING)
            : 0.0;

        $remaining = max(0.0, $threshold - $totalCart);
        $progress = $threshold > 0 ? min(100, (int) (($totalCart / $threshold) * 100)) : 100;

        $this->context->smarty->assign([
            'genisoft_active' => true,
            'genisoft_threshold_amount' => number_format($threshold, 2),
            'genisoft_threshold_formatted' => $this->formatPrice($threshold),
            'genisoft_remaining' => $remaining,
            'genisoft_remaining_formatted' => $this->formatPrice($remaining),
            'genisoft_reached' => ($remaining <= 0.0),
            'genisoft_progress_percent' => $progress
        ]);

        return $this->display(__FILE__, 'views/templates/hook/cart_threshold_bar.tpl');
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

        $helper->fields_value[self::CONFIG_AMOUNT] = Configuration::get(self::CONFIG_AMOUNT);
        $helper->fields_value[self::CONFIG_ACTIVE] = Configuration::get(self::CONFIG_ACTIVE);

        $currencySign = isset($this->context->currency) ? ' (' . $this->context->currency->sign . ')' : '';

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Cart Threshold Configuration'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Progress Bar'),
                        'name' => self::CONFIG_ACTIVE,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Free Shipping Threshold') . $currencySign,
                        'name' => self::CONFIG_AMOUNT,
                        'required' => true,
                        'class' => 'fixed-width-lg'
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save Settings'),
                    'class' => 'btn btn-default pull-right'
                ]
            ]
        ];

        return $helper->generateForm([$fieldsForm]);
    }
}
