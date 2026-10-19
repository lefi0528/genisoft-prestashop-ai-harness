# Lecture 04: Offensive AppSec: SQL Injection Prevention

## PrestaShop SQL Defense: `pSQL()` vs `(int)`

PrestaShop does not use prepared statements with placeholders by default in modules. Developers interact with the database via `Db::getInstance()`.

### The Core Rules of Sanitization

1. **All Numerical IDs MUST be explicitly cast to `(int)`**:
```php
$idOrder = (int) Tools::getValue('id_order');
```
2. **All String Literals MUST be escaped using `pSQL()`**:
```php
$search = pSQL(Tools::getValue('query'));
```
3. **Table Names MUST use the `_DB_PREFIX_` constant**:
```php
$sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . (int) $idOrder;
```

### What `pSQL()` Does Under the Hood
`pSQL($string, $htmlOK = false)` runs `addslashes()` or MySQL real escape, and optionally strips malicious HTML tags.

```php
// VULNERABLE TO 2ND ORDER SQL INJECTION
$comment = Tools::getValue('comment');
Db::getInstance()->execute("INSERT INTO " . _DB_PREFIX_ . "comments (body) VALUES ('$comment')");

// SECURED
$comment = pSQL(Tools::getValue('comment'), true);
Db::getInstance()->execute("INSERT INTO `" . _DB_PREFIX_ . "comments` (`body`) VALUES ('" . $comment . "')");
```
