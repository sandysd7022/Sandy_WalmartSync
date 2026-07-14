<?php
namespace Sandy\WalmartSync\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Environment implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'production', 'label' => __('Production')],
            ['value' => 'sandbox', 'label' => __('Walmart Dynamic Sandbox')]
        ];
    }
}
