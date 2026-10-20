<?php
declare(strict_types=1);

/**
 * Runs the Project 02 solution against minimal PrestaShop stubs and checks the SQL it builds.
 * `php -l` cannot catch runtime errors such as a TypeError from `'...' % '...'`; this does.
 */

const _DB_PREFIX_ = 'ps_';

function pSQL(string $string): string
{
    return addslashes($string);
}

class Shop
{
    public $id = 3;
}

class Context
{
    public $shop;

    public static function getContext(): self
    {
        $context = new self();
        $context->shop = new Shop();
        return $context;
    }
}

class Db
{
    public static array $queries = [];

    public static function getInstance(): self
    {
        return new self();
    }

    public function executeS(string $sql): array
    {
        self::$queries[] = preg_replace('/\s+/', ' ', $sql);
        return [];
    }
}

require dirname(__DIR__) . '/projects/project-02-sql-injection-and-multishop-leak/solution/hardened-query-module.php';

$repository = new HardenedRepository();
$repository->findCustomerOrders(42, "x' OR '1'='1 50%_off");
$repository->getCategoryProducts(7);

$expected = [
    "SELECT * FROM `ps_orders` WHERE `id_customer` = 42 AND `id_shop` = 3 AND `reference` LIKE '%x\' OR \'1\'=\'1 50\%\_off%'",
    'SELECT ps.`id_product`, ps.`price` FROM `ps_product_shop` ps INNER JOIN `ps_category_shop` cs ON (ps.`id_category_default` = cs.`id_category` AND cs.`id_shop` = 3) WHERE ps.`id_shop` = 3 AND cs.`id_category` = 7',
];

if (Db::$queries !== $expected) {
    fwrite(STDERR, "Unexpected SQL:\n" . var_export(Db::$queries, true) . "\n");
    exit(1);
}
echo "Project 02 solution: both queries run and are escaped as expected.\n";
