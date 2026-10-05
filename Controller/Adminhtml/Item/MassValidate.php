<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\ItemCreationManager;
use Sandy\WalmartSync\Model\ResourceModel\ItemCandidate\CollectionFactory;

class MassValidate extends MassAction
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
            $result = $this->manager->validate($this->selectedIds());
            $this->messageManager->addSuccessMessage(__('%1 payloads validated; %2 blocked. No Walmart API was called.', $result['valid'], $result['blocked']));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Validation failed: %1', $exception->getMessage()));
        }
        return $this->redirect();
    }
}
