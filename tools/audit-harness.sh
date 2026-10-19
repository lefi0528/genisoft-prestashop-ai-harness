#!/usr/bin/env bash
# ==========================================================
# Genisoft PrestaShop 8+ Harness: 100-Point Audit Runner
# ==========================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(dirname "$SCRIPT_DIR")"

echo "=========================================================="
echo "  GENISOFT PRESTASHOP 8+ HARNESS: 100-POINT AUDIT RUNNER"
echo "=========================================================="

cd "$REPO_DIR"

if [ ! -f "vendor/autoload.php" ]; then
    if command -v composer >/dev/null 2>&1; then
        echo "Generating Composer PSR-4 autoloader..."
        composer dump-autoload --optimize --no-interaction >/dev/null 2>&1 || true
    fi
fi

php "$SCRIPT_DIR/harness-auditor.php"
