<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\App\ResourceConnection;

class SkuStorage
{
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function upsert(array $data)
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('sandy_walmartsync_sku');
        $data['last_imported_at'] = gmdate('Y-m-d H:i:s');
        $connection->insertOnDuplicate($table, $data, array_keys($data));
    }

    public function beginCatalogTransaction()
    {
        $this->resource->getConnection()->beginTransaction();
    }

    public function commitCatalogTransaction()
    {
        $this->resource->getConnection()->commit();
    }

    public function rollBackCatalogTransaction()
    {
        $this->resource->getConnection()->rollBack();
    }

    public function getByWalmartSku($sku)
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('sandy_walmartsync_sku'))
            ->where('walmart_sku = ?', $sku)
            ->limit(1);
        $row = $connection->fetchRow($select);
        return is_array($row) ? $row : null;
    }

    public function getAll($sku = null, $limit = null)
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('sandy_walmartsync_sku'))
            ->order('entity_id ASC');
        if ($sku !== null && $sku !== '') {
            $select->where('walmart_sku = ?', $sku);
        }
        if ($limit !== null && (int)$limit > 0) {
            $select->limit((int)$limit);
        }
        return $connection->fetchAll($select);
    }

    /**
     * Return the narrow, safe set used to retire orphaned Walmart inventory.
     *
     * Ambiguous and unverified option mappings are intentionally excluded. They
     * still have a possible Magento relationship and must remain in manual review.
     */
    public function getPublishedUnmatched($limit = null)
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('sandy_walmartsync_sku'))
            ->where('UPPER(TRIM(published_status)) = ?', 'PUBLISHED')
            ->where('mapping_type = ?', 'unmatched')
            ->where('(product_id IS NULL OR product_id = ?)', 0)
            ->order('entity_id ASC');
        if ($limit !== null && (int)$limit > 0) {
            $select->limit((int)$limit);
        }
        return $connection->fetchAll($select);
    }

    public function updateStatus($sku, array $data)
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName('sandy_walmartsync_sku'),
            $data,
            ['walmart_sku = ?' => $sku]
        );
    }

    public function deleteSkusMissingFromCompleteCatalog(array $walmartSkus)
    {
        $walmartSkus = array_values(array_unique(array_filter(array_map('strval', $walmartSkus))));
        if (!$walmartSkus) {
            throw new \InvalidArgumentException('A complete Walmart catalog cannot be empty.');
        }

        return (int)$this->resource->getConnection()->delete(
            $this->resource->getTableName('sandy_walmartsync_sku'),
            ['walmart_sku NOT IN (?)' => $walmartSkus]
        );
    }

    public function resetControlsWhenMappingChanges($sku, array $mapping)
    {
        $existing = $this->getByWalmartSku($sku);
        if (!$existing) {
            return;
        }

        // Custom-option IDs are Magento database implementation details. Product
        // imports can recreate an unchanged option and assign new option_id and
        // option_type_id values. The Walmart mapping is still the same when its
        // exact SKU continues to resolve uniquely to the same Magento parent.
        // CatalogImporter has already performed that unique exact-SKU match, so
        // compare only the stable logical identity here. A mapping-type, parent
        // SKU, or parent product change still revokes approval as a safety guard.
        $fields = ['mapping_type', 'magento_sku', 'product_id'];
        foreach ($fields as $field) {
            $old = isset($existing[$field]) ? (string)$existing[$field] : '';
            $new = isset($mapping[$field]) ? (string)$mapping[$field] : '';
            if ($old !== $new) {
                // Exemption history belongs to the Walmart SKU, not to its current
                // Magento mapping. Preserve it while requiring the changed mapping
                // to be reviewed again.
                $this->updateStatus($sku, [
                    'mapping_verified' => 0,
                    'is_eligible' => 0,
                    'eligibility_reason' => 'Mapping changed and requires verification.',
                    'sync_enabled' => 0,
                    'is_meltable' => 0,
                    'seasonal_status' => 'unknown',
                    'magento_qty' => null,
                    'calculated_qty' => null,
                    'sync_action' => 'skip'
                ]);
                return;
            }
        }
    }

    public function configure($sku, array $data)
    {
        $allowed = ['mapping_verified', 'sku_exemption_status'];
        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (!$update) {
            return false;
        }
        return (bool)$this->resource->getConnection()->update(
            $this->resource->getTableName('sandy_walmartsync_sku'),
            $update,
            ['walmart_sku = ?' => $sku]
        );
    }

    public function verifyMappingEntityIds(array $entityIds)
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (!$entityIds) {
            return 0;
        }
        return (int)$this->resource->getConnection()->update(
            $this->resource->getTableName('sandy_walmartsync_sku'),
            [
                'mapping_verified' => 1,
                'is_eligible' => 0,
                'eligibility_reason' => 'Mapping verified. Run inventory preview to calculate the current action.',
                'sync_action' => 'skip'
            ],
            ['entity_id IN (?)' => $entityIds, 'mapping_type = ?' => 'custom_option']
        );
    }

    public function updateProductSyncState(array $productIds, $enabled)
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return 0;
        }
        $enabled = (bool)$enabled;
        return (int)$this->resource->getConnection()->update(
            $this->resource->getTableName('sandy_walmartsync_sku'),
            [
                'sync_enabled' => $enabled ? 1 : 0,
                'is_eligible' => 0,
                'eligibility_reason' => $enabled
                    ? 'Product sync enabled. Run inventory preview to calculate the current action.'
                    : 'Walmart Sync is not enabled for the product.',
                'sync_action' => 'skip'
            ],
            ['product_id IN (?)' => $productIds]
        );
    }
}
