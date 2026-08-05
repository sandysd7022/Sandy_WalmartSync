<?php
namespace Sandy\WalmartSync\Model\Inventory;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Sandy\WalmartSync\Model\Api\Client;
use Sandy\WalmartSync\Model\Config;
use Sandy\WalmartSync\Model\OperationLogger;
use Sandy\WalmartSync\Model\SkuStorage;

class Backup
{
    private $storage;
    private $client;
    private $config;
    private $directoryList;
    private $file;
    private $logger;

    public function __construct(
        SkuStorage $storage,
        Client $client,
        Config $config,
        DirectoryList $directoryList,
        File $file,
        OperationLogger $logger
    ) {
        $this->storage = $storage;
        $this->client = $client;
        $this->config = $config;
        $this->directoryList = $directoryList;
        $this->file = $file;
        $this->logger = $logger;
    }

    public function execute($sku = null, $limit = null, $scope = null)
    {
        if ($scope === 'published-unmatched') {
            if ($sku !== null && $sku !== '') {
                throw new \InvalidArgumentException('The published-unmatched scope cannot be combined with --sku.');
            }
            $records = $this->storage->getPublishedUnmatched($limit);
            return $this->executeRecords($records, 'published_unmatched', $scope);
        }
        if ($scope !== null && $scope !== '') {
            throw new \InvalidArgumentException('Unknown inventory backup scope.');
        }
        $records = $this->storage->getAll($sku, $limit);
        $suffix = $sku ? preg_replace('/[^A-Za-z0-9_.-]/', '_', $sku) : 'all';
        return $this->executeRecords($records, $suffix, $scope);
    }

    /**
     * Back up an already selected record set. The caller can then use this exact
     * same in-memory set for a write operation without re-querying candidates.
     */
    public function executeRecords(array $records, $suffix = 'selection', $scope = null)
    {
        $directory = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/export/walmart_sync';
        $this->file->checkAndCreateFolder($directory);
        $suffix = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$suffix);
        $path = $directory . '/walmart_inventory_' . $suffix . '_' . gmdate('Ymd_His') . '.csv';
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create Walmart inventory backup.');
        }
        fputcsv($handle, ['walmart_sku', 'item_id', 'ship_node', 'previous_quantity', 'magento_sku', 'scope', 'captured_at_utc', 'status', 'error']);
        $errors = 0;
        $captured = 0;
        $quantities = [];
        foreach ($records as $record) {
            try {
                $response = $this->client->getInventory($record['walmart_sku'], $this->config->getShipNode());
                $quantity = $this->extractQuantity($response);
                $quantities[(string)$record['walmart_sku']] = $quantity;
                fputcsv($handle, [$record['walmart_sku'], $record['item_id'], $this->config->getShipNode(), $quantity, $record['magento_sku'], $scope, gmdate('c'), 'success', '']);
                $this->storage->updateStatus($record['walmart_sku'], ['current_qty' => $quantity, 'last_sync_status' => 'backup_success', 'last_error' => null]);
                $captured++;
            } catch (\Exception $exception) {
                $errors++;
                fputcsv($handle, [$record['walmart_sku'], $record['item_id'], $this->config->getShipNode(), '', $record['magento_sku'], $scope, gmdate('c'), 'error', $exception->getMessage()]);
                $this->logger->log('inventory_backup', 'error', $record['walmart_sku'], $record['magento_sku'], null, null, $exception->getMessage(), $this->client->getLastCorrelationId());
            }
        }
        fclose($handle);
        $candidateSkus = array_map(function ($record) {
            return (string)$record['walmart_sku'];
        }, $records);
        sort($candidateSkus, SORT_STRING);
        $candidateHash = hash('sha256', implode("\n", $candidateSkus));
        $this->logger->log('inventory_backup', $errors ? 'partial' : 'success', $scope, null, null, $captured, sprintf('Backup saved to %s. Captured %d; errors %d; candidate hash %s.', $path, $captured, $errors, $candidateHash));
        return [
            'path' => $path,
            'captured' => $captured,
            'errors' => $errors,
            'total' => count($records),
            'quantities' => $quantities,
            'candidate_hash' => $candidateHash,
            'scope' => $scope
        ];
    }

    private function extractQuantity(array $response)
    {
        if (isset($response['quantity']['amount'])) {
            return (float)$response['quantity']['amount'];
        }
        if (isset($response['Inventory']['quantity']['amount'])) {
            return (float)$response['Inventory']['quantity']['amount'];
        }
        if (isset($response['inventory']['quantity']['amount'])) {
            return (float)$response['inventory']['quantity']['amount'];
        }
        throw new \RuntimeException('Walmart inventory response did not contain quantity.amount.');
    }
}
