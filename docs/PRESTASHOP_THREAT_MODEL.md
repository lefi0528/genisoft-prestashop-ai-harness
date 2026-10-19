# PrestaShop 8+ AppSec Threat Model for AI Coding Agents

When Large Language Models generate or refactor code in PrestaShop environments, they encounter structural traps that differ significantly from standard Laravel or WordPress architectures.

## Threat Matrix

| Threat Category | Root Cause in AI Agents | Consequence | Mitigation Gate |
| :--- | :--- | :--- | :--- |
| **SQL Injection (CWE-89)** | Treating SQL as a plain string rather than parameterized structure. | Complete DB takeover, exfiltration of customer hashed passwords. | `tools/ps-sql-guard.php` |
| **Cross-Site Scripting (CWE-79)** | Omitting Smarty escaping modifiers (`|escape`). | Session hijacking via admin back-office reflected XSS. | `tools/ps-smarty-guard.js` |
| **Cross-Store Data Leak** | Forgetting multi-store table joins (`id_shop`). | Products, orders, and customer lists leaked to competitor store. | `tools/ps-context-guard.php` |
| **Silent Hook Death** | Hallucinating hook names (`actionCartUpdate`). | Module silently fails to execute; business logic broken. | `tools/ps-hook-guard.php` |
| **Directory Index Traversal** | Leaving subdirectories without `index.php`. | Direct file listing reveals backup files and sensitive logs. | `tools/audit-harness.sh` |

## Code Examples of Critical Flaws

### Flaw 1: Unescaped Concatenation in `executeS`
```php
// CRITICAL VULNERABILITY
$id_cart = Tools::getValue('id_cart');
$results = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . $id_cart);
```
**Harness Correction:**
```php
// SECURE
$idCart = (int) Tools::getValue('id_cart');
$results = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'cart` WHERE `id_cart` = ' . $idCart);
```

### Flaw 2: Unescaped Smarty Output
```tpl
{* VULNERABLE *}
<p class="announcement">{$custom_message}</p>
```
**Harness Correction:**
```tpl
{* SECURE *}
<p class="announcement">{$custom_message|escape:'html':'UTF-8'}</p>
```
