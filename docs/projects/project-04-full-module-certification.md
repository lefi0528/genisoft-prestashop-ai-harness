# Project 04: Full Module Certification

## Objective
Conduct an exhaustive, end-to-end security and architecture audit of a production-ready PrestaShop 8+ module.

## Target Module
Examine the exemplary module provided in `examples/genisoft_cartthreshold/`.

## Verification Commands
```bash
# 1. Validate Hooks
php tools/ps-hook-guard.php examples/genisoft_cartthreshold/

# 2. Validate SQL AppSec
php tools/ps-sql-guard.php examples/genisoft_cartthreshold/

# 3. Validate Smarty Templates
node tools/ps-smarty-guard.js examples/genisoft_cartthreshold/views/templates/

# 4. Run Full Harness Audit
bash tools/audit-harness.sh
```

## Pass Criteria
- Harness Audit Score: **100/100 Certified**.
