# Verify local APP_KEY rotation (read-only)

Scope: **local development Docker only**. This tool never changes `.env.docker`, Redis, users, PDFs or MySQL.

## Before running

1. Back up `.env.docker`, the local MySQL database, and all private documents to a restricted, encrypted location outside Git. A real restore rehearsal is still a separate requirement.
2. Check all persisted encryption users. `users.mfa_secret` uses Laravel encrypted casting; session payloads are encrypted under `SESSION_ENCRYPT=true`. An observed count of zero non-null MFA secrets reduces one risk but does not prove all encrypted data is absent.
3. Rotate the local APP_KEY through a controlled offline process **only after the above**; recreate the `app`, `worker` and `scheduler` containers to read the new environment.

## Verify without displaying any keys

From the project root in PowerShell:

```powershell
node .\tools\verify-local-key-rotation.mjs
git status --short
docker compose ps
docker compose exec app php artisan repository:monitor-security
```

Exit codes: `0` means the current local `.env.docker` key and any `APP_PREVIOUS_KEYS` differ from parseable APP_KEY values found in the checked-out Git history of the old `.env.docker`; `1` means a current or fallback key matches a known exposed key; `2` means the comparison is **unverified** (missing file, invalid key, missing/truncated Git history, or read failure). Do not interpret `2` as success.

The tool checks `APP_KEY` and comma-separated `APP_PREVIOUS_KEYS`, then reads the local env file and historical blobs **in memory** and prints only a pass/fail status. It does not write hashes, paste the key, or clean the original Git history. It intentionally does not run automatically in GitHub CI because that runner must never have access to developer runtime `.env.docker`.

Even a pass does **not** prove the container is using the new key, that encrypted data remain decryptable, or that the old key was not shared in other archives/clones. Confirm a fresh container rollout, normal login and application behavior, plus `repository:check-mfa-encryption` after that command is merged and deployed. Keep old key material in restricted recovery storage only as long as needed; production uses an independent generated key.

## GitHub protection remains separate

Settings → Rules → Rulesets → New branch ruleset → target `main` → Active. Require pull requests, SQLite/MySQL/Docker checks, prevent force push and deletion. For a solo maintainer, requiring approvals with no additional reviewer can block all merges. Check actual GitHub API protection state; a checkbox in a planning issue is not evidence the protection was applied.
