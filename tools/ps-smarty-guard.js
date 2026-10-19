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
    const phpTagMatches = content.matchAll(/\{php\}[\s\S]*?\{\/php\}/g);
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

// CLI handler
if (require.main === module) {
  const args = process.argv.slice(2);
  if (args.length === 0 || args.includes('-h') || args.includes('--help')) {
    console.log('Usage: node ps-smarty-guard.js <path-to-tpl-or-dir>');
    process.exit(0);
  }

  const target = args[0];
  const guard = new PsSmartyGuard();
  const files = [];

  function collectTplFiles(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        collectTplFiles(fullPath);
      } else if (entry.isFile() && entry.name.endsWith('.tpl')) {
        files.push(fullPath);
      }
    }
  }

  if (fs.existsSync(target)) {
    if (fs.statSync(target).isDirectory()) {
      collectTplFiles(target);
    } else {
      files.push(target);
    }
  } else {
    console.error(`Error: Path '${target}' does not exist.`);
    process.exit(1);
  }

  let hasErrors = false;
  console.log('=== PrestaShop Smarty Security & Standards Guard ===');
  console.log(`Scanning ${files.length} template file(s)...\n`);

  files.forEach(file => {
    const issues = guard.validateFile(file);
    if (issues.length > 0) {
      console.log(`File: ${file}`);
      issues.forEach(issue => {
        console.log(`  Line ${issue.line}: [${issue.severity}] [${issue.category}] ${issue.message}`);
        if (issue.severity === 'ERROR' || issue.severity === 'CRITICAL') {
          hasErrors = true;
        }
      });
      console.log();
    }
  });

  if (hasErrors) {
    console.log('FAILED: Critical Smarty security violations detected.');
    process.exit(1);
  }

  console.log('PASSED: Smarty templates comply with PrestaShop 8+ XSS escaping guidelines.');
  process.exit(0);
}

module.exports = { PsSmartyGuard };
