<?php
namespace Sandy\WalmartSync\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class StockItemSaveAfter implements ObserverInterface
{
    private $resource;
    private $logger;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    )
    {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            $stockItem = $observer->getEvent()->getItem();
            if (!$stockItem) {
                return;
            }

            $productId = (int)$stockItem->getProductId();
            if (!$productId) {
                return;
            }

            $quantity = max(0, (int)floor((float)$stockItem->getQty()));
            $this->resource->getConnection()->update(
                $this->resource->getTableName('sandy_walmartsync_sku'),
                [
                    'magento_qty' => $quantity,
                    'is_eligible' => 0,
                    'calculated_qty' => 0,
                    'sync_action' => 'skip',
                    'eligibility_reason' => 'Magento stock changed; waiting for inventory preview or cron calculation.'
                ],
                ['product_id = ?' => $productId]
            );
        } catch (\Throwable $exception) {
            // A local reporting-grid refresh must never block Magento stock saves.
            $this->logger->error(
                'Walmart Sync could not refresh local stock metadata after a Magento stock save.',
                ['exception' => $exception]
            );
        }
    }
}
