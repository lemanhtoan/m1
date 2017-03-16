<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2016 Amasty (https://www.amasty.com)
 * @package Amasty_Shopby
 */


class Amasty_Shopby_Block_Catalog_Layer_Filter_Apply extends Mage_Core_Block_Template
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('amasty/amshopby/apply.phtml');
    }

    public function getPageDataForApplyButtonAsJson()
    {
        /** @var Amasty_Shopby_Model_Url_Builder $urlBuilder */
        $urlBuilder = Mage::getModel('amshopby/url_builder');
        $urlBuilder->reset();
        $urlBuilder->clearPagination();

        return json_encode($urlBuilder->getUrlConfig());
    }

    public function getAppliedAttributeOptionsConfig()
    {
        $appliedFilters = array();
        $filterCodes = Mage::helper('amshopby/attributes')->getRequestedFilterCodes();
        $urlBuilder = Mage::getModel('amshopby/url_builder');
        $urlHelper = Mage::helper('amshopby/url');
        $urlBuilder->reset();

        if($filterCodes) {
            foreach ($filterCodes as $code => $options) {
                if($code) {
                    $isDecimal = $urlHelper->isDecimal($code);
                    $opts = explode(',', $options);
                    if (($code == "price") || $isDecimal)
                    {
                        if(count($opts) > 1) {
                            $opts = array(($opts[0]-1)*$opts[1]."-".$opts[0]*$opts[1]);
                        }
                    }

                    foreach ($opts as $option) {
                        if($option) {
                            $appliedFilters[] = $urlBuilder->getAttributeOptionConfig($code, $option);
                        }
                    }
                }
            }
        }

        return json_encode($appliedFilters);
    }

    public function getNotFilterQueryParams() {
        $urlBuilder = Mage::getModel('amshopby/url_builder');
        $urlBuilder->reset();
        $urlBuilder->clearPagination();
        $params = $urlBuilder->getNotFilterQueryParams();
        return json_encode($params);
    }
}