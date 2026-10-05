# Stage 1 release notes

## 1.8.9

- Added accessible color coding to the New Walmart Items grid Creation Status column while retaining every text status.
- Blocked/failed is red, success is green, validated is teal, candidate is amber, submitted/in-progress is blue, already-existing is gray-blue, and unknown states are neutral gray.
- Added a visible status legend and explanatory hover text so administrators can quickly identify the next safe workflow action.
- This is an admin presentation change only; it does not validate, submit, publish or otherwise call Walmart.

## 1.8.8

- Added report-backed mappings for `main_cat = Marshmallow` to Walmart `Marshmallows` and `main_cat = Licorice` to Walmart `Licorice Candy`; `Gummies` continues to map to `Gummy Candy`.
- Added strict name/description cross-checking. A conflicting or missing category signal blocks local validation and explains which `main_cat` must be corrected.
- Salty Licorice/Salmiak takes precedence over Marshmallow, matching published Walmart SKU SD0922. Strawberry Marshmallow SKU SD0911 maps to Walmart `Marshmallows`.
- Stores and displays the reviewed Walmart category `Food & Beverages` in the candidate grid. Category and type remain part of the guarded local payload review before any API write.

## 1.8.7

- Replaced the fixed `foodForm = Pieces` value with guarded product-name resolution for Gummy Candy candidates.
- Recognizes gumdrops, gummy bears, gummy worms/crawlers, gummy rings, fruit slices, jelly beans, belts, strings/laces, sticks, chews, bites, drops and generic gummies.
- Unclear names are blocked during local validation instead of receiving an invented form. The resolved Food Form is stored and displayed in the review grid.
- The one-SKU CLI command derives Food Form from the Magento product name unless `--food-form` is explicitly supplied.

## 1.8.6

- Fixed Walmart's `Site End Date must be greater than Site Start Date` rejection for held-unpublished item creation.
- Held payloads now include both the configured future `startDate` and a later `endDate` (default `2099-12-31T23:59:59Z`).
- Added configuration and local validation for the hold end date, so a missing, malformed or non-later date is blocked before calling Walmart.
- The publish action retains the far-future end date while moving the start date to the current UTC time.

## 1.8.5

- Added guarded automatic flavor resolution for Gummy Candy candidates.
- Resolution order is explicit `walmart_flavor`, an existing Magento `flavor` attribute, then recognized flavor words in Product Name and Short Description.
- Uses `Assorted` only when multiple flavors are found or the source explicitly says assorted, mix, variety or multi-flavor.
- Unclear products remain blocked instead of receiving a guessed value. The resolved value is displayed in the grid after validation.

## 1.8.4

- Manual `walmart_feature_1`, `walmart_feature_2` and `walmart_feature_3` entry is no longer required for the New Walmart Items grid workflow.
- Feature 1 uses the explicit override when present, otherwise Magento Short Description, then Product Name.
- Feature 2 uses the explicit override when present, otherwise Magento Description, then Brand plus Product Name.
- Feature 3 uses the explicit override when present, otherwise a factual summary of `package_qty` and `total_package_weight`.
- Derived feature text is stripped of HTML, normalized and limited to 300 characters. Explicit feature attributes remain optional overrides.

## 1.8.3

- New-item discovery and validation now use the requested Magento source attributes: `jet_brand` for Brand, `package_qty` for Package Qty, and `total_package_weight` for Per Package Weight/net content.
- Brand is passed explicitly as both Walmart brand and manufacturer; a blank `jet_brand` blocks only that candidate.
- Added the three source values to the New Walmart Items grid for review before submission.
- Existing item-feed safety gates, unpublished hold, reviewed payload hash and manual publication workflow are unchanged.

## 1.8.2

- Fixed the remaining Magento 2.3 selected-row SQL error in the controller-side candidate collection.
- Explicitly sets `entity_id` on both the UI grid collection and the resource collection consumed by `Magento\Ui\Component\MassAction\Filter`.
- No Walmart API, payload, catalog-discovery or eligibility behavior changed.

