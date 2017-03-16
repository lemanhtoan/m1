
var amshopby_apply_builder = {

    button_selector : '.amshopby-apply-button',

    pageProperties : [],
    filtersList : [],
    pendingFilters : [],

    init: function() {
        this.reset();
        this.parsePageProperties();
        this.parseAppliedFilters();
        this.parseNotFilterableParams();
        this.addEventsHandlers();
        this.disableApplyButton();
        this.overrideExternalFunctions();
    },

    reset : function () {
        this.pageProperties = [];
        this.filtersList = [];
        this.pendingFilters = [];
    },

    parsePageProperties : function () {
        var pageData = window.apply_page_data;

        if (((typeof pageData === 'string') || (pageData instanceof String)) && pageData.isJSON()) {
            pageData = pageData.evalJSON();
        } else {
            throw new Error('Cannot convert page properties from JSON');
        }

        this.pageProperties = pageData;
    },

    parseAppliedFilters : function () {
        var filters = window.applied_filters_codes;

        if (((typeof filters === 'string') || (filters instanceof String)) && filters.isJSON()) {
            filters = filters.evalJSON();
        } else {
            throw new Error('Cannot convert applied filters properties');
        }

        if(Array.isArray(filters)) {
            filters.each(function(filter){
                amshopby_apply_builder.toggleAttribute(filter, false, amshopby_apply_builder.filtersList);
            });
        }
    },

    parseNotFilterableParams : function() {
        var params = window.not_filters_query_params;
        if ((typeof params === 'string' || params instanceof String) && params.isJSON()) {
            params = params.evalJSON();
        } else {
            throw new Error('Cannot convert not filter params');
        }

        if(Array.isArray(params)) {
            params.each(function(attr){
                if (attr.hasOwnProperty('option') && (attr.option !== '')) {
                    var options = attr.option.split(',');
                    options.each(function (opt) {
                        attr.option = opt;
                        amshopby_apply_builder.toggleAttribute(attr, false, amshopby_apply_builder.filtersList);
                    });
                }
            });
        }
    },

    addEventsHandlers : function () {
        var links = $$('div.block-layered-nav dl a');

        links.each(function(e){
            var p = e.up();
            if ((p.hasClassName('amshopby-cat') && !p.hasClassName('amshopby-cat-multi')) || p.hasClassName('amshopby-clearer')){
                return;
            }

            e.onclick = function(){
                var isSingleChoice = false;
                if(this.parentNode.hasClassName('amshopby-cat')) {
                    this.parentNode.toggleClassName('amshopby-cat-multiselected');
                }
                else{
                    // Deselect previous radio
                    if (this.parentNode.parentNode.hasClassName('single-choice')) {
                        isSingleChoice = true;
                        this.parentNode.parentNode.select('.amshopby-attr-selected').each(function (a) {
                            a.toggleClassName('amshopby-attr');
                            a.toggleClassName('amshopby-attr-selected');
                        });
                    }

                    this.toggleClassName('amshopby-attr');
                    this.toggleClassName('amshopby-attr-selected');
                }

                var s = this;
                var isSuccess = amshopby_apply_builder.parseNewFilterAttributes(s.dataset.config, isSingleChoice);
                if (isSuccess)
                    return false;
            };
        });

        var selectLinks = $$('div.block-layered-nav dl select');

        selectLinks.each(function(select){
            if(select.up().hasClassName('amshopby-clearer') || select.up().hasClassName('amshopby-category-select')) {
                return;
            }

            select.onchange = 'return false';
            select.stopObserving('change');

            Event.observe(select, 'change', function(e){
                var dataConfig = select.options[select.options.selectedIndex].dataset.config;

                if(!dataConfig.isJSON()) {
                    return false;
                }
                var params = dataConfig.evalJSON();

                var isSingleChoice = select.parentNode.parentNode.hasClassName('single-choice');

                if (!isSingleChoice) {
                    if (params.hasOwnProperty('option') && (params.option !== '')) {
                        if (select.options[select.options.selectedIndex].hasAttribute('selected')) {
                            select.options[select.options.selectedIndex].removeAttribute('selected');
                        } else {
                            select.options[select.options.selectedIndex].setAttribute('selected', true);
                        }
                    } else {
                        for (var i = 0; i < select.options.length; i++) {
                            var option = select.options[i];
                            if(option.hasAttribute('selected')) {
                                option.removeAttribute('selected');
                            }
                        }
                    }
                }

                amshopby_apply_builder.parseNewFilterAttributes(dataConfig, isSingleChoice);

                Event.stop(e);
            });
        });

        var buttons = $$(amshopby_apply_builder.button_selector);
        buttons.each(function (e) {
            Event.observe(e, 'click', function (button) {
                amshopby_apply_builder.onApplyButtonClick(button);
            });
        });

        $$('.block-layered-nav .form-button').each(function (e){
            e.stopObserving('click');
            e.observe('click', amshopby_apply_builder.priceClickCallbackOverride);
        });

    },

    disableApplyButton : function () {
        var buttons = $$(amshopby_apply_builder.button_selector);
        buttons.each(function (b) {
            b.disabled = true;
        });
    },

    enableApplyButton : function () {
        var buttons = $$(amshopby_apply_builder.button_selector);
        buttons.each(function (b) {
            b.disabled = false;
        });
    },

    overrideExternalFunctions : function () {
        window.amshopby_price_click_callback = this.priceClickCallbackOverride;
        window.amshopby_slider_ui_apply_filter = this.sliderUiApplyFilterOverride;
    },

    sliderUiApplyFilterOverride : function (evt, values, slider) {
        if (evt && evt.type == 'keypress' && 13 != evt.keyCode)
            return;

        var prefix = 'amshopby-price';

        if (typeof(evt) == 'string'){
            prefix = evt;
        }

        var a = prefix + '-from';
        var b = prefix + '-to';

        var urlElement =  $amQuery('#' + prefix + '-url')[0];

        var dataConfig = urlElement.dataset.config;
        if(dataConfig.isJSON()) {
            dataConfig = dataConfig.evalJSON();
            dataConfig.option = dataConfig.option.replace(a, values[0]).replace(b, values[1]);
            amshopby_apply_builder.updateAttributeSets(dataConfig, true);
        }
    },

    priceClickCallbackOverride : function (evt, element) {
        if( !element || element.nodeName == undefined ) element = null;
        if( typeof evt == 'object' && !element){
            element = Event.element(evt);
        }
        if (evt && evt.type == 'keypress' && 13 != evt.keyCode)
            return;

        var prefix = 'amshopby-price';
        // from slider
        if (typeof(evt) == 'string'){
            prefix = evt;
        }
        else {
            var el = Event.findElement(evt, 'input');
            if (!Object.isElement(el)){
                el = Event.findElement(evt, 'button');
            }
            prefix = el.name;
        }

        var a = prefix + '-from';
        var b = prefix + '-to';
        //get elements from parent container
        var parent = (element)? element.up('ol'): null;
        var from   = (parent)? parent.select('#' + a).first(): $(a);
        var to     = (parent)? parent.select('#' + b).first(): $(b);

        if(!from ||!to)
            return;

        if(from.value == '') {
            from.value = 0;
        }

        if(to.value == '' && to.getAttribute('data-value')) {
            to.value = to.getAttribute('data-value');
        }

        var numFrom = parseFloat(from.value);
        if (isNaN(numFrom)) {
            numFrom = '';
        }
        var numTo   = parseFloat(to.value);
        if (isNaN(numTo)) {
            numTo = '';
        }

        if (numFrom>numTo && numFrom != '' && numTo != '') numTo = [numFrom, numFrom = numTo][0];

        if (numFrom < 0 || numTo < 0) {
            return;
        }

        var urlElement = (parent)? parent.select('#' + prefix +'-url').first(): $(prefix +'-url');
        if ((numFrom!=='') || (numTo!=='')) {
            var dataConfig = urlElement.dataset.config;
            if(dataConfig.isJSON()) {
                dataConfig = dataConfig.evalJSON();
                dataConfig.option = dataConfig.option.gsub(a, numFrom).gsub(b, numTo);
                amshopby_apply_builder.updateAttributeSets(dataConfig, true);
            }
        }
    },

    parseNewFilterAttributes : function (urlParams, isSingleChoice) {
        if(!((typeof urlParams === 'string') || (urlParams instanceof String)) || !urlParams.isJSON()) {
            return false;
        }

        var params = urlParams.evalJSON();

        amshopby_apply_builder.updateAttributeSets(params, isSingleChoice);

        return true;
    },

    updateAttributeSets : function(params, isSingleChoice) {
        if(params.hasOwnProperty('option') && (params.option !== '')) {
            amshopby_apply_builder.toggleAttribute(params, isSingleChoice, this.filtersList);
            amshopby_apply_builder.toggleAttribute(params, isSingleChoice, this.pendingFilters);
        } else {//remove params
            amshopby_apply_builder.toggleAttribute(params, true, this.filtersList);
            amshopby_apply_builder.toggleAttribute(params, true, this.pendingFilters);
         }

        amshopby_apply_builder.onAttributeSetChanging();
    },

    toggleAttribute: function (params, isSingleChoice, attributeSet) {

        if ((params.code != undefined) && (params.option != undefined) && (params.type != undefined)) {

            var isAttrChanged = false;
            var attrs = attributeSet;
            for(var index = 0; index < attrs.length; index++) {
                var attribute = attrs[index];
                if(attribute.code === params.code) {
                    if(isSingleChoice) {
                        attrs[index].options = [{name: String(params.option)}];
                        isAttrChanged = true;
                        break;
                    }
                    
                    if(attribute.hasOwnProperty('options')) {
                        var opts = attribute.options;
                        var isDeleted = false;

                        for (var i = 0; i < opts.length; i++) {
                            if (opts[i].name == params.option) {
                                opts.splice(i, 1);
                                isDeleted = true;
                                break;
                            }
                        }

                        if(!isDeleted) {
                            attribute.options.push(
                                {
                                    name : params.option
                                }
                            );
                        }
                        else {
                            if (attribute.options.length === 0) {
                                attrs.splice(index, 1);
                            }
                        }

                        isAttrChanged = true;
                        break;
                    }
                }
            }

            if (!isAttrChanged) {
                attributeSet.push(
                    {
                        code : String(params.code),
                        type : params.type,
                        options : [
                            {
                                name : String(params.option)
                            }
                        ]
                    }
                );
            }
        }
    },

    onAttributeSetChanging : function () {
        var attributeSet = amshopby_apply_builder.pendingFilters;
        if(attributeSet.length === 0) {
            this.disableApplyButton();
        } else {
            this.enableApplyButton();
        }
    },

    onApplyButtonClick : function () {

        this.checkAddMultiselectParam();
        this.sortAttributes();

        var link = this.generateUrl();
        this.changeLocation(link);
    },

    checkAddMultiselectParam : function () {
        var multOptsExist  = this.isMultipleOptionsExist();
        var multParamExist = this.isMultiselectParamExist();
        if ((multOptsExist && !multParamExist) || (!multOptsExist && multParamExist)) {
            this.toggleAttribute(
                {
                    code : this.pageProperties.query_param_for_multiple,
                    type : 'get',
                    option : 'true'
                },
                false,
                this.filtersList);
        }
    },

    sortAttributes : function () {

        this.filtersList.each(function (e) {
            if (e.hasOwnProperty('options')) {
                e.options.sort(function (a, b) {
                    return a.name.localeCompare(b.name);
                });
            }
        });

        switch (this.pageProperties.sort_by) {
            case 'code':
                this.filtersList.sort(this.sortByCodeCallback);
                break;
            case 'position' :
                this.filtersList.sort(this.sortByPositionCallback);
                break;
            default :
                this.filtersList.sort(this.sortByCodeCallback);
                break;
        }

    },

    sortByPositionCallback : function (a, b) {

        var defaultSortPosition = 100;
        var brandAttr = amshopby_apply_builder.pageProperties.brand_attr;

        if(a.hasOwnProperty('code') && (brandAttr !== '') && (a.code == brandAttr)) {
            return -1;
        }

        if(b.hasOwnProperty('code') && (brandAttr !== '') && (b.code == brandAttr)) {
            return 1;
        }

        if (!a.hasOwnProperty("position")) {
            a.position = defaultSortPosition;
        }
        if (!b.hasOwnProperty("position")) {
            b.position = defaultSortPosition;
        }

        if (a.position > b.position) {
            return 1;
        } else if(a.position < b.position) {
            return -1;
        } else {

            if (a.hasOwnProperty('code') && b.hasOwnProperty('code')) {
                return a.code.localeCompare(b.code);
            } else {
                return 0
            }
        }
    },

    sortByCodeCallback : function (a, b) {
        var brandAttr = amshopby_apply_builder.pageProperties.brand_attr;
        if(a.hasOwnProperty('code') && (brandAttr !== '') && (a.code == brandAttr)) {
            return -1;
        }

        if(b.hasOwnProperty('code') && (brandAttr !== '') && (b.code == brandAttr)) {
            return 1;
        }

        return a.code.localeCompare(b.code);
    },

    generateUrl : function() {

        var paramPart = this.getFormattedParamPart();
        var basePart = this.getFormattedBasePart(paramPart);

        var url = basePart;
        if ((paramPart !== '') && (paramPart[0] != '?')) {
            url = [basePart, paramPart].join('/');
        }

        if ((paramPart !== '') && (paramPart[0] == '?')) {
            url = basePart + paramPart;
        }

        url = url.replace(/[^:]\/\//g,'/');
        return url;
    },

    getFormattedParamPart : function () {
        var paramPart = '';
        if (this.filtersList) {
            var attributes = this.filtersList;
            var getAttributes = [];
            var seoAttributes = [];

            attributes.each(function (attribute) {
                var optionsNames = [];
                if(attribute.options.length > 0) {
                    attribute.options.each(function (option) {
                        if(option.name !== '') {
                            optionsNames.push(option.name);
                        }
                    });
                }

                if(optionsNames.length >0) {
                    var formattedOptions = amshopby_apply_builder.formatAttribute(attribute.code, optionsNames, attribute.type);

                    if (attribute.type === 'seo') {
                        seoAttributes.push(formattedOptions);
                    } else if (attribute.type === 'get') {
                        getAttributes.push(formattedOptions);
                    }
                }
            });

            paramPart = this.joinAttributes(getAttributes, seoAttributes);
        }

        return paramPart;
    },

    getFormattedBasePart : function (paramPart) {
        var baseUrl = amshopby_apply_builder.pageProperties.base_url;
        var brandUrlKey = amshopby_apply_builder.pageProperties.brand_url_key;
        var urlKey = amshopby_apply_builder.pageProperties.url_key;
        var suffix = amshopby_apply_builder.pageProperties.url_suffix;
        var isSeoAttributePartExist = paramPart.length && (paramPart.indexOf('?') !== 0);
        var location = amshopby_apply_builder.pageProperties.location;

        if (baseUrl.substr(-1) === '/') {
            baseUrl = baseUrl.substr(0,baseUrl.length-1);
        }

        if((baseUrl !== '') && (this.checkIsNeedUrlKey(paramPart))) {
            baseUrl = [baseUrl, urlKey].join('/');
        }

        if (isSeoAttributePartExist && this.isBrandPage() && this.isBrandParamSeo() && (brandUrlKey !== '')) {
            baseUrl = [baseUrl, brandUrlKey].join('/');
        }

        if(!isSeoAttributePartExist && (['search', 'category_search', 'root', 'home'].indexOf(location) === -1)) {
            baseUrl += suffix;
        }

        return baseUrl;
    },

    formatAttribute : function (code, options, type) {
        var isHideAttrNames = amshopby_apply_builder.pageProperties.hide_names;
        var formattedAttributeString = '';
        var optionSeparator = (type === 'seo') ? amshopby_apply_builder.pageProperties.option_char : ',';
        var codeAndOptionsSeparator = (type === 'seo') ? amshopby_apply_builder.pageProperties.option_char : '=';

        if (options.length > 0) {
            formattedAttributeString += options.join(optionSeparator);

            if(type === 'get') {
                formattedAttributeString = encodeURIComponent(formattedAttributeString);
            }

            if ((formattedAttributeString !== '') && (!isHideAttrNames || (type == 'get'))) {
                formattedAttributeString = code + codeAndOptionsSeparator + formattedAttributeString;
            }
        }

        return formattedAttributeString;
    },

    joinAttributes : function (getAttributes, seoAttributes) {
        var getPart = getAttributes.join('&');
        var seoPart = seoAttributes.join(amshopby_apply_builder.pageProperties.attr_glue);
        if (seoPart !== '') {
            seoPart += amshopby_apply_builder.pageProperties.url_suffix;
        }

        var attrPart = seoPart;
        if (getPart !== '') {
            attrPart += '?' + getPart;
        }

        return attrPart;
    },

    checkIsNeedUrlKey : function (paramPart) {
        var location = amshopby_apply_builder.pageProperties.location;
        var isSeoAttributePartExist = paramPart.length && (paramPart.indexOf('?') !== 0);
        var isNeedUrlKey = false;
        var urlType = amshopby_apply_builder.pageProperties.url_type;
        switch (location) {
            case 'home' ://no break;
            case 'root' :
                switch (urlType) {
                    case 'long' :
                        isNeedUrlKey = !this.isBrandPage() || !this.isBrandParamSeo();
                        break;
                    case 'short' :
                        isNeedUrlKey = !isSeoAttributePartExist;
                        break;
                    case 'disabled' :
                        isNeedUrlKey = true;
                        break;
                }
                break;
            case 'category' :
                if (isSeoAttributePartExist) {
                    isNeedUrlKey = (['long', 'disabled'].indexOf(urlType) !== -1);
                }
                break;
        }

        return isNeedUrlKey;
    },

    changeLocation : function (link) {
        if ((typeof amshopby_ajax_push_state === 'function') && (typeof amshopby_ajax_request === 'function')) {
            try {
                amshopby_ajax_push_state(link);
                amshopby_ajax_request(link);
            } catch(e) {
                console.log(e.message);
                setLocation(link);
            }

        } else {
            setLocation(link);
        }
    },

    isMultipleOptionsExist : function () {
        var param = this.pageProperties.query_param_for_multiple;
        var isMultipleOptionsExist = false;
        if(param !== '') {
            this.filtersList.each(function (e) {
                var optionsCount = 0;
                if (e.hasOwnProperty('options') && e.options.length > 1) {
                    e.options.each(function (option) {
                        if (option.hasOwnProperty('name') && (option.name !== '')) {
                            optionsCount++;
                        }
                    });
                    if(optionsCount > 1) {
                        isMultipleOptionsExist = true;
                    }
                }
            });
        }
        return isMultipleOptionsExist;
    },

    isMultiselectParamExist : function () {
        var param = this.pageProperties.query_param_for_multiple;
        var isExist = false;
        if(param !== '') {
            this.filtersList.each(function (e) {
                if(e.hasOwnProperty('code') && (e.code == param)){
                    isExist = true;
                }
            });
        }

        return isExist;
    },

    isBrandPage : function () {
        var location = amshopby_apply_builder.pageProperties.location;
        var urlType = amshopby_apply_builder.pageProperties.url_type;
        var isBrand = false;
        var brandAttr = amshopby_apply_builder.pageProperties.brand_attr;
        if ((brandAttr !== '') && (urlType !== 'disabled') && (['home', 'root'].indexOf(location) !== -1)) {
            amshopby_apply_builder.filtersList.each(function (e) {
                if((e.code === brandAttr) && (e.options.length > 0)) {
                    e.options.each(function (option) {
                        if (option.hasOwnProperty('name') && (option.name !== '')) {
                            isBrand = true;
                        }
                    });
                }
            });
        }
        return isBrand;
    },

    isBrandParamSeo : function () {
        var isSeo = false;
        var brandAttr = this.pageProperties.brand_attr;
        if (brandAttr.trim() !== '') {
            this.filtersList.each(function (filter) {
                if(filter.hasOwnProperty('type') && filter.hasOwnProperty('code') && (filter.code === brandAttr) && (filter.type === 'seo')) {
                    isSeo = true;
                }
            });
        }

        return isSeo;
    }

};


document.observe("dom:loaded", function() {
    amshopby_apply_builder.init();
});
