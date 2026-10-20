# Lecture 10: The Autonomous Agent Harness Protocol

## Structuring the AI Pair-Programming Lifecycle

To orchestrate autonomous coding agents (Claude Code, Cursor, Copilot, Antigravity) without human supervision, the repository embeds standard protocol files:

### Protocol Files in Repository Root
- **`AGENTS.md`**: Top-level instructions read by autonomous agents during session initialization.
- **`tools/harness-auditor.php`** (or `tools/audit-harness.sh`): module auditor that runs every guard on a module directory.
- **`data/ps-hooks-reference.json`**: Reference hook catalog (669 hook names).

### The 4-Step Autonomous Agent Workflow
1. **Orient**: Read `AGENTS.md` and inspect existing module structure.
2. **Implement**: Write modular code respecting PSR-12 and PrestaShop 8+ guidelines.
3. **Verify**: Run `tools/ps-hook-guard.php`, `tools/ps-sql-guard.php`, and `tools/ps-smarty-guard.js`.
4. **Audit**: Run `php tools/harness-auditor.php <module-dir>` and fix every blocking finding (exit code 1) before commit. Exit code 2 means the audit did not run: fix the environment, do not treat it as a pass.
5. **Test**: Install the module on each PrestaShop version it declares and load the pages its hooks touch. Static checks cannot see runtime errors such as a removed core method.
