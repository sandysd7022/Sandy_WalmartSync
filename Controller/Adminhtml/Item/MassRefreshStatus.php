<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\ItemCreationManager;
use Sandy\WalmartSync\Model\ResourceModel\ItemCandidate\CollectionFactory;

class MassRefreshStatus extends MassAction
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
            $count = $this->manager->refreshStatus($this->selectedIds());
            $this->messageManager->addSuccessMessage(__('Refreshed Walmart feed status for %1 row(s). This action is read-only.', $count));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Feed status refresh failed: %1', $exception->getMessage()));
        }
        return $this->redirect();
    }
}
