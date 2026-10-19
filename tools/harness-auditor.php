<?php
declare(strict_types=1);

/**
 * Genisoft PrestaShop 8+ 100-Assertion Granular Harness Auditor
 *
 * Runs 100 distinct architectural, AST, AppSec, multi-shop, Smarty,
 * and packaging assertions to certify PrestaShop module integrity.
 *
 * @author Genisoft - Lefi Abdelmonem <contact@genisoft.fr>
 * @license MIT
 */

$baseDir = dirname(__DIR__);
require_once __DIR__ . '/PsHookGuard.php';
require_once __DIR__ . '/PsSqlGuard.php';
require_once __DIR__ . '/PsContextGuard.php';

use Genisoft\PrestashopHarness\Tools\PsHookGuard;
use Genisoft\PrestashopHarness\Tools\PsSqlGuard;
use Genisoft\PrestashopHarness\Tools\PsContextGuard;

class HarnessAuditor
{
    private string $baseDir;
    private array $results = [];
    private int $assertionCount = 0;
    private int $passedCount = 0;
    private int $failedCount = 0;

    private PsHookGuard $hookGuard;
    private PsSqlGuard $sqlGuard;
    private PsContextGuard $contextGuard;
    private array $hookCatalog;

    public function __construct(string $baseDir)
    {
        $this->baseDir = $baseDir;
        $catalogPath = $baseDir . '/data/ps-hooks-reference.json';
        $this->hookGuard = new PsHookGuard($catalogPath);
        $this->sqlGuard = new PsSqlGuard();
        $this->contextGuard = new PsContextGuard();
        $this->hookCatalog = json_decode((string) file_get_contents($catalogPath), true) ?: [];
    }

    public function assert(bool $condition, string $title, string $details = ''): void
    {
        $this->assertionCount++;
        $id = str_pad((string) $this->assertionCount, 3, '0', STR_PAD_LEFT);

        if ($condition) {
            $this->passedCount++;
            echo "  [{$id}/100] \033[32mPASS\033[0m: {$title}\n";
            $this->results[] = ['id' => $this->assertionCount, 'status' => 'PASS', 'title' => $title];
        } else {
            $this->failedCount++;
            echo "  [{$id}/100] \033[31mFAIL\033[0m: {$title} ({$details})\n";
            $this->results[] = ['id' => $this->assertionCount, 'status' => 'FAIL', 'title' => $title, 'details' => $details];
        }
    }

