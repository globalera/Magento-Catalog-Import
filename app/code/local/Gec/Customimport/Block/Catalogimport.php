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

class Gec_Customimport_Block_Catalogimport extends Mage_Core_Block_Template
{
  	public $_log_array = array();
    public $_xmlObj;
    public $default_asid;
    public $_product_list;
    public $_category_list;
    public $_cat_relation;
    public $_current_row = 1;
    public $_curitemids = array("sku"=>null);
    public $_optidcache = null;
    public $_dstore = array();
    public $_same;
    public $mode = "create";
    public $prod_etype = 4;
    public $_updated_num = 0;
    public $_created_num = 0;
    public $attributeGroupsGlobal = array();   
    public $_store_id;
    public $_default_category_id;
}
?>