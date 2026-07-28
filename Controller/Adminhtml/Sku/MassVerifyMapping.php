<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Sku;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\ResourceModel\WalmartSku\CollectionFactory;
use Sandy\WalmartSync\Model\SkuStorage;

class MassVerifyMapping extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';

    private $filter;
    private $collectionFactory;
    private $storage;

    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        SkuStorage $storage
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->storage = $storage;
    }

    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $entityIds = [];
            $ignored = 0;
            foreach ($collection as $item) {
                if (
                    (string)$item->getData('mapping_type') !== 'custom_option' ||
                    !(int)$item->getData('product_id') ||
                    !(int)$item->getData('option_type_id')
                ) {
                    $ignored++;
                    continue;
                }
                $entityIds[] = (int)$item->getId();
            }
            $updated = $this->storage->verifyMappingEntityIds($entityIds);
            if ($entityIds) {
                $this->messageManager->addSuccessMessage(__(
                    'Verified %1 selected custom-option mappings (%2 local rows changed). Run inventory preview to refresh quantities and actions. No Walmart API data was changed.',
                    count($entityIds),
                    $updated
                ));
            }
            if ($ignored) {
                $this->messageManager->addNoticeMessage(__(
                    'Ignored %1 selected rows because they were direct, unmatched, ambiguous, or incomplete mappings.',
                    $ignored
                ));
            }
            if (!$entityIds && !$ignored) {
                $this->messageManager->addNoticeMessage(__('No custom-option mappings were selected.'));
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Unable to verify selected mappings: %1', $exception->getMessage()));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sandy_walmartsync/sku/index');
    }
}
