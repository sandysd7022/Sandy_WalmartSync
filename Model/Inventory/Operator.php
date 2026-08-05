<?php
namespace Sandy\WalmartSync\Model\Inventory;

use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Sandy\WalmartSync\Model\Api\Client;
use Sandy\WalmartSync\Model\Config;
use Sandy\WalmartSync\Model\OperationLogger;
use Sandy\WalmartSync\Model\SkuStorage;

class Operator
{
    private $storage;
    private $eligibility;
    private $backup;
    private $client;
    private $config;
    private $logger;
    private $productAction;

    public function __construct(
        SkuStorage $storage,
        Eligibility $eligibility,
        Backup $backup,
        Client $client,
        Config $config,
        OperationLogger $logger,
        ProductAction $productAction
    ) {
        $this->storage = $storage;
        $this->eligibility = $eligibility;
        $this->backup = $backup;
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
        $this->productAction = $productAction;
    }

    public function preview($sku = null, $limit = null, $asOf = null)
    {
        $results = [];
        foreach ($this->storage->getAll($sku, $limit) as $record) {
            $decision = $this->eligibility->evaluate($record, $asOf);
            if ($asOf === null || $asOf === '') {
                $this->storage->updateStatus($record['walmart_sku'], [
                    'is_eligible' => $decision['eligible'] ? 1 : 0,
                    'eligibility_reason' => $decision['reason'],
                    'magento_sku' => $decision['magento_sku'],
                    'sync_enabled' => $decision['sync_enabled'] ? 1 : 0,
                    'is_meltable' => $decision['is_meltable'] ? 1 : 0,
                    'seasonal_status' => $decision['seasonal_status'],
                    'magento_qty' => $decision['magento_qty'],
                    'calculated_qty' => $decision['quantity'],
                    'sync_action' => $decision['sync_action']
                ]);
            }
            $results[] = array_merge($decision, [
                'walmart_sku' => $record['walmart_sku'],
                'current_qty' => $record['current_qty'],
                'item_id' => $record['item_id']
            ]);
        }
        return $results;
    }

    public function zero($sku = null, $limit = null, $execute = false, $scope = null, $expectedCandidateHash = null)
    {
        $records = $scope === 'published-unmatched'
            ? $this->storage->getPublishedUnmatched($limit)
            : $this->storage->getAll($sku, $limit);
        $candidateHash = $this->getCandidateHash($records);
        if (!$execute) {
            $results = [];
            foreach ($records as $record) {
                $results[] = [
                    'walmart_sku' => $record['walmart_sku'],
                    'previous_qty' => $record['current_qty'],
                    'new_qty' => 0,
                    'status' => 'dry_run'
                ];
            }
            return ['backup' => null, 'results' => $results, 'errors' => 0, 'scope' => $scope, 'total' => count($records), 'candidate_hash' => $candidateHash];
        }
        if (!$this->config->isWriteEnabled()) {
            throw new LocalizedException(__('Walmart write operations are disabled.'));
        }
        if ($scope === 'published-unmatched' && (string)$expectedCandidateHash !== $candidateHash) {
            throw new LocalizedException(__('Candidate set changed or was not confirmed. Run the dry run again and provide its exact candidate hash.'));
        }
        $suffix = $scope === 'published-unmatched'
            ? 'published_unmatched'
            : ($sku ? preg_replace('/[^A-Za-z0-9_.-]/', '_', $sku) : 'all');
        $backup = $this->backup->executeRecords($records, $suffix, $scope);
        if ($backup['total'] === 0) {
            throw new LocalizedException(__('No local Walmart SKUs matched this operation. Import the catalog first.'));
        }
        if ($backup['errors'] > 0 || $backup['captured'] !== $backup['total']) {
            throw new LocalizedException(__('Zero operation aborted because the inventory backup was incomplete.'));
        }
        $results = [];
        $errors = 0;
        foreach ($records as $record) {
            try {
                $recordSku = (string)$record['walmart_sku'];
                $previous = array_key_exists($recordSku, $backup['quantities'])
                    ? $backup['quantities'][$recordSku]
                    : $record['current_qty'];
                $this->client->updateInventory($record['walmart_sku'], 0, $this->config->getShipNode());
                $this->storage->updateStatus($record['walmart_sku'], [
                    'current_qty' => 0,
                    'last_sync_status' => 'zero_success',
                    'last_error' => null,
                    'last_synced_at' => gmdate('Y-m-d H:i:s')
                ]);
                $this->logger->log('inventory_zero', 'success', $record['walmart_sku'], $record['magento_sku'], $previous, 0, 'Walmart inventory set to zero.', $this->client->getLastCorrelationId());
                $results[] = ['walmart_sku' => $record['walmart_sku'], 'previous_qty' => $previous, 'new_qty' => 0, 'status' => 'success'];
            } catch (\Exception $exception) {
                $errors++;
                $this->storage->updateStatus($record['walmart_sku'], ['last_sync_status' => 'zero_error', 'last_error' => $exception->getMessage()]);
                $this->logger->log('inventory_zero', 'error', $record['walmart_sku'], $record['magento_sku'], $record['current_qty'], 0, $exception->getMessage(), $this->client->getLastCorrelationId());
                $results[] = ['walmart_sku' => $record['walmart_sku'], 'previous_qty' => $record['current_qty'], 'new_qty' => 0, 'status' => 'error', 'error' => $exception->getMessage()];
            }
        }
        return ['backup' => $backup, 'results' => $results, 'errors' => $errors, 'scope' => $scope, 'total' => count($records), 'candidate_hash' => $candidateHash];
    }

