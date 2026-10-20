<?php
/**
 * Fixture for tests/fixtures.sh: four request values reach SQL without escaping.
 * ps-sql-guard.php must report one "Request value" SQL_UNESCAPED_CONCAT finding for each.
 */
class RequestConcatFlagged
{
    public function findOrderByReference()
    {
        return Db::getInstance()->getRow('SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` WHERE `reference` = \'' . Tools::getValue('reference') . '\'');
    }

    public function findProductsInCategory()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_lang` WHERE `id_lang` = ' . (int) Context::getContext()->language->id . ' AND `name` LIKE \'%' . Tools::getValue('search') . '%\'');
    }

    public function findCustomerByEmail()
    {
        $sql = 'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` WHERE `email` = \'' . trim(Tools::getAllValues()['email']) . '\'';

        return Db::getInstance()->getValue($sql);
    }

    public function findLastViewedProduct()
    {
        $query = 'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `reference` = \'' . $_COOKIE['last_reference'] . '\'';

        return Db::getInstance()->getRow($query);
    }
}
