<?php
/**
 * Fixture for tests/fixtures.sh: the same queries as flagged.php, each request value cast or
 * escaped where it is concatenated. ps-sql-guard.php must report no blocking finding.
 */
class RequestConcatSanitized
{
    public function findOrderByReference()
    {
        return Db::getInstance()->getRow('SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` WHERE `reference` = \'' . pSQL(Tools::getValue('reference')) . '\'');
    }

    public function findProductsInCategory()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_lang` WHERE `id_lang` = ' . (int) Context::getContext()->language->id . ' AND `name` LIKE \'%' . Db::getInstance()->escape(Tools::getValue('search')) . '%\' AND `id_shop` = ' . (int) Tools::getValue('id_shop'));
    }

    public function findCustomerByEmail()
    {
        $sql = 'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` WHERE `email` = \'' . pSQL(trim(Tools::getAllValues()['email'])) . '\'';

        return Db::getInstance()->getValue($sql);
    }

    public function findLastViewedProduct()
    {
        $query = 'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . (int) $_COOKIE['last_id_product'] . ' AND `price` > ' . (float) Tools::getValue('min_price');

        return Db::getInstance()->getRow($query);
    }
}
