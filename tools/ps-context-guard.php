<?php
declare(strict_types=1);

require_once __DIR__ . '/GuardRunner.php';
require_once __DIR__ . '/PsContextGuard.php';

use Genisoft\PrestashopHarness\Tools\GuardRunner;
use Genisoft\PrestashopHarness\Tools\PsContextGuard;

if (php_sapi_name() === 'cli') {
    exit(GuardRunner::runCli(
        'PrestaShop Multi-Shop & Context Isolation Guard',
        'ps-context-guard.php',
        $argv,
        static function (): PsContextGuard {
            return new PsContextGuard();
        }
    ));
}
