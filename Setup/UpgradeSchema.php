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
    }
}
