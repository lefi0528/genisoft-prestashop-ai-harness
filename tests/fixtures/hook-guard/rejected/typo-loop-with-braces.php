<?php
/**
 * Fixture for tests/fixtures.sh: the loop body holds braces (an if block, "{$hook}" interpolation) before
 * registerHook(); the old regex stopped at the first '}' and missed the typo.
 * Expect: HALLUCINATED_HOOK x1
 */
class FixtureTypoLoopWithBraces extends Module
{
    public function install()
    {
        $hooks = ['displayHeadr'];
        foreach ($hooks as $hook) {
            if (Shop::isFeatureActive()) {
                Shop::setContext(Shop::CONTEXT_ALL);
            }
            PrestaShopLogger::addLog("Registering {$hook}", 1);
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return parent::install();
    }
}
