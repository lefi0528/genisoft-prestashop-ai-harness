<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Returns the free-shipping bar for the visitor's current cart, as HTML (empty when hidden).
 * views/js/cart_threshold.js requests it after each AJAX cart change, then swaps the bar.
 */
class Genisoft_CartthresholdBarModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        // Answer through displayAjax() even without an "ajax" parameter: this controller has no page.
        $this->ajax = true;
    }

    public function initContent()
    {
        // Only the bar is rendered: skip the page variables and the displayHeader hook of a full page.
    }

    public function displayAjax()
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        $this->ajaxRender($this->module instanceof Genisoft_Cartthreshold ? $this->module->renderBar() : '');
    }
}
