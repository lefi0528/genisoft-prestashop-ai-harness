<?php
/**
 * Fixture for tests/fixtures.sh: real WordPress API calls and WordPress hook names passed to registerHook().
 * Expect: WP_HALLUCINATION x6
 */
class FixtureWordPressCalls extends Module
{
    public function install()
    {
        add_action('wp_head', [$this, 'printHead']);
        \add_filter('the_content', [$this, 'filterContent']);
        wp_enqueue_script('fixture', 'views/js/front.js');
        do_action('fixture_installed');

        return parent::install()
            && $this->registerHook('init')
            && $this->registerHook('wp_footer');
    }
}
