<?php
namespace Sandy\WalmartSync\Model\Inventory;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Sandy\WalmartSync\Model\Config;

class MeltableResolver
{
    private $config;
    private $categoryCollectionFactory;
    private $productResults = [];
    private $categoryPaths = [];

    public function __construct(Config $config, CollectionFactory $categoryCollectionFactory)
    {
        $this->config = $config;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    public function isMeltable(ProductInterface $product)
    {
        $productId = (int)$product->getId();
        if (array_key_exists($productId, $this->productResults)) {
            return $this->productResults[$productId];
        }

        $override = strtolower(trim((string)$product->getData('walmart_meltable_override')));
        if ($override === 'yes') {
            return $this->productResults[$productId] = true;
        }
        if ($override === 'no') {
            return $this->productResults[$productId] = false;
        }

        $selected = $this->config->getMeltableCategoryIds();
        if (!$selected) {
            return $this->productResults[$productId] = false;
        }

        $selectedLookup = array_fill_keys($selected, true);
        foreach ((array)$product->getCategoryIds() as $categoryId) {
            $categoryId = (int)$categoryId;
            if (isset($selectedLookup[$categoryId])) {
                return $this->productResults[$productId] = true;
            }
            foreach ($this->getCategoryPathIds($categoryId) as $pathId) {
                if (isset($selectedLookup[$pathId])) {
                    return $this->productResults[$productId] = true;
                }
            }
        }
        return $this->productResults[$productId] = false;
    }

    public function isSeasonalZeroActive($asOf = null)
    {
        if (!$this->config->isMeltableRestrictionEnabled()) {
            return false;
        }
        $timezone = new \DateTimeZone($this->config->getSeasonalTimezone());
        if ($asOf instanceof \DateTimeInterface) {
            $date = new \DateTimeImmutable($asOf->format('Y-m-d H:i:s'), $asOf->getTimezone());
            $date = $date->setTimezone($timezone);
        } elseif (is_string($asOf) && trim($asOf) !== '') {
            $date = new \DateTimeImmutable(trim($asOf), $timezone);
        } else {
            $date = new \DateTimeImmutable('now', $timezone);
        }

        $current = $date->format('m-d');
        $start = $this->config->getMeltableZeroStart();
        $end = $this->config->getMeltableZeroEnd();
        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }
        return $current >= $start || $current <= $end;
    }

    private function getCategoryPathIds($categoryId)
    {
        if (isset($this->categoryPaths[$categoryId])) {
            return $this->categoryPaths[$categoryId];
        }
        $collection = $this->categoryCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', $categoryId)->setPageSize(1);
        $category = $collection->getFirstItem();
        $path = $category->getId() ? array_map('intval', $category->getPathIds()) : [];
        $this->categoryPaths[$categoryId] = $path;
        return $path;
    }
}
