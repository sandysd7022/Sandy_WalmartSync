<?php
namespace Sandy\WalmartSync\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Sandy\WalmartSync\Model\CatalogReconciler;
use Sandy\WalmartSync\Model\Config;

class Dashboard extends Template
{
    private $reconciler;
    private $moduleConfig;

    public function __construct(Context $context, CatalogReconciler $reconciler, Config $moduleConfig, array $data = [])
    {
        parent::__construct($context, $data);
        $this->reconciler = $reconciler;
        $this->moduleConfig = $moduleConfig;
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

    public function getStatusOptions()
    {
        return [
            '' => __('Use Status column from CSV'),
            'previously_requested' => __('Previously Requested'),
            'pending' => __('Pending Review'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'unknown' => __('Unknown')
        ];
    }
}
