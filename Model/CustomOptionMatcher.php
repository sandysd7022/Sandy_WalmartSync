<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\App\ResourceConnection;

class CustomOptionMatcher
{
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function match($walmartSku)
    {
        $connection = $this->resource->getConnection();
        $matches = [];
        $valueSelect = $connection->select()
            ->from(['cptv' => $this->resource->getTableName('catalog_product_option_type_value')], ['option_type_id', 'option_id', 'option_sku' => 'sku'])
            ->join(['cpo' => $this->resource->getTableName('catalog_product_option')], 'cpo.option_id = cptv.option_id', ['product_id'])
            ->join(['cpe' => $this->resource->getTableName('catalog_product_entity')], 'cpe.entity_id = cpo.product_id', ['magento_sku' => 'sku'])
            ->joinLeft(
                ['cptt' => $this->resource->getTableName('catalog_product_option_type_title')],
                'cptt.option_type_id = cptv.option_type_id AND cptt.store_id = 0',
                ['option_title' => 'title']
            )
            ->where('cptv.sku = ?', $walmartSku);
        foreach ($connection->fetchAll($valueSelect) as $row) {
            $matches[] = $row;
        }

        $optionSelect = $connection->select()
            ->from(['cpo' => $this->resource->getTableName('catalog_product_option')], ['option_id', 'option_sku' => 'sku'])
            ->join(['cpe' => $this->resource->getTableName('catalog_product_entity')], 'cpe.entity_id = cpo.product_id', ['product_id' => 'entity_id', 'magento_sku' => 'sku'])
            ->joinLeft(
                ['cpot' => $this->resource->getTableName('catalog_product_option_title')],
                'cpot.option_id = cpo.option_id AND cpot.store_id = 0',
                ['option_title' => 'title']
            )
            ->where('cpo.sku = ?', $walmartSku);
        foreach ($connection->fetchAll($optionSelect) as $row) {
            $row['option_type_id'] = null;
            $matches[] = $row;
        }

        $unique = [];
        foreach ($matches as $match) {
            $key = (int)$match['product_id'] . ':' . (int)$match['option_id'] . ':' . (int)$match['option_type_id'];
            $unique[$key] = $match;
        }
        if (count($unique) !== 1) {
            return count($unique) > 1 ? ['mapping_type' => 'ambiguous_option'] : null;
        }
        $match = reset($unique);
        return [
            'mapping_type' => 'custom_option',
            'sku' => (string)$match['magento_sku'],
            'id' => (int)$match['product_id'],
            'option_id' => (int)$match['option_id'],
            'option_type_id' => $match['option_type_id'] === null ? null : (int)$match['option_type_id'],
            'option_title' => isset($match['option_title']) ? trim((string)$match['option_title']) : ''
        ];
    }
}
