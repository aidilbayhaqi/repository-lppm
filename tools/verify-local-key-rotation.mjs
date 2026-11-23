#!/usr/bin/env node
/**
 * Verify whether the developer's active local APP_KEY matches a key previously
 * committed in .env.docker. Never print, persist, hash or export key material.
 * This is a *local*, read-only check; not a replacement for credential rotation.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { timingSafeEqual } from 'node:crypto';
import { pathToFileURL } from 'node:url';

const HISTORICAL_PATH = '.env.docker';

export function appKeyBytes(contents) {
  if (typeof contents !== 'string') return null;
  const matches = [...contents.matchAll(/^[ \t]*APP_KEY[ \t]*=[ \t]*([^\r\n]*)/gm)];
  if (matches.length !== 1) return null;
  let value = matches[0][1].trim();
  if (/^(["']).*\1$/.test(value)) value = value.slice(1, -1);

  if (value.startsWith('base64:')) {
    const encoded = value.slice(7);
    // Buffer.from accepts non-canonical base64. Check exact roundtrip.
    if (!/^[A-Za-z0-9+/]{43}=$/.test(encoded)) return null;
    const result = Buffer.from(encoded, 'base64');
    return result.length === 32 && result.toString('base64') === encoded ? result : null;
  }
  const result = Buffer.from(value, 'utf8');
  return result.length === 32 ? result : null;
}

/**
 * Laravel accepts APP_PREVIOUS_KEYS for decryption. A leaked former key here
 * remains active cryptographic material even after changing APP_KEY.
 * Return null when the list cannot be parsed safely.
 */
export function previousKeyBytes(contents) {
  if (typeof contents !== 'string') return null;
  const matches = [...contents.matchAll(/^[ \t]*APP_PREVIOUS_KEYS[ \t]*=[ \t]*([^\r\n]*)/gm)];
  if (matches.length > 1) return null;
  if (!matches.length) return [];
  let value = matches[0][1].trim();
  if (/^(["']).*\1$/.test(value)) value = value.slice(1, -1);
  if (!value) return [];
  const keys = [];
  for (const valuePart of value.split(',')) {
    const decoded = appKeyBytes('APP_KEY='+valuePart.trim());
    if (!decoded) return null;
    keys.push(decoded);
  }
  return keys;
}

/** Return only a status; actual secrets must not enter stdout/logs. */
export function compareLocalToCommittedKeys(localContents, historicalContents) {
  const local = appKeyBytes(localContents);
  if (!local) return 'invalid-local-key';
  const previousKeys = previousKeyBytes(localContents);
  if (previousKeys === null) return 'invalid-previous-keys';
  let compared = 0;
  for (const history of historicalContents) {
    const leaked = appKeyBytes(history);
    if (!leaked) continue;
    compared++;
    if (timingSafeEqual(local, leaked)) return 'historical-key-reused';
    if (previousKeys.some(key => timingSafeEqual(key, leaked))) return 'historical-previous-key-reused';
  }
  return compared ? 'different-from-history' : 'historical-keys-unavailable';
}

function runGit(root, args) {
  const result = spawnSync('git', ['-C', root, ...args], {
    encoding: 'utf8', maxBuffer: 4 * 1024 * 1024, windowsHide: true,
  });
  if (result.error || result.status !== 0) return null;
  return result.stdout;
}

export function main(args) {
  if (args.length !== 0) {
    process.stderr.write('Usage: node tools/verify-local-key-rotation.mjs\n');
    return 2;
  }
  // Run from the repository root; local .env.docker is never read by CI.
  const cwd = process.cwd();
  const localFile = path.join(cwd, HISTORICAL_PATH);
  try {
    if (!fs.existsSync(localFile) || fs.lstatSync(localFile).isSymbolicLink()) {
      process.stderr.write('UNVERIFIED: local .env.docker is missing or is a symlink.\n');
      return 2;
    }
    const localContents = fs.readFileSync(localFile, 'utf8');
    const log = runGit(cwd, ['log', '--all', '--format=%H', '--', HISTORICAL_PATH]);
    if (log === null) {
      process.stderr.write('UNVERIFIED: unable to inspect local Git history.\n');
      return 2;
    }
    const revisions = log.split(/\r?\n/).filter(sha => /^[a-f0-9]{40}$/.test(sha));
    const historical = [];
    for (const sha of revisions) {
      const file = runGit(cwd, ['show', sha+':'+HISTORICAL_PATH]);
      if (file !== null) historical.push(file);
    }
    const status = compareLocalToCommittedKeys(localContents, historical);
    if (status === 'different-from-history') {
      process.stdout.write('PASS: current local APP_KEY differs from keys found in committed .env.docker history.\n');
      return 0;
    }
    if (status === 'historical-key-reused' || status === 'historical-previous-key-reused') {
      process.stderr.write('FAIL: APP_KEY or APP_PREVIOUS_KEYS still accepts an exposed historical key. Review rotation and fallback config after backup.\n');
      return 1;
    }
    process.stderr.write('UNVERIFIED: invalid local APP_KEY or no comparable historical key found; do not infer safety.\n');
    return 2;
  } catch {
    process.stderr.write('UNVERIFIED: local key/history comparison could not complete. No secrets printed.\n');
    return 2;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  process.exitCode = main(process.argv.slice(2));
}
