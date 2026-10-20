<?php
declare(strict_types=1);

/**
 * Genisoft PrestaShop Module Auditor
 *
 * Usage: php tools/harness-auditor.php <module-dir>
 *
 *   1. Preflight: the hook catalog, Node.js and the Smarty guard must be usable.
 *   2. Guard self-test: each guard must still flag (or accept) a set of known snippets.
 *   3. Module scan: the 4 guards, `php -l` and packaging checks run on <module-dir>.
 *
 * Exit codes: 0 = no ERROR/CRITICAL finding, 1 = ERROR/CRITICAL finding(s),
 * 2 = tool error (bad arguments, missing path, missing Node.js, failed self-test, nothing scanned).
 * The checks are pattern-based: a clean result is not a security certification.
 *
 * @author Genisoft - Lefi Abdelmonem <contact@genisoft.fr>
 * @license MIT
 */

require_once __DIR__ . '/GuardRunner.php';
require_once __DIR__ . '/PsHookGuard.php';
require_once __DIR__ . '/PsSqlGuard.php';
require_once __DIR__ . '/PsContextGuard.php';

use Genisoft\PrestashopHarness\Tools\GuardRunner;
use Genisoft\PrestashopHarness\Tools\PsContextGuard;
use Genisoft\PrestashopHarness\Tools\PsHookGuard;
use Genisoft\PrestashopHarness\Tools\PsSqlGuard;

final class HarnessToolError extends \RuntimeException
{
}

final class HarnessAuditor
{
    private string $baseDir;
    private string $smartyGuard;
    private PsHookGuard $hookGuard;
    private PsSqlGuard $sqlGuard;
    private PsContextGuard $contextGuard;

    /** @var array<string, list<array{severity: string, category: string, message: string, line?: int}>> */
    private array $findings = [];

    public function __construct(string $baseDir)
    {
        $this->baseDir = $baseDir;
        $this->smartyGuard = $baseDir . '/tools/ps-smarty-guard.js';
    }

    /**
     * @param string[] $argv
     */
    public function run(array $argv): int
    {
        $usage = "Usage: php tools/harness-auditor.php <module-dir>\n"
            . "Exit codes: 0 no blocking finding, 1 ERROR/CRITICAL finding(s), 2 tool error.\n";
        if (isset($argv[1]) && in_array($argv[1], ['-h', '--help'], true)) {
            echo $usage;
            return GuardRunner::EXIT_CLEAN;
        }
        if (count($argv) !== 2) {
            fwrite(STDERR, $usage);
            return GuardRunner::EXIT_TOOL_ERROR;
        }

        try {
            $target = $this->preflight($argv[1]);
            $this->selfTest();
            $scanned = $this->scanModule($target);
        } catch (HarnessToolError $e) {
            fwrite(STDERR, "\nTOOL ERROR: " . $e->getMessage() . "\nNo verdict: the module was not (fully) audited.\n");
            return GuardRunner::EXIT_TOOL_ERROR;
        }

        return $this->report($target, $scanned);
    }

    private function preflight(string $targetArg): string
    {
        echo "==========================================================\n";
        echo "  GENISOFT PRESTASHOP MODULE AUDIT (pattern-based checks)\n";
        echo "==========================================================\n\n";
        echo "--> Preflight\n";

        $target = realpath($targetArg);
        if ($target === false || !is_dir($target)) {
            throw new HarnessToolError("'{$targetArg}' is not a module directory.");
        }
        echo "  Target: {$target}\n";

        try {
            $this->hookGuard = new PsHookGuard($this->baseDir . '/data/ps-hooks-reference.json');
        } catch (\RuntimeException $e) {
            throw new HarnessToolError($e->getMessage());
        }
        $this->sqlGuard = new PsSqlGuard();
        $this->contextGuard = new PsContextGuard();
        echo '  Hook catalog: ' . $this->hookGuard->getHookCount() . " hook names\n";

        [$code, $out] = $this->runProcess(['node', '--version']);
        if ($code !== 0) {
            throw new HarnessToolError('Node.js is required to run the Smarty guard (`node --version` failed).');
        }
        echo '  Node.js: ' . trim($out) . "\n";
        if (!is_file($this->smartyGuard)) {
            throw new HarnessToolError("Smarty guard not found: {$this->smartyGuard}");
        }
        echo '  PHP: ' . PHP_VERSION . " (used for php -l)\n";

        return $target;
    }

