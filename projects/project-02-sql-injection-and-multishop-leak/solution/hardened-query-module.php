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
        $idShop = (int) $this->context->shop->id;
        $safeSearch = pSQL($search);

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'orders`
                WHERE `id_customer` = ' . (int) $idCustomer . '
                  AND `id_shop` = ' . $idShop . '
                  AND `reference` LIKE '%' . $safeSearch . '%'';

        $results = Db::getInstance()->executeS($sql);
        return is_array($results) ? $results : [];
    }

    public function getCategoryProducts(int $idCategory): array
    {
        $idShop = (int) $this->context->shop->id;

        $sql = 'SELECT ps.`id_product`, ps.`price`
                FROM `' . _DB_PREFIX_ . 'product_shop` ps
                INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs
                    ON (ps.`id_category_default` = cs.`id_category` AND cs.`id_shop` = ' . $idShop . ')
                WHERE ps.`id_shop` = ' . $idShop . '
                  AND cs.`id_category` = ' . (int) $idCategory;

        $results = Db::getInstance()->executeS($sql);
        return is_array($results) ? $results : [];
    }
}
