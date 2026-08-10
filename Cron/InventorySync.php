<?php
namespace Sandy\WalmartSync\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;
use Sandy\WalmartSync\Model\CatalogImporter;
use Sandy\WalmartSync\Model\Config;
use Sandy\WalmartSync\Model\Inventory\Operator;

class InventorySync
{
    private $config;
    private $catalogImporter;
    private $operator;
    private $logger;
    private $directoryList;

    public function __construct(
        Config $config,
        CatalogImporter $catalogImporter,
        Operator $operator,
        LoggerInterface $logger,
        DirectoryList $directoryList
    ) {
        $this->config = $config;
        $this->catalogImporter = $catalogImporter;
        $this->operator = $operator;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
    }

    public function execute()
    {
        if (!$this->config->isCronEnabled()) {
            $this->logger->warning('Walmart inventory cron did not run because the module, write operations, or inventory cron is disabled.');
            return;
        }

        $lockHandle = null;
        $startedAt = microtime(true);
        try {
            $lockHandle = $this->acquireLock();
            if ($lockHandle === null) {
                $this->logger->warning('Walmart scheduled run skipped because another Walmart catalog/inventory run is still active.');
                return;
            }

            // Refresh and validate the complete Walmart catalog before calculating
            // inventory. Any catalog failure stops this run before remote writes.
            $catalogStartedAt = microtime(true);
            $catalog = $this->catalogImporter->execute(null, true);
            $catalogSeconds = microtime(true) - $catalogStartedAt;
            $expected = isset($catalog['expected']) ? (int)$catalog['expected'] : 0;
            $unique = isset($catalog['unique']) ? (int)$catalog['unique'] : 0;
            $catalogErrors = isset($catalog['errors']) ? (int)$catalog['errors'] : 0;
            if ($catalogErrors > 0 || $expected < 1 || $unique !== $expected) {
                throw new \RuntimeException(sprintf(
                    'Automatic Walmart catalog refresh was incomplete: expected %d unique SKU(s), received %d, errors %d. Inventory was not sent.',
                    $expected,
                    $unique,
                    $catalogErrors
                ));
            }

            $this->logger->info(sprintf(
                'Automatic Walmart catalog refresh completed: %d SKU(s), %d page(s), %d stale local row(s) removed in %.1f second(s).',
                $unique,
                isset($catalog['pages']) ? (int)$catalog['pages'] : 0,
                isset($catalog['removed']) ? (int)$catalog['removed'] : 0,
                $catalogSeconds
            ));

            // sync() performs a fresh preview first, recalculating mappings,
            // Magento stock, seasonal rules, quantities and SEND/SKIP actions.
            $syncStartedAt = microtime(true);
            $result = $this->operator->sync(null, null, true);
            $syncSeconds = microtime(true) - $syncStartedAt;
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
                'Walmart scheduled run completed: catalog %d SKU(s), evaluated %d inventory row(s), sent %d, skipped %d; catalog %.1fs, inventory %.1fs, total %.1fs.',
                $unique,
                count($result['results']),
                $sent,
                $skipped,
                $catalogSeconds,
                $syncSeconds,
                microtime(true) - $startedAt
            ));
        } catch (\Exception $exception) {
            $this->logger->error('Walmart inventory cron failed: ' . $exception->getMessage());
            throw $exception;
        } finally {
            if (is_resource($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
    }

    /**
     * Use a dedicated file lock rather than Magento's database LockManager.
     * Magento 2.3 may already hold the cron-group DB lock on this connection.
     */
    private function acquireLock()
    {
        $path = $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . DIRECTORY_SEPARATOR . 'walmart_sync_cron.lock';
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open the Walmart scheduled-run lock file: ' . $path);
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }
}
