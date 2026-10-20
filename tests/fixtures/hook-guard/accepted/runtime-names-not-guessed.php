<?php
/**
 * Fixture for tests/fixtures.sh: names built at runtime or read from input are not resolved, so strings
 * passed to other functions or concatenated into a name must not be reported as hook names.
 */
class FixtureRuntimeNames extends Module
{
    public function __construct()
    {
        $this->name = 'fixtureruntimenames';
        parent::__construct();
    }

    public function install()
    {
        $requested = Tools::getValue('hook');
        $config = json_decode(Configuration::get('FIXTURE_HOOKS'), true);

        return parent::install()
            && $this->registerHook('display' . ucfirst($this->name))
            && $this->registerHook($this->name . 'Banner')
            && $this->registerHook($requested)
            && $this->registerHook($config['hooks']);
    }
}
