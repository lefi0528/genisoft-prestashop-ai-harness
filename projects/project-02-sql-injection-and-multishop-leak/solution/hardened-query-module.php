<?php
declare(strict_types=1);

class HardenedRepository
{
    private Context $context;

    public function __construct(?Context $context = null)
    {
        $this->context = $context ?? Context::getContext();
    }

    public function findCustomerOrders(int $idCustomer, string $search): array
    {
        // Every value is cast or escaped where it is concatenated, so a reviewer (or the SQL guard)
        // can check each one locally. addcslashes() keeps % and _ typed by the user literal in LIKE.
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'orders`
                WHERE `id_customer` = ' . (int) $idCustomer . '
                  AND `id_shop` = ' . (int) $this->context->shop->id . '
                  AND `reference` LIKE \'%' . addcslashes(pSQL($search), '%_') . '%\'';

        $results = Db::getInstance()->executeS($sql);
        return is_array($results) ? $results : [];
    }

    public function getCategoryProducts(int $idCategory): array
    {
        $sql = 'SELECT ps.`id_product`, ps.`price`
                FROM `' . _DB_PREFIX_ . 'product_shop` ps
                INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs
                    ON (ps.`id_category_default` = cs.`id_category` AND cs.`id_shop` = ' . (int) $this->context->shop->id . ')
                WHERE ps.`id_shop` = ' . (int) $this->context->shop->id . '
                  AND cs.`id_category` = ' . (int) $idCategory;

        $results = Db::getInstance()->executeS($sql);
        return is_array($results) ? $results : [];
    }
}
