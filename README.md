# 🛡️ Genisoft PrestaShop AI Harness

[![CI & Security Matrix](https://github.com/lefi0528/genisoft-prestashop-ai-harness/actions/workflows/test-harness.yml/badge.svg)](https://github.com/lefi0528/genisoft-prestashop-ai-harness/actions/workflows/test-harness.yml)
[![Documentation](https://img.shields.io/badge/Docs-VitePress-0284c7?logo=vitepress)](https://lefi0528.github.io/genisoft-prestashop-ai-harness/)
[![PrestaShop](https://img.shields.io/badge/PrestaShop-8.0%2B%20%7C%201.7.8-df0067?logo=prestashop)](https://www.prestashop.com/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4?logo=php)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-emerald.svg)](LICENSE)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=lefi0528_genisoft-prestashop-ai-harness&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=lefi0528_genisoft-prestashop-ai-harness)

> **Autonomous PrestaShop 8+ Module Engineering & AppSec Harness for AI Coding Agents**  
> Empowers modern LLM coding agents (Claude Code, Cursor, Copilot, Antigravity) to design, build, audit, and refactor PrestaShop modules with **static hook hallucination prevention**, **strict AppSec SQL sanitization**, and **multi-store context isolation**.

---

## 🌟 Why PrestaShop Needs an AI Engineering Harness

Foundation models (Claude Opus, GPT-4o, DeepSeek R1) are trained overwhelmingly on standard generic PHP frameworks (Laravel, Symfony) and WordPress. While PrestaShop 8 integrates Symfony components into its Core Back-Office, **module development remains a unique hybrid ecosystem**:

1. **Hook Hallucination**: AI agents frequently inject WordPress functions (`add_action`, `add_filter`) or invent phantom hooks (`actionProductAdd`), leading to silent logic failure.
2. **SQL Injection Vulnerabilities**: PrestaShop modules query data through `Db::getInstance()`. Naive AI models concatenate raw variables, causing critical SQL injection vectors.
3. **Multi-Store Isolation Leaks**: Failing to filter by `id_shop` when joining `_shop` tables leaks customer data and catalog pricing between separate storefronts.
4. **Smarty XSS Vectors**: Missing `|escape:'html':'UTF-8'` in Smarty `.tpl` templates introduces reflected and stored Cross-Site Scripting flaws.

The **Genisoft PrestaShop AI Harness** provides an automated, deterministic verification gate between AI code generation and production deployment.

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
  |-- Gate 1: Hook Catalog Validator (669 Official Hooks)   |
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
| Production Deployment Ready   |   | Automated Agent Feedback Loop |
| Zero-Regression Guarantee     |   | Actionable Error Diagnostics  |
+-------------------------------+   +-------------------------------+
```

### The 4 Verification Tools

| Tool | Language | Purpose & Enforcement |
| :--- | :--- | :--- |
| **`tools/PsHookGuard.php`** (`ps-hook-guard.php`) | PHP 8.1+ (PSR-4) | Validates all registered hooks against the **669 official PrestaShop 8+ hooks catalog**. Eliminates WordPress action bleed and suggests corrections via Levenshtein distance. |
| **`tools/PsSqlGuard.php`** (`ps-sql-guard.php`) | PHP 8.1+ (PSR-4) | Static AST scanner enforcing mandatory `pSQL()` string sanitization, `(int)` numerical casting, and `_DB_PREFIX_` prefixing. |
| **`tools/ps-smarty-guard.js`** | Node.js | Smarty 3/4 linter detecting unescaped template variables, obsolete `{php}` blocks, and enforcing PrestaShop 8 translation domains (`d='Modules...'`). |
| **`tools/PsContextGuard.php`** (`ps-context-guard.php`) | PHP 8.1+ (PSR-4) | Verifies multi-shop context (`Shop::getContext()`, `id_shop`) across database joins and configuration updates. |
| **`tools/audit-harness.sh`** | Bash | 100-point repository auditor executing all verification gates and certifying module integrity. |

---

## 📚 10-Lecture Advanced Engineering Curriculum

Our interactive documentation provides 10 in-depth lectures covering every facet of PrestaShop module engineering:

1. [Lecture 01: Why AI Agents Fail on PrestaShop](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-01-why-ai-agents-fail-on-prestashop)
2. [Lecture 02: PrestaShop 8 Hybrid Architecture: Legacy Core vs Symfony](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-02-prestashop-8-hybrid-architecture)
3. [Lecture 03: The Hook Dispatch & Execution Lifecycle (669 Hooks Decoded)](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-03-hook-dispatch-and-execution-lifecycle)
4. [Lecture 04: Offensive AppSec: SQL Injection Prevention (pSQL vs int)](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-04-appsec-sql-sanitization-psql-vs-int)
5. [Lecture 05: The Silent Killer: Multi-Shop & Multi-Lang Context Leaks](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-05-multishop-and-multilang-context-isolation)
6. [Lecture 06: Smarty Template Security: XSS Prevention & Compilation](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-06-smarty-security-escaping-and-compilation)
7. [Lecture 07: Back-Office Forms: HelperForm vs Symfony Form Types](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-07-helperform-vs-symfony-forms-in-prestashop-8)
8. [Lecture 08: Verification Gates: Static AST Analysis & Scaffolding](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-08-verification-gates-and-ast-linting)
9. [Lecture 09: E-Commerce Performance: N+1 Query Elimination & Object Cache](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-09-performance-n-plus-one-queries-and-cache)
10. [Lecture 10: The Autonomous Agent Harness Protocol (AGENTS.md)](https://lefi0528.github.io/genisoft-prestashop-ai-harness/lectures/lecture-10-autonomous-agent-prompt-harness-protocol)

---

## 🧪 Hands-On Projects

| Project | Challenge | Skills Demonstrated |
| :--- | :--- | :--- |
| **[Project 01](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-01-hook-hallucination-repair)** | Hook Hallucination Repair | Replace WordPress actions and phantom hooks with valid PrestaShop 8+ hook signatures. |
| **[Project 02](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-02-sql-injection-and-multishop-leak)** | SQL Injection & Multi-Shop Leak | Sanitize vulnerable database queries with `(int)` and `pSQL()`; secure `id_shop` constraints. |
| **[Project 03](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-03-smarty-xss-and-translation-repair)** | Smarty XSS & Translation Domain | Neutralize template XSS vectors and update to modern PrestaShop 8 translation domains. |
| **[Project 04](https://lefi0528.github.io/genisoft-prestashop-ai-harness/projects/project-04-full-module-certification)** | Full Module Certification | Execute end-to-end verification and score 100/100 on the automated harness auditor. |

---

## 🚀 Exemplary Production Module: `genisoft_cartthreshold`

The repository includes a reference implementation of a complete, production-ready module in [`examples/genisoft_cartthreshold/`](examples/genisoft_cartthreshold/):
- **PrestaShop Compatibility**: Tested on PrestaShop 8.0, 8.1, 8.2 and 1.7.8.
- **Back-Office Configuration**: Built with `HelperForm` with automatic CSRF token protection and switch toggles.
- **Database Schema**: Hardened `install.sql` and `uninstall.sql` using `_DB_PREFIX_` and `_MYSQL_ENGINE_`.
- **Directory Security**: Standard `index.php` in every subdirectory preventing path disclosure.
- **Smarty Templating**: 100% escaped variables and modern translation domain formatting.

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

# Run the Full 100-Point Audit
bash tools/audit-harness.sh
```

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
