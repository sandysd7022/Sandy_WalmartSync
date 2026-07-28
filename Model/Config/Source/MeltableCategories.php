<?php
namespace Sandy\WalmartSync\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class MeltableCategories implements OptionSourceInterface
{
    private $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray()
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addFieldToFilter('level', ['gt' => 0])
            ->setOrder('path', 'ASC');

        $names = [];
        foreach ($collection as $category) {
            $names[(int)$category->getId()] = (string)$category->getName();
        }

        $options = [];
        foreach ($collection as $category) {
            $pathNames = [];
            foreach ($category->getPathIds() as $pathId) {
                if (isset($names[(int)$pathId])) {
                    $pathNames[] = $names[(int)$pathId];
                }
            }
            $options[] = [
                'value' => (int)$category->getId(),
                'label' => implode(' / ', $pathNames)
            ];
        }
        return $options;
    }
}