## 1.8.1

- Fixed the New Walmart Items mass-action SQL error caused by an undeclared grid identifier.
- The candidate grid now explicitly identifies `entity_id`, so Magento correctly filters selected rows instead of generating an empty column name in the `WHERE` clause.
- This fix changes only local admin-grid selection. It does not submit data to Walmart or alter candidate eligibility rules.

## 1.8.0

- Added a separate **New Walmart Items** admin grid for enabled simple Magento products directly assigned to the configured Collections category (default ID 273).
- Discovery requires a recent complete Walmart catalog import and excludes SKUs already present in that import or products already carrying a Walmart Item ID.
- Uses `main_cat` as the Walmart type discriminator. This first staging release supports only `Gummies` to the already-tested Walmart `Gummy Candy` schema; every other value is displayed as blocked instead of being guessed.
- Added local-only validation and exact payload/hash storage. Validation requires UPC/EAN/GTIN, price, weight, brand, country, description, main image, ingredients, flavor, three key features, package quantity and package weight.
- New items are submitted with a configurable future `startDate` (default `2099-12-31T00:00:00Z`) so Walmart holds them unpublished for review.
- Added separate grid actions to refresh feed status and, only after a successful held creation, publish selected reviewed items by resubmitting the stored payload with `startDate` changed to the current UTC time.
- New-item discovery and validation never call Walmart. Both global Walmart writes and the separate item-feed gate remain required for create and publish submissions. New-item creation is not added to cron.

## 1.7.6

- Removed the unsupported `specProductType` field from `Orderable` after Walmart returned `EXT_DATA_ERROR_60670554076755` for the live MP_ITEM canary.
- The product type remains correctly represented by the single `Visible` product-type object (`Gummy Candy`), matching Walmart's schema.
- Updated local preview validation to derive the product type from `Visible` and reject ambiguous multi-type payloads.

## 1.7.5

- Request `includeDetails=true` with paging parameters when checking a Walmart feed so failed item ingestion errors are returned instead of an empty detail list.
- Feed-status checks remain read-only and do not resubmit or modify any Walmart item.

## 1.7.4

- Accept Walmart processing feed IDs containing `@`, matching the identifier returned by a successful item-feed submission.
- Retain path safety by limiting feed IDs to 200 allowed characters and URL-encoding the identifier before the read-only status request.
- No catalog, item, inventory, cron, or write behavior changed.

## 1.7.3

- Added the visible global product attribute `ingredients` under the Walmart Sync group when that attribute does not already exist, for manual entry or Magento CSV import.
- New Gummy Candy item generation now requires actual ingredient text and excludes only the affected SKU when it is missing.
- Automatically creates a versioned public PNG ingredient label under `pub/media/walmart/ingredient-labels/`; a real public ingredient-image URL can still override it.
- Adds ingredient text to the Walmart payload and validates Walmart's 5,000-character limit before preview or submission.
- Item creation remains manual, one SKU at a time, preview-first, and separate from inventory synchronization and cron.
- If draft 1.7.2 was installed, its obsolete Walmart-specific ingredient and allergen attributes are hidden without deleting stored data.

## 1.7.1

- Added `walmart:item:generate-simple` to build a one-SKU Walmart `Gummy Candy` `MP_ITEM` JSON file from a Magento simple product.
- The generator includes Walmart's required orderable and visible fields, requires three factual features and a public ingredient-label image, and never includes Magento custom options or Walmart variant fields.
- Corrected item-feed submission to use Walmart's required multipart file upload while leaving all inventory API requests unchanged.
- Generation and preview remain read-only. Item submission still requires both write switches, the explicit confirmation phrase and the exact preview hash.
- Inventory quantity remains a separate operation after successful feed ingestion, catalog refresh, mapping review and sync enablement.

## 1.7.0

