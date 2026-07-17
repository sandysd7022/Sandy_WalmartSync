<?php
namespace Sandy\WalmartSync\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
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

    public function __construct(
        Client $client,
        SkuStorage $storage,
        ProductRepositoryInterface $productRepository,
        OperationLogger $logger,
        Config $config,
        ProductCollectionFactory $productCollectionFactory,
        CustomOptionMatcher $customOptionMatcher
    ) {
        $this->client = $client;
        $this->storage = $storage;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->customOptionMatcher = $customOptionMatcher;
    }

    public function execute($limit = null)
    {
        $imported = 0;
        $errors = 0;
        $recordsSeen = 0;
        $uniqueSkus = [];
        $cursor = null;
        $offset = 0;
        do {
            $response = $this->client->getAllItems($cursor, $this->config->getBatchSize(), $offset);
            $items = $this->extractItems($response);
            $reportedTotal = $this->extractReportedTotal($response);
            if (!$items && $reportedTotal > 0) {
                throw new \RuntimeException('Walmart reported catalog items, but the response structure was not recognized. No records were imported.');
            }
            $recordsSeen += count($items);
            foreach ($items as $item) {
                if ($limit !== null && $imported >= (int)$limit) {
                    break 2;
                }
                $sku = $this->value($item, ['sku', 'SKU', 'partnerItemId']);
                if ($sku === '') {
                    $errors++;
                    $this->logger->log('catalog_import', 'error', null, null, null, null, 'Walmart item did not contain a SKU.', $this->client->getLastCorrelationId());
                    continue;
                }
                $uniqueSkus[$sku] = true;
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
                $imported++;
            }
            if ($reportedTotal > $recordsSeen && $items) {
                if ($recordsSeen > 10000) {
                    throw new \RuntimeException('Walmart catalog exceeds the supported offset range of 10,000 records. Import stopped without changing Walmart data.');
                }
                $offset = $recordsSeen;
            } else {
                $offset = null;
            }
        } while ($offset !== null && $items);

        $unique = count($uniqueSkus);
        $repeated = max(0, $imported - $unique);
        $message = sprintf('Processed %d Walmart catalog records: %d unique SKUs, %d repeated SKU records, %d errors.', $imported, $unique, $repeated, $errors);
        $this->logger->log('catalog_import', $errors ? 'partial' : 'success', null, null, null, $unique, $message);
        return ['imported' => $imported, 'unique' => $unique, 'repeated' => $repeated, 'errors' => $errors];
    }

    private function matchMagentoProduct($walmartSku)
    {
        try {
            $product = $this->productRepository->get($walmartSku, false, null, true);
            $optionMatch = $this->customOptionMatcher->match($walmartSku);
            if ($optionMatch && isset($optionMatch['mapping_type']) && $optionMatch['mapping_type'] === 'custom_option' &&
                isset($optionMatch['id']) && (int)$optionMatch['id'] === (int)$product->getId()) {
                return $optionMatch;
            }
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
