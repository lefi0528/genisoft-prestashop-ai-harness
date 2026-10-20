<?php
/**
 * Fixture for tests/fixtures.sh: loops written without braces and with the foreach: ... endforeach syntax.
 * Expect: HALLUCINATED_HOOK x2
 */
class FixtureTypoLoopWithoutBraces extends Module
{
    public function install()
    {
        foreach (['displayHeadr'] as $hook)
            $this->registerHook($hook);

        $more = [];
        $more[] = 'displayFootr';
        foreach ($more as $hook):
            $this->registerHook($hook);
        endforeach;

        return parent::install();
    }
}
