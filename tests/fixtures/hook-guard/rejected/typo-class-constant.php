<?php
/**
 * Fixture for tests/fixtures.sh: typos in class constants passed to registerHook() directly and through a loop.
 * Expect: HALLUCINATED_HOOK x2
 */
class FixtureTypoClassConstant extends Module
{
    const HOOKS = ['displayHeader', 'displayHeadr'];
    const ADMIN_HOOKS = ['displayBackOfficeHeadr'];

    public function install()
    {
        if (!parent::install() || !$this->registerHook(self::HOOKS)) {
            return false;
        }
        foreach (static::ADMIN_HOOKS as $hook) {
            $this->registerHook($hook);
        }

        return true;
    }
}
