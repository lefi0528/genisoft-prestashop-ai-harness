# Lecture 05: Multi-Shop & Multi-Lang Context Leaks

## The Silent Multi-Store Disaster

PrestaShop allows a single database to host dozens of distinct storefronts (Multi-Boutique). Each store operates under its own `id_shop`, currency, and language set.

### How AI Agents Cause Cross-Store Data Leakage

When an unconstrained agent writes a product query:
```php
// DEFECTIVE QUERY
$products = Db::getInstance()->executeS('
    SELECT p.id_product, p.price, pl.name 
    FROM ' . _DB_PREFIX_ . 'product p
    JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product
');
```
**Catastrophic Consequence:**
In a multi-store setup, `product_lang` contains multiple rows for the same product across different shops and languages. This query returns duplicate rows, displays incorrect prices, or leaks private B2B products to the public B2C store.

### The Guarded Multi-Shop Pattern
```php
$idShop = (int) $this->context->shop->id;
$idLang = (int) $this->context->language->id;

$sql = 'SELECT ps.`id_product`, ps.`price`, pl.`name`
        FROM `' . _DB_PREFIX_ . 'product_shop` ps
        INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
            ON (ps.`id_product` = pl.`id_product` AND pl.`id_shop` = ' . $idShop . ' AND pl.`id_lang` = ' . $idLang . ')
        WHERE ps.`id_shop` = ' . $idShop . '
          AND ps.`active` = 1';
```
