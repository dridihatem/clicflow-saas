#!/usr/bin/env bash
# Rebuild Vite assets on the VPS (fixes missing marketing-site.js in manifest).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Working directory: $ROOT"

if [[ ! -f package.json ]]; then
  echo "ERROR: package.json not found. Are you in the app root?"
  exit 1
fi

if [[ ! -f resources/js/marketing-site.js ]]; then
  echo "ERROR: resources/js/marketing-site.js is missing from this checkout."
  exit 1
fi

if ! grep -q "marketing-site.js" vite.config.js; then
  echo "ERROR: vite.config.js does not list resources/js/marketing-site.js as an input."
  exit 1
fi

echo "==> Installing npm dependencies"
if [[ -f package-lock.json ]]; then
  npm ci
else
  npm install
fi

echo "==> Building Vite assets"
npm run build

if [[ ! -f public/build/manifest.json ]]; then
  echo "ERROR: public/build/manifest.json was not created."
  exit 1
fi

if ! node -e "const m=require('./public/build/manifest.json'); if(!m['resources/js/marketing-site.js']) process.exit(1)"; then
  echo "ERROR: manifest still missing resources/js/marketing-site.js"
  exit 1
fi

# Stale Vite HMR pointer would bypass the built manifest
rm -f public/hot

if command -v php >/dev/null 2>&1; then
  echo "==> Clearing Laravel caches"
  php artisan optimize:clear || true
fi

echo "==> OK — marketing-site.js is in the Vite manifest."
node -e "const m=require('./public/build/manifest.json'); console.log(m['resources/js/marketing-site.js']);"
