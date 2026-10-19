# Lecture 10: The Autonomous Agent Harness Protocol

## Structuring the AI Pair-Programming Lifecycle

To orchestrate autonomous coding agents (Claude Code, Cursor, Copilot, Antigravity) without human supervision, the repository embeds standard protocol files:

### Protocol Files in Repository Root
- **`AGENTS.md`**: Top-level instructions read by autonomous agents during session initialization.
- **`tools/audit-harness.sh`**: 100-point automated certification runner.
- **`data/ps-hooks-reference.json`**: Ground truth hook catalog.

### The 4-Step Autonomous Agent Workflow
1. **Orient**: Read `AGENTS.md` and inspect existing module structure.
2. **Implement**: Write modular code respecting PSR-12 and PrestaShop 8+ guidelines.
3. **Verify**: Run `tools/ps-hook-guard.php`, `tools/ps-sql-guard.php`, and `tools/ps-smarty-guard.js`.
4. **Certify**: Run `bash tools/audit-harness.sh` to ensure a 100/100 score before commit.