    /**
     * Known snippets each guard must flag (true) or accept (false). If one guard drifts,
     * its findings on the module cannot be trusted, so a failed self-test is a tool error.
     */
    private function selfTest(): void
    {
        echo "\n--> Guard self-test\n";
        $failures = [];
        $total = 0;

        $catalogHooks = ['displayHeader', 'displayTop', 'actionCartSave', 'actionValidateOrder', 'actionProductSave',
            'actionProductAdd', 'actionDispatcherBefore', 'displayBackOfficeHeader', 'displayPaymentReturn'];
        foreach ($catalogHooks as $hook) {
            $total++;
            if ($this->probe($this->hookGuard, "<?php\n\$this->registerHook('{$hook}');", 'HALLUCINATED_HOOK')) {
                $failures[] = "hook catalog: core hook {$hook} must be accepted";
            }
        }

        $hookProbes = [
            ["add_action('wp_head', 'foo');", 'WP_HALLUCINATION', true],
            ["add_filter('the_content', 'foo');", 'WP_HALLUCINATION', true],
            ["registerHook('wp_head');", 'WP_HALLUCINATION', true],
            ["registerHook('wp_footer');", 'WP_HALLUCINATION', true],
            ["init();", 'WP_HALLUCINATION', true],
            ["admin_init();", 'WP_HALLUCINATION', true],
            ["woocommerce_before_calculate_totals();", 'WP_HALLUCINATION', true],
            ["woocommerce_cart_calculate_fees();", 'WP_HALLUCINATION', true],
            ["\$this->registerHook('actionProductCreate');", 'HALLUCINATED_HOOK', true],
            ["\$this->registerHook('actionCartUpdate');", 'HALLUCINATED_HOOK', true],
            ["public function hookDisplayHeader(\$params) {}", 'HOOK_CASING', false],
            ["public function hookActionCartSave(\$params) {}", 'HOOK_CASING', false],
            ["public function hookDisplayTop(\$params) {}", 'HOOK_CASING', false],
            ["\$this->registerHook(['displayHeader', 'displayTop']);", 'HALLUCINATED_HOOK', false],
            ["\$hooks = ['fakeHookOne']; foreach (\$hooks as \$h) { \$this->registerHook(\$h); }", 'HALLUCINATED_HOOK', true],
        ];
        $sqlProbes = [
            ['"SELECT * FROM orders WHERE id = $id"', 'SQL_DIRECT_INTERPOLATION', true],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE ref = ' . \$ref);", 'SQL_UNESCAPED_CONCAT', true],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE id = ' . \$id);", 'SQL_UNESCAPED_CONCAT', true],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE id = ' . (int)\$id);", 'SQL_UNESCAPED_CONCAT', false],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE p = ' . (float)\$price);", 'SQL_UNESCAPED_CONCAT', false],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE r = \'' . pSQL(\$ref) . '\'');", 'SQL_UNESCAPED_CONCAT', false],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE c = \'' . pSQL(\$comment, true) . '\'');", 'SQL_UNESCAPED_CONCAT', false],
            ["FROM orders WHERE 1", 'SQL_MISSING_DB_PREFIX', true],
            ["JOIN customer c ON 1", 'SQL_MISSING_DB_PREFIX', true],
            ["UPDATE product SET active=1", 'SQL_MISSING_DB_PREFIX', true],
            ["INSERT INTO cart VALUES(1)", 'SQL_MISSING_DB_PREFIX', true],
            ["FROM `_DB_PREFIX_orders`", 'SQL_MISSING_DB_PREFIX', false],
            ["\$sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'c WHERE id = ' . \$id; Db::getInstance()->executeS(\$sql);", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql .= ' AND name = ' . \$search; Db::getInstance()->executeS(\$sql);", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql = 'SELECT * FROM c WHERE id = ' . \$_GET['id'];", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql = 'SELECT * FROM c WHERE id = ' . \$_POST['id'];", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql = 'SELECT * FROM c WHERE id = ' . \$_REQUEST['id'];", 'SQL_UNESCAPED_CONCAT', true],
            ["Db::getInstance()->getRow('SELECT * FROM o WHERE s = ' . (int) Configuration::get('S') . ' AND r = ' . \$ref);", 'SQL_UNESCAPED_CONCAT', true],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE ref = \'' . Tools::getValue('ref') . '\'');", 'SQL_UNESCAPED_CONCAT', true],
            ["Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT * FROM o WHERE s = ' . (int) Configuration::get('S') . ' AND r = \'' . \\Tools::getValue('r') . '\'');", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql = 'SELECT * FROM c WHERE r = \'' . Tools::getAllValues()['r'] . '\'';", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql = 'SELECT * FROM c WHERE r = \'' . \$_COOKIE['r'] . '\'';", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql = 'SELECT * FROM c WHERE r = \'' . trim(Tools::getValue('r')) . '\'';", 'SQL_UNESCAPED_CONCAT', true],
            ["\$sql = 'SELECT * FROM c WHERE id = ' . (int) Tools::getValue('id');", 'SQL_UNESCAPED_CONCAT', false],
            ["\$sql = 'SELECT * FROM c WHERE p = ' . (float) Tools::getValue('p');", 'SQL_UNESCAPED_CONCAT', false],
            ["\$sql = 'SELECT * FROM c WHERE id = ' . intval(Tools::getValue('id'));", 'SQL_UNESCAPED_CONCAT', false],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE r = \'' . pSQL(Tools::getValue('r')) . '\'');", 'SQL_UNESCAPED_CONCAT', false],
            ["Db::getInstance()->executeS('SELECT * FROM o WHERE r = \'' . Db::getInstance()->escape(Tools::getValue('r')) . '\'');", 'SQL_UNESCAPED_CONCAT', false],
            ["\$sql = 'SELECT * FROM c WHERE r = \'' . pSQL(trim(Tools::getValue('r'))) . '\'';", 'SQL_UNESCAPED_CONCAT', false],
            ["\$sql = 'SELECT * FROM c WHERE id = ' . (int) \$_COOKIE['id'];", 'SQL_UNESCAPED_CONCAT', false],
        ];
        $contextProbes = [
            ['JOIN _DB_PREFIX_product_shop ps ON ps.id_product=p.id_product', 'MULTISHOP_MISSING_FILTER', true],
            ['JOIN _DB_PREFIX_category_shop cs ON cs.id_cat=c.id_cat', 'MULTISHOP_MISSING_FILTER', true],
            ['JOIN `\' . _DB_PREFIX_ . \'product_shop` ps ON 1', 'MULTISHOP_MISSING_FILTER', true],
            ['JOIN `\' . _DB_PREFIX_ . \'product_shop` ps ON ps.id_shop = (int)$this->context->shop->id', 'MULTISHOP_MISSING_FILTER', false],
            ['JOIN `\' . _DB_PREFIX_ . \'product_shop` ps ON ps.`id_shop` = (int)$this->context->shop->id', 'MULTISHOP_MISSING_FILTER', false],
            ['foreach ($list as $item) { $ctx = Context::getContext(); }', 'PERF_CONTEXT_IN_LOOP', true],
            ['$ctx = Context::getContext(); foreach ($list as $item) { $x = $ctx->shop->id; }', 'PERF_CONTEXT_IN_LOOP', false],
            ['Configuration::updateValue(self::CONFIG_AMOUNT, 50.0);', 'MULTISHOP_CONF_UPDATE', true],
        ];
        $guardProbes = [
            'hook' => [$this->hookGuard, $hookProbes],
            'sql' => [$this->sqlGuard, $sqlProbes],
            'context' => [$this->contextGuard, $contextProbes],
        ];
        foreach ($guardProbes as $name => [$guard, $probes]) {
            foreach ($probes as [$code, $category, $expected]) {
                $total++;
                if ($this->probe($guard, "<?php\n" . $code, $category) !== $expected) {
                    $failures[] = "{$name} guard: expected " . ($expected ? '' : 'no ') . "{$category} for `{$code}`";
                }
            }
        }

        $smartyProbes = [
            ['<div>{$user_input}</div>', 'SMARTY_UNESCAPED_OUTPUT', true],
            ['<div>{$user_input|escape:\'html\':\'UTF-8\'}</div>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<div>{$user_input|escape:\'htmlall\':\'UTF-8\'}</div>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<script>var id = \'{$user_input|escape:\'javascript\':\'UTF-8\'}\';</script>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<span>{$count|intval}</span>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<span>{$price|floatval}</span>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<div>{$html|cleanHtml}</div>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<span>{$smarty.now|date_format:\'%Y\'}</span>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<span>{$price|string_format:\'%.2f\'}</span>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<span>{$items|count}</span>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<span>{$data|json_encode}</span>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<span>{$amount|number_format:2}</span>', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['<div>{$unsafe_data nofilter}</div>', 'SMARTY_NOFILTER_BYPASS', true],
            ['{php} echo date("Y"); {/php}', 'SMARTY_PHP_TAG', true],
            ['{/php}', 'SMARTY_PHP_TAG', true],
            ['{l s=\'Hello\' d=\'Modules.Genisoft.Shop\'}', 'SMARTY_LEGACY_TRANSLATION', false],
            ['{l s=\'Hello Legacy\'}', 'SMARTY_LEGACY_TRANSLATION', true],
            ['{$my_var = 123}', 'SMARTY_UNESCAPED_OUTPUT', false],
            ['{$counter++}', 'SMARTY_UNESCAPED_OUTPUT', false],
        ];
        $categoriesByProbe = $this->smartyProbeCategories(array_column($smartyProbes, 0));
        foreach ($smartyProbes as $i => [$tpl, $category, $expected]) {
            $total++;
            if (in_array($category, $categoriesByProbe[$i], true) !== $expected) {
                $failures[] = 'smarty guard: expected ' . ($expected ? '' : 'no ') . "{$category} for `{$tpl}`";
            }
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                echo "  FAIL: {$failure}\n";
            }
            throw new HarnessToolError(count($failures) . "/{$total} guard self-test probe(s) failed; the guards do not behave as documented.");
        }
        echo "  {$total}/{$total} probes behave as expected.\n";
    }

    private function probe(object $guard, string $code, string $category): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ps_probe_');
        if ($tmp === false) {
            throw new HarnessToolError('Cannot create a temporary file for the guard self-test.');
        }
        file_put_contents($tmp, $code);
        try {
            $issues = $guard->validateFile($tmp);
        } finally {
            unlink($tmp);
        }

        return in_array($category, array_column($issues, 'category'), true);
    }

    /**
     * Runs the Smarty guard once on a folder holding one template per probe.
     *
     * @param string[] $templates
     * @return list<string[]> categories reported for each template, in input order
     */
    private function smartyProbeCategories(array $templates): array
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ps_smarty_probe_' . bin2hex(random_bytes(6));
        if (!mkdir($dir)) {
            throw new HarnessToolError('Cannot create a temporary folder for the Smarty self-test.');
        }
        $paths = [];
        foreach ($templates as $i => $tpl) {
            $paths[$i] = $dir . DIRECTORY_SEPARATOR . sprintf('probe_%02d.tpl', $i);
            file_put_contents($paths[$i], $tpl);
        }
        try {
            $result = $this->runSmartyGuard($dir, count($templates));
        } finally {
            array_map('unlink', $paths);
            rmdir($dir);
        }

        $categories = array_fill(0, count($templates), []);
        $indexByName = array_flip(array_map('basename', $paths));
        foreach ($result['issues'] as $issue) {
            $i = $indexByName[basename((string) $issue['file'])] ?? null;
            if ($i === null) {
                throw new HarnessToolError("Smarty guard reported an unexpected file: {$issue['file']}");
            }
            $categories[$i][] = $issue['category'];
        }

        return $categories;
    }

    /**
     * @return array{files_scanned: int, issues: list<array<string, mixed>>}
     */
    private function runSmartyGuard(string $path, int $expectedFiles): array
    {
        [$code, $out, $err] = $this->runProcess(['node', $this->smartyGuard, '--json', $path]);
        if ($code !== GuardRunner::EXIT_CLEAN && $code !== GuardRunner::EXIT_FINDINGS) {
            throw new HarnessToolError("Smarty guard failed (exit {$code}): " . trim($err));
        }
        $result = json_decode($out, true);
        if (!is_array($result) || !isset($result['files_scanned'], $result['issues']) || !is_array($result['issues'])) {
            throw new HarnessToolError('Smarty guard returned unreadable output.');
        }
        if ($result['files_scanned'] !== $expectedFiles) {
            throw new HarnessToolError("Smarty guard scanned {$result['files_scanned']} template(s), expected {$expectedFiles}.");
        }

        return $result;
    }

    /**
     * @return array{php: int, tpl: int}
     */
    private function scanModule(string $target): array
    {
        echo "\n--> Module scan\n";
        $phpFiles = GuardRunner::collectFiles($target, ['php']);
        $tplFiles = GuardRunner::collectFiles($target, ['tpl']);
        if ($phpFiles === [] && $tplFiles === []) {
            throw new HarnessToolError("No .php or .tpl file found under {$target}. Nothing was scanned.");
        }
        echo '  ' . count($phpFiles) . ' PHP file(s), ' . count($tplFiles) . ' Smarty template(s) (skipping '
            . implode('/, ', GuardRunner::SKIPPED_DIRS) . "/)\n";

        foreach ($phpFiles as $file) {
            [$code, $out, $err] = $this->runProcess([PHP_BINARY, '-n', '-l', $file]);
            if ($code !== 0) {
                $detail = trim($err !== '' ? $err : $out);
                $this->addFinding($target, $file, ['severity' => 'ERROR', 'category' => 'PHP_SYNTAX', 'message' => 'php -l failed: ' . strtok($detail, "\n")]);
                continue;
            }
            foreach ([$this->hookGuard, $this->sqlGuard, $this->contextGuard] as $guard) {
                foreach ($guard->validateFile($file) as $issue) {
                    $this->addFinding($target, $file, $issue);
                }
            }
        }

        if ($tplFiles !== []) {
            foreach ($this->runSmartyGuard($target, count($tplFiles))['issues'] as $issue) {
                $file = (string) $issue['file'];
                unset($issue['file']);
                $this->addFinding($target, $file, $issue);
            }
        }

        $this->checkPackaging($target);

        return ['php' => count($phpFiles), 'tpl' => count($tplFiles)];
    }

    private function checkPackaging(string $target): void
    {
        $name = basename($target);
        if (!is_file($target . DIRECTORY_SEPARATOR . $name . '.php')) {
            $this->addFinding($target, $target, ['severity' => 'ERROR', 'category' => 'PACKAGING_MAIN_FILE',
                'message' => "Main module file {$name}.php is missing (PrestaShop loads <module>/<module>.php)."]);
        }
        if (!is_file($target . DIRECTORY_SEPARATOR . 'logo.png')) {
            $this->addFinding($target, $target, ['severity' => 'WARNING', 'category' => 'PACKAGING_LOGO',
                'message' => 'logo.png is missing (shown in the back-office module list).']);
        }

        $directories = [$target];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $file): bool {
                    return $file->isDir() && !in_array($file->getFilename(), GuardRunner::SKIPPED_DIRS, true);
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $dir) {
            $directories[] = $dir->getPathname();
        }
        foreach ($directories as $dir) {
            if (!is_file($dir . DIRECTORY_SEPARATOR . 'index.php')) {
                $this->addFinding($target, $dir, ['severity' => 'WARNING', 'category' => 'PACKAGING_INDEX_PHP',
                    'message' => 'No index.php in this folder (PrestaShop Addons validator requirement).']);
            }
        }
    }

    private function addFinding(string $target, string $path, array $issue): void
    {
        $relative = ltrim(substr($path, strlen($target)), '\\/');
        $this->findings[$relative === '' ? '.' : str_replace('\\', '/', $relative)][] = $issue;
    }

    /**
     * @param array{php: int, tpl: int} $scanned
     */
    private function report(string $target, array $scanned): int
    {
        $blocking = 0;
        $other = 0;
        ksort($this->findings);
        if ($this->findings !== []) {
            echo "\n--> Findings\n";
        }
        foreach ($this->findings as $file => $issues) {
            echo "  {$file}\n";
            foreach ($issues as $issue) {
                $line = isset($issue['line']) ? "line {$issue['line']}: " : '';
                echo "    {$line}[{$issue['severity']}] [{$issue['category']}] {$issue['message']}\n";
                GuardRunner::isBlocking($issue) ? $blocking++ : $other++;
            }
        }

        echo "\n==========================================================\n";
        echo "  Scanned: {$scanned['php']} PHP file(s), {$scanned['tpl']} template(s)\n";
        echo "  Findings: {$blocking} blocking (ERROR/CRITICAL), {$other} non-blocking (WARNING/INFO)\n";
        if ($blocking > 0) {
            echo "  RESULT: FAIL\n";
            echo "==========================================================\n";
            return GuardRunner::EXIT_FINDINGS;
        }
        echo "  RESULT: PASS - no blocking finding from these pattern-based checks.\n";
        echo "  This is not a security certification: also install and test the module\n";
        echo "  on every PrestaShop version it declares in ps_versions_compliancy.\n";
        echo "==========================================================\n";
        return GuardRunner::EXIT_CLEAN;
    }

    /**
     * Runs a command without a shell; output goes through temp files to avoid pipe deadlocks.
     *
     * @param string[] $command
     * @return array{0: int, 1: string, 2: string} exit code (-1 if it could not start), stdout, stderr
     */
    private function runProcess(array $command): array
    {
        $outFile = tempnam(sys_get_temp_dir(), 'ps_out_');
        $errFile = tempnam(sys_get_temp_dir(), 'ps_err_');
        if ($outFile === false || $errFile === false) {
            throw new HarnessToolError('Cannot create temporary files to capture process output.');
        }
        $process = @proc_open($command, [1 => ['file', $outFile, 'w'], 2 => ['file', $errFile, 'w']], $pipes);
        $code = is_resource($process) ? proc_close($process) : -1;
        $out = (string) file_get_contents($outFile);
        $err = (string) file_get_contents($errFile);
        unlink($outFile);
        unlink($errFile);

        return [$code, $out, $err];
    }
}

if (php_sapi_name() === 'cli') {
    exit((new HarnessAuditor(dirname(__DIR__)))->run($argv));
}
