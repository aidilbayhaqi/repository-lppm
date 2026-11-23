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

test('malformed component lacking name is rejected', () => {
  assert.match(validateCycloneDx({ ...valid, components: [{version: '1.2.3'}] }), /tanpa nama/);
});


test('some OS inventory components may legitimately omit a version', () => {
  assert.equal(validateCycloneDx({...valid, components: [{type: 'operating-system', name: 'linux'}]}), null);
});
