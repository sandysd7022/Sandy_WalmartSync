<?php
namespace Sandy\WalmartSync\Model\ResourceModel\WalmartSku\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();

        $liveQuantity = new \Zend_Db_Expr(
            'COALESCE(stock_item.qty, main_table.magento_qty)'
        );

        $this->getSelect()->joinLeft(
            ['stock_item' => $this->getTable('cataloginventory_stock_item')],
            'main_table.product_id = stock_item.product_id AND stock_item.stock_id = 1',
            ['magento_qty_live' => $liveQuantity]
        );

        $this->addFilterToMap('magento_qty_live', $liveQuantity);

        return $this;
    }
}
