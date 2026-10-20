# Project 03: Smarty XSS & Translation Repair

## Objective
Remediate template-level security vulnerabilities in `projects/project-03-smarty-xss-and-translation-repair/starter/insecure-template.tpl`.

## Vulnerabilities in Starter
- Unescaped variable outputs: `{$customer_name}`, `{$custom_banner}`.
- Deprecated `{php}` block.
- Missing PrestaShop 8 translation domain: `{l s='Update Profile'}`.

## Verification Command
```bash
node tools/ps-smarty-guard.js projects/project-03-smarty-xss-and-translation-repair/solution/hardened-template.tpl
```

## Pass Criteria
- Every rendered variable escaped with `|escape:'html':'UTF-8'` (or another escaping modifier).
- 0 `{php}` tags.
- Domain-qualified translation calls (`d='Modules.MyModule.Shop'`).
