<?php
/**
#    Copyright (C) 2013 Global Era (http://www.globalera.com). All Rights Reserved
#    @author Serenus Infotech <magento@serenusinfotech.com>
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU Affero General Public License as
#    published by the Free Software Foundation, either version 3 of the
#    License, or (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
**/

$installer = $this;
$installer->startSetup();

$installer->run("
 DROP TABLE IF EXISTS `{$this->getTable('external_attrgroup_mapping_info')}`;
CREATE TABLE IF NOT EXISTS `{$this->getTable('external_attrgroup_mapping_info')}` (
  `external_attrgroup_id` int(12) NOT NULL AUTO_INCREMENT COMMENT 'auto increment id',
  `attribute_set_id` smallint(5) unsigned NOT NULL COMMENT 'attribute set id',
  `external_id` varchar(255) NOT NULL,
  `magento_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`external_attrgroup_id`),
  UNIQUE KEY `magento_id` (`magento_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

 DROP TABLE IF EXISTS `{$this->getTable('external_attrsetmapping_info')}`;
CREATE TABLE IF NOT EXISTS `{$this->getTable('external_attrsetmapping_info')}` (
  `external_attrsetmapping_id` int(12) NOT NULL AUTO_INCREMENT,
  `external_id` varchar(255) NOT NULL,
  `magento_id` smallint(5) unsigned NOT NULL COMMENT 'same as attribute_set_id',
  PRIMARY KEY (`external_attrsetmapping_id`),
  UNIQUE KEY `external_id` (`external_id`,`magento_id`),
  KEY `magento_id` (`magento_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

 DROP TABLE IF EXISTS `{$this->getTable('external_category_mapping_info')}`;
 CREATE TABLE IF NOT EXISTS `{$this->getTable('external_category_mapping_info')}` (
  `external_category_id` int(12) NOT NULL AUTO_INCREMENT,
  `external_id` varchar(255) NOT NULL,
  `magento_id` int(10) unsigned NOT NULL,
  `external_pid` varchar(255) NOT NULL,
  `magento_pid` int(10) unsigned NOT NULL,
  PRIMARY KEY (`external_category_id`),
  UNIQUE KEY `external_id` (`external_id`,`magento_id`),
  KEY `magento_id` (`magento_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

   ALTER TABLE `{$this->getTable('external_attrgroup_mapping_info')}` ADD FOREIGN KEY (`magento_id`) REFERENCES `{$this->getTable('eav_attribute_group')}` (`attribute_group_id`)
   ON DELETE CASCADE ON UPDATE CASCADE;

   ALTER TABLE `{$this->getTable('external_attrsetmapping_info')}` ADD FOREIGN KEY (`magento_id`) REFERENCES `{$this->getTable('eav_attribute_set')}` (`attribute_set_id`)
   ON DELETE CASCADE ON UPDATE CASCADE;

   ALTER TABLE `{$this->getTable('external_category_mapping_info')}` ADD FOREIGN KEY (`magento_id`) REFERENCES `{$this->getTable('catalog_category_entity')}` (`entity_id`)
   ON DELETE CASCADE ON UPDATE CASCADE;

    ");


$resource = Mage::getSingleton('core/resource');
$read = $resource->getConnection('core_read');
$write = $resource->getConnection('core_write');

$database = Mage::getConfig()->getResourceConnectionConfig('default_setup')->dbname;
$option_table = Mage::getSingleton('core/resource')->getTableName('eav_attribute_option');
$attribute_table = Mage::getSingleton('core/resource')->getTableName('eav_attribute');

$query = "SELECT COUNT(*) as abc FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = '".$database."' AND
 TABLE_NAME = '".$option_table."' AND COLUMN_NAME = 'externalid'";

$results = $read->fetchRow($query);

if($results['abc'] == '0'):
    $result = $write->query("ALTER TABLE $option_table 
		ADD `externalid` VARCHAR( 255 ) NOT NULL AFTER `sort_order` ");

endif;

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$query_2 = "Select attribute_code from $attribute_table 
	    where entity_type_id = '3'
	    and attribute_code = 'external_id'";

$result_2 = $read->fetchRow($query_2);

if($result_2['attribute_code'] != 'external_id'):
   $setup->addAttribute('catalog_category', 'external_id', array(
    'group'         => 'General Information',
    'input'         => 'text',
    'type'          => 'varchar',
    'label'         => 'External id',        
    'backend'       => '',
    'visible'       => 1,   
    'required'      => 0,
    'user_defined'  => 1,
    'default'       => '',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,     
));

endif;
$query_3 = "Select attribute_code from $attribute_table 
	    where entity_type_id = '3'
	    and attribute_code = 'external_cat_image'";
$result_3 = $read->fetchRow($query_3);


if($result_3['attribute_code'] != 'external_cat_image'):
    $setup->addAttribute('catalog_category', 'external_cat_image', array(
    'group'         => 'General Information',
    'input'         => 'text',
    'type'          => 'varchar',
    'label'         => 'External Image',        
    'backend'       => '',
    'visible'       => 1,   
    'required'      => 0,
    'user_defined' => 1,
    'default'       => '',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,     
));

endif;




   $setup->addAttribute('catalog_product', 'external_image', array(
    'group'         => 'Images',
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'External image',
    'backend'       => '',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
    'searchable' => 1,
    'filterable' => 0,
    'comparable'    => 1,
    'visible_on_front' => 1,
    'visible_in_advanced_search'  => 0,
    'is_html_allowed_on_front' => 0,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$setup->addAttribute('catalog_product', 'external_small_image', array(
    'group'         => 'Images',
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'External small image',
    'backend'       => '',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
    'searchable' => 1,
    'filterable' => 0,
    'comparable'    => 1,
    'visible_on_front' => 1,
    'visible_in_advanced_search'  => 0,
    'is_html_allowed_on_front' => 0,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));


$setup->addAttribute('catalog_product', 'external_thumbnail', array(
    'group'         => 'Images',
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'External thumbnail',
    'backend'       => '',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
    'searchable' => 1,
    'filterable' => 0,
    'comparable'    => 1,
    'visible_on_front' => 1,
    'visible_in_advanced_search'  => 0,
    'is_html_allowed_on_front' => 0,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));


$installer->endSetup();