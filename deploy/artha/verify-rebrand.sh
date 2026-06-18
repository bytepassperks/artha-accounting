#!/usr/bin/env bash
# Asserts the Artha Accounting white-label layer is intact in the working tree.
#
# Run after merging upstream erpsaas commits: if upstream overwrote any branded
# file, an assertion fails LOUDLY here so the pipeline stops instead of shipping
# a half-branded (or erpsaas-leaking) build. Exit 0 = rebrand intact.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
cd "$ROOT"

fail=0
need() {
  # need <file> <grep-pattern> <human description>
  local file="$1" pat="$2" desc="$3"
  if [ ! -f "$file" ]; then
    printf ' !! MISSING FILE: %s (%s)\n' "$file" "$desc" >&2; fail=1; return
  fi
  if ! grep -qiF "$pat" "$file"; then
    printf ' !! REBRAND DRIFT: %s no longer contains "%s" (%s)\n' "$file" "$pat" "$desc" >&2; fail=1
  fi
}
forbid() {
  # forbid <file> <grep-pattern> <human description>
  local file="$1" pat="$2" desc="$3"
  [ -f "$file" ] || return 0
  if grep -qiF "$pat" "$file"; then
    printf ' !! LEAK: %s contains forbidden token "%s" (%s)\n' "$file" "$pat" "$desc" >&2; fail=1
  fi
}

# ── Brand identity must be present ────────────────────────────────────────────
need ".env.example"                                     'APP_NAME="Artha Accounting"' "app name"
need "composer.json"                                    'bytepassperks/artha-accounting' "package name"
need "database/seeders/UserCompanySeeder.php"           'admin@arthize.com'          "seeded admin"
need "database/seeders/UserCompanySeeder.php"           "'name' => 'Artha'"          "seeded company"
need "resources/views/components/icons/logo.blade.php"  'aria-label="Artha"'         "logo mark"
need "resources/views/components/icons/logo.blade.php"  '#202870'                    "Artha indigo brand colour"

# ── Deploy fix must survive upstream merges ───────────────────────────────────
need "bootstrap/app.php"                                'trustProxies'               "Scalingo HTTPS proxy trust"
need "Procfile"                                         'queue:work'                 "worker process"

# ── No upstream branding may leak into the user-facing logo ───────────────────
forbid "resources/views/components/icons/logo.blade.php" 'erpsaas'  "erpsaas brand in logo"
forbid "resources/views/components/icons/logo.blade.php" 'Wallo'    "upstream author brand in logo"

if [ "$fail" -ne 0 ]; then
  printf '\n !! Rebrand verification FAILED — fix deploy/artha or re-apply the rebrand layer before shipping.\n' >&2
  exit 1
fi
printf '==> rebrand verification OK — Artha Accounting branding intact, no erpsaas leaks.\n'
