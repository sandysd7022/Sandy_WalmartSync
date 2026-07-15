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

    public function __construct(
        Client $client,
        SkuStorage $storage,
        ProductRepositoryInterface $productRepository,
        OperationLogger $logger,
        Config $config,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->client = $client;
        $this->storage = $storage;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function execute($limit = null)
    {
        $imported = 0;
        $errors = 0;
        $cursor = null;
        $seenCursors = [];
        do {
            $response = $this->client->getAllItems($cursor, $this->config->getBatchSize());
            $items = $this->extractItems($response);
            if (!$items && $this->extractReportedTotal($response) > 0) {
                throw new \RuntimeException('Walmart reported catalog items, but the response structure was not recognized. No records were imported.');
            }
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
                $match = $this->matchMagentoProduct($sku);
                $this->storage->upsert([
                    'walmart_sku' => $sku,
                    'item_id' => $this->value($item, ['itemId', 'item_id', 'wpid']),
                    'product_name' => $this->value($item, ['productName', 'product_name', 'title']),
                    'magento_sku' => $match['sku'],
                    'product_id' => $match['id'],
                    'published_status' => $this->value($item, ['publishedStatus', 'published_status']),
                    'lifecycle_status' => $this->value($item, ['lifecycleStatus', 'lifecycle_status'])
                ]);
                $imported++;
            }
            $cursor = $this->extractCursor($response);
            if ($cursor !== null && isset($seenCursors[$cursor])) {
                throw new \RuntimeException('Walmart catalog pagination returned a repeated cursor. Import stopped to prevent an infinite loop.');
            }
            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null && $cursor !== '' && $items);

        $this->logger->log('catalog_import', $errors ? 'partial' : 'success', null, null, null, $imported, sprintf('Imported %d Walmart SKUs with %d errors.', $imported, $errors));
        return ['imported' => $imported, 'errors' => $errors];
    }

    private function matchMagentoProduct($walmartSku)
    {
        try {
            $product = $this->productRepository->get($walmartSku, false, null, true);
            return ['sku' => $product->getSku(), 'id' => (int)$product->getId()];
        } catch (NoSuchEntityException $exception) {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect('sku')
                ->addAttributeToFilter('walmart_sku', $walmartSku)
                ->setPageSize(1);
            $product = $collection->getFirstItem();
            if ($product->getId()) {
                return ['sku' => $product->getSku(), 'id' => (int)$product->getId()];
            }
            return ['sku' => null, 'id' => null];
        }
    }

    private function extractItems(array $response)
    {
        $candidates = [
            isset($response['ItemResponse']['Item']) ? $response['ItemResponse']['Item'] : null,
            isset($response['itemResponse']['item']) ? $response['itemResponse']['item'] : null,
            isset($response['itemResponse']) ? $response['itemResponse'] : null,
            isset($response['elements']['items']) ? $response['elements']['items'] : null,
            isset($response['list']['elements']['item']) ? $response['list']['elements']['item'] : null,
            isset($response['items']) ? $response['items'] : null,
            isset($response['Item']) ? $response['Item'] : null
        ];
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                if (!$candidate) {
                    return [];
                }
                return array_keys($candidate) === range(0, count($candidate) - 1) ? $candidate : [$candidate];
            }
        }
        return [];
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
        return null;
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
        return 0;
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
}
