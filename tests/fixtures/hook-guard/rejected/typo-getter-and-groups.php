<?php
/**
 * Fixture for tests/fixtures.sh: typos returned by a getter method, inside nested hook groups, in the keys
 * of a hook => settings list, inside array_merge() and in Hook::registerHook($module, 'name').
 * Expect: HALLUCINATED_HOOK x5
 */
class FixtureTypoGetterAndGroups extends Module
{
    const HOOK_GROUPS = [
        'front' => ['displayHeader', 'displayFootr'],
    ];
    const HOOK_POSITIONS = [
        'displayLeftColumm' => ['position' => 1],
    ];

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook($this->getHooks())
            || !$this->registerHook(array_merge(['displayTop'], ['displayHom']))
            || !Hook::registerHook($this, 'actionCartSav')
        ) {
            return false;
        }
        foreach (self::HOOK_GROUPS as $group) {
            foreach ($group as $hook) {
                $this->registerHook($hook);
            }
        }
        foreach (self::HOOK_POSITIONS as $hook => $settings) {
            $this->registerHook($hook);
        }

        return true;
    }

    private function getHooks()
    {
        return ['displayProductAdditionalInf'];
    }
}
