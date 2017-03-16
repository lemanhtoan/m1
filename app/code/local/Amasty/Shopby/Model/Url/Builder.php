<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2016 Amasty (https://www.amasty.com)
 * @package Amasty_Shopby
 */


class Amasty_Shopby_Model_Url_Builder
{
    /** @var  string */
    public $moduleName;

    /** @var  array */
    protected $query;

    /** @var  array */
    protected $effectiveQuery;

    protected $allowAjaxFlag = false;

    /** @var Mage_Catalog_Model_Category|int */
    public $category;

    protected static $initialized = false;
    protected static $magentoBaseUrl;
    protected static $rootCategoryId;
    protected static $reservedKey;
    protected static $mode;
    protected static $brandAttributeCode;
    protected static $filterUrlSortMode;
    protected static $brandUrlKey;
    protected static $specialChar;
    protected static $multiselectQueryParam;
    /** @var array */
    protected static $attributesPositions;
    protected static $categoryUrlHash;
    protected static $excludeParams;

    protected static $urlCache;

    protected static function initialize()
    {
        self::$mode = Mage::getStoreConfig('amshopby/seo/urls');
        self::$brandAttributeCode = trim(Mage::getStoreConfig('amshopby/brands/attr'));
        self::$filterUrlSortMode = Mage::getStoreConfig('amshopby/seo/sort_attributes_in_url');
        self::$brandUrlKey = trim(Mage::getStoreConfig('amshopby/brands/url_key'));
        self::$specialChar = Mage::getStoreConfig('amshopby/seo/special_char');
        self::$multiselectQueryParam = trim(Mage::getStoreConfig('amshopby/seo/query_param'));
        self::$attributesPositions = Mage::helper('amshopby/attributes')->getPositionsAttributes();

        self::$rootCategoryId = (int) Mage::app()->getStore()->getRootCategoryId();
        self::$reservedKey = trim(Mage::getStoreConfig('amshopby/seo/key'));
        self::$magentoBaseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, Mage::app()->getStore()->isCurrentlySecure());

        $excludeParamsStr = trim(Mage::getStoreConfig('amshopby/seo/query_param_exclude'));
        self::$excludeParams = $excludeParamsStr == '' ? array() : explode(',', $excludeParamsStr);

        self::$urlCache = array();
        self::$initialized = true;
    }

    public function reset()
    {
        if (!self::$initialized) {
            self::initialize();
        }

        /** @var Amasty_Shopby_Helper_Data $helper */
        $helper = Mage::helper('amshopby');
        $this->category = $helper->getCurrentCategory();

        // Destination parameters
        $this->moduleName = Mage::app()->getRequest()->getModuleName();
        if ($this->moduleName == 'cms') {
            $this->clearModule();
        }
        $this->query = Mage::app()->getRequest()->getQuery();
    }

    public function clearQuery()
    {
        $query = array();
        if ($this->isSomeSearch() && isset($this->query['q'])) {
            $query['q'] = $this->query['q'];
        }
        $this->query = $query;
    }

    public function clearPagination()
    {
        $pager = Mage::getBlockSingleton('page/html_pager');
        if (is_object($pager)) {
            $var = $pager->getPageVarName();
            if (isset($this->query[$var])) {
                unset($this->query[$var]);
            }
        }
    }

    public function clearCategory()
    {
        $this->category = Mage::app()->getStore()->getRootCategoryId();
    }

    public function clearModule()
    {
        $this->moduleName = 'amshopby';
    }

    public function changeQuery(array $delta)
    {
        $this->query = array_merge($this->query, $delta);
    }

    /**
     * "is_ajax=1" parameter should be added by JS in regular case
     *
     * @param bool $allow
     */
    public function setAllowAjaxFlag($allow)
    {
        $this->allowAjaxFlag = $allow;
    }

    public function getUrl()
    {
        $this->updateEffectiveQuery();

        $paramPart = $this->getParamPart();
        $basePart = $this->getBasePart($paramPart);

        $url = $basePart . $paramPart;
        $url = preg_replace('|(^:)/{2,}|', '$1/', $url);

        return $url;
    }

    protected function updateEffectiveQuery()
    {
        $this->effectiveQuery = $this->query;

        $this->processCategoryParameter();
        $this->excludeParams();
        $this->cleanNulls();
        $this->sortQuery();
        $this->detectMultiselectParam();
    }

    protected function processCategoryParameter()
    {
        /** @var Amasty_Shopby_Helper_Data $helper */
        $helper = Mage::helper('amshopby');
        if ($helper->getCategoriesMultiselectMode()) {
            // No action needed in multiselect mode: it`s already ok.
        } else {
            if (isset($this->effectiveQuery['cat'])) {
                $goToCategory = !($this->isNewOrSale() || $this->getCurrentLandingKey() || $this->isSomeSearch());
                if ($goToCategory) {
                    $this->category = $this->effectiveQuery['cat'];
                    $this->effectiveQuery['cat'] = null;
                }
            }
        }
    }

    protected function excludeParams()
    {
        if (self::$excludeParams) {
            $excludeParams = array_intersect(explode(',', self::$excludeParams), array_keys($this->effectiveQuery));
            foreach ($excludeParams as $param) {
                unset($this->effectiveQuery[$param]);
            }
        }

        if (isset($this->effectiveQuery['is_ajax']) && !$this->allowAjaxFlag) {
            unset($this->effectiveQuery['is_ajax']);
        }
    }

    protected function cleanNulls()
    {
        foreach ($this->effectiveQuery as $k => &$v){
            if (is_null($v) || $v === '') {
                unset($this->effectiveQuery[$k]);
                continue;
            }
        }
    }

    protected function sortQuery()
    {
        foreach ($this->effectiveQuery as &$v){
            //sort values to avoid duplicate content
            if (is_array($v)) {
                sort($v);
            } else {
                $vArray = explode(',', $v);
                sort($vArray);
                $v = implode(',', $vArray);
            }
        }
		uksort($this->effectiveQuery, array($this, 'compareParamsPriority'));
    }

    protected function compareParamsPriority($a, $b)
    {
        if ($a == 'is_ajax') {
            return 1;
        } else if ($b == 'is_ajax') {
            return -1;
        }

        if ($a == self::$brandAttributeCode) {
            return -1;
        } elseif ($b == self::$brandAttributeCode) {
            return 1;
        }

		if(self::$filterUrlSortMode == Amasty_Shopby_Model_Source_Url_SortMode::MODE_POSITION) {
			if(isset(self::$attributesPositions[$a]) && isset(self::$attributesPositions[$b])) {
				if(self::$attributesPositions[$a] < self::$attributesPositions[$b]){
					return -1;
				} elseif(self::$attributesPositions[$a] == self::$attributesPositions[$b]) {
					return 0;
				} else {
					return 1;
				}
			}
		}

        return strcmp($a, $b);
    }

    protected function detectMultiselectParam()
    {
        if (self::$multiselectQueryParam) {
            $foundMultipleValues = false;
            foreach ($this->query as $code => $v) {
                $isMultipleSelectedArray = is_array($v) && count($v) > 1;
                $isMultipleSelectedString = is_string($v) && preg_match('@\d+,[\d,]*\d@', $v);
                $isDecimal = $this->getUrlHelper()->isDecimal($code);

                if (($isMultipleSelectedArray || $isMultipleSelectedString) && !$isDecimal) {
                    $foundMultipleValues = true;
                    break;
                }
            }
            if ($foundMultipleValues){
                $this->effectiveQuery[self::$multiselectQueryParam] = 'true';
            }
            else {
                unset($this->effectiveQuery[self::$multiselectQueryParam]);
            }
        }
    }

    protected function getParamPart()
    {
        $seoParts = array();
        $query = array();
        // add attributes as keys, not as ids
        if (self::$mode && !$this->isSomeSearch()) {
            $options = $this->getUrlHelper()->getAllFilterableOptionsAsHash();
            foreach ($this->effectiveQuery as $origAttrCode => $ids)
            {
                $attrCode = str_replace(array('_', '-'), self::$specialChar, $origAttrCode);

                if (isset($options[$attrCode]) && $options[$attrCode]){ // it is filterable attribute
                    if (self::$mode == Amasty_Shopby_Model_Source_Url_Mode::MODE_SHORT) {
                        $part = $this->getUrlHelper()->_formatAttributePartShort($attrCode, $ids);
                    } else {
                        $part = $this->getUrlHelper()->_formatAttributePartMultilevel($attrCode, $ids);
                    }

                    if ($part != '') {
                        $seoParts[] = $part;
                    }
                }
                else {
                    $query[$origAttrCode] = $ids; // it is pager or smth else
                }
            }
        } else {
            $query = $this->effectiveQuery;
        }

        $glue = (self::$mode == Amasty_Shopby_Model_Source_Url_Mode::MODE_SHORT) ? Mage::getStoreConfig('amshopby/seo/option_char') : '/';
        $result = implode($glue, $seoParts);
        if ($result !== '') {
            $result = $this->getUrlHelper()->checkAddSuffix($result);
        }

        // add other params as query string if any
        $query = http_build_query($query);
        if ($query !== ''){
            $result .= '?' . $query;
        }

        return $result;
    }

    protected function getBasePart($paramPart)
    {
        $seoAttributePartExist = $paramPart !== '' && strpos($paramPart, '?') !== 0;

        if ($this->isCatalogSearch()){
            $url = self::$magentoBaseUrl . 'catalogsearch/result/';
        }
        elseif ($this->isNewOrSale()) {
            $url = self::$magentoBaseUrl . $this->moduleName;
        }
        elseif ($this->getCurrentLandingKey()) {
            $url = self::$magentoBaseUrl . $this->getCurrentLandingKey();

            if ($seoAttributePartExist) {
                $url.= '/';
            } else {
                $url = $this->getUrlHelper()->checkAddSuffix($url);
            }
        }
        elseif ($this->isCategorySearch()) {
            $url = self::$magentoBaseUrl . 'categorysearch/categorysearch/search/';
        }
        elseif ($this->moduleName == 'cms' && $this->getCategoryId() == self::$rootCategoryId) { // homepage,
            $hasFilter = false;
            if (Mage::getStoreConfig('amshopby/block/ajax')) {
                $hasFilter = true;
            }
            if (!$hasFilter) {
                foreach (array_keys($this->query) as $k){
                    if (!in_array($k, array('p','mode','order','dir','limit')) && false === strpos('__', $k)){
                        $hasFilter = true;
                        break;
                    }
                }
            }

            // homepage filter links
            if ($this->isUrlKeyMode() && $hasFilter){
                $url = self::$magentoBaseUrl . self::$reservedKey . '/';
            }
            // homepage sorting/paging url
            else {
                $url = self::$magentoBaseUrl;
            }
        }
        elseif ($this->getCategoryId() == self::$rootCategoryId) {
            $url = self::$magentoBaseUrl;

            switch (self::$mode) {
                case Amasty_Shopby_Model_Source_Url_Mode::MODE_DISABLED:
                    $needUrlKey = true;
                    break;
                case Amasty_Shopby_Model_Source_Url_Mode::MODE_MULTILEVEL:
                    $needUrlKey = !$this->isBrandPage() || !$this->isBrandParamSeo();
                    break;
                case Amasty_Shopby_Model_Source_Url_Mode::MODE_SHORT:
                    $needUrlKey = !$seoAttributePartExist;
                    break;
                default:
                    $needUrlKey = true;
            }
            if ($needUrlKey) {
                $url.= self::$reservedKey;
                if ($seoAttributePartExist) {
                    $url .=  '/';
                }
            }

            if($seoAttributePartExist && $this->isBrandPage() && $this->isBrandParamSeo() && self::$brandUrlKey && (self::$mode != Amasty_Shopby_Model_Source_Url_Mode::MODE_DISABLED)){
                if (substr($url, -1) !== '/') {
                    $url .=  '/';
                }

                $url .= self::$brandUrlKey;
                $url .=  '/';
            }
        }
        else { // we have a valid category
            $url = $this->getCategoryUrl();
            $pos = strpos($url,'?');
            $url = $pos ? substr($url, 0, $pos) : $url;

            if ($seoAttributePartExist) {
                $url = $this->getUrlHelper()->checkRemoveSuffix($url);
                if ($this->isUrlKeyMode()) {
                    $url .= '/' . self::$reservedKey;
                }
                $url.= '/';
            }
        }

        return $url;
    }

    protected function isBrandPage()
    {
        $isAttributeRequested = self::$brandAttributeCode && isset($this->effectiveQuery[self::$brandAttributeCode]);
        $isBrandPage = $this->moduleName == 'amshopby' && $isAttributeRequested;
        return $isBrandPage;
    }

    /**
     * @return int
     */
    protected function getCategoryId()
    {
        return is_object($this->category) ? $this->category->getId() : $this->category;
    }

    /**
     * @return string
     */
    protected function getCategoryUrl()
    {
        $catId = $this->getCategoryId();

        if (!isset(self::$categoryUrlHash[$catId])) {
            if (!is_object($this->category)) {
                $this->category = Mage::getModel('catalog/category')->load($this->category);
            }
            self::$categoryUrlHash[$catId] = $this->category->getUrl();
        }
        return self::$categoryUrlHash[$catId];
    }

    protected function isNewOrSale()
    {
        return in_array($this->moduleName, array('catalognew', 'catalogsale'));
    }

    protected function isSomeSearch()
    {
        return $this->isCatalogSearch() || $this->isCategorySearch();
    }

    protected function isCatalogSearch()
    {
        return in_array($this->moduleName, array('sqli_singlesearchresult', 'catalogsearch'));
    }

    protected function isCategorySearch()
    {
        return $this->moduleName == 'categorysearch';
    }

    protected function getCurrentLandingKey()
    {
        return Mage::app()->getRequest()->getParam('am_landing');
    }

    protected function getUrlHelper()
    {
        /** @var Amasty_Shopby_Helper_Url $helper */
        $helper = Mage::helper('amshopby/url');
        return $helper;
    }

    protected function isUrlKeyMode()
    {
        return self::$mode == Amasty_Shopby_Model_Source_Url_Mode::MODE_MULTILEVEL || self::$mode == Amasty_Shopby_Model_Source_Url_Mode::MODE_DISABLED;
    }

    public function getAttributeOptionConfig($code, $value) {

        $attrPosition = 100;  //default sorting position

        if(self::$attributesPositions && isset(self::$attributesPositions[$code])) {
            $attrPosition = self::$attributesPositions[$code];
        }

        $options = $this->getUrlHelper()->getAllFilterableOptionsAsHash();
        $attrCode = str_replace(array('_', '-'), self::$specialChar, $code);
        if (self::$mode && !$this->isSomeSearch() && (isset($options[$attrCode]) && $options[$attrCode])) {

            $resultCode = $attrCode;
            $resultOption = $this->getUrlHelper()->formatAttributePartForApply($attrCode, $value);
            $attributeType = 'seo';

        } else {
            $resultCode = $code;
            $resultOption = $value;
            $attributeType = 'get';
        }

        $additionalLinkData = array(
            'code' => $resultCode,
            'option' => $resultOption,
            'type' => $attributeType,
            'position' =>$attrPosition
        );

        return $additionalLinkData;
    }


    public function getUrlConfig() {

        switch (self::$mode) {
            case Amasty_Shopby_Model_Source_Url_Mode::MODE_SHORT:
                $urlType = 'short';
                break;
            case Amasty_Shopby_Model_Source_Url_Mode::MODE_MULTILEVEL:
                $urlType = 'long';
                break;
            default:
                $urlType = 'disabled';
                break;
        }

        switch (self::$filterUrlSortMode) {
            case Amasty_Shopby_Model_Source_Url_SortMode::MODE_CODE:
                $sortType = 'code';
                break;
            case Amasty_Shopby_Model_Source_Url_SortMode::MODE_POSITION:
                $sortType = 'position';
                break;
            default:
                $sortType = 'code';
                break;
        }

        $urlAndLocation = $this->getFormattedBaseUrlAndLocationAliasForApply();
        
        $pageParams  = array(
            "url_type"      => $urlType,
            "base_url"      => $urlAndLocation['url'],
            "url_key"       => self::$reservedKey,
            "url_suffix"    => $this->getUrlHelper()->getUrlSuffix(),
            "option_char"   => Mage::getStoreConfig('amshopby/seo/option_char'),
            "attr_glue"     => (self::$mode == Amasty_Shopby_Model_Source_Url_Mode::MODE_SHORT) ? Mage::getStoreConfig('amshopby/seo/option_char') : '/',
            "hide_names"    => (Mage::getStoreConfig('amshopby/seo/hide_attributes') || (self::$mode == Amasty_Shopby_Model_Source_Url_Mode::MODE_SHORT)),
            "brand_attr"    => self::$brandAttributeCode,
            "brand_url_key" => self::$brandUrlKey,
            "location"      => $urlAndLocation['location'],
            "query_param_for_multiple" => self::$multiselectQueryParam,
            "sort_by"       => $sortType,
        );

        return $pageParams;
    }

    public function getFormattedBaseUrlAndLocationAliasForApply() {

        $rootId = (int) Mage::app()->getStore()->getRootCategoryId();
        $isSecure = Mage::app()->getStore()->isCurrentlySecure();
        $base = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, $isSecure);
        $location = '';
        if ($this->isCatalogSearch()){
            $url = $base . 'catalogsearch/result/';
            $location = 'search';
        } elseif ($this->isNewOrSale()) {
            $url = $base . $this->moduleName;
            $location = 'new_or_sale';
        } elseif ($this->getCurrentLandingKey()) {
            $url = $base . $this->getCurrentLandingKey();
            $location = 'landing';
        } elseif ($this->isCategorySearch()) {
            $url = $base . 'categorysearch/categorysearch/search/';
            $location = 'category_search';
        } elseif (Mage::app()->getRequest()->getModuleName() == 'cms' && $this->getCategoryId() == $rootId) { // homepage,
            $url = $base;
            $location = 'home';
        } elseif ($this->getCategoryId() == $rootId) {
            $url = $base;
            $location = 'root';
        } else { // we have a valid category
            $url = $this->getCategoryUrl();
            $pos = strpos($url,'?');
            $url = $pos ? substr($url, 0, $pos) : $url;
            $url = $this->getUrlHelper()->checkRemoveSuffix($url);
            $location = 'category';
        }


        return array('url' => $url, 'location' => $location);
    }

    public function getNotFilterQueryParams() {
        $this->updateEffectiveQuery();
        $query = array();

        $options = $this->getUrlHelper()->getAllFilterableOptionsAsHash();
        foreach ($this->effectiveQuery as $origAttrCode => $ids) {
            $attrCode = str_replace(array('_', '-'), self::$specialChar, $origAttrCode);

            if ((!isset($options[$attrCode]) )){
                $query[] = array (
                    'code' => $origAttrCode,
                    'option' => $ids,
                    'type' => 'get'
                );
            }
        }

        return $query;
    }

    public function isBrandParamSeo() {
        $options = $this->getUrlHelper()->getAllFilterableOptionsAsHash();
        $attrCode = str_replace(array('_', '-'), self::$specialChar, self::$brandAttributeCode);
        return $attrCode !== '' && isset($options[$attrCode]) && !empty($options[$attrCode]);
    }
}
