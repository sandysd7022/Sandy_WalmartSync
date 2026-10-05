<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::new_items';
    private $resultPageFactory;

    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Sandy_WalmartSync::new_items');
        $page->getConfig()->getTitle()->prepend(__('New Walmart Items'));
        return $page;
    }
}
