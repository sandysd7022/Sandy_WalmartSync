<?php
namespace Sandy\WalmartSync\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WalmartSku extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('sandy_walmartsync_sku', 'entity_id');
    }
}
