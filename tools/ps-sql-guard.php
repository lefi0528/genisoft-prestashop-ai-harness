<?php
declare(strict_types=1);

require_once __DIR__ . '/GuardRunner.php';
require_once __DIR__ . '/PsSqlGuard.php';

use Genisoft\PrestashopHarness\Tools\GuardRunner;
use Genisoft\PrestashopHarness\Tools\PsSqlGuard;

if (php_sapi_name() === 'cli') {
    exit(GuardRunner::runCli(
        'PrestaShop SQL Injection & AppSec Guard',
        'ps-sql-guard.php',
        $argv,
        static function (): PsSqlGuard {
            return new PsSqlGuard();
        }
    ));
}
