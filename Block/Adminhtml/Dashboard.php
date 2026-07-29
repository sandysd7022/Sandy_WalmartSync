<?php
namespace Sandy\WalmartSync\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Session;
use Sandy\WalmartSync\Model\CatalogReconciler;
use Sandy\WalmartSync\Model\Config;
use Sandy\WalmartSync\Controller\Adminhtml\Dashboard\PreviewSafeBulk;

class Dashboard extends Template
{
    private $reconciler;
    private $moduleConfig;
    private $backendSession;

    public function __construct(
        Context $context,
        CatalogReconciler $reconciler,
        Config $moduleConfig,
        Session $backendSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->reconciler = $reconciler;
        $this->moduleConfig = $moduleConfig;
        $this->backendSession = $backendSession;
    }

    public function getSummary()
    {
        return $this->reconciler->execute();
    }

    public function isModuleEnabled()
    {
        return $this->moduleConfig->isEnabled();
    }

    public function isWriteEnabled()
    {
        return $this->moduleConfig->isWriteEnabled();
    }

    public function isCronEnabled()
    {
        return $this->moduleConfig->isCronEnabled();
    }

    public function getSafeBulkPreview()
    {
        $preview = $this->backendSession->getData(PreviewSafeBulk::SESSION_KEY);
        if (
            !is_array($preview) ||
            empty($preview['created_at']) ||
            time() - (int)$preview['created_at'] > 1800
        ) {
            return null;
        }
        return $preview;
    }

}
