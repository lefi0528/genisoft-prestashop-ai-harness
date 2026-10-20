<?php
/**
 * Fixture for tests/fixtures.sh: every registration form the guard reads, all with valid hook names.
 * rejected/ holds the same forms with a typo, to prove that each form is actually read.
 */
class FixtureRegistrationForms extends Module
{
    const FRONT_HOOKS = ['displayHeader', 'displayFooter'];
    const HOOK_GROUPS = [
        'front' => ['displayTop', 'displayHome'],
        'back' => ['displayBackOfficeHeader'],
    ];
    const HOOK_POSITIONS = [
        'displayLeftColumn' => ['position' => 1],
        'displayRightColumn' => ['position' => 2],
    ];

    private $cartHooks = array('actionCartSave', 'displayShoppingCart');
    protected static $orderHooks = ['actionValidateOrder'];
    private $adminHooks;

    public function __construct()
    {
        $this->adminHooks = ['actionAdminControllerSetMedia'];
        parent::__construct();
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook(self::FRONT_HOOKS)
            || !$this->registerHook(static::HOOK_GROUPS['front'])
            || !$this->registerHook(array_merge(self::$orderHooks, ['actionOrderStatusPostUpdate']))
            || !$this->registerHook($this->getProductHooks())
            || !Hook::registerHook($this, 'displayFooterProduct')
        ) {
            return false;
        }

        $customerHooks = array('actionCustomerAccountAdd', 'displayCustomerAccount');
        foreach ($customerHooks as $hook) {
            if (!$this->registerHook($hook)) {
                PrestaShopLogger::addLog("Cannot register {$hook}", 3);
                return false;
            }
        }
        foreach ($this->cartHooks as $hook) {
            $this->registerHook($hook);
        }
        foreach ($this->adminHooks as $hook) {
            $this->registerHook($hook);
        }
        foreach (self::HOOK_GROUPS as $group) {
            foreach ($group as $hook) {
                $this->registerHook($hook);
            }
        }
        foreach (self::HOOK_POSITIONS as $hook => $settings) {
            $this->registerHook($hook);
        }
        foreach (['displayNav1', 'displayNav2'] as $hook)
            $this->registerHook($hook);

        $searchHooks = [];
        $searchHooks[] = 'actionSearch';
        foreach ($searchHooks as $hook):
            $this->registerHook($hook);
        endforeach;

        return true;
    }

    private function getProductHooks()
    {
        return ['displayProductAdditionalInfo', 'actionProductUpdate'];
    }
}
