<?php
namespace Sandy\WalmartSync\Model\ResourceModel\ItemCandidate;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Magento 2.3 mass-action filtering asks for this value before the
     * resource model has initialized the select. Keep it explicit or Magento
     * can generate an invalid WHERE (`` IN (...)) condition.
     *
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(
            \Sandy\WalmartSync\Model\ItemCandidate::class,
            \Sandy\WalmartSync\Model\ResourceModel\ItemCandidate::class
        );
    }
}
