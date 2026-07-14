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
}
