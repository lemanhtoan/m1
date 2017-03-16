<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2016 Amasty (https://www.amasty.com)
 * @package Amasty_Shopby
 */


if (method_exists('Mage', 'getEdition')) { // CE 1.7+, EE 1.12+
    $autoloader = Varien_Autoload::instance();
    $autoloader->autoload('Amasty_Shopby_Model_Catalog_Layer_Filter_Price_Adapter');
}
else { // CE 1.3.2 - 1.6.2
    class Amasty_Shopby_Model_Catalog_Layer_Filter_Price_Pure extends Amasty_Shopby_Model_Catalog_Layer_Filter_Price_Price14ce
    {
    }
}
