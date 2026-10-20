<?php
/**
 * Fixture for tests/fixtures.sh: PHP method names are case-insensitive, so PrestaShop calls these methods.
 * DisplayOverrideTemplate is executed by the core with a capital D (FrontController) and must be accepted as is.
 */
class FixtureHookMethodCasing extends Module
{
    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('DisplayOverrideTemplate');
    }

    public function hookdisplayheader($params)
    {
        // Empty on purpose: the hook guard only reads the method name.
    }

    public function HOOKDISPLAYTOP($params)
    {
        // Empty on purpose: the hook guard only reads the method name.
    }

    public function hookDisplayOverrideTemplate($params)
    {
        // Empty on purpose: the hook guard only reads the method name.
    }
}
