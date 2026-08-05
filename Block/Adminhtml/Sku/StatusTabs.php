<?php
namespace Sandy\WalmartSync\Block\Adminhtml\Sku;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class StatusTabs extends Template
{
    private $resource;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->resource = $resource;
    }

    public function getStatusTabs()
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('sandy_walmartsync_sku');
        $status = "UPPER(TRIM(COALESCE(published_status, '')))";
        $select = $connection->select()->from($table, [
            'all_count' => new \Zend_Db_Expr('COUNT(*)'),
            'unpublished_count' => new \Zend_Db_Expr(
                "SUM(CASE WHEN {$status} = 'UNPUBLISHED' THEN 1 ELSE 0 END)"
            ),
            'error_count' => new \Zend_Db_Expr(
                "SUM(CASE WHEN {$status} = 'SYSTEM_PROBLEM' THEN 1 ELSE 0 END)"
            ),
            'draft_count' => new \Zend_Db_Expr(
                "SUM(CASE WHEN {$status} = 'DRAFT' THEN 1 ELSE 0 END)"
            ),
            'published_count' => new \Zend_Db_Expr(
                "SUM(CASE WHEN {$status} = 'PUBLISHED' THEN 1 ELSE 0 END)"
            )
        ]);
        $counts = $connection->fetchRow($select) ?: [];

        return [
            ['key' => '', 'label' => __('All'), 'count' => (int)($counts['all_count'] ?? 0)],
            ['key' => 'UNPUBLISHED', 'label' => __('Unpublished'), 'count' => (int)($counts['unpublished_count'] ?? 0)],
            ['key' => 'SYSTEM_PROBLEM', 'label' => __('Errors'), 'count' => (int)($counts['error_count'] ?? 0)],
            ['key' => 'DRAFT', 'label' => __('Drafts'), 'count' => (int)($counts['draft_count'] ?? 0)],
            ['key' => 'PUBLISHED', 'label' => __('Published'), 'count' => (int)($counts['published_count'] ?? 0)]
        ];
    }

    public function getStatusUrl($status)
    {
        $params = [];
        if ($status !== '') {
            $params['_query'] = ['filters' => ['published_status' => $status]];
        }
        return $this->getUrl('sandy_walmartsync/sku/index', $params);
    }

    public function getActiveStatus()
    {
        $filters = $this->getRequest()->getParam('filters', []);
        if (is_string($filters)) {
            parse_str($filters, $parsed);
            $filters = $parsed;
        }
        return is_array($filters) && isset($filters['published_status'])
            ? strtoupper(trim((string)$filters['published_status']))
            : '';
    }
}
