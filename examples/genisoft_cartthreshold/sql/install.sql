CREATE TABLE IF NOT EXISTS `_DB_PREFIX_genisoft_threshold_log` (
    `id_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_cart` INT(11) UNSIGNED NOT NULL,
    `id_customer` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `cart_total` DECIMAL(20, 6) NOT NULL DEFAULT '0.000000',
    `threshold_reached` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    UNIQUE KEY `uniq_cart_shop` (`id_cart`, `id_shop`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=utf8mb4;
