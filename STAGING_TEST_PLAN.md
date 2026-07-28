# Staging test plan

## Preconditions

- Confirm Magento is exactly 2.3.4 and PHP is 7.1, 7.2, or 7.3.
- Take code and database backups.
- Confirm whether the account is seller-fulfilled, WFS, or mixed.
- Confirm the default ship node.
- Confirm the existing order service does not write Walmart inventory.
- Obtain Walmart API credentials with Items, Inventory, Feeds, and Pricing permissions. Do not grant Orders permissions.
- Select non-critical seller-fulfilled SKUs covering one non-meltable product, one meltable product, and one verified custom-option mapping.

## Installation validation

```bash
php bin/magento module:enable Sandy_WalmartSync
php bin/magento setup:upgrade
php bin/magento cache:flush
php bin/magento module:status Sandy_WalmartSync
php bin/magento list | grep walmart
```

Verify that the two new database tables exist and that the Walmart Sync product attributes appear on a test product.

## Read-only phase

Keep both **Allow Walmart Write Operations** and **Enable Inventory Cron** set to **No**.

```bash
php bin/magento walmart:connection:test
php bin/magento walmart:catalog:import
php bin/magento walmart:catalog:reconcile
php bin/magento walmart:inventory:backup --sku=TEST-SKU
php bin/magento walmart:inventory:preview --sku=TEST-SKU
php bin/magento walmart:inventory:preview --sku=TEST-MELTABLE-SKU --date=2026-07-15
php bin/magento walmart:inventory:preview --sku=TEST-MELTABLE-SKU --date=2027-01-15
php bin/magento walmart:inventory:zero --sku=TEST-SKU
php bin/magento walmart:inventory:sync --sku=TEST-SKU
```

Verify the Admin **Walmart Sync > Known Walmart SKUs** grid. Confirm the Walmart SKU, Item ID, Magento match, last known quantity, eligibility, and reason.

Open **Walmart Sync > Inventory Dashboard** and verify:

- Module/write/cron safety indicators are correct.
- Unique, matched, unmatched, and unverified counts match the CLI reconciliation report.
- The dashboard does not expose long-running catalog refresh or obsolete exemption request controls.
- Return-exemption totals are labelled historical/reference only.
- Every grid column has a plain-language explanation.

For a Magento custom-option SKU, verify that the grid shows the parent Magento SKU, mapping type `custom_option`, and the correct option title. Select the row and run **Verify Selected Custom-Option Mappings**. Then select the mapped direct and option rows and run **Enable Sync for Selected Magento Products**. Both actions must confirm that no Walmart API data changed.

Run a normal preview to refresh the grid:

```bash
php bin/magento walmart:inventory:preview --sku=TEST-OPTION-SKU
```

Confirm Mapping Verified = Yes, Sync Enabled = Yes and that SEND/SKIP, meltable state and calculated quantity are correct. Select the same row and test **Disable Sync for Selected Magento Products**; after a new preview it must show SKIP. Re-enable it for further tests. Direct product mappings do not require mapping verification.

## Cron canary

Keep every product disabled except the approved staging canaries. Set **Allow Walmart Write Operations = Yes**, **Enable Inventory Cron = Yes**, and temporarily set the expression to `* * * * *`. Run Magento cron twice at least one minute apart:

```bash
php bin/magento cron:run --group=default
php bin/magento cron:run --group=default
```

Verify `sandy_walmartsync_inventory` in `cron_schedule`, then check the grid Last Result, Last Error and Last Sync Time. After the test, immediately set cron and write operations back to No. The inventory cron refreshes calculated grid state, but new Walmart catalog records still require the read-only catalog import maintenance command.

## One-product write canary

Obtain written client approval. Enable **Allow Walmart Write Operations**, but leave cron disabled.

1. Run a fresh backup for the selected SKU.
2. Execute zero for that SKU only.
3. Verify zero through the API and Seller Center.
4. Wait long enough to prove the existing service does not restore inventory.
5. Set the Magento product fields:
   - Enable Walmart Sync = Yes
   - Force Walmart Inventory to Zero = No
6. Preview restoration.
7. Execute restoration.
8. Verify the expected quantity through the API and Seller Center.

```bash
php bin/magento walmart:inventory:zero --sku=TEST-SKU --execute --confirm="ZERO:TEST-SKU"
php bin/magento walmart:inventory:preview --sku=TEST-SKU
php bin/magento walmart:inventory:sync --sku=TEST-SKU --execute --confirm="SYNC:TEST-SKU"
```

## Negative eligibility tests

For the canary SKU, test each condition separately and use preview before executing:

- Exemption history does not change eligibility or calculated quantity.
- Enable Walmart Sync No produces SKIP and sends no Walmart request.
- Magento product Disabled produces quantity zero.
- Force Walmart Inventory to Zero Yes produces quantity zero.
- Magento out of stock produces quantity zero.
- Quantity less than or equal to the configured buffer produces quantity zero.
- Unmatched, ambiguous and unverified custom-option mappings produce SKIP and send no Walmart request.

## Zero-all gate

Do not execute zero-all until all conditions pass:

- Complete catalog import, without a limit.
- Walmart item count reconciled with the local grid.
- Complete inventory backup with zero errors.
- Default/multiple ship-node behavior confirmed.
- Dry-run output reviewed and archived.
- Client provides written approval.
- Inventory cron remains disabled.

The execution command is intentionally explicit:

```bash
php bin/magento walmart:inventory:zero --execute --confirm="ZERO-ALL"
```

Restore is always a separate operation after eligibility review.
