#!/usr/bin/env bash
set -euo pipefail

echo "=========================================================="
echo "  GENISOFT PRESTASHOP 8+ HARNESS ENGINEERING AUDITOR"
echo "=========================================================="

SCORE=100
FAILURES=0

record_failure() {
  local points="$1"
  local reason="$2"
  SCORE=$((SCORE - points))
  FAILURES=$((FAILURES + 1))
  echo "  ❌ (-${points} pts) ${reason}"
}

echo "--> Check 1: Composer & PSR-4 Autoloading validation..."
if [ -f composer.json ]; then
  if ! composer validate --strict >/dev/null 2>&1; then
    record_failure 10 "composer.json is invalid"
  else
    echo "  ✅ composer.json strictly valid"
  fi
else
  record_failure 20 "composer.json is missing"
fi

echo "--> Check 2: PrestaShop Hook Guard verification..."
if [ -f tools/PsHookGuard.php ] && [ -f data/ps-hooks-reference.json ]; then
  # Verify exemplary module passes with 0 errors
  if php tools/ps-hook-guard.php examples/genisoft_cartthreshold/genisoft_cartthreshold.php >/dev/null 2>&1; then
    echo "  ✅ Exemplary module hooks verified"
  else
    record_failure 15 "Exemplary module failed hook verification"
  fi

  # Verify starter fixture is detected as vulnerable
  if php tools/ps-hook-guard.php projects/project-01-hook-hallucination-repair/starter/vulnerable-module.php >/dev/null 2>&1; then
    record_failure 15 "Hook Guard failed to detect vulnerable starter"
  else
    echo "  ✅ Hook Guard successfully intercepts hallucinated starter"
  fi
else
  record_failure 20 "Hook Guard or catalog missing"
fi

echo "--> Check 3: SQL Injection AppSec Guard verification..."
if [ -f tools/PsSqlGuard.php ]; then
  # Verify exemplary module passes
  if php tools/ps-sql-guard.php examples/genisoft_cartthreshold/genisoft_cartthreshold.php >/dev/null 2>&1; then
    echo "  ✅ Exemplary module SQL queries verified"
  else
    record_failure 15 "Exemplary module failed SQL verification"
  fi

  # Verify starter fixture is detected as vulnerable
  if php tools/ps-sql-guard.php projects/project-02-sql-injection-and-multishop-leak/starter/leaky-query-module.php >/dev/null 2>&1; then
    record_failure 15 "SQL Guard failed to detect raw query concatenation"
  else
    echo "  ✅ SQL Guard successfully intercepts raw query concatenation"
  fi
else
  record_failure 20 "SQL Guard missing"
fi

echo "--> Check 4: Smarty Security Guard verification..."
if [ -f tools/ps-smarty-guard.js ]; then
  # Verify exemplary template passes
  if node tools/ps-smarty-guard.js examples/genisoft_cartthreshold/views/templates/hook/cart_threshold_bar.tpl >/dev/null 2>&1; then
    echo "  ✅ Exemplary Smarty template verified"
  else
    record_failure 15 "Exemplary template failed Smarty verification"
  fi

  # Verify starter fixture is detected
  if node tools/ps-smarty-guard.js projects/project-03-smarty-xss-and-translation-repair/starter/insecure-template.tpl >/dev/null 2>&1; then
    record_failure 15 "Smarty Guard failed to detect unescaped template"
  else
    echo "  ✅ Smarty Guard successfully intercepts insecure template"
  fi
else
  record_failure 15 "Smarty Guard missing"
fi

echo "--> Check 5: Multi-Shop Context Guard verification..."
if [ -f tools/PsContextGuard.php ]; then
  if php tools/ps-context-guard.php examples/genisoft_cartthreshold/genisoft_cartthreshold.php >/dev/null 2>&1; then
    echo "  ✅ Exemplary module context isolation verified"
  else
    record_failure 15 "Exemplary module failed context verification"
  fi
else
  record_failure 15 "Context Guard missing"
fi

echo "--> Check 6: PrestaShop Module Standards & Assets..."
if [ -f examples/genisoft_cartthreshold/logo.png ] && [ -f examples/genisoft_cartthreshold/config.xml ]; then
  echo "  ✅ Mandatory logo.png and config.xml present"
else
  record_failure 10 "Missing logo.png or config.xml in exemplary module"
fi

echo "=========================================================="
echo "  AUDIT FINISHED: Final Score: ${SCORE}/100"
echo "=========================================================="

if [ "$SCORE" -lt 85 ]; then
  echo "❌ Harness certification failed (Score: ${SCORE}/100)."
  exit 1
fi

echo "✅ Harness certified by Genisoft AppSec Standards (Score: ${SCORE}/100)."
exit 0
