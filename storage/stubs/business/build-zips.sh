#!/usr/bin/env bash
# Build installable .zip packages for first-party business companion modules.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="$ROOT/zips"
mkdir -p "$OUT"

for slug in ecommerce retail-pos real-estate shopify-sync woocommerce-sync odoo-sync; do
    dir="$ROOT/$slug"
    if [[ ! -f "$dir/module.json" ]]; then
        echo "Skip missing $slug"
        continue
    fi
    zip_path="$OUT/${slug}.zip"
    rm -f "$zip_path"
    (cd "$dir" && zip -qr "$zip_path" . -x '*.DS_Store')
    echo "Built $zip_path"
done

echo "Done. Upload via Settings → Modules, or seed onto store listings."
