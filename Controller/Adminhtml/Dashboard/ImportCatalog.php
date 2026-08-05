<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Sandy\WalmartSync\Model\CatalogImporter;

class ImportCatalog extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';

    private $importer;

    public function __construct(Action\Context $context, CatalogImporter $importer)
    {
        parent::__construct($context);
        $this->importer = $importer;
    }

    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sandy_walmartsync/dashboard/index');
        }
        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            $result = $this->importer->execute();
            $this->messageManager->addSuccessMessage(__(
                'Catalog refresh completed: %1 records processed across %2 pages, %3 unique of %4 expected SKUs, %5 repeated records, %6 stale local rows removed, %7 errors. No Walmart data was changed.',
                $result['imported'],
                $result['pages'],
                $result['unique'],
                $result['expected'],
                $result['repeated'],
                $result['removed'],
                $result['errors']
            ));
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Catalog refresh failed: %1', $exception->getMessage()));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sandy_walmartsync/dashboard/index');
    }
}
