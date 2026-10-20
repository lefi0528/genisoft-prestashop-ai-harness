# Lecture 03: The Hook Dispatch & Execution Lifecycle

## The Hook Catalog (669 entries in `data/ps-hooks-reference.json`)

PrestaShop modules integrate into the core exclusively through **Hooks**. Hooks fall into three structural categories:

1. **Display Hooks** (`displayHeader`, `displayTop`, `displayProductAdditionalInfo`):
   Return rendered HTML strings to be injected into the DOM.
2. **Action Hooks** (`actionCartSave`, `actionValidateOrder`, `actionProductUpdate`):
   Execute event-driven business logic with no return value.
3. **Filter/Modifier Hooks** (`actionDispatcherBefore`, `actionOutputHTMLBefore`):
   Receive mutable data structures and return modified payloads.

### Hook Naming & Registration Rules

```php
public function install(): bool
{
    return parent::install()
        && $this->registerHook('displayHeader')
        && $this->registerHook('actionCartSave');
}

// Method name MUST follow the format: hook<PascalCaseHookName>
public function hookDisplayHeader(): void
{
    $this->context->controller->registerStylesheet(
        'module-css',
        'modules/' . $this->name . '/views/css/front.css'
    );
}
```
