<?php
/**
#    Copyright (C) 2013-2015 Global Era Commerce (http://www.globalera.com). All Rights Reserved
#    @author Serenus Infotech <magento@serenusinfotech.com>
#    @author Intelliant <magento@intelliant.net>
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

class Gec_Customimport_Block_Adminhtml_Customimport extends Gec_Customimport_Block_Adminhtml_Catalogimport
{
    protected $customHelper;
    protected $logPath;
    
    public function __construct()
    {
        parent::__construct();
        $this->customHelper = Mage::helper('customimport');
        $this->logPath      = Mage::getBaseDir('log') . '/customimport.log';
    }
    
    public function parseXml($xmlPath)
    {
        $this->_store_id            = Mage::app()->getWebsite()->getDefaultGroup()->getDefaultStoreId();
        $this->_default_category_id = Mage::app()->getStore('default')->getRootCategoryId();
        $xmlObj                     = new Varien_Simplexml_Config($xmlPath);
        $this->_xmlObj              = $xmlObj;
    }
    
    public function reindexDB($val)
    {
        $process = Mage::getModel('index/process')->load($val);
        $process->reindexAll();
    }
    
    public function countAll()
    {
        $xmlObj               = $this->_xmlObj;
        $xmlData              = $xmlObj->getNode();
        $this->_category_list = $xmlData->productAssociations->association;
        $this->lookup($this->_category_list);
    }
    
    public function countCategory()
    {
        $xmlObj               = $this->_xmlObj;
        $xmlData              = $xmlObj->getNode();
        $this->_category_list = $xmlData->categories->category;
        return $this->lookup($this->_category_list);
    }
    
    public function countProduct()
    {
        $xmlObj              = $this->_xmlObj;
        $xmlData             = $xmlObj->getNode();
        $this->_product_list = $xmlData->products->product;
        return $this->lookup($this->_product_list);
    }    
    
    public function showCategory()
    {
        $xmlObj  = $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if ($xmlData->categories->category instanceof Varien_Simplexml_Element) {
            return $xmlData->categories->category;
        } else {
            return false;
        }
    }
    
    public function importAllCategory($categories)
    {
        $this->_created_num = 0;
        $this->_updated_num = 0;
        foreach ($categories as $category) {
            $this->customHelper->reportInfo($this->customHelper->__('Start process for category # %s', $category->id));
            $this->importCategory($category);
            $this->customHelper->reportInfo($this->customHelper->__('End process for category # %s', $category->id));
        }
        $this->customHelper->reportSuccess($this->customHelper->__('categories was successfully created %s', $this->_created_num));
        $this->customHelper->reportSuccess($this->customHelper->__('categories was successfully updated %s', $this->_updated_num));
        $this->_created_num = 0;
        $this->_updated_num = 0;
    }
    
    public function parseAttribute()
    {
        $xmlObj  = $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if ($xmlData->attributeConfiguration->attributes->attribute instanceof Varien_Simplexml_Element) {
            return $xmlData->attributeConfiguration->attributes->attribute;
        }
    }
    
    public function importAttribute($parsedAttribute)
    {
        foreach ($parsedAttribute as $attribute) {
            $this->customHelper->reportInfo($this->customHelper->__('Start process for attribute # %s', $attribute->id));
            $this->createAttribute($attribute);
            $this->customHelper->reportInfo($this->customHelper->__('End process for attribute # %s', $attribute->id));
        }
        $this->reindexDB(7); // 7 is used after attribute creation.
    }
    
    public function parseAttributeSet()
    {
        $xmlObj  = $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if ($xmlData->attributeConfiguration->attributeSets->attributeSet instanceof Varien_Simplexml_Element) {
            return $xmlData->attributeConfiguration->attributeSets->attributeSet;
        }
    }
    
    public function importAttributeSet($parsedAttributeSet)
    {
        $attributeSet_groups           = array();
        $attributeSet_groups['status'] = array();
        $attributeSet_groups['id']     = array();
        $attributegroup_id     = array();
        $attributegroup_status = array();
        
        foreach ($parsedAttributeSet as $set) {
            $this->customHelper->reportInfo($this->customHelper->__('Start process for attribute set # %s', $set->id));
            $attributeSetId   = (string) $set->id;
            $attributeSetName = (string) $set->name;
            
            if ($attributeSetName == '') {
                $attributeSetName = $attributeSetId;
            }
            $attributeGrp     = $set->attributeGroups->attributeGroup;
            $attribute_set_id = $this->createAttributeSet($attributeSetName, $attributeSetId);
            
            foreach ($attributeGrp as $attrgroup) {
                $attributegroup_id                                   = (string) $attrgroup->id;
                $attributegroup_status                               = (string) $attrgroup->isActive;
                $attributeSet_groups['id'][$attributegroup_id][]     = $attribute_set_id;
                $attributeSet_groups['status'][$attributegroup_id][] = $attributegroup_status;
            }
            unset($attributegroup_status);
            unset($attributegroup_id);
            $this->customHelper->reportInfo($this->customHelper->__('End process for attribute set # %s', $set->id));
        }
        $this->attributeGroupsGlobal = $attributeSet_groups;
    }
        
    public function createAttributeSet($attribute_set_name, $external_id)
    {
        $helper         = Mage::helper('adminhtml');
        $mapobj         = Mage::getModel('customimport/customimport');
        $attributeSetId = $mapobj->getAttributeSetIdByExternalId($external_id);
        $entityTypeId = $this->getEntityTypeId();
        $modelSet     = Mage::getModel('eav/entity_attribute_set')->setEntityTypeId($entityTypeId);
        
        if (isset($attributeSetId) && !empty($attributeSetId)) {
            $modelSet->load($attributeSetId);
            if (!$modelSet->getId()) {
                $this->customHelper->reportError($this->customHelper->__('This attribute set no longer exists'));
                Mage::throwException($this->customHelper->__('This attribute set no longer exists.'));
            }
            $modelSet->setAttributeSetName(trim($attribute_set_name));
            try{
                $modelSet->validate();
                $modelSet->save();
            }
            catch(Exception $e) {
                $this->customHelper->reportError($this->customHelper->__('Attribute set name %s with id %s already exists in Magento system with same name', $attribute_set_name, $external_id));
            }
            return $attributeSetId;
        }
        
        $modelSet->setAttributeSetName($attribute_set_name);    // to add attribute set
        $defaultAttributeSetId = $this->getAttributeSetId('Default');
        try {
            if ($modelSet->validate()) {
                $attributeSetId = $modelSet->save()->getAttributeSetId();
                $modelSet->initFromSkeleton($defaultAttributeSetId)->save();
            }
        }
        catch (Exception $e) {
            $this->customHelper->reportError($this->customHelper->__('Attribute set name %s with id %s already exists in Magento system with same name', $attribute_set_name, $external_id));
        }
        $mapobj->mapAttributeSet($external_id, $attributeSetId);
        return $attributeSetId;
    }
        
    public function parseAttributegrp()
    {
        $xmlObj  = $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if ($xmlData->attributeConfiguration->attributeGroups->attributeGroup instanceof Varien_Simplexml_Element) {
            return $xmlData->attributeConfiguration->attributeGroups->attributeGroup;
        }
    }
    
    public function importAttributeGrp($parsedAttribute)
    {
        $setOfGroups = $this->attributeGroupsGlobal;
        foreach ($parsedAttribute as $attribute) {
            $attributeSets             = array();
            $attributesOfGroup         = array(); // array to store attribute detail info of attribute group
            $attributesIdOfGroup       = array();
            $attributesStatusOfGroup   = array();
            $attributesSequenceOfGroup = array();
            $groupAttributes = $attribute->groupedAttributes->attribute;
            foreach ($groupAttributes as $grp) {
                $attributesIdOfGroup[]       = (string) $grp->id;
                $attributesStatusOfGroup[]   = (string) $grp->isActive ? (string) $grp->isActive : 'Y';
                $attributesSequenceOfGroup[] = (string) $grp->position ? (string) $grp->position : 0;
            }
            
            $attributesOfGroup['attr_ids']      = $attributesIdOfGroup;
            $attributesOfGroup['attr_status']   = $attributesStatusOfGroup;
            $attributesOfGroup['attr_sequence'] = $attributesSequenceOfGroup;
            $groupId   = (string) $attribute->id;
            $groupName = (string) $attribute->name;
            
            if ($groupName == '') {
                $groupName = $groupId;
            }
            
            $attributeSets['set_ids']    = $setOfGroups['id'][$groupId];
            $attributeSets['set_status'] = $setOfGroups['status'][$groupId];
            $this->manageAttributeGroup($groupName, $groupId, $attributeSets, $attributesOfGroup);
            unset($attributeSets);
            unset($attributesOfGroup);
            unset($attributesIdOfGroup);
            unset($attributesStatusOfGroup);
            unset($attributesSequenceOfGroup);
        }
    }
    
    /**
    * setname and group are arrays
    * @param $attribute_group_id is external group id
    */
    public function manageAttributeGroup($attribute_group_name, $attribute_group_id, $attribute_set_ids, $attributesOfGroup)
    {
        // loop for attribute sets
        foreach ($attribute_set_ids['set_ids'] as $k => $set_id) {
            if ($attribute_set_ids['set_status'][$k] == 'Y') {
                //create group here
                $res = $this->createAttributeGroup($attribute_group_name, $attribute_group_id, $set_id);
                // loop for all attributes
                foreach ($attributesOfGroup['attr_ids'] as $k => $attribute_code) {
                    if ($attributesOfGroup['attr_status'][$k] == 'Y') {
                        // insert attributes inside group
                        $attributeSortOrder = 0;
                        if (isset($attributesOfGroup['attr_sequence'])) {
                            $attributeSortOrder = $attributesOfGroup['attr_sequence'][$k];
                        }
                        $this->importAttributeInsideGroup($attribute_group_id, $set_id, $attribute_code, $attributeSortOrder);
                        
                    } else {
                        $this->removeAttributeFromGroup($attribute_group_id, $set_id, $attribute_code);
                    }
                }
            } else {
                // remove group from this set
                $this->removeAttributeGroup($attribute_group_name, $attribute_group_id, $set_id);
                
            }
        }
    }
    
    public function createAttributeGroup($attribute_group_name, $attribute_group_id, $attributeSetId)
    {
        $model            = Mage::getModel('eav/entity_attribute_group');
        $mapobj           = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId);
        if (isset($attributeGroupId) && !empty($attributeGroupId)) {
            $model->load($attributeGroupId);
            $oldGroupName = $model->getAttributeGroupName();
            if ($oldGroupName != $attribute_group_name) { // if name has been updated
                $model->setAttributeGroupName($attribute_group_name);
                if (!$model->itemExists()) {
                    $model->save();
                }
            }
        } else {
            $model->setAttributeGroupName($attribute_group_name)->setAttributeSetId($attributeSetId);
            if ($model->itemExists()) {
            } else {
                try {
                    $model->save();
                }
                catch (Exception $e) {
                    $this->customHelper->reportError($this->customHelper->__("An error occurred while saving this group."));
                }
            }
            $attributeGroupId = $mapobj->getGroupIdUsingSetId($attribute_group_name, $attributeSetId);
            $mapobj->mapAttributeGroup($attribute_group_id, $attributeGroupId, $attributeSetId); // externalid, magentoid
        }
    }
    
    public function removeAttributeFromGroup($attribute_group_id, $attributeSetId, $attribute_code)
    {
        $mapobj           = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId); // $attribute_group_id is external group id
        
        if ($attributeGroupId) {
            $setup        = new Mage_Eav_Model_Entity_Setup('core_setup');
            $attribute_id = $setup->getAttributeId('catalog_product', $attribute_code);
            $attribute_exists = $mapobj->isAttributeExistsInGroup($attribute_id, $attributeGroupId);
            if ($attribute_exists) {
                $installer = $this->getInstaller();
                $installer->startSetup();
                $installer->deleteTableRow('eav/entity_attribute', 'attribute_id', $attribute_id, 'attribute_set_id', $attributeSetId);
                $installer->endSetup();
            }
        }
    }
    
    public function removeAttributeGroup($attribute_group_name, $attribute_group_id, $attributeSetId)
    {
        $setup            = new Mage_Eav_Model_Entity_Setup('core_setup');
        $mapobj           = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId);
        if ($attributeGroupId) {
            $setup->removeAttributeGroup('catalog_product', $attributeSetId, $attributeGroupId);
        } else {
            $this->customHelper->reportInfo($this->customHelper->__("Attribute Group is not available to be removed."));
        }
    }
        
    public function importAttributeInsideGroup($attribute_group_id, $attributeSetId, $attribute_code, $attribute_sort_order)
    {
        $mapobj           = Mage::getModel('customimport/customimport');
        $attributeGroupId = $mapobj->getAttributeGroupByExternalId($attribute_group_id, $attributeSetId); // $attribute_group_id is external group id
        
        if ($attributeGroupId) {
            $setup            = new Mage_Eav_Model_Entity_Setup('core_setup');
            $attribute_id     = $setup->getAttributeId('catalog_product', $attribute_code);
	    if (!$attribute_id) {
                $this->customHelper->reportError($this->customHelper->__("Attribute code %s is missing during attribute group %s import",$attribute_code, $attribute_group_id));
            } else {

	            $attribute_exists = $mapobj->isAttributeExistsInGroup($attribute_id, $attributeGroupId);
        	    if ($attribute_exists) {
                	$mapobj->updateSequenceOfAttribute($attributeGroupId, $attribute_id, $attribute_sort_order, $attribute_code, $attribute_group_id);
	            } else {
        	        $setup->addAttributeToGroup('catalog_product', $attributeSetId, $attributeGroupId, $attribute_id, $attribute_sort_order);
            	    }
	    }
        }
    }
    
    public function getAttributeGroupId($attribute_group_name, $attribute_set_name)
    {
        $entityTypeId         = $this->getEntityTypeId();
        $attributeSetId       = $this->getAttributeSetId($attribute_set_name);
        $installer            = $this->getInstaller(); //new Mage_Eav_Model_Entity_Setup(‘core_setup’);
        $attributeGroupObject = new Varien_Object($installer->getAttributeGroup($entityTypeId, $attributeSetId, $attribute_group_name));
        return $attributeGroupId = $attributeGroupObject->getAttributeGroupId();
    }
    
    public function getGroupIdUsingSetId($attribute_group_name, $attributeSetId)
    {
        $entityTypeId         = $this->getEntityTypeId();
        $installer            = $this->getInstaller(); //new Mage_Eav_Model_Entity_Setup(‘core_setup’);
        $attributeGroupObject = new Varien_Object($installer->getAttributeGroup($entityTypeId, $attributeSetId, $attribute_group_name));
        return $attributeGroupId = $attributeGroupObject->getAttributeGroupId();
    }
    
    public function showProducts()
    {
        $xmlObj              = $this->_xmlObj;
        $xmlData             = $xmlObj->getNode();
        $this->_product_list = $xmlData->products->product;
        if ($this->_product_list instanceof Varien_Simplexml_Element) {
            return $this->_product_list;
        } else {
            return false;
        }
    }
    
    public function importAllProducts($products, $Currfilepath = null, $Errfilepath = null)
    {
        $item = array();
        foreach ($products as $product) {
            $this->customHelper->reportInfo($this->customHelper->__("Start process for Product # %s", $product->id));
            $this->_current_row++;
            $this->importItem($product, $Currfilepath, $Errfilepath);
            $this->customHelper->reportInfo($this->customHelper->__("End process for Product # %s", $product->id));
        }
        $this->customHelper->reportSuccess($this->customHelper->__("Successfully created products %s", $this->_created_num));
        $this->customHelper->reportSuccess($this->customHelper->__("Successfully updated products %s", $this->_updated_num));
        $this->_created_num = 0;
        $this->_updated_num = 0;
    }
    
    /**
     * parses <categoryProducts> block , and returns <categoryProducts><categoryProduct> block
     */
    public function associatedProductsCategory()
    {
        $xmlObj  = $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if ($xmlData->categoryProducts->categoryProduct instanceof Varien_Simplexml_Element) {
            return $xmlData->categoryProducts->categoryProduct;
        } else {
            return false;
        }
    }
    
    public function associateProducts($association)
    {
        foreach ($association as $associate) {
            $parent    = (string) $associate->categoryId;
            $externall = $this->checkExternalId($parent);
            if ($externall) {
                if (count($externall) == 1) {
                    reset($externall);
                    $first_key = key($externall);
                    foreach ($associate->product as $product) {
                        $this->customHelper->reportInfo($this->customHelper->__("Start Process for category # %s and product %s association", $externall[$first_key], $product->id));
                        $this->associateProductToCategory($product, $first_key);
                        $this->customHelper->reportInfo($this->customHelper->__("End Process for category # %s and product %s association", $externall[$first_key], $product->id));
                    }
                } else {
                    foreach ($externall as $systemCatid => $v) {
                        foreach ($associate->product as $product) {
                            $this->customHelper->reportInfo($this->customHelper->__("Start Process for category # %s and product %s association", $v, $product->id));
                            $this->associateProductToCategory($product, $systemCatid);
                            $this->customHelper->reportInfo($this->customHelper->__("End Process for category # %s and product %s association", $v, $product->id));
                        }
                    }
                }
            } else {
                if (count($externall) == 0) {
                    $this->customHelper->reportError($this->customHelper->__('category id not found : %s', $parent));
                }
            }
        }
    }
    
    //Get product Association from XML
    public function associatedProductsProducts()
    {
        $xmlObj  = $this->_xmlObj;
        $xmlData = $xmlObj->getNode();
        if ($xmlData->productAssociations->association instanceof Varien_Simplexml_Element) {
            return $xmlData->productAssociations->association;
        } else {
            $this->customHelper->reportInfo($this->customHelper->__('Association block is empty.'));
        }
    }
    
    public function associatePdtPdt($association)
    {
        foreach ($association as $associate) {
            $this->customHelper->reportInfo($this->customHelper->__('Start Process for product association # %s', $associate->productIdFrom));
            $mainProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', (string) $associate->productIdFrom);
            if ($mainProduct) {
                $productId       = $mainProduct->getId();
                $relatedArray    = array();
                $upsellArray     = array();
                $crossArray      = array();
                $associatedArray = array();
                $bundleArray     = array();
				$preAssociatedArray = array();
				$disAssociateArray = array(); 
				$preRelatedArray    = array();
				$preUpsellArray     = array();
				$preCrossArray      =  array();
 
                 if ($mainProduct->getTypeId() == "configurable") {
                     $configurable = Mage::getModel('catalog/product_type_configurable')->setProduct($mainProduct);
                    $simpleCollection = $configurable->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();
                    foreach($simpleCollection as $simpleProduct){
                        $preAssociatedArray[] = $simpleProduct->getId();
                    }
                }
                
                $relatedCollection = $mainProduct->getRelatedLinkCollection();
                foreach($relatedCollection as $item){
                    $preRelatedArray[$item->getLinkedProductId()] = array(
                                    'position' => $item->getPosition()
                                );
                }
                
                $upsellCollection = $mainProduct->getUpSellLinkCollection();
                foreach($upsellCollection as $item){
                    $preUpsellArray[$item->getLinkedProductId()] = array(
                                    'position' => $item->getPosition()
                                );
                }
                
                $crossCollection = $mainProduct->getCrossSellLinkCollection();
                foreach($crossCollection as $item){
                    $preCrossArray[$item->getLinkedProductId()] = array(
                                    'position' => $item->getPosition()
                                );
                }
                foreach ($associate->associatedProduct as $association) {
                    if ($association instanceof Varien_Simplexml_Element) { // if associatedProduct is an object in form of <associatedProduct>
                        unset($prid);
                        $prid = Mage::getModel('catalog/product')->getIdBySku((string) $association->id); // get id of associated product
                        if ($prid && (string) $association->isActive == 'Y') {
                            $position = (string) $association->position ? (string) $association->position : 0;
                            if ((string) $association->assocType == 0) {
                                $preCrossArray[$prid] = array(
                                    'position' => $position
                                );
                            } elseif ((string) $association->assocType == 1) {
                                $preUpsellArray[$prid] = array(
                                    'position' => $position
                                );
                            } elseif ((string) $association->assocType == 2) {
                                $preRelatedArray[$prid] = array(
                                    'position' => $position
                                );
                            } elseif ((string) $association->assocType == 3) {
                                $preAssociatedArray[] = $prid;
                                $this->_hideVisibility($prid);
                            } elseif ((string) $association->assocType == 4) {
                                $bundleArray[]         = $prid;
                                $bundleQuantityArray[] = (int) $association->quantity;
                                $bundlePositionArray[] = (int) $position;
                            }
                        } elseif($prid && strtolower((string) $association->isActive) == 'n') {
                            if ((string) $association->assocType == 0) {
                                $crossArray[$prid] = array(
                                    'position' => $position
                                );
                            } elseif ((string) $association->assocType == 1) {
                                $upsellArray[$prid] = array(
                                    'position' => $position
                                );
                            } elseif ((string) $association->assocType == 2) {
                                $relatedArray[$prid] = array(
                                    'position' => $position
                                );
                            } elseif ((string) $association->assocType == 3) {
                                $disAssociateArray[] = $prid;
                                $this->_bothVisibility($prid);
                            }
                        }
                    }
                }
				
				if(is_array($preAssociatedArray) && count($preAssociatedArray) > 0){
                     $associatedArray = array_unique(array_diff($preAssociatedArray, $disAssociateArray));
                }
                
                foreach($preRelatedArray as $preRelatedkey => $relatedPro){
                    if (array_key_exists($preRelatedkey,$relatedArray)){
                        unset($preRelatedArray[$preRelatedkey]);
                    }
                }
                
                foreach($preUpsellArray as $preUpsellkey => $upsellPro){
                    if (array_key_exists($preUpsellkey,$upsellArray)){
                        unset($preUpsellArray[$preUpsellkey]);
                    }
                }
                
                foreach($preCrossArray as $preCrosskey => $crossPro){
                    if (array_key_exists($preCrosskey,$crossArray)){
                        unset($preCrossArray[$preCrosskey]);
                    }
                }
                
                $mainProduct->setCrossSellLinkData($preCrossArray);
                $mainProduct->setUpSellLinkData($preUpsellArray);
                $mainProduct->setRelatedLinkData($preRelatedArray);
                $mainProduct->save();
				
				if (count($bundleArray) > 0) {
                    $proobj        = Mage::getModel('catalog/product');
                    $productbundle = Mage::getModel('catalog/product')->setStoreId(0);
                    if ($productId) {
                        $productbundle->load($productId);
                    }
                    Mage::register('product', $productbundle);
                    Mage::register('current_product', $productbundle);
                    $bundleOptions    = array();
                    $bundleSelections = array();
                    $i                = 0;
                    foreach ($bundleArray as $proid) {
                        $_product_obj         = $proobj->load($proid);
                        $bundleOptions[$i]    = array(
                            'title' => $_product_obj->getName(), //option title
                            'option_id' => '',
                            'delete' => '',
                            'type' => 'select',
                            'required' => '1',
                            'position' => $bundlePositionArray[$i]
                        );
                        $bundleSelections[$i] = array(
                            '0' => array(
                                'product_id' => $proid, //if of a product in selection
                                'delete' => '',
                                'selection_price_value' => '10',
                                'selection_price_type' => 0,
                                'selection_qty' => $bundleQuantityArray[$i],
                                'selection_can_change_qty' => 0,
                                'position' => 0,
                                'is_default' => 1
                            )
                        );
                        $i++;
                    }
                    try {
                        $productbundle->setCanSaveConfigurableAttributes(false);
                        $productbundle->setCanSaveCustomOptions(true);
                        $productbundle->setBundleOptionsData($bundleOptions);
                        $productbundle->setBundleSelectionsData($bundleSelections);
                        $productbundle->setCanSaveCustomOptions(true);
                        $productbundle->setCanSaveBundleSelections(true);
                        $productbundle->save();
                    }
                    catch (Exception $e) {
                        $this->customHelper->reportError($e->getMessage());
                        $this->customHelper->sendLogEmail($this->logPath);
                    }
                }
                
                if (count($associatedArray) > 0) {
                    try {
                        Mage::getResourceModel('catalog/product_type_configurable')->saveProducts($mainProduct, $associatedArray);
                    }
                    catch (Exception $e) {
                        $this->customHelper->reportError($this->customHelper->__('ERROR: product association failed for %s', $associate->productIdFrom));
                    }
                }
                
                unset($crossArray);
                unset($upsellArray);
                unset($relatedArray);
                unset($associatedArray);
            } else {
                $this->customHelper->reportError($this->customHelper->__('INFO: product not found for association %s', $associate->productIdFrom));
            }
            $this->customHelper->reportInfo($this->customHelper->__('End Process for product association # %s', $associate->productIdFrom));
        }
    }
    
    public function parseAndUpdateCategoryRelation()
    {
        $xmlObj                 = $this->_xmlObj;
        $xmlData                = $xmlObj->getNode();
        $this->_cat_relation    = $xmlData->categoryRelations->categoryRelation;
        $categoryRelationStatus = 1;
        if (count($this->_cat_relation) > 0) {
            foreach ($this->_cat_relation as $catRelation) {
                $parent    = (string) $catRelation->parentId;
                $externall = $this->checkExternalId($parent);
                if ($externall) { //check if parent id exists.
                    if (count($externall) == 1) {
                        reset($externall); //to take 1st key of array
                        $first_key = key($externall);
                        foreach ($catRelation->subCategory as $sub) {
                            $this->updateCategoryRelation($sub, $first_key, $parent);
                        }
                    } else {
                        foreach ($externall as $systemCatid => $v) {
                            foreach ($catRelation->subCategory as $sub) {
                                $this->updateCategoryRelation($sub, $systemCatid, $parent);
                            }
                        }
                    }
                } else {
                    $categoryRelationStatus = 2;
                    $this->customHelper->reportError($this->customHelper->__('Parent category not found : ') . $parent);
                }
            }
        } else {
            $categoryRelationStatus = 0;
        }
        return $categoryRelationStatus;
    }
    
    //duplicating categoryid 
    protected function duplicateCategory($categoryId, $parentId, $status)
    {
        $default_root_category = $this->_default_category_id;
        $parent_id             = ($parentId) ? $parentId : $default_root_category;
        $isActive              = ($status == 'Y') ? 1 : 0;
        $category              = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($categoryId); //load category to duplicate
        $duplicate_category    = Mage::getModel('catalog/category')->setStoreId($this->_store_id);
        $parent_category       = $this->_initCategory($parentId, $this->_store_id);
        if (!$parent_category->getId()) {
            exit;
        }
        $duplicate_category->addData(array(
            'path' => implode('/', $parent_category->getPathIds())
        ));
        $duplicate_category->setParentId($parent_category->getId());
        $duplicate_category->setAttributeSetId($duplicate_category->getDefaultAttributeSetId());
        $duplicate_category->setData('name', $category->getName());
        $duplicate_category->setData('include_in_menu', 1);
        $duplicate_category->setData('meta_title', $category->getmetaTitle());
        $duplicate_category->setData('meta_keywords', $category->getmetaKeywords());
        $duplicate_category->setData('meta_description', $category->getmetaDescription());
        $duplicate_category->setData('description', $category->getdescription());
        $duplicate_category->setData('available_sort_by', 'position');
        $duplicate_category->setData('default_sort_by', 'position');
        $duplicate_category->setData('is_active', $isActive);
        $duplicate_category->setData('is_anchor', 1);
        $duplicate_category->setData('external_id', $category->getexternalId());
        $duplicate_category->setData('external_cat_image', $category->getexternalCatImage());
        try {
            $validate = $duplicate_category->validate();
            if ($validate !== true) {
                foreach ($validate as $code => $error) {
                    if ($error === true) {
                        $this->customHelper->reportError($this->customHelper->__('Attribute "%s" is required', $code));
                        $this->customHelper->sendLogEmail($this->logPath);
                    } else {
                        $this->customHelper->reportError($error);
                        $this->customHelper->sendLogEmail($this->logPath);
                        Mage::throwException($error);
                    }
                }
            }
            $duplicate_category->save();
            return $duplicate_category->getId();
        }
        catch (Exception $e) {
            $this->customHelper->reportError($e->getMessage());
            $this->customHelper->sendLogEmail($this->logPath);
        }
        return false;
    }
    
    public function getTreeCategories($category_id, $p_id, $isActive, $isChild) //$parentId, $isChild
    {
        $duplicatedCategoryId = $this->duplicateCategory($category_id, $p_id, $isActive);
        $mapObj               = Mage::getModel('customimport/customimport');
        $sub_category         = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($duplicatedCategoryId);
        $parent_category      = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($p_id);
        $ext_subid            = $sub_category->getExternalId();
        $parent_external_id   = $parent_category->getExternalId();
        $mapObj->updateCategoryMappingInfo($ext_subid, $duplicatedCategoryId, $parent_external_id, $p_id);
        $allCats = Mage::getModel('catalog/category')->getCollection()->addAttributeToSelect('*')->addAttributeToFilter('parent_id', array(
            'eq' => $category_id
        ));
        foreach ($allCats as $category) {
            $subcats    = $category->getChildren();
            $isActive   = 'N';
            $status_cat = $category->getData('is_active');
            if ($status_cat == 1) {
                $isActive = 'Y';
            }
            if ($subcats != '') {
                $this->getTreeCategories($category->getId(), $duplicatedCategoryId, $isActive, true);
            } else {
                $duplicatedSubcategoryId = $this->duplicateCategory($category->getId(), $duplicatedCategoryId, $isActive); // duplicated category id is parent for current subcategory
                if ($duplicatedSubcategoryId) {
                    $sub_category       = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($duplicatedSubcategoryId);
                    $parent_category    = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($duplicatedCategoryId);
                    $ext_subid          = $sub_category->getExternalId();
                    $parent_external_id = $parent_category->getExternalId();
                    $mapObj->updateCategoryMappingInfo($ext_subid, $duplicatedSubcategoryId, $parent_external_id, $duplicatedCategoryId);
                } else {
                    $this->customHelper->reportError($this->customHelper->__('There was an error while duplicating category'));
                }
            }
        }
    }
    
    public function updateCategoryRelation($subcat, $p_id, $parent_external_id)
    {
        $ext_subid   = (string) $subcat->id;
        $actualSubId = $this->checkExternalId($ext_subid);
        $mapObj      = Mage::getModel('customimport/customimport');
        
        if ($actualSubId) {
            if (count($actualSubId) == 1) {
                reset($actualSubId); //to take 1st key of array
                $subcat_id   = key($actualSubId);
                $category_id = $mapObj->isSubcategoryExists($ext_subid, $p_id); // external subcat id , parent magento id
                
                if ($category_id) {
                    $category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($subcat_id);
                    $isActive = ((string) $subcat->isActive == 'Y') ? 1 : 0;
                    $category->setData('is_active', $isActive);
                    $category->save();
                } else {
                    $category_id = $mapObj->isCategoryExists($ext_subid);
                    if ($category_id) {
                        $isActive = ((string) $subcat->isActive);
                        $status   = $this->getTreeCategories($category_id, $p_id, $isActive, false);
                    } else {
                        // category is not under any other parent , move
                        $category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($subcat_id);
                        $category->move($p_id, 0);
                        $isActive = ((string) $subcat->isActive == 'Y') ? 1 : 0;
                        $category->setData('is_active', $isActive);
                        $category->save();
                        $mapObj->updateParent($p_id, $subcat_id);
                        $mapObj->updateCategoryMappingInfo($ext_subid, $subcat_id, $parent_external_id, $p_id);
                    }
                }
            } else {
                $category_id = $mapObj->isSubcategoryExists($ext_subid, $p_id);
                if ($category_id) {
                    //echo 'subcat present in this cat '.$ext_subid;
                    $category = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($category_id);
                    $isActive = ((string) $subcat->isActive == 'Y') ? 1 : 0;
                    $category->setData('is_active', $isActive);
                    $category->save();
                } elseif ($category_id = $mapObj->isCategoryExists($ext_subid)) {
                    $isActive = ((string) $subcat->isActive);
                    $status   = $this->getTreeCategories($category_id, $p_id, $isActive, false);
                } else {
                    // category is not under any other parent , move                   
                    $this->customHelper->reportInfo($this->customHelper->__('block will not execute as category is not under any other parent'));
                }
            }
        } else {
            if (count($actualSubId) == 0) {
                $this->customHelper->reportError($this->customHelper->__('There was no subcategory id found %s ', $ext_subid));
            }
        }
    }
    
    public function importItem(&$item, $Currfilepath = null, $Errfilepath = null)
    {
        $missingInfoRow = $this->_current_row - 1;
        if (!isset($item->id) || trim($item->id) == '') {
            $this->customHelper->reportError($this->customHelper->__('There was no sku found for product  %s ', $this->_current_row));
            return false;
        }
        
        if (($item->attributeSetId) == '') {
            $item->attributeSetId = 'Default';
        }
        
        if (!isset($item->type) || trim($item->type) == '') {
            $this->customHelper->reportError($this->customHelper->__('There was no product type found for product  %s ', $item->id));
            return false;
        }
        $itemids = $this->getItemIds($item, $Currfilepath, $Errfilepath);
        if ($itemids) {
            $pid  = $itemids["pid"];
            $asid = $itemids["asid"];
            if (!isset($pid)) {
                if (!isset($asid)) {
                    $this->customHelper->reportError($this->customHelper->__('Cannot create product sku  %s ', $item->id));
                    return false;
                }
                if ((string) $item->type == 'configurable') {
                    $this->createConfigurableProduct($item, $asid); //create con product
                } elseif ((string) $item->type == 'simple') {
                    $this->createProduct($item, $asid); //create simple product
                } elseif ((string) $item->type == 'bundle') {
                    $this->createBundleProduct($item, $asid); //create bundle product
                } else {
                    $this->customHelper->reportError($this->customHelper->__('Import function does not support product type of record: %s ', $item->id));
                }
                $this->_curitemids["pid"] = $pid;
                $isnew                    = true;
            } else {
                try {
                    if ((string) $item->type == 'configurable') {
                        $this->updateConfigurableProduct($item, $pid); //create con product
                    } elseif ((string) $item->type == 'simple') {
                        $this->updateProduct($item, $pid); //create simple product
                    } elseif ((string) $item->type == 'bundle') {
                        $this->updateBundleProduct($item, $pid); //create simple product
                    }
                } catch (Exception $e) {
                    $this->customHelper->reportError($this->customHelper->__('Product update failed for # %s', $item->id));
                }
            }
        } else {
            $this->customHelper->reportInfo($this->customHelper->__('Product import skipped due to some error for # %s ', $item->id));
        }
    }
    
    public function updateConfigurableProduct(&$item, $pid)
    {
        $p_status   = ((string) $item->isActive == 'Y') ? 1 : 2;
        $p_taxclass = ((string) $item->isTaxable == 'Y') ? 2 : 0;
        $SKU        = (string) $item->id;
        $product    = Mage::getModel('catalog/product')->loadByAttribute('sku', $SKU);
        
        if ($product) {
            $product->setData('name', (string) $item->name);
            $product->setPrice((real) $item->price);
            
            $splAmt = (array) $item->specialPrice->amount;
            if (isset($item->specialPrice->amount) && $item->specialPrice->amount != NULL) {
                if (!empty($splAmt))
                    $product->setSpecialPrice((real) $item->specialPrice->amount); //special price in form 11.22
                else
                    $product->setSpecialPrice("");
            }
            
            $fromDate = (array) $item->specialPrice->fromDateTime;
            if (isset($item->specialPrice->fromDateTime) && $item->specialPrice->fromDateTime != NULL) {
                if (!empty($fromDate))
                    $product->setSpecialFromDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->fromDateTime)); //special price from (MM-DD-YYYY)
                else
                    $product->setSpecialFromDate("");
            }
            
            $toDate = (array) $item->specialPrice->toDateTime;
            if (isset($item->specialPrice->toDateTime) && $item->specialPrice->toDateTime != NULL) {
                if (!empty($toDate))
                    $product->setSpecialToDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->toDateTime)); //special price to (MM-DD-YYYY)
                else
                    $product->setSpecialToDate("");
            }
            
            $product->setWeight((real) $item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);    
            $product->setDescription((string) $item->longDescription);
            $product->setShortDescription((string) $item->shortDescription);
            $product->setMetaTitle((string) $item->pageTitle);
            $product->setMetaKeyword((string) $item->metaKeywords);
            $product->setMetaDescription((string) $item->metaDescription);
            $product->setExternalImage((string) $item->originalImageUrl);
            $product->setExternalSmallImage((string) $item->largeImageUrl);
            $product->setExternalThumbnail((string) $item->smallImageUrl);
            $attributeValues      = $item->attributeValues;
            $attributeOcuurance   = array(); //stores no. of occurance for all attributes
            $configAttributeValue = array(); // will use to take value of attributes that ocuures once
            $i                    = 1;
            foreach ($attributeValues->attribute as $attr) {
                if (array_key_exists((string) $attr->id, $attributeOcuurance)) {
                    $attributeOcuurance[(string) $attr->id] = (int) $attributeOcuurance[(string) $attr->id] + 1;
                } else {
                    $attributeOcuurance[(string) $attr->id]   = $i;
                    $configAttributeValue[(string) $attr->id][] = (string) $attr->valueDefId;
                    $configAttributeValue[(string) $attr->id][] = (string) $attr->value;
                }
            }
            $config_attribute_array = array(); //attributes with single occurance
            foreach ($attributeOcuurance as $key => $val) {
                if ($val == 1) {
                    $config_attribute_array[] = $key;
                }
            }

            foreach ($config_attribute_array as $attr) {
                $model       = Mage::getModel('catalog/resource_eav_attribute');
                $loadedattr  = $model->loadByCode('catalog_product', $attr);
                $attr_id     = $loadedattr->getAttributeId(); // attribute id of magento
                $attr_type   = $loadedattr->getFrontendInput();
                if ($attr_type == 'select' || $attr_type == 'multiselect') {
                    $external_id = $configAttributeValue[$attr][0]; // valueDefId from XML for an attribute
                    $mapObj    = Mage::getModel('customimport/customimport');
                    $option_id = $mapObj->isOptionExistsInAttribute($external_id, $attr_id);
                    if ($option_id) {
                        $product->setData($attr, $option_id);
                    } else {
		        $this->customHelper->reportError($this->customHelper->__('Attribute %s has an undefined option value %s. Hence skipping product # %s', $attr, $external_id, $item->id));
                        return;
		    }
                } elseif ($attr_type == 'text' || $attr_type == 'textarea') {
                    $attr_value = $configAttributeValue[$attr][1];
                    $product->setData($attr, $attr_value);
                } elseif ($attr_type == 'boolean') {
                    $optVal = Mage::getSingleton('customimport/customimport')->getOptVal($configAttributeValue[$attr][0]);
                    if (strtolower($optVal->getValue()) == 'y' || strtolower($optVal->getValue()) == 'yes') {
                        $attOptVal = 1;
                    } else {
                        $attOptVal = 0;
                    }
                    $product->setData($attr, $attOptVal);
                }
            }
            
            try {
                Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
                $product->save();
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->assignProduct($product);
                $stockItem->setData('stock_id', (int) 1);
                $stockItem->setData('use_config_manage_stock', (int) 1);
                $stockItem->setData('min_qty', (int) 0);
                $stockItem->setData('is_decimal_divided', (int) 0);
                $stockItem->setData('qty', (int) 0);
                $stockItem->setData('use_config_min_qty', 1);
                $stockItem->setData('use_config_backorders', 1);
                $stockItem->setData('min_sale_qty', 1);
                $stockItem->setData('use_config_min_sale_qty', 1);
                $stockItem->setData('use_config_max_sale_qty', 1);
                $stockItem->setData('is_in_stock', 1);
                $stockItem->setData('use_config_notify_stock_qty', 1);
                $inventory  = $item->inventory;
                $manageItem = (string) $inventory->manageStock;
                if ($manageItem == 'N' || $manageItem == 'n') {
                    $stockItem->setData('use_config_manage_stock', 0);
                    $stockItem->setData('manage_stock', 0);
                } else {
                    $stockItem->setData('use_config_manage_stock', 1);
                    $stockItem->setData('manage_stock', 1);
                }
                
                $stockItem->save();
                $stockStatus = Mage::getModel('cataloginventory/stock_status');
                $stockStatus->assignProduct($product);
                $stockStatus->saveProductStatus($product->getId(), 1);
            }
            catch (Exception $e) {
                $this->customHelper->reportError($this->customHelper->__('Unable to update configurable product due to some error'));
                $this->customHelper->reportError($e->getMessage());
                $this->customHelper->sendLogEmail($this->logPath);
            }
            $this->_updated_num++;
            unset($product);
            $productId = (isset($productId) ? $productId : null);
            
            return $productId;
        } else {
            return false;
        }
    }
    
    public function createConfigurableProduct(&$item, $asid)
    {
        $attributeSetModel = Mage::getModel('eav/entity_attribute_set');
        $attributeSetModel->load($asid);
        $attributeSetModel = $attributeSetModel->getData();
        if (count($attributeSetModel) > 0) {
            $p_status   = ((string) $item->isActive == 'Y') ? 1 : 2;
            $p_taxclass = ((string) $item->isTaxable == 'Y') ? 2 : 0;
            $product = new Mage_Catalog_Model_Product();
            $product->setTypeId('configurable');
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
            //New and created data code start
            $format                = 'Y-m-d H:i:s';
            $catalogNewproductDays = Mage::getStoreConfig('catalog/newproduct/days', Mage::app()->getStore());
            if (!empty($catalogNewproductDays) && $catalogNewproductDays >= 0) {
                $currenDateTime = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
                $new_from_date = date($format, strtotime('1 days' . $currenDateTime));
                $new_to_date = date($format, strtotime($catalogNewproductDays . ' days' . $new_from_date));
                $product->setNewsFromDate($new_from_date);
                $product->setNewsToDate($new_to_date);
            }
            if ($product->getCreatedTime == NULL || $product->getUpdateTime() == NULL) {
                $product->setCreatedTime($currenDateTime)->setUpdateTime($currenDateTime);
            }
            //New and created data code end
            $product->setSku((string) $item->id); //Product custom id
            $product->setWebsiteIds(array(
                Mage::app()->getStore(true)->getWebsite()->getId()
            ));
            $product->setStoreIDs(array(
                $this->_store_id
            )); // Default store id .
            $product->setAttributeSetId($asid);
            
            $product->setData('name', (string) $item->name);
            $product->setPrice((real) $item->price);
            $splAmt = (array) $item->specialPrice->amount;
            if (isset($item->specialPrice->amount) && $item->specialPrice->amount != NULL) {
                if (!empty($splAmt))
                    $product->setSpecialPrice((real) $item->specialPrice->amount); //special price in form 11.22
            }
            
            $fromDate = (array) $item->specialPrice->fromDateTime;
            if (isset($item->specialPrice->fromDateTime) && $item->specialPrice->fromDateTime != NULL) {
                if (!empty($fromDate))
                    $product->setSpecialFromDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->fromDateTime)); //special price from (MM-DD-YYYY)
            }
            
            $toDate = (array) $item->specialPrice->toDateTime;
            if (isset($item->specialPrice->toDateTime) && $item->specialPrice->toDateTime != NULL) {
                if (!empty($toDate))
                    $product->setSpecialToDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->toDateTime)); //special price to (MM-DD-YYYY)
            }
            $product->setWeight((real) $item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);
            $product->setDescription((string) $item->longDescription);
            $product->setShortDescription((string) $item->shortDescription);
            $product->setMetaTitle((string) $item->pageTitle);
            $product->setMetaKeyword((string) $item->metaKeywords);
            $product->setMetaDescription((string) $item->metaDescription);
            $product->setExternalImage((string) $item->originalImageUrl);
            $product->setExternalSmallImage((string) $item->largeImageUrl);
            $product->setExternalThumbnail((string) $item->smallImageUrl);
            $attributeValues      = $item->attributeValues;
            $attributeOcuurance   = array(); //stores no. of occurance for all attributes
            $configAttributeValue = array(); // will use to take value of attributes that ocuures once
            $i                    = 1;
            
            foreach ($attributeValues->attribute as $attr) {
                if (array_key_exists((string) $attr->id, $attributeOcuurance)) {
                    $attributeOcuurance[(string) $attr->id] = (int) $attributeOcuurance[(string) $attr->id] + 1;
                } else {
                    $attributeOcuurance[(string) $attr->id]   = $i;
                    $configAttributeValue[(string) $attr->id][] = (string) $attr->valueDefId;
		    $configAttributeValue[(String) $attr->id][] = (String) $attr->value;
                }
            }
            $superattribute_array   = array(); // attributes with multiple occurances
            $config_attribute_array = array(); //attributes with single occurance
            foreach ($attributeOcuurance as $key => $val) {
                if ($val > 1) {
                    $superattribute_array[] = $key;
                } else {
                    $config_attribute_array[] = $key;
                }
            }
            
            $attributes_array = array();
            if (count($superattribute_array) > 0) {
                $super_attribute_created = $this->makeAttributeConfigurable($superattribute_array);
                if ($super_attribute_created) {
                    foreach ($superattribute_array as $attr) {
                        $attributes_array[] = $attr; // contains attribute codes
                    }
                    $ProductAttributeIds = array(); // array stores only super attribute id's
                    $attribute_detail    = array(); // stores super attribute's detail
                    $attrnum             = 0;
                    foreach ($attributes_array as $attribute_code) {
                        $model                 = Mage::getModel('catalog/resource_eav_attribute');
                        $attr                  = $model->loadByCode('catalog_product', $attribute_code);
                        $attr_id               = $attr->getAttributeId();
                        if (!$attr_id) {
                            $this->customHelper->reportError($this->customHelper->__('Attribute %s is not available in magento. Hence skipping product # %s', $attribute_code, $item->id));
                            return;
                        }
                        $ProductAttributeIds[] = $attr_id;
                        $attribute_label       = $attr->getFrontendLabel();
                        $attr_detail           = array(
                            'id' => NULL,
                            'label' => "$attribute_label",
                            'attribute_id' => $attr_id,
                            'attribute_code' => "$attribute_code",
                            'frontend_label' => "$attribute_label",
                            'html_id' => "config_super_product__attribute_$attrnum"
                        );
                        $attribute_detail[]    = $attr_detail;
                        $attrnum++;
                    }
                    $product->getTypeInstance()->setUsedProductAttributeIds($ProductAttributeIds);
                    $product->setConfigurableAttributesData($attribute_detail);
                    $product->setCanSaveConfigurableAttributes(1);
                    foreach ($config_attribute_array as $attr) {
                        $model       = Mage::getModel('catalog/resource_eav_attribute');
                        $loadedattr  = $model->loadByCode('catalog_product', $attr);
                        $attr_id     = $loadedattr->getAttributeId(); // attribute id of magento
                        if (!$attr_id) {
                            $this->customHelper->reportError($this->customHelper->__('Attribute %s is not available in magento. Hence skipping product # %s', $attr, $item->id));
                            return;
                        } else {
                            $attr_type   = $loadedattr->getFrontendInput();
                            if ($attr_type == 'select' || $attr_type == 'multiselect') {
                                $external_id = $configAttributeValue[$attr][0]; // valueDefId from XML for an attribute
                                $mapObj    = Mage::getModel('customimport/customimport');
                                $option_id = $mapObj->isOptionExistsInAttribute($external_id, $attr_id);
                                if ($option_id) {
                                    $product->setData($attr, $option_id);
                                } else {
                                    $this->customHelper->reportError($this->customHelper->__('Attribute %s has an undefined option value %s. Hence skipping product # %s', $attr, $external_id, $item->id));
                                    return;
                                }
                            } elseif ($attr_type == 'text' || $attr_type == 'textarea') {
                                $attr_value = $configAttributeValue[$attr][1];
                                $product->setData($attr, $attr_value);
                            } elseif ($attr_type == 'boolean') {
                                $optVal = Mage::getSingleton('customimport/customimport')->getOptVal($configAttributeValue[$attr][0]);
                                if (strtolower($optVal->getValue()) == 'y' || strtolower($optVal->getValue()) == 'yes') {
                                    $attOptVal = 1;
                                } else {
                                    $attOptVal = 0;
                                }
                                $product->setData($attr, $attOptVal);
                            }
                        }
                    }
                    try {
                        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
                        $product->save();
                        $stockItem = Mage::getModel('cataloginventory/stock_item');
                        $stockItem->assignProduct($product);
                        $stockItem->setData('stock_id', (int) 1);        
                        $stockItem->setData('use_config_manage_stock', (int) 1);
                        $stockItem->setData('min_qty', (int) 0);
                        $stockItem->setData('is_decimal_divided', (int) 0);
                        $stockItem->setData('qty', (int) 0);
                        $stockItem->setData('use_config_min_qty', 1);
                        $stockItem->setData('use_config_backorders', 1);
                        $stockItem->setData('min_sale_qty', 1);
                        $stockItem->setData('use_config_min_sale_qty', 1);
                        $stockItem->setData('use_config_max_sale_qty', 1);
                        $stockItem->setData('is_in_stock', 1);
                        $stockItem->setData('use_config_notify_stock_qty', 1);
                        // Manage Stock
                        $inventory  = $item->inventory;
                        $manageItem = (string) strtoupper($inventory->manageStock);
                        if ($manageItem == 'N') {
                            
                            $stockItem->setData('use_config_manage_stock', 0);
                            $stockItem->setData('manage_stock', 0);
                        }
                        $stockItem->save();
                        $stockStatus = Mage::getModel('cataloginventory/stock_status');
                        $stockStatus->assignProduct($product);
                        $stockStatus->saveProductStatus($product->getId(), 1);
                        $this->_created_num++;
                    } catch (Exception $e) {
                        $this->customHelper->writeCustomLog('<span style="color:red;">' . $e->getMessage() . '</span>', $this->logPath);
                        $this->customHelper->sendLogEmail($this->logPath);
                    }
                } else {
                    $this->customHelper->reportError($this->customHelper->__('Could not create super attribute for configurable product from %s. Hence skipped product # %s', array_values($superattribute_array), $item->id));
                }
            } else {
                $this->customHelper->reportError($this->customHelper->__('No super attributes defined for configurable product. Hence skipped product # %s', $item->id));
            }
        } else {
            $this->customHelper->reportError($this->customHelper->__('Attribute set ID # %s is missing. Hence skipped product # %s', $asid, $item->id));
        }
    }
    
    public function createProduct(&$item, $asid)
    {
        $logFileName       = Mage::getSingleton('core/session')->getEmailID();
        $attributeSetModel = Mage::getModel('eav/entity_attribute_set');
        $attributeSetModel->load($asid);
        if (count($attributeSetModel->getData()) > 0) {
            $p_status   = ((string) $item->isActive == 'Y') ? 1 : 2;
            $p_taxclass = ((string) $item->isTaxable == 'Y') ? 2 : 0;        
            $product = new Mage_Catalog_Model_Product();
            $product->setTypeId('simple');
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
            //New and created data code start
            $format                = 'Y-m-d H:i:s';
            $catalogNewproductDays = Mage::getStoreConfig('catalog/newproduct/days', Mage::app()->getStore());
            if (!empty($catalogNewproductDays) && $catalogNewproductDays >= 0) {
                $currenDateTime = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
                $new_from_date = date($format, strtotime('1 days' . $currenDateTime));
                $new_to_date = date($format, strtotime($catalogNewproductDays . ' days' . $new_from_date));
                $product->setNewsFromDate($new_from_date);
                $product->setNewsToDate($new_to_date);
            }
            if ($product->getCreatedTime == NULL || $product->getUpdateTime() == NULL) {
                $product->setCreatedTime($currenDateTime)->setUpdateTime($currenDateTime);
            }
            //New and created data code end
            $product->setSku((string) $item->id); //Product custom id
            $product->setWebsiteIds(array(
                Mage::app()->getStore(true)->getWebsite()->getId()
            )); //Default website (main website) ?? To Do : make it dynamic
            $product->setStoreIDs(array(
                $this->_store_id
            )); // Default store id .
            $inventory  = $item->inventory;
            $manageItem = (string) $inventory->manageStock;
            $manageItem = strtoupper($manageItem);
            if ($manageItem == 'Y' && (strtoupper($inventory->allowBackorders) == 'Y')) {
                $product->setStockData(array(
                    'is_in_stock' => 1,
                    'qty' => $inventory->atp,
                    'manage_stock' => 1,
                    'use_config_backorders' => 0,
                    'backorders' => 1
                ));
            } elseif ($manageItem == 'Y') {
                $product->setStockData(array(
                    'is_in_stock' => 1,
                    'qty' => $inventory->atp,
                    'use_config_backorders' => 0,
                    'manage_stock' => 1
                ));
            } elseif ($manageItem == 'N') {
                $product->setStockData(array(
                    'use_config_backorders' => 0,
                    'manage_stock' => 0
                ));
            }
            
            $product->setAttributeSetId($asid);
            $product->setData('name', (string) $item->name);
            $product->setPrice((real) $item->price);
            $splAmt = (array) $item->specialPrice->amount;
            if (isset($item->specialPrice->amount) && $item->specialPrice->amount != NULL) {
                if (!empty($splAmt))
                    $product->setSpecialPrice((real) $item->specialPrice->amount); //special price in form 11.22
            }
            
            $fromDate = (array) $item->specialPrice->fromDateTime;
            if (isset($item->specialPrice->fromDateTime) && $item->specialPrice->fromDateTime != NULL) {
                if (!empty($fromDate))
                    $product->setSpecialFromDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->fromDateTime)); //special price from (MM-DD-YYYY)
            }
            
            $toDate = (array) $item->specialPrice->toDateTime;
            if (isset($item->specialPrice->toDateTime) && $item->specialPrice->toDateTime != NULL) {
                if (!empty($toDate))
                    $product->setSpecialToDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->toDateTime)); //special price to (MM-DD-YYYY)
            }
            $product->setWeight((real) $item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);
            $product->setDescription((string) $item->longDescription);
            $product->setShortDescription((string) $item->shortDescription);
            $product->setMetaTitle((string) $item->pageTitle);
            $product->setMetaKeyword((string) $item->metaKeywords);
            $product->setMetaDescription((string) $item->metaDescription);
            $product->setExternalImage((string) $item->originalImageUrl);
            $product->setExternalSmallImage((string) $item->largeImageUrl);
            $product->setExternalThumbnail((string) $item->smallImageUrl);
            $attributeValues      = $item->attributeValues;
            $attributeOcuurance   = array(); //stores no. of occurance for all attributes
            $configAttributeValue = array(); // will use to take value of attributes that ocuures once
            $multiple_values      = array(); // stores an array of available values
            $i                    = 1;
            $model      = Mage::getModel('catalog/resource_eav_attribute');
            foreach ($attributeValues->attribute as $attr) {
                $loadedattr = $model->loadByCode('catalog_product', (string) $attr->id);
                $attr_type = $loadedattr->getFrontendInput();
                if (array_key_exists((string) $attr->id, $attributeOcuurance)) {
                    $multiple_values[(string) $attr->id][]  = (string) $attr->valueDefId;
                    $attributeOcuurance[(string) $attr->id] = (int) $attributeOcuurance[(string) $attr->id] + 1;
                    if($attr_type == 'text' || $attr_type == 'textarea'){
                        $multiple_values[(string) $attr->id][]  = (string) $attr->value;
                    }
                } else {
                    $multiple_values[(string) $attr->id][]  = (string) $attr->valueDefId;
                    $attributeOcuurance[(string) $attr->id] = $i;
                    if($attr_type == 'text' || $attr_type == 'textarea'){
                        $multiple_values[(string) $attr->id][]  = (string) $attr->value;
                    }
                }
            }
            foreach ($multiple_values as $attribute_code => $attribute_values) {
                $loadedattr = $model->loadByCode('catalog_product', $attribute_code);
                $attr_id    = $loadedattr->getAttributeId(); // attribute id of magento
                if (!$attr_id) {
                    $this->customHelper->reportError($this->customHelper->__('Attribute %s is not available in magento. Hence skipping product # %s', $attribute_code, $item->id));
                    return;
                } else {
                    $attr_type = $loadedattr->getFrontendInput();
                    if ($attr_type == 'select' && count($attribute_values) == 1) {
                        $mapObj    = Mage::getModel('customimport/customimport');
                        $option_id = $mapObj->isOptionExistsInAttribute($attribute_values[0], $attr_id);
                        if ($option_id) {
                            $product->setData($attribute_code, $option_id);
                        }  else {
                            $this->customHelper->reportError($this->customHelper->__('Attribute %s has an undefined option value %s. Hence skipping product # %s', $attribute_code, $attribute_values[0], $item->id));
                            return;
                        }
                    } elseif ($attr_type == 'select' && count($attribute_values) > 1) {
                        //multiple values for attribute which is not multiselect
                        $this->customHelper->reportError($this->customHelper->__('Attribute %s can not have multiple values. Hence skipping product # %s', $attribute_code, $item->id));
                        return;
                    } elseif ($attr_type == 'multiselect') {
                        $multivalues = array();
                        foreach ($attribute_values as $value) {
                            $mapObj    = Mage::getModel('customimport/customimport');
                            $option_id = $mapObj->isOptionExistsInAttribute($value, $attr_id);
                            if ($option_id) {
                                $multivalues[] = $option_id;
                            } else {
                                $this->customHelper->reportError($this->customHelper->__('Attribute %s has an undefined option value %s. Hence skipping product id %s', $attribute_code, $value, $item->id));
                                return;
                            }
                        }
                        $product->addData(array(
                            $attribute_code => $multivalues
                        ));
                    } elseif ($attr_type == 'text' || $attr_type == 'textarea') {
                        $product->setData($attribute_code, $attribute_values[1]);
                    } elseif ($attr_type == 'boolean') {
                        $optVal = Mage::getSingleton('customimport/customimport')->getOptVal($attribute_values[0]);
                        if (strtolower($optVal->getValue()) == 'y' || strtolower($optVal->getValue()) == 'yes') {
                            $attOptVal = 1;
                        } else {
                            $attOptVal = 0;
                        }
                        $product->setData($attribute_code, $attOptVal);
                    }
                }
            }
            try {
                Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
                $productId = $product->save()->getId();
                if ($manageItem == 'N' || $manageItem == 'n') {
                    $product->setStockData(array(
                        'use_config_backorders' => 0,
                        'is_in_stock' => 1,
                        'manage_stock' => 0
                    ));
                    //code for instock while update product
                    $stockItem = Mage::getModel('cataloginventory/stock_item');
                    $stockItem->assignProduct($product);
                    $stockItem->setData('use_config_manage_stock', 0);
                    $stockItem->setData('manage_stock', 0);
                    $stockItem->save();
                    $stockStatus = Mage::getModel('cataloginventory/stock_status');
                    $stockStatus->assignProduct($product);
                    $stockStatus->saveProductStatus($product->getId(), 1);
                }
                if ($productId) {
                    $this->_created_num++;
                    unset($product);
                    unset($multiple_values);
                    unset($attributeOcuurance);
                    return $productId;
                } else {
                    $this->customHelper->reportError($this->customHelper->__('Skipped product due to some error while saving # %s', $item->id));
                }
            } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
                $this->customHelper->reportError($e->getMessage());
                $this->customHelper->reportError($e->getAttributeCode());
                $this->customHelper->sendLogEmail($this->logPath);
            }
        } else {
            $this->customHelper->reportError($this->customHelper->__('Attribute set ID # %s is missing. Hence skipped product # %s', $asid, $item->id));
        }
    }
    
    public function createBundleProduct(&$item, $asid)
    {
        $attributeSetModel = Mage::getModel('eav/entity_attribute_set');
        $attributeSetModel->load($asid);
        if (count($attributeSetModel) > 0) {
            $p_status   = ((string) $item->isActive == 'Y') ? 1 : 2;
            $p_taxclass = ((string) $item->isTaxable == 'Y') ? 2 : 0;
            
            $product = new Mage_Catalog_Model_Product();
            $product->setTypeId('bundle');
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
            //New and created data code start
            $format                = 'Y-m-d H:i:s';
            $catalogNewproductDays = Mage::getStoreConfig('catalog/newproduct/days', Mage::app()->getStore());
            if (!empty($catalogNewproductDays) && $catalogNewproductDays >= 0) {
                $currenDateTime = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
                $new_from_date = date($format, strtotime('1 days' . $currenDateTime));
                $new_to_date = date($format, strtotime($catalogNewproductDays . ' days' . $new_from_date));
                $product->setNewsFromDate($new_from_date);
                $product->setNewsToDate($new_to_date);
            }
            if ($product->getCreatedTime == NULL || $product->getUpdateTime() == NULL) {
                $product->setCreatedTime($currenDateTime)->setUpdateTime($currenDateTime);
            }
            //New and created data code end
            $product->setSku((string) $item->id); //Product custom id
            $product->setWebsiteIds(array(
                Mage::app()->getStore(true)->getWebsite()->getId()
            ));
            $product->setStoreIDs(array(
                $this->_store_id
            )); // Default store id .
            $product->setAttributeSetId($asid);
            $product->setData('name', (string) $item->name);
            $product->setPrice((real) $item->price);
            $splAmt = (array) $item->specialPrice->amount;
            if (isset($item->specialPrice->amount) && $item->specialPrice->amount != NULL) {
                if (!empty($splAmt))
                    $product->setSpecialPrice((real) $item->specialPrice->amount); //special price in form 11.22
            }
            
            $fromDate = (array) $item->specialPrice->fromDateTime;
            if (isset($item->specialPrice->fromDateTime) && $item->specialPrice->fromDateTime != NULL) {
                if (!empty($fromDate))
                    $product->setSpecialFromDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->fromDateTime)); //special price from (MM-DD-YYYY)
            }
            
            $toDate = (array) $item->specialPrice->toDateTime;
            if (isset($item->specialPrice->toDateTime) && $item->specialPrice->toDateTime != NULL) {
                if (!empty($toDate))
                    $product->setSpecialToDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->toDateTime)); //special price to (MM-DD-YYYY)
            }
            
            $product->setWeight((real) $item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);
            $product->setDescription((string) $item->longDescription);
            $product->setShortDescription((string) $item->shortDescription);
            $product->setMetaTitle((string) $item->pageTitle);
            $product->setMetaKeyword((string) $item->metaKeywords);
            $product->setMetaDescription((string) $item->metaDescription);
            $product->setExternalImage((string) $item->originalImageUrl);
            $product->setExternalSmallImage((string) $item->largeImageUrl);
            $product->setExternalThumbnail((string) $item->smallImageUrl);
            $product->setShipmentType(0); //shipment type (0 - together, 1 - separately
            try {
                Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
                $product->save();
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->assignProduct($product);
                $stockItem->setData('stock_id', (int) 1);
                $stockItem->setData('use_config_manage_stock', (int) 1);
                $stockItem->setData('min_qty', (int) 0);
                $stockItem->setData('is_decimal_divided', (int) 0);
                $stockItem->setData('qty', (int) 0);
                $stockItem->setData('use_config_min_qty', 1);
                $stockItem->setData('use_config_backorders', 1);
                $stockItem->setData('min_sale_qty', 1);
                $stockItem->setData('use_config_min_sale_qty', 1);
                $stockItem->setData('use_config_max_sale_qty', 1);
                $stockItem->setData('is_in_stock', 1);
                $stockItem->setData('use_config_notify_stock_qty', 1);
                // Added Manage Stock Functionality
                // Manage Stock
                $inventory  = $item->inventory;
                $manageItem = (string) strtoupper($inventory->manageStock);
                if ($manageItem == 'N') {
                    $stockItem->setData('use_config_manage_stock', 0);
                    $stockItem->setData('manage_stock', 0);
                }
                $stockItem->save();
                $stockStatus = Mage::getModel('cataloginventory/stock_status');
                $stockStatus->assignProduct($product);
                $stockStatus->saveProductStatus($product->getId(), 1);
            }
            catch (Exception $e) {
                $this->customHelper->reportError($e->getMessage());
                $this->customHelper->sendLogEmail($this->logPath);
            }
        } else {
            $this->customHelper->reportError($this->customHelper->__('Attribute set ID # %s is missing. Hence skipped product # %s', $asid, $item->id));
        }
    }
    
    public function updateProduct(&$item, $pid)
    {
        $p_status   = ((string) $item->isActive == 'Y') ? 1 : 2;
        $p_taxclass = ((string) $item->isTaxable == 'Y') ? 2 : 0;
        $SKU        = (string) $item->id;
        $product    = Mage::getModel('catalog/product')->loadByAttribute('sku', $SKU);
        
        if ($product) {
            //Product found, so we need to update it in Magento.
            $product->setData('name', (string) $item->name);
            $product->setPrice((real) $item->price);            
            $splAmt = (array) $item->specialPrice->amount;
            if (isset($item->specialPrice->amount) && $item->specialPrice->amount != NULL) {
                if (!empty($splAmt))
                    $product->setSpecialPrice((real) $item->specialPrice->amount); //special price in form 11.22
                else
                    $product->setSpecialPrice("");
            }
            
            $fromDate = (array) $item->specialPrice->fromDateTime;
            if (isset($item->specialPrice->fromDateTime) && $item->specialPrice->fromDateTime != NULL) {
                if (!empty($fromDate))
                    $product->setSpecialFromDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->fromDateTime)); //special price from (MM-DD-YYYY)
                else
                    $product->setSpecialFromDate("");
            }
            
            $toDate = (array) $item->specialPrice->toDateTime;
            if (isset($item->specialPrice->toDateTime) && $item->specialPrice->toDateTime != NULL) {
                if (!empty($toDate))
                    $product->setSpecialToDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->toDateTime)); //special price to (MM-DD-YYYY)
                else
                    $product->setSpecialToDate("");
            }
            
            $product->setWeight((real) $item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);
            $product->setDescription((string) $item->longDescription);
            $product->setShortDescription((string) $item->shortDescription);
            $product->setMetaTitle((string) $item->pageTitle);
            $product->setMetaKeyword((string) $item->metaKeywords);
            $product->setMetaDescription((string) $item->metaDescription);
            $product->setExternalImage((string) $item->originalImageUrl);
            $product->setExternalSmallImage((string) $item->largeImageUrl);
            $product->setExternalThumbnail((string) $item->smallImageUrl);
            $attributeValues      = $item->attributeValues;
            $attributeOcuurance   = array(); //stores no. of occurance for all attributes
            $configAttributeValue = array(); // will use to take value of attributes that ocuures once
            $multiple_values      = array();
            $i                    = 1;
            $model      = Mage::getModel('catalog/resource_eav_attribute');
            foreach ($attributeValues->attribute as $attr) {
                $loadedattr = $model->loadByCode('catalog_product', (string) $attr->id);
                $attr_type = $loadedattr->getFrontendInput();
                if (array_key_exists((string) $attr->id, $attributeOcuurance)) {
                    $multiple_values[(string) $attr->id][]  = (string) $attr->valueDefId;
                    $attributeOcuurance[(string) $attr->id] = (int) $attributeOcuurance[(string) $attr->id] + 1;
                } else {
                    $multiple_values[(string) $attr->id][]  = (string) $attr->valueDefId;
                    $attributeOcuurance[(string) $attr->id] = $i;
                }
                if($attr_type == 'text' || $attr_type == 'textarea'){
                    $multiple_values[(string) $attr->id][]  = (string) $attr->value;
                }
            }
            foreach ($multiple_values as $attribute_code => $attribute_values) {
                $loadedattr = $model->loadByCode('catalog_product', $attribute_code);
                $attr_id    = $loadedattr->getAttributeId(); // attribute id of magento
                if (!$attr_id) {
                    $this->customHelper->reportError($this->customHelper->__('Attribute %s is not available in magento. Hence skipping product # %s', $attribute_code, $item->id));
                    return;
                } else {
                    $attr_type = $loadedattr->getFrontendInput();
                    if ($attr_type == 'select' && count($attribute_values) == 1) {
                        $mapObj    = Mage::getModel('customimport/customimport');
                        $option_id = $mapObj->isOptionExistsInAttribute($attribute_values[0], $attr_id);
                        if ($option_id) {
                            $product->setData($attribute_code, $option_id);
                        } else {
                            $this->customHelper->reportError($this->customHelper->__('Attribute %s has an undefined option value %s. Hence skipping product # %s', $attribute_code, $attribute_values[0], $item->id));
                            return;
                        }
                    } elseif ($attr_type == 'select' && count($attribute_values) > 1) {
                        //multiple values for attribute which is not multiselect
                        $this->customHelper->reportError($this->customHelper->__('Attribute %s can not have multiple values. Hence skipping product # %s', $attribute_code, $item->id));
                        return;
                    } elseif ($attr_type == 'multiselect') {
                        $multivalues = array();
                        foreach ($attribute_values as $value) {
                            $mapObj    = Mage::getModel('customimport/customimport');
                            $option_id = $mapObj->isOptionExistsInAttribute($value, $attr_id);
                            if ($option_id) {
                                $multivalues[] = $option_id;
                            } else {
                                $this->customHelper->reportError($this->customHelper->__('Attribute %s has an undefined option value %s. Hence skipping product # %s', $attribute_code, $value, $item->id));
                                return;
                            }
                        }
                        $product->addData(array(
                            $attribute_code => $multivalues
                        ));
                    } elseif ($attr_type == 'text' || $attr_type == 'textarea') { // if type is text/textarea
                        $product->setData($attribute_code, $attribute_values[1]);
                    } elseif ($attr_type == 'boolean') {
                        $optVal = Mage::getSingleton('customimport/customimport')->getOptVal($attribute_values[0]);
                        if (strtolower($optVal->getValue()) == 'y' || strtolower($optVal->getValue()) == 'yes') {
                            $attOptVal = 1;
                        } else {
                            $attOptVal = 0;
                        }
                        $product->setData($attribute_code, $attOptVal);
                    }
                }
            }

            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
            $productId = $product->save()->getId();
            $this->_updated_num++;
            $stockItem   = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
            $stockItemId = $stockItem->getId();
            $inventory   = $item->inventory;
            $manageItem  = (string) $inventory->manageStock;
            $manageItem  = strtoupper($manageItem);
            if ($manageItem == 'Y') { // if product item exist
                $stockItem->setData('manage_stock', 1);
                $stockItem->setData('is_in_stock', 1);
                $stockItem->setData('qty', $inventory->atp);
                if (strtoupper($inventory->allowBackorders) == 'Y') { // if back order allowed
                    $stockItem->setData('use_config_backorders', 0);
                    $stockItem->setData('backorders', 1);
                }
                if (strtoupper($inventory->allowBackorders) == 'N') { // if back order allowed
                    $stockItem->setData('use_config_backorders', 0);
                    $stockItem->setData('backorders', 0);
                }
            } else {
                $stockItem->setData('use_config_manage_stock', 0);
                $stockItem->setData('manage_stock', 0); // manage stock to no
            }

            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
            $stockItem->save();
            unset($product);
        } else {
            $this->customHelper->reportError($this->customHelper->__('Skipped product due to some error while save : %s', $item->id));
        }
    }
    
    public function updateBundleProduct(&$item, $pid)
    {
        $p_status   = ((string) $item->isActive == 'Y') ? 1 : 2;
        $p_taxclass = ((string) $item->isTaxable == 'Y') ? 2 : 0;
        $SKU     = (string) $item->id;
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $SKU);
        if ($product) {
            $product->setData('name', (string) $item->name);
            $product->setPrice((real) $item->price);
            $splAmt = (array) $item->specialPrice->amount;
            if (isset($item->specialPrice->amount) && $item->specialPrice->amount != NULL) {
                if (!empty($splAmt))
                    $product->setSpecialPrice((real) $item->specialPrice->amount); //special price in form 11.22
                else
                    $product->setSpecialPrice("");
            }
            
            $fromDate = (array) $item->specialPrice->fromDateTime;
            if (isset($item->specialPrice->fromDateTime) && $item->specialPrice->fromDateTime != NULL) {
                if (!empty($fromDate))
                    $product->setSpecialFromDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->fromDateTime)); //special price from (MM-DD-YYYY)
                else
                    $product->setSpecialFromDate("");
            }
            
            $toDate = (array) $item->specialPrice->toDateTime;
            if (isset($item->specialPrice->toDateTime) && $item->specialPrice->toDateTime != NULL) {
                if (!empty($toDate))
                    $product->setSpecialToDate(Mage::helper('customimport')->getCurrentLocaleDateTime($item->specialPrice->toDateTime)); //special price to (MM-DD-YYYY)
                else
                    $product->setSpecialToDate("");
            }
            
            $product->setWeight((real) $item->weight);
            $product->setStatus($p_status);
            $product->setTaxClassId($p_taxclass);
            $product->setDescription((string) $item->longDescription);
            $product->setShortDescription((string) $item->shortDescription);
            $product->setMetaTitle((string) $item->pageTitle);
            $product->setMetaKeyword((string) $item->metaKeywords);
            $product->setMetaDescription((string) $item->metaDescription);
            $product->setExternalImage((string) $item->originalImageUrl);
            $product->setExternalSmallImage((string) $item->largeImageUrl);
            $product->setExternalThumbnail((string) $item->smallImageUrl);
            $product->setShipmentType(0); //shipment type (0 - together, 1 - separately
            
            try {
                $product->save();
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                $stockItem->assignProduct($product);
                $inventory  = $item->inventory;
                $manageItem = (string) $inventory->manageStock;
                if ($manageItem == 'N') {
                    $stockItem->setData('use_config_manage_stock', 0);
                    $stockItem->setData('manage_stock', 0);
                } else {
                    $stockItem->setData('use_config_manage_stock', 1);
                    $stockItem->setData('manage_stock', 1);
                }
                $stockItem->save();
                $this->updateBundleItems($pid);
            }
            catch (Exception $e) {
                $this->customHelper->reportError($this->customHelper->__('Bunduled Prduct not updated'));
                $this->customHelper->reportError($e->getMessage());
                $this->customHelper->sendLogEmail($this->logPath);
            }
        }
    }
    
    public function updateBundleItems($pid)
    {
        $bundled = Mage::getModel('catalog/product');
        $bundled->load($pid);
        $selectionCollection = $bundled->getTypeInstance(true)->getSelectionsCollection($bundled->getTypeInstance(true)->getOptionsIds($bundled), $bundled);
        
        foreach ($selectionCollection as $option) {
            $optionModel = Mage::getModel('bundle/option');
            $optionModel->setId($option->option_id);
            $optionModel->delete();
        }
    }
    
    public function getAttributeSetId($attrSetName)
    {
        $attributeSetId = Mage::getModel('eav/entity_attribute_set')->getCollection()->setEntityTypeFilter(4) // 4is entity type id...4= product
            ->addFieldToFilter('attribute_set_name', $attrSetName)->getFirstItem()->getAttributeSetId();
        return $attributeSetId;
    }
    
    public function getItemIds($item, $Currfilepath = null, $Errfilepath = null)
    {
        $entityTypeId      = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $attributeSetModel = Mage::getModel('eav/entity_attribute_set');
        $attributeSetId    = 0;
        $attributeSetName  = (string) $item->attributeSetId;
        if (empty($attributeSetName)) {
            $attributeSetName = "Default";
        }
        
        if ($attributeSetName != "Default") {
            $mapObj                              = Mage::getModel('customimport/customimport');
            $externalAttrsetMappingInfoAttrSetId = $mapObj->getAttributeSetIdByExternalId($attributeSetName);
            if ($externalAttrsetMappingInfoAttrSetId == "") {
                $attributeSetId = 0;
            } else {
                $set = $attributeSetModel->load($externalAttrsetMappingInfoAttrSetId);
                if (count($set->getData()) > 0) {
                    $attributeSetId = $externalAttrsetMappingInfoAttrSetId;
                }
            }
        } else {
            $attributeSetId = $attributeSetModel->getCollection()->setEntityTypeFilter($entityTypeId)->addFieldToFilter('attribute_set_name', $attributeSetName)->getFirstItem()->getAttributeSetId();
        }
        
        if ($attributeSetId) {
            $sku        = (string) $item->id;
            $curitemids = (isset($this->_curitemids["sku"]) ? $this->_curitemids["sku"] : null);
            if ($sku != $curitemids) {
                $cids = $this->getProductIds($sku);
                if ($cids !== false) {
                    $this->_curitemids = $cids;
                } else {
                    $this->_curitemids = array(
                        "pid" => null,
                        "sku" => $sku,
                        "asid" => isset($attributeSetId) ? $attributeSetId : null
                    );
                }
                $this->onNewSku($sku, ($cids !== false));
                unset($cids);
            } else {
                $this->onSameSku($sku);
            }
            return $this->_curitemids;
        } else {
            $this->customHelper->reportInfo($this->customHelper->__("Attribute set %s is not found", $attributeSetName));
            return false;
        }
    }
    
    public function getProductIds($sku)
    {
        $toReturn          = array();
        $product1          = Mage::getModel('catalog/product');
        $productCollection = $product1->getCollection()->addAttributeToSelect('sku', 'entity_id', 'attribute_set_id')->addAttributeToFilter('sku', $sku)->load();
        if (count($productCollection) > 0) {
            foreach ($productCollection as $product) {
                $productArr       = $product->getData();
                $toReturn['pid']  = $productArr['entity_id'];
                $toReturn['asid'] = $productArr['attribute_set_id'];
                return $toReturn;
            }
        } else {
            return false;
        }
    }
    
    public function clearOptCache()
    {
        unset($this->_optidcache);
        $this->_optidcache = array();
    }
    
    public function onNewSku($sku, $existing)
    {
        $this->clearOptCache();
        //only assign values to store 0 by default in create mode for new sku, for store related options
        if (!$existing) {
            $this->_dstore = array(
                0
            );
        } else {
            $this->_dstore = array();
        }
        $this->_same = false;
    }
    
    public function onSameSku($sku)
    {
        unset($this->_dstore);
        $this->_dstore = array();
        $this->_same   = true;
    }
    
    public function createLog($msg)
    {
        $this->_log_array[] = $msg;
    }
    
    public function lookup($listType)
    {
        $t0              = microtime(true);
        $count           = count($listType);
        $this->_item_num = $count;
        $t1              = microtime(true);
        $time            = $t1 - $t0;
        $this->customHelper->reportInfo($this->customHelper->__('Found %s records', $count));
    }
    
    /*
     * create category
     * */
    protected function createCategory($item)
    {
        $default_root_category = $this->_default_category_id;
        $parent_id             = ((string) $item->isRoot == 'Y') ? 1 : $default_root_category;
        $isActive              = ((string) $item->isActive == 'Y') ? 1 : 0;
        
        $category        = Mage::getModel('catalog/category')->setStoreId($this->_store_id);
        $parent_category = $this->_initCategory($parent_id, $this->_store_id);
        if (!$parent_category->getId()) {
            $this->customHelper->reportError($this->customHelper->__('parent category not found'));
        } else {
            $category->addData(array(
                'path' => implode('/', $parent_category->getPathIds())
            ));
            /* @var $validator Mage_Catalog_Model_Api2_Product_Validator_Product */
            $category->setParentId($parent_category->getId());
            $category->setAttributeSetId($category->getDefaultAttributeSetId());
            $category->setData('name', (string) $item->name);
            $category->setData('include_in_menu', 1);
            $category->setData('meta_title', (string) $item->pageTitle);
            $category->setData('meta_keywords', (string) $item->metaKeywords);
            $category->setData('meta_description', (string) $item->metaDescription);
            $category->setData('description', (string) $item->description);
            $category->setData('available_sort_by', 'position');
            $category->setData('default_sort_by', 'position');
            $category->setData('is_active', $isActive);
            $category->setData('is_anchor', 1);
            $category->setData('external_id', (string) $item->id);
            $category->setData('external_cat_image', (string) $item->imageUrl);
            try {
                $validate = $category->validate();
                if ($validate !== true) {
                    foreach ($validate as $code => $error) {
                        if ($error === true) {
                            $this->customHelper->reportError($this->customHelper->__('Attribute "%s" is required', $code));
                            $this->customHelper->sendLogEmail($this->logPath);
                            Mage::throwException($this->customHelper->__->__('Attribute "%s" is required.', $code));
                        } else {
                            $this->customHelper->reportError($error);
                            $this->customHelper->sendLogEmail($this->logPath);
                            Mage::throwException($error);
                        }
                    }
                }
                $category->save();
            }
            catch (Exception $e) {
                $this->customHelper->reportError($e->getMessage());
                $this->customHelper->sendLogEmail($this->logPath);
            }
        }
    }
    
    protected function updateCategory($item, $categoryId)
    {
        $category = Mage::getModel('catalog/category')->load($categoryId);
        $isActive = ((string) $item->isActive == 'Y') ? 1 : 0;
        $category->setData('name', (string) $item->name);
        $category->setData('include_in_menu', 1);
        $category->setData('meta_title', (string) $item->pageTitle);
        $category->setData('meta_keywords', (string) $item->metaKeywords);
        $category->setData('meta_description', (string) $item->metaDescription);
        $category->setData('description', (string) $item->description);
        $category->setData('is_active', $isActive);
        $category->setData('is_anchor', 1);
        $category->setData('external_cat_image', (string) $item->imageUrl);
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $category->save();
    }
    
    /**
     * main function to import category
     */
    protected function importCategory($item)
    {
        $externalId = (string) $item->id;
        $externall  = $this->checkExternalId($externalId);
        
        if ($externall) {
            if (count($externall) == 1) {
                //update already existing category with this id
                reset($externall); //to take 1st key of array
                $first_key = key($externall);
                $this->updateCategory($item, $first_key);
                $this->_updated_num++;
            } else {
                foreach ($externall as $systemCatid => $v) {
                    $this->updateCategory($item, $systemCatid);
                }
            }
        } else {
            // category is not available hence create new
            if (count($externall) == 0) {
                $this->createCategory($item);
                $this->_created_num++;
            }
        }
    }
    
    /**
     * checks for a category existance using external id
     * @param external id of category
     */
    protected function checkExternalId($externalId)
    {
        $catsWithCustomAttr = array();
        $collection         = Mage::getModel('catalog/category')->getCollection();
        $collection->addAttributeToSelect("external_id");
        //Do a left join, and get values that aren't null - you could add other conditions in the second parameter also (check out all options in the _getConditionSql method of lib/Varien/Data/Collection/Db.php
        $collection->addAttributeToFilter('external_id', $externalId, 'left');
        foreach ($collection as $category) {
            $catsWithCustomAttr[$category->getId()] = $category->getExternalId();
        }
        return $catsWithCustomAttr;
    }
    
    /**
     * loads a category, if exists
     * @param category id
     */
    protected function _initCategory($categoryId, $store = null)
    {
        try {
            $category = Mage::getModel('catalog/category')->setStoreId($store)->load($categoryId);
            if (!$category->getId()) {
                $errorMsg = $this->customHelper->__('Parent category %s is not available', $categoryId);
                $this->customHelper->reportError($errorMsg);
                $this->customHelper->sendLogEmail($this->logPath);
                Mage::throwException($errorMsg);
            }
        }
        catch (Exception $e) {
            $this->customHelper->reportError($e->getMessage());
            $this->customHelper->sendLogEmail($this->logPath);
        }
        return $category;
    }
    
    public function getCurrentRow()
    {
        return $this->_current_row;
    }
    
    public function setCurrentRow($cnum)
    {
        $this->_current_row = $cnum;
    }
    
    /*
     * associates a product with a category
     * */
    public function associateProductToCategory($productDetail, $catId) //actual id of category
    {
        $cat_api    = new Mage_Catalog_Model_Category_Api;
        $newProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', (string) $productDetail->id);
        if ($newProduct) {
            $category  = Mage::getModel('catalog/category')->setStoreId($this->_store_id)->load($catId);
            $positions = $category->getProductsPosition();
            $productDetail->position;
            if ((string) $productDetail->isActive == 'Y') {
                // assignproduct is default function of magento, to assign product in a category
                $cat_api->assignProduct($catId, $newProduct->getId(), (real) $productDetail->position);
            } else {
                if (isset($positions[$newProduct->getId()])) {
                    // removeProduct is default function of magento, to remove product from a category
                    $cat_api->removeProduct($catId, $newProduct->getId());
                }
            }
        } else {
            $this->customHelper->reportError($this->customHelper->__('Product does not exists %s', $productDetail->id));
        }
    }
    
    public function makeAttributeConfigurable($superattribute_array)
    {
        $result = false;
        $model  = Mage::getModel('catalog/resource_eav_attribute');
        
        foreach ($superattribute_array as $attribute_id) {
            $attr    = $model->loadByCode('catalog_product', $attribute_id);
            $attr_id = $attr->getAttributeId();
            if ($attr_id) {
                if ($attr_configurable = $attr->getIsConfigurable()) {
                    $result = true;
                } else {
                    $attribute_type  = $attr->getFrontendInput();
                    $_attribute_data = array();
                    if ($attribute_type == 'select') {
                        $_attribute_data['is_configurable'] = 1;
                        $model->addData($_attribute_data);
                        $attr->save();
                        $result = true;
                    } else {
                        $this->customHelper->reportError($this->customHelper->__('Attribute %s can not be updated to become superattribute', $attribute_id));
                        $result = false;
                    }
                }
            } else {
                $this->customHelper->reportError($this->customHelper->__('Attribute %s does not exists', $attribute_id));
                $result = false;
            }
        }
        return $result;
    }
    
    public function createAttribute($attribute)
    {
        $mapobj         = Mage::getModel('customimport/customimport');
        $attr_values    = (array) $attribute->values;
        $attribute_type = 'select';
        if (isset($attribute->type)) {
            $att_type             = (string) $attribute->type;
            $magento_array_values = array(
                'text',
                'textarea',
                'date',
                'boolean',
                'multiselect',
                'select',
                'price',
                'media_image',
                'weee'
            );
            if (in_array($att_type, $magento_array_values)) {
                $attribute_type = $att_type;
            }
        }
        $count_value     = count($attr_values['valueDef']);
        $_attribute_data = array(
            'attribute_code' => (string) $attribute->id,
            'backend_model' => ($attribute_type == 'multiselect' ? 'eav/entity_attribute_backend_array' : NULL),
            'is_global' => '1',
            'frontend_input' => $attribute_type, //'text/select',
            'default_value_text' => '',
            'default_value_yesno' => '0',
            'default_value_date' => '',
            'default_value_textarea' => '',
            'is_unique' => '0',
            'is_required' => '0',
            'apply_to' => array(
                'simple',
                'configurable'
            ), //array('grouped')
            'is_configurable' => '0',
            'is_searchable' => '0',
            'is_visible_in_advanced_search' => '0',
            'is_comparable' => '0',
            'is_used_for_price_rules' => '0',
            'is_wysiwyg_enabled' => '0',
            'is_html_allowed_on_front' => '1',
            'is_visible_on_front' => '1',
            'used_in_product_listing' => '0',
            'used_for_sort_by' => '0',
            'frontend_label' => array(
                (string) $attribute->name
            )
        );
        
        $model   = Mage::getModel('catalog/resource_eav_attribute');
        $attr    = $model->loadByCode('catalog_product', (string) $attribute->id);
        $attr_id = $attr->getAttributeId();
        
        if ($attr_id != '') {
            $attr->addData($_attribute_data);
            $option['attribute_id'] = $attr_id;
            if ($count_value > 0 && ($attribute_type == 'select' || $attribute_type == 'multiselect' || $attribute_type == 'boolean')) {
                for ($i = 0; $i < $count_value; $i++) {
                    $attrdet  = $attr_values['valueDef'][$i];
                    $optionId = $mapobj->isOptionExistsInAttribute($attrdet->id, $attr_id);
                    if (!isset($optionId)) {
                        $option['value']['option_' . $i][0]   = $attrdet->value;
                        $option['order']['option_' . $i]      = $attrdet->position;
                        $option['externalid']['option_' . $i] = $attrdet->id;
                    } else {
                        $option['value'][$optionId][0]   = $attrdet->value;
                        $option['order'][$optionId]      = $attrdet->position;
                        $option['externalid'][$optionId] = $attrdet->id;
                    }
                    $attr->setOption($option);
                }
            }
            $attr->save();
        } else {
            if (!isset($_attribute_data['is_configurable'])) {
                $_attribute_data['is_configurable'] = 0;
            }
            if (!isset($_attribute_data['is_filterable'])) {
                $_attribute_data['is_filterable'] = 0;
            }
            if (!isset($_attribute_data['is_filterable_in_search'])) {
                $_attribute_data['is_filterable_in_search'] = 0;
            }
            if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
                $_attribute_data['backend_type'] = $model->getBackendTypeByInput($_attribute_data['frontend_input']);
            }
            $defaultValueField = $model->getDefaultValueByInput($_attribute_data['frontend_input']);
            if ($defaultValueField) {
                $_attribute_data['default_value'] = $this->getRequest()->getParam($defaultValueField);
            }
            $model->addData($_attribute_data);
            $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
            $model->setIsUserDefined(1);
            try {
                $model->save();
                $attribute_code = (string) $attribute->id;
                unset($model);
                if ($count_value > 0) {
                    $model                  = Mage::getModel('catalog/resource_eav_attribute');
                    $attr                   = $model->loadByCode('catalog_product', (string) $attribute->id);
                    $attr_id                = $attr->getAttributeId();
                    $option['attribute_id'] = $attr_id;
                    
                    for ($i = 0; $i < $count_value; $i++) {
                        $attrdet  = $attr_values['valueDef'][$i];
                        $optionId = $mapobj->isOptionExistsInAttribute($attrdet->id, $attr_id);
                        if (!isset($optionId)) {
                            $option['value']['option_' . $i][0]   = $attrdet->value;
                            $option['order']['option_' . $i]      = $attrdet->position;
                            $option['externalid']['option_' . $i] = $attrdet->id;
                        } else {
                            $option['value'][$optionId][0]   = $attrdet->value;
                            $option['order'][$optionId]      = $attrdet->position;
                            $option['externalid'][$optionId] = $attrdet->id;
                        }
                    }
                    $attr->setOption($option);
                    $attr->save();
                }
            }
            catch (Exception $e) {
                $this->customHelper->reportError($this->customHelper->__('Sorry, error occured while trying to save the attribute. Error: %s', $e->getMessage()));
                $this->customHelper->sendLogEmail($this->logPath);
            }
        }
    }
    
    public function getEntityTypeId()
    {
        return $entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
    }
    
    public function getInstaller()
    {
        return new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');
    }
    
    private function _getMageId($attriextid)
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select     = $connection->select()->from('external_attrsetmapping_info', 'magento_id')->where('external_id=?', "$attriextid");
        $rowArray   = $connection->fetchRow($select);
        return $rowArray['magento_id'];
    }
    
    private function _hideVisibility($proid)
    {
        $product = Mage::getModel('catalog/product');
        $product->load($proid);
        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        $product->save();
    }

    private function _bothVisibility($proid)
    {
        $product = Mage::getModel('catalog/product');
        $product->load($proid);
        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        $product->save();
    }

}
?>
