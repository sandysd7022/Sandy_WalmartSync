<?php
namespace Sandy\WalmartSync\Model\Product\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class MeltableOverride extends AbstractSource
{
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => 'auto', 'label' => __('Automatic (use configured categories)')],
                ['value' => 'yes', 'label' => __('Yes')],
                ['value' => 'no', 'label' => __('No')]
            ];
        }
        return $this->_options;
    }
}
