# Stage 1 release notes

## 1.6.8

- Fixed unchanged custom-option mappings losing verification and sync approval after Magento product imports regenerated internal option IDs.
- Custom-option approval now remains valid when the exact Walmart option SKU still resolves uniquely to the same Magento parent product.
- Approval is still revoked when the mapping type, Magento parent SKU, or Magento parent product changes, including ambiguous or unmatched results.
- Existing disabled rows are not silently approved by this update; restore only current published, active and uniquely matched rows through Safe Bulk Sync Approval while Walmart writes and cron are disabled.
- No Walmart API data is changed during catalog refresh or Safe Bulk Sync Approval.

## 1.6.7

- Added a guarded `published-unmatched` inventory-zero scope for published Walmart SKUs that have no Magento product mapping.
- Excluded ambiguous and unverified custom-option mappings from automatic orphan zeroing.
- Added a candidate-set hash which must match the reviewed complete dry run before execution.
- Ensured the zero operation backs up and writes the exact same frozen in-memory candidate set without re-querying between those steps.
- Added previous Walmart quantities and the candidate hash to backup/write audit output.
- Existing matched-product inventory synchronization, catalog content, pricing, orders and tracking behavior are unchanged.

## 1.6.6

- Fixed the Magento Admin Orders grid error `Not registered handle sales_order_grid_data_source`.
- Moved the Walmart UI collection registration from area-specific Admin DI to global DI so it merges safely with Magento's core Sales and other grid data sources.
- No Walmart catalog, mapping, inventory, pricing, order, or tracking behavior changed.

## 1.6.5

- Added read-only All, Unpublished, Errors, Drafts and Published counters above the Known Walmart SKUs grid.
- Each counter is a grid shortcut that applies the exact Walmart publication-status filter.
- Counts use the latest successfully imported local Walmart catalog and never contact or change Walmart.

## 1.6.4

- Fixed complete Walmart catalog imports to use `nextCursor` pagination instead of offset pagination.
- Fetches and validates the entire Walmart cursor snapshot before changing Magento catalog rows.
- Rejects incomplete, repeated-page, inconsistent-total, or malformed catalog responses without changing Magento catalog data.
- Uses Walmart's supported 1,000-item page size so cursor retrieval completes before cursor expiry.
- Removes stale local SKU cache rows only after a complete catalog snapshot passes all validation.
- Applies the validated local refresh in one database transaction and rolls it back completely if any SKU fails.
- Reports expected totals, received unique SKUs, page count, duplicates, removals, and errors in CLI/admin results.
- This catalog refresh remains read-only toward Walmart and never changes inventory, prices, content, orders, or tracking.

## 1.6.3

- Fixed the mapping and sync status colors when Magento loads the SKU grid asynchronously.
- The attention script now waits for grid rendering and reapplies labels after filtering, paging and AJAX refreshes.

## 1.6.2

- The **Mapping Verified** cell now displays **Not required** for direct `product_sku` mappings.
- Verified custom-option mappings are shown in green; unverified custom-option mappings are shown in red as **Review required**.
- **Sync Enabled** is shown in green for Yes and red for No.
- These visual labels do not change filtering, mapping, approval or Walmart data.

## 1.6.1

- Changed the Known Walmart SKUs **Published Status** filter from free text to an exact-value dropdown.
- Selecting **Published** no longer includes rows whose status is **Unpublished**.

## 1.6.0

- Added a two-step Safe Bulk Sync Approval workflow to the Magento Admin dashboard.
- Preview is read-only and accepts only published/active Walmart rows mapped to enabled Magento products.
- Direct mappings must remain exact Walmart SKU = Magento SKU matches.
- Custom-option mappings must remain one unique exact option-value SKU match to the same imported parent, option and value.
- Unmatched, ambiguous, disabled, unpublished, inactive and incomplete mappings remain disabled for manual review.
- Apply requires a confirmation checkbox, an additional browser confirmation, and a preview less than 30 minutes old.
- Apply is blocked unless Walmart Write Operations and Automatic Inventory Cron are both disabled.
- Apply rejects the request if the candidate fingerprint changes after preview.
- Bulk approval updates Magento controls only; it never calls Walmart or executes inventory synchronization.