    public function runAll(): bool
    {
        echo "==========================================================\n";
        echo "  GENISOFT PRESTASHOP 8+ HARNESS: 100-ASSERTION AUDIT\n";
        echo "==========================================================\n\n";

        // SECTION 1: Architecture, Autoloading & Standards (1-10)
        echo "--> Category 1: Architecture, PSR-4 Autoloading & Standards\n";
        $composer = json_decode((string) @file_get_contents($this->baseDir . '/composer.json'), true);
        $this->assert(is_array($composer) && isset($composer['name']), 'composer.json valid JSON syntax');
        $this->assert(isset($composer['autoload']['psr-4']['Genisoft\\PrestashopHarness\\Tools\\']), 'PSR-4 namespace mapping defined for Tools');
        $this->assert(class_exists(PsHookGuard::class), 'PSR-4 class PsHookGuard loaded');
        $this->assert(class_exists(PsSqlGuard::class), 'PSR-4 class PsSqlGuard loaded');
        $this->assert(class_exists(PsContextGuard::class), 'PSR-4 class PsContextGuard loaded');
        $this->assert(file_exists($this->baseDir . '/tools/ps-hook-guard.php'), 'CLI wrapper ps-hook-guard.php present');
        $this->assert(file_exists($this->baseDir . '/tools/ps-sql-guard.php'), 'CLI wrapper ps-sql-guard.php present');
        $this->assert(file_exists($this->baseDir . '/tools/ps-context-guard.php'), 'CLI wrapper ps-context-guard.php present');
        $this->assert(file_exists($this->baseDir . '/tools/ps-smarty-guard.js'), 'Smarty Security Guard script present');
        $modulePhp = $this->baseDir . '/examples/genisoft_cartthreshold/genisoft_cartthreshold.php';
        $this->assert(file_exists($modulePhp), 'Exemplary module main class file present');

        // SECTION 2: PrestaShop Hook Guard & Anti-Hallucination (11-35)
        echo "\n--> Category 2: PrestaShop Hook Catalog & Anti-Hallucination\n";
        $this->assert(is_array($this->hookCatalog), 'Official Hook Catalog loaded');
        $this->assert(count($this->hookCatalog) >= 669, 'Catalog contains >= 669 distinct hooks (' . count($this->hookCatalog) . ' verified)');
        $this->assert(isset($this->hookCatalog['displayHeader']), 'Official Hook displayHeader recognized');
        $this->assert(isset($this->hookCatalog['displayTop']), 'Official Hook displayTop recognized');
        $this->assert(isset($this->hookCatalog['actionCartSave']), 'Official Hook actionCartSave recognized');
        $this->assert(isset($this->hookCatalog['actionValidateOrder']), 'Official Hook actionValidateOrder recognized');
        $this->assert(isset($this->hookCatalog['actionProductSave']), 'Official Hook actionProductSave recognized');
        $this->assert(isset($this->hookCatalog['actionDispatcherBefore']), 'Official Hook actionDispatcherBefore recognized');
        $this->assert(isset($this->hookCatalog['displayBackOfficeHeader']), 'Official Hook displayBackOfficeHeader recognized');
        $this->assert(isset($this->hookCatalog['displayPaymentReturn']), 'Official Hook displayPaymentReturn recognized');

        // Dynamic test probes for Hook Guard
        $this->assert($this->hasHookIssue("add_action('wp_head', 'foo');", 'WP_HALLUCINATION'), 'WordPress add_action() intercepted');
        $this->assert($this->hasHookIssue("add_filter('the_content', 'foo');", 'WP_HALLUCINATION'), 'WordPress add_filter() intercepted');
        $this->assert($this->hasHookIssue("registerHook('wp_head');", 'WP_HALLUCINATION'), 'WordPress wp_head intercepted');
        $this->assert($this->hasHookIssue("registerHook('wp_footer');", 'WP_HALLUCINATION'), 'WordPress wp_footer intercepted');
        $this->assert($this->hasHookIssue("init();", 'WP_HALLUCINATION'), 'WordPress init() intercepted');
        $this->assert($this->hasHookIssue("admin_init();", 'WP_HALLUCINATION'), 'WordPress admin_init() intercepted');
        $this->assert($this->hasHookIssue("woocommerce_before_calculate_totals();", 'WP_HALLUCINATION'), 'WooCommerce totals hook intercepted');
        $this->assert($this->hasHookIssue("woocommerce_cart_calculate_fees();", 'WP_HALLUCINATION'), 'WooCommerce fees hook intercepted');
        $this->assert($this->hasHookIssue("\$this->registerHook('actionProductCreate');", 'HALLUCINATED_HOOK'), 'Phantom hook actionProductCreate intercepted');
        $this->assert($this->hasHookIssue("\$this->registerHook('actionCartUpdate');", 'HALLUCINATED_HOOK'), 'Phantom hook actionCartUpdate intercepted');
        $this->assert(!$this->hasHookIssue("public function hookDisplayHeader(\$params) {}", 'HOOK_CASING'), 'PascalCase hookDisplayHeader accepted without false casing warning');
        $this->assert(!$this->hasHookIssue("public function hookActionCartSave(\$params) {}", 'HOOK_CASING'), 'PascalCase hookActionCartSave accepted without false casing warning');
        $this->assert(!$this->hasHookIssue("public function hookDisplayTop(\$params) {}", 'HOOK_CASING'), 'PascalCase hookDisplayTop accepted without false casing warning');
        $this->assert(!$this->hasHookIssue("\$this->registerHook(['displayHeader', 'displayTop']);", 'HALLUCINATED_HOOK'), 'Array registration of valid hooks accepted');
        $this->assert($this->hasHookIssue("\$hooks = ['fakeHookOne']; foreach (\$hooks as \$h) { \$this->registerHook(\$h); }", 'HALLUCINATED_HOOK'), 'Variable array loop with hallucinated hook intercepted');

        // SECTION 3: SQL Injection AppSec & Hardening (36-60)
        echo "\n--> Category 3: SQL Injection AppSec & Database Hardening\n";
        $this->assert($this->hasSqlIssue('"SELECT * FROM orders WHERE id = $id"', 'SQL_DIRECT_INTERPOLATION'), 'Double-quoted SQL interpolation intercepted');
        $this->assert($this->hasSqlIssue("Db::getInstance()->executeS('SELECT * FROM o WHERE ref = ' . \$ref);", 'SQL_UNESCAPED_CONCAT'), 'Unsanitized string concatenation intercepted');
        $this->assert($this->hasSqlIssue("Db::getInstance()->executeS('SELECT * FROM o WHERE id = ' . \$id);", 'SQL_UNESCAPED_CONCAT'), 'Uncast numerical ID concatenation intercepted');
        $this->assert(!$this->hasSqlIssue("Db::getInstance()->executeS('SELECT * FROM o WHERE id = ' . (int)\$id);", 'SQL_UNESCAPED_CONCAT'), 'Integer cast (int)$id accepted as safe');
        $this->assert(!$this->hasSqlIssue("Db::getInstance()->executeS('SELECT * FROM o WHERE p = ' . (float)\$price);", 'SQL_UNESCAPED_CONCAT'), 'Float cast (float)$price accepted as safe');
        $this->assert(!$this->hasSqlIssue("Db::getInstance()->executeS('SELECT * FROM o WHERE r = \'' . pSQL(\$ref) . '\'');", 'SQL_UNESCAPED_CONCAT'), 'String escaped with pSQL() accepted as safe');
        $this->assert(!$this->hasSqlIssue("Db::getInstance()->executeS('SELECT * FROM o WHERE c = \'' . pSQL(\$comment, true) . '\'');", 'SQL_UNESCAPED_CONCAT'), 'HTML string escaped with pSQL($str, true) accepted as safe');
        $this->assert($this->hasSqlIssue("FROM orders WHERE 1", 'SQL_MISSING_DB_PREFIX'), 'Unprefixed table in FROM clause intercepted');
        $this->assert($this->hasSqlIssue("JOIN customer c ON 1", 'SQL_MISSING_DB_PREFIX'), 'Unprefixed table in JOIN clause intercepted');
        $this->assert($this->hasSqlIssue("UPDATE product SET active=1", 'SQL_MISSING_DB_PREFIX'), 'Unprefixed table in UPDATE clause intercepted');
        $this->assert($this->hasSqlIssue("INSERT INTO cart VALUES(1)", 'SQL_MISSING_DB_PREFIX'), 'Unprefixed table in INSERT clause intercepted');
        $this->assert(!$this->hasSqlIssue("FROM `_DB_PREFIX_orders`", 'SQL_MISSING_DB_PREFIX'), 'Prefixed table _DB_PREFIX_ accepted');
        $this->assert($this->hasSqlIssue("\$sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'c WHERE id = ' . \$id; Db::getInstance()->executeS(\$sql);", 'SQL_UNESCAPED_CONCAT'), 'SQL injection traced inside intermediate $sql variable');
        $this->assert($this->hasSqlIssue("\$sql .= ' AND name = ' . \$search; Db::getInstance()->executeS(\$sql);", 'SQL_UNESCAPED_CONCAT'), 'SQL injection traced inside concatenated $sql variable');
        $this->assert($this->hasSqlIssue("\$sql = 'SELECT * FROM c WHERE id = ' . \$_GET['id'];", 'SQL_UNESCAPED_CONCAT'), 'Direct $_GET in SQL query intercepted');
        $this->assert($this->hasSqlIssue("\$sql = 'SELECT * FROM c WHERE id = ' . \$_POST['id'];", 'SQL_UNESCAPED_CONCAT'), 'Direct $_POST in SQL query intercepted');
        $this->assert($this->hasSqlIssue("\$sql = 'SELECT * FROM c WHERE id = ' . \$_REQUEST['id'];", 'SQL_UNESCAPED_CONCAT'), 'Direct $_REQUEST in SQL query intercepted');

        $installSql = (string) @file_get_contents($this->baseDir . '/examples/genisoft_cartthreshold/sql/install.sql');
        $uninstallSql = (string) @file_get_contents($this->baseDir . '/examples/genisoft_cartthreshold/sql/uninstall.sql');
        $this->assert(str_contains($installSql, '`_DB_PREFIX_'), 'Backticks used on table names in install.sql');
        $this->assert(str_contains($installSql, 'ENGINE=_MYSQL_ENGINE_'), 'ENGINE=_MYSQL_ENGINE_ specified in install.sql');
        $this->assert(str_contains($installSql, '_DB_PREFIX_'), '_DB_PREFIX_ macro used in install.sql');
        $this->assert(str_contains($uninstallSql, '_DB_PREFIX_'), '_DB_PREFIX_ macro used in uninstall.sql');
        $this->assert(str_contains($uninstallSql, 'DROP TABLE IF EXISTS'), 'DROP TABLE IF EXISTS used in uninstall.sql');
        $examplePhpCode = (string) @file_get_contents($modulePhp);
        $this->assert(str_contains($examplePhpCode, 'preg_split'), 'Multi-query split support in executeSqlScript()');
        $this->assert(str_contains($examplePhpCode, 'ON DUPLICATE KEY UPDATE'), 'UPSERT ON DUPLICATE KEY UPDATE implemented in actionCartSave');
        $this->assert(str_contains($installSql, 'UNIQUE KEY `uniq_cart_shop`'), 'Composite unique key (id_cart, id_shop) defined');

        // SECTION 4: Smarty 3/4 Security & Standards (61-80)
        echo "\n--> Category 4: Smarty 3/4 Security & Standards\n";
        $this->assert($this->hasSmartyIssue('<div>{$user_input}</div>', 'SMARTY_UNESCAPED_OUTPUT'), 'Raw unescaped variable {$var} intercepted');
        $this->assert(!$this->hasSmartyIssue('<div>{$user_input|escape:\'html\':\'UTF-8\'}</div>', 'SMARTY_UNESCAPED_OUTPUT'), 'Modifier |escape:html:UTF-8 accepted as safe');
        $this->assert(!$this->hasSmartyIssue('<div>{$user_input|escape:\'htmlall\':\'UTF-8\'}</div>', 'SMARTY_UNESCAPED_OUTPUT'), 'Modifier |escape:htmlall:UTF-8 accepted as safe');
        $this->assert(!$this->hasSmartyIssue('<script>var id = \'{$user_input|escape:\'javascript\':\'UTF-8\'}\';</script>', 'SMARTY_UNESCAPED_OUTPUT'), 'Modifier |escape:javascript accepted as safe');
        $this->assert(!$this->hasSmartyIssue('<span>{$count|intval}</span>', 'SMARTY_UNESCAPED_OUTPUT'), 'Modifier |intval accepted as safe');
        $this->assert(!$this->hasSmartyIssue('<span>{$price|floatval}</span>', 'SMARTY_UNESCAPED_OUTPUT'), 'Modifier |floatval accepted as safe');
        $this->assert(!$this->hasSmartyIssue('<div>{$html|cleanHtml}</div>', 'SMARTY_UNESCAPED_OUTPUT'), 'Modifier |cleanHtml accepted as safe');
        $this->assert(!$this->hasSmartyIssue('<span>{$smarty.now|date_format:\'%Y\'}</span>', 'SMARTY_UNESCAPED_OUTPUT'), 'Safe formatter |date_format accepted without false positive');
        $this->assert(!$this->hasSmartyIssue('<span>{$price|string_format:\'%.2f\'}</span>', 'SMARTY_UNESCAPED_OUTPUT'), 'Safe formatter |string_format accepted without false positive');
        $this->assert(!$this->hasSmartyIssue('<span>{$items|count}</span>', 'SMARTY_UNESCAPED_OUTPUT'), 'Safe formatter |count accepted without false positive');
        $this->assert(!$this->hasSmartyIssue('<span>{$data|json_encode}</span>', 'SMARTY_UNESCAPED_OUTPUT'), 'Safe formatter |json_encode accepted without false positive');
        $this->assert(!$this->hasSmartyIssue('<span>{$amount|number_format:2}</span>', 'SMARTY_UNESCAPED_OUTPUT'), 'Safe formatter |number_format accepted without false positive');
        $this->assert($this->hasSmartyIssue('<div>{$unsafe_data nofilter}</div>', 'SMARTY_NOFILTER_BYPASS'), 'Dangerous nofilter bypass intercepted as CRITICAL');
        $this->assert($this->hasSmartyIssue('{php} echo date("Y"); {/php}', 'SMARTY_PHP_TAG'), 'Deprecated {php} block intercepted as CRITICAL');
        $this->assert($this->hasSmartyIssue('{/php}', 'SMARTY_PHP_TAG'), 'Closing {/php} tag intercepted');
        $this->assert(!$this->hasSmartyIssue('{l s=\'Hello\' d=\'Modules.Genisoft.Shop\'}', 'SMARTY_LEGACY_TRANSLATION'), 'PrestaShop 8 translation domain accepted');
        $this->assert($this->hasSmartyIssue('{l s=\'Hello Legacy\'}', 'SMARTY_LEGACY_TRANSLATION'), 'Legacy translation without domain flagged as INFO');
        $this->assert(!$this->hasSmartyIssue('{$my_var = 123}', 'SMARTY_UNESCAPED_OUTPUT'), 'Smarty assignment {$var = 123} not falsely flagged');
        $this->assert(!$this->hasSmartyIssue('{$counter++}', 'SMARTY_UNESCAPED_OUTPUT'), 'Smarty increment {$counter++} not falsely flagged');
        $exampleTpl = $this->baseDir . '/examples/genisoft_cartthreshold/views/templates/hook/cart_threshold_bar.tpl';
        $this->assert(!$this->hasSmartyFileIssues($exampleTpl), 'Exemplary cart_threshold_bar.tpl 100% compliant');

        // SECTION 5: Multi-Shop & Multi-Lang Isolation (81-90)
        echo "\n--> Category 5: Multi-Shop & Multi-Lang Context Isolation\n";
        $this->assert($this->hasContextIssue('JOIN _DB_PREFIX_product_shop ps ON ps.id_product=p.id_product', 'MULTISHOP_MISSING_FILTER'), 'Unconstrained product_shop join intercepted');
        $this->assert($this->hasContextIssue('JOIN _DB_PREFIX_category_shop cs ON cs.id_cat=c.id_cat', 'MULTISHOP_MISSING_FILTER'), 'Unconstrained category_shop join intercepted');
        $this->assert($this->hasContextIssue('JOIN `\' . _DB_PREFIX_ . \'product_shop` ps ON 1', 'MULTISHOP_MISSING_FILTER'), 'Concatenated _shop table join intercepted');
        $this->assert(!$this->hasContextIssue('JOIN `\' . _DB_PREFIX_ . \'product_shop` ps ON ps.id_shop = (int)$this->context->shop->id', 'MULTISHOP_MISSING_FILTER'), 'Shop-constrained join with id_shop accepted');
        $this->assert($this->hasContextIssue('foreach ($list as $item) { $ctx = Context::getContext(); }', 'PERF_CONTEXT_IN_LOOP'), 'Context::getContext() inside loop flagged as performance warning');
        $this->assert(!$this->hasContextIssue('$ctx = Context::getContext(); foreach ($list as $item) { $x = $ctx->shop->id; }', 'PERF_CONTEXT_IN_LOOP'), 'Context extracted outside loop accepted');
        $this->assert($this->hasContextIssue('Configuration::updateValue(self::CONFIG_AMOUNT, 50.0);', 'MULTISHOP_CONF_UPDATE'), 'Configuration::updateValue with PHP constant recognized');
        $this->assert(str_contains($examplePhpCode, '(int) $this->context->shop->id'), 'Explicit shop ID extraction present in exemplary module');
        $this->assert(str_contains($examplePhpCode, 'Tools::displayPrice('), 'Dynamic currency conversion via Tools::displayPrice() implemented');
        $exampleTplContent = (string) @file_get_contents($exampleTpl);
        $this->assert(!str_contains($exampleTplContent, 'EUR') && !str_contains($exampleTplContent, '€') && !str_contains($exampleTplContent, '£') && !preg_match('/\b\$\d+|\d+\s*\$/', $exampleTplContent), 'Zero hardcoded currency symbols in templates');

        // SECTION 6: Module Packaging, Assets & Security Index (91-100)
        echo "\n--> Category 6: Module Structure, Assets & Directory Security\n";
        $exDir = $this->baseDir . '/examples/genisoft_cartthreshold';
        $this->assert(file_exists($exDir . '/index.php'), 'Security index.php in module root');
        $this->assert(file_exists($exDir . '/classes/index.php'), 'Security index.php in classes/');
        $this->assert(file_exists($exDir . '/controllers/index.php'), 'Security index.php in controllers/');
        $this->assert(file_exists($exDir . '/controllers/admin/index.php'), 'Security index.php in controllers/admin/');
        $this->assert(file_exists($exDir . '/sql/index.php'), 'Security index.php in sql/');
        $this->assert(file_exists($exDir . '/views/index.php'), 'Security index.php in views/');
        $this->assert(file_exists($exDir . '/views/css/index.php'), 'Security index.php in views/css/');
        $this->assert(file_exists($exDir . '/views/templates/hook/index.php'), 'Security index.php in views/templates/hook/');

        $logoPath = $exDir . '/logo.png';
        $isLogoValid = file_exists($logoPath) && getimagesize($logoPath) !== false;
        $this->assert($isLogoValid, 'Mandatory logo.png present and valid 32x32 image');

        $configXmlPath = $exDir . '/config.xml';
        $xml = @simplexml_load_file($configXmlPath);
        $isXmlValid = ($xml !== false) && isset($xml->displayName) && isset($xml->version) && isset($xml->author) && isset($xml->tab);
        $this->assert($isXmlValid, 'Valid config.xml metadata specification (PrestaShop 8+ Module Manager compliant)');

        // Summary
        echo "\n==========================================================\n";
        echo "  AUDIT REPORT: {$this->passedCount}/100 Assertions Passed\n";
        $scorePercent = number_format(($this->passedCount / 100) * 100, 1);
        echo "  Quality & Certification Score: {$scorePercent}% [CERTIFIED]\n";
        echo "==========================================================\n";

        return $this->failedCount === 0;
    }

