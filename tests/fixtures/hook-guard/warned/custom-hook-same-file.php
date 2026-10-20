<?php
/**
 * Fixture for tests/fixtures.sh: a module may create its own hook by registering it and executing it with
 * Hook::exec(). That name is not a core hook, so it gets a warning instead of an error.
 * Expect: CUSTOM_HOOK x2
 */
class FixtureCustomHook extends Module
{
    const BANNER_HOOK = 'displayFixtureBanner';

    public function install()
    {
        return parent::install() && $this->registerHook('displayFixtureBanner');
    }

    public function renderBanner()
    {
        return Hook::exec(self::BANNER_HOOK, ['id_shop' => (int) $this->context->shop->id]);
    }

    public function hookDisplayFixtureBanner($params)
    {
        return '';
    }
}
