<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2016 Amasty (https://www.amasty.com)
 * @package Amasty_Shopby
 */


/**
 * Class Amasty_Shopby_Block_Catalog_Layer_Filter_Attribute
 * @method Mage_Catalog_Model_Resource_Eav_Attribute getAttributeModel()
 */
class Amasty_Shopby_Block_Catalog_Layer_Filter_Attribute extends Amasty_Shopby_Block_Catalog_Layer_Filter_Attribute_Pure
{
    public function getItemsAsArray()
    {
        $cacheKey = 'items_as_array';
        $cached = $this->getData($cacheKey);
        if (isset($cached)) {
            return $cached;
        }

        $params = Mage::app()->getRequest()->getParams();
        $isMultipleNoidexMode = $this->getSeoNoindex() == Amasty_Shopby_Model_Filter::SEO_NO_INDEX_MULTIPLE_MODE;

        $selected = false;
        $items = array();
        foreach (parent::getItems() as $itemObject){
            $item = array();
            $item['id'] = $itemObject->getOptionId();
            $item['url']   = $this->htmlEscape($itemObject->getUrl());
            $item['label'] = $itemObject->getLabel();
            $item['descr'] = $itemObject->getDescr();

            $item['count'] = '';
            $item['countValue']  = $itemObject->getCount();
            if (!$this->getHideCounts()) {
                $item['count']  = '&nbsp;<span class="count">(' . $itemObject->getCount() . ')</span>';
            }

            $item['image'] = '';
            if ($itemObject->getImage()){
                $item['image'] = Mage::getBaseUrl('media') . 'amshopby/' . $itemObject->getImage();
            }

            if ($itemObject->getImageHover()) {
                $item['image_hover'] = Mage::getBaseUrl('media') . 'amshopby/' . $itemObject->getImageHover();
            }

            $item['css'] = 'amshopby-attr';
            if (in_array($this->getDisplayType(), array(1,3))) { //dropdown and images
                $item['css'] = '';
            }

            if ($itemObject->getIsSelected()){
                $selected = true;
                $item['css'] .= '-selected';
                if (3 == $this->getDisplayType()){ //dropdown
                    $item['css'] = 'selected';
                }
            }

            if ($itemObject->getCount() === 0)
            {
                $item['css'] .= ' amshopby-attr-inactive';
            }

            if($isMultipleNoidexMode) {
                if ($this->getSeoRel() && isset($params[$this->getRequestValue()]) && ($params[$this->getRequestValue()] != $item['id']))
                    $item['rel'] =  ' rel="nofollow" ';
                else
                    $item['rel'] = '';
            }
            else
                $item['rel'] =  $this->getSeoRel() ? ' rel="nofollow" ' : '';

            $item['is_featured'] = $itemObject->getIsFeatured();

            $item['data-config'] = $itemObject->getUrlAttributeOptionConfigAsJson(false);
            $items[] = $item;
        }

        $sortBy = $this->getSortBy();
        $functions = array(
            Amasty_Shopby_Model_Filter::SORT_BY_NAME => 'sortOptionsByName',
            Amasty_Shopby_Model_Filter::SORT_BY_QTY => 'sortOptionsByCounts'
        );
        if (isset($functions[$sortBy])){
            usort($items, array(Mage::helper('amshopby/attributes'), $functions[$sortBy]));
        }

        // add less/more
        $max = $this->getMaxOptions();
        if ($selected) {
            $max = 0;
        }
		$featuredItems = array();
		$standartItems = array();
        $i   = 0;
        foreach ($items as $k => $item){
            $style = '';
            if ($max && (++$i > $max)){
                $style = 'style="display:none" class="amshopby-attr-' . $this->getRequestValue() . '"';
            }
            $items[$k]['style'] = $style;
			$items[$k]['default_sort'] = $i;
			$items[$k]['featured_sort'] = $i;
			if($item['is_featured']) {
				$featuredItems[] = $items[$k];
			} else {
				$standartItems[] = $items[$k];
			}
        }
		if($this->getSortFeaturedFirst() && count($featuredItems) > 0) {
			usort($featuredItems, array(Mage::helper('amshopby/attributes'), 'sortOptionsByName'));
			$items = array_merge($featuredItems,$standartItems);
			$i = 0;
			foreach($items as $k=>$item) {
				$style = '';
				if ($max && (++$i > $max)){
					$style = 'style="display:none" class="amshopby-attr-' . $this->getRequestValue() . '"';
				}
				$items[$k]['style'] = $style;
				$items[$k]['featured_sort'] = $i;
			}
		}
        $this->setShowLessMore($max && ($i > $max));

        $this->setData($cacheKey, $items);
        return $items;
    }

    public function getRequestValue()
    {
        return $this->_filter->getAttributeModel()->getAttributeCode();
    }

    public function getItemsCount()
    {
        $v = Mage::app()->getRequest()->getParam($this->getRequestValue());
        if (isset($v) && $this->getRequestValue() == trim(Mage::getStoreConfig('amshopby/brands/attr'))){
            $cat    = Mage::registry('current_category');
            $rootId = (int) Mage::app()->getStore()->getRootCategoryId();
            if ($cat && $cat->getId() == $rootId){
                // and this is not landing page
                $page = Mage::app()->getRequest()->getParam('am_landing');
                if (!$page) return 0;
            }
        }

        $cnt     = parent::getItemsCount();
        $showAll = !Mage::getStoreConfig('amshopby/general/hide_one_value');
        return ($cnt > 1 || $showAll) ? $cnt : 0;
    }

    public function getRemoveUrl()
    {
        /** @var Amasty_Shopby_Model_Url_Builder $urlBuilder */
        $urlBuilder = Mage::getModel('amshopby/url_builder');
        $urlBuilder->reset();
        $urlBuilder->clearPagination();
        $urlBuilder->changeQuery(array(
            $this->getRequestValue() => null,
        ));

        $url = $urlBuilder->getUrl();
        return $url;
    }

    public function getShowSearch()
    {
        return
            parent::getShowSearch() &&
            (
                !$this->getNumberOptionsForShowSearch() ||
                $this->getNumberOptionsForShowSearch() <= count($this->getItemsAsArray())
            );
    }

    public function getSingleChoice()
    {
        $attributeCode = $this->_filter->getAttributeModel()->getAttributeCode();
        $brandCode = trim(Mage::getStoreConfig('amshopby/brands/attr'));
        $rootId = (int) Mage::app()->getStore()->getRootCategoryId();
        $moduleName = Mage::app()->getRequest()->getModuleName();
        $currentCategoryId = (int)$this->_filter->getLayer()->getCurrentCategory()->getId();

        return parent::getSingleChoice()
            || (($attributeCode === $brandCode) && ($rootId == $currentCategoryId) && ($moduleName === 'amshopby' ||$moduleName === 'cms' ));
    }

    public function getRemoveOptionConfig() {
        /** @var Amasty_Shopby_Model_Url_Builder $urlBuilder */
        $urlBuilder = Mage::getModel('amshopby/url_builder');
        $urlBuilder->reset();
        $dataConfig = $urlBuilder->getAttributeOptionConfig($this->getRequestValue(), '');

        return json_encode($dataConfig);
    }
}
