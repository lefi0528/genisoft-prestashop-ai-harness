# Lecture 01: Why AI Agents Fail on PrestaShop

## The PrestaShop Complexity Gap

Foundation models such as Claude Opus, GPT-4o, and DeepSeek are trained predominantly on open-source code from generic PHP frameworks (Laravel, Symfony) and WordPress. While PrestaShop 8 is based on Symfony, its module subsystem retains 15 years of legacy conventions.

When an AI agent is instructed to "create a PrestaShop discount module", it consistently makes four fatal assumptions:

### 1. WordPress Function Bleed
Because WordPress occupies 60%+ of open-source CMS repositories, models hallucinate WordPress hooks:
```php
// FATAL ERROR IN PRESTASHOP
add_action('init', 'my_custom_init');
add_filter('the_content', 'my_content_filter');
```
PrestaShop does not have `add_action()`. Modules must extend the base `Module` class and invoke `$this->registerHook('actionName')`.

### 2. The Smarty Compilation Trap
AI models frequently output raw variable tags:
```tpl
<div class="user-greeting">Welcome {$user_name}</div>
```
In PrestaShop, this represents a severe **Cross-Site Scripting (XSS)** vulnerability. If `$user_name` contains malicious JavaScript, it executes in the customer's browser.

### 3. The Multi-Store Blind Spot
In multi-store merchants, failing to filter by `id_shop` joins all products across all sister shops, causing severe data leaks.