- Added `walmart:item:feed` for local validation and hash-confirmed submission of Walmart `MP_ITEM` and `MP_ITEM_MATCH` JSON feeds.
- Added `walmart:item:feed:status` for read-only processing-status checks.
- Added a separate **Allow New Item Feed Submission** safety switch, disabled by default, plus a configurable local maximum of 25 items.
- Prevented automatic retries for feed POST requests to avoid duplicate submissions after ambiguous network timeouts.
- Kept catalog import, inventory calculations, inventory cron, meltable rules and all existing commands unchanged.
- Item creation is not included in cron. Newly accepted items must be confirmed through feed status and catalog import before inventory sync is enabled.

## 1.6.15

- The **Meltable Magento Categories** selector now lists only active Magento categories.
- A category that is later disabled is also ignored by automatic meltable classification, even if its old ID remains saved in configuration.
- No Walmart write, inventory calculation, mapping, cron, or custom-option behavior was changed.

## 1.6.14

- Meltable detection now evaluates both the Magento `main_cat` attribute and every assigned Magento category.
- Added configurable exact meltable `main_cat` values with safe defaults for Chocolate, Chocolate Covered Sweets, Nougat and Marzipan.
- The broad `Collections` category/value is always ignored because it contains both meltable and non-meltable products.
- Preserved the existing product override priority: Yes forces meltable, No forces non-meltable and Automatic evaluates both category sources.
- Inventory writes, sync approvals, custom-option inheritance and seasonal dates are unchanged.

## 1.6.13

- Fixed the All, Unpublished, Errors, Drafts and Published tabs so the selected publication status is applied to the Magento UI grid.
- Kept the selected tab compatible with other active grid filters such as Sync Enabled and Meltable.
- This is an admin-grid filtering fix only; inventory calculations, Walmart writes and scheduled synchronization are unchanged.

## 1.6.12

- Replaced the unsafe 15-minute default with one daily scheduled run at `30 3 * * *`.
- Added a whole-job file lock so overlapping catalog-refresh and inventory-sync runs are safely skipped.
- Added an automatic catalog-shrink guard: if Walmart unexpectedly returns less than 80% of the existing local catalog, the refresh and all inventory writes stop for review.
- Moved Magento product and custom-option matching outside the catalog write transaction to reduce database lock time.
- Added catalog, inventory and total run durations to the scheduled-run log.
- Retained the sequence introduced in 1.6.11: complete read-only catalog refresh, fresh inventory calculation, then eligible inventory writes.

## 1.6.11

- Every enabled scheduled inventory run now starts with a complete read-only Walmart catalog refresh.
- The job automatically rebuilds local mappings and recalculates Magento quantity, meltable season, calculated Walmart quantity and SEND/SKIP action before inventory writes.
- A failed or incomplete catalog refresh stops the run before any Walmart inventory is changed.
- The dashboard shows the latest automatic catalog-refresh time and explains the scheduled sequence.
- Routine operation no longer requires the client to refresh the complete catalog from a terminal.

## 1.6.10

- Fixed inventory preview so an unmatched Walmart SKU is no longer copied into the Magento SKU column. The Magento SKU now stays blank when no Magento product mapping exists.
- Dashboard matched/unmatched totals now require a confirmed mapping type, product ID and Magento SKU instead of treating any non-empty fallback value as a match.
- Running the inventory preview once after deployment cleans previously persisted fallback values from unmatched rows without contacting Walmart.

## 1.6.9

- Added guarded bulk scope `unpublished-meltable` for a one-time seasonal zero of unpublished Walmart SKUs that Magento has confirmed as meltable.
- Excluded unmatched, ambiguous and unverified custom-option rows from this scope.
- Added candidate-hash confirmation and an exact mandatory remote inventory backup before any write.
- SKUs already at zero in the mandatory remote backup are skipped without an unnecessary Walmart write.
- The scope does not enable synchronization and does not add unpublished SKUs to the normal scheduled inventory process.

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
