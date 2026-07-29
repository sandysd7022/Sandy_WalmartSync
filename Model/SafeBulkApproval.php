<?php
namespace Sandy\WalmartSync\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;

class SafeBulkApproval
{
    private $resource;
    private $productCollectionFactory;
    private $productAction;
    private $storage;
    private $logger;
    private $config;

    public function __construct(
        ResourceConnection $resource,
        ProductCollectionFactory $productCollectionFactory,
        ProductAction $productAction,
        SkuStorage $storage,
        OperationLogger $logger,
        Config $config
    ) {
        $this->resource = $resource;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productAction = $productAction;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Builds a read-only list of mappings that can be approved without judgment.
     *
     * Safe direct rows must still be an exact Magento product-SKU match.
     * Safe custom-option rows must still have exactly one exact option-value SKU
     * match and must point to the same parent, option and option value imported
     * into the local review table.
     */
    public function preview()
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('sandy_walmartsync_sku');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table)
                ->order('entity_id ASC')
        );

        $productIds = [];
        foreach ($rows as $row) {
            if (!empty($row['product_id'])) {
                $productIds[(int)$row['product_id']] = true;
            }
        }
        $enabledProducts = $this->getEnabledProducts(array_keys($productIds));
        $optionMatches = $this->getOptionMatches($rows);

        $safeDirectIds = [];
        $safeCustomIds = [];
        $candidateTokens = [];
        $safeProductIds = [];
        $alreadyEnabledProductIds = [];
        $alreadyVerifiedCustom = 0;
        $publishedActive = 0;
        $disabledMagento = 0;

        foreach ($rows as $row) {
            if (!$this->isPublishedActive($row)) {
                continue;
            }
            $publishedActive++;
            $productId = isset($row['product_id']) ? (int)$row['product_id'] : 0;
            if (!$productId || !isset($enabledProducts[$productId])) {
                if ($productId) {
                    $disabledMagento++;
                }
                continue;
            }

            $mappingType = isset($row['mapping_type']) ? (string)$row['mapping_type'] : '';
            if (
                $mappingType === 'product_sku' &&
                $this->isExactDirectMatch($row, $enabledProducts)
            ) {
                $safeDirectIds[] = (int)$row['entity_id'];
                $candidateTokens[] = $this->candidateToken($row);
                $safeProductIds[$productId] = true;
                if (!empty($row['sync_enabled'])) {
                    $alreadyEnabledProductIds[$productId] = true;
                }
                continue;
            }

            if ($mappingType === 'custom_option' && $this->isExactUniqueOptionMatch($row, $optionMatches)) {
                $safeCustomIds[] = (int)$row['entity_id'];
                $candidateTokens[] = $this->candidateToken($row);
                $safeProductIds[$productId] = true;
                if (!empty($row['sync_enabled'])) {
                    $alreadyEnabledProductIds[$productId] = true;
                }
                if (!empty($row['mapping_verified'])) {
                    $alreadyVerifiedCustom++;
                }
            }
        }

        $safeProductIdList = array_keys($safeProductIds);
        sort($safeDirectIds);
        sort($safeCustomIds);
        sort($safeProductIdList);
        sort($candidateTokens);
        $signature = hash(
            'sha256',
            implode('|', $candidateTokens) . '|' .
            implode(',', $safeProductIdList)
        );

        return [
            'total_rows' => count($rows),
            'published_active_rows' => $publishedActive,
            'safe_direct_rows' => count($safeDirectIds),
            'safe_custom_rows' => count($safeCustomIds),
            'safe_products' => count($safeProductIdList),
            'custom_rows_to_verify' => count($safeCustomIds) - $alreadyVerifiedCustom,
            'products_to_enable' => count(array_diff($safeProductIdList, array_keys($alreadyEnabledProductIds))),
            'manual_review_rows' => max(0, $publishedActive - count($safeDirectIds) - count($safeCustomIds)),
            'not_published_active_rows' => count($rows) - $publishedActive,
            'disabled_magento_rows' => $disabledMagento,
            'safe_direct_ids' => $safeDirectIds,
            'safe_custom_ids' => $safeCustomIds,
            'safe_product_ids' => $safeProductIdList,
            'signature' => $signature
        ];
    }

    /**
     * Applies only the candidate set represented by the current preview.
     * This method never calls the Walmart API and never runs inventory sync.
     */
    public function apply($expectedSignature)
    {
        if ($this->config->isWriteEnabled() || $this->config->isCronEnabled()) {
            throw new \RuntimeException(
                'Disable Walmart Write Operations and Automatic Inventory Cron before applying bulk sync approval.'
            );
        }
        $preview = $this->preview();
        if (!hash_equals((string)$preview['signature'], (string)$expectedSignature)) {
            throw new \RuntimeException(
                'The safe candidate set changed after preview. Run Preview Safe Bulk Sync Approval again.'
            );
        }
        if (!$preview['safe_product_ids']) {
            return array_merge($preview, ['verified_rows' => 0, 'enabled_rows' => 0]);
        }

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $verifiedRows = $this->storage->verifyMappingEntityIds($preview['safe_custom_ids']);
            $this->productAction->updateAttributes(
                $preview['safe_product_ids'],
                ['walmart_sync_enabled' => 1],
                0
            );
            $enabledRows = $this->storage->updateProductSyncState(
                $preview['safe_product_ids'],
                true
            );
            $this->logger->log(
                'safe_bulk_approval',
                'success',
                null,
                null,
                null,
                count($preview['safe_product_ids']),
                sprintf(
                    'Safe bulk approval enabled %d Magento products and verified %d custom-option rows. No Walmart API data was changed.',
                    count($preview['safe_product_ids']),
                    $verifiedRows
                )
            );
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return array_merge($preview, [
            'verified_rows' => $verifiedRows,
            'enabled_rows' => $enabledRows
        ]);
    }

    private function getEnabledProducts(array $productIds)
    {
        if (!$productIds) {
            return [];
        }
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter('entity_id', ['in' => $productIds])
            ->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED);
        $result = [];
        foreach ($collection as $product) {
            $result[(int)$product->getId()] = (string)$product->getSku();
        }
        return $result;
    }

    private function getOptionMatches(array $rows)
    {
        $skus = [];
        foreach ($rows as $row) {
            if (
                isset($row['mapping_type']) &&
                (string)$row['mapping_type'] === 'custom_option' &&
                !empty($row['option_type_id']) &&
                isset($row['walmart_sku'])
            ) {
                $skus[(string)$row['walmart_sku']] = true;
            }
        }
        if (!$skus) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['value' => $this->resource->getTableName('catalog_product_option_type_value')],
                ['option_type_id', 'option_id', 'option_sku' => 'sku']
            )
            ->join(
                ['option' => $this->resource->getTableName('catalog_product_option')],
                'option.option_id = value.option_id',
                ['product_id']
            )
            ->join(
                ['product' => $this->resource->getTableName('catalog_product_entity')],
                'product.entity_id = option.product_id',
                ['magento_sku' => 'sku']
            )
            ->where('value.sku IN (?)', array_keys($skus));

        $matches = [];
        foreach ($connection->fetchAll($select) as $match) {
            $sku = (string)$match['option_sku'];
            if (!isset($matches[$sku])) {
                $matches[$sku] = [];
            }
            $key = (int)$match['product_id'] . ':' .
                (int)$match['option_id'] . ':' .
                (int)$match['option_type_id'];
            $matches[$sku][$key] = $match;
        }
        return $matches;
    }

    private function isPublishedActive(array $row)
    {
        $published = isset($row['published_status'])
            ? strtoupper(trim((string)$row['published_status']))
            : '';
        $lifecycle = isset($row['lifecycle_status'])
            ? strtoupper(trim((string)$row['lifecycle_status']))
            : '';
        return $published === 'PUBLISHED' && ($lifecycle === '' || $lifecycle === 'ACTIVE');
    }

    private function isExactDirectMatch(array $row, array $enabledProducts)
    {
        if (
            empty($row['product_id']) ||
            !isset($row['walmart_sku']) ||
            !isset($row['magento_sku']) ||
            (string)$row['walmart_sku'] !== (string)$row['magento_sku'] ||
            !isset($enabledProducts[(int)$row['product_id']])
        ) {
            return false;
        }
        return (string)$enabledProducts[(int)$row['product_id']] ===
            (string)$row['walmart_sku'];
    }

    private function isExactUniqueOptionMatch(array $row, array $optionMatches)
    {
        if (
            empty($row['product_id']) ||
            empty($row['option_id']) ||
            empty($row['option_type_id']) ||
            !isset($row['walmart_sku']) ||
            !isset($optionMatches[(string)$row['walmart_sku']])
        ) {
            return false;
        }
        $matches = $optionMatches[(string)$row['walmart_sku']];
        if (count($matches) !== 1) {
            return false;
        }
        $match = reset($matches);
        return (string)$match['option_sku'] === (string)$row['walmart_sku'] &&
            (int)$match['product_id'] === (int)$row['product_id'] &&
            (int)$match['option_id'] === (int)$row['option_id'] &&
            (int)$match['option_type_id'] === (int)$row['option_type_id'] &&
            (string)$match['magento_sku'] === (string)$row['magento_sku'];
    }

    private function candidateToken(array $row)
    {
        $fields = [
            'entity_id',
            'walmart_sku',
            'magento_sku',
            'product_id',
            'mapping_type',
            'option_id',
            'option_type_id',
            'published_status',
            'lifecycle_status'
        ];
        $values = [];
        foreach ($fields as $field) {
            $values[] = isset($row[$field]) ? (string)$row[$field] : '';
        }
        return implode(':', $values);
    }
}
