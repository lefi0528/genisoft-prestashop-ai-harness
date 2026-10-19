# Project 02: SQL Injection & Multi-Shop Leak

## Objective
Remediate critical SQL injection flaws and multi-store catalog leaks in `projects/project-02-sql-injection-and-multishop-leak/starter/leaky-query-module.php`.

## Vulnerabilities in Starter
- Raw concatenation of `$id_customer` and search query string without `(int)` or `pSQL()`.
- Joining `_shop` tables without filtering by `id_shop`.

## Verification Command
```bash
php tools/ps-sql-guard.php projects/project-02-sql-injection-and-multishop-leak/solution/hardened-query-module.php
```

## Pass Criteria
- All variable inputs strictly cast to `(int)` or sanitized with `pSQL()`.
- Multi-store table joins strictly constrained by `id_shop = (int)$this->context->shop->id`.
