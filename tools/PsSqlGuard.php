<?php
declare(strict_types=1);

namespace Genisoft\PrestashopHarness\Tools;

/**
 * PrestaShop 8+ SQL Injection & AppSec Guard (PSR-4 Compliant)
 *
 * Enforces mandatory pSQL() string escaping, (int) cast on IDs,
 * _DB_PREFIX_ prefixing, and multi-shop table isolation.
 */
class PsSqlGuard
{
    public function validateFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [['severity' => 'ERROR', 'category' => 'FILE_NOT_FOUND', 'message' => "File not found: {$filePath}"]];
        }

        $code = (string) file_get_contents($filePath);
        $issues = [];

        // 1. Direct variable interpolation in SQL double-quoted strings
        if (preg_match_all('/"(SELECT|UPDATE|DELETE|INSERT\s+INTO)\s+[^"]*?\$([a-zA-Z0-9_]+)[^"]*?"/is', $code, $interpMatches)) {
            foreach ($interpMatches[2] as $varName) {
                $issues[] = [
                    'severity' => 'CRITICAL',
                    'category' => 'SQL_DIRECT_INTERPOLATION',
                    'message' => "Direct variable interpolation '\${$varName}' inside SQL double quotes. Must use pSQL() or (int) concatenation."
                ];
            }
        }

        // 2. Analyze Db::getInstance()->(execute|executeS|getValue|getRow) calls
        if (preg_match_all('/Db::getInstance\(\)->(execute|executeS|getValue|getRow)\s*\(\s*(.*?)\s*\)/is', $code, $matches)) {
            foreach ($matches[2] as $queryArg) {
                $queryArgTrim = trim($queryArg);
                // Case A: Query passed directly as expression
                if (str_starts_with($queryArgTrim, "'") || str_starts_with($queryArgTrim, '"')) {
                    $subIssues = $this->analyzeQueryExpression($queryArgTrim);
                    foreach ($subIssues as $issue) {
                        $issues[] = $issue;
                    }
                }
                // Case B: Query passed via variable e.g. $sql or $query
                elseif (preg_match('/^\$([a-zA-Z0-9_]+)$/', $queryArgTrim, $varMatch)) {
                    $varName = $varMatch[1];
                    // Find assignment(s) to this variable: $sql = ...;
                    if (preg_match_all('/\$' . preg_quote($varName, '/') . '\s*(\.=|==?)\s*(.*?);/is', $code, $assignMatches)) {
                        foreach ($assignMatches[2] as $assignedExpr) {
                            $subIssues = $this->analyzeQueryExpression($assignedExpr);
                            foreach ($subIssues as $issue) {
                                $issues[] = $issue;
                            }
                        }
                    }
                }
            }
        }

        // 3. Scan for standalone SQL variable assignments $sql = 'SELECT...' that contain unescaped concatenation
        if (preg_match_all('/\$([a-zA-Z0-9_]*sql[a-zA-Z0-9_]*)\s*=\s*(.*?);/is', $code, $sqlVars)) {
            foreach ($sqlVars[2] as $assignedExpr) {
                if (preg_match('/(SELECT|INSERT|UPDATE|DELETE)/i', $assignedExpr)) {
                    $subIssues = $this->analyzeQueryExpression($assignedExpr);
                    foreach ($subIssues as $issue) {
                        $issues[] = $issue;
                    }
                }
            }
        }

        // 4. Hardcoded database table names without _DB_PREFIX_
        // Matches FROM table, JOIN table, UPDATE table, INSERT INTO table
        if (preg_match_all('/(FROM|JOIN|UPDATE|INSERT\s+INTO)\s+`?([a-zA-Z0-9_]+)`?/i', $code, $fromMatches)) {
            foreach ($fromMatches[2] as $table) {
                if (!str_starts_with($table, '_DB_PREFIX_') &&
                    !str_starts_with($table, '$') &&
                    !in_array(strtolower($table), ['dual', 'information_schema', 'values', 'set'])) {
                    $issues[] = [
                        'severity' => 'WARNING',
                        'category' => 'SQL_MISSING_DB_PREFIX',
                        'message' => "Table '{$table}' referenced without '_DB_PREFIX_'. Always use '`' . _DB_PREFIX_ . '{$table}`'."
                    ];
                }
            }
        }

        return $this->deduplicateIssues($issues);
    }

    private function analyzeQueryExpression(string $expr): array
    {
        $issues = [];

        // Check for raw variable concatenation without (int), (float), or pSQL()
        // e.g. . " WHERE id = " . $id_customer or . "'" . $name . "'"
        if (preg_match_all('/\.\s*\$([a-zA-Z0-9_]+)\b/i', $expr, $concatMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($concatMatches[1] as $idx => $match) {
                $varName = $match[0];
                $offset = $match[1];
                
                // Exclude constants or PrestaShop globals
                if (in_array($varName, ['this', '_DB_PREFIX_', '_MYSQL_ENGINE_'])) {
                    continue;
                }

                // Look at the prefix before the dot to see if it is cast with (int) or (float)
                $prefix = substr($expr, max(0, $offset - 15), 15);
                if (preg_match('/\((int|float)\)\s*\.\s*$/i', $prefix) || preg_match('/\((int|float)\)\s*$/i', $prefix)) {
                    continue;
                }

                // Check if wrapped in pSQL($var)
                $surrounding = substr($expr, max(0, $offset - 25), 45);
                if (preg_match('/pSQL\s*\(\s*\$' . preg_quote($varName, '/') . '\b/i', $surrounding)) {
                    continue;
                }

                $issues[] = [
                    'severity' => 'ERROR',
                    'category' => 'SQL_UNESCAPED_CONCAT',
                    'message' => "Unsanitized variable '\${$varName}' concatenated in SQL query. Wrap in (int) or pSQL()."
                ];
            }
        }

        return $issues;
    }

    private function deduplicateIssues(array $issues): array
    {
        $unique = [];
        $result = [];
        foreach ($issues as $issue) {
            $key = $issue['category'] . '|' . $issue['message'];
            if (!isset($unique[$key])) {
                $unique[$key] = true;
                $result[] = $issue;
            }
        }
        return $result;
    }
}
