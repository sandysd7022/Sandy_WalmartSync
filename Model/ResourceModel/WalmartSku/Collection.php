<?php
namespace Sandy\WalmartSync\Model\ResourceModel\WalmartSku;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Sandy\WalmartSync\Model\WalmartSku::class,
            \Sandy\WalmartSync\Model\ResourceModel\WalmartSku::class
        );
    }
}
