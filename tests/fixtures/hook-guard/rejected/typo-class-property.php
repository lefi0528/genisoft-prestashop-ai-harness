<?php
/**
 * Fixture for tests/fixtures.sh: typos in a property default value and in a property set in the constructor.
 * Expect: HALLUCINATED_HOOK x2
 */
class FixtureTypoClassProperty extends Module
{
    private $hooksList = ['displayHeader', 'displayHeadr'];
    private $orderHooks;

    public function __construct()
    {
        $this->orderHooks = ['actionValidateOrdr'];
        parent::__construct();
    }

    public function install()
    {
        foreach ($this->hooksList as $hook) {
            $this->registerHook($hook);
        }

        return parent::install() && $this->registerHook($this->orderHooks);
    }
}
