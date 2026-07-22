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
                'default' => 0, 'required' => false, 'sort_order' => 10
            ],
            'walmart_exemption_status' => [
                'type' => 'varchar', 'label' => 'Walmart Return Exemption Status', 'input' => 'select',
                'source' => 'Sandy\\WalmartSync\\Model\\Product\\Attribute\\Source\\ExemptionStatus',
                'default' => 'unknown', 'required' => false, 'sort_order' => 20,
                'note' => 'Product-level status for a direct Walmart SKU match. Custom-option SKUs can have separate statuses; review those under Walmart Sync > Known Walmart SKUs. Unknown = not recorded; Previously Requested = historical request, not approval; Pending Review = submitted; Approved = Walmart confirmed; Rejected = Walmart declined.'
            ],
            'walmart_sku' => [
                'type' => 'varchar', 'label' => 'Walmart SKU', 'input' => 'text',
                'required' => false, 'sort_order' => 30, 'note' => 'Leave empty when Walmart SKU equals Magento SKU.'
            ],
            'walmart_item_id' => [
                'type' => 'varchar', 'label' => 'Walmart Item ID', 'input' => 'text',
                'required' => false, 'sort_order' => 40
            ],
            'walmart_force_zero' => [
                'type' => 'int', 'label' => 'Force Walmart Inventory to Zero', 'input' => 'boolean',
                'default' => 0, 'required' => false, 'sort_order' => 50
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
