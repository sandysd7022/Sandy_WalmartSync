# Sandy_WalmartSync

Magento Open Source 2.3.4 / PHP 7.1 module for safe Magento-to-Walmart catalog inventory synchronization.

## Safety defaults

- Module disabled after installation.
- Walmart write operations disabled after installation.
- Inventory cron disabled after installation.
- Catalog import is read-only.
- Preview commands never write to Walmart.
- Zero execution creates and verifies a remote inventory backup before changing any SKU.
- Zero-all requires the exact confirmation `ZERO-ALL` and does not support a partial `--limit` execution.
- Inventory sync sends zero for every ineligible SKU.
- No Walmart order, shipment, cancellation, return, or tracking code is included.

## Install

Copy `app/code/Sandy/WalmartSync` into the Magento root and run:

```bash
php bin/magento module:enable Sandy_WalmartSync
php bin/magento setup:upgrade
php bin/magento cache:flush
```

For production mode also run the deployment commands required by the existing Magento environment.

## Configure

Open `Stores > Configuration > Sandy > Walmart Sync`.

1. Keep **Allow Walmart Write Operations** set to **No**.
2. Enter the Walmart Client ID and Client Secret.
3. Confirm environment and default ship node.
4. Enable the module.
5. Import and review data before enabling writes.

Seller Center access alone does not provide API credentials. Obtain the seller's personal Client ID and Client Secret from Seller Center's API Integration / API Key Management area with Items, Inventory, Feeds, and Pricing permissions only. Orders permissions are not required.

## Full catalog review and return exemptions

### Client-facing Admin workflow

Open **Walmart Sync > Dashboard & Exemptions**. The page shows safety settings and catalog totals and provides:

- A read-only complete Walmart catalog refresh.
- A link to the searchable SKU review grid.
- Master review, complete request, and new-request-only CSV downloads.
- CSV validation preview and guarded local status application.
- Clear warnings that generated request files require Product URL review.

The **Known Walmart SKUs** grid also includes mass actions for exemption statuses. The Approved action displays an explicit confirmation. These actions change Magento controls only and never call Walmart.

Bulk zero-all and sync-all remain CLI-only while the catalog is being reconciled. This prevents a client user from accidentally changing thousands of Walmart quantities from the dashboard.

### Developer CLI workflow

Keep Walmart writes and inventory cron disabled. Import the full catalog, then reconcile the unique local SKU records:

```bash
php bin/magento walmart:catalog:import
php bin/magento walmart:catalog:reconcile
```

Export a Walmart-format request CSV and a separate internal review CSV:

```bash
php bin/magento walmart:exemption:export --scope=all
```

The files are written to `var/export` by default. The request CSV uses Walmart's eight-column template. The review CSV includes mapping, publication, exemption, and missing-URL information. Do not submit the request CSV until every missing Product URL is filled and validated.

To identify SKUs from an older request, first convert the old workbook to CSV and preview it using a default status:

```bash
php bin/magento walmart:exemption:import --file=/absolute/path/previous-request.csv --default-status=previously_requested
php bin/magento walmart:exemption:import --file=/absolute/path/previous-request.csv --default-status=previously_requested --execute --confirm="IMPORT-EXEMPTIONS"
```

The first command is a dry run. The second changes only local Magento exemption controls and does not call Walmart. After Walmart returns its decision CSV, run the same command without `--default-status`; the file must contain `SKU` and `Status` columns. Supported statuses are `unknown`, `previously_requested`, `pending`, `approved`, and `rejected`.

The import validates the entire file first. If any row is invalid, no status is updated.

After the client submits the final request CSV, mark those same rows Pending with a dry run first:

```bash
php bin/magento walmart:exemption:import --file=var/export/walmart-return-exemption-request.csv --default-status=pending
php bin/magento walmart:exemption:import --file=var/export/walmart-return-exemption-request.csv --default-status=pending --execute --confirm="IMPORT-EXEMPTIONS"
```

## Safe one-product test

```bash
php bin/magento walmart:connection:test
php bin/magento walmart:catalog:diagnose
php bin/magento walmart:catalog:import --limit=10
php bin/magento walmart:sku:configure --sku=SD0205J --mapping-verified=yes --exemption=approved
php bin/magento walmart:inventory:backup --sku=TEST-SKU
php bin/magento walmart:inventory:preview --sku=TEST-SKU
php bin/magento walmart:inventory:zero --sku=TEST-SKU
```

The last command is a dry run. Only after client approval, enable writes in Admin and execute:

```bash
php bin/magento walmart:inventory:zero --sku=TEST-SKU --execute --confirm="ZERO:TEST-SKU"
php bin/magento walmart:inventory:sync --sku=TEST-SKU
php bin/magento walmart:inventory:sync --sku=TEST-SKU --execute --confirm="SYNC:TEST-SKU"
```

Do not run zero-all until the complete catalog has been imported, the backup succeeds for every SKU, and the client approves the dry-run results.

```bash
php bin/magento walmart:inventory:zero
php bin/magento walmart:inventory:zero --execute --confirm="ZERO-ALL"
```
