<?php
namespace Sandy\WalmartSync\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ItemCandidate extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('sandy_walmartsync_item_candidate', 'entity_id');
    }
}
