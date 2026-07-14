<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\Model\AbstractModel;

class WalmartSku extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Sandy\WalmartSync\Model\ResourceModel\WalmartSku::class);
    }
}
