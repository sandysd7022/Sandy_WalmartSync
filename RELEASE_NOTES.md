# Stage 1 release notes

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
