# Lecture 02: PrestaShop 8 Hybrid Architecture: Legacy Core vs Symfony

## The Dual Architecture Paradigm

PrestaShop 8 represents a hybrid architecture:
- **Core Back-Office & API**: Managed by Symfony (Symfony 4.4 in PS 1.7, Symfony 5.4/6.x in PS 8.x).
- **Module System & Front-Office**: Driven by legacy `Context`, `Hook::exec()`, and `Db` singleton patterns.

```
+-----------------------------------------------------------+
|                   PrestaShop 8 Runtime                    |
+-----------------------------+-----------------------------+
|    Symfony Modern Core      |     Legacy Module Engine    |
| - Service Container (DI)    | - Context::getContext()     |
| - Doctrine ORM (Admin)      | - Db::getInstance() (MySQL) |
| - Twig Templates (BO)       | - Smarty 3/4 Engine (FO/BO) |
| - Modern Routing & Security | - Hook::exec() Dispatcher   |
+-----------------------------+-----------------------------+
```

### When to Use Symfony vs Legacy APIs

| Requirement | Preferred Pattern | Fallback / Legacy |
| :--- | :--- | :--- |
| **Database Queries** | `Db::getInstance()->executeS()` | Doctrine EntityManager (Admin only) |
| **Front Office Templates** | Smarty (`.tpl`) | Twig (Experimental in PS 8/9) |
| **Configuration Storage** | `Configuration::get()` / `updateValue()` | Symfony ParameterBag |
| **Translations** | `{l s='...' d='Modules.Name.Shop'}` | `$this->l('...')` |
