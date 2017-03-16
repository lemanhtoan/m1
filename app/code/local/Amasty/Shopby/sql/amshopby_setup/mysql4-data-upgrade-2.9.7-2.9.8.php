<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2016 Amasty (https://www.amasty.com)
 * @package Amasty_Shopby
 */


$dropdownDisplayType = Amasty_Shopby_Model_Source_Attribute::DT_DROPDOWN;

$this->run("
UPDATE `{$this->getTable('amshopby/filter')}` f
SET f.`single_choice` = 1
WHERE f.`display_type` = {$dropdownDisplayType}
");
