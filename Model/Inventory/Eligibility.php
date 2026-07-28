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
    private $meltableResolver;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        Config $config,
        MeltableResolver $meltableResolver
    ) {
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->config = $config;
        $this->meltableResolver = $meltableResolver;
    }

    public function evaluate(array $record, $asOf = null)
    {
        $walmartSku = isset($record['walmart_sku']) ? (string)$record['walmart_sku'] : '';
        $magentoSku = !empty($record['magento_sku']) ? (string)$record['magento_sku'] : $walmartSku;
        $mappingType = isset($record['mapping_type']) ? (string)$record['mapping_type'] : 'unmatched';
        if ($mappingType === 'ambiguous_option') {
            return $this->result(false, 0, $magentoSku, 'Custom-option SKU matches more than one Magento option.', null);
        }
        try {
            $product = $this->productRepository->get($magentoSku, false, null, true);
        } catch (NoSuchEntityException $exception) {
            return $this->result(false, 0, $magentoSku, 'Magento product does not exist.', null);
        }
        $productId = (int)$product->getId();
        $syncEnabled = (bool)$product->getData('walmart_sync_enabled');
        $isMeltable = $this->meltableResolver->isMeltable($product);
        $seasonalZero = $isMeltable && $this->meltableResolver->isSeasonalZeroActive($asOf);
        $metadata = [
            'sync_enabled' => $syncEnabled,
            'is_meltable' => $isMeltable,
            'seasonal_status' => $isMeltable ? ($seasonalZero ? 'zero_period' : 'selling_period') : 'not_meltable',
            'magento_qty' => 0
        ];
        $stockItem = $this->stockRegistry->getStockItem($productId);
        $metadata['magento_qty'] = max(0, (int)floor((float)$stockItem->getQty()));
        if ($mappingType === 'custom_option' && empty($record['mapping_verified'])) {
            return $this->result(false, 0, $magentoSku, 'Custom-option mapping is not manually verified.', $productId, $metadata);
        }
        if (!$syncEnabled) {
            return $this->result(false, 0, $magentoSku, 'Walmart Sync is not enabled for the product.', $productId, $metadata);
        }
        $configuredWalmartSku = trim((string)$product->getData('walmart_sku'));
        if ($mappingType !== 'custom_option' && $configuredWalmartSku !== '' && $configuredWalmartSku !== $walmartSku) {
            return $this->result(false, 0, $magentoSku, 'Configured Walmart SKU does not match this Walmart record.', $productId, $metadata);
        }
        $metadata['sync_action'] = 'send';
        if ((int)$product->getStatus() !== Status::STATUS_ENABLED) {
            return $this->result(true, 0, $magentoSku, 'Magento product is disabled; Walmart quantity will be zero.', $productId, $metadata);
        }
        if ((bool)$product->getData('walmart_force_zero')) {
            $metadata['seasonal_status'] = 'manual_zero';
            return $this->result(true, 0, $magentoSku, 'Product is manually forced to zero.', $productId, $metadata);
        }
        if ($seasonalZero) {
            return $this->result(
                true,
                0,
                $magentoSku,
                'Meltable seasonal restriction is active; Walmart quantity is forced to zero.',
                $productId,
                $metadata
            );
        }
        if (!$stockItem->getIsInStock()) {
            return $this->result(true, 0, $magentoSku, 'Magento product is out of stock; Walmart quantity will be zero.', $productId, $metadata);
        }
        $rawQuantity = (float)$stockItem->getQty();
        $quantity = max(0, (int)floor($rawQuantity - $this->config->getInventoryBuffer()));
        if ($quantity <= 0) {
            return $this->result(true, 0, $magentoSku, 'Available quantity after buffer is zero.', $productId, $metadata);
        }
        $reason = $isMeltable
            ? 'Meltable selling period is active; eligible for Walmart inventory synchronization.'
            : 'Eligible for Walmart inventory synchronization.';
        return $this->result(true, $quantity, $magentoSku, $reason, $productId, $metadata);
    }

    private function result($eligible, $quantity, $magentoSku, $reason, $productId, array $metadata = [])
    {
        return array_merge([
            'eligible' => (bool)$eligible,
            'quantity' => (int)$quantity,
            'magento_sku' => $magentoSku,
            'product_id' => $productId,
            'reason' => $reason,
            'sync_enabled' => false,
            'is_meltable' => false,
            'seasonal_status' => 'unknown',
            'magento_qty' => 0,
            'sync_action' => 'skip'
        ], $metadata);
    }
}
