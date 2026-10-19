<?php
declare(strict_types=1);

require_once __DIR__ . '/PsHookGuard.php';

use Genisoft\PrestashopHarness\Tools\PsHookGuard;

if (php_sapi_name() === 'cli') {
    if ($argc < 2 || in_array($argv[1], ['-h', '--help'])) {
        echo "Usage: php ps-hook-guard.php <path-to-file-or-dir>\n";
        exit(0);
    }

    $target = $argv[1];
    $catalog = dirname(__DIR__) . '/data/ps-hooks-reference.json';

    try {
        $guard = new PsHookGuard($catalog);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Init error: ' . $e->getMessage() . PHP_EOL);
        exit(2);
    }

    $files = [];
    if (is_dir($target)) {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target));
        foreach ($iterator as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }
    } elseif (is_file($target)) {
        $files[] = $target;
    } else {
        fwrite(STDERR, "Error: Target path '{$target}' does not exist.\n");
        exit(1);
    }

    $hasErrors = false;
    echo "=== PrestaShop Hook Guard Scanner ===\n";
    echo "Catalog: 669 official PrestaShop hooks loaded.\n";
    echo "Scanning " . count($files) . " file(s)...\n\n";

    foreach ($files as $file) {
        $issues = $guard->validateFile($file);
        if (!empty($issues)) {
            echo "File: {$file}\n";
            foreach ($issues as $issue) {
                echo "  [{$issue['severity']}] [{$issue['category']}] {$issue['message']}\n";
                if ($issue['severity'] === 'ERROR' || $issue['severity'] === 'CRITICAL') {
                    $hasErrors = true;
                }
            }
            echo "\n";
        }
    }

    if ($hasErrors) {
        echo "FAILED: Hook safety violations detected.\n";
        exit(1);
    }

    echo "PASSED: All registered hooks comply with official PrestaShop specifications.\n";
    exit(0);
}
