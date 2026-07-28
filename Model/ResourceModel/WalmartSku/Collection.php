<?php
namespace Sandy\WalmartSync\Model\ResourceModel\WalmartSku;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Required by Magento UI mass-action filtering.
     *
     * Magento 2.3 can ask the collection for its ID field before the resource
     * model has initialized the select. Without this explicit value it builds
     * an invalid condition such as WHERE (`` IN (...)).
     *
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(
            \Sandy\WalmartSync\Model\WalmartSku::class,
            \Sandy\WalmartSync\Model\ResourceModel\WalmartSku::class
        );
    }
}
