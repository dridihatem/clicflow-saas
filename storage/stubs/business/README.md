# Business companion modules (installable ZIP)

Companion packages for first-party **core SaaS** verticals (E-commerce pack, Shopify, WooCommerce, Real estate).

Runtime UI and sync live in `app/` (not inside the zip). Marketplace purchase grants **company entitlements** that unlock core navigation — companion zips are **not** shown under Settings → Modules or in the installed-modules nav.

## E-commerce pack (one zip)

Buying **E-commerce** (`ecommerce`) unlocks:

| Capability | Feature key | Routes |
|---|---|---|
| Products, variants, stock, orders, deliveries | `ecommerce` | `/commerce/*` |
| Fullscreen retail POS | `pos` | `/pos` |

Standalone `retail-pos` listing is unpublished (legacy only).

## Build

```bash
cd storage/stubs/business
chmod +x build-zips.sh
./build-zips.sh
```

Output: `storage/stubs/business/zips/{slug}.zip`

For a **visible** installable module (shows in Settings → Modules nav), use `storage/stubs/installable/` instead (e.g. `comptabilite`). Companion zips here unlock core features and stay hidden by design.

## Attach to store listings

```bash
php artisan db:seed --class=BusinessModulesSeeder
```

This publishes `/modules` listings **and** copies each zip to `storage/app/marketplace-modules/{id}/package.zip`.

## Purchase / entitlement

1. Buy **E-commerce** on `/modules` — fulfillment grants `ecommerce` **and** `pos`.
2. Core routes appear in workspace nav (`/commerce/*`, `/pos`, …).
3. Shopify / WooCommerce remain optional paid add-ons that sync into the shared catalog.
