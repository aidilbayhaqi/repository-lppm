import test from 'node:test';
import assert from 'node:assert/strict';
import { validateCycloneDx } from '../../tools/verify-sbom.mjs';

const valid = {
  bomFormat: 'CycloneDX',
  specVersion: '1.6',
  components: [{ type: 'library', name: 'laravel/framework', version: '13.42.0' }],
};

test('valid CycloneDX with named versioned components passes', () => {
  assert.equal(validateCycloneDx(valid), null);
});

test('non-CycloneDX or missing component list is rejected', () => {
  assert.match(validateCycloneDx({ ...valid, bomFormat: 'SPDX' }), /CycloneDX/);
  assert.match(validateCycloneDx({ ...valid, components: [] }), /komponen/);
});

test('malformed component lacking version is rejected', () => {
  assert.match(validateCycloneDx({ ...valid, components: [{name: 'unknown'}] }), /nama\/versi/);
});
