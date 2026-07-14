<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class OperationLogger
{
    private $resource;
    private $logger;

    public function __construct(ResourceConnection $resource, LoggerInterface $logger)
    {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    public function log($action, $status, $walmartSku = null, $magentoSku = null, $previousValue = null, $newValue = null, $message = null, $correlationId = null)
    {
        $safeMessage = $this->sanitize($message);
        $this->resource->getConnection()->insert(
            $this->resource->getTableName('sandy_walmartsync_log'),
            [
                'magento_sku' => $magentoSku,
                'walmart_sku' => $walmartSku,
                'action' => $action,
                'previous_value' => $previousValue,
                'new_value' => $newValue,
                'status' => $status,
                'correlation_id' => $correlationId,
                'message' => $safeMessage
            ]
        );
        $context = ['action' => $action, 'status' => $status, 'walmart_sku' => $walmartSku];
        if ($status === 'error') {
            $this->logger->error($safeMessage ?: 'Walmart Sync operation failed.', $context);
        } else {
            $this->logger->info($safeMessage ?: 'Walmart Sync operation completed.', $context);
        }
    }

    private function sanitize($message)
    {
        return preg_replace('/(access[_ -]?token|client[_ -]?secret|authorization)[^,}\r\n]*/i', '$1=[redacted]', (string)$message);
    }
}
