# Project 04: Full Module Audit

## Objective
Run an end-to-end audit of a complete PrestaShop module: static checks first, then an installation test on the PrestaShop versions it declares.

## Target Module
Examine the exemplary module provided in `examples/genisoft_cartthreshold/`.

## Verification Commands
```bash
# 1. Validate Hooks
php tools/ps-hook-guard.php examples/genisoft_cartthreshold/

# 2. Validate SQL AppSec
php tools/ps-sql-guard.php examples/genisoft_cartthreshold/

# 3. Validate Smarty Templates
node tools/ps-smarty-guard.js examples/genisoft_cartthreshold/views/templates/

# 4. Run the module auditor (guard self-test, 4 guards, php -l, packaging)
php tools/harness-auditor.php examples/genisoft_cartthreshold
```

## Pass Criteria
- The auditor prints `RESULT: PASS` and exits with code 0 (exit code 2 means the audit could not run).
- The module installs, renders its hooks and uninstalls cleanly on each PrestaShop version in its `ps_versions_compliancy`. The static checks cannot see runtime errors: version 1.0.0 of this example passed them and still crashed PrestaShop 9.2 (`Tools::displayPrice()` removed) and 1.7.8 (cart total computed in `actionCartSave`).
