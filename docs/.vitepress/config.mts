import { defineConfig } from 'vitepress'

export default defineConfig({
  title: "Genisoft PrestaShop AI Harness",
  description: "Autonomous PrestaShop 8+ Module Engineering & AppSec Harness for AI Coding Agents",
  base: '/genisoft-prestashop-ai-harness/',
  head: [
    ['link', { rel: 'icon', href: '/favicon.ico' }],
    ['meta', { name: 'theme-color', content: '#0284c7' }]
  ],
  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Architecture', link: '/ARCHITECTURE' },
      { text: 'Lectures', link: '/lectures/' },
      { text: 'Projects', link: '/projects/' },
      { text: 'Threat Model', link: '/PRESTASHOP_THREAT_MODEL' }
    ],
    sidebar: {
      '/lectures/': [
        {
          text: 'Lectures Curriculum',
          items: [
            { text: 'Overview', link: '/lectures/' },
            { text: '01: Why AI Agents Fail on PrestaShop', link: '/lectures/lecture-01-why-ai-agents-fail-on-prestashop' },
            { text: '02: PrestaShop 8 Hybrid Architecture', link: '/lectures/lecture-02-prestashop-8-hybrid-architecture' },
            { text: '03: Hook Dispatch & Execution Lifecycle', link: '/lectures/lecture-03-hook-dispatch-and-execution-lifecycle' },
            { text: '04: AppSec SQL Sanitization: pSQL() vs (int)', link: '/lectures/lecture-04-appsec-sql-sanitization-psql-vs-int' },
            { text: '05: Multi-Shop & Multi-Lang Isolation', link: '/lectures/lecture-05-multishop-and-multilang-context-isolation' },
            { text: '06: Smarty Security & Escaping', link: '/lectures/lecture-06-smarty-security-escaping-and-compilation' },
            { text: '07: HelperForm vs Symfony Form Types', link: '/lectures/lecture-07-helperform-vs-symfony-forms-in-prestashop-8' },
            { text: '08: Verification Gates & Static Checks', link: '/lectures/lecture-08-verification-gates-and-ast-linting' },
            { text: '09: Performance & N+1 Query Elimination', link: '/lectures/lecture-09-performance-n-plus-one-queries-and-cache' },
            { text: '10: Autonomous Agent Prompt Protocol', link: '/lectures/lecture-10-autonomous-agent-prompt-harness-protocol' }
          ]
        }
      ],
      '/projects/': [
        {
          text: 'Hands-on Projects',
          items: [
            { text: 'Overview', link: '/projects/' },
            { text: 'Project 01: Hook Hallucination Repair', link: '/projects/project-01-hook-hallucination-repair' },
            { text: 'Project 02: SQL Injection & Multi-Shop Leak', link: '/projects/project-02-sql-injection-and-multishop-leak' },
            { text: 'Project 03: Smarty XSS & Translation Repair', link: '/projects/project-03-smarty-xss-and-translation-repair' },
            { text: 'Project 04: Full Module Audit', link: '/projects/project-04-full-module-certification' }
          ]
        }
      ]
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/lefi0528/genisoft-prestashop-ai-harness' }
    ],
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 Genisoft - Lefi Abdelmonem'
    }
  }
})
