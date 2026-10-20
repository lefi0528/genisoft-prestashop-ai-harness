<?php
/**
 * Fixture for tests/fixtures.sh: the module registers its own hook here and executes it only in
 * views/templates/hook/banner.tpl. Scanning the module folder gives a CUSTOM_HOOK warning; scanning this
 * file alone cannot see the template and reports an unknown hook.
 */
class CustomHookModule extends Module
{
    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHome')
            && $this->registerHook('displayCustomHookModuleBanner');
    }

    public function hookDisplayHome($params)
    {
        return $this->display(__FILE__, 'views/templates/hook/banner.tpl');
    }
}
