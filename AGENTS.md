# AGENTS.md - PrestaShop 8+ Autonomous Engineering Harness Protocol

## 1. System Identity & Mission
You are operating within the **Genisoft PrestaShop 8+ Engineering Harness**.
Your mission is to develop, audit, and refactor PrestaShop modules with **deterministic pattern-based checks**, **AppSec SQL sanitization**, and **multi-store context isolation**. The checks catch common mistakes; they do not replace installation tests.

## 2. Mandatory Verification Gates
Before submitting any code change:
1. **Hook Safety Check**:
   `php tools/ps-hook-guard.php <target-file-or-dir>`
   Must pass with 0 unrecognized or WordPress hooks.
2. **SQL Injection AppSec Check**:
   `php tools/ps-sql-guard.php <target-file-or-dir>`
   All concatenated variables MUST use `pSQL()`. All numerical IDs MUST use `(int)` or `(float)`.
3. **Smarty XSS Check**:
   `node tools/ps-smarty-guard.js <target-file-or-dir>`
   All variable outputs in templates MUST be escaped (`|escape:'html':'UTF-8'`). Avoid unsafe `nofilter`.
4. **Context Isolation Check**:
   `php tools/ps-context-guard.php <target-file-or-dir>`
   Ensure `(int)$this->context->shop->id` is used to filter `_shop` tables.
5. **Module Audit**:
   `php tools/harness-auditor.php <module-dir>`
   Exit code 0 = no blocking finding, 1 = fix the findings, 2 = the audit did not run (never treat it as a pass).
6. **Runtime Check**:
   Install the module on each PrestaShop version in `ps_versions_compliancy` and load the pages its hooks touch.

## 3. Strict Prohibitions
- NEVER use WordPress functions (`add_action`, `add_filter`, `wp_head`).
- NEVER use direct `$_GET` or `$_POST` in database queries; use `Tools::getValue()` with explicit casting.
- NEVER leave a module directory without a security `index.php`.
- NEVER query `_shop` tables without filtering by `id_shop`.
