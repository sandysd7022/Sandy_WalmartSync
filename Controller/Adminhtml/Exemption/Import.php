<?php
namespace Sandy\WalmartSync\Controller\Adminhtml\Exemption;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Math\Random;
use Sandy\WalmartSync\Model\Exemption\Importer;

class Import extends Action
{
    const ADMIN_RESOURCE = 'Sandy_WalmartSync::operations';

    private $importer;
    private $directoryList;
    private $random;

    public function __construct(Action\Context $context, Importer $importer, DirectoryList $directoryList, Random $random)
    {
        parent::__construct($context);
        $this->importer = $importer;
        $this->directoryList = $directoryList;
        $this->random = $random;
    }

    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirectFactory->create()->setPath('sandy_walmartsync/dashboard/index');
        }
        $path = null;
        try {
            $file = $this->getRequest()->getFiles('status_file');
            if (!is_array($file) || empty($file['tmp_name']) || !isset($file['name'])) {
                throw new \InvalidArgumentException('Select a CSV file.');
            }
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
                throw new \InvalidArgumentException('Only CSV files are accepted.');
            }
            $directory = $this->directoryList->getPath(DirectoryList::VAR_DIR) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'walmart-exemption';
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create the temporary import directory.');
            }
            $path = $directory . DIRECTORY_SEPARATOR . $this->random->getRandomString(24) . '.csv';
            if (!move_uploaded_file($file['tmp_name'], $path) && !rename($file['tmp_name'], $path)) {
                throw new \RuntimeException('Unable to save the uploaded CSV.');
            }
            $apply = (bool)$this->getRequest()->getParam('apply_changes');
            if ($apply && !(bool)$this->getRequest()->getParam('confirm_changes')) {
                throw new \InvalidArgumentException('Confirm the status changes before applying the CSV.');
            }
            $defaultStatus = $this->getRequest()->getParam('default_status');
            $result = $this->importer->execute($path, $defaultStatus, $apply);
            if ($result['errors']) {
                foreach (array_slice($result['errors'], 0, 20) as $error) {
                    $this->messageManager->addErrorMessage($error);
                }
                $this->messageManager->addErrorMessage(__('No statuses were changed because the CSV contains errors.'));
            } elseif ($apply) {
                $this->messageManager->addSuccessMessage(__('Updated %1 local exemption statuses. No Walmart API data was changed.', $result['updated']));
            } else {
                $this->messageManager->addSuccessMessage(__('CSV validation passed for %1 SKUs. This was a preview; no status was changed.', $result['processed']));
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Exemption CSV failed: %1', $exception->getMessage()));
        }
        if ($path && is_file($path)) {
            unlink($path);
        }
        return $this->resultRedirectFactory->create()->setPath('sandy_walmartsync/dashboard/index');
    }
}