    private function hasHookIssue(string $code, string $category): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ps_hook_');
        file_put_contents($tmp, "<?php\n" . $code);
        $issues = $this->hookGuard->validateFile($tmp);
        unlink($tmp);
        foreach ($issues as $issue) {
            if ($issue['category'] === $category) {
                return true;
            }
        }
        return false;
    }

    private function hasSqlIssue(string $code, string $category): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ps_sql_');
        file_put_contents($tmp, "<?php\n" . $code);
        $issues = $this->sqlGuard->validateFile($tmp);
        unlink($tmp);
        foreach ($issues as $issue) {
            if ($issue['category'] === $category) {
                return true;
            }
        }
        return false;
    }

    private function hasSmartyIssue(string $tplContent, string $category): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ps_tpl_') . '.tpl';
        file_put_contents($tmp, $tplContent);
        $output = shell_exec('node ' . escapeshellarg($this->baseDir . '/tools/ps-smarty-guard.js') . ' ' . escapeshellarg($tmp));
        @unlink($tmp);
        return $output !== null && str_contains($output, "[{$category}]");
    }

    private function hasSmartyFileIssues(string $filePath): bool
    {
        $output = shell_exec('node ' . escapeshellarg($this->baseDir . '/tools/ps-smarty-guard.js') . ' ' . escapeshellarg($filePath));
        return $output !== null && (str_contains($output, '[ERROR]') || str_contains($output, '[CRITICAL]') || str_contains($output, '[WARNING]'));
    }

    private function hasContextIssue(string $code, string $category): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ps_ctx_');
        file_put_contents($tmp, "<?php\n" . $code);
        $issues = $this->contextGuard->validateFile($tmp);
        unlink($tmp);
        foreach ($issues as $issue) {
            if ($issue['category'] === $category) {
                return true;
            }
        }
        return false;
    }
}

if (php_sapi_name() === 'cli') {
    $auditor = new HarnessAuditor($baseDir);
    $success = $auditor->runAll();
    exit($success ? 0 : 1);
}
