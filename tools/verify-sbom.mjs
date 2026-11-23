#!/usr/bin/env node
/** Read-only CycloneDX JSON release artifact gate, no external dependencies. */
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

export function validateCycloneDx(sbom) {
  if (sbom === null || typeof sbom !== 'object' || Array.isArray(sbom)) {
    return 'SBOM harus berupa JSON object';
  }
  if (sbom.bomFormat !== 'CycloneDX') {
    return 'Format bukan CycloneDX';
  }
  if (!/^\d+\.\d+$/.test(String(sbom.specVersion ?? ''))) {
    return 'Versi spesifikasi CycloneDX tidak valid';
  }
  if (!Array.isArray(sbom.components) || sbom.components.length === 0) {
    return 'SBOM tidak memiliki komponen software';
  }
  if (sbom.components.some(component =>
    !component || typeof component !== 'object'
    || (typeof component.name !== 'string' || !component.name.trim())
    || (typeof component.version !== 'string' || !component.version.trim())
  )) {
    return 'Komponen SBOM tanpa nama/versi ditemukan';
  }
  return null;
}

export function main(args) {
  if (args.length !== 1) {
    process.stderr.write('Usage: node tools/verify-sbom.mjs path/to/image.cdx.json\n');
    return 2;
  }
  try {
    const data = JSON.parse(fs.readFileSync(args[0], 'utf8'));
    const reason = validateCycloneDx(data);
    if (reason) {
      process.stderr.write('FAIL SBOM: '+reason+'\n');
      return 1;
    }
    process.stdout.write('PASS SBOM: '+data.components.length+' software components inventoried.\n');
    return 0;
  } catch {
    // Do not print file contents or arbitrary JSON parse error details.
    process.stderr.write('FAIL SBOM: file tidak dapat dibaca sebagai JSON valid.\n');
    return 1;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  process.exitCode = main(process.argv.slice(2));
}
