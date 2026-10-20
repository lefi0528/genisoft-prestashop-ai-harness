#!/usr/bin/env node
/**
 * PrestaShop 8+ Smarty Security & Modern Standards Guard
 *
 * Enforces XSS escaping, flags unsafe nofilter bypasses, and validates translation domains.
 */

const fs = require('fs');
const path = require('path');

class PsSmartyGuard {
  constructor() {
    this.safeModifiers = [
      'escape', 'intval', 'floatval', 'cleanHtml',
      'date_format', 'string_format', 'count', 'json_encode', 'number_format'
    ];
  }

  validateFile(filePath) {
    if (!fs.existsSync(filePath)) {
      return [{ severity: 'ERROR', category: 'FILE_NOT_FOUND', message: `File not found: ${filePath}` }];
    }

    const content = fs.readFileSync(filePath, 'utf-8');
    const issues = [];

    // 1. Check for dangerous 'nofilter' bypass
    const nofilterMatches = content.matchAll(/\{\$([a-zA-Z0-9_.]+)[^}]*\bnofilter\b[^}]*\}/g);
    for (const match of nofilterMatches) {
      issues.push({
        severity: 'CRITICAL',
        category: 'SMARTY_NOFILTER_BYPASS',
        line: this.getLineNumber(content, match.index),
        message: `Detected 'nofilter' on variable {\$${match[1]}}. Disabling Smarty escaping introduces stored/reflected XSS risks.`
      });
    }

    // 2. Check for deprecated {php}...{/php} blocks
    const phpTagMatches = content.matchAll(/\{\/?php\}/g);
    for (const match of phpTagMatches) {
      issues.push({
        severity: 'CRITICAL',
        category: 'SMARTY_PHP_TAG',
        line: this.getLineNumber(content, match.index),
        message: 'Deprecated {php} block detected. Strictly prohibited in PrestaShop 8 and Smarty 3/4.'
      });
    }

    // 3. Check for raw unescaped variable output: {$my_var}
    const varMatches = content.matchAll(/\{\$([a-zA-Z0-9_.]+)([^}]*)\}/g);
    for (const match of varMatches) {
      const varName = match[1];
      const tail = match[2].trim();

      // Skip assignments {$var = ...}, increment {$var++}, logic, or already flagged nofilter
      if (tail.startsWith('=') || tail.startsWith('++') || tail.startsWith('--') || tail.includes('nofilter')) {
        continue;
      }

      // Check if tail has any safe modifier
      const isSafe = this.safeModifiers.some(mod => tail.includes(`|${mod}`));
      if (!isSafe) {
        issues.push({
          severity: 'WARNING',
          category: 'SMARTY_UNESCAPED_OUTPUT',
          line: this.getLineNumber(content, match.index),
          message: `Unescaped output {\$${varName}}. Add '|escape:\'html\':\'UTF-8\'' or appropriate modifier to prevent XSS.`
        });
      }
    }

    // 4. Check for legacy translation format {l s='Text'} without d='Modules...'
    const transMatches = content.matchAll(/\{l\s+s=['"](.*?)['"](.*?)\}/g);
    for (const tm of transMatches) {
      const tail = tm[2];
      if (!tail.includes('d=')) {
        issues.push({
          severity: 'INFO',
          category: 'SMARTY_LEGACY_TRANSLATION',
          line: this.getLineNumber(content, tm.index),
          message: `Legacy translation {l s='${tm[1]}'}. In PrestaShop 8+, specify domain: d='Modules.ModuleName.Admin'.`
        });
      }
    }

    return issues;
  }

  getLineNumber(content, charIndex) {
    return content.substring(0, charIndex).split('\n').length;
  }
}

// CLI handler. Exit codes match the PHP guards: 0 no ERROR/CRITICAL finding,
// 1 ERROR/CRITICAL finding(s), 2 tool error (bad arguments, missing path, no template found).
const EXIT_CLEAN = 0;
const EXIT_FINDINGS = 1;
const EXIT_TOOL_ERROR = 2;
const SKIPPED_DIRS = ['vendor', 'node_modules', '.git'];

if (require.main === module) {
  const args = process.argv.slice(2);
  const usage = 'Usage: node tools/ps-smarty-guard.js [--json] <path-to-tpl-or-dir>\n'
    + `Scans .tpl files recursively (skipping ${SKIPPED_DIRS.join('/, ')}/).\n`
    + 'Exit codes: 0 no blocking finding, 1 ERROR/CRITICAL finding(s), 2 tool error.';
  if (args.includes('-h') || args.includes('--help')) {
    console.log(usage);
    process.exit(EXIT_CLEAN);
  }

  const json = args.includes('--json');
  const targets = args.filter(arg => arg !== '--json');
  if (targets.length !== 1) {
    console.error(usage);
    process.exit(EXIT_TOOL_ERROR);
  }

  const target = targets[0];
  const guard = new PsSmartyGuard();
  const files = [];

  function collectTplFiles(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        if (!SKIPPED_DIRS.includes(entry.name)) {
          collectTplFiles(fullPath);
        }
      } else if (entry.isFile() && entry.name.toLowerCase().endsWith('.tpl')) {
        files.push(fullPath);
      }
    }
  }

  try {
    if (!fs.existsSync(target)) {
      throw new Error(`Path '${target}' does not exist.`);
    }
    if (fs.statSync(target).isDirectory()) {
      collectTplFiles(target);
    } else {
      files.push(target);
    }
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(EXIT_TOOL_ERROR);
  }
  files.sort((a, b) => a.localeCompare(b));

  if (files.length === 0) {
    console.error(`Error: no .tpl file found under '${target}'. Nothing was scanned.`);
    process.exit(EXIT_TOOL_ERROR);
  }

  const findings = [];
  files.forEach(file => {
    guard.validateFile(file).forEach(issue => findings.push({ file, ...issue }));
  });
  const blocking = findings.filter(issue => issue.severity === 'ERROR' || issue.severity === 'CRITICAL').length;
  const exitCode = blocking > 0 ? EXIT_FINDINGS : EXIT_CLEAN;

  if (json) {
    console.log(JSON.stringify({ files_scanned: files.length, issues: findings }));
    process.exit(exitCode);
  }

  console.log('=== PrestaShop Smarty Security & Standards Guard ===');
  console.log(`Scanning ${files.length} template file(s)...\n`);
  let currentFile = null;
  findings.forEach(issue => {
    if (issue.file !== currentFile) {
      if (currentFile !== null) {
        console.log();
      }
      currentFile = issue.file;
      console.log(`File: ${issue.file}`);
    }
    console.log(`  Line ${issue.line}: [${issue.severity}] [${issue.category}] ${issue.message}`);
  });
  if (currentFile !== null) {
    console.log();
  }

  const summary = `${blocking} blocking (ERROR/CRITICAL), ${findings.length - blocking} non-blocking (WARNING/INFO) finding(s) in ${files.length} template(s).`;
  if (exitCode === EXIT_FINDINGS) {
    console.log(`FAILED: ${summary}`);
  } else {
    console.log(`PASSED: ${summary} Pattern-based checks only; this is not a proof of absence of XSS.`);
  }
  process.exit(exitCode);
}

module.exports = { PsSmartyGuard };
