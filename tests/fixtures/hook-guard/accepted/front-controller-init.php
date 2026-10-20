<?php
/**
 * Fixture for tests/fixtures.sh: init() is the standard entry point of PrestaShop front controllers.
 */
class FixtureDisplayModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();
        $this->display_column_left = false;
    }

    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('module:fixture/views/templates/front/display.tpl');
    }
}
