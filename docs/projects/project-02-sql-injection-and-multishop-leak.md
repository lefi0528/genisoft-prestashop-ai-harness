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
- Every value cast to `(int)` or escaped with `pSQL()` where it is concatenated (the guard does not follow variables).
- Multi-store table joins constrained by `id_shop = (int)$this->context->shop->id`.
- The solution actually runs: `php tests/project-02-solution-smoke.php` executes both queries against PrestaShop stubs.
- CI (`tests/fixtures.sh`) checks that the SQL and context guards exit 1 on the starter and 0 on the solution.
