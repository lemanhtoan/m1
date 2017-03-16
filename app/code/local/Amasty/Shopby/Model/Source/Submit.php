<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2016 Amasty (https://www.amasty.com)
 * @package Amasty_Shopby
 */
class Amasty_Shopby_Model_Source_Submit extends Amasty_Shopby_Model_Source_Abstract
{
    const INSTANTLY = 0;
    const BY_BUTTON = 1;
    public function toOptionArray()
    {
        $hlp = Mage::helper('amshopby');
        return array(
            array('value' => Amasty_Shopby_Model_Source_Submit::INSTANTLY,    'label' => $hlp->__('Instantly')),
            array('value' => Amasty_Shopby_Model_Source_Submit::BY_BUTTON,   'label' => $hlp->__('By Button Click')),
        );
    }
}