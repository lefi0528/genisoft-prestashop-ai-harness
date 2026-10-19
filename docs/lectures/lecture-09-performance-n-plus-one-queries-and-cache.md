# Lecture 09: E-Commerce Performance: N+1 Query Elimination & Object Cache

## Eliminating Performance Bottlenecks in High-Traffic Stores

PrestaShop e-commerce stores handle thousands of concurrent cart operations. A poorly written module hook can easily degrade server performance.

### Anti-Pattern: N+1 Hook Execution
```php
// DISASTROUS: Executes a query for every product in the cart
public function hookActionCartSave($params)
{
    foreach ($params['cart']->getProducts() as $product) {
        $data = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'my_table WHERE id_product = ' . (int)$product['id_product']);
    }
}
```

### High-Performance Batch Pattern
```php
// HIGH PERFORMANCE: Single batch query with WHERE IN
public function hookActionCartSave($params)
{
    $productIds = array_map(fn($p) => (int)$p['id_product'], $params['cart']->getProducts());
    if (empty($productIds)) {
        return;
    }

    $results = Db::getInstance()->executeS('
        SELECT * FROM `' . _DB_PREFIX_ . 'my_table`
        WHERE `id_product` IN (' . implode(',', $productIds) . ')
    ');
}
```
