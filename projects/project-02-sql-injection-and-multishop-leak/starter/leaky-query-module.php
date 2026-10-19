<?php
// Vulnerable SQL query written by unconstrained AI agent
class VulnerableRepository
{
    public function findCustomerOrders($id_customer, $search)
    {
        // Direct string interpolation & concatenation (SQL injection vector)
        $sql = "SELECT * FROM " . _DB_PREFIX_ . "orders WHERE id_customer = " . $id_customer . " AND reference LIKE '%" . $search . "%'";
        return Db::getInstance()->executeS($sql);
    }

    public function getCategoryProducts()
    {
        // Missing id_shop check on multi-shop table (Multi-store data leak)
        return Db::getInstance()->executeS("SELECT * FROM " . _DB_PREFIX_ . "product_shop ps JOIN " . _DB_PREFIX_ . "category_shop cs ON ps.id_category_default = cs.id_category");
    }
}