## 1.4.6

- Added color-coded attention columns and a legend to the Known Walmart SKUs grid.
- Highlighted live-write/cron safety gates, unmatched and unverified counts, SEND/SKIP totals, seasonal-zero totals, sync errors and latest actual sync time on the dashboard.
- Documented that inventory cron refreshes operational grid state while complete catalog discovery remains a separate read-only maintenance import.
- Added a controlled staging cron-canary procedure.

## 1.4.5

- Made the Known Walmart SKUs grid the single Admin control for product sync enablement.
- Hid the duplicate product-edit toggle after confirming Magento 2.3 could render No while the stored global value, grid and inventory engine all correctly read Yes.
- Direct and custom-option Walmart rows continue to share the same parent-product value.

## 1.4.4

- Added Magento's standard Boolean source model to Enable Walmart Sync and Force Walmart Inventory to Zero.
- Fixed the Magento 2.3 product form displaying No while the stored global integer value and inventory engine correctly read Yes.

## 1.4.3

- Normalized all Walmart product-control attributes to Global scope.
- Fixed the product edit form showing a store-view `No` value while inventory preview correctly used the enabled Default-scope value.
- Grid bulk actions continue to update the one global parent-product setting inherited by custom-option Walmart SKUs.

## 1.4.2

- Fixed Magento 2.3 Admin mass actions generating an empty SQL identifier (`WHERE (`` IN (...))`) by explicitly declaring `entity_id` as the Walmart SKU collection ID field.
- The fix applies to mapping verification and product sync enable/disable actions.

## 1.4.1

- Added guarded grid actions to verify selected custom-option mappings and enable or disable synchronization for selected mapped Magento products.
- Parent products are deduplicated when multiple Walmart option rows are selected.
- All grid actions update Magento controls only and never call the Walmart API.
- Added an Admin workflow guide and plain-language explanations for operational grid columns.
- Added comments explaining every connection, safety and scheduling configuration field.
- Removed active return-exemption download/import controls and exemption mass actions from the client workflow; history remains read-only.
- Removed the long-running browser catalog-refresh button to prevent Cloudflare 524 timeouts; complete imports remain a server maintenance command.

## 1.4.0

- Return-exemption status remains available as historical information but no longer controls inventory synchronization.
- Added Magento-category-driven meltable detection, including products assigned to child categories.
- Added a product-level Meltable Product Override with Automatic, Yes and No values.
- Custom-option Walmart SKUs inherit meltable status and inventory from their mapped Magento parent product.
- Added configurable seasonal zero dates and timezone; defaults are May 1 through November 30 in America/New_York.
- Added a safe `--date=YYYY-MM-DD` inventory preview option for seasonal testing without changing the server clock or persisting simulated results.
- Added Sync Enabled, Magento Qty, Meltable, Seasonal Status, Calculated Walmart Qty and Last Sync Time to the SKU grid.
- Added last-preview counts for ready, meltable and seasonally-zero Walmart SKUs to the dashboard.
- Bulk sync now skips sync-disabled, unmatched, ambiguous and unverified SKUs instead of sending zero. Intentional zero states remain sendable.

## 1.3.3

- Preserve per-Walmart-SKU exemption history across every Magento mapping change.
- Continue resetting mapping verification and eligibility when a mapping changes.
- Mirror the preserved status to a newly selected direct Magento product mapping.
- Prevent historical requests from reappearing in New Requests Only after catalog reclassification.

## 1.3.2

- Treat a Walmart SKU that exactly matches a Magento product SKU as a direct product mapping, even when the default custom-option value repeats the same SKU.
- Preserve the Walmart-SKU exemption status when that same-product mapping is reclassified from custom option to direct product.
- Copy the preserved status to the Magento product attribute during catalog refresh.
- Added plain-language exemption status explanations to the dashboard and product attribute help text.

