#!/usr/bin/env bash
# Runs the guards on the project starters (each must report findings: exit 1), on the
# solutions and the example module (each must be clean: exit 0), and on the SQL request-value
# fixtures in tests/fixtures/. Any other exit code, including 2 (tool error), fails the run.
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

P1=projects/project-01-hook-hallucination-repair
P2=projects/project-02-sql-injection-and-multishop-leak
P3=projects/project-03-smarty-xss-and-translation-repair
EXAMPLE=examples/genisoft_cartthreshold
failures=0
out=$(mktemp)
trap 'rm -f "$out"' EXIT

expect() {
    local expected=$1
    shift
    "$@" >"$out" 2>&1
    local code=$?
    if [[ "$code" -eq "$expected" ]]; then
        echo "ok   (exit $code) $*"
    else
        echo "FAIL (exit $code, expected $expected) $*"
        sed 's/^/     | /' "$out"
        failures=$((failures + 1))
    fi
}

# expect_findings <count> <text> <command...>: exit 1 with exactly <count> output lines containing <text>.
expect_findings() {
    local count=$1 text=$2
    shift 2
    "$@" >"$out" 2>&1
    local code=$? found
    found=$(grep -c -F -- "$text" "$out")
    if [[ "$code" -eq 1 && "$found" -eq "$count" ]]; then
        echo "ok   (exit 1, $found x '$text') $*"
    else
        echo "FAIL (exit $code, $found x '$text'; expected exit 1, $count) $*"
        sed 's/^/     | /' "$out"
        failures=$((failures + 1))
    fi
}

echo "--> Starters: the guards must catch the planted defects"
expect 1 php tools/ps-hook-guard.php "$P1/starter"
expect 1 php tools/ps-sql-guard.php "$P2/starter"
expect 1 php tools/ps-context-guard.php "$P2/starter"
expect 1 node tools/ps-smarty-guard.js "$P3/starter"
expect 1 php tools/harness-auditor.php "$P2/starter"

echo "--> Solutions: every applicable guard must pass"
for guard in hook sql context; do
    expect 0 php "tools/ps-$guard-guard.php" "$P1/solution"
    expect 0 php "tools/ps-$guard-guard.php" "$P2/solution"
done
expect 0 node tools/ps-smarty-guard.js "$P3/solution"

echo "--> Example module: every guard and the auditor must pass"
for guard in hook sql context; do
    expect 0 php "tools/ps-$guard-guard.php" "$EXAMPLE"
done
expect 0 node tools/ps-smarty-guard.js "$EXAMPLE"
expect 0 php tools/harness-auditor.php "$EXAMPLE"

echo "--> SQL guard: request values concatenated into SQL must be cast or escaped"
expect_findings 4 '[SQL_UNESCAPED_CONCAT] Request value' php tools/ps-sql-guard.php tests/fixtures/sql-request-concat/flagged.php
expect 0 php tools/ps-sql-guard.php tests/fixtures/sql-request-concat/sanitized.php

echo "--> Tool errors must exit 2, never 0"
expect 2 php tools/ps-sql-guard.php does-not-exist
expect 2 php tools/ps-hook-guard.php "$P3/starter"
expect 2 node tools/ps-smarty-guard.js tools
expect 2 php tools/harness-auditor.php does-not-exist

if [[ "$failures" -gt 0 ]]; then
    echo "$failures fixture check(s) failed."
    exit 1
fi
echo "All fixture checks passed."
