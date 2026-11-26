# Installable modules (ZIP)

These packages are **real CRM modules** (not core companions). After upload they appear under **Settings → Modules** and in the workspace modules nav.

## Comptabilité (`comptabilite`)

- International chart of accounts + journals + balanced entries
- Trial balance / P&L / balance sheet summaries
- Études de projet with AI generation and PDF (header/footer)

```bash
cd storage/stubs/installable
chmod +x build-zips.sh
./build-zips.sh
```

Zip: `storage/stubs/installable/zips/comptabilite.zip`

Requires workspace plan feature **modules** (Pro / Enterprise).

## Note about business companion zips

Packages under `storage/stubs/business/` (Odoo, Shopify, …) unlock **core** features and stay hidden from the modules list by design. Use installable stubs above when you want a visible module after ZIP upload.