## 1.3.1

- Assigned distinct filenames to the master review, all-SKU request, and new-requests-only downloads.
- Kept the filtering rule unchanged: New Requests Only excludes Previously Requested, Pending, Approved, and Rejected SKUs.

## 1.3.0

- Added a client-facing Magento Admin dashboard under **Walmart Sync > Dashboard & Exemptions**.
- Added plain-language module, write-operation, and cron safety indicators.
- Added unique, matched, unmatched, and unverified custom-option catalog totals.
- Added a read-only full catalog refresh button and direct access to the SKU review grid.
- Added browser downloads for the master review CSV, complete request CSV, and new-requests-only CSV.
- Added guarded Admin CSV validation/application for previous, pending, approved, rejected, and unknown exemption statuses.
- Added grid mass actions for local exemption statuses, including an explicit warning before setting Approved.
- Admin exemption controls never call Walmart. Destructive zero-all and sync-all controls remain CLI-only during the review stage.

## 1.2.0

- Added `walmart:catalog:reconcile`, a read-only report for unique SKU, mapping, publication, and exemption counts.
- Added `walmart:exemption:export` to create a Walmart-format request CSV plus an internal master review CSV.
- The review export explicitly identifies unresolved product URLs and prevents treating generated output as submission-ready.
- Added `walmart:exemption:import` with dry-run-by-default behavior and exact execution confirmation.
- Bulk exemption imports support Walmart result files with SKU/status columns and older request files through `--default-status=previously_requested`.
- Bulk status imports validate the complete CSV before writing and never call the Walmart API.

## 1.1.0

- Added Magento custom-option SKU matching, including option value SKUs such as `SD0205J`.
- Added mapping type, option identifiers/title, manual mapping verification, and per-Walmart-SKU exemption status.
- Custom-option mappings use the parent Magento product quantity unchanged; the configured global buffer still applies and should remain `0` for exact quantities.
- Added safety gates: custom-option mappings cannot send positive inventory until manually verified and individually marked exemption-approved.
- Added `walmart:sku:configure` to manage local verification and exemption controls without changing Walmart.
- Added an installed-module schema upgrade and new grid columns for mapping review.
- Product content, titles, prices, images, UPCs, and GTINs remain read-only and are not modified by this release.

## 1.0.6

- Clarified catalog import results by reporting API records processed, unique Walmart SKUs, repeated SKU records, and errors separately.
- Kept unique-SKU upsert behavior, so repeated API records update one local SKU row instead of creating duplicates.

## 1.0.5

- Added the required `nextCursor=*` pagination marker for direct cursor-based All Items requests.
- Used Walmart offset/limit pagination for full catalog imports.
- Tracked API records consumed separately from successfully imported records to prevent skipped pages.

## 1.0.4

- Added direct support for `ItemResponse`, nested `items`, `payload`, and `data.items` All Items response envelopes.
- Added a bounded recursive fallback that recognizes item lists by SKU and item metadata fields.
- Added recursive cursor/total discovery for response validation.
- Added the read-only `walmart:catalog:diagnose` command, which prints structures while hiding values and credentials.

## 1.0.3

- Added support for Walmart's current All Items response containing a top-level `itemResponse` array.
- Added compatibility with `elements.items` and `list.elements.item` response variants.
- Added `meta.nextCursor` and `list.meta.nextCursor` pagination variants.
- Added a fail-safe error when Walmart reports items but the response shape is not recognized.

## 1.0.2

- Replaced Zend HTTP transport with a module-owned cURL transport because Magento 2.3's Zend HTTP client rejects Walmart's required underscore-style headers such as `WM_SEC.ACCESS_TOKEN`.
- Added raw-header support for Walmart GET, POST, and PUT requests.
- Declared the required PHP cURL extension.

