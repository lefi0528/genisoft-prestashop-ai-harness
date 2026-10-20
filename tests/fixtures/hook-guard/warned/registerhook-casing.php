<?php
/**
 * Fixture for tests/fixtures.sh: PrestaShop looks hook names up case-insensitively, so a wrong casing in
 * registerHook() works but gets a warning with the official spelling.
 * Expect: HOOK_CASING x1
 */
class FixtureRegisterHookCasing extends Module
{
    public function install()
    {
        return parent::install() && $this->registerHook('displayheader');
    }
}
