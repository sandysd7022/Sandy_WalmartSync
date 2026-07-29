<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\Model\Session;
use Magento\Framework\Controller\ResultFactory;
use Sandy\WalmartSync\Model\SafeBulkApproval;

class ApplySafeBulk extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';
    const SESSION_KEY = 'sandy_walmartsync_safe_bulk_preview';
    const PREVIEW_TTL = 1800;

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
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('sandy_walmartsync/dashboard/index');
        if ((string)$this->getRequest()->getParam('confirm_safe_bulk') !== '1') {
            $this->messageManager->addErrorMessage(
                __('Check the confirmation box before applying safe bulk sync approval.')
            );
            return $redirect;
        }

        $preview = $this->backendSession->getData(self::SESSION_KEY);
        if (
            !is_array($preview) ||
            empty($preview['signature']) ||
            empty($preview['created_at']) ||
            time() - (int)$preview['created_at'] > self::PREVIEW_TTL
        ) {
            $this->backendSession->unsetData(self::SESSION_KEY);
            $this->messageManager->addErrorMessage(
                __('The safe preview is missing or older than 30 minutes. Run Preview Safe Bulk Sync Approval again.')
            );
            return $redirect;
        }

        try {
            $result = $this->safeBulkApproval->apply($preview['signature']);
            $this->backendSession->unsetData(self::SESSION_KEY);
            $this->messageManager->addSuccessMessage(__(
                'Safe bulk sync approval completed: %1 Magento products enabled and %2 custom-option rows verified. %3 local Walmart SKU rows were refreshed. No Walmart API data was changed. Run the complete inventory preview before enabling live writes or cron.',
                $result['safe_products'],
                $result['verified_rows'],
                $result['enabled_rows']
            ));
        } catch (\Throwable $exception) {
            $this->backendSession->unsetData(self::SESSION_KEY);
            $this->messageManager->addErrorMessage(
                __('Safe bulk sync approval was not applied: %1', $exception->getMessage())
            );
        }
        return $redirect;
    }
}
