# Lecture 08: Verification Gates: Static AST Analysis & Scaffolding

## Automated Quality Assurance with AST Parsers

To guarantee that AI coding agents produce zero regressions, the Genisoft Harness deploys static analysis tools based on PHP tokenizer and AST analysis.

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

When an agent introduces an unescaped variable or invalid hook, the gate fails immediately and outputs exact line numbers and recommendations.
