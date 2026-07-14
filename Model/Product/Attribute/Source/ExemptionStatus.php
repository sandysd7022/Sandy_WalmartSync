<?php
namespace Sandy\WalmartSync\Model\Product\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class ExemptionStatus extends AbstractSource
{
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => 'unknown', 'label' => __('Unknown')],
                ['value' => 'previously_requested', 'label' => __('Previously Requested')],
                ['value' => 'pending', 'label' => __('Pending Review')],
                ['value' => 'approved', 'label' => __('Approved')],
                ['value' => 'rejected', 'label' => __('Rejected')]
            ];
        }
        return $this->_options;
    }
}
