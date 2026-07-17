# Staging test plan

## Preconditions

- Confirm Magento is exactly 2.3.4 and PHP is 7.1, 7.2, or 7.3.
- Take code and database backups.
- Confirm whether the account is seller-fulfilled, WFS, or mixed.
- Confirm the default ship node.
- Confirm the existing order service does not write Walmart inventory.
- Obtain Walmart API credentials with Items, Inventory, Feeds, and Pricing permissions. Do not grant Orders permissions.
- Select one non-critical, seller-fulfilled SKU with confirmed return-exemption approval.

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
php bin/magento walmart:catalog:import --limit=10
php bin/magento walmart:inventory:backup --sku=TEST-SKU
php bin/magento walmart:inventory:preview --sku=TEST-SKU
php bin/magento walmart:inventory:zero --sku=TEST-SKU
php bin/magento walmart:inventory:sync --sku=TEST-SKU
```

Verify the Admin **Walmart Sync > Known Walmart SKUs** grid. Confirm the Walmart SKU, Item ID, Magento match, last known quantity, eligibility, and reason.

For a Magento custom-option SKU, verify that the grid shows the parent Magento SKU, mapping type `custom_option`, and the correct option title. Keep it ineligible until the mapping and exemption are confirmed:

```bash
php bin/magento walmart:sku:configure --sku=TEST-OPTION-SKU --mapping-verified=yes --exemption=approved
php bin/magento walmart:inventory:preview --sku=TEST-OPTION-SKU
```

This configuration command updates only the local Magento mapping controls. It does not call Walmart.

## One-product write canary

Obtain written client approval. Enable **Allow Walmart Write Operations**, but leave cron disabled.

1. Run a fresh backup for the selected SKU.
2. Execute zero for that SKU only.
3. Verify zero through the API and Seller Center.
4. Wait long enough to prove the existing service does not restore inventory.
5. Set the Magento product fields:
   - Enable Walmart Sync = Yes
   - Return Exemption Status = Approved
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

- Exemption status Pending produces quantity zero.
- Enable Walmart Sync No produces quantity zero.
- Magento product Disabled produces quantity zero.
- Force Walmart Inventory to Zero Yes produces quantity zero.
- Magento out of stock produces quantity zero.
- Quantity less than or equal to the configured buffer produces quantity zero.
- Incorrect Walmart SKU mapping produces quantity zero.

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
