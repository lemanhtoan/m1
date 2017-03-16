<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2016 Amasty (https://www.amasty.com)
 * @package Amasty_Shopby
 */


/**
 * Copyright © 2016 Amasty. All rights reserved.
 */
class Amasty_Shopby_Model_Mysql4_Product_Attribute_Collection extends Mage_Catalog_Model_Resource_Product_Attribute_Collection
{
    /**
     * initialize select object
     *
     * @return Mage_Catalog_Model_Resource_Product_Attribute_Collection
     */
    protected function _initSelect()
    {
        $entityTypeId = (int)Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $columns = $this->getConnection()->describeTable($this->getResource()->getMainTable());
        unset($columns['attribute_id']);
        $retColumns = array();
        foreach ($columns as $labelColumn => $columnData) {
            $retColumns[$labelColumn] = $labelColumn;
            if ($columnData['DATA_TYPE'] == Varien_Db_Ddl_Table::TYPE_TEXT) {
                $retColumns[$labelColumn] = Mage::getResourceHelper('core')->castField('main_table.'.$labelColumn);
            }
        }
        $this->getSelect()
            ->from(array('main_table' => $this->getResource()->getMainTable()), $retColumns)
            ->join(
                array('additional_table' => $this->getTable('catalog/eav_attribute')),
                'additional_table.attribute_id = main_table.attribute_id'
            )
            ->where('main_table.entity_type_id = ?', $entityTypeId);
        return $this;
    }
}
