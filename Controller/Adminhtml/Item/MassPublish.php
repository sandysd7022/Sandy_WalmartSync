<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\ItemCreationManager;
use Sandy\WalmartSync\Model\ResourceModel\ItemCandidate\CollectionFactory;

class MassPublish extends MassAction
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
            $result = $this->manager->publish($this->selectedIds());
            $this->messageManager->addSuccessMessage(__('Submitted the publish update for %1 reviewed item(s). Feed ID: %2. Refresh status before checking Seller Center.', $result['count'], $result['feed_id']));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Walmart publish submission failed: %1', $exception->getMessage()));
        }
        return $this->redirect();
    }
}
