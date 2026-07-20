<?php
namespace Sandy\WalmartSync\Model\Exemption;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Sandy\WalmartSync\Model\SkuStorage;

class StatusUpdater
{
    private $storage;
    private $productRepository;

    public function __construct(SkuStorage $storage, ProductRepositoryInterface $productRepository)
    {
        $this->storage = $storage;
        $this->productRepository = $productRepository;
    }

    public function update($sku, $status, array $record = null)
    {
        $status = $this->normalize($status);
        $record = $record ?: $this->storage->getByWalmartSku($sku);
        if (!$record) {
            throw new \InvalidArgumentException('Walmart SKU is not present in the local catalog: ' . $sku);
        }
        $this->storage->updateStatus($sku, ['sku_exemption_status' => $status]);
        if (isset($record['mapping_type']) && $record['mapping_type'] !== 'custom_option' && !empty($record['magento_sku'])) {
            $product = $this->productRepository->get($record['magento_sku'], false, null, true);
            $product->setData('walmart_exemption_status', $status);
            $this->productRepository->save($product);
        }
        return $status;
    }

    public function normalize($value)
    {
        $value = strtolower(trim((string)$value));
        $value = str_replace(['-', ' '], '_', $value);
        $aliases = [
            'previouslyrequested' => 'previously_requested',
            'previous_request' => 'previously_requested',
            'submitted' => 'pending',
            'pending_review' => 'pending',
            'in_review' => 'pending',
            'denied' => 'rejected',
            'declined' => 'rejected'
        ];
        if (isset($aliases[$value])) {
            $value = $aliases[$value];
        }
        if (!in_array($value, ['unknown', 'previously_requested', 'pending', 'approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Invalid exemption status: ' . $value);
        }
        return $value;
    }
}
