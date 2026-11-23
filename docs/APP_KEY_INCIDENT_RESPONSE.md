# APP_KEY incident response — OSDJ-LPPM

## Confirmed exposure and scope

A runtime `.env.docker` containing an application encryption key was committed in the Git history of this public repository. Removing that file from HEAD does **not** revoke it or erase historical Git objects. Before production approval, determine whether the same key was ever used in dev/staging/production. Never paste the key or its hash into issues, PR descriptions, or CI logs.

The currently confirmed persisted encrypted attribute is `users.mfa_secret` (`User` cast `encrypted`). Sessions are also encrypted when `SESSION_ENCRYPT=true`; session cookies and encrypted session payloads may become invalid after rotation. Reinspect all model casts and external encryption integrations before action—this is an initial inventory, not a proof of completeness.

## Read-only compatibility probe

On a trusted host with the intended application key and working database, run:

```bash
php artisan repository:check-mfa-encryption
```

This command reads non-null MFA ciphertext in batches of 100 and tries to decrypt it using the current Laravel encryption configuration. It prints **counts only**, not identities, TOTP secrets, ciphertext, decrypted values, or exception text. Exit code nonzero signals a decryption failure. It does **not** rotate, rewrite, delete, export, or re-encrypt data; a successful check does **not** mean the key is uncompromised.

## Required incident response and rotation gates

1. **Assess exposure:** security owner inventories historic commits, forks, CI artifacts, distributed ZIPs, backups and every runtime environment that could use the exposed key. Record time range and risk without secret values.
2. **Prepare recovery:** secure an encrypted, access-limited, off-host backup of the database, retained private PDF versions, config and key recovery material; restore it on separate staging infrastructure and verify it.
3. **Inventory encrypted fields:** verify `users.mfa_secret` and other encrypted attributes, run the read-only command, and test login, TOTP, recovery codes, password reset, session handling, file download and queued email in isolated staging.
4. **Plan rotation outside Git:** create a new random key through approved secret management. Test re-encryption of persisted data on staging before cutting over. Do **not** run `php artisan key:generate` blindly on a live database. Temporarily accepting an exposed old key as `APP_PREVIOUS_KEYS` extends exposure; if considered, ensure restricted duration and explicit risk approval.
5. **Execute after approval:** coordinate maintenance, pause mutations/worker if needed, take a fresh verified backup, perform the rehearsed migration/cutover, invalidate sessions as appropriate, verify MFA and services, and store sanitized evidence with owner sign-off.
6. **History handling:** evaluate a history rewrite only after owner approval and coordination across clones/forks. A forced rewrite cannot remove copies already obtained by others, and must never be the only remediation.

## Exit criteria

The incident remains **OPEN** until the owner determines whether the exposed key was used outside the local environment, retires any affected active key, verifies data restore and authentication after rotation, and signs off on historical exposure handling. CI passing and clean current-tree scans do not close it.
