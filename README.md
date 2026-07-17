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
