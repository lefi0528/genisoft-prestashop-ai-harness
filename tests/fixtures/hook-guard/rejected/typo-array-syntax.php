<?php
/**
 * Fixture for tests/fixtures.sh: a typo in a list written with array() and registered in a loop.
 * Expect: HALLUCINATED_HOOK x1
 */
class FixtureTypoArraySyntax extends Module
{
    public function install()
    {
        $hooks = array('displayHeader', 'displayHeadr');
        foreach ($hooks as $hook) {
            $this->registerHook($hook);
        }

        return parent::install();
    }
}
