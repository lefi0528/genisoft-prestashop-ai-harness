# 🛡️ Genisoft PrestaShop AI Harness

[![CI & Security Matrix](https://github.com/lefi0528/genisoft-prestashop-ai-harness/actions/workflows/test-harness.yml/badge.svg)](https://github.com/lefi0528/genisoft-prestashop-ai-harness/actions/workflows/test-harness.yml)
[![Documentation](https://img.shields.io/badge/Docs-VitePress-0284c7?logo=vitepress)](https://lefi0528.github.io/genisoft-prestashop-ai-harness/)
[![PrestaShop](https://img.shields.io/badge/example%20module%20tested%20on-PrestaShop%201.7.8%20%7C%209.2-df0067?logo=prestashop)](examples/genisoft_cartthreshold/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4?logo=php)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-emerald.svg)](LICENSE)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=lefi0528_genisoft-prestashop-ai-harness&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=lefi0528_genisoft-prestashop-ai-harness)

> **Autonomous PrestaShop 8+ Module Engineering & AppSec Harness for AI Coding Agents**  
> Gives LLM coding agents (Claude Code, Cursor, Copilot, Antigravity) fast, pattern-based checks for the PrestaShop mistakes they make most: **hallucinated hook names**, **unescaped SQL concatenation**, **multi-store joins without `id_shop`** and **unescaped Smarty output**.  
> The SQL, multi-shop and Smarty checks are regular expressions; the hook check reads PHP tokens. None of them is a full PHP parser: they catch common mistakes, they do not prove that a module is secure (see Limitations below).

---

## 🌟 Why PrestaShop Needs an AI Engineering Harness

Foundation models (Claude Opus, GPT-4o, DeepSeek R1) are trained overwhelmingly on standard generic PHP frameworks (Laravel, Symfony) and WordPress. While PrestaShop 8 integrates Symfony components into its Core Back-Office, **module development remains a unique hybrid ecosystem**:

1. **Hook Hallucination**: AI agents frequently inject WordPress functions (`add_action`, `add_filter`) or invent hooks that the core never executes (`actionProductCreate`, `actionCartUpdate`), leading to silent logic failure.
2. **SQL Injection Vulnerabilities**: PrestaShop modules query data through `Db::getInstance()`. Naive AI models concatenate raw variables, causing critical SQL injection vectors.
3. **Multi-Store Isolation Leaks**: Failing to filter by `id_shop` when joining `_shop` tables leaks customer data and catalog pricing between separate storefronts.
4. **Smarty XSS Vectors**: Missing `|escape:'html':'UTF-8'` in Smarty `.tpl` templates introduces reflected and stored Cross-Site Scripting flaws.

The **Genisoft PrestaShop AI Harness** provides an automated, deterministic first gate between AI code generation and human review. It does not replace code review, nor testing the module on the PrestaShop versions it targets.

---

## 🏗️ Architecture & Verification Subsystems

```
+-----------------------------------------------------------+
|               Autonomous AI Coding Agent                 |
|       (Claude Code, Antigravity, Cursor, Copilot)         |
+-----------------------------------------------------------+
                             |
                             v [Module Code Generation]
+-----------------------------------------------------------+
|        Genisoft PrestaShop 8+ Verification Harness        |
+-----------------------------------------------------------+
  |-- Gate 1: Hook Catalog Validator (1,301 hook names)     |
  |-- Gate 2: SQL AppSec & Type Safety (pSQL / (int))       |
  |-- Gate 3: Smarty XSS & Translation Domain Linter        |
  |-- Gate 4: Multi-Shop Context Isolation Scanner          |
  |-- Gate 5: Security Index & Module Structure Auditor     |
+-----------------------------------------------------------+
                             |
       +---------------------+---------------------+
       | [PASS]                                    | [FAIL]
       v                                           v
+-------------------------------+   +-------------------------------+
| Next: human review, then      |   | Automated Agent Feedback Loop |
| install tests on target PS    |   | Actionable Error Diagnostics  |
+-------------------------------+   +-------------------------------+
```

### The 4 Verification Tools

| Tool | Language | Purpose & Enforcement |
| :--- | :--- | :--- |
| **`tools/PsHookGuard.php`** (`ps-hook-guard.php`) | PHP 8.1+ (PSR-4) | Reads PHP tokens to find the hooks a module registers (strings, `[]`/`array()` lists, variables, class constants, properties, getter methods, `foreach` loops) and its `hook<Name>()` methods. Accepts the 1,301 hook names of `data/ps-hooks-reference.json` (built from PrestaShop 1.7.8.11, 8.2.8 and 9.2.0) and the names the core builds at runtime (`actionObject<Class>AddAfter`, grid and form hooks); warns on legacy aliases and on custom hooks the module executes itself; flags unknown names with the closest hook (Levenshtein distance) and WordPress calls. |
| **`tools/PsSqlGuard.php`** (`ps-sql-guard.php`) | PHP 8.1+ (PSR-4) | Regex-based scanner: flags `$variables` and request values (`Tools::getValue()`, `Tools::getAllValues()`, `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`) concatenated into SQL without `(int)`, `(float)` or `pSQL()` at the concatenation point, variables interpolated in double-quoted SQL, and table names without `_DB_PREFIX_`. |
| **`tools/ps-smarty-guard.js`** | Node.js | Regex-based Smarty linter flagging unescaped `{$variables}`, `nofilter`, `{php}` blocks, and `{l}` strings without a translation domain (`d='Modules...'`). |
| **`tools/PsContextGuard.php`** (`ps-context-guard.php`) | PHP 8.1+ (PSR-4) | Flags `product_shop` / `category_shop` joins in files with no `id_shop =` condition, `Context::getContext()` inside loops, and 2-argument `Configuration::updateValue()` calls (info). |
| **`tools/harness-auditor.php`** (`audit-harness.sh`) | PHP 8.1+ | Module auditor: `php tools/harness-auditor.php <module-dir>` self-tests the guards on known snippets, then runs the 4 guards, `php -l` and packaging checks (main file, `logo.png`, `index.php` in each folder) on the module. |

Every tool uses the same exit codes: **0** = no ERROR/CRITICAL finding, **1** = ERROR/CRITICAL finding(s), **2** = tool error (missing path, no file to scan, missing or corrupt hook catalog, Node.js missing). WARNING and INFO findings are printed but do not fail the run.

---

## 📚 10-Lecture Advanced Engineering Curriculum

Our interactive documentation provides 10 in-depth lectures covering every facet of PrestaShop module engineering:

1. [Lecture 01: Why AI Agents Fail on PrestaShop](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-01-why-ai-agents-fail-on-prestashop)
2. [Lecture 02: PrestaShop 8 Hybrid Architecture: Legacy Core vs Symfony](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-02-prestashop-8-hybrid-architecture)
3. [Lecture 03: The Hook Dispatch & Execution Lifecycle](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-03-hook-dispatch-and-execution-lifecycle)
4. [Lecture 04: Offensive AppSec: SQL Injection Prevention (pSQL vs int)](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-04-appsec-sql-sanitization-psql-vs-int)
5. [Lecture 05: The Silent Killer: Multi-Shop & Multi-Lang Context Leaks](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-05-multishop-and-multilang-context-isolation)
6. [Lecture 06: Smarty Template Security: XSS Prevention & Compilation](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-06-smarty-security-escaping-and-compilation)
7. [Lecture 07: Back-Office Forms: HelperForm vs Symfony Form Types](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-07-helperform-vs-symfony-forms-in-prestashop-8)
8. [Lecture 08: Verification Gates: Pattern-Based Static Checks](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-08-verification-gates-and-ast-linting)
9. [Lecture 09: E-Commerce Performance: N+1 Query Elimination & Object Cache](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-09-performance-n-plus-one-queries-and-cache)
10. [Lecture 10: The Autonomous Agent Harness Protocol (AGENTS.md)](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-10-autonomous-agent-prompt-harness-protocol)

---

## 🧪 Hands-On Projects

| Project | Challenge | Skills Demonstrated |
| :--- | :--- | :--- |
| **[Project 01](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-01-hook-hallucination-repair)** | Hook Hallucination Repair | Replace WordPress actions and phantom hooks with valid PrestaShop 8+ hook signatures. |
| **[Project 02](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-02-sql-injection-and-multishop-leak)** | SQL Injection & Multi-Shop Leak | Sanitize vulnerable database queries with `(int)` and `pSQL()`; secure `id_shop` constraints. |
| **[Project 03](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-03-smarty-xss-and-translation-repair)** | Smarty XSS & Translation Domain | Neutralize template XSS vectors and update to modern PrestaShop 8 translation domains. |
| **[Project 04](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-04-full-module-certification)** | Full Module Audit | Run the guards and the module auditor on a complete module, then confirm the result by installing it on PrestaShop. |

---

## 🚀 Example Module: `genisoft_cartthreshold`

A small teaching module (free-shipping progress bar) in [`examples/genisoft_cartthreshold/`](examples/genisoft_cartthreshold/). It is an example, not a product: review and test it before using it on a live shop.

- **What was tested (version 1.1.0)**, by installing it with `bin/console prestashop:module` and requesting real pages:
  - PrestaShop **1.7.8.11** (PHP 7.4): upgrade from 1.0.0, uninstall, install; home, category, product and cart pages with an empty and a non-empty cart; language switch and currency switch (EUR → AED → EUR) with a non-empty cart.
  - PrestaShop **9.2.0** (PHP 8.3): upgrade from 1.0.0, uninstall, install; home, category, product and cart pages with an empty and a non-empty cart (that test shop has a single language and currency).
  - Not tested: PrestaShop 8.x, saving the back-office form, per-shop thresholds in multistore. `ps_versions_compliancy` therefore stops at 9.2.
- **Back-Office Configuration**: `HelperForm`, posted to the back office's tokenized module URL.
- **Prices**: formatted with `Context::getCurrentLocale()->formatPrice()` (`Tools::displayPrice()` no longer exists in PrestaShop 9); the threshold is converted to the visitor's currency before comparison.
- **Directory Security**: `index.php` in every subdirectory to block directory listing.
- **Smarty Templating**: escaped variables and domain-qualified translation strings.
- **1.1.0 changes**: 1.0.0 crashed every front page on PrestaShop 9.2 (`Tools::displayPrice()`) and crashed PrestaShop 1.7.8 on a language or currency switch with a non-empty cart (cart total computed in `actionCartSave`, which `FrontController::init()` triggers before the cart is in the context). 1.1.0 fixes both and its upgrade script drops the write-only `genisoft_threshold_log` table that 1.0.0 filled from that hook. Both 1.0.0 bugs passed every static check of this harness, which is why the Limitations section below matters.

---

## ⚡ Quick Start

```bash
# Clone the repository
git clone https://github.com/lefi0528/genisoft-prestashop-ai-harness.git
cd genisoft-prestashop-ai-harness

# Run the Hook Guard on a module
php tools/ps-hook-guard.php examples/genisoft_cartthreshold/

# Run the SQL AppSec Guard
php tools/ps-sql-guard.php examples/genisoft_cartthreshold/

# Run the Smarty Security Guard
node tools/ps-smarty-guard.js examples/genisoft_cartthreshold/views/templates/

# Audit a whole module: guard self-test, 4 guards, php -l, packaging checks
php tools/harness-auditor.php examples/genisoft_cartthreshold

# Check that the guards still catch every project starter and pass every solution
bash tests/fixtures.sh
```

---

## ⚠️ Limitations

- The SQL, multi-shop and Smarty guards match **regular expressions** on the source text. The hook guard reads PHP tokens (`token_get_all()`), so comments and strings are ignored, but it does not run the code. None of them follows data flow across files. A clean run is not proof that a module is free of SQL injection or XSS.
- **Where the hook guard finds hook names**: string literals passed to `registerHook()` (and `Hook::registerHook($module, 'name')`), and the lists that reach it within the same file: `[]` or `array()` literals, variables, class constants, properties (default value or `$this->hooks = [...]`), methods that `return` a list, `array_merge()`, and `foreach` loops over any of these. **Not checked, and not reported**: names built at runtime (`'display' . $this->name`), read from configuration or a request, defined in another file (a parent class, an interface constant) or passed through a callback (`array_map()`).
- **What the hook guard accepts**: the 1,301 hook names of `data/ps-hooks-reference.json`, rebuilt from the `ps_hook` tables (without the rows created by non-PrestaShop modules or themes) and from the `Hook::exec()` / hook dispatcher calls, `{hook h=…}` and `renderhook()` templates of PrestaShop 1.7.8.11, 8.2.8 and 9.2.0 (core, classic and hummingbird themes, PrestaShop's own modules; each entry gives its `source` and `versions`), plus the names the core builds at runtime, by pattern: `actionObject<Class>(Add|Update|Delete|Duplicate)(Before|After)`, `action<Form>FormBuilderModifier`, `action<Grid>Grid(Definition|QueryBuilder|Data|FilterForm|Presenter)Modifier`, `action(Before|After)(Create|Update)<Form>FormHandler`, `action<Name>Controller…(Before|After)`, `action<AdminController>(FormModifier|ListingFieldsModifier|ListingResultsModifier|OptionsModifier)` and `action<Kpi>KpiRowModifier`. A name that fits a pattern is accepted even if the class, grid or controller does not exist, and a catalog name is accepted even if the PrestaShop version the module targets does not execute it: the guard does not read `versions`.
- **Warnings, not errors**: the 82 legacy aliases of `ps_hook_alias` (`header`, `top`, `footer`, `paymentReturn`…), which PrestaShop still maps to the modern name given in the warning, and a hook name the module executes itself (`Hook::exec('name')`, `{hook h='name'}` or `renderhook('name')`), treated as the module's own custom hook. A custom hook executed only in a template is recognised when the module folder is scanned, not when its PHP file is scanned alone.
- **WordPress**: calls to `add_action()`, `add_filter()`, `remove_action()`, `remove_filter()`, `do_action()`, `apply_filters()` and any `wp_*()` or `woocommerce_*()` function are flagged, and so are the WordPress hook names `init`, `admin_init`, `save_post`, `the_content` and `wp_*` passed to `registerHook()`. Methods called `init()` or `admin_init()` are not flagged: a front controller's `init()` is standard PrestaShop code.
- **Measured on local installs of PrestaShop 1.7.8.11, 8.2.8 and 9.2.0**: the previous 669-name catalog rejected 41 % (1.7.8.11, 8.2.8) and 50 % (9.2.0) of the hooks registered by the installed modules; the current guard rejects none on 1.7.8.11 and 8.2.8, and on 9.2.0 only 5 hooks declared by a custom (non-PrestaShop) theme. Run on the 207 PrestaShop module folders shipped with those shops, it passes 204: the 3 others are `ps_emailsubscription`, which registers `displayAdminCustomersForm`, a hook that none of these core versions executes.
- **Where the SQL guard looks**: the arguments of `Db::getInstance()->execute()`, `executeS()`, `getValue()` and `getRow()` (also with `Db::getInstance(_PS_USE_SQL_SLAVE_)`), the assignments to a variable passed to those calls, and `$…sql…` assignments that contain `SELECT`, `INSERT`, `UPDATE` or `DELETE`. SQL built anywhere else is not checked: `DbQuery` methods such as `->where()`, the conditions of `Db::getInstance()->update()` / `delete()`, or a query written inline in `$db->executeS('…')` on a stored instance.
- **Request values** (`Tools::getValue()`, `Tools::getAllValues()`, `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`) are flagged when they follow the `.` operator, directly (`' WHERE ref = \'' . Tools::getValue('ref') . '\''`) or inside other calls (`. trim(Tools::getValue('ref'))`), unless `(int)`, `(float)`, `intval()`, `floatval()`, `pSQL()`, `bqSQL()` or `Db::getInstance()->escape()` wraps them there. **Not flagged**: a request value passed as a later argument (`implode(',', Tools::getValue('ids'))`) or placed first in a concatenation (`Tools::getValue('way') . ' DESC'`, `$sql .= Tools::getValue('way');`).
- An accepted wrapper is not checked against the SQL around it: `' WHERE id = ' . pSQL(Tools::getValue('id'))` passes, although a value outside quotes stays injectable after `pSQL()`; use `(int)` there.
- **False positives**: a variable cast earlier (`$id = (int) $id;`) and concatenated later is still flagged, and so is `. $db->escape($value)` (reported as the variable `$db`); SQL keywords in comments can trigger `SQL_MISSING_DB_PREFIX` warnings.
- The multi-shop check works per file: one `id_shop =` condition anywhere in the file satisfies every `_shop` join in it.
- Passing the harness says nothing about runtime compatibility: install and test the module on each PrestaShop version it declares.

---

## 👨‍💻 Author & Professional Services

Designed and maintained by **Lefi Abdelmonem** (Genisoft).  
Senior Technical Architect specializing in PrestaShop E-Commerce Engineering, Web Performance, and AppSec.

- 💼 **Agency Website**: [https://www.genisoft.fr/agence-web/](https://www.genisoft.fr/agence-web/)
- 🌐 **Portfolio & Case Studies**: [https://lefi-abdelmonem.com](https://lefi-abdelmonem.com)
- 🐙 **GitHub Profile**: [@lefi0528](https://github.com/lefi0528)

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
