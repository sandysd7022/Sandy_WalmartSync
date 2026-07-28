<?php
namespace Sandy\WalmartSync\Cron;

use Psr\Log\LoggerInterface;
use Sandy\WalmartSync\Model\Config;
use Sandy\WalmartSync\Model\Inventory\Operator;

class InventorySync
{
    private $config;
    private $operator;
    private $logger;

    public function __construct(Config $config, Operator $operator, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->operator = $operator;
        $this->logger = $logger;
    }

    public function execute()
    {
        if (!$this->config->isCronEnabled()) {
            $this->logger->warning('Walmart inventory cron did not run because the module, write operations, or inventory cron is disabled.');
            return;
        }

        try {
            $result = $this->operator->sync(null, null, true);
            $sent = 0;
            $skipped = 0;
            foreach ($result['results'] as $row) {
                if (isset($row['status']) && $row['status'] === 'success') {
                    $sent++;
                } elseif (isset($row['status']) && $row['status'] === 'skipped') {
                    $skipped++;
                }
            }

            if (!empty($result['errors'])) {
                throw new \RuntimeException(sprintf(
                    'Walmart inventory cron completed with %d item error(s); sent %d and skipped %d. Review the SKU grid Last Error column.',
                    (int)$result['errors'],
                    $sent,
                    $skipped
                ));
            }

            $this->logger->info(sprintf(
                'Walmart inventory cron completed: evaluated %d row(s), sent %d, skipped %d.',
                count($result['results']),
                $sent,
                $skipped
            ));
        } catch (\Exception $exception) {
            $this->logger->error('Walmart inventory cron failed: ' . $exception->getMessage());
            throw $exception;
        }
    }
}