    public function sync($sku = null, $limit = null, $execute = false)
    {
        $preview = $this->preview($sku, $limit);
        if (!$execute) {
            return ['results' => $preview, 'errors' => 0];
        }
        if (!$this->config->isWriteEnabled()) {
            throw new LocalizedException(__('Walmart write operations are disabled.'));
        }
        $results = [];
        $errors = 0;
        foreach ($preview as $decision) {
            if ($decision['sync_action'] !== 'send') {
                $decision['sent_quantity'] = null;
                $decision['status'] = 'skipped';
                $results[] = $decision;
                continue;
            }
            $quantity = $decision['quantity'];
            try {
                $this->client->updateInventory($decision['walmart_sku'], $quantity, $this->config->getShipNode());
                $this->storage->updateStatus($decision['walmart_sku'], [
                    'current_qty' => $quantity,
                    'last_sync_status' => 'sync_success',
                    'last_error' => null,
                    'last_synced_at' => gmdate('Y-m-d H:i:s')
                ]);
                $this->logger->log('inventory_sync', 'success', $decision['walmart_sku'], $decision['magento_sku'], $decision['current_qty'], $quantity, $decision['reason'], $this->client->getLastCorrelationId());
                $this->updateProductSyncResult($decision, null);
                $decision['sent_quantity'] = $quantity;
                $decision['status'] = 'success';
            } catch (\Exception $exception) {
                $errors++;
                $this->storage->updateStatus($decision['walmart_sku'], ['last_sync_status' => 'sync_error', 'last_error' => $exception->getMessage()]);
                $this->logger->log('inventory_sync', 'error', $decision['walmart_sku'], $decision['magento_sku'], $decision['current_qty'], $quantity, $exception->getMessage(), $this->client->getLastCorrelationId());
                $this->updateProductSyncResult($decision, $exception->getMessage());
                $decision['sent_quantity'] = null;
                $decision['status'] = 'error';
                $decision['error'] = $exception->getMessage();
            }
            $results[] = $decision;
        }
        return ['results' => $results, 'errors' => $errors];
    }

    private function updateProductSyncResult(array $decision, $error)
    {
        if (empty($decision['product_id'])) {
            return;
        }
        $this->productAction->updateAttributes(
            [(int)$decision['product_id']],
            ['walmart_last_sync_at' => gmdate('Y-m-d H:i:s'), 'walmart_last_error' => $error],
            0
        );
    }

    private function getCandidateHash(array $records)
    {
        $skus = array_map(function ($record) {
            return (string)$record['walmart_sku'];
        }, $records);
        sort($skus, SORT_STRING);
        return hash('sha256', implode("\n", $skus));
    }
}
