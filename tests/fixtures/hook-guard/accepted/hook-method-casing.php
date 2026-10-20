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
    }

    public function HOOKDISPLAYTOP($params)
    {
    }

    public function hookDisplayOverrideTemplate($params)
    {
    }
}
