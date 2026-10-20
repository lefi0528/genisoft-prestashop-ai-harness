# Lecture 03: The Hook Dispatch & Execution Lifecycle

## The Hook Catalog (1,301 hook names in `data/ps-hooks-reference.json`)

PrestaShop modules integrate into the core exclusively through **Hooks**. Hooks fall into three structural categories:

1. **Display Hooks** (`displayHeader`, `displayTop`, `displayProductAdditionalInfo`):
   Return rendered HTML strings to be injected into the DOM.
2. **Action Hooks** (`actionCartSave`, `actionValidateOrder`, `actionProductUpdate`):
   Execute event-driven business logic with no return value.
3. **Filter/Modifier Hooks** (`actionDispatcherBefore`, `actionOutputHTMLBefore`):
   Receive mutable data structures and return modified payloads.

The catalog was rebuilt from three real installs (PrestaShop 1.7.8.11, 8.2.8 and 9.2.0): the `ps_hook` table, and every hook the core, the classic and hummingbird themes and PrestaShop's own modules execute. No static list is complete, though:

- **Hooks named at runtime**: `ObjectModel` executes `actionObject<Class>AddAfter` (and `Update`, `Delete`, `Before` variants) for every class, including a module's own classes; grids, Symfony forms and admin controllers build names the same way (`action<Grid>GridDefinitionModifier`, `actionAfterUpdate<Form>FormHandler`, `actionAdmin<Controller>Controller<Action>After`). The hook guard accepts these by pattern.
- **Hooks found through a property**: `paymentOptions`, mandatory for payment modules, is executed by `PaymentOptionsFinder` through `$this->hookName`, not by a literal `Hook::exec('paymentOptions')`.
- **Legacy aliases** (`ps_hook_alias`): `header`, `top`, `footer` or `paymentReturn` still work and map to `displayHeader`, `displayTop`, `displayFooter` and `displayPaymentReturn`. Use the modern name.
- **Custom hooks**: a module can create its own hook by registering a name and executing it with `Hook::exec('displayMyModuleBanner')` or `{hook h='displayMyModuleBanner'}`.

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
