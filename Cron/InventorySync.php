<?php
namespace Sandy\WalmartSync\Cron;

use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use Sandy\WalmartSync\Model\Config;
use Sandy\WalmartSync\Model\Inventory\Operator;

class InventorySync
{
    const LOCK_NAME = 'sandy_walmartsync_inventory_cron';

    private $config;
    private $operator;
    private $lockManager;
    private $logger;

    public function __construct(Config $config, Operator $operator, LockManagerInterface $lockManager, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->operator = $operator;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        if (!$this->config->isCronEnabled()) {
            return;
        }
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            $this->logger->warning('Walmart inventory cron skipped because a previous run is active.');
            return;
        }
        try {
            $this->operator->sync(null, null, true);
        } catch (\Exception $exception) {
            $this->logger->error('Walmart inventory cron failed: ' . $exception->getMessage());
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }
}
