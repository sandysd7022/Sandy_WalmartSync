<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Sandy\WalmartSync\Model\ItemCreationManager;

class RefreshCandidates extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::new_items';
    private $manager;

    public function __construct(Action\Context $context, ItemCreationManager $manager)
    {
        parent::__construct($context);
        $this->manager = $manager;
    }

    public function execute()
    {
        try {
            $result = $this->manager->refreshCandidates();
            $this->messageManager->addSuccessMessage(__(
                'Discovery completed: %1 enabled simple Collection products; %2 supported candidates, %3 unsupported and blocked, %4 already in the imported Walmart catalog. No Walmart API was called.',
                $result['discovered'], $result['ready'], $result['blocked'], $result['existing']
            ));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Candidate discovery failed: %1', $exception->getMessage()));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sandy_walmartsync/item/index');
    }
}
