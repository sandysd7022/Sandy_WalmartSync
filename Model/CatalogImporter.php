<?php
namespace Sandy\WalmartSync\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Magento\Framework\Exception\NoSuchEntityException;
use Sandy\WalmartSync\Model\Api\Client;

class CatalogImporter
{
    private $client;
    private $storage;
    private $productRepository;
    private $logger;
    private $config;
    private $productCollectionFactory;
    private $customOptionMatcher;
    private $productAction;

    public function __construct(
        Client $client,
        SkuStorage $storage,
        ProductRepositoryInterface $productRepository,
        OperationLogger $logger,
        Config $config,
        ProductCollectionFactory $productCollectionFactory,
        CustomOptionMatcher $customOptionMatcher,
        ProductAction $productAction
    ) {
        $this->client = $client;
        $this->storage = $storage;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->customOptionMatcher = $customOptionMatcher;
        $this->productAction = $productAction;
    }

    public function execute($limit = null)
    {
        $imported = 0;
        $errors = 0;
        $recordsSeen = 0;
        $uniqueSkus = [];
        $catalogItems = [];
        $reportedTotal = 0;
        $pages = 0;
        $cursor = null;
        $pageFingerprints = [];
        $requestedLimit = $limit !== null ? max(0, (int)$limit) : null;
        $pageSize = $requestedLimit !== null && $requestedLimit > 0
            ? min(1000, $requestedLimit)
            : 1000;

        // Fetch and validate the complete cursor snapshot before changing Magento.
        // Deferring product matching and database work also avoids cursor expiry.
        do {
            $response = $this->client->getAllItems($cursor, $pageSize, null);
            $pages++;
            $items = $this->extractItems($response);
            $pageReportedTotal = $this->extractReportedTotal($response);
            if ($pageReportedTotal > 0) {
                if ($reportedTotal > 0 && $reportedTotal !== $pageReportedTotal) {
                    throw new \RuntimeException(sprintf(
                        'Walmart changed the reported catalog total during pagination (%d to %d). No Magento catalog data was changed; run the refresh again.',
                        $reportedTotal,
                        $pageReportedTotal
                    ));
                }
                $reportedTotal = $pageReportedTotal;
            }
            if (!$items && $reportedTotal > 0 && $recordsSeen === 0) {
                throw new \RuntimeException('Walmart reported catalog items, but the response structure was not recognized. No records were imported.');
            }

            $pageSkus = [];
            foreach ($items as $item) {
                $pageSkus[] = $this->value($item, ['sku', 'SKU', 'partnerItemId']);
            }
            $fingerprint = sha1(implode("\n", $pageSkus));
            if ($items && isset($pageFingerprints[$fingerprint])) {
                throw new \RuntimeException(sprintf(
                    'Walmart returned a repeated catalog page while using cursor pagination (page %d). No Magento catalog data was changed; run the refresh again.',
                    $pages
                ));
            }
            if ($items) {
                $pageFingerprints[$fingerprint] = true;
            }

            $recordsSeen += count($items);
            foreach ($items as $item) {
                if ($requestedLimit !== null && count($catalogItems) >= $requestedLimit) {
                    break 2;
                }
                $sku = $this->value($item, ['sku', 'SKU', 'partnerItemId']);
                if ($sku === '') {
                    $errors++;
                    $this->logger->log('catalog_import', 'error', null, null, null, null, 'Walmart item did not contain a SKU.', $this->client->getLastCorrelationId());
                    continue;
                }
                $uniqueSkus[$sku] = true;
                $catalogItems[$sku] = $item;
            }

            if ($requestedLimit !== null && count($catalogItems) >= $requestedLimit) {
                break;
            }
            if ($reportedTotal > 0 && $recordsSeen >= $reportedTotal) {
                break;
            }

            $nextCursor = $this->extractCursor($response);
            if ($nextCursor === null || $nextCursor === '') {
                break;
            }
            $cursor = $nextCursor;
            if ($pages >= 1000) {
                throw new \RuntimeException('Walmart catalog pagination exceeded 1,000 pages. No Magento catalog data was changed.');
            }
        } while ($items);

        $unique = count($uniqueSkus);
        $isCompleteImport = $requestedLimit === null;
        if ($isCompleteImport) {
            if ($reportedTotal <= 0) {
                throw new \RuntimeException('Walmart did not report the catalog total. Completeness could not be verified, so no Magento catalog data was changed.');
            }
            if ($recordsSeen !== $reportedTotal || $unique !== $reportedTotal || $errors > 0) {
                throw new \RuntimeException(sprintf(
                    'Incomplete Walmart catalog received: expected %d, received %d records and %d unique SKUs with %d errors. No Magento catalog data was changed.',
                    $reportedTotal,
                    $recordsSeen,
                    $unique,
                    $errors
                ));
            }
        }

        $removed = 0;
        $this->storage->beginCatalogTransaction();
        try {
            foreach ($catalogItems as $sku => $item) {
                $existing = $this->storage->getByWalmartSku($sku);
                $match = $this->matchMagentoProduct($sku);
                $mapping = [
                    'mapping_type' => isset($match['mapping_type']) ? $match['mapping_type'] : 'unmatched',
                    'magento_sku' => isset($match['sku']) ? $match['sku'] : null,
                    'product_id' => isset($match['id']) ? $match['id'] : null,
                    'option_id' => isset($match['option_id']) ? $match['option_id'] : null,
                    'option_type_id' => isset($match['option_type_id']) ? $match['option_type_id'] : null,
                    'option_title' => isset($match['option_title']) ? $match['option_title'] : null
                ];
                $this->storage->resetControlsWhenMappingChanges($sku, $mapping);
                $this->storage->upsert(array_merge([
                    'walmart_sku' => $sku,
                    'item_id' => $this->value($item, ['itemId', 'item_id', 'wpid']),
                    'product_name' => $this->value($item, ['productName', 'product_name', 'title']),
                    'published_status' => $this->value($item, ['publishedStatus', 'published_status']),
                    'lifecycle_status' => $this->value($item, ['lifecycleStatus', 'lifecycle_status'])
                ], $mapping));
                $this->syncProductStatusAfterMappingChange($existing, $mapping);
                $imported++;
            }

            $removed = $isCompleteImport
                ? $this->storage->deleteSkusMissingFromCompleteCatalog(array_keys($uniqueSkus))
                : 0;
            $this->storage->commitCatalogTransaction();
        } catch (\Exception $exception) {
            $this->storage->rollBackCatalogTransaction();
            throw new \RuntimeException(
                'Magento catalog refresh failed and was rolled back: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
        $repeated = max(0, $recordsSeen - $unique);
        $message = sprintf(
            'Processed %d Walmart catalog records across %d page(s): %d unique SKUs, expected %d, %d repeated records, %d stale local rows removed, %d errors.',
            $imported,
            $pages,
            $unique,
            $reportedTotal,
            $repeated,
            $removed,
            $errors
        );
        $this->logger->log('catalog_import', $errors ? 'partial' : 'success', null, null, null, $unique, $message);
        return [
            'imported' => $imported,
            'unique' => $unique,
            'expected' => $reportedTotal,
            'pages' => $pages,
            'repeated' => $repeated,
            'removed' => $removed,
            'errors' => $errors
        ];
    }

    private function matchMagentoProduct($walmartSku)
    {
        try {
            $product = $this->productRepository->get($walmartSku, false, null, true);
            // A real Magento product SKU is authoritative. Some default custom-option
            // values repeat the parent SKU; those must not turn a direct product match
            // into a per-option mapping.
            return ['mapping_type' => 'product_sku', 'sku' => $product->getSku(), 'id' => (int)$product->getId()];
        } catch (NoSuchEntityException $exception) {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect('sku')
                ->addAttributeToFilter('walmart_sku', $walmartSku)
                ->setPageSize(1);
            $product = $collection->getFirstItem();
            if ($product->getId()) {
                return ['mapping_type' => 'product_attribute', 'sku' => $product->getSku(), 'id' => (int)$product->getId()];
            }
            $optionMatch = $this->customOptionMatcher->match($walmartSku);
            return $optionMatch ?: ['mapping_type' => 'unmatched', 'sku' => null, 'id' => null];
        }
    }

    private function syncProductStatusAfterMappingChange($existing, array $mapping)
    {
        if (!is_array($existing) || empty($mapping['product_id']) || empty($mapping['magento_sku'])) {
            return;
        }
        $oldType = isset($existing['mapping_type']) ? (string)$existing['mapping_type'] : '';
        $newType = isset($mapping['mapping_type']) ? (string)$mapping['mapping_type'] : '';
        if ($oldType === $newType || !in_array($newType, ['product_sku', 'product_attribute'], true)) {
            return;
        }
        $status = isset($existing['sku_exemption_status']) ? (string)$existing['sku_exemption_status'] : 'unknown';
        $this->productAction->updateAttributes(
            [(int)$mapping['product_id']],
            ['walmart_exemption_status' => $status],
            0
        );
    }

    private function extractItems(array $response)
    {
        $candidates = [
            isset($response['ItemResponse']['Item']) ? $response['ItemResponse']['Item'] : null,
            isset($response['ItemResponse']['items']) ? $response['ItemResponse']['items'] : null,
            isset($response['ItemResponse']) ? $response['ItemResponse'] : null,
            isset($response['itemResponse']['item']) ? $response['itemResponse']['item'] : null,
            isset($response['itemResponse']['items']) ? $response['itemResponse']['items'] : null,
            isset($response['itemResponse']) ? $response['itemResponse'] : null,
            isset($response['elements']['items']) ? $response['elements']['items'] : null,
            isset($response['list']['elements']['item']) ? $response['list']['elements']['item'] : null,
            isset($response['list']['elements']['items']) ? $response['list']['elements']['items'] : null,
            isset($response['payload']) ? $response['payload'] : null,
            isset($response['data']['items']) ? $response['data']['items'] : null,
            isset($response['items']) ? $response['items'] : null,
            isset($response['Item']) ? $response['Item'] : null
        ];
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                if (!$candidate) {
                    continue;
                }
                if ($this->looksLikeItem($candidate)) {
                    return [$candidate];
                }
                if ($this->isList($candidate) && isset($candidate[0]) && is_array($candidate[0]) && $this->looksLikeItem($candidate[0])) {
                    return $candidate;
                }
            }
        }
        return $this->findItemList($response);
    }

