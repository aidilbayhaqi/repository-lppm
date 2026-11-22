import test from 'node:test';
import assert from 'node:assert/strict';
import { auditEntries, auditDockerIgnore, auditTrackedFiles, classifyPath } from '../../tools/release-safety.mjs';

const forbidden = [
  ['OSDJ/.env', 'environment-secret'],
  ['OSDJ/.env.production', 'environment-secret'],
  ['OSDJ/storage/app/private/document.pdf', 'runtime-data'],
  ['OSDJ/.lppm-updater-backups/backup.txt', 'updater-or-backup'],
  ['OSDJ/Update-OSDJ-LPPM-Priority-1.6.1/manifest.json', 'updater-or-backup'],
  ['OSDJ/deploy/secrets/password', 'deployment-secret'],
  ['OSDJ/node_modules/react/index.js', 'dependencies'],
  ['OSDJ/vendor/composer/autoload.php', 'dependencies'],
  ['OSDJ/backup.sql', 'sensitive-or-nested-artifact'],
  ['../escape.php', 'invalid-path'],
  ['C:\\private\\archive.zip', 'invalid-path'],
];

for (const [p, reason] of forbidden) {
  test(`reject unsafe path type: ${reason} (${p})`, () => assert.equal(classifyPath(p), reason));
}

test('allow non-secret env templates and normal application code', () => {
  for (const p of [
    'OSDJ/.env.example', 'OSDJ/.env.production.example',
    'OSDJ/app/Services/PublishingService.php',
    'OSDJ/public/images/umiba-logo.png',
    'OSDJ/resources/views/welcome.blade.php',
  ]) assert.equal(classifyPath(p), null, p);
});

test('reject archive with forbidden file paths without reading file contents', () => {
  const issues = auditEntries(['OSDJ/app/Services/X.php','OSDJ/.env','OSDJ/storage/logs/laravel.log']);
  assert.equal(issues.get('environment-secret'), 1);
  assert.equal(issues.get('runtime-data'), 1);
});

test('Docker ignore policy requires backup and archive exclusions', () => {
  assert.deepEqual(auditDockerIgnore('.env\nnode_modules\nvendor\n'), [
    '.env.*','storage/app/**','storage/framework/**','deploy/secrets','deploy/tls',
    '.lppm-updater-backups','*.zip','*.sql','*.pem','*.key',
  ]);
});

test('Docker ignore policy rejects broad negative exceptions', () => {
  const content = `.env\n.env.*\nvendor\nnode_modules\nstorage/app/**\nstorage/framework/**\ndeploy/secrets\ndeploy/tls\n.lppm-updater-backups\n*.zip\n*.sql\n*.pem\n*.key\n!*.zip`;
  assert.deepEqual(auditDockerIgnore(content), ['unsafe-negation:!*.zip']);
});


test('reject tracked Docker runtime env, including nested secrets', () => {
  assert.deepEqual(
    auditTrackedFiles(['.env.docker', '.env', 'nested/.env.production', 'deploy/secrets/db']),
    ['.env.docker', '.env', 'nested/.env.production', 'deploy/secrets/db'],
  );
});

test('allow public templates and unrelated files to remain tracked', () => {
  assert.deepEqual(
    auditTrackedFiles(['.env.example', '.env.docker.example', '.env.production.example', 'app/Models/User.php']),
    [],
  );
});
