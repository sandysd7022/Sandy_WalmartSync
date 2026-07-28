<?php
namespace Sandy\WalmartSync\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $table = $installer->getConnection()->newTable($installer->getTable('sandy_walmartsync_sku'))
            ->addColumn('entity_id', Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true], 'ID')
            ->addColumn('walmart_sku', Table::TYPE_TEXT, 255, ['nullable' => false], 'Walmart SKU')
            ->addColumn('item_id', Table::TYPE_TEXT, 64, [], 'Walmart Item ID')
            ->addColumn('product_name', Table::TYPE_TEXT, 512, [], 'Walmart Product Name')
            ->addColumn('current_qty', Table::TYPE_DECIMAL, '12,4', [], 'Last Known Walmart Quantity')
            ->addColumn('magento_sku', Table::TYPE_TEXT, 255, [], 'Matched Magento SKU')
            ->addColumn('product_id', Table::TYPE_INTEGER, null, ['unsigned' => true], 'Magento Product ID')
            ->addColumn('mapping_type', Table::TYPE_TEXT, 32, ['nullable' => false, 'default' => 'unmatched'], 'Magento Mapping Type')
            ->addColumn('option_id', Table::TYPE_INTEGER, null, ['unsigned' => true], 'Magento Custom Option ID')
            ->addColumn('option_type_id', Table::TYPE_INTEGER, null, ['unsigned' => true], 'Magento Custom Option Value ID')
            ->addColumn('option_title', Table::TYPE_TEXT, 255, [], 'Magento Custom Option Title')
            ->addColumn('mapping_verified', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0], 'Mapping Manually Verified')
            ->addColumn('sku_exemption_status', Table::TYPE_TEXT, 32, ['nullable' => false, 'default' => 'unknown'], 'Walmart SKU Exemption Status')
            ->addColumn('published_status', Table::TYPE_TEXT, 64, [], 'Walmart Published Status')
            ->addColumn('lifecycle_status', Table::TYPE_TEXT, 64, [], 'Walmart Lifecycle Status')
            ->addColumn('is_eligible', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0], 'Last Eligibility Result')
            ->addColumn('eligibility_reason', Table::TYPE_TEXT, 1024, [], 'Eligibility Reason')
            ->addColumn('sync_enabled', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0], 'Magento Product Walmart Sync Enabled')
            ->addColumn('is_meltable', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0], 'Meltable Product Result')
            ->addColumn('seasonal_status', Table::TYPE_TEXT, 32, [], 'Meltable Seasonal Status')
            ->addColumn('magento_qty', Table::TYPE_DECIMAL, '12,4', [], 'Last Previewed Magento Quantity')
            ->addColumn('calculated_qty', Table::TYPE_DECIMAL, '12,4', [], 'Last Calculated Walmart Quantity')
            ->addColumn('sync_action', Table::TYPE_TEXT, 16, ['nullable' => false, 'default' => 'skip'], 'Last Calculated Sync Action')
            ->addColumn('last_sync_status', Table::TYPE_TEXT, 32, [], 'Last Sync Status')
            ->addColumn('last_error', Table::TYPE_TEXT, '2M', [], 'Last Error')
            ->addColumn('last_imported_at', Table::TYPE_TIMESTAMP, null, [], 'Last Imported At')
            ->addColumn('last_synced_at', Table::TYPE_TIMESTAMP, null, [], 'Last Synced At')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT], 'Created At')
            ->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE], 'Updated At')
            ->addIndex($installer->getIdxName('sandy_walmartsync_sku', ['walmart_sku'], \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE), ['walmart_sku'], ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE])
            ->addIndex($installer->getIdxName('sandy_walmartsync_sku', ['magento_sku']), ['magento_sku']);
        $installer->getConnection()->createTable($table);

        $logTable = $installer->getConnection()->newTable($installer->getTable('sandy_walmartsync_log'))
            ->addColumn('entity_id', Table::TYPE_BIGINT, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true], 'ID')
            ->addColumn('magento_sku', Table::TYPE_TEXT, 255, [], 'Magento SKU')
            ->addColumn('walmart_sku', Table::TYPE_TEXT, 255, [], 'Walmart SKU')
            ->addColumn('action', Table::TYPE_TEXT, 64, ['nullable' => false], 'Action')
            ->addColumn('previous_value', Table::TYPE_TEXT, '64k', [], 'Previous Value')
            ->addColumn('new_value', Table::TYPE_TEXT, '64k', [], 'New Value')
            ->addColumn('status', Table::TYPE_TEXT, 32, ['nullable' => false], 'Status')
            ->addColumn('correlation_id', Table::TYPE_TEXT, 64, [], 'Walmart Correlation ID')
            ->addColumn('message', Table::TYPE_TEXT, '2M', [], 'Message')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT], 'Created At')
            ->addIndex($installer->getIdxName('sandy_walmartsync_log', ['walmart_sku']), ['walmart_sku'])
            ->addIndex($installer->getIdxName('sandy_walmartsync_log', ['action', 'status']), ['action', 'status']);
        $installer->getConnection()->createTable($logTable);

        $installer->endSetup();
    }
}
