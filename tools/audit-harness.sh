#!/usr/bin/env bash
# ==========================================================
# Genisoft PrestaShop Harness: module audit runner
# Usage: bash tools/audit-harness.sh <module-dir>
# Exit codes: 0 no blocking finding, 1 finding(s), 2 tool error.
# ==========================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "$#" -ne 1 ]; then
    echo "Usage: bash tools/audit-harness.sh <module-dir>" >&2
    exit 2
fi

exec php "$SCRIPT_DIR/harness-auditor.php" "$1"
