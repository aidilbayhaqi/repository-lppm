#!/usr/bin/env node
/** OSDJ-LPPM release safety gate. Node 22+, no dependencies, read-only. */
import fs from 'node:fs';
import path from 'node:path';
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
