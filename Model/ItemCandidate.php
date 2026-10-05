<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\Model\AbstractModel;

class ItemCandidate extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Sandy\WalmartSync\Model\ResourceModel\ItemCandidate::class);
    }
}
