<?php
namespace Sandy\WalmartSync\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $setup->startSetup();
            $table = $setup->getTable('sandy_walmartsync_sku');
            $connection = $setup->getConnection();
            $columns = [
                'mapping_type' => ['type' => Table::TYPE_TEXT, 'length' => 32, 'nullable' => false, 'default' => 'unmatched', 'comment' => 'Magento Mapping Type'],
                'option_id' => ['type' => Table::TYPE_INTEGER, 'nullable' => true, 'unsigned' => true, 'comment' => 'Magento Custom Option ID'],
                'option_type_id' => ['type' => Table::TYPE_INTEGER, 'nullable' => true, 'unsigned' => true, 'comment' => 'Magento Custom Option Value ID'],
                'option_title' => ['type' => Table::TYPE_TEXT, 'length' => 255, 'nullable' => true, 'comment' => 'Magento Custom Option Title'],
                'mapping_verified' => ['type' => Table::TYPE_SMALLINT, 'nullable' => false, 'default' => 0, 'comment' => 'Mapping Manually Verified'],
                'sku_exemption_status' => ['type' => Table::TYPE_TEXT, 'length' => 32, 'nullable' => false, 'default' => 'unknown', 'comment' => 'Walmart SKU Exemption Status']
            ];
            foreach ($columns as $name => $definition) {
                if (!$connection->tableColumnExists($table, $name)) {
                    $connection->addColumn($table, $name, $definition);
                }
            }
            $setup->endSetup();
        }
        if (version_compare($context->getVersion(), '1.4.0', '<')) {
            $setup->startSetup();
            $table = $setup->getTable('sandy_walmartsync_sku');
            $connection = $setup->getConnection();
            $columns = [
                'sync_enabled' => ['type' => Table::TYPE_SMALLINT, 'nullable' => false, 'default' => 0, 'comment' => 'Magento Product Walmart Sync Enabled'],
                'is_meltable' => ['type' => Table::TYPE_SMALLINT, 'nullable' => false, 'default' => 0, 'comment' => 'Meltable Product Result'],
                'seasonal_status' => ['type' => Table::TYPE_TEXT, 'length' => 32, 'nullable' => true, 'comment' => 'Meltable Seasonal Status'],
                'magento_qty' => ['type' => Table::TYPE_DECIMAL, 'length' => '12,4', 'nullable' => true, 'comment' => 'Last Previewed Magento Quantity'],
                'calculated_qty' => ['type' => Table::TYPE_DECIMAL, 'length' => '12,4', 'nullable' => true, 'comment' => 'Last Calculated Walmart Quantity'],
                'sync_action' => ['type' => Table::TYPE_TEXT, 'length' => 16, 'nullable' => false, 'default' => 'skip', 'comment' => 'Last Calculated Sync Action']
            ];
            foreach ($columns as $name => $definition) {
                if (!$connection->tableColumnExists($table, $name)) {
                    $connection->addColumn($table, $name, $definition);
                }
            }
            $setup->endSetup();
        }
    }
}