## 1.0.1

- Added the required `WM_SVC.NAME` header to OAuth token requests.
- Routed Dynamic Sandbox requests and token generation to `https://sandbox.walmartapis.com`.
- Added `WM_SANDBOX: v2` to Dynamic Sandbox token requests.
- Kept Production and Sandbox credentials isolated through environment-specific token cache keys.

This package implements the safety-critical catalog and inventory foundation requested by the client:

- Magento 2.3.4 / PHP 7.1-compatible module structure.
- Encrypted Walmart Client ID and Client Secret configuration.
- Client-credentials OAuth token handling with encrypted token caching.
- Read-only Walmart catalog import with cursor pagination.
- Local retention of matched and unmatched Walmart SKUs.
- Product attributes for sync enablement, exemption status, Walmart SKU, item ID, force-zero, last sync, and last error.
- Exemption states: Unknown, Previously Requested, Pending, Approved, and Rejected.
- Eligibility enforcement with safe-zero behavior.
- Remote inventory backup export before zero execution.
- Dry-run preview for one SKU or the full local Walmart catalog.
- Guarded one-SKU and zero-all commands with exact confirmation phrases.
- Separate inventory restore/sync operation.
- Read-only Admin SKU grid.
- Inventory cron with overlap locking; disabled by default.
- Database audit log without authentication secrets.
- No order, shipment, tracking, cancellation, return, or product-deletion functionality.

Product-content feeds and price synchronization are intentionally not enabled in this stage. They require the client's confirmed price rule, fulfillment model, product types, and Magento-to-Walmart attribute mappings derived from current Walmart item specs. Adding them before those inputs are known could submit incorrect product data.

## 1.4.7

- Shortened the inventory cron lock identifier to remain within MySQL's 64-character lock-name limit when Magento adds a long database prefix.
- Protected lock acquisition and conditional release so a lock-provider failure cannot attempt to release a lock that was never acquired.

## 1.4.8

- Moved Walmart inventory automation out of Magento's shared `default` cron group into the dedicated, short `wm_sync` group.
- Configured the group to execute in the current process so shared-group locks or separate-process spawning cannot leave Walmart jobs permanently pending.
- Kept the schedule-ahead window small for clear staging diagnostics and isolated execution.

## 1.4.9

- Added an explicit inventory cron completion summary with evaluated, sent and skipped counts.
- Made item-level synchronization errors fail the Magento cron schedule instead of being silently recorded as a successful cron run.
- Logged a clear warning when the module, global write gate or inventory cron gate prevents execution.

## 1.5.0

- Removed the redundant inventory-job database lock because Magento already holds the dedicated `wm_sync` cron-group lock.
- Fixed `Current connection is already holding lock ... only single lock allowed` on MySQL installations that allow one named lock per connection.
- Overlap protection remains enforced by Magento's dedicated cron-group processing lock.

## 1.5.1

- Applied explicit Magento UI `fieldClass` values to high-attention SKU grid cells for reliable Magento 2.3 rendering.
- Yellow identifies mapping and eligibility review; blue identifies quantities and sync decisions; red identifies results and errors; gray identifies historical reference data.
- Strengthened the cell colors and added a colored left edge so important operational columns remain visible in alternating grid rows.

## 1.5.2

- Added a Magento 2.3-safe grid attention initializer that maps visible header labels to colored header and body cells after every grid redraw.
- Preserved attention colors after filtering, pagination, AJAX refreshes and column reordering.
- Removed the unnecessary product-edit-toggle explanation from the SKU grid guide.

## 1.5.3

- Read Magento stock before mapping and sync-enable eligibility gates so disabled or unverified rows still show their real Magento quantity.
- Added a stock-item-save observer that immediately refreshes Magento Qty for every local Walmart row mapped to the edited parent product.
- Marked calculated quantity and sync action as awaiting recalculation after a stock edit; the next preview or cron run safely recomputes the Walmart action.
