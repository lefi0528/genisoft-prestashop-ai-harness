---
layout: home

hero:
  name: "Genisoft PrestaShop AI Harness"
  text: "Autonomous PrestaShop 8+ Module Engineering & AppSec Harness"
  tagline: "Pattern-based checks that help AI coding agents (Claude Code, Cursor, Copilot, Antigravity) avoid the most common PrestaShop mistakes: hallucinated hooks, SQL injection, multi-store leaks and Smarty XSS."
  actions:
    - theme: brand
      text: Get Started with Lectures
      link: /lectures/
    - theme: alt
      text: Explore Hands-on Projects
      link: /projects/
    - theme: alt
      text: View on GitHub
      link: https://github.com/lefi0528/genisoft-prestashop-ai-harness

features:
  - title: 🛡️ Anti-Hallucination Hook Guard
    details: Checks registered hooks and hook methods against 1,301 hook names taken from PrestaShop 1.7.8, 8.2 and 9.2, plus the names the core builds at runtime. Flags WordPress calls and unknown hook names; warns on legacy aliases.
  - title: 🔒 Zero-Trust SQL AppSec
    details: Regex-based checks that flag variables concatenated into SQL without pSQL() or an (int)/(float) cast, and table names without _DB_PREFIX_.
  - title: 🌐 Multi-Shop Isolation
    details: Flags product_shop / category_shop joins in files that never filter on id_shop, a common source of cross-store data leaks.
  - title: ⚡ Smarty 3/4 Security Guard
    details: Automated XSS detection and validation of modern PrestaShop 8+ translation domains across all .tpl templates.
  - title: 🎓 10 In-Depth Engineering Lectures
    details: From hybrid legacy/Symfony architecture to high-load caching and N+1 query elimination.
  - title: 🧪 4 Hands-on Projects
    details: Vulnerable starters and guarded solutions to train and evaluate AI coding agents. CI checks that the guards fail every starter and pass every solution.
---

## Why Harness Engineering for PrestaShop?

Modern Large Language Models possess exceptional general PHP knowledge, yet they fail consistently when generating code for PrestaShop. 

PrestaShop combines **legacy 1.7 architecture**, **Symfony 4.4/5.4/6.x DI containers**, **Smarty template compilation**, and a **multi-store relational schema**. Without an explicit engineering harness, autonomous AI agents:
1. Hallucinate hook names or invoke WordPress functions like `add_action()`.
2. Generate fatal SQL injections by concatenating raw variables inside `Db::getInstance()->executeS()`.
3. Forget `id_shop` filtering on `_shop` tables, corrupting multi-store catalog isolation.
4. Omit `|escape:'html':'UTF-8'` in Smarty templates, exposing stores to stored XSS attacks.

The **Genisoft PrestaShop AI Harness** establishes an automated, deterministic first gate between AI code generation and human review. It catches common mistakes; it does not replace code review or testing on the PrestaShop versions a module targets.
