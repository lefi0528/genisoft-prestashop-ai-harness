<?php
declare(strict_types=1);

// Guarded solution compliant with PrestaShop 8+ hook specifications
class GuardedModule extends Module
{
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        // Compliant official PrestaShop 8+ hooks from 669 catalog
        $validHooks = [
            'displayHeader',
            'actionProductSave',
            'actionCartSave'
        ];

        foreach ($validHooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    public function hookDisplayHeader(): void
    {
        // Safe asset registration
    }

    public function hookActionProductSave(array $params): void
    {
        // Valid hook execution
    }

    public function hookActionCartSave(array $params): void
    {
        // Valid cart lifecycle hook
    }
}
