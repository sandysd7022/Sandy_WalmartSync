<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Exemption;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\RawFactory;
use Sandy\WalmartSync\Model\Exemption\Exporter;

class Download extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';

    private $exporter;
    private $rawFactory;

    public function __construct(Action\Context $context, Exporter $exporter, RawFactory $rawFactory)
    {
        parent::__construct($context);
        $this->exporter = $exporter;
        $this->rawFactory = $rawFactory;
    }

    public function execute()
    {
        try {
            $scope = $this->getRequest()->getParam('scope', 'all');
            $type = $this->getRequest()->getParam('type', 'request');
            $export = $this->exporter->execute(null, $scope);
            $path = $type === 'review' ? $export['review_path'] : $export['path'];
            $name = basename($path);
            $result = $this->rawFactory->create();
            $result->setHeader('Content-Type', 'text/csv; charset=UTF-8');
            $result->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
            $result->setContents(file_get_contents($path));
            return $result;
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Unable to create export: %1', $exception->getMessage()));
            return $this->resultRedirectFactory->create()->setPath('sandy_walmartsync/dashboard/index');
        }
    }
}
