<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\ResourceModel\ItemCandidate\CollectionFactory;

abstract class MassAction extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::new_items';
    protected $filter;
    protected $collectionFactory;

    public function __construct(Action\Context $context, Filter $filter, CollectionFactory $collectionFactory)
    {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
    }

    protected function selectedIds()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        return array_map('intval', $collection->getAllIds());
    }

    protected function redirect()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sandy_walmartsync/item/index');
    }
}
