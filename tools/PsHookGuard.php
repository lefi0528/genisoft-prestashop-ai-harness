<?php
declare(strict_types=1);

namespace Genisoft\PrestashopHarness\Tools;

/**
 * PrestaShop 8+ Hook Safety Guard (PSR-4 Compliant)
 *
 * Prevents AI coding agents from registering hallucinated hooks,
 * using WordPress actions, or violating PrestaShop hook execution conventions.
 */
class PsHookGuard
{
    private array $officialHooks = [];
    private array $forbiddenWpHooks = [
        'add_action',
        'add_filter',
        'wp_head',
        'wp_footer',
        'init',
        'admin_init',
        'save_post',
        'woocommerce_before_calculate_totals',
        'woocommerce_cart_calculate_fees'
    ];

    public function __construct(string $catalogPath)
    {
        if (!file_exists($catalogPath)) {
            throw new \RuntimeException("Hook catalog not found: {$catalogPath}");
        }
        $raw = (string) file_get_contents($catalogPath);
        $this->officialHooks = json_decode($raw, true) ?: [];
    }

    public function validateFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [['severity' => 'ERROR', 'category' => 'FILE_NOT_FOUND', 'message' => "File not found: {$filePath}"]];
        }

        $code = (string) file_get_contents($filePath);
        $issues = [];

        // 1. Check for WordPress hallucinations
        foreach ($this->forbiddenWpHooks as $wpFunc) {
            if (preg_match('/\b' . preg_quote($wpFunc, '/') . '\s*\(/i', $code)) {
                $issues[] = [
                    'severity' => 'CRITICAL',
                    'category' => 'WP_HALLUCINATION',
                    'message' => "WordPress function/hook '{$wpFunc}' detected in PrestaShop module code."
                ];
            }
        }

        // 2. Direct string registration: registerHook('hookName')
        if (preg_match_all('/registerHook\s*\(\s*([\'"])(.*?)\1/i', $code, $matches)) {
            foreach ($matches[2] as $hook) {
                $hookCheck = $this->verifyHookName($hook, false);
                if ($hookCheck !== null) {
                    $issues[] = $hookCheck;
                }
            }
        }

        // 3. Direct array registration: registerHook(['hook1', 'hook2'])
        if (preg_match_all('/registerHook\s*\(\s*\[(.*?)\]/is', $code, $arrayMatches)) {
            foreach ($arrayMatches[1] as $arrBody) {
                if (preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', $arrBody, $items)) {
                    foreach ($items[1] as $h) {
                        $hookCheck = $this->verifyHookName($h, false);
                        if ($hookCheck !== null) {
                            $issues[] = $hookCheck;
                        }
                    }
                }
            }
        }

        // 4. Variable array registration: $hooks = ['h1', 'h2']; foreach ($hooks as $h) { $this->registerHook($h); }
        if (preg_match_all('/\$([a-zA-Z0-9_]+)\s*=\s*\[(.*?)\];/is', $code, $varArrays)) {
            foreach ($varArrays[1] as $idx => $varName) {
                // If this variable is passed to registerHook($varName) or iterated in foreach
                if (preg_match('/registerHook\s*\(\s*\$' . preg_quote($varName, '/') . '\b/i', $code) ||
                    preg_match('/foreach\s*\(\s*\$' . preg_quote($varName, '/') . '\s+as\s+\$([a-zA-Z0-9_]+)\s*\)\s*\{[^}]*registerHook\s*\(\s*\$\1\b/is', $code)) {
                    $arrayContent = $varArrays[2][$idx];
                    if (preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', $arrayContent, $items)) {
                        foreach ($items[1] as $h) {
                            $hookCheck = $this->verifyHookName($h, false);
                            if ($hookCheck !== null) {
                                $issues[] = $hookCheck;
                            }
                        }
                    }
                }
            }
        }

        // 5. Hook implementation methods: public function hook<Name>($params)
        if (preg_match_all('/public\s+function\s+hook([a-zA-Z0-9_]+)\s*\(/i', $code, $methodMatches)) {
            foreach ($methodMatches[1] as $methodHook) {
                // PrestaShop convention: method is hookPascalCase, but registered hook is camelCase (lcfirst)
                $hookCheck = $this->verifyHookName($methodHook, true);
                if ($hookCheck !== null) {
                    $issues[] = $hookCheck;
                }
            }
        }

        return $issues;
    }

    private function verifyHookName(string $hookName, bool $isMethod = false): ?array
    {
        // If it's a method name (e.g. DisplayHeader), normalize to camelCase (displayHeader)
        $normalized = $isMethod ? lcfirst($hookName) : $hookName;

        if (isset($this->officialHooks[$normalized])) {
            return null;
        }

        // Also check if the raw name exists directly
        if (isset($this->officialHooks[$hookName])) {
            return null;
        }

        // Check case-insensitive match
        $lowerHook = strtolower($hookName);
        foreach ($this->officialHooks as $official => $data) {
            if (strtolower($official) === $lowerHook) {
                // For methods, if normalized matches, it is the correct convention, not a mismatch!
                if ($isMethod && lcfirst($hookName) === $official) {
                    return null;
                }
                return [
                    'severity' => 'WARNING',
                    'category' => 'HOOK_CASING',
                    'message' => "Hook casing mismatch: '{$hookName}'. Official casing is '{$official}'."
                ];
            }
        }

        // Find closest hook using Levenshtein distance
        $closest = null;
        $shortest = 4;
        foreach (array_keys($this->officialHooks) as $official) {
            $dist = levenshtein($lowerHook, strtolower($official));
            if ($dist <= $shortest) {
                $closest = $official;
                $shortest = $dist;
            }
        }

        $suggestion = $closest ? " Did you mean '{$closest}'?" : '';

        return [
            'severity' => 'ERROR',
            'category' => 'HALLUCINATED_HOOK',
            'message' => "Unknown or hallucinated hook '{$hookName}'.{$suggestion}"
        ];
    }
}
