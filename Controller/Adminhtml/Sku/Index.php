<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Sku;

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
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Sandy_WalmartSync::sku_grid');
        $resultPage->getConfig()->getTitle()->prepend(__('Known Walmart SKUs'));
        return $resultPage;
    }
}
