import test from 'node:test';
import assert from 'node:assert/strict';
import { buildEvidence, verifyEvidence } from '../../tools/release-evidence.mjs';

const commit = 'a'.repeat(40);
const data = {
  app: { configId: 'sha256:'+'b'.repeat(64), sbomSha256: 'c'.repeat(64) },
  web: { configId: 'sha256:'+'d'.repeat(64), sbomSha256: 'e'.repeat(64) },
};

test('release evidence records immutable source commit and local image config IDs', () => {
  const evidence = buildEvidence(commit, data);
  assert.equal(evidence.source_commit, commit);
  assert.equal(evidence.images.app.image, 'osdj-app:latest');
  assert.equal(evidence.images.web.image, 'osdj-web:latest');
  assert.equal(evidence.images.app.sbom_sha256, data.app.sbomSha256);
  assert.equal(verifyEvidence(evidence, buildEvidence(commit, data)), true);
});

test('changing a single SBOM checksum or image config ID invalidates evidence', () => {
  const existing = buildEvidence(commit, data);
  const changed = buildEvidence(commit, {...data, app: {...data.app, sbomSha256:'f'.repeat(64)}});
  assert.equal(verifyEvidence(existing, changed), false);
  const changedImage = buildEvidence(commit, {...data, web: {...data.web, configId:'sha256:'+'f'.repeat(64)}});
  assert.equal(verifyEvidence(existing, changedImage), false);
});

test('invalid Git commit, image ID and SBOM hash are rejected', () => {
  assert.throws(() => buildEvidence('invalid', data), /commit/);
  assert.throws(() => buildEvidence(commit, {...data, app:{...data.app, configId:'sha256:short'}}), /image ID/);
  assert.throws(() => buildEvidence(commit, {...data, web:{...data.web, sbomSha256:'bad'}}), /SBOM checksum/);
});

test('malformed release evidence is rejected', () => {
  const expected = buildEvidence(commit, data);
  assert.equal(verifyEvidence(null, expected), false);
  assert.equal(verifyEvidence([], expected), false);
  assert.equal(verifyEvidence({...expected, source_commit:'0'.repeat(40)}, expected), false);
});
