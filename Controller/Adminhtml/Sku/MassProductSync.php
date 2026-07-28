<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Sku;

use Magento\Backend\App\Action;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Sandy\WalmartSync\Model\ResourceModel\WalmartSku\CollectionFactory;
use Sandy\WalmartSync\Model\SkuStorage;

class MassProductSync extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';

    private $filter;
    private $collectionFactory;
    private $productAction;
    private $storage;

    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ProductAction $productAction,
        SkuStorage $storage
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->productAction = $productAction;
        $this->storage = $storage;
    }

    public function execute()
    {
        $enabled = (bool)$this->getRequest()->getParam('enabled');
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $productIds = [];
            $ignored = 0;
            foreach ($collection as $item) {
                $mappingType = (string)$item->getData('mapping_type');
                $productId = (int)$item->getData('product_id');
                if (
                    !$productId ||
                    in_array($mappingType, ['unmatched', 'ambiguous_option'], true)
                ) {
                    $ignored++;
                    continue;
                }
                $productIds[$productId] = true;
            }
            $productIds = array_keys($productIds);
            if ($productIds) {
                $this->productAction->updateAttributes(
                    $productIds,
                    ['walmart_sync_enabled' => $enabled ? 1 : 0],
                    0
                );
                $affectedRows = $this->storage->updateProductSyncState($productIds, $enabled);
                $this->messageManager->addSuccessMessage(__(
                    '%1 Walmart Sync for %2 unique Magento products affecting %3 local Walmart SKU rows. No Walmart API data was changed.',
                    $enabled ? __('Enabled') : __('Disabled'),
                    count($productIds),
                    $affectedRows
                ));
            } else {
                $this->messageManager->addNoticeMessage(__('No mapped Magento products were selected.'));
            }
            if ($ignored) {
                $this->messageManager->addNoticeMessage(__(
                    'Ignored %1 selected unmatched or ambiguous rows.',
                    $ignored
                ));
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Unable to change product sync settings: %1', $exception->getMessage()));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sandy_walmartsync/sku/index');
    }
}
