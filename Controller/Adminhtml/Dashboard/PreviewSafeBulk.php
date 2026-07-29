<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\Model\Session;
use Magento\Framework\Controller\ResultFactory;
use Sandy\WalmartSync\Model\SafeBulkApproval;

class PreviewSafeBulk extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';
    const SESSION_KEY = 'sandy_walmartsync_safe_bulk_preview';

    private $safeBulkApproval;
    private $backendSession;

    public function __construct(
        Action\Context $context,
        SafeBulkApproval $safeBulkApproval,
        Session $backendSession
    ) {
        parent::__construct($context);
        $this->safeBulkApproval = $safeBulkApproval;
        $this->backendSession = $backendSession;
    }

    public function execute()
    {
        try {
            $preview = $this->safeBulkApproval->preview();
            unset(
                $preview['safe_direct_ids'],
                $preview['safe_custom_ids'],
                $preview['safe_product_ids']
            );
            $preview['created_at'] = time();
            $this->backendSession->setData(self::SESSION_KEY, $preview);
            $this->messageManager->addSuccessMessage(__(
                'Safe preview complete: %1 direct rows, %2 unique custom-option rows and %3 Magento products qualify. %4 published rows remain for manual review. Nothing was changed in Magento or Walmart.',
                $preview['safe_direct_rows'],
                $preview['safe_custom_rows'],
                $preview['safe_products'],
                $preview['manual_review_rows']
            ));
        } catch (\Throwable $exception) {
            $this->backendSession->unsetData(self::SESSION_KEY);
            $this->messageManager->addErrorMessage(
                __('Unable to build the safe bulk preview: %1', $exception->getMessage())
            );
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('sandy_walmartsync/dashboard/index');
    }
}
