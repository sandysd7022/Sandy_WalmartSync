<?php
namespace Sandy\WalmartSync\Setup;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare((string)$context->getVersion(), '1.3.2', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_exemption_status',
                'note',
                'Product-level status for a direct Walmart SKU match. Custom-option SKUs can have separate statuses; review those under Walmart Sync > Known Walmart SKUs. Unknown = not recorded; Previously Requested = historical request, not approval; Pending Review = submitted; Approved = Walmart confirmed; Rejected = Walmart declined.'
            );
        }
        if (version_compare((string)$context->getVersion(), '1.4.0', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            if (!$eavSetup->getAttributeId(Product::ENTITY, 'walmart_meltable_override')) {
                $eavSetup->addAttribute(Product::ENTITY, 'walmart_meltable_override', [
                    'type' => 'varchar',
                    'label' => 'Meltable Product Override',
                    'input' => 'select',
                    'source' => 'Sandy\\WalmartSync\\Model\\Product\\Attribute\\Source\\MeltableOverride',
                    'default' => 'auto',
                    'required' => false,
                    'sort_order' => 55,
                    'group' => 'Walmart Sync',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'user_defined' => true,
                    'used_in_product_listing' => true,
                    'note' => 'Automatic uses the configured Magento meltable categories. Yes or No overrides category detection for this product. Custom-option Walmart SKUs inherit this parent-product result.'
                ]);
            }
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_exemption_status',
                'note',
                'Historical tracking only; this status does not control inventory synchronization. Custom-option Walmart SKUs can have separate statuses under Walmart Sync > Known Walmart SKUs.'
            );
        }
        if (version_compare((string)$context->getVersion(), '1.4.1', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->updateAttribute(Product::ENTITY, 'walmart_exemption_status', 'is_visible', 0);
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_exemption_status',
                'note',
                'Historical tracking only; this status does not control inventory synchronization.'
            );
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_sync_enabled',
                'note',
                'Product-level opt-in. Custom-option Walmart SKUs inherit this setting from their Magento parent. Enabling it does not immediately contact Walmart.'
            );
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_sku',
                'note',
                'Optional direct-product override. Leave empty when Walmart SKU equals Magento SKU. Custom-option SKUs are reviewed in Walmart Sync > Known Walmart SKUs.'
            );
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_item_id',
                'note',
                'Reference identifier imported from Walmart. Do not edit unless the product is being manually remapped.'
            );
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_force_zero',
                'note',
                'Emergency product-level override. A ready SKU calculates zero until this is changed back to No.'
            );
        }
        if (version_compare((string)$context->getVersion(), '1.4.3', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $globalAttributes = [
                'walmart_sync_enabled',
                'walmart_sku',
                'walmart_item_id',
                'walmart_force_zero',
                'walmart_meltable_override',
                'walmart_exemption_status',
                'walmart_last_sync_at',
                'walmart_last_error'
            ];
            foreach ($globalAttributes as $attributeCode) {
                $eavSetup->updateAttribute(
                    Product::ENTITY,
                    $attributeCode,
                    'is_global',
                    \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL
                );
            }
        }
        if (version_compare((string)$context->getVersion(), '1.4.4', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            foreach (['walmart_sync_enabled', 'walmart_force_zero'] as $attributeCode) {
                $eavSetup->updateAttribute(
                    Product::ENTITY,
                    $attributeCode,
                    'source_model',
                    'Magento\\Eav\\Model\\Entity\\Attribute\\Source\\Boolean'
                );
            }
        }
        if (version_compare((string)$context->getVersion(), '1.4.5', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'walmart_sync_enabled',
                'is_visible',
                0
            );
        }
        $setup->endSetup();
    }
}
