import test from 'node:test';
import assert from 'node:assert/strict';
import { appKeyBytes, compareLocalToCommittedKeys } from '../../tools/verify-local-key-rotation.mjs';

function envWithKey(n, quoted = false) {
  const key = 'base64:' + Buffer.alloc(32, n).toString('base64');
  return 'APP_ENV=local\nAPP_KEY=' + (quoted ? '"' + key + '"' : key) + '\n';
}

test('valid base64 APP_KEY is parsed to exactly 32 bytes without printing key', () => {
  assert.equal(appKeyBytes(envWithKey(11))?.length, 32);
  assert.equal(appKeyBytes(envWithKey(11, true))?.length, 32);
  assert.equal(appKeyBytes('APP_KEY=base64:invalid') , null);
  assert.equal(appKeyBytes('APP_KEY=\n'), null);
  assert.equal(appKeyBytes(envWithKey(10)+envWithKey(11)), null);
});

test('still-exposed key is rejected even with different quoting', () => {
  assert.equal(
    compareLocalToCommittedKeys(envWithKey(4), [envWithKey(9), envWithKey(4, true)]),
    'historical-key-reused'
  );
});

test('new local key passes only after a valid historical key was compared', () => {
  assert.equal(compareLocalToCommittedKeys(envWithKey(5), [envWithKey(4)]), 'different-from-history');
  assert.equal(compareLocalToCommittedKeys(envWithKey(5), []), 'historical-keys-unavailable');
  assert.equal(compareLocalToCommittedKeys(envWithKey(5), ['APP_KEY=']), 'historical-keys-unavailable');
});

test('bad local key cannot be certified as rotated', () => {
  assert.equal(compareLocalToCommittedKeys('APP_KEY=not-a-valid-key', [envWithKey(4)]), 'invalid-local-key');
});
