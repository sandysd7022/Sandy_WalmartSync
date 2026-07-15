# Stage 1 release notes

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
