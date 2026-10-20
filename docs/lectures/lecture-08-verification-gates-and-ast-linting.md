# Lecture 08: Verification Gates: Pattern-Based Static Checks

## Automated Quality Assurance with Patterns and Tokens

To catch the most common mistakes of AI coding agents early, the Genisoft Harness runs fast static checks. The SQL, Smarty and multi-shop checks are regular expressions; the hook check reads PHP tokens, so a WordPress name in a comment or a string is not mistaken for code. None of them follows data flow across files, so they reduce risk without guaranteeing the absence of regressions: a module still needs review and installation tests on its target PrestaShop versions.

### The 4 Mandatory Verification Gates

```bash
# 1. Hook Verification
php tools/ps-hook-guard.php <path-to-module>

# 2. SQL AppSec Verification
php tools/ps-sql-guard.php <path-to-module>

# 3. Smarty Security Verification
node tools/ps-smarty-guard.js <path-to-views>

# 4. Multi-Shop Isolation Verification
php tools/ps-context-guard.php <path-to-module>
```

When an agent introduces an unescaped variable or invalid hook, the gate reports the file (and the line, for templates and hook findings) with a recommendation. Every tool exits with 0 when there is no ERROR/CRITICAL finding, 1 when there is at least one, and 2 when it could not run (missing path, no file to scan, corrupt hook catalog), so an agent never mistakes a failed run for a clean one.

```bash
# All four guards plus php -l and packaging checks on one module
php tools/harness-auditor.php <path-to-module>
```
