<?php
namespace Sandy\WalmartSync\Model\Inventory;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Sandy\WalmartSync\Model\Config;

class Eligibility
{
    private $productRepository;
    private $stockRegistry;
    private $config;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        Config $config
    ) {
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->config = $config;
    }

    public function evaluate(array $record)
    {
        $walmartSku = isset($record['walmart_sku']) ? (string)$record['walmart_sku'] : '';
        $magentoSku = !empty($record['magento_sku']) ? (string)$record['magento_sku'] : $walmartSku;
        $mappingType = isset($record['mapping_type']) ? (string)$record['mapping_type'] : 'unmatched';
        if ($mappingType === 'custom_option' && empty($record['mapping_verified'])) {
            return $this->result(false, 0, $magentoSku, 'Custom-option mapping is not manually verified.', isset($record['product_id']) ? (int)$record['product_id'] : null);
        }
        if ($mappingType === 'ambiguous_option') {
            return $this->result(false, 0, $magentoSku, 'Custom-option SKU matches more than one Magento option.', null);
        }
        try {
            $product = $this->productRepository->get($magentoSku, false, null, true);
        } catch (NoSuchEntityException $exception) {
            return $this->result(false, 0, $magentoSku, 'Magento product does not exist.', null);
        }
        if ((int)$product->getStatus() !== Status::STATUS_ENABLED) {
            return $this->result(false, 0, $magentoSku, 'Magento product is disabled.', (int)$product->getId());
        }
        if (!(bool)$product->getData('walmart_sync_enabled')) {
            return $this->result(false, 0, $magentoSku, 'Walmart Sync is not enabled for the product.', (int)$product->getId());
        }
        $exemptionStatus = $mappingType === 'custom_option'
            ? (isset($record['sku_exemption_status']) ? (string)$record['sku_exemption_status'] : 'unknown')
            : (string)$product->getData('walmart_exemption_status');
        if ($exemptionStatus !== 'approved') {
            return $this->result(false, 0, $magentoSku, 'Return exemption status is not Approved for this Walmart SKU.', (int)$product->getId());
        }
        if ((bool)$product->getData('walmart_force_zero')) {
            return $this->result(false, 0, $magentoSku, 'Product is manually forced to zero.', (int)$product->getId());
        }
        $configuredWalmartSku = trim((string)$product->getData('walmart_sku'));
        if ($mappingType !== 'custom_option' && $configuredWalmartSku !== '' && $configuredWalmartSku !== $walmartSku) {
            return $this->result(false, 0, $magentoSku, 'Configured Walmart SKU does not match this Walmart record.', (int)$product->getId());
        }
        $stockItem = $this->stockRegistry->getStockItem((int)$product->getId());
        if (!$stockItem->getIsInStock()) {
            return $this->result(false, 0, $magentoSku, 'Magento product is out of stock.', (int)$product->getId());
        }
        $rawQuantity = (float)$stockItem->getQty();
        $quantity = max(0, (int)floor($rawQuantity - $this->config->getInventoryBuffer()));
        if ($quantity <= 0) {
            return $this->result(false, 0, $magentoSku, 'Available quantity after buffer is zero.', (int)$product->getId());
        }
        return $this->result(true, $quantity, $magentoSku, 'Eligible for Walmart inventory synchronization.', (int)$product->getId());
    }

    private function result($eligible, $quantity, $magentoSku, $reason, $productId)
    {
        return [
            'eligible' => (bool)$eligible,
            'quantity' => (int)$quantity,
            'magento_sku' => $magentoSku,
            'product_id' => $productId,
            'reason' => $reason
        ];
    }
}
