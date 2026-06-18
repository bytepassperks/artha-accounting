# Artha Accounting — deploy & auto-update

Artha Accounting is a thin white-label fork of
[andrewdwallo/erpsaas](https://github.com/andrewdwallo/erpsaas) (branch `3.x`,
Laravel 12 + Filament 3). The rebrand is a small set of isolated git commits on
top of upstream, so upstream commits keep merging cleanly and the app
auto-updates.

| | |
|---|---|
| Fork | `bytepassperks/artha-accounting` (branch `3.x`) |
| Upstream | `andrewdwallo/erpsaas` (branch `3.x`) |
| Scalingo app | `artha-accounting` (region `osc-fr1`) |
| Live URL | https://artha-accounting.osc-fr1.scalingo.io |
| Login | `admin@arthize.com` / `password` |
| DB | MySQL (Scalingo addon) · Redis for queue/cache |

## Rebrand layer (what makes it "Artha")

A single commit (`Apply Artha Accounting rebrand layer`) plus the deploy config:

- `APP_NAME="Artha Accounting"`, `composer.json` name, brand colours
- `resources/views/components/icons/logo.blade.php` — Artha mark (indigo
  `#202870` + gold `#E0B030`) + wordmark
- `public/favicon.*`, `public/apple-touch-icon.png`
- `database/seeders/UserCompanySeeder.php` — seeds `admin@arthize.com` + company "Artha"
- `bootstrap/app.php` — trusts Scalingo's proxy so asset URLs are generated as
  HTTPS (otherwise CSS/JS load over http on an https page and get blocked)
- `Procfile` + `scalingo.json`

## Auto-update

`deploy/artha/update.sh` fetches upstream `3.x`, merges it (preserving the
rebrand), rebuilds assets, pushes the fork, deploys the archive to Scalingo,
runs migrations, and verifies the live login. `deploy/artha/verify-rebrand.sh`
asserts the brand tokens are intact after the merge — if upstream overwrote a
branded file or a merge conflicts, the pipeline stops **before** deploying and
asks for a human. Production is never touched on failure.

```bash
export GH_TOKEN=...            # repo scope (push the fork)
export SCALINGO_API_TOKEN=...  # headless Scalingo login

# Prove the next upstream is safe (trial-merge + assert + build, no deploy):
deploy/artha/update.sh --dry-run

# Ship it:
deploy/artha/update.sh
```

`deploy/artha/VERSION` records the upstream erpsaas SHA currently deployed.

## Scalingo deploy notes

- Buildpack-only (no Docker). The PHP buildpack runs `composer install` and
  publishes Filament assets, but **does not** run `npm run build` — Vite assets
  are pre-built and committed under `public/build/`.
- Archive deploys must place the source inside a top-level `artha-accounting/`
  prefix in the tarball, or Scalingo silently drops the source.
- The `update.sh` pipeline runs `php artisan migrate --force` explicitly after
  each deploy rather than relying on the `scalingo.json` postdeploy hook.
