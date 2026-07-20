<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Sku;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\Exemption\StatusUpdater;
use Sandy\WalmartSync\Model\ResourceModel\WalmartSku\CollectionFactory;

class MassExemption extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';

    private $filter;
    private $collectionFactory;
    private $statusUpdater;

    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        StatusUpdater $statusUpdater
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->statusUpdater = $statusUpdater;
    }

    public function execute()
    {
        $status = $this->getRequest()->getParam('status');
        try {
            $status = $this->statusUpdater->normalize($status);
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $updated = 0;
            $errors = 0;
            foreach ($collection as $item) {
                try {
                    $record = $item->getData();
                    $this->statusUpdater->update($item->getData('walmart_sku'), $status, $record);
                    $updated++;
                } catch (\Exception $exception) {
                    $errors++;
                }
            }
            if ($updated) {
                $this->messageManager->addSuccessMessage(__('Updated %1 local exemption statuses to %2. No Walmart API data was changed.', $updated, $status));
            }
            if ($errors) {
                $this->messageManager->addErrorMessage(__('%1 selected SKUs could not be updated. Review Magento logs.', $errors));
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Unable to update exemption statuses: %1', $exception->getMessage()));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sandy_walmartsync/sku/index');
    }
}
