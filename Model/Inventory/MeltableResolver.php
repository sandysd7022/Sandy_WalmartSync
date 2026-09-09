<?php
namespace Sandy\WalmartSync\Model\Inventory;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Sandy\WalmartSync\Model\Config;

class MeltableResolver
{
    private $config;
    private $categoryCollectionFactory;
    private $eavConfig;
    private $productResults = [];
    private $categoryPaths = [];
    private $safeSelectedCategoryIds;

    public function __construct(
        Config $config,
        CollectionFactory $categoryCollectionFactory,
        EavConfig $eavConfig
    )
    {
        $this->config = $config;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->eavConfig = $eavConfig;
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

        if ($this->matchesAssignedCategories($product)) {
            return $this->productResults[$productId] = true;
        }
        if ($this->matchesMainCategory($product)) {
            return $this->productResults[$productId] = true;
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

    private function matchesAssignedCategories(ProductInterface $product)
    {
        $selected = $this->getSafeSelectedCategoryIds();
        if (!$selected) {
            return false;
        }

        $selectedLookup = array_fill_keys($selected, true);
        foreach ((array)$product->getCategoryIds() as $categoryId) {
            $categoryId = (int)$categoryId;
            if (isset($selectedLookup[$categoryId])) {
                return true;
            }
            foreach ($this->getCategoryPathIds($categoryId) as $pathId) {
                if (isset($selectedLookup[$pathId])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getSafeSelectedCategoryIds()
    {
        if ($this->safeSelectedCategoryIds !== null) {
            return $this->safeSelectedCategoryIds;
        }

        $selected = $this->config->getMeltableCategoryIds();
        if (!$selected) {
            return $this->safeSelectedCategoryIds = [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'is_active'])
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', ['in' => $selected]);

        $safe = [];
        foreach ($collection as $category) {
            $name = $this->normalize((string)$category->getName());
            // Collections is a merchandising group containing mixed product types.
            // It must never make every product beneath it meltable.
            if ($name === 'collection' || $name === 'collections') {
                continue;
            }
            $safe[] = (int)$category->getId();
        }
        return $this->safeSelectedCategoryIds = array_values(array_unique($safe));
    }

    private function matchesMainCategory(ProductInterface $product)
    {
        $configured = $this->config->getMeltableMainCategoryValues();
        if (!$configured) {
            return false;
        }
        $lookup = array_fill_keys($configured, true);
        foreach ($this->getMainCategoryLabels($product) as $label) {
            $label = $this->normalize($label);
            if ($label === '' || $label === 'collection' || $label === 'collections') {
                continue;
            }
            if (isset($lookup[$label])) {
                return true;
            }
        }
        return false;
    }

    private function getMainCategoryLabels(ProductInterface $product)
    {
        $raw = $product->getData('main_cat');
        $labels = [];
        $this->appendValues($labels, $raw);

        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, 'main_cat');
            if ($attribute && $attribute->getId() && $attribute->usesSource()) {
                foreach ($this->flattenValues($raw) as $value) {
                    $this->appendValues($labels, $attribute->getSource()->getOptionText($value));
                }
            }
        } catch (\Exception $exception) {
            // The custom main_cat attribute is optional. Assigned categories still apply.
        }

        return array_values(array_unique(array_filter($labels, 'strlen')));
    }

    private function appendValues(array &$target, $value)
    {
        foreach ($this->flattenValues($value) as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $target[] = $item;
            }
        }
    }

    private function flattenValues($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        return preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
    }

    private function normalize($value)
    {
        $value = strtolower(trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')));
        return preg_replace('/\s+/', ' ', $value);
    }
}
