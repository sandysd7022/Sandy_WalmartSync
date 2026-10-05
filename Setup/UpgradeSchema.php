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
        if (version_compare((string)$context->getVersion(), '1.8.0', '<')) {
            $setup->startSetup();
            $connection = $setup->getConnection();
            $tableName = $setup->getTable('sandy_walmartsync_item_candidate');
            if (!$connection->isTableExists($tableName)) {
                $table = $connection->newTable($tableName)
                    ->addColumn('entity_id', Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true], 'ID')
                    ->addColumn('product_id', Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false], 'Magento Product ID')
                    ->addColumn('magento_sku', Table::TYPE_TEXT, 255, ['nullable' => false], 'Magento SKU')
                    ->addColumn('walmart_sku', Table::TYPE_TEXT, 255, ['nullable' => false], 'Walmart SKU')
                    ->addColumn('product_name', Table::TYPE_TEXT, 512, [], 'Product Name')
                    ->addColumn('main_category', Table::TYPE_TEXT, 255, [], 'Magento Main Category')
                    ->addColumn('walmart_product_type', Table::TYPE_TEXT, 255, [], 'Walmart Product Type')
                    ->addColumn('in_scope', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 1], 'In Current Discovery Scope')
                    ->addColumn('already_in_walmart', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0], 'Found in Imported Walmart Catalog')
                    ->addColumn('validation_status', Table::TYPE_TEXT, 32, ['nullable' => false, 'default' => 'not_validated'], 'Validation Status')
                    ->addColumn('validation_error', Table::TYPE_TEXT, '2M', [], 'Validation Error')
                    ->addColumn('payload_hash', Table::TYPE_TEXT, 64, [], 'Reviewed Payload Hash')
                    ->addColumn('payload_json', Table::TYPE_TEXT, '4M', [], 'Reviewed Exact Item Payload')
                    ->addColumn('ingredient_image_url', Table::TYPE_TEXT, 1024, [], 'Generated Ingredient Image URL')
                    ->addColumn('creation_status', Table::TYPE_TEXT, 32, ['nullable' => false, 'default' => 'candidate'], 'Creation Status')
                    ->addColumn('creation_feed_id', Table::TYPE_TEXT, 255, [], 'Creation Feed ID')
                    ->addColumn('publish_status', Table::TYPE_TEXT, 32, ['nullable' => false, 'default' => 'not_requested'], 'Publish Status')
                    ->addColumn('publish_feed_id', Table::TYPE_TEXT, 255, [], 'Publish Feed ID')
                    ->addColumn('item_id', Table::TYPE_TEXT, 64, [], 'Walmart Item ID')
                    ->addColumn('wpid', Table::TYPE_TEXT, 64, [], 'Walmart Product ID')
                    ->addColumn('last_error', Table::TYPE_TEXT, '2M', [], 'Last Error')
                    ->addColumn('submitted_at', Table::TYPE_TIMESTAMP, null, [], 'Creation Submitted At')
                    ->addColumn('publish_requested_at', Table::TYPE_TIMESTAMP, null, [], 'Publish Requested At')
                    ->addColumn('processed_at', Table::TYPE_TIMESTAMP, null, [], 'Last Feed Processed At')
                    ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT], 'Created At')
                    ->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE], 'Updated At')
                    ->addIndex($setup->getIdxName('sandy_walmartsync_item_candidate', ['product_id'], \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE), ['product_id'], ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE])
                    ->addIndex($setup->getIdxName('sandy_walmartsync_item_candidate', ['walmart_sku']), ['walmart_sku'])
                    ->addIndex($setup->getIdxName('sandy_walmartsync_item_candidate', ['creation_status']), ['creation_status']);
                $connection->createTable($table);
            }
            $setup->endSetup();
        }
        if (version_compare((string)$context->getVersion(), '1.8.3', '<')) {
            $setup->startSetup();
            $connection = $setup->getConnection();
            $table = $setup->getTable('sandy_walmartsync_item_candidate');
            $columns = [
                'brand' => ['type' => Table::TYPE_TEXT, 'length' => 255, 'nullable' => true, 'comment' => 'Magento jet_brand'],
                'package_qty' => ['type' => Table::TYPE_TEXT, 'length' => 64, 'nullable' => true, 'comment' => 'Magento package_qty'],
                'total_package_weight' => ['type' => Table::TYPE_TEXT, 'length' => 64, 'nullable' => true, 'comment' => 'Magento total_package_weight']
            ];
            foreach ($columns as $name => $definition) {
                if (!$connection->tableColumnExists($table, $name)) {
                    $connection->addColumn($table, $name, $definition);
                }
            }
            $setup->endSetup();
        }
        if (version_compare((string)$context->getVersion(), '1.8.5', '<')) {
            $setup->startSetup();
            $connection = $setup->getConnection();
            $table = $setup->getTable('sandy_walmartsync_item_candidate');
            if (!$connection->tableColumnExists($table, 'flavor')) {
                $connection->addColumn($table, 'flavor', [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Resolved Walmart Flavor'
                ]);
            }
            $setup->endSetup();
        }
        if (version_compare((string)$context->getVersion(), '1.8.7', '<')) {
            $setup->startSetup();
            $connection = $setup->getConnection();
            $table = $setup->getTable('sandy_walmartsync_item_candidate');
            if (!$connection->tableColumnExists($table, 'food_form')) {
                $connection->addColumn($table, 'food_form', [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Resolved Walmart Food Form'
                ]);
            }
            $setup->endSetup();
        }
        if (version_compare((string)$context->getVersion(), '1.8.8', '<')) {
            $setup->startSetup();
            $connection = $setup->getConnection();
            $table = $setup->getTable('sandy_walmartsync_item_candidate');
            if (!$connection->tableColumnExists($table, 'walmart_category')) {
                $connection->addColumn($table, 'walmart_category', [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Walmart Product Category'
                ]);
            }
            $setup->endSetup();
        }
    }
}
