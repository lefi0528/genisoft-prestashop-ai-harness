<?php
declare(strict_types=1);

namespace Genisoft\PrestashopHarness\Tools;

/**
 * PrestaShop 8+ Multi-Shop & Multi-Language Context Isolation Guard (PSR-4 Compliant)
 *
 * Verifies that modules do not leak customer data or catalog configurations
 * across multi-store instances.
 */
class PsContextGuard
{
    public function validateFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [['severity' => 'ERROR', 'category' => 'FILE_NOT_FOUND', 'message' => "File not found: {$filePath}"]];
        }

        $code = (string) file_get_contents($filePath);
        $issues = [];

        // 1. Check for Configuration::updateValue without considering multi-shop context
        if (preg_match_all('/Configuration::updateValue\s*\(\s*(self::[a-zA-Z0-9_]+|[\'"][^\'"]+[\'"])\s*,\s*[^,)]+\s*\)/i', $code, $confMatches)) {
            $issues[] = [
                'severity' => 'INFO',
                'category' => 'MULTISHOP_CONF_UPDATE',
                'message' => "Configuration::updateValue used with default 2 arguments. Ensure multi-shop inheritance is intentional or pass id_shop_group / id_shop."
            ];
        }

        // 2. Check for missing shop restriction when joining product or category _shop tables
        // Supports both concatenated `' . _DB_PREFIX_ . 'product_shop` and literal `_DB_PREFIX_product_shop`
        $hasShopJoin = (bool) preg_match('/JOIN\s+[^;\n]*?(product|category)_shop\b/i', $code);
        if ($hasShopJoin && !preg_match('/id_shop\s*=\s*/i', $code)) {
            $issues[] = [
                'severity' => 'ERROR',
                'category' => 'MULTISHOP_MISSING_FILTER',
                'message' => "Table '_shop' joined without explicit 'id_shop' condition. Risk of multi-store catalog data leakage."
            ];
        }

        // 3. Check for static Context::getContext() inside loops
        if (preg_match('/(foreach|while|for)\s*\(.*?\)\s*\{[^}]*Context::getContext\(\)/is', $code)) {
            $issues[] = [
                'severity' => 'WARNING',
                'category' => 'PERF_CONTEXT_IN_LOOP',
                'message' => "Context::getContext() invoked inside a loop. Store in a local variable before the loop for better performance."
            ];
        }

        return $issues;
    }
}
