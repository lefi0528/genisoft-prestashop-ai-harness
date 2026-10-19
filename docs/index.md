---
layout: home

hero:
  name: "Genisoft PrestaShop AI Harness"
  text: "Autonomous PrestaShop 8+ Module Engineering & AppSec Harness"
  tagline: "Empowering AI coding agents (Claude Code, Cursor, Copilot, Antigravity) to write bulletproof, multi-store, and secure PrestaShop 8+ modules with zero hallucinations."
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
    details: Scans module code against the 669 official PrestaShop 8+ hook catalog. Eliminates WordPress action bleed and phantom hooks.
  - title: 🔒 Zero-Trust SQL AppSec
    details: Static AST analysis enforcing pSQL() string sanitization, (int)/(float) ID casting, and mandatory _DB_PREFIX_ prefixing.
  - title: 🌐 Multi-Shop Isolation
    details: Prevents silent cross-store catalog and customer data leakage by enforcing strict Shop::getContext() boundaries.
  - title: ⚡ Smarty 3/4 Security Guard
    details: Automated XSS detection and validation of modern PrestaShop 8+ translation domains across all .tpl templates.
  - title: 🎓 10 In-Depth Engineering Lectures
    details: From hybrid legacy/Symfony architecture to high-load caching and N+1 query elimination.
  - title: 🧪 4 Hands-on Projects
    details: Real-world vulnerable starters and guarded solutions to train and certify autonomous AI coding agents.
---

## Why Harness Engineering for PrestaShop?

Modern Large Language Models possess exceptional general PHP knowledge, yet they fail consistently when generating code for PrestaShop. 

PrestaShop combines **legacy 1.7 architecture**, **Symfony 4.4/5.4/6.x DI containers**, **Smarty template compilation**, and a **multi-store relational schema**. Without an explicit engineering harness, autonomous AI agents:
1. Hallucinate hook names or invoke WordPress functions like `add_action()`.
2. Generate fatal SQL injections by concatenating raw variables inside `Db::getInstance()->executeS()`.
3. Forget `id_shop` filtering on `_shop` tables, corrupting multi-store catalog isolation.
4. Omit `|escape:'html':'UTF-8'` in Smarty templates, exposing stores to stored XSS attacks.

The **Genisoft PrestaShop AI Harness** establishes an automated, deterministic verification gate between AI code generation and production deployment.
