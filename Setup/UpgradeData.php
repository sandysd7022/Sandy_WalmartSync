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
        $setup->endSetup();
    }
}
