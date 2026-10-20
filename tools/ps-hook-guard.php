<?php
declare(strict_types=1);

require_once __DIR__ . '/GuardRunner.php';
require_once __DIR__ . '/PsHookGuard.php';

use Genisoft\PrestashopHarness\Tools\GuardRunner;
use Genisoft\PrestashopHarness\Tools\PsHookGuard;

if (php_sapi_name() === 'cli') {
    exit(GuardRunner::runCli(
        'PrestaShop Hook Guard Scanner',
        'ps-hook-guard.php',
        $argv,
        static function (): PsHookGuard {
            return new PsHookGuard(dirname(__DIR__) . '/data/ps-hooks-reference.json');
        },
        static function (PsHookGuard $guard): string {
            return 'Catalog: ' . $guard->getHookCount() . ' hook names loaded from data/ps-hooks-reference.json.';
        }
    ));
}
