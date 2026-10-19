<?php
declare(strict_types=1);

require_once __DIR__ . '/PsContextGuard.php';

use Genisoft\PrestashopHarness\Tools\PsContextGuard;

if (php_sapi_name() === 'cli') {
    if ($argc < 2 || in_array($argv[1], ['-h', '--help'])) {
        echo "Usage: php ps-context-guard.php <path-to-file-or-dir>\n";
        exit(0);
    }

    $target = $argv[1];
    $guard = new PsContextGuard();
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
    echo "=== PrestaShop Multi-Shop & Context Isolation Guard ===\n";
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
        echo "FAILED: Context isolation violations detected.\n";
        exit(1);
    }

    echo "PASSED: Context isolation and multi-shop checks verified.\n";
    exit(0);
}
