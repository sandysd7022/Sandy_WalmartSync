<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\ItemCreationManager;
use Sandy\WalmartSync\Model\ResourceModel\ItemCandidate\CollectionFactory;

class MassSubmit extends MassAction
{
    private $manager;
    public function __construct(Action\Context $context, Filter $filter, CollectionFactory $collectionFactory, ItemCreationManager $manager)
    {
        parent::__construct($context, $filter, $collectionFactory);
        $this->manager = $manager;
    }
    public function execute()
    {
        try {
            $result = $this->manager->submitUnpublished($this->selectedIds());
            $this->messageManager->addSuccessMessage(__('Submitted %1 reviewed item(s) with the future unpublished hold date. Feed ID: %2', $result['count'], $result['feed_id']));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Walmart creation submission failed: %1', $exception->getMessage()));
        }
        return $this->redirect();
    }
}
