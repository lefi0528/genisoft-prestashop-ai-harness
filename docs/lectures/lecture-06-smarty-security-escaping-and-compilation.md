# Lecture 06: Smarty Template Security & Escaping

## Preventing Cross-Site Scripting (XSS) in Smarty 3/4

Smarty does not enable automatic output escaping by default in legacy themes. Every variable rendered in a `.tpl` template must be explicitly escaped.

### Standard Escaping Modifiers

| Target Context | Modifier | Example |
| :--- | :--- | :--- |
| **HTML Body / Element Content** | `|escape:'html':'UTF-8'` | `<h1>{$title\|escape:'html':'UTF-8'}</h1>` |
| **HTML Attribute Values** | `|escape:'htmlattr':'UTF-8'` | `<input value="{$val\|escape:'htmlattr':'UTF-8'}">` |
| **Inline JavaScript** | `|escape:'javascript':'UTF-8'` | `var id = '{$uid\|escape:'javascript':'UTF-8'}';` |
| **Numerical Counters** | `|intval` | `<span class="badge">{$count\|intval}</span>` |

### PrestaShop 8 Translation Domains
In PrestaShop 8+, translations must declare explicit translation domains:
```tpl
{* PRESTASHOP 8+ DOMAIN FORMAT *}
<button class="btn btn-primary">
    {l s='Add to Cart' d='Shop.Theme.Actions'}
</button>
```
