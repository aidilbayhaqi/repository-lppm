#!/usr/bin/env node
/** OSDJ-LPPM release safety gate. Node 22+, no dependencies, read-only. */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const REQUIRED_IGNORE = [
  '.env', '.env.*', 'vendor', 'node_modules',
  'storage/app/**', 'storage/framework/**', 'deploy/secrets', 'deploy/tls',
  '.lppm-updater-backups', '*.zip', '*.sql', '*.pem', '*.key',
];

const ALLOWED_ENV_EXAMPLES = new Set([
  '.env.example', '.env.production.example', '.env.docker.example', '.env.testing.example',
]);

/** Classify a distribution entry by path only; never inspect/print secret contents. */
export function classifyPath(untrustedPath) {
  if (typeof untrustedPath !== 'string' || untrustedPath.includes('\0')) return 'invalid-path';
  const p = untrustedPath.replaceAll('\\', '/').replace(/^\.\//, '');
  if (/^(?:\/|[A-Za-z]:\/)/.test(p)) return 'invalid-path';
  const parts = p.split('/');
  if (parts.some(part => part === '..' || part === '')) return 'invalid-path';
  if (parts.at(-1) === '') return null;
  const lower = parts.map(part => part.toLowerCase());
  const base = lower.at(-1);
  if (lower.includes('.git')) return 'version-control';
  if (lower.includes('vendor') || lower.includes('node_modules')) return 'dependencies';
  if (lower.some(x => x === '.lppm-updater-backups' || x.startsWith('.lppm-updater-backups-') || x.startsWith('update-osdj-') || x.startsWith('fix-osdj-') || x.startsWith('rollback-osdj-'))) return 'updater-or-backup';
  if (lower.some((x,i)=>x === 'deploy' && ['secrets','tls'].includes(lower[i+1]))) return 'deployment-secret';
  if (base === '.env' || (base.startsWith('.env.') && !ALLOWED_ENV_EXAMPLES.has(base))) return 'environment-secret';
  if (lower.some((x,i)=> x === 'storage' && ['app','logs','framework'].includes(lower[i+1]))) return 'runtime-data';
  if (lower.some((x,i)=> x === 'public' && ['storage','hot'].includes(lower[i+1]))) return 'runtime-data';
  if (lower.some((x,i)=> x === 'database' && /\.(sqlite|sqlite3|db)(?:-.+)?$/.test(lower[i+1] || ''))) return 'runtime-database';
  if (/\.(?:zip|7z|rar|tar|gz|sql|sqlite|sqlite3|db|bak|backup|pem|p12|pfx|key|log)$/.test(base)) return 'sensitive-or-nested-artifact';
  if (/^(?:auth\.json|id_rsa|id_ed25519)$/.test(base)) return 'credentials';
  return null;
}

/** Assert that Docker COPY . . has the expected exclusion defenses. */
export function auditDockerIgnore(content) {
  const lines = content.split(/\r?\n/).map(x => x.trim()).filter(x => x && !x.startsWith('#'));
  const exclusions = new Set(lines.filter(x => !x.startsWith('!')));
  const missing = REQUIRED_IGNORE.filter(rule => !exclusions.has(rule));
  // Allow only specific public env templates and repository placeholder .gitignore entries.
  // Broad negation rules would silently reintroduce secrets into Docker COPY . .
  const safeNegations = new Set([
    '!.env.example', '!.env.production.example', '!.env.docker.example',
    '!**/.env.example', '!**/.env.production.example',
    '!**/.env.docker.example', '!**/.env.testing.example',
    '!storage/app/.gitignore', '!storage/app/private/.gitignore',
  ]);
  for (const rule of lines) {
    if (rule.startsWith('!') && !safeNegations.has(rule)) missing.push(`unsafe-negation:${rule}`);
  }
  return missing;
}

/** Read the ZIP central directory (without decompressing or opening contents). */
export function zipEntryNames(filePath) {
  const data = fs.readFileSync(filePath);
  const min = Math.max(0, data.length - 22 - 0xffff);
  let eocd = -1;
  for (let i = data.length - 22; i >= min; i--) {
    if (data.readUInt32LE(i) === 0x06054b50 && i + 22 + data.readUInt16LE(i + 20) === data.length) {eocd = i; break;}
  }
  if (eocd < 0) throw new Error('ZIP EOCD tidak ditemukan (arsip rusak/tidak didukung).');
  const count = data.readUInt16LE(eocd + 10);
  const directorySize = data.readUInt32LE(eocd + 12);
  let cursor = data.readUInt32LE(eocd + 16);
  if (count === 0xffff || cursor === 0xffffffff) throw new Error('ZIP64 perlu alat inspeksi terpisah; rilis ditolak.');
  if (cursor + directorySize > eocd) throw new Error('Central directory ZIP tidak valid.');
  const entries = [];
  for (let i=0; i<count; i++) {
    if (cursor + 46 > data.length || data.readUInt32LE(cursor) !== 0x02014b50) throw new Error('ZIP central directory rusak.');
    const nameLength = data.readUInt16LE(cursor + 28);
    const extraLength = data.readUInt16LE(cursor + 30);
    const commentLength = data.readUInt16LE(cursor + 32);
    const next = cursor + 46 + nameLength + extraLength + commentLength;
    if (next > data.length) throw new Error('ZIP entry rusak.');
    entries.push(data.subarray(cursor + 46, cursor + 46 + nameLength).toString('utf8'));
    cursor = next;
  }
  if (cursor !== data.readUInt32LE(eocd+16) + directorySize) throw new Error('Ukuran central directory tidak konsisten.');
  return entries;
}

export function auditEntries(entries) {
  const reasons = new Map();
  for (const entry of entries) {
    if (entry.endsWith('/')) continue;
    const issue = classifyPath(entry);
    if (issue) reasons.set(issue, (reasons.get(issue) || 0) + 1);
  }
  return reasons;
}

function printReasons(reasons) {
  for (const [reason, count] of [...reasons].sort()) {
    process.stderr.write(` - ${reason}: ${count} file\n`);
  }
}


/**
 * Reject tracked private runtime files. .gitignore alone cannot protect files
 * that were committed earlier, so enforce this on the Git index in CI.
 */
export function auditTrackedFiles(paths) {
  const allowed = new Set([
    '.env.example',
    '.env.production.example',
    '.env.docker.example',
    '.env.testing.example',
  ]);
  return paths.filter(p => {
    const normal = p.replaceAll('\\', '/');
    const base = normal.split('/').at(-1);
    if (normal === 'auth.json' || /(?:^|\/)deploy\/secrets\//i.test(normal)) return true;
    return base === '.env' || (base.startsWith('.env.') && !allowed.has(base));
  });
}



/**
 * Detect a narrow set of high-confidence active secrets in tracked text.
 * The output is a category, NEVER the matched value or line content.
 * This scans only the current checkout; it cannot remediate Git history.
 */
export function detectEmbeddedSecrets(content) {
  if (typeof content !== 'string') return [];
  const issues = [];
  if (/^[ \t]*APP_KEY[ \t]*=[ \t]*(?:base64:[A-Za-z0-9+/]{43}=?|[A-Za-z0-9]{32})[ \t]*$/gm.test(content)) {
    issues.push('laravel-app-key');
  }
  if (/-----BEGIN[ \t]+(?:RSA[ \t]+|EC[ \t]+|OPENSSH[ \t]+|ENCRYPTED[ \t]+)?PRIVATE[ \t]+KEY-----/.test(content)) {
    issues.push('private-key');
  }
  if (/\b(?:AKIA|ASIA)[A-Z0-9]{16}\b/.test(content)) {
    issues.push('aws-access-key-id');
  }
  if (/\b(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{50,})\b/.test(content)) {
    issues.push('github-token');
  }
  return issues;
}

const TEXT_SUFFIXES = /\.(?:php|blade\.php|js|mjs|cjs|ts|tsx|jsx|json|xml|ya?ml|md|txt|toml|ini|sh|ps1|py|sql|css|html|env\.example)$/i;
const TEXT_BASENAMES = new Set(['Dockerfile', '.gitignore', '.dockerignore', '.npmrc', '.gitattributes']);

/** Uses the Git index, not a glob that could accidentally scan local .env. */
export function auditTrackedSecretContent(root, files) {
  const findings = [];
  for (const name of files) {
    if (!TEXT_SUFFIXES.test(name) && !TEXT_BASENAMES.has(path.basename(name))) continue;
    const full = path.join(root, name);
    if (!fs.existsSync(full)) continue;
    const stat = fs.lstatSync(full);
    if (stat.isSymbolicLink()) {
      findings.push({path: name, reason: 'tracked-text-symlink'});
      continue;
    }
    if (!stat.isFile()) continue;
    if (stat.size > 4 * 1024 * 1024) {
      findings.push({path: name, reason: 'unscanned-oversized-text'});
      continue;
    }
    const buffer = fs.readFileSync(full);
    if (buffer.includes(0)) continue; // binary: requires a separate scanner
    for (const reason of detectEmbeddedSecrets(buffer.toString('utf8'))) {
      findings.push({path: name, reason});
    }
  }
  return findings;
}

/** Reject movable upstream action tags; allow local composite actions. */
export function auditWorkflowActionPins(workflow) {
  const issues = [];
  for (const match of workflow.matchAll(/^\s*(?:-\s*)?uses:\s*([^\s#]+)/gm)) {
    const action = match[1];
    if (action.startsWith('./') || action.startsWith('docker://')) continue;
    if (!/^[^@\s]+@[0-9a-fA-F]{40}$/.test(action)) {
      issues.push(action.split('@')[0]);
    }
  }
  return issues;
}

export function main(args) {
  if (args.length !== 2 || !['--source','--archive'].includes(args[0])) {
    process.stderr.write('Penggunaan: node tools/release-safety.mjs --source PROJECT_DIR | --archive FILE.zip\n');
    return 2;
  }
  const [mode, value] = args;
  if (mode === '--archive') {
    const entries = zipEntryNames(value);
    const reasons = auditEntries(entries);
    if (reasons.size) {
      process.stderr.write(`FAIL release ZIP: ${entries.length} entry diperiksa; file terlarang ditemukan:\n`);
      printReasons(reasons);
      return 1;
    }
    process.stdout.write(`PASS release ZIP: ${entries.length} entry, tanpa path sensitif terdeteksi.\n`);
    return 0;
  }
  const root = path.resolve(value);
  const dockerignore = path.join(root, '.dockerignore');
  const dockerfile = path.join(root, 'Dockerfile');
  if (!fs.existsSync(dockerignore) || !fs.existsSync(dockerfile)) {
    process.stderr.write('FAIL: .dockerignore atau Dockerfile tidak tersedia.\n');
    return 1;
  }

  // A fresh checkout may have no secrets in its working tree but still track
  // them in Git; scan the index without ever printing file contents.
  const index = spawnSync('git', ['-C', root, 'ls-files', '-z'], { encoding: 'utf8' });
  if (index.error || index.status !== 0) {
    process.stderr.write('FAIL: tidak dapat memeriksa tracked files dengan git ls-files.\n');
    return 1;
  }
  const trackedPaths = index.stdout.split('\0').filter(Boolean);
  const trackedSecrets = auditTrackedFiles(trackedPaths);
  if (trackedSecrets.length) {
    process.stderr.write('FAIL tracked sensitive runtime files (remove from Git index):\n');
    trackedSecrets.forEach(v => process.stderr.write(' - '+v+'\n'));
    return 1;
  }
  const embeddedSecrets = auditTrackedSecretContent(root, trackedPaths);
  if (embeddedSecrets.length) {
    process.stderr.write('FAIL: possible active secrets in tracked files (values are hidden):\n');
    for (const finding of embeddedSecrets) {
      process.stderr.write(' - '+finding.path+' ['+finding.reason+']\n');
    }
    return 1;
  }
  const workflowPath = path.join(root, '.github', 'workflows', 'ci.yml');
  if (!fs.existsSync(workflowPath)) {
    process.stderr.write('FAIL: workflow CI tidak ditemukan.\n');
    return 1;
  }
  const mutableActions = auditWorkflowActionPins(fs.readFileSync(workflowPath, 'utf8'));
  if (mutableActions.length) {
    process.stderr.write('FAIL: GitHub Actions belum dipin ke SHA commit:\n');
    mutableActions.forEach(action => process.stderr.write(' - ' + action + '\n'));
    return 1;
  }
  const missing = auditDockerIgnore(fs.readFileSync(dockerignore, 'utf8'));
  if (missing.length) {
    process.stderr.write('FAIL Docker context policy: pola perlindungan belum lengkap:\n');
    missing.forEach(v=>process.stderr.write(` - ${v}\n`));
    return 1;
  }
  process.stdout.write('PASS Docker context policy: pola wajib ditemukan.\n');
  return 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  try { process.exitCode = main(process.argv.slice(2)); }
  catch (e) { process.stderr.write(`FAIL release safety: ${e.message}\n`); process.exitCode = 1; }
}
