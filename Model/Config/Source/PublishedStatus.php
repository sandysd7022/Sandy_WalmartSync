<?php
declare(strict_types=1);

namespace Sandy\WalmartSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PublishedStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'PUBLISHED', 'label' => __('Published')],
            ['value' => 'UNPUBLISHED', 'label' => __('Unpublished')],
            ['value' => 'SYSTEM_PROBLEM', 'label' => __('System Problem')],
            ['value' => 'DRAFT', 'label' => __('Draft')],
        ];
    }
}
