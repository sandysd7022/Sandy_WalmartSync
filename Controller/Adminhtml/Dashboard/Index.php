<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';

    private $resultPageFactory;

    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Sandy_WalmartSync::dashboard');
        $page->getConfig()->getTitle()->prepend(__('Walmart Sync Dashboard'));
        return $page;
    }
}
