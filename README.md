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
- Published-unmatched zeroing includes only currently published Walmart rows explicitly classified as unmatched and without a Magento product ID. Ambiguous and unverified option mappings are excluded.
- Published-unmatched execution requires the reviewed dry-run candidate hash and creates a second mandatory remote inventory backup from the exact same frozen candidate set before writing.
- Inventory sync skips disabled, unmatched, ambiguous and unverified mappings. It sends zero only for ready SKUs that are out of stock, manually forced to zero, disabled in Magento, or inside the meltable zero period.
- Return-exemption status is reference history only and does not control inventory synchronization.
- Meltable products can be detected from configured Magento categories and automatically held at zero from May 1 through November 30.
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

### Meltable seasonal inventory

Under **Meltable Seasonal Inventory**, enable the restriction and select the Magento categories Chocolates, Nougats, Marzipan and any future meltable categories. Category IDs are stored, so renaming a category does not break the rule. Products assigned to child categories are included.

The defaults force calculated Walmart inventory to zero from `05-01` through `11-30` in `America/New_York`. From December 1 through April 30, the latest Magento quantity is used. The product attribute **Meltable Product Override** can force Yes or No for individual exceptions; Automatic follows category configuration. Custom-option Walmart SKUs inherit the parent product result.

Test both seasons without changing the server clock:

```bash
php bin/magento walmart:inventory:preview --sku=TEST-MELTABLE-SKU --date=2026-07-15
php bin/magento walmart:inventory:preview --sku=TEST-MELTABLE-SKU --date=2027-01-15
```

The July preview must calculate zero. The January preview must calculate the latest Magento quantity after the inventory buffer. Simulated-date previews do not persist their test result to the grid and never call Walmart write APIs.

## Full catalog and mapping review

### Client-facing Admin workflow

Open **Walmart Sync > Inventory Dashboard**. The page shows safety settings, inventory readiness totals, publication totals and read-only exemption history.

- A link to the searchable SKU review grid.
- Counts for matched, unmatched, unverified, sync-enabled, ready and meltable records.
- Clear reminders that the complete catalog refresh is a server maintenance task.

The **Known Walmart SKUs** grid provides three guarded Admin actions:

Publication-status tabs above the grid show All, Unpublished, Errors, Drafts and Published totals from the latest successful Walmart catalog refresh. Selecting a tab filters the local grid only and never contacts Walmart.

- **Verify Selected Custom-Option Mappings** after checking the Walmart SKU, parent Magento SKU and option title.
- **Enable Sync for Selected Magento Products** to opt in each unique mapped parent product.
- **Disable Sync for Selected Magento Products** to make the selected products SKIP.

These actions change Magento controls only and never call Walmart. Custom-option verification is required once and remains valid until the logical mapping changes. Regenerated internal Magento option IDs do not revoke approval when the exact option SKU still resolves uniquely to the same parent product. A changed parent, ambiguous option or unmatched SKU is disabled for review. Direct product SKU mappings do not require verification. Custom-option rows inherit sync enablement, meltable status and quantity from their parent Magento product.

Sync enablement is intentionally managed from the SKU review grid rather than a duplicate product-edit toggle. The grid shows the effective stored value used by inventory preview and execution.

The inventory cron recalculates and refreshes operational grid fields (Ready, reason, Magento quantity, meltable/seasonal result, calculated Walmart quantity, action, result/error and sync time) whenever it runs. It does not discover newly created Walmart listings or rebuild mappings; a complete `walmart:catalog:import` remains a developer/administrator maintenance task and should run before reviewing new catalog items.

Return exemptions were rejected and no longer control inventory synchronization. Their statuses remain visible only as historical reference. Exemption request download/upload controls are intentionally removed from the normal client workflow.

Bulk zero-all, sync execution and the complete catalog import remain CLI-only while the catalog is being reconciled. This prevents accidental Walmart changes and avoids browser/Cloudflare timeouts.

To retire inventory for published Walmart SKUs which have no Magento product, first keep cron disabled and run the guarded preview:

```bash
php bin/magento walmart:inventory:zero --scope=published-unmatched
```

Review the candidate count and SKUs. An optional separate read-only remote backup is available with `walmart:inventory:backup --scope=published-unmatched`. After written client approval, enable Walmart writes and execute with both the exact confirmation phrase and the hash printed by the complete dry run:

```bash
php bin/magento walmart:inventory:zero --scope=published-unmatched --execute --confirm="ZERO-PUBLISHED-UNMATCHED" --candidate-hash="<DRY-RUN-HASH>"
```

Execution creates another mandatory remote backup and aborts before any write if the backup is incomplete or the candidate set changed. It does not alter matched Magento products, ambiguous mappings, or unverified custom-option mappings.

### Developer CLI workflow

Keep Walmart writes and inventory cron disabled. Import the full catalog, then reconcile the unique local SKU records:

```bash
php bin/magento walmart:catalog:import
php bin/magento walmart:catalog:reconcile
```

The complete import uses Walmart `nextCursor` pagination and fetches the full
snapshot before changing the local Magento SKU cache. Magento applies the
refresh only when the received record count and unique-SKU count both equal
Walmart's reported total. A repeated page, expired/incomplete cursor, malformed
item, changed total, or local save failure rejects and rolls back the refresh.
After a validated complete refresh, stale rows that Walmart no longer returns
are removed from the local cache. This command never writes data to Walmart.

The success result must show the same values for `unique SKUs` and
`Walmart expected`, with `repeated records: 0` and `errors: 0`.

Historical exemption export/import CLI commands remain in the code for audit or recovery purposes, but they are not part of the current inventory rollout.

## Safe one-product test

```bash
php bin/magento walmart:connection:test
php bin/magento walmart:catalog:diagnose
php bin/magento walmart:catalog:import --limit=10
php bin/magento walmart:sku:configure --sku=SD0205J --mapping-verified=yes
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
