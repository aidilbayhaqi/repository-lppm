#!/usr/bin/env node
/**
 * Links a CI checkout to local image config IDs and CycloneDX SHA256 checksums.
 * This is build evidence, NOT a registry image digest, signature or attestation.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

export const IMAGES = [
  { name: 'app', image: 'osdj-app:latest', sbom: 'osdj-app.cdx.json' },
  { name: 'web', image: 'osdj-web:latest', sbom: 'osdj-web.cdx.json' },
];

const SHA256 = /^[0-9a-f]{64}$/i;
const COMMIT = /^[0-9a-f]{40}$/i;
export function buildEvidence(commit, imageData) {
  if (!COMMIT.test(commit)) throw new Error('Invalid Git commit identifier');
  const images = {};
  for (const spec of IMAGES) {
    const entry = imageData[spec.name];
    if (!entry || !/^sha256:[0-9a-f]{64}$/i.test(entry.configId) || !SHA256.test(entry.sbomSha256)) {
      throw new Error('Invalid image ID or SBOM checksum');
    }
    images[spec.name] = {
      image: spec.image,
      image_config_id: entry.configId,
      sbom_filename: spec.sbom,
      sbom_sha256: entry.sbomSha256,
    };
  }
  return { schema_version: 1, source_commit: commit, images };
}

export function verifyEvidence(saved, expected) {
  if (!saved || typeof saved !== 'object' || Array.isArray(saved)) {
    return false;
  }
  return JSON.stringify(saved) === JSON.stringify(expected);
}

export function sha256File(filename) {
  return createHash('sha256').update(fs.readFileSync(filename)).digest('hex');
}

function command(program, args) {
  const result = spawnSync(program, args, { encoding: 'utf8' });
  if (result.error || result.status !== 0 || !result.stdout) {
    throw new Error('Required build tool unavailable or returned an error');
  }
  return result.stdout.trim();
}

function captureExpected() {
  const sha = command('git', ['rev-parse', 'HEAD']);
  const images = {};
  for (const spec of IMAGES) {
    const id = command('docker', ['image', 'inspect', '--format', '{{.Id}}', spec.image]);
    images[spec.name] = { configId: id, sbomSha256: sha256File(spec.sbom) };
  }
  return buildEvidence(sha, images);
}

export function main(args) {
  if (args.length !== 1 || !['create', 'verify'].includes(args[0])) {
    process.stderr.write('Usage: node tools/release-evidence.mjs create|verify\n');
    return 2;
  }
  try {
    const expected = captureExpected();
    const output = 'release-evidence.json';
    if (args[0] === 'create') {
      // The manifest contains only commit, local image config IDs and file hashes.
      fs.writeFileSync(output, JSON.stringify(expected, null, 2)+'\n', { flag: 'w' });
    }
    const saved = JSON.parse(fs.readFileSync(output, 'utf8'));
    if (!verifyEvidence(saved, expected)) {
      process.stderr.write('FAIL release evidence: source, image or SBOM checksum mismatch.\n');
      return 1;
    }
    process.stdout.write('PASS release evidence: source commit, local image IDs and SBOM hashes match.\n');
    return 0;
  } catch {
    process.stderr.write('FAIL release evidence: missing build input or invalid evidence file.\n');
    return 1;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  process.exitCode = main(process.argv.slice(2));
}
