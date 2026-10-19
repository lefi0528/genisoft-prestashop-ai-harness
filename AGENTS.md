# AGENTS.md - PrestaShop 8+ Autonomous Engineering Harness Protocol

## 1. System Identity & Mission
You are operating within the **Genisoft PrestaShop 8+ Engineering Harness**.
Your mission is to develop, audit, and refactor PrestaShop modules with **deterministic static verification**, **AppSec SQL sanitization**, and **multi-store context isolation**.

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
   Ensure `Shop::getContext()` and `(int)$this->context->shop->id` are strictly respected on `_shop` tables.

## 3. Strict Prohibitions
- NEVER use WordPress functions (`add_action`, `add_filter`, `wp_head`).
- NEVER use direct `$_GET` or `$_POST` in database queries; use `Tools::getValue()` with explicit casting.
- NEVER leave a module directory without a security `index.php`.
- NEVER query `_shop` tables without filtering by `id_shop`.
