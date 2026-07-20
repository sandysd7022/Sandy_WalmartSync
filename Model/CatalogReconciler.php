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
            'mapping_types' => [],
            'exemption_statuses' => [],
            'published_statuses' => []
        ];

        foreach ($rows as $row) {
            if (!empty($row['magento_sku'])) {
                $result['matched']++;
            } else {
                $result['unmatched']++;
            }
            if (isset($row['mapping_type']) && $row['mapping_type'] === 'custom_option' && empty($row['mapping_verified'])) {
                $result['mapping_unverified']++;
            }
            $this->increment($result['mapping_types'], isset($row['mapping_type']) ? $row['mapping_type'] : 'unknown');
            $this->increment($result['exemption_statuses'], isset($row['sku_exemption_status']) ? $row['sku_exemption_status'] : 'unknown');
            $this->increment($result['published_statuses'], isset($row['published_status']) && $row['published_status'] !== '' ? $row['published_status'] : 'unknown');
        }

        ksort($result['mapping_types']);
        ksort($result['exemption_statuses']);
        ksort($result['published_statuses']);
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