    private function extractCursor(array $response)
    {
        if (isset($response['nextCursor'])) {
            return (string)$response['nextCursor'];
        }
        if (isset($response['ItemResponse']['nextCursor'])) {
            return (string)$response['ItemResponse']['nextCursor'];
        }
        if (isset($response['itemResponse']['nextCursor'])) {
            return (string)$response['itemResponse']['nextCursor'];
        }
        if (isset($response['meta']['nextCursor'])) {
            return (string)$response['meta']['nextCursor'];
        }
        if (isset($response['list']['meta']['nextCursor'])) {
            return (string)$response['list']['meta']['nextCursor'];
        }
        return $this->findScalarByKey($response, 'nextCursor');
    }

    private function extractReportedTotal(array $response)
    {
        $candidates = [
            isset($response['totalItems']) ? $response['totalItems'] : null,
            isset($response['meta']['totalCount']) ? $response['meta']['totalCount'] : null,
            isset($response['list']['meta']['totalCount']) ? $response['list']['meta']['totalCount'] : null
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== null) {
                return (int)$candidate;
            }
        }
        $found = $this->findScalarByKeys($response, ['totalItems', 'totalCount']);
        return $found === null ? 0 : (int)$found;
    }

    private function value(array $item, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && !is_array($item[$key])) {
                return trim((string)$item[$key]);
            }
        }
        return '';
    }

    private function findItemList(array $node, $depth = 0)
    {
        if ($depth > 8) {
            return [];
        }
        if ($this->isList($node) && isset($node[0]) && is_array($node[0]) && $this->looksLikeItem($node[0])) {
            return $node;
        }
        foreach ($node as $value) {
            if (!is_array($value)) {
                continue;
            }
            if ($this->looksLikeItem($value)) {
                return [$value];
            }
            $items = $this->findItemList($value, $depth + 1);
            if ($items) {
                return $items;
            }
        }
        return [];
    }

    private function looksLikeItem(array $value)
    {
        $hasSku = isset($value['sku']) || isset($value['SKU']) || isset($value['partnerItemId']);
        $hasItemField = isset($value['productName']) || isset($value['product_name']) ||
            isset($value['publishedStatus']) || isset($value['lifecycleStatus']) ||
            isset($value['itemId']) || isset($value['wpid']) || isset($value['mart']);
        return $hasSku && $hasItemField;
    }

    private function isList(array $value)
    {
        return $value && array_keys($value) === range(0, count($value) - 1);
    }

    private function findScalarByKey(array $node, $key, $depth = 0)
    {
        if ($depth > 8) {
            return null;
        }
        if (array_key_exists($key, $node) && !is_array($node[$key]) && $node[$key] !== null) {
            return (string)$node[$key];
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $found = $this->findScalarByKey($value, $key, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    private function findScalarByKeys(array $node, array $keys, $depth = 0)
    {
        foreach ($keys as $key) {
            $found = $this->findScalarByKey($node, $key, $depth);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }
}
