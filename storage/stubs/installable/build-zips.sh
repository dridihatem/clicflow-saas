#!/usr/bin/env bash
# Build installable (non core-bundled) module zip packages.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="$ROOT/zips"
mkdir -p "$OUT"

for slug in comptabilite; do
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

echo "Done. Install via Settings → Modules (requires plan feature: modules)."
