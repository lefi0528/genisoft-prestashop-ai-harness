<?php
/**
 * Fixture for tests/fixtures.sh: legacy names from ps_hook_alias still work, so they are accepted with a
 * warning that gives the modern name (header -> displayHeader, paymentReturn -> displayPaymentReturn).
 * Expect: HOOK_ALIAS x3
 */
class FixtureLegacyAliases extends Module
{
    public function install()
    {
        return parent::install()
            && $this->registerHook('header')
            && $this->registerHook('paymentReturn');
    }

    public function hookHeader($params)
    {
    }
}
