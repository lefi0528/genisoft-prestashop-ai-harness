<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Genisoft Cart Threshold & Free Gift Progression Module
 *
 * Implements strict PrestaShop 8+ architecture, AppSec parameters,
 * HelperForm with CSRF token, and multi-shop context isolation.
 *
 * @author Genisoft - Lefi Abdelmonem <contact@genisoft.fr>
 * @license MIT
 */
class Genisoft_Cartthreshold extends Module
{
    public const CONFIG_AMOUNT = 'GENISOFT_THRESHOLD_AMOUNT';
    public const CONFIG_ACTIVE = 'GENISOFT_THRESHOLD_ACTIVE';

    public function __construct()
    {
        $this->name = 'genisoft_cartthreshold';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Genisoft - Lefi Abdelmonem';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Genisoft Cart Threshold Progression');
        $this->description = $this->l('Displays a dynamic progress bar for free shipping thresholds.');
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        $hooks = [
            'displayHeader',
            'displayTop',
            'actionCartSave'
        ];

        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        Configuration::updateValue(self::CONFIG_AMOUNT, 50.00);
        Configuration::updateValue(self::CONFIG_ACTIVE, 1);

        return $this->executeSqlScript('install');
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName(self::CONFIG_AMOUNT);
        Configuration::deleteByName(self::CONFIG_ACTIVE);

        return $this->executeSqlScript('uninstall') && parent::uninstall();
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
        if (!Configuration::get(self::CONFIG_ACTIVE) || !isset($this->context->cart)) {
            return '';
        }

        $threshold = (float) Configuration::get(self::CONFIG_AMOUNT);
        $cart = $this->context->cart;
        $totalCart = (float) $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);

        $remaining = max(0.0, $threshold - $totalCart);
        $progress = $threshold > 0 ? min(100, (int) (($totalCart / $threshold) * 100)) : 100;

        $this->context->smarty->assign([
            'genisoft_active' => true,
            'genisoft_threshold_amount' => number_format($threshold, 2),
            'genisoft_threshold_formatted' => Tools::displayPrice($threshold),
            'genisoft_remaining' => $remaining,
            'genisoft_remaining_formatted' => Tools::displayPrice($remaining),
            'genisoft_reached' => ($remaining <= 0.0),
            'genisoft_progress_percent' => $progress
        ]);

        return $this->display(__FILE__, 'views/templates/hook/cart_threshold_bar.tpl');
    }

    public function hookActionCartSave(array $params): void
    {
        if (!isset($params['cart']) || !Validate::isLoadedObject($params['cart'])) {
            return;
        }

        $cart = $params['cart'];
        $idShop = (int) $this->context->shop->id;
        $idCustomer = (int) $cart->id_customer;
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);
        $threshold = (float) Configuration::get(self::CONFIG_AMOUNT);
        $reached = ($cartTotal >= $threshold) ? 1 : 0;

        // Uses UPSERT to prevent infinite table growth on repeated cart micro-updates
        Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'genisoft_threshold_log`
            (`id_cart`, `id_customer`, `id_shop`, `cart_total`, `threshold_reached`, `date_add`)
            VALUES (' . (int) $cart->id . ', ' . $idCustomer . ', ' . $idShop . ', ' . $cartTotal . ', ' . $reached . ', NOW())
            ON DUPLICATE KEY UPDATE
                `cart_total` = VALUES(`cart_total`),
                `threshold_reached` = VALUES(`threshold_reached`),
                `date_add` = NOW()
        ');
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

    private function executeSqlScript(string $type): bool
    {
        $path = dirname(__FILE__) . '/sql/' . $type . '.sql';
        if (!file_exists($path)) {
            return false;
        }

        $sql = (string) file_get_contents($path);
        $sql = str_replace(['_DB_PREFIX_', '_MYSQL_ENGINE_'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);

        // Split multi-query SQL files safely
        $queries = preg_split('/;\s*[\r\n]+/', trim($sql));
        if ($queries === false) {
            return false;
        }

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!Db::getInstance()->execute($query)) {
                    return false;
                }
            }
        }

        return true;
    }
}
