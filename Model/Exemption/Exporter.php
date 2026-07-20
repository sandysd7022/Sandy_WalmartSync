<?php
namespace Sandy\WalmartSync\Model\Exemption;

use Magento\Framework\App\Filesystem\DirectoryList;
use Sandy\WalmartSync\Model\SkuStorage;

class Exporter
{
    private $storage;
    private $directoryList;

    public function __construct(SkuStorage $storage, DirectoryList $directoryList)
    {
        $this->storage = $storage;
        $this->directoryList = $directoryList;
    }

    public function execute($file = null, $scope = 'all', $reason = 'Other', $notes = 'perishable')
    {
        $scope = strtolower(trim((string)$scope));
        if (!in_array($scope, ['all', 'new'], true)) {
            throw new \InvalidArgumentException('Scope must be all or new.');
        }
        $path = $this->resolvePath($file);
        $reviewPath = preg_replace('/\.csv$/i', '', $path) . '-review.csv';
        $this->ensureDirectory(dirname($path));

        $request = fopen($path, 'wb');
        $review = fopen($reviewPath, 'wb');
        if (!$request || !$review) {
            if (is_resource($request)) {
                fclose($request);
            }
            if (is_resource($review)) {
                fclose($review);
            }
            throw new \RuntimeException('Unable to create exemption export files.');
        }

        fputcsv($request, [
            'Request #', 'SKU', 'Exemption Reason',
            '**For Freight only** Package Weight (in pounds)',
            '**For Freight only** Package length or longest side (in inches)',
            '**For Freight only** Package length + girth (in inches)',
            'Product URL',
            "Notes\nAdd Explanation for Restriction Request if Other was used\n"
        ]);
        fputcsv($review, [
            'Request #', 'Walmart SKU', 'Walmart Product', 'Walmart Item ID', 'Published Status',
            'Magento SKU', 'Mapping Type', 'Mapping Verified', 'Exemption Status',
            'Product URL', 'Needs Product URL', 'Include In Request'
        ]);

        $requestNumber = 0;
        $reviewNumber = 0;
        $missingUrls = 0;
        $excludedPrevious = 0;
        foreach ($this->storage->getAll() as $row) {
            $status = isset($row['sku_exemption_status']) ? strtolower((string)$row['sku_exemption_status']) : 'unknown';
            $isNew = !in_array($status, ['previously_requested', 'pending', 'approved', 'rejected'], true);
            $include = $scope === 'all' || $isNew;
            if (!$include) {
                $excludedPrevious++;
            }
            $url = $this->productUrl(isset($row['item_id']) ? $row['item_id'] : '');
            if ($include && $url === '') {
                $missingUrls++;
            }
            if ($include) {
                $requestNumber++;
                fputcsv($request, [$requestNumber, $row['walmart_sku'], $reason, '', '', '', $url, $notes]);
            }
            $reviewNumber++;
            fputcsv($review, [
                $reviewNumber,
                $row['walmart_sku'],
                isset($row['product_name']) ? $row['product_name'] : '',
                isset($row['item_id']) ? $row['item_id'] : '',
                isset($row['published_status']) ? $row['published_status'] : '',
                isset($row['magento_sku']) ? $row['magento_sku'] : '',
                isset($row['mapping_type']) ? $row['mapping_type'] : '',
                !empty($row['mapping_verified']) ? 'Yes' : 'No',
                $status,
                $url,
                $url === '' ? 'Yes' : 'No',
                $include ? 'Yes' : 'No'
            ]);
        }
        fclose($request);
        fclose($review);

        return [
            'path' => $path,
            'review_path' => $reviewPath,
            'request_rows' => $requestNumber,
            'review_rows' => $reviewNumber,
            'missing_urls' => $missingUrls,
            'excluded_previous' => $excludedPrevious
        ];
    }

    private function resolvePath($file)
    {
        $file = trim((string)$file);
        if ($file === '') {
            return $this->directoryList->getPath(DirectoryList::VAR_DIR) . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . 'walmart-return-exemption-request.csv';
        }
        if (strtolower(substr($file, -4)) !== '.csv') {
            $file .= '.csv';
        }
        if ($this->isAbsolute($file)) {
            return $file;
        }
        return $this->directoryList->getRoot() . DIRECTORY_SEPARATOR . ltrim($file, '/\\');
    }

    private function isAbsolute($path)
    {
        return substr($path, 0, 1) === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private function ensureDirectory($directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create export directory: ' . $directory);
        }
    }

    private function productUrl($itemId)
    {
        $itemId = trim((string)$itemId);
        return $itemId !== '' && ctype_digit($itemId) ? 'https://www.walmart.com/ip/' . $itemId : '';
    }
}
