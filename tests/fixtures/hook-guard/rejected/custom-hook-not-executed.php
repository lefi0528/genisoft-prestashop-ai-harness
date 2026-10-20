<?php
/**
 * Fixture for tests/fixtures.sh: a hook name that neither the core nor the module executes stays an error,
 * for the registration and for the hook method.
 * Expect: HALLUCINATED_HOOK x2
 */
class FixtureCustomHookNotExecuted extends Module
{
    public function install()
    {
        return parent::install() && $this->registerHook('displayFixtureBanner');
    }

    public function hookDisplayFixtureBanner($params)
    {
        return '';
    }
}
