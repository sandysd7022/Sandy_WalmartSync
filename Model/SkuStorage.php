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

    public function updateStatus($sku, array $data)
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName('sandy_walmartsync_sku'),
            $data,
            ['walmart_sku = ?' => $sku]
        );
    }

    public function resetControlsWhenMappingChanges($sku, array $mapping)
    {
        $existing = $this->getByWalmartSku($sku);
        if (!$existing) {
            return;
        }
        $fields = ['mapping_type', 'magento_sku', 'product_id', 'option_id', 'option_type_id'];
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
