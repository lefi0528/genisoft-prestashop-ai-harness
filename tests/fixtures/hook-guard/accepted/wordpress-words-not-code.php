<?php
/**
 * Fixture for tests/fixtures.sh: WordPress names in comments and strings are not code, and init(),
 * admin_init() and save_post() are ordinary method names. Unlike add_action('wp_head', ...) in WordPress,
 * PrestaShop uses registerHook().
 */
class FixtureWordPressWords extends Module
{
    // add_filter('the_content', 'x') and wp_enqueue_script() would be WordPress; this is a comment.

    public function install()
    {
        return parent::install() && $this->registerHook('displayHeader');
    }

    public function getHelp()
    {
        $text = $this->l('Migration from add_filter() and wp_head() in WordPress');
        $more = "Do not call add_action() here";
        $doc = <<<TXT
        WooCommerce users: woocommerce_cart_calculate_fees() maps to a cart rule.
        TXT;

        return $text . $more . $doc . (function_exists('add_action') ? 'WordPress' : 'PrestaShop');
    }

    public function init()
    {
        $this->admin_init();
        $this->save_post();
    }

    protected function admin_init()
    {
    }

    protected function save_post()
    {
    }
}
