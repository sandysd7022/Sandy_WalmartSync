<?php
namespace Sandy\WalmartSync\Model;

class CatalogReconciler
{
    private $storage;

    public function __construct(SkuStorage $storage)
    {
        $this->storage = $storage;
    }

    public function execute()
    {
        $rows = $this->storage->getAll();
        $result = [
            'total' => count($rows),
            'matched' => 0,
            'unmatched' => 0,
            'mapping_unverified' => 0,
            'sync_enabled' => 0,
            'ready_to_sync' => 0,
            'meltable_skus' => 0,
            'meltable_products' => 0,
            'seasonal_zero' => 0,
            'send_actions' => 0,
            'skip_actions' => 0,
            'sync_errors' => 0,
            'last_sync_at' => null,
            'mapping_types' => [],
            'exemption_statuses' => [],
            'published_statuses' => []
        ];

        $meltableProductIds = [];
        foreach ($rows as $row) {
            if (!empty($row['magento_sku'])) {
                $result['matched']++;
            } else {
                $result['unmatched']++;
            }
            if (isset($row['mapping_type']) && $row['mapping_type'] === 'custom_option' && empty($row['mapping_verified'])) {
                $result['mapping_unverified']++;
            }
            if (!empty($row['sync_enabled'])) {
                $result['sync_enabled']++;
            }
            if (!empty($row['is_eligible'])) {
                $result['ready_to_sync']++;
            }
            if (!empty($row['is_meltable'])) {
                $result['meltable_skus']++;
                if (!empty($row['product_id'])) {
                    $meltableProductIds[(int)$row['product_id']] = true;
                }
            }
            if (isset($row['seasonal_status']) && $row['seasonal_status'] === 'zero_period') {
                $result['seasonal_zero']++;
            }
            if (isset($row['sync_action']) && $row['sync_action'] === 'send') {
                $result['send_actions']++;
            } else {
                $result['skip_actions']++;
            }
            if (!empty($row['last_error'])) {
                $result['sync_errors']++;
            }
            if (
                !empty($row['last_synced_at']) &&
                ($result['last_sync_at'] === null || $row['last_synced_at'] > $result['last_sync_at'])
            ) {
                $result['last_sync_at'] = $row['last_synced_at'];
            }
            $this->increment($result['mapping_types'], isset($row['mapping_type']) ? $row['mapping_type'] : 'unknown');
            $this->increment($result['exemption_statuses'], isset($row['sku_exemption_status']) ? $row['sku_exemption_status'] : 'unknown');
            $this->increment($result['published_statuses'], isset($row['published_status']) && $row['published_status'] !== '' ? $row['published_status'] : 'unknown');
        }

        ksort($result['mapping_types']);
        ksort($result['exemption_statuses']);
        ksort($result['published_statuses']);
        $result['meltable_products'] = count($meltableProductIds);
        return $result;
    }

    private function increment(array &$values, $key)
    {
        $key = strtolower(trim((string)$key));
        if ($key === '') {
            $key = 'unknown';
        }
        if (!isset($values[$key])) {
            $values[$key] = 0;
        }
        $values[$key]++;
    }
}
