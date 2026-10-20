<?php
declare(strict_types=1);

namespace Genisoft\PrestashopHarness\Tools;

/**
 * Shared file collection, reporting and exit codes for the guard CLIs.
 *
 * Exit codes: 0 = no ERROR/CRITICAL finding, 1 = at least one ERROR/CRITICAL finding,
 * 2 = the tool could not do its job (bad arguments, missing path, no file to scan,
 * missing or corrupt hook catalog). WARNING and INFO findings are printed but do not fail.
 */
final class GuardRunner
{
    public const EXIT_CLEAN = 0;
    public const EXIT_FINDINGS = 1;
    public const EXIT_TOOL_ERROR = 2;

    /** Third-party or generated folders that are not the module's own code. */
    public const SKIPPED_DIRS = ['vendor', 'node_modules', '.git'];

    /**
     * @param string[] $extensions lower-case extensions without the dot
     * @return string[] sorted file paths
     * @throws \InvalidArgumentException when the target does not exist
     */
    public static function collectFiles(string $target, array $extensions): array
    {
        if (is_file($target)) {
            return [$target];
        }
        if (!is_dir($target)) {
            throw new \InvalidArgumentException("Target path '{$target}' does not exist.");
        }

        $directory = new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator(
            $directory,
            static function (\SplFileInfo $file): bool {
                return !($file->isDir() && in_array($file->getFilename(), self::SKIPPED_DIRS, true));
            }
        );

        $files = [];
        foreach (new \RecursiveIteratorIterator($filter) as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public static function isBlocking(array $issue): bool
    {
        return in_array($issue['severity'], ['ERROR', 'CRITICAL'], true);
    }

    /**
     * @param string[] $argv
     * @param callable(): object $makeGuard returns an object exposing validateFile(string): array
     * @param callable(object): string|null $describeGuard optional extra header line
     */
    public static function runCli(string $title, string $script, array $argv, callable $makeGuard, ?callable $describeGuard = null): int
    {
        $usage = "Usage: php tools/{$script} <path-to-file-or-dir>\n"
            . "Scans .php files recursively (skipping " . implode('/, ', self::SKIPPED_DIRS) . "/).\n"
            . "Exit codes: 0 no blocking finding, 1 ERROR/CRITICAL finding(s), 2 tool error.\n";

        if (isset($argv[1]) && in_array($argv[1], ['-h', '--help'], true)) {
            echo $usage;
            return self::EXIT_CLEAN;
        }
        if (count($argv) !== 2) {
            fwrite(STDERR, $usage);
            return self::EXIT_TOOL_ERROR;
        }

        $target = $argv[1];
        try {
            $guard = $makeGuard();
            $files = self::collectFiles($target, ['php']);
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
            return self::EXIT_TOOL_ERROR;
        }

        echo "=== {$title} ===\n";
        if ($describeGuard !== null) {
            echo $describeGuard($guard) . "\n";
        }
        if ($files === []) {
            fwrite(STDERR, "Error: no .php file found under '{$target}'. Nothing was scanned.\n");
            return self::EXIT_TOOL_ERROR;
        }
        echo 'Scanning ' . count($files) . " file(s)...\n\n";
        if (method_exists($guard, 'prepare')) {
            // The hook guard reads the whole target first: a custom hook can be executed in another file or a template.
            $guard->prepare(self::collectFiles($target, ['php', 'tpl', 'twig']));
        }

        $counts = ['blocking' => 0, 'other' => 0];
        foreach ($files as $file) {
            $issues = $guard->validateFile($file);
            if ($issues === []) {
                continue;
            }
            echo "File: {$file}\n";
            foreach ($issues as $issue) {
                $line = isset($issue['line']) ? "line {$issue['line']}: " : '';
                echo "  {$line}[{$issue['severity']}] [{$issue['category']}] {$issue['message']}\n";
                $counts[self::isBlocking($issue) ? 'blocking' : 'other']++;
            }
            echo "\n";
        }

        $summary = "{$counts['blocking']} blocking (ERROR/CRITICAL), {$counts['other']} non-blocking (WARNING/INFO) finding(s) in " . count($files) . ' file(s).';
        if ($counts['blocking'] > 0) {
            echo "FAILED: {$summary}\n";
            return self::EXIT_FINDINGS;
        }
        echo "PASSED: {$summary} Pattern-based checks only; this is not a proof of absence of vulnerabilities.\n";
        return self::EXIT_CLEAN;
    }
}
