<?php
namespace Sandy\WalmartSync\Setup;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $attributes = [
            'walmart_sync_enabled' => [
                'type' => 'int', 'label' => 'Enable Walmart Sync', 'input' => 'boolean',
                'source' => 'Magento\\Eav\\Model\\Entity\\Attribute\\Source\\Boolean',
                'default' => 0, 'required' => false, 'sort_order' => 10, 'visible' => false,
                'note' => 'Product-level opt-in. Custom-option Walmart SKUs inherit this setting from their Magento parent. Enabling it does not immediately contact Walmart.'
            ],
            'walmart_exemption_status' => [
                'type' => 'varchar', 'label' => 'Walmart Return Exemption Status', 'input' => 'select',
                'source' => 'Sandy\\WalmartSync\\Model\\Product\\Attribute\\Source\\ExemptionStatus',
                'default' => 'unknown', 'required' => false, 'sort_order' => 20,
                'visible' => false,
                'note' => 'Historical tracking only; this status does not control inventory synchronization.'
            ],
            'walmart_sku' => [
                'type' => 'varchar', 'label' => 'Walmart SKU', 'input' => 'text',
                'required' => false, 'sort_order' => 30,
                'note' => 'Optional direct-product override. Leave empty when Walmart SKU equals Magento SKU. Custom-option SKUs are reviewed in Walmart Sync > Known Walmart SKUs.'
            ],
            'walmart_item_id' => [
                'type' => 'varchar', 'label' => 'Walmart Item ID', 'input' => 'text',
                'required' => false, 'sort_order' => 40,
                'note' => 'Reference identifier imported from Walmart. Do not edit unless the product is being manually remapped.'
            ],
            'walmart_force_zero' => [
                'type' => 'int', 'label' => 'Force Walmart Inventory to Zero', 'input' => 'boolean',
                'source' => 'Magento\\Eav\\Model\\Entity\\Attribute\\Source\\Boolean',
                'default' => 0, 'required' => false, 'sort_order' => 50,
                'note' => 'Emergency product-level override. A ready SKU calculates zero until this is changed back to No.'
            ],
            'walmart_meltable_override' => [
                'type' => 'varchar', 'label' => 'Meltable Product Override', 'input' => 'select',
                'source' => 'Sandy\\WalmartSync\\Model\\Product\\Attribute\\Source\\MeltableOverride',
                'default' => 'auto', 'required' => false, 'sort_order' => 55,
                'note' => 'Automatic uses the configured Magento meltable categories. Yes or No overrides category detection for this product. Custom-option Walmart SKUs inherit this parent-product result.'
            ],
            'walmart_last_sync_at' => [
                'type' => 'datetime', 'label' => 'Walmart Last Sync Date', 'input' => 'date',
                'required' => false, 'sort_order' => 60, 'visible' => false
            ],
            'walmart_last_error' => [
                'type' => 'text', 'label' => 'Walmart Last Error', 'input' => 'textarea',
                'required' => false, 'sort_order' => 70, 'visible' => false
            ]
        ];
        foreach ($attributes as $code => $data) {
            $data['group'] = 'Walmart Sync';
            $data['global'] = \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL;
            $data['visible'] = isset($data['visible']) ? $data['visible'] : true;
            $data['user_defined'] = true;
            $data['used_in_product_listing'] = true;
            $eavSetup->addAttribute(Product::ENTITY, $code, $data);
        }
        $setup->endSetup();
    }
}
